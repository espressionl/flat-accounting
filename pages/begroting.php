<?php
// Begroting beheer - budget per categorie

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $budgetten = $_POST['budget'] ?? [];
        foreach ($budgetten as $cat_id => $bedrag) {
            $bedrag = (float)$bedrag;
            if ($bedrag > 0) {
                yaml_save('begroting', [
                    'id' => $cat_id,
                    'categorie_id' => $cat_id,
                    'bedrag' => $bedrag,
                ]);
            } else {
                // Verwijder budget als 0
                yaml_delete('begroting', $cat_id);
            }
        }
        flash('Begroting opgeslagen.');
        header('Location: ?page=begroting');
        exit;
    }
}

$categorieen = yaml_read('categorieen');
$begroting = yaml_read('begroting');
$kosten = yaml_read('kosten');
$facturen = yaml_read('facturen');

// Budget map: categorie_id -> bedrag
$budget_map = [];
foreach ($begroting as $b) {
    $budget_map[$b['categorie_id']] = (float)$b['bedrag'];
}

// Werkelijke uitgaven per categorie
$uitgaven_map = [];
foreach ($kosten as $k) {
    $cat_id = $k['categorie_id'] ?? '';
    $uitgaven_map[$cat_id] = ($uitgaven_map[$cat_id] ?? 0) + $k['bedrag'];
}

// Werkelijke inkomsten: facturen totaal (geen categorie op facturen)
$totaal_gefactureerd = array_sum(array_column($facturen, 'bedrag'));

// Splits categorieën op type
$cats_uitgaven = array_filter($categorieen, fn($c) => $c['type'] === 'uitgaven');
$cats_inkomsten = array_filter($categorieen, fn($c) => $c['type'] === 'inkomsten');
?>

<h1>Begroting &mdash; Boekjaar <?= current_boekjaar() ?></h1>

<form method="post">
    <input type="hidden" name="action" value="save">

    <div class="grid" style="grid-template-columns: 1fr 1fr;">

        <div class="card">
            <h2>Inkomsten</h2>
            <table>
                <thead>
                    <tr>
                        <th>Categorie</th>
                        <th class="text-right">Budget (&euro;)</th>
                        <th class="text-right">Werkelijk</th>
                        <th class="text-right">Verschil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cats_inkomsten as $cat):
                        $budget = $budget_map[$cat['id']] ?? 0;
                        // For inkomsten categories we don't track actuals per category yet
                        // Show facturatie total for contributie-like categories
                        $werkelijk = 0;
                        $verschil = $werkelijk - $budget;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['naam']) ?></td>
                        <td class="text-right">
                            <input type="number" name="budget[<?= $cat['id'] ?>]" value="<?= $budget > 0 ? $budget : '' ?>" step="0.01" min="0" style="width: 120px; text-align: right;" placeholder="0,00">
                        </td>
                        <td class="text-right"><?= format_money($werkelijk) ?></td>
                        <td class="text-right <?= $verschil >= 0 ? 'positive' : 'negative' ?>"><?= format_money($verschil) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><small>Inkomsten worden bijgehouden via facturen en boekingen, niet per categorie.</small></p>
        </div>

        <div class="card">
            <h2>Uitgaven</h2>
            <table>
                <thead>
                    <tr>
                        <th>Categorie</th>
                        <th class="text-right">Budget (&euro;)</th>
                        <th class="text-right">Besteed</th>
                        <th class="text-right">Resterend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totaal_budget = 0;
                    $totaal_besteed = 0;
                    foreach ($cats_uitgaven as $cat):
                        $budget = $budget_map[$cat['id']] ?? 0;
                        $besteed = $uitgaven_map[$cat['id']] ?? 0;
                        $resterend = $budget - $besteed;
                        $totaal_budget += $budget;
                        $totaal_besteed += $besteed;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['naam']) ?></td>
                        <td class="text-right">
                            <input type="number" name="budget[<?= $cat['id'] ?>]" value="<?= $budget > 0 ? $budget : '' ?>" step="0.01" min="0" style="width: 120px; text-align: right;" placeholder="0,00">
                        </td>
                        <td class="text-right"><?= format_money($besteed) ?></td>
                        <td class="text-right <?= $resterend >= 0 ? 'positive' : 'negative' ?>"><?= format_money($resterend) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Totaal</strong></td>
                        <td class="text-right"><strong><?= format_money($totaal_budget) ?></strong></td>
                        <td class="text-right"><strong><?= format_money($totaal_besteed) ?></strong></td>
                        <td class="text-right <?= ($totaal_budget - $totaal_besteed) >= 0 ? 'positive' : 'negative' ?>"><strong><?= format_money($totaal_budget - $totaal_besteed) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>

    <div style="margin-top: 1rem; text-align: center;">
        <button type="submit" class="btn btn-primary">Begroting opslaan</button>
    </div>
</form>
