<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$target = listingCatalogRedirectTarget('Apple', 'iphone-15-pro');
$check($target === '/katalog/apple/iphone-15-pro', 'oglasi model → katalog: ' . $target);
$check(listingCatalogRedirectTarget('Apple', 'nema-takav-model-xyz') === '', 'nepoznat model se ne preusmerava');

$rows = modelHubPriceRows([
    ['storage' => '128GB', 'condition_state' => 'Polovno', 'price' => 400, 'price_type' => 'fixed', 'currency' => 'eur', 'ad_type' => 'telefon'],
    ['storage' => '128GB', 'condition_state' => 'Polovno', 'price' => 420, 'price_type' => 'fixed', 'currency' => 'eur', 'ad_type' => 'telefon'],
]);
$check($rows !== [] && $rows[0]['min'] === null, 'uzorak od 2 je premalo podataka');

$sellers = [
    ['created_by' => 1],
    ['created_by' => 1],
    ['created_by' => 2],
];
$check(listingLandingIndexable(['location' => 'Beograd'], $sellers) === true, 'grad sa 3 oglasa i 2 prodavca je indeksabilan');
$check(listingLandingIndexable(['location' => 'Ada'], [['created_by' => 1]]) === false, 'jedan oglas u gradu nije indeksabilan');
$check(listingLandingIndexable(['brand' => 'Apple', 'location' => 'Beograd'], $sellers) === true, 'brend+grad preko praga');
$check(listingLandingIndexable(['brand' => 'Apple', 'location' => 'Ada'], [['created_by' => 1], ['created_by' => 2]]) === false, 'brend+grad ispod 3 nije indeksabilan');

$model = phoneCatalogFindBySlug('apple', 'iphone-15-pro');
$faq = is_array($model) ? seoModelFaq($model, ['active' => 0, 'fixed' => 0]) : [];
ob_start();
seoRenderFaq($faq);
$html = (string)ob_get_clean();
$check($faq !== [] && str_contains($html, (string)$faq[0]['q']), 'FAQ HTML prati JSON pitanje');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
