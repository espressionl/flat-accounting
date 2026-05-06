<?php
// Kosten vastleggen

$bonnetjes_dir = __DIR__ . '/../uploads/bonnetjes';
if (!is_dir($bonnetjes_dir)) mkdir($bonnetjes_dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $id = generate_id();
        $bonnetje = null;

        // Handle bonnetje upload
        if (!empty($_FILES['bonnetje']['tmp_name']) && $_FILES['bonnetje']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['bonnetje']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf'];
            if (in_array($ext, $allowed)) {
                $filename = $id . '.' . $ext;
                move_uploaded_file($_FILES['bonnetje']['tmp_name'], $bonnetjes_dir . '/' . $filename);
                $bonnetje = $filename;
            }
        }

        yaml_save('kosten', [
            'id' => $id,
            'datum' => $_POST['datum'],
            'omschrijving' => trim($_POST['omschrijving']),
            'bedrag' => (float)$_POST['bedrag'],
            'categorie_id' => $_POST['categorie_id'],
            'bonnetje' => $bonnetje,
            'aangemaakt' => date('Y-m-d H:i:s'),
        ]);
        flash('Kosten toegevoegd.');
    }

    if ($_POST['action'] === 'koppel_boeking') {
        $kosten_id = $_POST['kosten_id'];
        $boeking_id = $_POST['boeking_id'] ?? '';
        $boekingen = yaml_read('boekingen');

        // Remove existing coupling from any boeking pointing to this kosten_id
        foreach ($boekingen as &$b) {
            if (!empty($b['kosten_id']) && $b['kosten_id'] === $kosten_id) {
                unset($b['kosten_id']);
                yaml_save('boekingen', $b);
            }
        }
        unset($b);

        // Set new coupling if selected
        if (!empty($boeking_id)) {
            $boeking = yaml_find('boekingen', $boeking_id);
            if ($boeking) {
                $boeking['kosten_id'] = $kosten_id;
                yaml_save('boekingen', $boeking);
            }
        }

        flash('Koppeling bijgewerkt.');
        header('Location: ?page=kosten');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        // Delete bonnetje file if exists
        $record = yaml_find('kosten', $_POST['id']);
        if ($record && !empty($record['bonnetje'])) {
            $file = $bonnetjes_dir . '/' . $record['bonnetje'];
            if (file_exists($file)) unlink($file);
        }
        yaml_delete('kosten', $_POST['id']);
        flash('Kosten verwijderd.');
    }

    header('Location: ?page=kosten');
    exit;
}

$kosten = yaml_read('kosten');
$cats = yaml_read('categorieen');
$cats_map = [];
foreach ($cats as $c) $cats_map[$c['id']] = $c['naam'];

$boekingen = yaml_read('boekingen');
// Build map: kosten_id -> boeking
$kosten_boeking_map = [];
foreach ($boekingen as $b) {
    if (!empty($b['kosten_id'])) $kosten_boeking_map[$b['kosten_id']] = $b;
}
// Boekingen of type "af" that are not yet linked to kosten (for the dropdown)
$beschikbare_boekingen = array_filter($boekingen, fn($b) => $b['type'] === 'af' && empty($b['kosten_id']));

// Sorteer op datum (nieuwste eerst)
usort($kosten, fn($a, $b) => strcmp($b['datum'], $a['datum']));
?>

<h1>Kosten</h1>

<div class="card">
    <h2>Nieuwe uitgave</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Categorie</label>
                <select name="categorie_id" required>
                    <option value="">-- Kies --</option>
                    <?php foreach ($cats as $cat): ?>
                        <?php if ($cat['type'] === 'uitgaven'): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['naam']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Bedrag (&euro;)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
            </div>
            <div class="form-group">
                <label>Bonnetje</label>
                <input type="file" name="bonnetje" accept="image/*,.pdf" capture="environment">
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Overzicht kosten</h2>
    <?php if (empty($kosten)): ?>
        <p>Nog geen kosten ingevoerd.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Omschrijving</th>
                    <th>Categorie</th>
                    <th class="text-right">Bedrag</th>
                    <th>Bankboeking</th>
                    <th>Bonnetje</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $totaal = 0; foreach ($kosten as $k): $totaal += $k['bedrag']; ?>
                <tr>
                    <td><?= format_date($k['datum']) ?></td>
                    <td><?= htmlspecialchars($k['omschrijving']) ?></td>
                    <td><?= htmlspecialchars($cats_map[$k['categorie_id']] ?? '?') ?></td>
                    <td class="text-right"><?= format_money($k['bedrag']) ?></td>
                    <td>
                        <?php
                        $huidige = $kosten_boeking_map[$k['id']] ?? null;
                        $matching_boekingen = array_filter($beschikbare_boekingen, fn($b) => abs($b['bedrag'] - $k['bedrag']) < 0.01);
                        ?>
                        <?php if ($huidige || !empty($matching_boekingen)): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="koppel_boeking">
                            <input type="hidden" name="kosten_id" value="<?= $k['id'] ?>">
                            <select name="boeking_id" onchange="this.form.submit()">
                                <option value="">-- Geen boeking --</option>
                                <?php if ($huidige):
                                    $label = $huidige['datum'] . ' ' . mb_strimwidth($huidige['omschrijving'], 0, 25, '…');
                                ?>
                                <option value="<?= $huidige['id'] ?>" selected><?= htmlspecialchars($label) ?></option>
                                <?php endif; ?>
                                <?php foreach ($matching_boekingen as $b):
                                    $label = $b['datum'] . ' ' . mb_strimwidth($b['omschrijving'], 0, 25, '…');
                                ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($k['bonnetje'])): ?>
                            <?php
                            $ext = strtolower(pathinfo($k['bonnetje'], PATHINFO_EXTENSION));
                            $url = 'uploads/bonnetjes/' . htmlspecialchars($k['bonnetje']);
                            ?>
                            <?php if ($ext === 'pdf'): ?>
                                <a href="<?= $url ?>" target="_blank" class="btn btn-link btn-small">PDF</a>
                            <?php else: ?>
                                <a href="<?= $url ?>" target="_blank" class="bonnetje-thumb">
                                    <img src="<?= $url ?>" alt="Bonnetje">
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Weet je het zeker?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"><strong>Totaal</strong></td>
                    <td></td>
                    <td class="text-right"><strong><?= format_money($totaal) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>
