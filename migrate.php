<?php
/**
 * Eenmalig migratiescript: zet monolithische YAML-bestanden om naar
 * losse bestanden per record in data/{collectie}/{id}.yaml
 *
 * Gebruik: php migrate.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$collections = ['leden', 'categorieen', 'facturen', 'boekingen', 'kosten'];
$base = __DIR__ . '/data';

foreach ($collections as $collection) {
    $source = $base . '/' . $collection . '.yaml';
    $target_dir = $base . '/' . $collection;

    if (!file_exists($source)) {
        echo "SKIP  {$collection}.yaml (niet gevonden)\n";
        continue;
    }

    $data = Yaml::parseFile($source);
    if (!is_array($data) || empty($data)) {
        echo "SKIP  {$collection}.yaml (leeg)\n";
        continue;
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $count = 0;
    foreach ($data as $record) {
        if (!isset($record['id'])) {
            echo "WARN  {$collection}: record zonder id overgeslagen\n";
            continue;
        }
        $path = $target_dir . '/' . $record['id'] . '.yaml';
        file_put_contents($path, Yaml::dump($record, 4, 2));
        $count++;
    }

    // Hernoem origineel als backup
    rename($source, $source . '.bak');
    echo "OK    {$collection}: {$count} records gemigreerd, origineel hernoemd naar {$collection}.yaml.bak\n";
}

echo "\nMigratie voltooid.\n";
