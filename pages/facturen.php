<?php
// Facturen (contributie)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $facturen = yaml_read('facturen');

    if ($_POST['action'] === 'add') {
        $facturen[] = [
            'id' => generate_id(),
            'nummer' => next_factuurnummer(),
            'lid_id' => $_POST['lid_id'],
            'datum' => $_POST['datum'],
            'vervaldatum' => date('Y-m-d', strtotime($_POST['datum'] . ' +30 days')),
            'omschrijving' => trim($_POST['omschrijving']),
            'bedrag' => (float)$_POST['bedrag'],
            'status' => 'open', // open, deels, betaald
            'betaald' => 0.0,
            'aangemaakt' => date('Y-m-d H:i:s'),
        ];
        yaml_write('facturen', $facturen);
        flash('Factuur aangemaakt: ' . $facturen[count($facturen)-1]['nummer']);
    }

    if ($_POST['action'] === 'bulk') {
        $leden = yaml_read('leden');
        $bedrag = (float)$_POST['bedrag'];
        $omschrijving = trim($_POST['omschrijving']);
        $datum = $_POST['datum'];
        $count = 0;

        foreach ($leden as $lid) {
            if (!($lid['actief'] ?? true)) continue;
            $facturen[] = [
                'id' => generate_id(),
                'nummer' => next_factuurnummer(),
                'lid_id' => $lid['id'],
                'datum' => $datum,
                'vervaldatum' => date('Y-m-d', strtotime($datum . ' +30 days')),
                'omschrijving' => $omschrijving,
                'bedrag' => $bedrag,
                'status' => 'open',
                'betaald' => 0.0,
                'aangemaakt' => date('Y-m-d H:i:s'),
            ];
            // Re-read to get correct next nummer
            yaml_write('facturen', $facturen);
            $count++;
        }
        flash("{$count} facturen aangemaakt voor alle leden.");
    }

    if ($_POST['action'] === 'delete') {
        $facturen = array_filter($facturen, fn($f) => $f['id'] !== $_POST['id']);
        yaml_write('facturen', array_values($facturen));
        flash('Factuur verwijderd.');
    }

    header('Location: ?page=facturen');
    exit;
}

$facturen = yaml_read('facturen');
$leden = yaml_read('leden');
$leden_map = [];
foreach ($leden as $l) $leden_map[$l['id']] = $l['naam'];

usort($facturen, fn($a, $b) => strcmp($b['datum'], $a['datum']));
?>

<h1>Facturen</h1>

<div class="card">
    <h2>Contributie facturen aanmaken (alle leden)</h2>
    <form method="post">
        <input type="hidden" name="action" value="bulk">
        <div class="form-row">
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" value="Contributie <?= current_boekjaar() ?>" required>
            </div>
            <div class="form-group">
                <label>Bedrag per lid (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-success" onclick="return confirm('Facturen aanmaken voor alle <?= count($leden) ?> leden?')">Alle leden factureren</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Enkele factuur</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Lid</label>
                <select name="lid_id" required>
                    <option value="">-- Kies lid --</option>
                    <?php foreach ($leden as $lid): ?>
                    <option value="<?= $lid['id'] ?>"><?= htmlspecialchars($lid['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
            </div>
            <div class="form-group">
                <label>Bedrag (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Aanmaken</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Overzicht facturen</h2>
    <?php if (empty($facturen)): ?>
        <p>Nog geen facturen.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Datum</th>
                    <th>Lid</th>
                    <th>Omschrijving</th>
                    <th class="text-right">Bedrag</th>
                    <th class="text-right">Betaald</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturen as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['nummer']) ?></strong></td>
                    <td><?= format_date($f['datum']) ?></td>
                    <td><?= htmlspecialchars($leden_map[$f['lid_id']] ?? '?') ?></td>
                    <td><?= htmlspecialchars($f['omschrijving']) ?></td>
                    <td class="text-right"><?= format_money($f['bedrag']) ?></td>
                    <td class="text-right"><?= format_money($f['betaald']) ?></td>
                    <td>
                        <span class="badge badge-<?= $f['status'] ?>"><?= $f['status'] ?></span>
                    </td>
                    <td>
                        <a href="factuur_pdf.php?id=<?= $f['id'] ?>" class="btn btn-primary btn-small">PDF</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Factuur verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
