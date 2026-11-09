<?php

declare(strict_types=1);

/**
 * Wikidata sync kataloga telefona.
 *
 *   php tools/sync_phone_catalog_wikidata.php
 *   php tools/sync_phone_catalog_wikidata.php --brand=apple
 *   php tools/sync_phone_catalog_wikidata.php --brand=apple --apply
 *   php tools/sync_phone_catalog_wikidata.php --list-brands
 *
 * Podrazumevano je dry-run. --apply upisuje spojeni dokument u phone_models.json.
 * --brand ograničava sync na jednu etapu (preporučeno).
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$listBrands = in_array('--list-brands', $argv, true);
$brandSlug = null;
foreach ($argv as $arg) {
    if (str_starts_with((string)$arg, '--brand=')) {
        $brandSlug = substr((string)$arg, strlen('--brand='));
        break;
    }
}

if ($listBrands) {
    foreach (phoneCatalogWikidataBrandStages() as $stage) {
        echo $stage['slug'] . "\t" . $stage['brand'] . "\t" . implode(',', $stage['qids']) . "\n";
    }
    exit(0);
}

// Ako je prosleđen nepoznat brand string (ne all/prazan), prijavi grešku.
foreach ($argv as $arg) {
    if (!str_starts_with((string)$arg, '--brand=')) {
        continue;
    }
    $raw = substr((string)$arg, strlen('--brand='));
    if (phoneCatalogWikidataNormalizeBrandSlug($raw) === null && mb_strtolower(trim($raw)) !== 'all' && trim($raw) !== '') {
        fwrite(STDERR, "Nepoznat brend: {$raw}\nDostupno: " . implode(', ', phoneCatalogWikidataBrandSlugs()) . "\n");
        exit(1);
    }
    $brandSlug = phoneCatalogWikidataNormalizeBrandSlug($raw);
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . ' Wikidata sync'
    . ($brandSlug ? (' [' . $brandSlug . ']') : ' [all]') . "\n";

$result = phoneCatalogWikidataSync($apply, null, $brandSlug);
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
    echo "Ništa nije upisano. Za upis dodaj --apply"
        . ($brandSlug ? (' (npr. --brand=' . $brandSlug . ' --apply)') : '') . "\n";
    exit(0);
}

echo 'Upisano: ' . phoneCatalogStoreFilename() . ' (' . storageDriver() . ")\n";
exit(0);
