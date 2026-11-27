<?php

declare(strict_types=1);

/**
 * Naslovi, canonical, paginacija, legacy 301 ciljevi i sitemap.
 * Pokretanje: php tests/seo_meta_test.php
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'kupitelefon.rs';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_USER_AGENT'] = '';

require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "OK  $message\n";
        return;
    }
    $failed++;
    echo "FAIL  $message\n";
};

$home = seoHomeMeta();
$check($home['title'] === 'Polovni telefoni – kupi i prodaj besplatno | KupiTelefon.rs', 'početna title: ' . $home['title']);
$check(mb_strlen($home['title']) <= 60, 'početna title ≤ 60 (' . mb_strlen($home['title']) . ')');
$check(mb_strlen($home['description']) >= 140 && mb_strlen($home['description']) <= 160, 'početna opis 140–160 (' . mb_strlen($home['description']) . '): ' . $home['description']);
$check($home['h1'] === 'Polovni telefoni, tableti i servis u Srbiji', 'početni H1 ostaje');

$phones = seoListingMeta(['type' => 'telefon'], ['count' => 120]);
$check($phones['title'] === 'Polovni mobilni telefoni – oglasi i cene | KupiTelefon.rs', 'telefoni title: ' . $phones['title']);
$check(mb_strlen($phones['title']) <= 60, 'telefoni title ≤ 60');
$check(($phones['h1'] ?? '') === 'Polovni mobilni telefoni', 'telefoni H1');
$check(str_contains($phones['description'], '120 oglasa'), 'telefoni opis ima broj oglasa');
$check(mb_strlen($phones['description']) <= 160, 'telefoni opis ≤ 160 (' . mb_strlen($phones['description']) . ')');

$brand = seoListingMeta(['brand' => 'Apple'], ['count' => 12]);
$check(str_starts_with($brand['title'], 'Polovni Apple telefoni'), 'brend title: ' . $brand['title']);
$check(str_contains($brand['title'], '12 oglasa'), 'brend title ima broj');
$check(mb_strlen($brand['title']) <= 60, 'brend title ≤ 60 (' . mb_strlen($brand['title']) . ')');
$check(seoListingHeading(['brand' => 'Apple']) === 'Polovni Apple telefoni', 'brend H1');

$city = seoListingMeta(['location' => 'Beograd'], ['count' => 40]);
$check(str_starts_with($city['title'], 'Polovni telefoni Beograd'), 'grad title: ' . $city['title']);
$check(str_contains($city['title'], '40 oglasa'), 'grad title ima broj');
$check(mb_strlen($city['title']) <= 60, 'grad title ≤ 60');

$combo = seoListingMeta(['brand' => 'Apple', 'location' => 'Beograd'], ['count' => 8]);
$check($combo['title'] === 'Polovni Apple telefoni Beograd | KupiTelefon.rs', 'brend+grad title: ' . $combo['title']);
$check(seoListingHeading(['brand' => 'Apple', 'location' => 'Beograd']) === 'Polovni Apple telefoni Beograd', 'brend+grad H1');

$paged = seoAppendPageSuffix($phones['title'], 2);
$check(str_contains($paged, 'strana 2'), 'sufiks strane: ' . $paged);
$check(mb_strlen($paged) <= 60, 'title strane ≤ 60 (' . mb_strlen($paged) . ')');

$model = phoneCatalogFindBySlug('apple', 'iphone-15-pro');
$check(is_array($model), 'katalog ima iphone-15-pro');
if (is_array($model)) {
    $meta = phoneCatalogModelMeta($model, ['active' => 4, 'fixed' => 4, 'min' => 400, 'max' => 700]);
    $check(str_contains($meta['title'], 'cena i polovni oglasi'), 'katalog title: ' . $meta['title']);
    $check(str_contains($meta['title'], 'KupiTelefon.rs'), 'katalog title ima brend sajta');
    $check(mb_strlen($meta['title']) <= 60, 'katalog title ≤ 60 (' . mb_strlen($meta['title']) . ')');
    $check(str_contains($meta['description'], '400') || str_contains($meta['description'], '700'), 'katalog opis pominje raspon');
    $check(mb_strlen($meta['description']) <= 160, 'katalog opis ≤ 160');
}

$check(listingLegacyIndexTarget([]) === '/', 'index.php bez parametara → /');
$check(listingLegacyIndexTarget(['brand' => 'Apple']) === '/oglasi/apple', 'brand=Apple → /oglasi/apple');
$check(listingLegacyIndexTarget(['type' => 'telefon']) === '/oglasi/telefoni', 'type=telefon → /oglasi/telefoni');
$check(listingLegacyIndexTarget(['location' => 'Beograd']) === '/oglasi/grad/beograd', 'location=Beograd → grad');
$check(listingLegacyIndexTarget(['brand' => 'Apple', 'sort' => 'price_asc']) === '/oglasi/apple?sort=price_asc', 'sort ostaje na landingu');
$check(listingLegacyIndexTarget(['q' => 'iphone']) === '/oglasi?q=iphone', 'pretraga ide na /oglasi');
$check(listingLegacyIndexTarget(['page' => '2']) === '/?page=2', 'samo strana ide na početnu');
$check(str_contains(seoWebsiteJsonLd()['potentialAction']['target'], '/oglasi?q='), 'SearchAction cilja /oglasi');

$page2 = listingSeoDecision([
    'landing' => ['filters' => ['type' => 'telefon'], 'path' => '/oglasi/telefoni'],
    'filters' => ['type' => 'telefon'],
    'ads' => [['created_by' => 1], ['created_by' => 2], ['created_by' => 3]],
    'page' => 2,
    'sort' => 'newest',
    'has_extra' => false,
]);
$check($page2['robots'] === '', 'telefoni?page=2 nema noindex');
$check($page2['canonical_path'] === '/oglasi/telefoni?page=2', 'telefoni?page=2 self-canonical');

$sorted = listingSeoDecision([
    'landing' => ['filters' => ['type' => 'telefon'], 'path' => '/oglasi/telefoni'],
    'filters' => ['type' => 'telefon'],
    'ads' => [['created_by' => 1], ['created_by' => 2], ['created_by' => 3]],
    'page' => 1,
    'sort' => 'price_asc',
    'has_extra' => false,
]);
$check($sorted['robots'] === 'noindex,follow', 'sort je noindex');
$check($sorted['canonical_path'] === '/oglasi/telefoni', 'sort canonical je čist landing');

$emptyCity = listingLandingIndexable(['location' => 'Ada'], []);
$check($emptyCity === false, 'grad bez oglasa nije indeksabilan');

$intro = seoListingIntro(['brand' => 'Apple'], [
    ['brand' => 'Apple', 'model' => 'iPhone 13', 'location' => 'Beograd', 'price' => 300, 'currency' => 'eur', 'price_type' => 'fixed'],
    ['brand' => 'Apple', 'model' => 'iPhone 13', 'location' => 'Beograd', 'price' => 400, 'currency' => 'eur', 'price_type' => 'fixed'],
    ['brand' => 'Apple', 'model' => 'iPhone 14', 'location' => 'Novi Sad', 'price' => 500, 'currency' => 'eur', 'price_type' => 'fixed'],
]);
$check(str_contains($intro, '3 aktivnih oglasa') && str_contains($intro, 'iPhone 13') && str_contains($intro, '300'), 'uvod brenda iz podataka: ' . $intro);

$mass = seoMassUpdatedStamps([
    ['updated_at' => '2026-10-05 00:00:00'],
    ['updated_at' => '2026-10-05 00:00:00'],
    ['updated_at' => '2026-10-05 00:00:00'],
]);
for ($i = 0; $i < 20; $i++) {
    $mass = seoMassUpdatedStamps(array_merge(
        array_fill(0, 20, ['updated_at' => '2026-10-05 00:00:00']),
        [['updated_at' => '2026-11-02 12:00:00', 'created_at' => '2026-08-01 10:00:00']]
    ));
}
$check(isset($mass['2026-10-05 00:00:00']), 'masovni updated_at je prepoznat');
$imported = seoAdSitemapLastmod([
    'updated_at' => '2026-10-05 00:00:00',
    'created_at' => '2026-08-14 09:00:00',
], $mass);
$check($imported === '2026-08-14', 'masovni lastmod koristi created_at: ' . $imported);
$edited = seoAdSitemapLastmod([
    'updated_at' => '2026-11-02 12:00:00',
    'created_at' => '2026-08-01 10:00:00',
], $mass);
$check($edited === '2026-11-02', 'pojedinačna izmena zadržava updated_at');

$adsPath = dirname(__DIR__) . '/data/ads.json';
$adsOriginal = (string)file_get_contents($adsPath);
$adsDecoded = json_decode($adsOriginal, true);
if (is_array($adsDecoded)) {
    foreach ($adsDecoded as &$adRow) {
        if (!is_array($adRow) || !empty($adRow['is_sold'])) {
            continue;
        }
        $adRow['is_active'] = 1;
        $adRow['expires_at'] = '2027-12-31 23:59:59';
    }
    unset($adRow);
    file_put_contents($adsPath, json_encode($adsDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
try {
$buckets = seoSitemapBuckets();
} finally {
    file_put_contents($adsPath, $adsOriginal);
}
$joined = [];
foreach ($buckets as $name => $rows) {
    $optional = in_array($name, ['servisi', 'izlozi'], true);
    $check($rows !== [] || $optional, "sitemap deo $name nije prazan (" . count($rows) . ')');
    foreach ($rows as $row) {
        $loc = (string)$row['loc'];
        $joined[] = $loc;
        $check(!str_contains($loc, '?'), "sitemap bez query: $loc");
    }
}
$blob = implode("\n", $joined);
foreach (['/prijava', '/registracija', '/postavi-oglas', 'device_type='] as $banned) {
    $check(!str_contains($blob, $banned), "sitemap ne sadrži $banned");
}
$check(str_contains($blob, '/oglasi/telefoni') || str_contains($blob, '/servisi/'), 'sitemap ima landing ili servise');
$indexXml = seoSitemapDocument('index');
$check(str_contains($indexXml, '<sitemapindex'), 'sitemap index je sitemapindex');
$check(str_contains($indexXml, '/sitemap-oglasi.xml'), 'index povezuje oglase');
$check(!str_contains($indexXml, '<priority>'), 'nema priority');
$adsXml = seoSitemapDocument('oglasi');
$check(str_contains($adsXml, 'xmlns:image='), 'oglasi imaju image namespace');

$seller = ['id' => 5, 'shop_slug' => 'eprom', 'username' => 'eprom'];
$check(shopReviewsUrl($seller) === '/izlog/eprom/ocene', 'ocene su čist URL');
$check(!str_contains(shopReviewsUrl($seller, 'positive'), 'izlog_ocene.php'), 'filter ocena nije php URL');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PROVERA PALO\n");
exit($failed === 0 ? 0 : 1);
