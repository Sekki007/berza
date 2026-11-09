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

// 1) suggestAdTitle za 3 kategorije + prazan naslov
$phoneTitle = suggestAdTitle([
    'ad_type' => 'telefon',
    'brand' => 'Apple',
    'model' => 'iPhone 15',
    'storage' => '128GB',
    'condition_state' => 'Polovno',
]);
check($phoneTitle === 'iPhone 15 128GB – Polovno', 'naslov telefona: ' . $phoneTitle);

$partsTitle = suggestAdTitle([
    'ad_type' => 'delovi',
    'equipment_type' => 'Displej/LCD',
    'compatible_models' => 'iPhone 16 Pro',
    'originality' => 'Original',
]);
check($partsTitle === 'Ekran/LCD za iPhone 16 Pro – Original', 'naslov delova: ' . $partsTitle);

$serviceTitle = suggestAdTitle([
    'ad_type' => 'servis',
    'service_types' => ['screen'],
]);
check(str_contains($serviceTitle, 'Servis'), 'naslov servisa: ' . $serviceTitle);

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
