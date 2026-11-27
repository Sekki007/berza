<?php

declare(strict_types=1);

/**
 * Predlog reklasifikacije. Podrazumevano samo CSV na stdout.
 *   php tools/reclassify_ads.php --dry-run
 *   php tools/reclassify_ads.php --apply
 *
 * --apply pravi data/backups/ads-before-reclassify-YYYYMMDD-HHMMSS.json
 * i upisuje samo sigurne predloge (confident). Vrati backup prepisom ads.json.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$ads = getAllAds();
$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'naslov', 'stari_tip', 'novi_tip', 'stari_brend', 'novi_brend', 'listing_type', 'razlog', 'sigurno']);

$changes = [];
foreach ($ads as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    $suggestion = classifyAd($ad);
    if (empty($suggestion['changed'])) {
        continue;
    }
    $row = [
        (int)($ad['id'] ?? 0),
        (string)($ad['title'] ?? ''),
        function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? ''),
        $suggestion['ad_type'],
        (string)($ad['brand'] ?? ''),
        $suggestion['brand'],
        $suggestion['listing_type'],
        $suggestion['reason'],
        !empty($suggestion['confident']) ? 'da' : 'ne',
    ];
    fputcsv($out, $row);
    if (!empty($suggestion['confident'])) {
        $changes[(int)$ad['id']] = $suggestion;
    }
}
fclose($out);

fwrite(STDERR, 'Predloga: ' . count($changes) . " sigurnih.\n");
if (!$apply) {
    fwrite(STDERR, "Dry-run. Ništa nije upisano. Za upis: php tools/reclassify_ads.php --apply\n");
    exit(0);
}

$backupDir = dirname(__DIR__) . '/data/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Ne mogu da napravim backup direktorijum.\n");
    exit(1);
}
$backup = $backupDir . '/ads-before-reclassify-' . date('Ymd-His') . '.json';
file_put_contents($backup, json_encode($ads, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($ads as &$ad) {
    if (!is_array($ad)) {
        continue;
    }
    $id = (int)($ad['id'] ?? 0);
    if (!isset($changes[$id])) {
        continue;
    }
    $ad['ad_type'] = $changes[$id]['ad_type'];
    $ad['category_group'] = $changes[$id]['category_group'];
    $ad['listing_type'] = $changes[$id]['listing_type'];
    if ($changes[$id]['brand'] !== '' && $changes[$id]['brand'] !== (string)($ad['brand'] ?? '')) {
        $ad['brand'] = $changes[$id]['brand'];
    }
}
unset($ad);
writeJsonFile('ads.json', $ads);
fwrite(STDERR, "Upisano. Backup: $backup\nVrati: cp " . $backup . " data/ads.json\n");
