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

$cases = [
    ['Prednje svetlo za elektricni trotinet M365 tip 1', 'delovi', 'trotinet'],
    ['Guma za Xiaomi M365', 'delovi', 'trotinet'],
    ['Narukvica za sat crna', 'delovi', 'sat'],
    ['Kaiš za sat 22mm', 'delovi', 'sat'],
    ['Futrola za iPhone 13 silikon', 'delovi', 'oprema'],
    ['Maska za Samsung A54', 'delovi', 'oprema'],
    ['Zaštitno staklo iPhone 14', 'delovi', 'oprema'],
    ['Punjač USB-C 20W', 'delovi', 'oprema'],
    ['Kabl USB C 1m', 'delovi', 'oprema'],
    ['Ekran za iPhone 11 original', 'delovi', 'delovi'],
    ['Displej za Samsung A52', 'delovi', 'delovi'],
    ['Matična ploča iPhone XR', 'delovi', 'delovi'],
    ['Baterija za iPhone 12', 'delovi', 'delovi'],
    ['Tražim: Otkup iPhone 13', 'telefon', 'buy'],
    ['Kupujem Samsung S23', 'telefon', 'buy'],
    ['Otkup telefona Beograd', 'telefon', 'buy'],
    ['iPhone 13 128GB plavi', 'telefon', 'sell'],
    ['Samsung Galaxy S22 256GB', 'telefon', 'sell'],
    ['Xiaomi Redmi Note 13 8/256', 'telefon', 'sell'],
    ['iPhone 14 Pro 256GB Deep Purple', 'telefon', 'sell'],
    ['Honor 90 256GB', 'telefon', 'sell'],
    ['Google Pixel 8 128GB', 'telefon', 'sell'],
    ['Huawei P30 Pro', 'telefon', 'sell'],
    ['Sajla za trotinet', 'delovi', 'trotinet'],
    ['Prednje svetlo M365', 'delovi', 'trotinet'],
    ['Narukvice 10 komada', 'delovi', 'sat'],
    ['Maskica za telefon prozirna', 'delovi', 'oprema'],
    ['LCD za iPhone X', 'delovi', 'delovi'],
    ['Trazim iPhone 12', 'telefon', 'buy'],
    ['Polovni iPhone 15 Pro 256GB', 'telefon', 'sell'],
];

foreach ($cases as [$title, $type, $extra]) {
    $ad = ['title' => $title, 'ad_type' => 'telefon', 'brand' => 'Ostalo', 'price' => 10, 'price_type' => 'fixed', 'currency' => 'eur'];
    $result = classifyAd($ad);
    if ($extra === 'buy' || $extra === 'sell') {
        $check($result['ad_type'] === 'telefon' && $result['listing_type'] === $extra, $title . ' → ' . $result['listing_type'] . ' / ' . $result['ad_type']);
    } else {
        $check($result['ad_type'] === $type && $result['category_group'] === $extra, $title . ' → ' . $result['ad_type'] . '/' . $result['category_group']);
    }
}

$phone = [
    'title' => 'iPhone 13 sa novom baterijom',
    'ad_type' => 'telefon',
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'phone_model_id' => 'apple-iphone-13',
    'price' => 350,
    'price_type' => 'fixed',
    'currency' => 'eur',
];
$kept = classifyAd($phone);
$check($kept['ad_type'] === 'telefon', 'iPhone sa novom baterijom ostaje telefon');

$wrongBrand = classifyAd([
    'title' => 'Samsung galaxy s22 green 128gb',
    'ad_type' => 'telefon',
    'brand' => 'Apple',
    'price' => 200,
    'price_type' => 'fixed',
    'currency' => 'eur',
]);
$check($wrongBrand['brand'] === 'Samsung', 'pogrešan brend Apple za Galaxy → Samsung');

$report = staleImportedAdsReport();
$check($report['enabled'] === false, 'podsetnik za stare oglase je isključen');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
