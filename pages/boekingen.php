<?php
// Bankboekingen

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── ABN AMRO .TAB Import ──────────────────────────────────────────
    if ($_POST['action'] === 'import_tab') {
        if (!empty($_FILES['tabfile']['tmp_name']) && $_FILES['tabfile']['error'] === UPLOAD_ERR_OK) {
            $lines = file($_FILES['tabfile']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $bestaande = yaml_read('boekingen');
            $bestaande_refs = [];
            foreach ($bestaande as $b) {
                if (!empty($b['bank_ref'])) $bestaande_refs[$b['bank_ref']] = true;
            }

            $imported = 0;
            $skipped = 0;

            foreach ($lines as $line) {
                $cols = explode("\t", $line);
                if (count($cols) < 8) continue;

                $datum_raw = trim($cols[2]);
                $bedrag_raw = trim($cols[6]);
                $omschrijving = trim($cols[7]);

                // Parse datum YYYYMMDD -> Y-m-d
                $datum = substr($datum_raw, 0, 4) . '-' . substr($datum_raw, 4, 2) . '-' . substr($datum_raw, 6, 2);

                // Parse bedrag: Dutch format (comma decimal), negative = af
                $bedrag_float = (float)str_replace(',', '.', str_replace('.', '', $bedrag_raw));
                $type = $bedrag_float >= 0 ? 'bij' : 'af';
                $bedrag = abs($bedrag_float);

                // Maak een unieke referentie-hash van de regel
                $bank_ref = md5($datum_raw . '|' . $bedrag_raw . '|' . $omschrijving);

                // Controleer of deze boeking al geïmporteerd is
                if (isset($bestaande_refs[$bank_ref])) {
                    $skipped++;
                    continue;
                }

                // Compact omschrijving: verwijder overtollige spaties
                $omschrijving = preg_replace('/\s{2,}/', ' ', $omschrijving);

                $boeking = [
                    'id' => generate_id(),
                    'datum' => $datum,
                    'omschrijving' => $omschrijving,
                    'bedrag' => $bedrag,
                    'type' => $type,
                    'bank_ref' => $bank_ref,
                    'aangemaakt' => date('Y-m-d H:i:s'),
                ];

                yaml_save('boekingen', $boeking);
                $bestaande_refs[$bank_ref] = true;
                $imported++;
            }

            if ($skipped > 0) {
                flash("{$imported} boekingen geïmporteerd, {$skipped} duplicaten overgeslagen.");
            } else {
                flash("{$imported} boekingen geïmporteerd.");
            }
        } else {
            flash('Geen bestand geüpload of fout bij uploaden.', 'error');
        }

        header('Location: ?page=boekingen');
        exit;
    }

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
            $f = yaml_find('facturen', $_POST['factuur_id']);
            if ($f) {
                $f['betaald'] += abs((float)$_POST['bedrag']);
                if ($f['betaald'] >= $f['bedrag']) {
                    $f['status'] = 'betaald';
                    $f['betaald'] = $f['bedrag'];
                } else {
                    $f['status'] = 'deels';
                }
                yaml_save('facturen', $f);
            }
        }

        // Koppeling aan kosten
        if (!empty($_POST['kosten_id'])) {
            $boeking['kosten_id'] = $_POST['kosten_id'];
        }

        yaml_save('boekingen', $boeking);
        flash('Bankboeking toegevoegd.');
    }

    if ($_POST['action'] === 'startsaldo') {
        yaml_save('instellingen', [
            'id' => 'startsaldo',
            'bedrag' => (float)$_POST['bedrag'],
        ]);
        flash('Startsaldo opgeslagen.');
        header('Location: ?page=boekingen');
        exit;
    }

    if ($_POST['action'] === 'koppel_kosten') {
        $boeking = yaml_find('boekingen', $_POST['id']);
        if ($boeking) {
            if (!empty($_POST['kosten_id'])) {
                $boeking['kosten_id'] = $_POST['kosten_id'];
            } else {
                unset($boeking['kosten_id']);
            }
            yaml_save('boekingen', $boeking);
            flash('Koppeling bijgewerkt.');
        }
        header('Location: ?page=boekingen');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        // Als gekoppeld aan factuur, ongedaan maken
        $del = yaml_find('boekingen', $_POST['id']);
        if ($del && !empty($del['factuur_id'])) {
            $f = yaml_find('facturen', $del['factuur_id']);
            if ($f) {
                $f['betaald'] = max(0, $f['betaald'] - abs($del['bedrag']));
                $f['status'] = $f['betaald'] <= 0 ? 'open' : ($f['betaald'] >= $f['bedrag'] ? 'betaald' : 'deels');
                yaml_save('facturen', $f);
            }
        }

        yaml_delete('boekingen', $_POST['id']);
        flash('Boeking verwijderd.');
    }

    header('Location: ?page=boekingen');
    exit;
}

$boekingen = yaml_read('boekingen');
$facturen = yaml_read('facturen');
$kosten = yaml_read('kosten');
$leden = yaml_read('leden');

$startsaldo_record = yaml_find('instellingen', 'startsaldo');
$startsaldo = $startsaldo_record ? (float)$startsaldo_record['bedrag'] : 0.0;

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
    <h2>ABN AMRO import (.TAB)</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_tab">
        <div class="form-row">
            <div class="form-group">
                <label>Selecteer .TAB bestand</label>
                <input type="file" name="tabfile" accept=".tab,.TAB" required>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary">Importeren</button>
            </div>
        </div>
        <p><small>Duplicaten worden automatisch overgeslagen op basis van datum, bedrag en omschrijving.</small></p>
    </form>
</div>

<div class="card">
    <h2>Startsaldo</h2>
    <form method="post">
        <input type="hidden" name="action" value="startsaldo">
        <div class="form-row">
            <div class="form-group">
                <label>Beginsaldo bank (&euro;)</label>
                <input type="number" name="bedrag" step="0.01" value="<?= $startsaldo ?>" required>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary">Opslaan</button>
            </div>
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
                ?>
                <tr>
                    <td><?= format_date($b['datum']) ?></td>
                    <td><?= htmlspecialchars($b['omschrijving']) ?></td>
                    <td>
                        <?php if (!empty($b['factuur_id'])): ?>
                            Factuur: <?= htmlspecialchars($facturen_map[$b['factuur_id']] ?? '?') ?>
                        <?php endif; ?>
                        <?php
                        $matching_kosten = array_filter($kosten, fn($k) => abs($k['bedrag'] - $b['bedrag']) < 0.01);
                        $has_kosten_link = !empty($b['kosten_id']);
                        ?>
                        <?php if ($has_kosten_link || !empty($matching_kosten)): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="koppel_kosten">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <select name="kosten_id" onchange="this.form.submit()">
                                <option value="">-- Geen kosten --</option>
                                <?php foreach ($matching_kosten as $k):
                                    $label = $k['datum'] . ' ' . mb_strimwidth($k['omschrijving'], 0, 25, '…');
                                ?>
                                <option value="<?= $k['id'] ?>"<?= ($has_kosten_link && $b['kosten_id'] === $k['id']) ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </td>
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
                    <td colspan="2" class="text-right"><strong><?= format_money($startsaldo + $saldo_bij - $saldo_af) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>
