<?php
require_once __DIR__ . '/helpers.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? '';
if (!$id) { http_response_code(400); exit('Geen factuur ID'); }

$factuur = yaml_find('facturen', $id);
if (!$factuur) { http_response_code(404); exit('Factuur niet gevonden'); }

$is_externe_factuur = ($factuur['ontvanger_type'] ?? 'lid') === 'extern';
if ($is_externe_factuur) {
    $lid_naam = $factuur['ontvanger_naam'] ?? 'Onbekend';
    $lid_bedrijf = $factuur['ontvanger_bedrijf'] ?? '';
    $lid_adres = $factuur['ontvanger_adres'] ?? '';
    $lid_postcode = $factuur['ontvanger_postcode'] ?? '';
    $lid_plaats = $factuur['ontvanger_plaats'] ?? '';
} else {
    $lid = yaml_find('leden', $factuur['lid_id'] ?? '');
    $lid_naam = $lid['naam'] ?? 'Onbekend';
    $lid_bedrijf = $lid['bedrijf'] ?? '';
    $lid_adres = $lid['adres'] ?? '';
    $lid_postcode = $lid['postcode'] ?? '';
    $lid_plaats = $lid['plaats'] ?? '';
}
$lid_postcode_plaats = trim($lid_postcode . ' ' . $lid_plaats);

// Format datum in Dutch
$maanden = ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
$ts = strtotime($factuur['datum']);
$datum_nl = (int)date('j', $ts) . ' ' . $maanden[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);

$vervaldatum_ts = strtotime($factuur['vervaldatum']);
$vervaldatum_nl = (int)date('j', $vervaldatum_ts) . ' ' . $maanden[(int)date('n', $vervaldatum_ts) - 1] . ' ' . date('Y', $vervaldatum_ts);

// Betreft text
$boekjaar = current_boekjaar();
$betreft = $is_externe_factuur
    ? $factuur['omschrijving']
    : 'Factuur Contributie Tafelronde 22 - Tafeljaar ' . $boekjaar;
$body_text = $is_externe_factuur
    ? 'Hierbij brengen wij de hieronder omschreven levering of dienst bij u in rekening.'
    : 'Voor het lidmaatschap van en daaraan inherente participatie aan Tafelronde 22 te Hengelo, breng ik, benoemd tot penningmeester, namens het bestuur ' . htmlspecialchars($boekjaar) . ', u in rekening de contributie voor het Tafeljaar ' . htmlspecialchars($boekjaar) . '.';

// Build address block
$adres_html = '';
if ($lid_bedrijf) {
    $adres_html .= '<p style="margin:0; line-height:1.6;">' . htmlspecialchars($lid_bedrijf) . '</p>';
    $adres_html .= '<p style="margin:0; line-height:1.6;">t.a.v. ' . htmlspecialchars($lid_naam) . '</p>';
} else {
    $adres_html .= '<p style="margin:0; line-height:1.6;">' . htmlspecialchars($lid_naam) . '</p>';
}
if ($lid_adres) {
    $adres_html .= '<p style="margin:0; line-height:1.6;">' . htmlspecialchars($lid_adres) . '</p>';
}
if ($lid_postcode_plaats) {
    $adres_html .= '<p style="margin:0; line-height:1.6;">' . htmlspecialchars($lid_postcode_plaats) . '</p>';
}

$bedrag_fmt = '€ ' . number_format($factuur['bedrag'], 2, ',', '.');
$btw_fmt = '€ 0,00';

// Embed logo as base64 for DomPDF compatibility
$logo_path = __DIR__ . '/tr22logo.png';
$logo_data = base64_encode(file_get_contents($logo_path));
$logo_src = 'data:image/png;base64,' . $logo_data;

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 40px 50px 60px 50px; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10pt;
        color: #333333;
        margin: 0;
        padding: 0;
    }

    /* Header */
    .header {
        overflow: hidden;
        margin-bottom: 30px;
    }
    .header-left {
        float: left;
    }
    .header-left .org-name {
        font-size: 14pt;
        font-weight: bold;
        color: #333;
        margin: 0;
        line-height: 1.2;
    }
    .header-left .org-name span {
        color: #EF7D00;
    }
    .header-right {
        float: right;
        text-align: right;
    }
    .header-right .factuur-title {
        font-size: 28pt;
        font-weight: bold;
        color: #EF7D00;
        margin: 0;
        letter-spacing: 2px;
    }

    /* Address block */
    .address-block {
        margin-bottom: 40px;
        font-size: 10pt;
        color: #333;
    }

    /* Meta fields */
    .meta-fields {
        margin-bottom: 15px;
        font-size: 10pt;
    }
    .meta-fields p {
        margin: 2px 0;
        line-height: 1.4;
    }

    /* Body text */
    .body-text {
        margin-bottom: 20px;
        font-size: 10pt;
        line-height: 1.5;
        color: #333;
    }
    .body-text p { margin: 0; }

    /* Invoice table */
    table.factuur-tabel {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
    }
    table.factuur-tabel th {
        background: #EF7D00;
        color: #fff;
        text-align: left;
        padding: 7px 10px;
        font-size: 9pt;
        font-weight: bold;
    }
    table.factuur-tabel th.text-right {
        text-align: right;
    }
    table.factuur-tabel td {
        padding: 8px 10px;
        border-bottom: 1px solid #e0e0e0;
        font-size: 10pt;
    }
    table.factuur-tabel td.text-right {
        text-align: right;
    }
    table.factuur-tabel tr.btw td {
        text-align: right;
        color: #666;
        font-size: 9pt;
    }
    table.factuur-tabel tr.totaal td {
        font-weight: bold;
        text-align: right;
        border-top: 2px solid #EF7D00;
        border-bottom: none;
        font-size: 10pt;
    }

    /* Payment info */
    .betaalinfo {
        margin-bottom: 20px;
        font-size: 10pt;
        line-height: 1.5;
        color: #333;
    }
    .betaalinfo p { margin: 0; }
    .betaalinfo .iban {
        font-weight: bold;
    }

    /* Signature */
    .signature {
        font-size: 10pt;
        line-height: 1.5;
    }
    .signature p { margin: 0; }
    .signature .naam { margin-top: 12px; }

    /* Footer text at bottom of page */
    .footer-text {
        margin-top: 40px;
        text-align: center;
        font-size: 8pt;
        color: #666;
        border-top: 2px solid #EF7D00;
        padding-top: 8px;
    }
</style>
</head>
<body>

    <!-- Header -->
    <table style="width:100%; margin-bottom:30px; border:none;">
        <tr>
            <td style="border:none; padding:0; vertical-align:top;">
                <img src="' . $logo_src . '" height="60">
            </td>
            <td style="border:none; padding:0; vertical-align:top; text-align:right;">
                <span style="font-size:28pt; font-weight:bold; color:#EF7D00; letter-spacing:2px;">FACTUUR</span>
            </td>
        </tr>
    </table>

    <!-- Address -->
    <div class="address-block">
        ' . $adres_html . '
    </div>

    <!-- Meta -->
    <div class="meta-fields">
        <p><strong>Betreft:</strong> ' . htmlspecialchars($betreft) . '</p>
        <p><strong>Datum:</strong> ' . htmlspecialchars($datum_nl) . '</p>
        <p><strong>Factuurnummer:</strong> ' . htmlspecialchars($factuur['nummer']) . '</p>
    </div>

    <!-- Body text -->
    <div class="body-text">
        <p>' . $body_text . '</p>
    </div>

    <!-- Invoice table -->
    <table class="factuur-tabel">
        <thead>
            <tr>
                <th>Omschrijving</th>
                <th class="text-right">Bedrag</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>' . htmlspecialchars($factuur['omschrijving']) . '</strong></td>
                <td class="text-right">' . $bedrag_fmt . '</td>
            </tr>
            <tr class="btw">
                <td></td>
                <td>BTW: 0%&nbsp;&nbsp;&nbsp;&nbsp;' . $btw_fmt . '</td>
            </tr>
            <tr class="totaal">
                <td><strong>Totaalbedrag:</strong></td>
                <td>' . $bedrag_fmt . '</td>
            </tr>
        </tbody>
    </table>

    <!-- Payment info -->
    <div class="betaalinfo">
        <p>Gelieve dit bedrag uiterlijk ' . htmlspecialchars($vervaldatum_nl) . ' te voldoen op IBAN bankrekeningnummer
        <span class="iban">NL08 ABNA 0109 6619 23</span> tnv Ronde Tafel 22 onder vermelding van bovengenoemd
        factuurnummer.</p>
    </div>

    <!-- Signature -->
    <div class="signature">
        <p>Namens het bestuur,<br>
        Met vriendelijke groet,</p>
        <p class="naam">Junior Melgert - Penningmeester</p>
    </div>

    <!-- Footer -->
    <div class="footer-text">Tafelronde 22 Hengelo | ABN-AMRO NL08 ABNA 0109 6619 23</div>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Factuur-' . $factuur['nummer'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
