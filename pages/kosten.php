<?php
// Kosten vastleggen

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $kosten = yaml_read('kosten');

    if ($_POST['action'] === 'add') {
        $kosten[] = [
            'id' => generate_id(),
            'datum' => $_POST['datum'],
            'omschrijving' => trim($_POST['omschrijving']),
            'bedrag' => (float)$_POST['bedrag'],
            'categorie_id' => $_POST['categorie_id'],
            'aangemaakt' => date('Y-m-d H:i:s'),
        ];
        yaml_write('kosten', $kosten);
        flash('Kosten toegevoegd.');
    }

    if ($_POST['action'] === 'delete') {
        $kosten = array_filter($kosten, fn($k) => $k['id'] !== $_POST['id']);
        yaml_write('kosten', array_values($kosten));
        flash('Kosten verwijderd.');
    }

    header('Location: ?page=kosten');
    exit;
}

$kosten = yaml_read('kosten');
$cats = yaml_read('categorieen');
$cats_map = [];
foreach ($cats as $c) $cats_map[$c['id']] = $c['naam'];

// Sorteer op datum (nieuwste eerst)
usort($kosten, fn($a, $b) => strcmp($b['datum'], $a['datum']));
?>

<h1>Kosten</h1>

<div class="card">
    <h2>Nieuwe uitgave</h2>
    <form method="post">
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
                <label>Bedrag (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
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
                    <td colspan="3"><strong>Totaal</strong></td>
                    <td class="text-right"><strong><?= format_money($totaal) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>
