<?php

declare(strict_types=1);

/**
 * Forma oglasa: naslov, 3 kategorije, staging slika posle greške.
 * Pokretanje: php tests/ad_form_test.php
 */

define('KT_SKIP_BACKGROUND', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = '';
$_SERVER['REQUEST_URI'] = '/postavi-oglas';

$dataDir = dirname(__DIR__) . '/data';
$restorePaths = [
    $dataDir . '/ads.json',
    $dataDir . '/users.json',
    $dataDir . '/notifications.json',
];
$restore = [];
foreach ($restorePaths as $path) {
    $restore[$path] = is_file($path) ? (string)file_get_contents($path) : null;
}
$stagedRoot = dirname(__DIR__) . '/public/uploads/ads/staged';
register_shutdown_function(static function () use ($restore, $stagedRoot): void {
    foreach ($restore as $path => $contents) {
        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
            continue;
        }
        file_put_contents($path, $contents);
    }
    if (is_dir($stagedRoot)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagedRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
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

// 1) suggestAdTitle za 3 kategorije + memorija u naslovu + Menjam
$phoneTitle = suggestAdTitle([
    'ad_type' => 'telefon',
    'brand' => 'Apple',
    'model' => 'iPhone 15',
    'storage' => '128GB',
    'condition_state' => 'Polovno',
]);
check($phoneTitle === 'iPhone 15 128GB – Polovno', 'naslov telefona: ' . $phoneTitle);

$phoneTitleMem = suggestAdTitle([
    'ad_type' => 'telefon',
    'listing_type' => 'sell',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro',
    'storage' => '256GB',
    'condition_state' => 'Polovno',
]);
check($phoneTitleMem === 'iPhone 15 Pro 256GB – Polovno', 'naslov sa memorijom: ' . $phoneTitleMem);

$phoneTitleBh = suggestAdTitle([
    'ad_type' => 'telefon',
    'listing_type' => 'sell',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro',
    'storage' => '256GB',
    'battery_health' => 87,
    'condition_state' => 'Polovno',
]);
check($phoneTitleBh === 'iPhone 15 Pro 256GB BH 87% – Polovno', 'naslov sa baterijom: ' . $phoneTitleBh);

$tradeBh = suggestAdTitle([
    'ad_type' => 'telefon',
    'listing_type' => 'trade',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro',
    'storage' => '256GB',
    'battery_health' => '92',
    'condition_state' => 'Polovno',
    'trade_for' => 'iPhone 14 Pro',
]);
check($tradeBh === 'iPhone 15 Pro 256GB BH 92% – Polovno za iPhone 14 Pro', 'Menjam naslov sa baterijom: ' . $tradeBh);

$bhExtras = parseAdFormExtras(['battery_health' => '87', 'listing_type' => 'sell'], 'telefon');
check(($bhExtras['battery_health'] ?? null) === 87, 'tačan procenat baterije se čuva');
$bhEmpty = parseAdFormExtras(['battery_health' => '', 'listing_type' => 'sell'], 'telefon');
check(array_key_exists('battery_health', $bhEmpty) && $bhEmpty['battery_health'] === null, 'prazna baterija ostaje nepoznata');
$bhRow = '';
foreach (adAttributeRows(['ad_type' => 'telefon', 'battery_health' => 82, 'brand' => 'Apple']) as $row) {
    if (($row['label'] ?? '') === 'Battery Health') {
        $bhRow = (string)($row['value'] ?? '');
    }
}
check($bhRow === '82%', 'detalj prikazuje tačan procenat baterije: ' . $bhRow);

$tradeTitle = suggestAdTitle([
    'ad_type' => 'telefon',
    'listing_type' => 'trade',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro',
    'storage' => '256GB',
    'condition_state' => 'Polovno',
    'trade_for' => 'iPhone 14 Pro',
]);
check($tradeTitle === 'iPhone 15 Pro 256GB – Polovno za iPhone 14 Pro', 'naslov Menjam: ' . $tradeTitle);
check(normalizeListingType('trade', 'telefon') === 'trade', 'listing Menjam = trade');
check(isTradeListing(['ad_type' => 'telefon', 'listing_type' => 'trade']), 'isTradeListing za Menjam');

$partsTitle = suggestAdTitle([
    'ad_type' => 'delovi',
    'equipment_type' => 'Displej/LCD',
    'compatible_models' => 'iPhone 16 Pro',
    'originality' => 'Original',
]);
check($partsTitle === 'Ekran/LCD za iPhone 16 Pro – Original', 'naslov delova: ' . $partsTitle);

$serviceTitle = suggestAdTitle([
    'ad_type' => 'servis',
    'listing_type' => 'service',
    'service_types' => ['screen'],
]);
check(str_contains($serviceTitle, 'Servis'), 'naslov servisa: ' . $serviceTitle);
check(normalizeListingType('service', 'servis') === 'service', 'listing Servis = service');
check(normalizeListingType('sell', 'servis') === 'service', 'Servis forsira listing service');

$serviceExtras = parseAdFormExtras([
    'listing_type' => 'service',
    'service_types' => ['screen'],
    'supported_brands' => ['Apple'],
], 'servis');
check(($serviceExtras['listing_type'] ?? '') === 'service', 'parseAdFormExtras Servis listing');

$tradeExtras = parseAdFormExtras([
    'listing_type' => 'trade',
    'trade_for' => 'Galaxy S24',
    'ram' => '8GB',
], 'telefon');
check(($tradeExtras['listing_type'] ?? '') === 'trade' && ($tradeExtras['trade_for'] ?? '') === 'Galaxy S24', 'parseAdFormExtras Menjam za');

if (function_exists('formatPhoneRsDisplay')) {
    check(formatPhoneRsDisplay('0612345678') === '061 234 5678', 'format telefona 06x xxx xxxx');
    check(formatPhoneRsDisplay('+381612345678') === '061 234 5678', 'format telefona iz +381');
}

// 2) Kreiranje oglasa — sve 3 kategorije (preko saveAd, bez HTTP)
$users = getUsers();
$seller = null;
foreach ($users as $u) {
    if ((int)($u['id'] ?? 0) > 0) {
        $seller = $u;
        break;
    }
}
check(is_array($seller), 'postoji korisnik za test');
$uid = (int)$seller['id'];

function makeAdPayload(int $uid, string $type, string $title = ''): array
{
    $base = [
        'title' => $title,
        'description' => 'Test opis ' . $type,
        'ad_type' => $type,
        'listing_type' => $type === 'servis' ? 'service' : 'sell',
        'category_group' => $type === 'telefon' ? 'phones' : ($type === 'servis' ? 'service' : 'other_parts'),
        'brand' => $type === 'servis' ? '' : 'Apple',
        'model' => $type === 'telefon' ? 'iPhone 15' : '',
        'storage' => $type === 'telefon' ? '128GB' : '',
        'price' => 100,
        'currency' => 'eur',
        'price_type' => 'fixed',
        'condition_state' => $type === 'servis' ? '' : ($type === 'delovi' ? 'Novo' : 'Polovno'),
        'location' => 'Beograd',
        'country' => 'Srbija',
        'contact_phone' => '0611111111',
        'shop_name' => 'Test Shop',
        'images' => [],
        'is_active' => 1,
        'is_sold' => 0,
        'is_promoted' => 0,
        'created_by' => $uid,
        'equipment_type' => $type === 'delovi' ? 'Displej/LCD' : '',
        'compatible_models' => $type === 'delovi' ? 'iPhone 16 Pro' : '',
        'originality' => $type === 'delovi' ? 'Original' : '',
        'service_types' => $type === 'servis' ? ['screen'] : [],
        'supported_brands' => $type === 'servis' ? ['Apple'] : [],
    ];
    if ($title === '') {
        $base['title'] = suggestAdTitle($base);
    }
    return $base;
}

$idPhone = saveAd(makeAdPayload($uid, 'telefon', ''));
$adPhone = getAdById($idPhone);
check(is_array($adPhone) && getAdType($adPhone) === 'telefon', 'kreiran telefon oglas #' . $idPhone);
check(trim((string)($adPhone['title'] ?? '')) !== '', 'telefon ima auto naslov: ' . (string)($adPhone['title'] ?? ''));

$idParts = saveAd(makeAdPayload($uid, 'delovi', ''));
$adParts = getAdById($idParts);
check(is_array($adParts) && getAdType($adParts) === 'delovi', 'kreiran delovi oglas #' . $idParts);
check(str_contains((string)($adParts['title'] ?? ''), 'za'), 'delovi naslov sadrži za');

$idService = saveAd(makeAdPayload($uid, 'servis', ''));
$adService = getAdById($idService);
check(is_array($adService) && getAdType($adService) === 'servis', 'kreiran servis oglas #' . $idService);
check(getAdListingType($adService) === 'service', 'servis oglas ima listing_type=service');

$tradePayload = makeAdPayload($uid, 'telefon', '');
$tradePayload['listing_type'] = 'trade';
$tradePayload['trade_for'] = 'iPhone 13';
$tradePayload['storage'] = '256GB';
$tradePayload['model'] = 'iPhone 15 Pro';
$tradePayload['title'] = suggestAdTitle($tradePayload);
$idTrade = saveAd($tradePayload);
$adTrade = getAdById($idTrade);
check(is_array($adTrade) && getAdListingType($adTrade) === 'trade', 'kreiran Menjam oglas #' . $idTrade);
check(str_contains((string)($adTrade['title'] ?? ''), '256GB'), 'Menjam naslov sadrži memoriju');
check(str_contains((string)($adTrade['title'] ?? ''), 'za iPhone 13'), 'Menjam naslov sadrži Menjam za');

// 3) Staging slika posle "greške" — simulacija upload-a (minimalni JPEG bez GD)
$tmp = tempnam(sys_get_temp_dir(), 'adimg');
// 1x1 JPEG
$jpegBin = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUWFxUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAADBAECBQYAB//EABUBAQEAAAAAAAAAAAAAAAAAAAAB/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAB6gAf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwFf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwFf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyFf/9k=');
if ($jpegBin === false || $jpegBin === '') {
    // Fallback: copy any existing ad image if present
    $sample = null;
    foreach (glob(dirname(__DIR__) . '/public/uploads/ads/*/*.jpg') ?: [] as $cand) {
        $sample = $cand;
        break;
    }
    check($sample !== null && is_file($sample), 'postoji sample jpeg za staging test');
    copy($sample, $tmp);
} else {
    file_put_contents($tmp, $jpegBin);
}

$_FILES['images'] = [
    'name' => ['test.jpg'],
    'type' => ['image/jpeg'],
    'tmp_name' => [$tmp],
    'error' => [UPLOAD_ERR_OK],
    'size' => [filesize($tmp) ?: 1],
];
$staged = stageAdFormUploads($uid);
check($staged !== [], 'stageAdFormUploads čuva sliku');
check(isset($staged[0]) && is_file(dirname(__DIR__) . '/public' . $staged[0]), 'staged fajl postoji na disku');

// Promote mora da radi i bez novog $_FILES (scenario posle greške validacije)
$_FILES = [];
$promoted = promoteStagedAdImages($idPhone, $staged);
check($promoted !== [] && str_contains($promoted[0], '/uploads/ads/' . $idPhone . '/'), 'promoteStagedAdImages prebacuje u oglas');
check(!is_file(dirname(__DIR__) . '/public' . $staged[0]), 'staged fajl je uklonjen posle promote');

echo $failed === 0 ? "\nSvi ad_form testovi prolaze.\n" : "\nNeuspešnih: {$failed}\n";
exit($failed === 0 ? 0 : 1);
