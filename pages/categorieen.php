<?php
// Categorieën beheer

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cats = yaml_read('categorieen');

    if ($_POST['action'] === 'add') {
        $cats[] = [
            'id' => generate_id(),
            'naam' => trim($_POST['naam']),
            'type' => $_POST['type'], // inkomsten of uitgaven
        ];
        yaml_write('categorieen', $cats);
        flash('Categorie toegevoegd.');
    }

    if ($_POST['action'] === 'delete') {
        $cats = array_filter($cats, fn($c) => $c['id'] !== $_POST['id']);
        yaml_write('categorieen', array_values($cats));
        flash('Categorie verwijderd.');
    }

    header('Location: ?page=categorieen');
    exit;
}

$cats = yaml_read('categorieen');

// Standaard categorieën aanmaken als er geen zijn
if (empty($cats)) {
    $cats = [
        ['id' => generate_id(), 'naam' => 'Contributie', 'type' => 'inkomsten'],
        ['id' => generate_id(), 'naam' => 'Activiteiten', 'type' => 'uitgaven'],
        ['id' => generate_id(), 'naam' => 'Bestuurskosten', 'type' => 'uitgaven'],
        ['id' => generate_id(), 'naam' => 'Huur / Locatie', 'type' => 'uitgaven'],
        ['id' => generate_id(), 'naam' => 'Nationaal (RT NL)', 'type' => 'uitgaven'],
        ['id' => generate_id(), 'naam' => 'Overige inkomsten', 'type' => 'inkomsten'],
        ['id' => generate_id(), 'naam' => 'Overige uitgaven', 'type' => 'uitgaven'],
    ];
    yaml_write('categorieen', $cats);
}
?>

<h1>Categorieën</h1>

<div class="card">
    <h2>Nieuwe categorie</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Naam</label>
                <input type="text" name="naam" required>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type">
                    <option value="uitgaven">Uitgaven</option>
                    <option value="inkomsten">Inkomsten</option>
                </select>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Overzicht</h2>
    <table>
        <thead>
            <tr>
                <th>Categorie</th>
                <th>Type</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cats as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat['naam']) ?></td>
                <td><span class="badge badge-<?= $cat['type'] === 'inkomsten' ? 'betaald' : 'open' ?>"><?= $cat['type'] ?></span></td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Weet je het zeker?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
