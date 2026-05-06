<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Flat-file YAML helpers voor RT22 Boekhouding
 */

function data_path(string $file): string {
    return __DIR__ . '/data/' . $file . '.yaml';
}

function yaml_read(string $file): array {
    $path = data_path($file);
    if (!file_exists($path)) {
        return [];
    }
    $data = Yaml::parseFile($path);
    return is_array($data) ? $data : [];
}

function yaml_write(string $file, array $data): void {
    $path = data_path($file);
    file_put_contents($path, Yaml::dump($data, 4, 2));
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
