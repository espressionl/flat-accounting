<?php
// Rapportage: Winst & Verliesrekening en Balans

$leden = yaml_read('leden');
$kosten = yaml_read('kosten');
$facturen = yaml_read('facturen');
$boekingen = yaml_read('boekingen');
$categorieen = yaml_read('categorieen');
$instellingen = yaml_read('instellingen');

$startsaldo_record = yaml_find('instellingen', 'startsaldo');
$startsaldo = $startsaldo_record ? (float)$startsaldo_record['bedrag'] : 0.0;

// Categorieën mappen
$cats_map = [];
foreach ($categorieen as $c) {
    $cats_map[$c['id']] = $c;
}

// Begroting laden
$begroting = yaml_read('begroting');
$budget_map = []; // categorie_id -> bedrag
foreach ($begroting as $b) {
    $budget_map[$b['categorie_id']] = (float)$b['bedrag'];
}

// --- WINST & VERLIESREKENING ---

// Inkomsten: gefactureerde bedragen per categorie
// Facturen hebben geen categorie, dus groeperen we op omschrijving of tonen als totaal
$totaal_gefactureerd = array_sum(array_column($facturen, 'bedrag'));

// Inkomsten uit boekingen (type=bij) die niet aan een factuur gekoppeld zijn
$overige_inkomsten = 0;
foreach ($boekingen as $b) {
    if ($b['type'] === 'bij' && empty($b['factuur_id'])) {
        $overige_inkomsten += $b['bedrag'];
    }
}

// Uitgaven per categorie (by id and naam)
$uitgaven_per_cat = [];   // cat_naam -> bedrag
$uitgaven_per_id = [];    // cat_id -> bedrag
$totaal_uitgaven = 0;
foreach ($kosten as $k) {
    $cat_id = $k['categorie_id'] ?? '';
    $cat_naam = $cats_map[$cat_id]['naam'] ?? 'Overig';
    $uitgaven_per_cat[$cat_naam] = ($uitgaven_per_cat[$cat_naam] ?? 0) + $k['bedrag'];
    $uitgaven_per_id[$cat_id] = ($uitgaven_per_id[$cat_id] ?? 0) + $k['bedrag'];
    $totaal_uitgaven += $k['bedrag'];
}
arsort($uitgaven_per_cat);

// Bouw uitgaven tabel met budget info (cat_id -> [naam, besteed, budget])
$uitgaven_rows = [];
foreach ($uitgaven_per_cat as $cat_naam => $bedrag) {
    // Zoek cat_id terug
    $cat_id = '';
    foreach ($cats_map as $id => $c) {
        if ($c['naam'] === $cat_naam) { $cat_id = $id; break; }
    }
    $budget = $budget_map[$cat_id] ?? 0;
    $uitgaven_rows[] = ['naam' => $cat_naam, 'besteed' => $bedrag, 'budget' => $budget];
}
// Voeg categorieën toe die wel budget hebben maar geen uitgaven
foreach ($budget_map as $cat_id => $budget) {
    if ($budget > 0 && !isset($uitgaven_per_id[$cat_id]) && isset($cats_map[$cat_id]) && $cats_map[$cat_id]['type'] === 'uitgaven') {
        $uitgaven_rows[] = ['naam' => $cats_map[$cat_id]['naam'], 'besteed' => 0, 'budget' => $budget];
    }
}

$totaal_budget_uitgaven = 0;
foreach ($uitgaven_rows as $row) $totaal_budget_uitgaven += $row['budget'];

$totaal_inkomsten = $totaal_gefactureerd + $overige_inkomsten;
$resultaat = $totaal_inkomsten - $totaal_uitgaven;

// --- BALANS ---

// Activa
$bij = 0; $af = 0;
foreach ($boekingen as $b) {
    if ($b['type'] === 'bij') $bij += $b['bedrag'];
    else $af += $b['bedrag'];
}
$banksaldo = $startsaldo + $bij - $af;

// Debiteuren (openstaande facturen)
$totaal_betaald = array_sum(array_column($facturen, 'betaald'));
$debiteuren = $totaal_gefactureerd - $totaal_betaald;

$totaal_activa = $banksaldo + $debiteuren;

// Passiva
// Eigen vermogen = startsaldo + resultaat boekjaar
$eigen_vermogen = $startsaldo;
$totaal_passiva = $eigen_vermogen + $resultaat;

// Leden map voor debiteuren detail
$leden_map = [];
foreach ($leden as $l) $leden_map[$l['id']] = $l['naam'];
$open_facturen = array_filter($facturen, fn($f) => $f['status'] !== 'betaald');
?>

<h1>Rapportage &mdash; Boekjaar <?= current_boekjaar() ?></h1>

<div class="grid" style="grid-template-columns: 1fr 1fr;">

    <!-- WINST & VERLIESREKENING -->
    <div class="card">
        <h2>Winst &amp; Verliesrekening</h2>
        <table>
            <thead>
                <tr>
                    <th colspan="2">Inkomsten</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Facturatie (contributie e.d.)</td>
                    <td class="text-right"><?= format_money($totaal_gefactureerd) ?></td>
                </tr>
                <?php if ($overige_inkomsten > 0): ?>
                <tr>
                    <td>Overige inkomsten</td>
                    <td class="text-right"><?= format_money($overige_inkomsten) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totaal inkomsten</strong></td>
                    <td class="text-right"><strong><?= format_money($totaal_inkomsten) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <table style="margin-top: 1rem;">
            <thead>
                <tr>
                    <th>Uitgaven</th>
                    <th class="text-right">Budget</th>
                    <th class="text-right">Besteed</th>
                    <th class="text-right">Resterend</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uitgaven_rows as $row):
                    $resterend = $row['budget'] - $row['besteed'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['naam']) ?></td>
                    <td class="text-right"><?= $row['budget'] > 0 ? format_money($row['budget']) : '&mdash;' ?></td>
                    <td class="text-right"><?= format_money($row['besteed']) ?></td>
                    <td class="text-right <?= $row['budget'] > 0 ? ($resterend >= 0 ? 'positive' : 'negative') : '' ?>"><?= $row['budget'] > 0 ? format_money($resterend) : '&mdash;' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($uitgaven_rows)): ?>
                <tr>
                    <td colspan="4">Nog geen uitgaven.</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totaal uitgaven</strong></td>
                    <td class="text-right"><strong><?= $totaal_budget_uitgaven > 0 ? format_money($totaal_budget_uitgaven) : '&mdash;' ?></strong></td>
                    <td class="text-right"><strong><?= format_money($totaal_uitgaven) ?></strong></td>
                    <td class="text-right <?= $totaal_budget_uitgaven > 0 ? (($totaal_budget_uitgaven - $totaal_uitgaven) >= 0 ? 'positive' : 'negative') : '' ?>"><strong><?= $totaal_budget_uitgaven > 0 ? format_money($totaal_budget_uitgaven - $totaal_uitgaven) : '&mdash;' ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <table style="margin-top: 1.5rem; border-top: 3px double #333;">
            <tbody>
                <tr>
                    <td><strong style="font-size: 1.1em;"><?= $resultaat >= 0 ? 'Winst' : 'Verlies' ?></strong></td>
                    <td class="text-right">
                        <strong style="font-size: 1.1em;" class="<?= $resultaat >= 0 ? 'positive' : 'negative' ?>">
                            <?= format_money(abs($resultaat)) ?>
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- BALANS -->
    <div class="card">
        <h2>Balans</h2>
        <table>
            <thead>
                <tr>
                    <th colspan="2">Activa</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Startsaldo bank</td>
                    <td class="text-right"><?= format_money($startsaldo) ?></td>
                </tr>
                <tr>
                    <td>Mutaties (bij - af)</td>
                    <td class="text-right"><?= format_money($bij - $af) ?></td>
                </tr>
                <tr>
                    <td><strong>Banksaldo huidig</strong></td>
                    <td class="text-right"><strong><?= format_money($banksaldo) ?></strong></td>
                </tr>
                <tr>
                    <td>Debiteuren (openstaand)</td>
                    <td class="text-right"><?= format_money($debiteuren) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totaal activa</strong></td>
                    <td class="text-right"><strong><?= format_money($totaal_activa) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <table style="margin-top: 1rem;">
            <thead>
                <tr>
                    <th colspan="2">Passiva</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Eigen vermogen (startsaldo)</td>
                    <td class="text-right"><?= format_money($eigen_vermogen) ?></td>
                </tr>
                <tr>
                    <td><?= $resultaat >= 0 ? 'Winst boekjaar' : 'Verlies boekjaar' ?></td>
                    <td class="text-right <?= $resultaat >= 0 ? 'positive' : 'negative' ?>"><?= format_money($resultaat) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Totaal passiva</strong></td>
                    <td class="text-right"><strong><?= format_money($totaal_passiva) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($open_facturen)): ?>
        <table style="margin-top: 1.5rem;">
            <thead>
                <tr>
                    <th colspan="3">Openstaande debiteuren</th>
                </tr>
                <tr>
                    <th>Factuur</th>
                    <th>Ontvanger</th>
                    <th class="text-right">Openstaand</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($open_facturen as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['nummer']) ?></td>
                    <td><?= htmlspecialchars(factuur_ontvanger_naam($f, $leden_map)) ?></td>
                    <td class="text-right"><?= format_money($f['bedrag'] - $f['betaald']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
