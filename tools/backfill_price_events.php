<?php

declare(strict_types=1);

/**
 * Dry-run pravi created/sold događaje iz postojećih oglasa i ništa ne upisuje.
 *   php tools/backfill_price_events.php --dry-run
 *   php tools/backfill_price_events.php --apply
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$existing = $apply ? priceEventLog() : [];
$known = [];
foreach ($existing as $row) {
    $known[(int)($row['ad_id'] ?? 0) . '|' . (string)($row['event'] ?? '')] = true;
}
$added = 0;
foreach (getAllAds() as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    $id = (int)($ad['id'] ?? 0);
    if (!isset($known[$id . '|created'])) {
        $added++;
        if ($apply) {
            recordAdPriceEvent($ad, 'created');
        }
    }
    if (!empty($ad['is_sold']) && !isset($known[$id . '|sold'])) {
        $added++;
        if ($apply) {
            recordAdPriceEvent($ad, 'sold');
        }
    }
}
echo ($apply ? 'Upisano' : 'Dry-run, bilo bi upisano') . ': ' . $added . " događaja.\n";
if (!$apply) {
    echo "Ništa nije menjano. --apply upisuje data/ad_price_events.json.\n";
}
