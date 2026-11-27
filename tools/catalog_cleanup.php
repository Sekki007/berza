<?php

declare(strict_types=1);

/**
 * Predlog čišćenja kataloga. Ne briše modele bez --apply.
 *   php tools/catalog_cleanup.php --dry-run
 *   php tools/catalog_cleanup.php --apply
 *
 * --apply samo upisuje data/catalog_redirects.json (301 ili 410).
 * Modele u seed-u ne briše. Backup prethodne mape je data/backups/catalog_redirects-*.json.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$models = phoneCatalogModels();
$byPath = [];
foreach ($models as $model) {
    $byPath[strtolower($model['brand_slug'] . '/' . $model['slug'])] = $model;
}

$junk = ['trotinet', 'svetlo', 'svjetlo', 'guma', 'narukvica', 'futrola', 'maska', 'punjac', 'kabl', 'displej', 'ekran'];
$out = fopen('php://output', 'w');
fputcsv($out, ['from', 'akcija', 'to', 'razlog']);
$redirects = [];
foreach ($models as $model) {
    $sources = array_map('strval', (array)($model['source_ids'] ?? []));
    $onlyAds = $sources === [] || $sources === ['site-ads'];
    $path = strtolower($model['brand_slug'] . '/' . $model['slug']);
    $name = mb_strtolower($model['brand'] . ' ' . $model['name'] . ' ' . $model['slug']);
    $isJunk = false;
    foreach ($junk as $needle) {
        if (str_contains($name, $needle)) {
            $isJunk = true;
            break;
        }
    }
    if ($isJunk) {
        fputcsv($out, [$path, '410', '', 'nije model telefona']);
        $redirects[] = ['from' => $path, 'to' => '', 'status' => 410];
        continue;
    }
    if (!$onlyAds && ($model['specs'] ?? []) !== []) {
        continue;
    }
    $target = catalogCleanupTarget($model, $byPath);
    if ($target !== null && $target !== $path) {
        fputcsv($out, [$path, '301', '/katalog/' . $target, 'spajanje sa modelom']);
        $redirects[] = ['from' => $path, 'to' => '/katalog/' . $target, 'status' => 301];
    }
}
fclose($out);
fwrite(STDERR, 'Redirekcija: ' . count($redirects) . "\n");
if (!$apply) {
    fwrite(STDERR, "Dry-run. Mapa nije upisana.\n");
    exit(0);
}
$dir = dirname(__DIR__) . '/data/backups';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$current = catalogRedirectsPath();
if (is_file($current)) {
    copy($current, $dir . '/catalog_redirects-' . date('Ymd-His') . '.json');
}
file_put_contents($current, json_encode(['redirects' => $redirects], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fwrite(STDERR, "Mapa upisana u data/catalog_redirects.json. Modeli u seed-u nisu obrisani.\n");
