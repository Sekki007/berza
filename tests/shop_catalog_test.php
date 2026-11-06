<?php

declare(strict_types=1);

/**
 * Katalog izloga: telefoni bez opreme/satova/tableta, filteri, linkovi, prazna polja, raspored.
 * Pokretanje: php tests/shop_catalog_test.php
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/izlog/demo-izlog';
$_SERVER['HTTP_USER_AGENT'] = '';
ini_set('display_errors', '0');

$dataDir = dirname(__DIR__) . '/data';
$restorePaths = [
    $dataDir . '/ads.json',
    $dataDir . '/users.json',
    $dataDir . '/notifications.json',
    $dataDir . '/ratings.json',
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

$phone = [
    'id' => 11,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'iPhone 13 128GB',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'storage' => '128GB',
    'condition_state' => 'Kao novo',
    'battery_health' => 95,
    'price' => 400,
    'price_type' => 'fixed',
    'currency' => 'eur',
    'location' => 'Beograd',
    'is_active' => 1,
    'created_by' => 42,
];
$airpodsTyped = [
    'id' => 12,
    'ad_type' => 'telefon',
    'device_type' => 'earbuds',
    'title' => 'AirPods Pro',
    'brand' => 'Apple',
    'model' => 'AirPods Pro',
    'price' => 80,
    'price_type' => 'fixed',
];
$airpodsTitle = [
    'id' => 13,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Apple AirPods 2',
    'brand' => 'Apple',
    'model' => 'AirPods',
    'price' => 40,
    'price_type' => 'fixed',
];
$watch = [
    'id' => 14,
    'ad_type' => 'telefon',
    'device_type' => 'watch',
    'title' => 'Apple Watch SE',
    'brand' => 'Apple',
    'model' => 'Watch SE',
    'price' => 180,
    'price_type' => 'fixed',
];
$watchTitle = [
    'id' => 15,
    'ad_type' => 'telefon',
    'title' => 'Galaxy Watch 6',
    'brand' => 'Samsung',
    'model' => 'Galaxy Watch 6',
    'price' => 150,
    'price_type' => 'fixed',
];
$tablet = [
    'id' => 16,
    'ad_type' => 'telefon',
    'device_type' => 'tablet',
    'title' => 'iPad 9',
    'brand' => 'Apple',
    'model' => 'iPad',
    'price' => 220,
    'price_type' => 'fixed',
];
$tabletTitle = [
    'id' => 17,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Samsung Galaxy Tab A8',
    'model' => 'Galaxy Tab A8',
    'price' => 160,
    'price_type' => 'fixed',
];
$mask = [
    'id' => 18,
    'ad_type' => 'delovi',
    'equipment_type' => 'Maska/Futrola',
    'title' => 'Maska za iPhone 13',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'price' => 8,
    'price_type' => 'fixed',
];
$headphones = [
    'id' => 19,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Bežične slušalice',
    'model' => 'Buds',
    'price' => 25,
    'price_type' => 'fixed',
];
$phoneWithAccessory = [
    'id' => 20,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'iPhone 13 128GB slušalice u kutiji',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'storage' => '128GB',
    'price' => 380,
    'price_type' => 'fixed',
];

$catalog = [$phone, $airpodsTyped, $airpodsTitle, $watch, $watchTitle, $tablet, $tabletTitle, $mask, $headphones, $phoneWithAccessory];
$phonesOnly = filterShopAds($catalog, ['type' => 'telefon']);
$phoneIds = array_map(static fn(array $ad): int => (int)$ad['id'], $phonesOnly);
sort($phoneIds);
check($phoneIds === [11, 20], 'tab Telefoni ostavlja samo telefone');
check(!in_array(12, $phoneIds, true) && !in_array(13, $phoneIds, true), 'AirPods nisu u telefonima');
check(!in_array(14, $phoneIds, true) && !in_array(15, $phoneIds, true), 'satovi nisu u telefonima');
check(!in_array(16, $phoneIds, true) && !in_array(17, $phoneIds, true), 'tableti nisu u telefonima');
check(!in_array(18, $phoneIds, true) && !in_array(19, $phoneIds, true), 'oprema i slušalice nisu u telefonima');

$counts = shopAdTypeCounts($catalog);
check($counts['telefon'] === 2, 'broj telefona ne uključuje opremu, satove i tablete');
check($counts['delovi'] === 1, 'maska ostaje u delovima');
check($counts['all'] === count($catalog), 'tab Sve i dalje broji sve aktivne oglase');

$allIds = array_map(static fn(array $ad): int => (int)$ad['id'], filterShopAds($catalog, []));
check(count($allIds) === count($catalog), 'bez taba svi oglasi ostaju u katalogu');

$spec = shopCatalogLayoutSpec();
check(shopCatalogGridColumns(1366) === 4, '1366px ima 4 kartice');
check(shopCatalogGridColumns(1280) === 4, '1280px ima 4 kartice');
check(shopCatalogGridColumns(1279) === 3, 'uži laptop ima 3 kartice');
check(shopCatalogGridColumns(1024) === 3, '1024px ima 3 kartice');
check(shopCatalogGridColumns(900) === 3, '900px ima 3 kartice');
check(shopCatalogGridColumns(899) === 2 && shopCatalogGridColumns(390) === 2, 'mobilni ima 2 kartice');
check(shopCatalogBatteryFilterVisible(1366) && shopCatalogBatteryFilterVisible(768), 'BH filter ostaje na desktopu');
check(!shopCatalogBatteryFilterVisible(767) && !shopCatalogBatteryFilterVisible(390), 'BH filter je skriven samo na mobilnom');

$css = (string)file_get_contents(dirname(__DIR__) . '/public/assets/css/style.css');
$layoutCss = '';
if (preg_match('/shop-catalog-layout:start(.*?)shop-catalog-layout:end/s', $css, $layoutMatch) === 1) {
    $layoutCss = $layoutMatch[1];
}
check($layoutCss !== '' && str_contains($layoutCss, (string)$spec['marker']), 'CSS marker prati specifikaciju rasporeda');
check(preg_match('/\.listings\.shop-listings\{[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/', $layoutCss) === 1, 'podrazumevani raspored je 2 kolone');
check(preg_match('/@media\s*\(\s*min-width:\s*900px\s*\)\s*\{[^}]*repeat\(3,\s*minmax\(0,\s*1fr\)\)/', $layoutCss) === 1, 'od 900px raspored je 3 kolone');
check(preg_match('/@media\s*\(\s*min-width:\s*1280px\s*\)\s*\{[^}]*repeat\(4,\s*minmax\(0,\s*1fr\)\)/', $layoutCss) === 1, 'od 1280px raspored je 4 kolone');
check(preg_match('/@media\s*\(\s*max-width:\s*767px\s*\)\s*\{[^}]*\.shop-filter-bh\s*\{[^}]*display:\s*none/', $layoutCss) === 1, 'CSS skriva BH filter ispod 768px');
check(preg_match('/\.shop-card-thumb\{[^}]*aspect-ratio:\s*1\s*\/\s*0\.82/', $layoutCss) === 1, 'slika kartice je oko 18% niža od kvadrata');
check(preg_match('/\.shop-card-thumb img\{[^}]*object-fit:\s*cover/', $layoutCss) === 1, 'slika se ne deformiše');
check(!preg_match('/position\s*:\s*fixed/', $layoutCss), 'katalog ne dodaje fiksnu traku');

$seller = ['id' => 42, 'username' => 'demoradnja', 'shop_slug' => 'demo-izlog', 'shop_name' => 'Demo Radnja'];
$publicUrl = shopCatalogUrl($seller, shopCatalogPublicParams([
    'type' => 'telefon',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'min_price' => '100',
    'max_price' => '400',
    'condition' => 'Kao novo',
    'min_battery' => '85',
    'page' => 2,
]));
check(str_starts_with($publicUrl, '/izlog/demo-izlog?'), 'link filtera ostaje na izlogu');
foreach (['type=telefon', 'brand=Apple', 'model=iPhone+13', 'min_price=100', 'max_price=400', 'min_battery=85', 'page=2'] as $part) {
    check(str_contains($publicUrl, $part), 'link sadrži ' . $part);
}
check(substr_count($publicUrl, 'condition=') === 1, 'stanje je u linku');

$evilParams = shopCatalogPublicParams([
    'brand' => "Apple' OR '1'='1",
    'model' => '<script>alert(1)</script>',
    'condition' => "Novo'; DROP TABLE ads",
    'min_battery' => '85 OR 1=1',
    'min_price' => '0 OR 1=1',
    'max_price' => '999999999',
    'type' => 'telefon;delete',
    'q' => "' UNION SELECT",
]);
$evilUrl = shopCatalogUrl($seller, $evilParams);
check(!str_contains($evilUrl, 'DROP') && !str_contains($evilUrl, '<') && !str_contains($evilUrl, "'") && !str_contains($evilUrl, '--'), 'filter link ne prosleđuje navodnike, tagove ni SQL komentar');
check(!str_contains($evilUrl, 'min_battery=') && !str_contains($evilUrl, 'min_price='), 'neispravna cijena i BH ne ulaze u link');
check(!shopCatalogFiltersNeedNoindex(['min_battery' => '85 OR 1=1']), 'neispravan BH ne pravi poseban indeks');

$evilFiltered = filterShopAds($catalog, [
    'brand' => "Apple' OR '1'='1",
    'model' => "1' OR '1'='1",
    'condition' => "Novo' OR '1'='1",
    'min_battery' => '0 OR 1=1',
    'min_price' => '1; DROP TABLE ads',
]);
check(count($evilFiltered) < count($catalog), 'pokvaren filter ne vraća cijeli katalog');

$canonical = shopCatalogUrl($seller, shopCatalogCanonicalParams([
    'cat' => 'iphone',
    'brand' => 'Apple',
    'min_battery' => '85',
    'type' => 'telefon',
    'page' => 3,
]));
check($canonical === '/izlog/demo-izlog', 'kanonski URL ignoriše filtere koji nisu stvarna kategorija');
check(shopCatalogFiltersNeedNoindex(['type' => 'telefon', 'brand' => 'Apple', 'page' => 2]) === true, 'filter i strana dobijaju noindex');
check(shopCatalogFiltersNeedNoindex(['cat' => 'iphone']) === false, 'sama kategorija izloga ostaje indeksabilna');

$withChannels = shopSellerExtraContactLinks([
    'shop_name' => 'Demo Radnja',
    'phone' => '0601112233',
    'shop_page_contact_whatsapp' => '0609998888',
    'shop_page_contact_viber' => '0607776666',
]);
$channelKeys = array_column($withChannels, 'key');
check(in_array('viber', $channelKeys, true), 'Viber se dodaje samo iz sopstvenog polja izloga');
if (!empty(siteSettings()['enable_whatsapp'])) {
    check(in_array('whatsapp', $channelKeys, true), 'WhatsApp se dodaje kad je uključen i broj postoji');
} else {
    check(!in_array('whatsapp', $channelKeys, true), 'isključen WhatsApp se ne prikazuje ni uz sačuvan broj');
}
check(shopSellerExtraContactLinks(['phone' => '0601112233', 'shop_name' => 'Demo Radnja']) === [], 'sam telefon ne uključuje Viber ni WhatsApp');
check(shopSellerCallPhone(['phone' => '060 111 2233']) === '060 111 2233', 'Pozovi koristi broj profila');
check(shopSellerCallPhone(['phone' => 'nema'], [
    ['contact_phone' => '0601112233'],
    ['contact_phone' => '0601112233'],
]) === '0601112233', 'jedinstveni broj oglasa može da bude Pozovi');
check(shopSellerCallPhone(['phone' => ''], [
    ['contact_phone' => '0601112233'],
    ['contact_phone' => '0610000000'],
]) === '', 'različiti brojevi se ne spajaju u jedan kontakt');
check(adTelHref('"><script>') === '', 'Pozovi i dalje odbija tekst umjesto broja');

$sparse = shopCatalogCardHtml([
    'id' => 21,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Samo naslov telefona',
    'price' => 50,
    'price_type' => 'fixed',
    'currency' => 'eur',
]);
check(str_contains($sparse, 'Samo naslov telefona'), 'kartica prikazuje naslov');
check(str_contains($sparse, '>Detalji<') && str_contains($sparse, '>Kontakt<'), 'kartica ima Detalji i Kontakt');
check(str_contains($sparse, 'href="/oglas/21-samo-naslov-telefona"') && str_contains($sparse, 'href="/oglas/21-samo-naslov-telefona#kontakt"'), 'Kontakt vodi na oglas, ne na prijavu');
check(!str_contains($sparse, '/prijava') && !str_contains($sparse, 'login.php'), 'kartica nema login zid');
check(!str_contains($sparse, 'shop-card-chip') && !str_contains($sparse, 'BH ') && !str_contains($sparse, 'shop-card-loc'), 'prazna polja se ne iscrtavaju');
check(!str_contains($sparse, 'Facemobile') && !str_contains($sparse, '1.320'), 'kartica ne ubacuje podatke iz mockupa');

$fullCard = shopCatalogCardModel($phone);
check($fullCard['brand'] === 'Apple' && $fullCard['battery'] === 95 && $fullCard['storage'] === '128GB', 'puna kartica čita stvarna polja');
check($fullCard['contact_href'] === $fullCard['detail_href'] . '#kontakt', 'Kontakt je nastavak putanje oglasa');

file_put_contents($dataDir . '/users.json', json_encode([
    [
        'id' => 42,
        'username' => 'demoradnja',
        'shop_slug' => 'demo-izlog',
        'shop_name' => 'Demo Radnja',
        'full_name' => 'Demo Radnja',
        'phone' => '0601112233',
        'shop_bio' => '',
        'created_at' => '2026-01-01 00:00:00',
    ],
], JSON_UNESCAPED_UNICODE));
$pageAds = [$phone, $airpodsTitle, $watch, $tablet, $mask];
foreach ($pageAds as &$pageAd) {
    $pageAd['created_by'] = 42;
    $pageAd['is_active'] = 1;
    $pageAd['is_sold'] = 0;
}
unset($pageAd);
$pageAds[] = [
    'id' => 22,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Prodati telefon',
    'brand' => 'Apple',
    'model' => 'iPhone 11',
    'price' => 90,
    'price_type' => 'fixed',
    'is_active' => 1,
    'is_sold' => 1,
    'created_by' => 42,
];
$pageAds[] = [
    'id' => 21,
    'ad_type' => 'telefon',
    'device_type' => 'phone',
    'title' => 'Samo naslov telefona',
    'price' => 50,
    'price_type' => 'fixed',
    'currency' => 'eur',
    'is_active' => 1,
    'created_by' => 42,
];
file_put_contents($dataDir . '/ads.json', json_encode($pageAds, JSON_UNESCAPED_UNICODE));
file_put_contents($dataDir . '/ratings.json', '[]');

function renderShop(array $query): string
{
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/izlog/demo-izlog' . ($query === [] ? '' : '?' . http_build_query($query));
    ob_start();
    include dirname(__DIR__) . '/public/izlog.php';
    return (string)ob_get_clean();
}

$html = renderShop(['u' => 'demo-izlog']);
check($html !== '' && !str_contains($html, 'Fatal error'), 'izlog se renderuje');
check(preg_match('/class="shop-call" href="([^"]+)"/', $html, $callMatch) === 1 && str_contains($callMatch[1], 'pozovi=1') && !preg_match('/\d{6}/', $callMatch[1]) && str_contains($html, 'Pozovi'), 'Pozovi vodi na zahtjev bez broja u linku');
check(!str_contains($html, 'tel:') && !str_contains($html, '0601112233'), 'stranica izloga ne ispisuje broj ni tel href');
check(!str_contains($html, 'shop-phone-note') && !str_contains($html, 'shop-link-input'), 'nema vidljivog broja ni velikog URL polja');
check(str_contains($html, 'Više:') && str_contains($html, 'Kopiraj link') && str_contains($html, 'Prijavi korisnika'), 'sporedne akcije su u liniji Više');
check(!str_contains($html, 'shop-extra-contact'), 'nema Viber/WhatsApp bez podataka izloga');
check(!str_contains($html, 'class="shop-bio"'), 'prazan opis se ne prikazuje');
check(!str_contains($html, 'business-badge') && !str_contains($html, 'Verifikovan izlog'), 'bedž se ne prikazuje ako prodavac nije verifikovan');
check(shopHeaderVerifiedBadgeHtml(['verified_seller' => true]) !== '' && str_contains(shopHeaderVerifiedBadgeHtml(['verified_seller' => true]), 'Verifikovan izlog'), 'verifikovan izlog dobija plavi tekstualni bedž');
check(shopHeaderVerifiedBadgeHtml(['verified_seller' => false, 'shop_name' => 'Demo']) === '', 'neverifikovan izlog nema bedž');
check(!str_contains($html, 'kp-online') && !str_contains($html, '>Online<'), 'online status se ne izmišlja');
check(str_contains($html, 'shop-filter-bh') && str_contains($html, 'name="min_battery"'), 'BH filter postoji u formi');
check(str_contains($html, 'name="brand"') && str_contains($html, 'name="model"') && str_contains($html, 'name="min_price"') && str_contains($html, 'name="condition"'), 'filteri brend, model, cijena i stanje postoje');
check(str_contains($html, '>Detalji<') && str_contains($html, '>Kontakt<') && str_contains($html, '#kontakt'), 'kartice vode na detalj i kontakt');
check(!str_contains($html, 'Naruči / Prijavi se') && !str_contains($html, 'shop-order-bar'), 'nema login zida umjesto kontakta');
preg_match_all('/data-shop-contact href="([^"]+)"/', $html, $contactHrefs);
check($contactHrefs[1] !== [] && !array_filter($contactHrefs[1], static fn(string $href): bool => str_contains($href, 'prijava') || str_contains($href, 'login') || !str_contains($href, '#kontakt')), 'Kontakt na kartici otvara oglas bez prijave');
check(!str_contains($html, 'shop-sticky') && !str_contains($html, 'shop-mobile-dock') && !str_contains($html, 'storefront-sticky-contact'), 'nema nove mobilne trake');
check(str_contains($html, 'iPhone 13 128GB') && str_contains($html, 'Apple AirPods 2') && str_contains($html, 'iPad 9'), 'tab Sve prikazuje i telefone i ostale uređaje');
check(!str_contains($html, 'Prodati telefon'), 'prodat oglas nije u aktivnom katalogu');
check(!str_contains($html, 'name="robots"'), 'osnovni izlog nema noindex');

$phonesHtml = renderShop(['u' => 'demo-izlog', 'type' => 'telefon']);
check(str_contains($phonesHtml, 'iPhone 13 128GB') && str_contains($phonesHtml, 'Samo naslov telefona'), 'filter telefona prikazuje telefone');
check(!str_contains($phonesHtml, 'Apple AirPods 2') && !str_contains($phonesHtml, 'Apple Watch SE') && !str_contains($phonesHtml, 'iPad 9') && !str_contains($phonesHtml, 'Maska za iPhone 13'), 'filter telefona izbacuje opremu, sat i tablet');
check(str_contains($phonesHtml, 'name="robots" content="noindex,follow"'), 'filter telefona je noindex');
check(preg_match('/<link rel="canonical" href="[^"]*\/izlog\/demo-izlog">/', $phonesHtml) === 1, 'kanonski link filtera je čist izlog');

$filteredHtml = renderShop([
    'u' => 'demo-izlog',
    'brand' => "Apple' OR '1'='1",
    'q' => '<script>alert(1)</script>',
    'min_battery' => '85',
]);
check(str_contains($filteredHtml, 'noindex,follow'), 'kombinovani filter je noindex');
check(!str_contains($filteredHtml, '<script>alert') && !str_contains($filteredHtml, "OR '1'"), 'unos filtera je očišćen u HTML-u');
check(preg_match('/<link rel="canonical" href="[^"]*\/izlog\/demo-izlog">/', $filteredHtml) === 1, 'kanonski link ne nosi brand ni BH');

$bridge = renderShop(['u' => 'demo-izlog', 'pozovi' => '1']);
check(str_contains($bridge, 'href="tel:0601112233"') && str_contains($bridge, 'noindex, nofollow'), 'eksplicitni Pozovi vraća tel i noindex');
check(!str_contains($bridge, 'shop-card') && !str_contains($bridge, 'Demo Radnja'), 'poziv ne renderuje katalog');
ob_start();
shopEmitCallBridge('javascript:alert(1)');
$blockedBridge = (string)ob_get_clean();
check(str_contains($blockedBridge, 'nije dostupan') && !str_contains($blockedBridge, 'javascript:'), 'poziv odbija nebezbjednu šemu');

if ($failed > 0) {
    echo "\n{$failed} provera nije prošlo.\n";
    exit(1);
}

echo "\nSve provere su prošle.\n";
exit(0);
