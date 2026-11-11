<?php

declare(strict_types=1);

/**
 * Katalog telefona: poklapanje modela, cene, dozvole, rute i forma.
 * Pokretanje: php tests/phone_catalog_test.php
 */

define('KT_SKIP_BACKGROUND', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/katalog';
$_SERVER['HTTPS'] = '';
$_GET = [];

$dataDir = dirname(__DIR__) . '/data';
$restorePaths = [
    $dataDir . '/ads.json',
    $dataDir . '/notifications.json',
    $dataDir . '/expiry_state.json',
    $dataDir . '/phone_models.json',
];
$restore = [];
foreach ($restorePaths as $path) {
    $restore[$path] = is_file($path) ? (string)file_get_contents($path) : null;
}
register_shutdown_function(static function () use ($restore): void {
    foreach ($restore as $path => $contents) {
        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
            continue;
        }
        file_put_contents($path, $contents);
    }
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

function catalogPage(string $uri): string
{
    $root = dirname(__DIR__);
    $query = [];
    $parts = parse_url($uri);
    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
    }
    $code = '$_SERVER["REQUEST_METHOD"]="GET";'
        . '$_SERVER["HTTP_HOST"]="localhost";'
        . '$_SERVER["HTTPS"]="";'
        . '$_SERVER["REQUEST_URI"]=' . var_export($uri, true) . ';'
        . '$_SERVER["QUERY_STRING"]=' . var_export((string)($parts['query'] ?? ''), true) . ';'
        . '$_GET=' . var_export($query, true) . ';'
        . 'require ' . var_export($root . '/router.php', true) . ';';
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);
    $output = shell_exec($cmd . ' 2>&1');
    return is_string($output) ? $output : '';
}

$bundle = phoneCatalogBundle();
check(!empty($bundle['ok']), 'seed prolazi proveru: ' . implode('; ', $bundle['errors']));
check(phoneCatalogModel('apple-iphone-15') !== null, 'iPhone 15 je u katalogu');
check(phoneCatalogFindBySlug('apple', 'iphone-15')['id'] === 'apple-iphone-15', 'URL slug apple/iphone-15');
check(phoneCatalogUrl(phoneCatalogModel('apple-iphone-15')) === '/katalog/apple/iphone-15', 'putanja modela je čitljiva');

$seed = json_decode((string)file_get_contents(phoneCatalogSeedPath()), true);
$broken = $seed;
$broken['models'][0]['specs'] = [[
    'group' => 'Ekran',
    'label' => 'Dijagonala',
    'value' => '6,1 inča',
    'source_id' => 'nema-izvora',
]];
check(phoneCatalogValidateBundle($broken) !== [], 'specifikacija bez izvora ne prolazi');
$brokenImage = $seed;
$brokenImage['models'][0]['image'] = ['url' => 'https://example.com/phone.jpg', 'license' => 'all-rights-reserved'];
check(phoneCatalogValidateBundle($brokenImage) !== [], 'slika bez dozvoljene licence ne prolazi');
$iphone15 = phoneCatalogModel('apple-iphone-15');
check($iphone15['specs'] !== [] && $iphone15['variants'] !== [], 'iPhone 15 ima proverene specifikacije i varijante');
check(phoneCatalogModel('apple-iphone-14-pro')['specs'] === [], 'model bez proizvođačkog izvoda nema izmišljene specifikacije');
$sourceIds = array_column(phoneCatalogSourcesForModel($iphone15), 'url');
check(in_array('https://support.apple.com/en-us/111831', $sourceIds, true), 'iPhone 15 citira Apple Support');

$base = [
    'is_active' => 1,
    'is_sold' => 0,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'listing_type' => 'sell',
    'price_type' => 'fixed',
    'currency' => 'eur',
    'expires_at' => '2099-01-01 00:00:00',
    'created_at' => '2026-11-01 10:00:00',
    'created_by' => 1,
    'description' => 'Opis koji mora ostati netaknut.',
    'contact_phone' => '0615550199',
];
$ads = [
    array_merge($base, ['id' => 501, 'title' => 'iPhone 15 128GB crni', 'brand' => 'Apple', 'model' => 'iPhone 15', 'storage' => '128 GB', 'price' => 420, 'condition_state' => 'Polovno', 'location' => 'Beograd', 'battery_health' => 95, 'has_warranty' => 1]),
    array_merge($base, ['id' => 502, 'title' => 'iPhone 15', 'brand' => 'Apple', 'model' => 'iPhone 15', 'storage' => '256GB', 'price' => 0, 'price_type' => 'negotiable', 'condition_state' => 'Novo', 'location' => 'Novi Sad']),
    array_merge($base, ['id' => 503, 'title' => 'iPhone 15 Pro 256GB', 'brand' => 'Apple', 'model' => 'iPhone 15 Pro', 'storage' => '256GB', 'price' => 900, 'condition_state' => 'Polovno', 'location' => 'Niš']),
    array_merge($base, ['id' => 504, 'title' => 'Apple Watch SE', 'brand' => 'Apple', 'model' => 'Watch SE', 'device_type' => 'watch', 'price' => 180]),
    array_merge($base, ['id' => 505, 'title' => 'Maska za iPhone 15', 'brand' => 'Apple', 'model' => 'iPhone 15', 'ad_type' => 'delovi', 'price' => 8]),
    array_merge($base, ['id' => 506, 'title' => 'iPhone 15 ekran', 'brand' => 'Apple', 'model' => 'iPhone 15', 'price' => 40]),
    array_merge($base, ['id' => 507, 'title' => 'Samsung A54 5G 128GB', 'brand' => 'Samsung', 'model' => 'Galaxy A54', 'storage' => '128 GB', 'price' => 180, 'condition_state' => 'Polovno', 'location' => 'Beograd']),
    array_merge($base, ['id' => 508, 'title' => 'Samsung Galaxy S23 Ultra', 'brand' => 'Samsung', 'model' => 'Galaxy S23 Ultra', 'storage' => '256GB', 'price' => 700]),
    array_merge($base, ['id' => 509, 'title' => 'iPad 9', 'brand' => 'Apple', 'model' => 'iPad', 'device_type' => 'tablet', 'price' => 220]),
    array_merge($base, ['id' => 510, 'title' => 'iPhone 15 Pro Max', 'brand' => 'Apple', 'model' => 'iPhone 15 Pro Max', 'storage' => '256GB', 'price' => 1100]),
    array_merge($base, ['id' => 511, 'title' => 'iPhone 15 128GB', 'brand' => 'Apple', 'model' => 'iPhone 15', 'storage' => '128GB', 'price' => 330, 'condition_state' => 'Novo', 'location' => 'Beograd', 'is_sold' => 1]),
];

$match15 = phoneCatalogMatchAd($ads[0]);
check($match15['model_id'] === 'apple-iphone-15' && $match15['variant_id'] === '128gb', 'iPhone 15 128 GB dobija model i varijantu');
check(phoneCatalogMatchAd($ads[2])['model_id'] === 'apple-iphone-15-pro', 'iPhone 15 Pro ne pripada običnom iPhone 15');
check(phoneCatalogMatchAd($ads[3])['status'] === 'skip', 'sat nije telefon');
check(phoneCatalogMatchAd($ads[4])['status'] === 'skip', 'maska nije telefon');
check(phoneCatalogMatchAd($ads[5])['status'] === 'skip', 'ekran nije telefon');
check(phoneCatalogMatchAd($ads[6])['model_id'] === 'samsung-galaxy-a54', 'Galaxy A54 5G se vezuje na A54');
check(phoneCatalogMatchAd($ads[7])['model_id'] === 'samsung-galaxy-s23-ultra', 'S23 Ultra ne ide na običan S23');
check(phoneCatalogMatchAd($ads[8])['status'] === 'skip', 'tablet nije telefon');
check(phoneCatalogMatchAd($ads[9])['status'] === 'unmatched', 'iPhone 15 Pro Max se ne vezuje na kraći model');

$manual = $ads[0];
$manual['phone_model_link'] = ['method' => 'manual'];
$seller = $ads[0];
$seller['phone_model_id'] = 'apple-iphone-12';
$seller['phone_model_link'] = ['method' => 'seller'];
$plan = phoneCatalogPlanLinks(array_merge($ads, [array_merge($manual, ['id' => 512]), array_merge($seller, ['id' => 513])]), false, false);
check($plan['summary']['linked'] >= 3, 'dry-run prepoznaje telefone');
check($plan['summary']['skipped_not_phone'] >= 4, 'sat, tablet, maska i ekran su preskočeni');
check($plan['summary']['skipped_manual'] === 1, 'ručni unos se ne prepisuje');
check($plan['summary']['skipped_seller'] === 1, 'prodavčeva veza se ne prepisuje');
$applied = phoneCatalogPlanLinks($ads, true, false);
$linked501 = null;
foreach ($applied['ads'] as $row) {
    if ((int)$row['id'] === 501) {
        $linked501 = $row;
    }
    if ((int)$row['id'] === 504) {
        check(!isset($row['phone_model_id']), 'sat ne dobija model_id');
        check($row['contact_phone'] === '0615550199' && $row['description'] === 'Opis koji mora ostati netaknut.' && (float)$row['price'] === 180.0, 'javna polja oglasa ostaju ista');
    }
}
check(is_array($linked501) && $linked501['phone_model_id'] === 'apple-iphone-15' && $linked501['phone_variant_id'] === '128gb', 'apply upisuje model i varijantu');
check($linked501['title'] === 'iPhone 15 128GB crni' && $linked501['description'] === 'Opis koji mora ostati netaknut.' && $linked501['contact_phone'] === '0615550199', 'apply ne menja naslov, opis ni telefon');
$summaryJson = json_encode($plan['summary'], JSON_UNESCAPED_UNICODE);
check(!str_contains((string)$summaryJson, '0615550199'), 'izveštaj veze ne sadrži broj telefona');

$offers = phoneCatalogOffersByModel($applied['ads']);
$stats = phoneCatalogPriceStats($offers['apple-iphone-15'] ?? []);
check($stats['active'] === 2 && $stats['fixed'] === 1 && $stats['negotiable'] === 1, 'po dogovoru je u broju, a prodato nije');
check($stats['min'] === 420.0 && $stats['max'] === 420.0 && $stats['median'] === 420.0, 'raspon ne koristi po dogovoru');
$pair = phoneCatalogPriceStats([
    $base + ['id' => 1, 'title' => 'iPhone 15', 'brand' => 'Apple', 'model' => 'iPhone 15', 'price' => 100, 'phone_model_id' => 'apple-iphone-15'],
    $base + ['id' => 2, 'title' => 'iPhone 15', 'brand' => 'Apple', 'model' => 'iPhone 15', 'price' => 300, 'phone_model_id' => 'apple-iphone-15'],
]);
check($pair['median'] === 200.0 && $pair['min'] === 100.0 && $pair['max'] === 300.0, 'medijana paranog broja je prosek srednje dve');

$filters = phoneCatalogSanitizeIndexQuery(['with_ads' => '1', 'price_band' => 'gt600'], $offers);
$visible = phoneCatalogFilterModels($filters, $offers);
$visibleIds = array_column($visible, 'id');
check(!in_array('apple-iphone-15', $visibleIds, true), 'iPhone 15 od 420 € ne ulazi u opseg 600+');
check(in_array('apple-iphone-15-pro', $visibleIds, true), 'iPhone 15 Pro od 900 € ulazi u opseg 600+');
$batteryFilters = phoneCatalogSanitizeIndexQuery(['min_battery' => '90'], $offers);
$batteryIds = array_column(phoneCatalogFilterModels($batteryFilters, $offers), 'id');
check(in_array('apple-iphone-15', $batteryIds, true) && !in_array('apple-iphone-15-pro', $batteryIds, true), 'BH filter traži oglas koji stvarno ima battery health');
$noBatteryFacets = phoneCatalogIndexFacets([]);
check($noBatteryFacets['battery'] === false, 'BH filter se ne nudi kad nijedan oglas nema podatak');

$assignment = phoneCatalogAssignmentFromPost([
    'phone_model_id' => 'apple-iphone-15',
    'phone_variant_id' => '256gb',
    'description' => 'Ne diraj ovo',
], 'telefon', 'phone', '128GB', 'Ostalo', 'nešto drugo');
check($assignment['phone_model_id'] === 'apple-iphone-15' && $assignment['phone_variant_id'] === '256gb', 'prodavac bira model i varijantu');
check($assignment['brand'] === 'Apple' && $assignment['model'] === 'iPhone 15' && $assignment['storage'] === '256GB', 'izbor popunjava brend, naziv i memoriju varijante');
check(!array_key_exists('description', $assignment) && !array_key_exists('price', $assignment) && !array_key_exists('contact_phone', $assignment), 'dodeljivanje ne dira opis, cenu ni telefon');
$manualAssignment = phoneCatalogAssignmentFromPost(['phone_model_id' => ''], 'telefon', 'phone', '64GB', 'Samsung', 'A10');
check($manualAssignment['phone_model_id'] === null && $manualAssignment['phone_model_link']['method'] === 'manual' && !array_key_exists('brand', $manualAssignment), 'ručni unos ne dobija lažan model i ne briše brend');
$unknown = phoneCatalogAssignmentFromPost(['phone_model_id' => 'nema-ga'], 'telefon', 'phone', '', '', '');
check($unknown['phone_model_id'] === null, 'nepoznat model_id se odbija');
$foreignVariant = phoneCatalogAssignmentFromPost(['phone_model_id' => 'apple-iphone-15', 'phone_variant_id' => '1tb'], 'telefon', 'phone', '', 'Apple', 'iPhone 15');
check($foreignVariant['phone_model_id'] === 'apple-iphone-15' && $foreignVariant['phone_variant_id'] === null, 'varijanta drugog modela se ne prihvata');
$watchAssignment = phoneCatalogAssignmentFromPost(['phone_model_id' => 'apple-iphone-15'], 'telefon', 'watch', '', 'Apple', 'Watch');
check($watchAssignment['phone_model_id'] === null && $watchAssignment['phone_model_link'] === null, 'sat ne može da nosi model telefona');

file_put_contents($dataDir . '/ads.json', json_encode($applied['ads'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
phoneCatalogResetCache();

$index = catalogPage('/katalog');
check(str_contains($index, 'Katalog telefona') && str_contains($index, 'href="/katalog"'), 'katalog se otvara na /katalog');
check(str_contains($index, '2 aktivnih oglasa'), 'kartica prikazuje stvaran broj oglasa');
check(str_contains($index, '420') && str_contains($index, '/katalog/apple/iphone-15'), 'kartica vodi na model i prikazuje stvarnu cenu');
check(!str_contains($index, '0615550199') && !str_contains($index, 'tel:0615550199'), 'katalog ne ispisuje broj iz oglasa');
check(!str_contains($index, 'Predlog dizajna') && !str_contains(strtolower($index), 'gsmarena') && !str_contains(strtolower($index), '<iframe'), 'nema oznake predloga, tuđeg kataloga ni iframe-a');
check(str_contains($index, 'name="min_battery"'), 'BH filter se vidi kad oglas ima battery health');
check(str_contains($index, 'rel="canonical" href="http://localhost/katalog"'), 'kanonski URL kataloga je čist');

$withAds = catalogPage('/katalog?with_ads=1');
check(!str_contains($withAds, 'iPhone 11'), 'filter samo modeli sa oglasima sklanja modele bez oglasa');
check(str_contains($withAds, 'noindex,follow'), 'filter kataloga je noindex');

$modelPage = catalogPage('/katalog/apple/iphone-15');
check(str_contains($modelPage, '<h1>iPhone 15</h1>') && str_contains($modelPage, 'A16 Bionic'), 'stranica modela ima naziv i proverenu specifikaciju');
check(str_contains($modelPage, 'support.apple.com/en-us/111831'), 'stranica modela navodi Apple izvor');
check(str_contains($modelPage, 'Po dogovoru') && str_contains($modelPage, phoneCatalogPriceMethodology()), 'vidi se po dogovoru i metodologija');
check(str_contains($modelPage, 'iPhone 15 128GB crni') && !str_contains($modelPage, 'iPhone 15 Pro 256GB'), 'na modelu su samo njegovi aktivni oglasi');
check(!str_contains($modelPage, '0615550199'), 'stranica modela ne ispisuje broj iz oglasa');
check(str_contains($modelPage, 'rel="canonical" href="http://localhost/katalog/apple/iphone-15"'), 'kanonski URL modela');
check(str_contains($modelPage, 'name="garancija"') && str_contains($modelPage, 'name="grad"'), 'garancija i grad se nude samo jer su u strukturisanim podacima');

$emptyModel = catalogPage('/katalog/apple/iphone-12');
check(str_contains($emptyModel, 'Trenutno nema aktivnih oglasa za Apple iPhone 12') && !str_contains($emptyModel, 'od 0'), 'prazan model nema izmišljenu cenu');
check(!str_contains($emptyModel, 'A16 Bionic'), 'model bez izvora ne dobija specifikacije iPhone 15');

$missing = catalogPage('/katalog/apple/iphone-17-pro-max');
check(str_contains($missing, 'Model nije u katalogu'), 'nepoznat model je 404, bez izmišljenog unosa');

$sitemap = catalogPage('/sitemap.xml');
check(str_contains($sitemap, '/katalog</loc>') && str_contains($sitemap, '/katalog/apple/iphone-15</loc>'), 'sitemap ima katalog i model');
check(!str_contains($sitemap, '/katalog?'), 'sitemap nema filtrirane URL-ove kataloga');

$before = md5((string)file_get_contents($dataDir . '/ads.json'));
shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/link_phone_models.php') . ' 2>&1');
$after = md5((string)file_get_contents($dataDir . '/ads.json'));
check($before === $after, 'dry-run alata za vezu ne upisuje oglase');
$import = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/import_phone_catalog.php') . ' 2>&1');
check(is_string($import) && str_contains($import, 'DRY-RUN') && str_contains($import, 'apple-iphone-15'), 'uvoz bez --apply samo javlja pregled');
check(!is_file($dataDir . '/phone_models.json'), 'dry-run uvoza ne pravi dokument');

$_SERVER['REQUEST_URI'] = '/postavi-oglas?katalog=apple-iphone-15';
$_GET = ['katalog' => 'apple-iphone-15'];
$_SESSION['user'] = findUserById(1);
ob_start();
include dirname(__DIR__) . '/public/ad_form.php';
$form = (string)ob_get_clean();
check(str_contains($form, 'name="phone_model_id"') && (str_contains($form, 'Ručni unos') || str_contains($form, 'ručni unos')), 'forma ima izbor modela i ručni unos');
check(str_contains($form, 'value="apple-iphone-15"') && str_contains($form, 'selected'), 'link sa modela unapred bira iPhone 15');
check(str_contains($form, 'data-phone-variant-chips') && str_contains($form, 'data-storage="128GB"') && str_contains($form, 'data-storage="256GB"'), 'forma prikazuje čipove memorije za iPhone 15');
check(str_contains($form, 'name="description"') && !str_contains($form, 'A16 Bionic'), 'specifikacije se ne upisuju u opis');
check(str_contains($form, 'name="condition_state"') && str_contains($form, 'name="price"') && str_contains($form, 'name="location"'), 'stanje, cena i grad ostaju polja prodavca');

$_SERVER['REQUEST_URI'] = '/postavi-oglas';
$_GET = [];
ob_start();
include dirname(__DIR__) . '/public/ad_form.php';
$formBlank = (string)ob_get_clean();
check(str_contains($formBlank, 'data-phone-variant-chips') && str_contains($formBlank, '>128GB<') && str_contains($formBlank, '>256GB<'), 'prazna forma i dalje nudi čipove memorije');
check(str_contains($formBlank, 'data-default-storage') && str_contains($formBlank, '1TB'), 'fallback memorije je u data-default-storage');

$home = catalogPage('/');
check(str_contains($home, 'href="/katalog"') && str_contains($home, 'Katalog telefona'), 'link kataloga je u navigaciji početne');

if ($failed > 0) {
    echo "\n{$failed} provera nije prošlo.\n";
    exit(1);
}

echo "\nSve provere su prošle.\n";
exit(0);
