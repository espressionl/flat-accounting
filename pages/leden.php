<?php
// Leden beheer

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leden = yaml_read('leden');

    if ($_POST['action'] === 'add') {
        $leden[] = [
            'id' => generate_id(),
            'naam' => trim($_POST['naam']),
            'email' => trim($_POST['email']),
            'bedrijf' => trim($_POST['bedrijf'] ?? ''),
            'adres' => trim($_POST['adres'] ?? ''),
            'postcode' => trim($_POST['postcode'] ?? ''),
            'plaats' => trim($_POST['plaats'] ?? ''),
            'actief' => true,
            'aangemaakt' => date('Y-m-d'),
        ];
        yaml_write('leden', $leden);
        flash('Lid toegevoegd.');
    }

    if ($_POST['action'] === 'delete') {
        $leden = array_filter($leden, fn($l) => $l['id'] !== $_POST['id']);
        yaml_write('leden', array_values($leden));
        flash('Lid verwijderd.');
    }

    header('Location: ?page=leden');
    exit;
}

$leden = yaml_read('leden');
?>

<h1>Leden</h1>

<div class="card">
    <h2>Nieuw lid</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Naam</label>
                <input type="text" name="naam" required>
            </div>
            <div class="form-group">
                <label>Bedrijfsnaam</label>
                <input type="text" name="bedrijf">
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Adres</label>
                <input type="text" name="adres">
            </div>
            <div class="form-group">
                <label>Postcode</label>
                <input type="text" name="postcode">
            </div>
            <div class="form-group">
                <label>Plaats</label>
                <input type="text" name="plaats">
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Ledenlijst (<?= count($leden) ?>)</h2>
    <?php if (empty($leden)): ?>
        <p>Nog geen leden toegevoegd.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Bedrijf</th>
                    <th>Adres</th>
                    <th>E-mail</th>
                    <th>Lid sinds</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leden as $lid): ?>
                <tr>
                    <td><?= htmlspecialchars($lid['naam']) ?></td>
                    <td><?= htmlspecialchars($lid['bedrijf'] ?? '') ?></td>
                    <td><?= htmlspecialchars(implode(', ', array_filter([$lid['adres'] ?? '', ($lid['postcode'] ?? '') . ' ' . ($lid['plaats'] ?? '')]))) ?></td>
                    <td><?= htmlspecialchars($lid['email']) ?></td>
                    <td><?= format_date($lid['aangemaakt']) ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Weet je het zeker?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $lid['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
