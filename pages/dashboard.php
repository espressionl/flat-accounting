<?php
// Dashboard

$leden = yaml_read('leden');
$kosten = yaml_read('kosten');
$facturen = yaml_read('facturen');
$boekingen = yaml_read('boekingen');

// Totalen berekenen
$totaal_kosten = array_sum(array_column($kosten, 'bedrag'));
$totaal_gefactureerd = array_sum(array_column($facturen, 'bedrag'));
$totaal_betaald = array_sum(array_column($facturen, 'betaald'));
$openstaand = $totaal_gefactureerd - $totaal_betaald;

$bij = 0; $af = 0;
foreach ($boekingen as $b) {
    if ($b['type'] === 'bij') $bij += $b['bedrag'];
    else $af += $b['bedrag'];
}
$saldo = $bij - $af;

// Kosten per categorie
$cats = yaml_read('categorieen');
$cats_map = [];
foreach ($cats as $c) $cats_map[$c['id']] = $c['naam'];
$kosten_per_cat = [];
foreach ($kosten as $k) {
    $cat_naam = $cats_map[$k['categorie_id']] ?? 'Overig';
    $kosten_per_cat[$cat_naam] = ($kosten_per_cat[$cat_naam] ?? 0) + $k['bedrag'];
}
arsort($kosten_per_cat);

// Openstaande facturen
$open_facturen = array_filter($facturen, fn($f) => $f['status'] !== 'betaald');
$leden_map = [];
foreach ($leden as $l) $leden_map[$l['id']] = $l['naam'];
?>

<h1>Dashboard &mdash; Boekjaar <?= current_boekjaar() ?></h1>

<div class="grid">
    <div class="stat">
        <div class="label">Leden</div>
        <div class="value"><?= count($leden) ?></div>
    </div>
    <div class="stat">
        <div class="label">Banksaldo</div>
        <div class="value <?= $saldo >= 0 ? 'positive' : 'negative' ?>"><?= format_money($saldo) ?></div>
    </div>
    <div class="stat">
        <div class="label">Totaal kosten</div>
        <div class="value negative"><?= format_money($totaal_kosten) ?></div>
    </div>
    <div class="stat">
        <div class="label">Openstaand</div>
        <div class="value <?= $openstaand > 0 ? 'negative' : 'positive' ?>"><?= format_money($openstaand) ?></div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr;">
    <div class="card">
        <h2>Kosten per categorie</h2>
        <?php if (empty($kosten_per_cat)): ?>
            <p>Nog geen kosten.</p>
        <?php else: ?>
            <table>
                <tbody>
                    <?php foreach ($kosten_per_cat as $cat => $bedrag): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat) ?></td>
                        <td class="text-right"><?= format_money($bedrag) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Totaal</strong></td>
                        <td class="text-right"><strong><?= format_money($totaal_kosten) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Openstaande facturen</h2>
        <?php if (empty($open_facturen)): ?>
            <p>Alles betaald!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Lid</th>
                        <th class="text-right">Open</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($open_facturen as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($leden_map[$f['lid_id']] ?? '?') ?></td>
                        <td class="text-right"><?= format_money($f['bedrag'] - $f['betaald']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
