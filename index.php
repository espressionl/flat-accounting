<?php
session_start();
require_once __DIR__ . '/helpers.php';

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'leden', 'categorieen', 'kosten', 'facturen', 'boekingen', 'begroting', 'rapportage'];

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
    <script>
        (function() {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        function toggleTheme() {
            var d = document.documentElement;
            var isDark = d.getAttribute('data-theme') === 'dark';
            d.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }
    </script>
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
            <li><a href="?page=begroting" class="<?= $page === 'begroting' ? 'active' : '' ?>">Begroting</a></li>
            <li><a href="?page=rapportage" class="<?= $page === 'rapportage' ? 'active' : '' ?>">Rapportage</a></li>
        </ul>
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
            <span class="icon-moon">&#9790;</span>
            <span class="icon-sun">&#9728;</span>
        </button>
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
