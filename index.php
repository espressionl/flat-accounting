<?php
session_start();
require_once __DIR__ . '/helpers.php';

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'leden', 'categorieen', 'kosten', 'facturen', 'boekingen'];

if (!in_array($page, $allowed)) {
    $page = 'dashboard';
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RT22 Hengelo - Boekhouding</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="nav-brand">RT22 Hengelo</div>
        <ul>
            <li><a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="?page=leden" class="<?= $page === 'leden' ? 'active' : '' ?>">Leden</a></li>
            <li><a href="?page=categorieen" class="<?= $page === 'categorieen' ? 'active' : '' ?>">Categorieën</a></li>
            <li><a href="?page=kosten" class="<?= $page === 'kosten' ? 'active' : '' ?>">Kosten</a></li>
            <li><a href="?page=facturen" class="<?= $page === 'facturen' ? 'active' : '' ?>">Facturen</a></li>
            <li><a href="?page=boekingen" class="<?= $page === 'boekingen' ? 'active' : '' ?>">Bankboekingen</a></li>
        </ul>
    </nav>

    <main>
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <?php include __DIR__ . '/pages/' . $page . '.php'; ?>
    </main>

    <footer>
        <p>Ronde Tafel 22 Hengelo &mdash; Boekhouding <?= current_boekjaar() ?></p>
    </footer>
</body>
</html>
