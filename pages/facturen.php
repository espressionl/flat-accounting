<?php
// Facturen (contributie)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $factuur = [
            'id' => generate_id(),
            'nummer' => next_factuurnummer(),
            'lid_id' => $_POST['lid_id'],
            'datum' => $_POST['datum'],
            'vervaldatum' => date('Y-m-d', strtotime($_POST['datum'] . ' +30 days')),
            'omschrijving' => trim($_POST['omschrijving']),
            'bedrag' => (float)$_POST['bedrag'],
            'status' => 'open',
            'betaald' => 0.0,
            'aangemaakt' => date('Y-m-d H:i:s'),
        ];
        yaml_save('facturen', $factuur);
        flash('Factuur aangemaakt: ' . $factuur['nummer']);
    }

    if ($_POST['action'] === 'add_external') {
        $datum = $_POST['datum'] ?? '';
        $datum_object = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
        $bedrag = (float)($_POST['bedrag'] ?? 0);
        $naam = trim($_POST['ontvanger_naam'] ?? '');
        $adres = trim($_POST['ontvanger_adres'] ?? '');
        $postcode = trim($_POST['ontvanger_postcode'] ?? '');
        $plaats = trim($_POST['ontvanger_plaats'] ?? '');
        $email = trim($_POST['ontvanger_email'] ?? '');
        $omschrijving = trim($_POST['omschrijving'] ?? '');

        if (!$datum_object || $datum_object->format('Y-m-d') !== $datum || $bedrag <= 0 || $naam === '' || $adres === '' || $postcode === '' || $plaats === '' || $omschrijving === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vul alle verplichte velden voor de externe factuur correct in.', 'error');
        } else {
            $factuur = [
                'id' => generate_id(),
                'nummer' => next_factuurnummer(),
                'ontvanger_type' => 'extern',
                'ontvanger_naam' => $naam,
                'ontvanger_bedrijf' => trim($_POST['ontvanger_bedrijf'] ?? ''),
                'ontvanger_email' => $email,
                'ontvanger_adres' => $adres,
                'ontvanger_postcode' => $postcode,
                'ontvanger_plaats' => $plaats,
                'datum' => $datum,
                'vervaldatum' => date('Y-m-d', strtotime($datum . ' +30 days')),
                'omschrijving' => $omschrijving,
                'bedrag' => $bedrag,
                'status' => 'open',
                'betaald' => 0.0,
                'aangemaakt' => date('Y-m-d H:i:s'),
            ];
            yaml_save('facturen', $factuur);
            flash('Externe factuur aangemaakt: ' . $factuur['nummer']);
        }
    }

    if ($_POST['action'] === 'bulk') {
        $leden = yaml_read('leden');
        $bedrag = (float)$_POST['bedrag'];
        $omschrijving = trim($_POST['omschrijving']);
        $datum = $_POST['datum'];
        $count = 0;

        foreach ($leden as $lid) {
            if (!($lid['actief'] ?? true)) continue;
            $factuur = [
                'id' => generate_id(),
                'nummer' => next_factuurnummer(),
                'lid_id' => $lid['id'],
                'datum' => $datum,
                'vervaldatum' => date('Y-m-d', strtotime($datum . ' +30 days')),
                'omschrijving' => $omschrijving,
                'bedrag' => $bedrag,
                'status' => 'open',
                'betaald' => 0.0,
                'aangemaakt' => date('Y-m-d H:i:s'),
            ];
            yaml_save('facturen', $factuur);
            $count++;
        }
        flash("{$count} facturen aangemaakt voor alle leden.");
    }

    if ($_POST['action'] === 'koppel_boeking') {
        $factuur_id = $_POST['factuur_id'];
        $boeking_id = $_POST['boeking_id'] ?? '';
        $boekingen = yaml_read('boekingen');
        $factuur = yaml_find('facturen', $factuur_id);

        // Remove existing coupling from any boeking pointing to this factuur
        foreach ($boekingen as &$b) {
            if (!empty($b['factuur_id']) && $b['factuur_id'] === $factuur_id) {
                unset($b['factuur_id']);
                yaml_save('boekingen', $b);
            }
        }
        unset($b);

        // Reset factuur betaald status
        if ($factuur) {
            $factuur['betaald'] = 0.0;
            $factuur['status'] = 'open';
        }

        // Set new coupling if selected
        if (!empty($boeking_id)) {
            $boeking = yaml_find('boekingen', $boeking_id);
            if ($boeking && $factuur) {
                $boeking['factuur_id'] = $factuur_id;
                yaml_save('boekingen', $boeking);

                $factuur['betaald'] = min($factuur['bedrag'], abs($boeking['bedrag']));
                $factuur['status'] = $factuur['betaald'] >= $factuur['bedrag'] ? 'betaald' : 'deels';
            }
        }

        if ($factuur) yaml_save('facturen', $factuur);

        flash('Koppeling bijgewerkt.');
        header('Location: ?page=facturen');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        yaml_delete('facturen', $_POST['id']);
        flash('Factuur verwijderd.');
    }

    header('Location: ?page=facturen');
    exit;
}

$facturen = yaml_read('facturen');
$leden = yaml_read('leden');
$leden_map = [];
foreach ($leden as $l) $leden_map[$l['id']] = $l['naam'];

$boekingen = yaml_read('boekingen');
// Map: factuur_id -> boeking
$factuur_boeking_map = [];
foreach ($boekingen as $b) {
    if (!empty($b['factuur_id'])) $factuur_boeking_map[$b['factuur_id']] = $b;
}
// Available "bij" boekingen not yet linked to a factuur
$beschikbare_boekingen = array_filter($boekingen, fn($b) => $b['type'] === 'bij' && empty($b['factuur_id']));

usort($facturen, fn($a, $b) => strcmp($b['datum'], $a['datum']));
?>

<h1>Facturen</h1>

<div class="card">
    <h2>Contributie facturen aanmaken (alle leden)</h2>
    <form method="post">
        <input type="hidden" name="action" value="bulk">
        <div class="form-row">
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" value="Contributie <?= current_boekjaar() ?>" required>
            </div>
            <div class="form-group">
                <label>Bedrag per lid (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-success" onclick="return confirm('Facturen aanmaken voor alle <?= count($leden) ?> leden?')">Alle leden factureren</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Enkele factuur voor lid</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Lid</label>
                <select name="lid_id" required>
                    <option value="">-- Kies lid --</option>
                    <?php foreach ($leden as $lid): ?>
                    <option value="<?= $lid['id'] ?>"><?= htmlspecialchars($lid['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
            </div>
            <div class="form-group">
                <label>Bedrag (€)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Aanmaken</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Losse factuur voor externe ontvanger</h2>
    <form method="post">
        <input type="hidden" name="action" value="add_external">
        <div class="form-row">
            <div class="form-group">
                <label>Naam</label>
                <input type="text" name="ontvanger_naam" required>
            </div>
            <div class="form-group">
                <label>Bedrijfsnaam (optioneel)</label>
                <input type="text" name="ontvanger_bedrijf">
            </div>
            <div class="form-group">
                <label>E-mail (optioneel)</label>
                <input type="email" name="ontvanger_email">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Adres</label>
                <input type="text" name="ontvanger_adres" required>
            </div>
            <div class="form-group">
                <label>Postcode</label>
                <input type="text" name="ontvanger_postcode" required>
            </div>
            <div class="form-group">
                <label>Plaats</label>
                <input type="text" name="ontvanger_plaats" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="omschrijving" required>
            </div>
            <div class="form-group">
                <label>Bedrag (&euro;)</label>
                <input type="number" name="bedrag" step="0.01" min="0.01" required>
            </div>
            <div class="form-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Aanmaken</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Overzicht facturen</h2>
    <?php if (empty($facturen)): ?>
        <p>Nog geen facturen.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Datum</th>
                    <th>Ontvanger</th>
                    <th>Omschrijving</th>
                    <th class="text-right">Bedrag</th>
                    <th class="text-right">Betaald</th>
                    <th>Status</th>
                    <th>Bankboeking</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturen as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['nummer']) ?></strong></td>
                    <td><?= format_date($f['datum']) ?></td>
                    <td>
                        <?= htmlspecialchars(factuur_ontvanger_naam($f, $leden_map)) ?>
                        <?php if (($f['ontvanger_type'] ?? 'lid') === 'extern'): ?>
                            <small class="text-muted">(extern)</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['omschrijving']) ?></td>
                    <td class="text-right"><?= format_money($f['bedrag']) ?></td>
                    <td class="text-right"><?= format_money($f['betaald']) ?></td>
                    <td>
                        <span class="badge badge-<?= $f['status'] ?>"><?= $f['status'] ?></span>
                    </td>
                    <td>
                        <?php
                        $huidige_b = $factuur_boeking_map[$f['id']] ?? null;
                        $matching_boekingen = array_filter($beschikbare_boekingen, fn($b) => abs($b['bedrag'] - $f['bedrag']) < 0.01);
                        ?>
                        <?php if ($huidige_b || !empty($matching_boekingen)): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="koppel_boeking">
                            <input type="hidden" name="factuur_id" value="<?= $f['id'] ?>">
                            <select name="boeking_id" onchange="this.form.submit()">
                                <option value="">-- Geen boeking --</option>
                                <?php if ($huidige_b):
                                    $label = $huidige_b['datum'] . ' ' . mb_strimwidth($huidige_b['omschrijving'], 0, 25, '…');
                                ?>
                                <option value="<?= $huidige_b['id'] ?>" selected><?= htmlspecialchars($label) ?></option>
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
                        <a href="factuur_pdf.php?id=<?= $f['id'] ?>" class="btn btn-primary btn-small">PDF</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Factuur verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Verwijder</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
