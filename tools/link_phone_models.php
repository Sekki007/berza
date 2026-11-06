<?php

declare(strict_types=1);

/**
 * Povezuje postojeće oglase telefona sa katalogom.
 *
 *   php tools/link_phone_models.php
 *   php tools/link_phone_models.php --apply
 *
 * Dry-run je podrazumevan i ništa ne upisuje. --apply dodaje samo
 * phone_model_id, phone_variant_id, phone_storage_norm i phone_model_link.
 * Naslov, opis, cena, telefon, slike i ostala javna polja ostaju ista.
 * Satovi, tableti, oprema i delovi se preskaču. Ručna veza prodavca se ne dira.
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
$ads = getAllAds();
$plan = phoneCatalogPlanLinks($ads, $apply, $force);
$summary = $plan['summary'];

echo ($apply ? 'APPLY' : 'DRY-RUN') . ($force ? ' FORCE' : '') . "\n";
foreach (['total', 'linked', 'already', 'skipped_not_phone', 'skipped_manual', 'skipped_seller', 'conflict', 'ambiguous', 'unmatched'] as $key) {
    echo str_pad($key, 22, ' ') . $summary[$key] . "\n";
}
echo "Pregled:\n";
foreach ($summary['rows'] as $row) {
    if (!in_array($row['result'], ['linked', 'ambiguous', 'conflict', 'unmatched'], true)) {
        continue;
    }
    echo ' - #' . $row['id'] . ' [' . $row['result'] . '] ' . $row['title']
        . ($row['model_id'] ? ' -> ' . $row['model_id'] : '')
        . ($row['variant_id'] ? ' / ' . $row['variant_id'] : '')
        . ($row['storage'] ? ' (' . $row['storage'] . ')' : '')
        . "\n";
}

if (!$apply) {
    echo "Ništa nije upisano. Za upis: php tools/link_phone_models.php --apply\n";
    exit(0);
}

writeJsonFile('ads.json', $plan['ads']);
echo "Upisane su samo veze ka katalogu.\n";
exit(0);
