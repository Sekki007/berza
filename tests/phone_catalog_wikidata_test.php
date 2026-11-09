<?php

declare(strict_types=1);

/**
 * Wikidata sync kataloga — offline fixture, bez mreže.
 * Pokretanje: php tests/phone_catalog_wikidata_test.php
 */

define('KT_SKIP_BACKGROUND', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/katalog';
$_SERVER['HTTPS'] = '';

$dataDir = dirname(__DIR__) . '/data';
$storePath = $dataDir . '/phone_models.json';
$restoreStore = is_file($storePath) ? (string)file_get_contents($storePath) : null;
register_shutdown_function(static function () use ($storePath, $restoreStore): void {
    if ($restoreStore === null) {
        if (is_file($storePath)) {
            unlink($storePath);
        }
        return;
    }
    file_put_contents($storePath, $restoreStore);
});

require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "OK  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$fixture = json_decode((string)file_get_contents(dirname(__DIR__) . '/tests/fixtures/wikidata_phone_bindings.json'), true);
$bindings = is_array($fixture['bindings'] ?? null) ? $fixture['bindings'] : [];
check($bindings !== [], 'fixture bindings postoje');

$built = phoneCatalogWikidataBuildFromBindings($bindings);
check(!empty($built['ok']), 'build iz fixture-a prolazi');
check((int)$built['stats']['incoming'] === 3, 'tri modela iz fixture-a');
check((int)$built['stats']['with_specs'] >= 2, 'bar dva modela imaju specifikacije');

$ids = array_column($built['models'], 'id');
check(in_array('apple-iphone-15', $ids, true), 'iPhone 15 id');
check(in_array('samsung-galaxy-test-one', $ids, true), 'Galaxy Test One id');
check(in_array('google-pixel-fiction', $ids, true), 'Pixel Fiction id');

$iphoneIncoming = null;
foreach ($built['models'] as $model) {
    if ($model['id'] === 'apple-iphone-15') {
        $iphoneIncoming = $model;
        break;
    }
}
check(is_array($iphoneIncoming), 'iPhone 15 ulazni model');
$storages = array_column($iphoneIncoming['variants'] ?? [], 'storage');
sort($storages);
check($storages === ['128GB', '256GB'], 'memorijske varijante sa Wikidata');
$specLabels = array_column($iphoneIncoming['specs'] ?? [], 'label');
check(in_array('Težina', $specLabels, true) && in_array('Dijagonala', $specLabels, true), 'težina i dijagonala');

// Seed kao baza: manufacturer specs na iPhone 15 moraju ostati.
$seed = json_decode((string)file_get_contents(phoneCatalogSeedPath()), true);
$beforeSpecs = [];
foreach ($seed['models'] as $model) {
    if (($model['id'] ?? '') === 'apple-iphone-15') {
        $beforeSpecs = $model['specs'] ?? [];
        break;
    }
}
check($beforeSpecs !== [], 'seed iPhone 15 ima manufacturer specs');

$merged = phoneCatalogWikidataMerge($seed, [
    'sources' => $built['sources'],
    'models' => $built['models'],
]);
check(!empty($merged['ok']), 'merge prolazi validaciju: ' . (string)($merged['error'] ?? ''));
check((int)$merged['summary']['added'] >= 2, 'dodati novi modeli iz fixture-a');

$iphoneMerged = null;
foreach ($merged['document']['models'] as $model) {
    if (($model['id'] ?? '') === 'apple-iphone-15') {
        $iphoneMerged = $model;
        break;
    }
}
check(is_array($iphoneMerged), 'iPhone 15 ostaje posle merge');
$mergedSourceIds = array_map('strval', $iphoneMerged['source_ids'] ?? []);
check(in_array('apple-iphone-15', $mergedSourceIds, true), 'Apple Support izvor ostaje');
check(in_array('wd-Q121992935', $mergedSourceIds, true), 'Wikidata izvor dodat');

$manufacturerLeft = 0;
$wikidataSpecs = 0;
foreach ($iphoneMerged['specs'] as $spec) {
    if (($spec['source_id'] ?? '') === 'apple-iphone-15') {
        $manufacturerLeft++;
    }
    if (($spec['source_id'] ?? '') === 'wd-Q121992935') {
        $wikidataSpecs++;
    }
}
check($manufacturerLeft === count($beforeSpecs), 'manufacturer specifikacije nisu obrisane');
check($wikidataSpecs > 0, 'Wikidata specifikacije dodate uz manufacturer');

// Apply sync into temporary store via API, then ensure catalog reads it.
if (is_file($storePath)) {
    unlink($storePath);
}
phoneCatalogResetCache();
$applied = phoneCatalogWikidataSync(true, $bindings);
check(!empty($applied['ok']), 'apply sync sa fixture-om');
phoneCatalogResetCache();
$after = phoneCatalogBundle();
check(!empty($after['ok']) && count($after['models']) >= count($seed['models']), 'bundle posle apply je veći ili jednak seedu');
$pixel = phoneCatalogModel('google-pixel-fiction');
check(is_array($pixel) && ($pixel['specs'][0]['label'] ?? '') === 'Težina', 'novi Pixel Fiction model je u katalogu');

// Prošireni sync: etape, tipovi, godine, filter brenda
$stages = phoneCatalogWikidataBrandStages();
$slugs = phoneCatalogWikidataBrandSlugs();
check(count($stages) >= 15, 'ima bar 15 brand etapa');
check(in_array('nokia', $slugs, true) && in_array('vivo', $slugs, true) && in_array('xiaomi', $slugs, true), 'brendovi nokia/vivo/xiaomi');
check(phoneCatalogWikidataMinYear() <= 2014, 'min godina ≤ 2014');
$types = phoneCatalogWikidataInstanceTypes();
check(in_array('Q19723451', $types, true) && in_array('Q22645', $types, true), 'smartphone + mobile phone tipovi');

$appleSparql = phoneCatalogWikidataSparql('apple');
check(str_contains($appleSparql, 'wd:Q312') && !str_contains($appleSparql, 'wd:Q20718'), 'SPARQL za apple samo Apple Q-id');
check(str_contains($appleSparql, 'wd:Q22645'), 'SPARQL uključuje mobile phone tip');

$appleOnly = phoneCatalogWikidataSync(false, $bindings, 'apple');
check(!empty($appleOnly['ok']), 'sync sa brand=apple prolazi na fixture');
check((int)$appleOnly['summary']['incoming'] === 1, 'brand=apple filtrira na 1 model iz fixture-a');
check(($appleOnly['summary']['brand'] ?? '') === 'apple', 'summary.brand=apple');

$bad = phoneCatalogWikidataSync(false, null, 'nije-brend');
check(empty($bad['ok']), 'nepoznat brand vraća grešku');

check(phoneCatalogWikidataLabelAllowed('nokia', 'Nokia G22'), 'nokia label ok');
check(!phoneCatalogWikidataLabelAllowed('nokia', 'Samsung Galaxy A54'), 'nokia odbija samsung label');
check(phoneCatalogWikidataLabelAllowed('vivo', 'Vivo X100'), 'vivo label ok');

echo $failed === 0 ? "\nSvi testovi prolaze.\n" : "\nNeuspešnih: {$failed}\n";
exit($failed === 0 ? 0 : 1);
