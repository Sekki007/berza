<?php

declare(strict_types=1);

/**
 * JSON-LD: stanje, seller u ponudi, breadcrumb, FAQ.
 * Pokretanje: php tests/structured_data_test.php
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'kupitelefon.rs';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/oglas/6';

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

$seller = [
    'id' => 2,
    'shop_slug' => 'test-shop',
    'username' => 'test',
    'full_name' => 'Pera Peric',
    'location' => 'Beograd',
];
$base = [
    'id' => 6,
    'title' => 'Apple iPhone 13 128GB',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'ad_type' => 'telefon',
    'price' => 400,
    'price_type' => 'fixed',
    'currency' => 'eur',
    'location' => 'Beograd',
    'description' => 'Polovan telefon, baterija dobra.',
    'expires_at' => '2027-06-01 00:00:00',
    'images' => [],
];

$blocksFor = static function (array $ad) use ($seller): array {
    $html = seoJsonLdScript(seoAdJsonLd($ad, $seller)) . seoJsonLdScript(seoBreadcrumbJsonLd(seoAdBreadcrumbCrumbs($ad)));
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $out = [];
    foreach ($m[1] as $raw) {
        $decoded = json_decode($raw, true);
        $check = $decoded !== null;
        if (!$check) {
            echo "FAIL  JSON nije validan\n";
        }
        $out[] = is_array($decoded) ? $decoded : [];
    }
    return $out;
};

$newAd = $base;
$newAd['condition_state'] = 'Novo';
$newBlocks = $blocksFor($newAd);
$product = $newBlocks[0] ?? [];
$check(($product['itemCondition'] ?? '') === 'https://schema.org/NewCondition', 'Novo je NewCondition');
$check(!isset($product['seller']), 'seller nije na Product');
$check(($product['offers']['seller']['@type'] ?? '') === 'Person', 'seller je u offers');
$check(($product['offers']['seller']['name'] ?? '') !== '', 'seller ima ime');
$check(str_contains((string)($product['offers']['hasMerchantReturnPolicy']['returnPolicyCategory'] ?? ''), 'MerchantReturnNotPermitted'), 'privatna prodaja nema povrat');
$check(isset($product['offers']['shippingDetails']), 'ima shippingDetails');
$check(($product['name'] ?? '') === seoAdHeadline($newAd), 'name je headline');
$check(($product['brand']['name'] ?? '') === 'Apple', 'brend ostaje');

$used = $base;
$used['condition_state'] = 'Polovno';
$usedProduct = $blocksFor($used)[0] ?? [];
$check(($usedProduct['itemCondition'] ?? '') === 'https://schema.org/UsedCondition', 'Polovno je UsedCondition');
$check(($usedProduct['offers']['itemCondition'] ?? '') === 'https://schema.org/UsedCondition', 'Offer condition prati stanje');

$open = $base;
$open['price_type'] = 'negotiable';
$open['price'] = 0;
$openProduct = seoAdJsonLd($open, $seller);
$check(!isset($openProduct['offers']), 'po dogovoru nema offers');

$crumbs = $newBlocks[1]['itemListElement'] ?? [];
$check(($newBlocks[1]['@type'] ?? '') === 'BreadcrumbList', 'oglas ima BreadcrumbList');
$check(count($crumbs) >= 3, 'breadcrumb ima bar 3 nivoa');
foreach ($crumbs as $crumb) {
    $item = (string)($crumb['item'] ?? '');
    $check(str_starts_with($item, 'https://kupitelefon.rs/'), 'breadcrumb je apsolutan: ' . $item);
    $check(!str_contains($item, '/index.php') && !str_contains($item, '?'), 'breadcrumb je kanonski: ' . $item);
}

$other = $base;
$other['brand'] = 'Ostalo';
$otherProduct = seoAdJsonLd($other, null);
$check(!isset($otherProduct['brand']), 'Ostalo nije brand');

$model = phoneCatalogFindBySlug('apple', 'iphone-15-pro');
$check(is_array($model), 'model postoji');
if (is_array($model)) {
    $faq = seoModelFaq($model, ['active' => 4, 'fixed' => 4, 'min' => 500, 'max' => 800]);
    $faqLd = seoFaqJsonLd($faq);
    ob_start();
    seoRenderFaq($faq);
    $html = (string)ob_get_clean();
    $check(($faqLd['@type'] ?? '') === 'FAQPage', 'model FAQPage');
    foreach ($faqLd['mainEntity'] as $entity) {
        $q = (string)$entity['name'];
        $a = (string)$entity['acceptedAnswer']['text'];
        $check(str_contains($html, $q) && str_contains($html, $a), 'FAQ HTML = JSON-LD: ' . $q);
    }
    $bc = seoBreadcrumbJsonLd([
        ['name' => 'Početna', 'url' => '/'],
        ['name' => 'Katalog', 'url' => '/katalog'],
        ['name' => 'Apple', 'url' => '/oglasi/apple'],
        ['name' => (string)$model['name'], 'url' => phoneCatalogUrl($model)],
    ]);
    $check(str_contains((string)$bc['itemListElement'][2]['item'], '/oglasi/apple'), 'model breadcrumb brend je čist');
}

$listing = seoListingCollectionJsonLd(['brand' => 'Apple'], [[
    'id' => 6,
    'title' => 'iPhone 13 128',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'ad_type' => 'telefon',
]], 'https://kupitelefon.rs/oglasi/apple');
$check(($listing['mainEntity']['itemListElement'][0]['name'] ?? '') === seoAdHeadline([
    'title' => 'iPhone 13 128',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'ad_type' => 'telefon',
]), 'ItemList name je headline');
$check(($listing['@type'] ?? '') === 'CollectionPage', 'landing ostaje CollectionPage');

$org = seoOrganizationJsonLd();
$check(str_contains((string)($org['logo'] ?? ''), 'pwa-192.png'), 'Organization ima logo');
$check(in_array('https://t.me/kupitelefon', $org['sameAs'] ?? [], true), 'sameAs ima Telegram');
foreach ($org['sameAs'] ?? [] as $url) {
    $check(!str_contains((string)$url, '?'), 'sameAs bez query: ' . $url);
}
$search = (string)(seoWebsiteJsonLd()['potentialAction']['target'] ?? '');
$check(str_contains($search, '/oglasi?q={search_term_string}'), 'SearchAction je jedini template sa query');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PROVERA PALO\n");
exit($failed === 0 ? 0 : 1);
