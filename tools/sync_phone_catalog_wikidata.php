<?php

declare(strict_types=1);

/**
 * Wikidata sync kataloga telefona.
 *
 *   php tools/sync_phone_catalog_wikidata.php
 *   php tools/sync_phone_catalog_wikidata.php --apply
 *
 * Podrazumevano je dry-run. --apply upisuje spojeni dokument u phone_models.json.
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);

echo ($apply ? 'APPLY' : 'DRY-RUN') . " Wikidata sync\n";
$result = phoneCatalogWikidataSync($apply);
if (empty($result['ok'])) {
    fwrite(STDERR, (string)($result['error'] ?? 'Sync nije uspeo.') . "\n");
    exit(1);
}

$summary = $result['summary'];
echo 'Wikidata modela: ' . (int)$summary['incoming'] . "\n";
echo 'Sa bar jednom specifikacijom: ' . (int)$summary['incoming_with_specs'] . "\n";
echo 'SPARQL redova: ' . (int)$summary['bindings'] . "\n";
echo 'Novih modela: ' . (int)$summary['added'] . "\n";
echo 'Ažuriranih modela: ' . (int)$summary['updated'] . "\n";
echo 'Ukupno posle spajanja: ' . (int)$summary['total'] . "\n";

if (!empty($summary['added_ids'])) {
    echo "Primer novih:\n";
    foreach ($summary['added_ids'] as $id) {
        echo ' - ' . $id . "\n";
    }
}

if (!$apply) {
    echo "Ništa nije upisano. Za upis: php tools/sync_phone_catalog_wikidata.php --apply\n";
    exit(0);
}

echo 'Upisano: ' . phoneCatalogStoreFilename() . ' (' . storageDriver() . ")\n";
exit(0);
