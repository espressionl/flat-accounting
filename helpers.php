<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Flat-file YAML helpers voor RT22 Boekhouding
 *
 * Data is opgeslagen als losse YAML-bestanden per record:
 *   data/{collectie}/{id}.yaml
 */

function data_dir(string $collection): string {
    $dir = __DIR__ . '/data/' . $collection;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function data_record_path(string $collection, string $id): string {
    return data_dir($collection) . '/' . $id . '.yaml';
}

/**
 * Lees alle records uit een collectie-map.
 */
function yaml_read(string $collection): array {
    $dir = data_dir($collection);
    $files = glob($dir . '/*.yaml');
    if (!$files) {
        return [];
    }
    $records = [];
    foreach ($files as $file) {
        $data = Yaml::parseFile($file);
        if (is_array($data)) {
            $records[] = $data;
        }
    }
    return $records;
}

/**
 * Schrijf een volledige collectie (array van records) naar losse bestanden.
 * Verwijdert bestanden die niet meer in $data voorkomen.
 */
function yaml_write(string $collection, array $data): void {
    $dir = data_dir($collection);

    // Bepaal welke IDs er nu zijn
    $new_ids = [];
    foreach ($data as $record) {
        if (!isset($record['id'])) continue;
        $new_ids[$record['id']] = true;
        $path = $dir . '/' . $record['id'] . '.yaml';
        file_put_contents($path, Yaml::dump($record, 4, 2));
    }

    // Verwijder bestanden van records die niet meer bestaan
    $existing = glob($dir . '/*.yaml');
    foreach ($existing ?: [] as $file) {
        $id = basename($file, '.yaml');
        if (!isset($new_ids[$id])) {
            unlink($file);
        }
    }
}

/**
 * Sla een enkel record op (toevoegen of bijwerken).
 */
function yaml_save(string $collection, array $record): void {
    if (!isset($record['id'])) return;
    $path = data_record_path($collection, $record['id']);
    file_put_contents($path, Yaml::dump($record, 4, 2));
}

/**
 * Verwijder een enkel record.
 */
function yaml_delete(string $collection, string $id): void {
    $path = data_record_path($collection, $id);
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Zoek een enkel record op ID.
 */
function yaml_find(string $collection, string $id): ?array {
    $path = data_record_path($collection, $id);
    if (!file_exists($path)) {
        return null;
    }
    $data = Yaml::parseFile($path);
    return is_array($data) ? $data : null;
}

function generate_id(): string {
    return uniqid('', true);
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function format_money(float $amount): string {
    return '€ ' . number_format($amount, 2, ',', '.');
}

function format_date(string $date): string {
    return date('d-m-Y', strtotime($date));
}

function current_boekjaar(): string {
    // Boekjaar loopt van juli t/m juni (RT tafeljarensysteem)
    $month = (int)date('n');
    $year = (int)date('Y');
    if ($month >= 7) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}

function next_factuurnummer(): string {
    $facturen = yaml_read('facturen');
    $jaar = date('Y');
    $max = 0;
    foreach ($facturen as $f) {
        if (str_starts_with($f['nummer'], "RT22-{$jaar}-")) {
            $num = (int)substr($f['nummer'], strlen("RT22-{$jaar}-"));
            if ($num > $max) $max = $num;
        }
    }
    return sprintf("RT22-%s-%03d", $jaar, $max + 1);
}
