<?php
// Bankboekingen

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $boekingen = yaml_read('boekingen');

    if ($_POST['action'] === 'add') {
        $boeking = [
            'id' => generate_id(),
            'datum' => $_POST['datum'],
            'omschrijving' => trim($_POST['omschrijving']),
            'bedrag' => (float)$_POST['bedrag'],
            'type' => $_POST['type'], // bij of af
            'aangemaakt' => date('Y-m-d H:i:s'),
        ];

        // Koppeling aan factuur
        if (!empty($_POST['factuur_id'])) {
            $boeking['factuur_id'] = $_POST['factuur_id'];

            // Update factuur betaald-status
            $facturen = yaml_read('facturen');
            foreach ($facturen as &$f) {
                if ($f['id'] === $_POST['factuur_id']) {
                    $f['betaald'] += abs((float)$_POST['bedrag']);
                    if ($f['betaald'] >= $f['bedrag']) {
                        $f['status'] = 'betaald';
                        $f['betaald'] = $f['bedrag'];
                    } else {
                        $f['status'] = 'deels';
                    }
                    break;
                }
            }
            unset($f);
            yaml_write('facturen', $facturen);
        }

        // Koppeling aan kosten
        if (!empty($_POST['kosten_id'])) {
            $boeking['kosten_id'] = $_POST['kosten_id'];
        }

        $boekingen[] = $boeking;
        yaml_write('boekingen', $boekingen);
        flash('Bankboeking toegevoegd.');
    }

    if ($_POST['action'] === 'delete') {
        // Als gekoppeld aan factuur, ongedaan maken
        $del = null;
        foreach ($boekingen as $b) {
            if ($b['id'] === $_POST['id']) { $del = $b; break; }
        }
        if ($del && !empty($del['factuur_id'])) {
            $facturen = yaml_read('facturen');
            foreach ($facturen as &$f) {
                if ($f['id'] === $del['factuur_id']) {
                    $f['betaald'] = max(0, $f['betaald'] - abs($del['bedrag']));
                    $f['status'] = $f['betaald'] <= 0 ? 'open' : ($f['betaald'] >= $f['bedrag'] ? 'betaald' : 'deels');
                    break;
                }
            }
            unset($f);
            yaml_write('facturen', $facturen);
        }

        $boekingen = array_filter($boekingen, fn($b) => $b['id'] !== $_POST['id']);
        yaml_write('boekingen', array_values($boekingen));
        flash('Boeking verwijderd.');
    }

    header('Location: ?page=boekingen');
    exit;
}

$boekingen = yaml_read('boekingen');
$facturen = yaml_read('facturen');
$kosten = yaml_read('kosten');
$leden = yaml_read('leden');

$leden_map = [];
foreach ($leden as $l) $leden_map[$l['id']] = $l['naam'];

$facturen_map = [];
foreach ($facturen as $f) {
    $lid_naam = $leden_map[$f['lid_id']] ?? '?';
    $facturen_map[$f['id']] = $f['nummer'] . ' - ' . $lid_naam;
}

$kosten_map = [];
foreach ($kosten as $k) $kosten_map[$k['id']] = $k['datum'] . ' - ' . $k['omschrijving'];

usort($boekingen, fn($a, $b) => strcmp($b['datum'], $a['datum']));

// Open facturen
$open_facturen = array_filter($facturen, fn($f) => $f['status'] !== 'betaald');
?>

<h1>Bankboekingen</h1>

<div class="card">
    <h2>Nieuwe boeking</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Bij / Af</label>
                <select name="type" id="boeking-type">
                    <option value="bij">Bij (ontvangst)</option>
                    <option value="af">Af (betaling)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Bedrag (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Koppel aan factuur (optioneel)</label>
                <select name="factuur_id">
                    <option value="">-- Geen koppeling --</option>
                    <?php foreach ($open_facturen as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($facturen_map[$f['id']] ?? $f['nummer']) ?> (<?= format_money($f['bedrag'] - $f['betaald']) ?> open)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Koppel aan kosten (optioneel)</label>
                <select name="kosten_id">
                    <option value="">-- Geen koppeling --</option>
                    <?php foreach ($kosten as $k): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($kosten_map[$k['id']]) ?> (<?= format_money($k['bedrag']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <button type="submit" class="btn btn-primary">Boeken</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Overzicht boekingen</h2>
    <?php if (empty($boekingen)): ?>
        <p>Nog geen boekingen.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Omschrijving</th>
                    <th>Koppeling</th>
                    <th class="text-right">Bij</th>
                    <th class="text-right">Af</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $saldo_bij = 0; $saldo_af = 0; foreach ($boekingen as $b):
                    if ($b['type'] === 'bij') $saldo_bij += $b['bedrag'];
                    else $saldo_af += $b['bedrag'];

                    $koppeling = '';
                    if (!empty($b['factuur_id'])) $koppeling = 'Factuur: ' . ($facturen_map[$b['factuur_id']] ?? '?');
                    if (!empty($b['kosten_id'])) $koppeling = 'Kosten: ' . ($kosten_map[$b['kosten_id']] ?? '?');
                ?>
                <tr>
                    <td><?= format_date($b['datum']) ?></td>
                    <td><?= htmlspecialchars($b['omschrijving']) ?></td>
                    <td><?= htmlspecialchars($koppeling) ?></td>
                    <td class="text-right"><?= $b['type'] === 'bij' ? format_money($b['bedrag']) : '' ?></td>
                    <td class="text-right"><?= $b['type'] === 'af' ? format_money($b['bedrag']) : '' ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Boeking verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Totaal</strong></td>
                    <td class="text-right"><strong><?= format_money($saldo_bij) ?></strong></td>
                    <td class="text-right"><strong><?= format_money($saldo_af) ?></strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Saldo</strong></td>
                    <td colspan="2" class="text-right"><strong><?= format_money($saldo_bij - $saldo_af) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>
