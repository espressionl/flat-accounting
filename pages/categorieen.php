<?php
// Categorieën beheer

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        yaml_save('categorieen', [
            'id' => generate_id(),
            'naam' => trim($_POST['naam']),
            'type' => $_POST['type'],
        ]);
        flash('Categorie toegevoegd.');
    }

    if ($_POST['action'] === 'delete') {
        yaml_delete('categorieen', $_POST['id']);
        flash('Categorie verwijderd.');
    }

    header('Location: ?page=categorieen');
    exit;
}

$cats = yaml_read('categorieen');

// Standaard categorieën aanmaken als er geen zijn
if (empty($cats)) {
    $defaults = [
        ['naam' => 'Contributie', 'type' => 'inkomsten'],
        ['naam' => 'Activiteiten', 'type' => 'uitgaven'],
        ['naam' => 'Bestuurskosten', 'type' => 'uitgaven'],
        ['naam' => 'Huur / Locatie', 'type' => 'uitgaven'],
        ['naam' => 'Nationaal (RT NL)', 'type' => 'uitgaven'],
        ['naam' => 'Overige inkomsten', 'type' => 'inkomsten'],
        ['naam' => 'Overige uitgaven', 'type' => 'uitgaven'],
    ];
    foreach ($defaults as $d) {
        $d['id'] = generate_id();
        yaml_save('categorieen', $d);
    }
    $cats = yaml_read('categorieen');
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
