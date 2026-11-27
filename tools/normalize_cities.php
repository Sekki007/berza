<?php

declare(strict_types=1);

/**
 * Predlog normalizacije grada. Ne menja oglase bez --apply.
 *   php tools/normalize_cities.php --dry-run
 *   php tools/normalize_cities.php --apply
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$ads = getAllAds();
$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'naslov', 'stari_grad', 'novi_grad', 'opstina']);
$changes = [];
foreach ($ads as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    $raw = trim((string)($ad['location'] ?? ''));
    $parsed = normalizeCity($raw);
    if ($parsed['city'] === '' || ($parsed['city'] === $raw && $parsed['district'] === '')) {
        continue;
    }
    if ($parsed['city'] === $raw) {
        continue;
    }
    fputcsv($out, [(int)($ad['id'] ?? 0), (string)($ad['title'] ?? ''), $raw, $parsed['city'], $parsed['district']]);
    $changes[(int)$ad['id']] = $parsed;
}
fclose($out);
fwrite(STDERR, 'Gradova za izmenu: ' . count($changes) . "\n");
if (!$apply) {
    fwrite(STDERR, "Dry-run. Filter grada već prikazuje ove oglase po normalizovanom gradu, bez upisa.\n");
    exit(0);
}
$backupDir = dirname(__DIR__) . '/data/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}
$backup = $backupDir . '/ads-before-cities-' . date('Ymd-His') . '.json';
file_put_contents($backup, json_encode($ads, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($ads as &$ad) {
    $id = (int)($ad['id'] ?? 0);
    if (!isset($changes[$id])) {
        continue;
    }
    $ad['location_district'] = $changes[$id]['district'];
    $ad['location'] = $changes[$id]['city'];
}
unset($ad);
writeJsonFile('ads.json', $ads);
fwrite(STDERR, "Upisano. Backup: $backup\n");
