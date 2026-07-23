<?php

declare(strict_types=1);

/**
 * Napuni data/ads.json sa ~15 demo oglasa + preuzme placeholder slike.
 * php tools/seed_demo_ads.php
 */

$root = dirname(__DIR__);
$uploads = $root . '/public/uploads/ads';
$dataFile = $root . '/data/ads.json';

if (!is_dir($uploads)) {
    mkdir($uploads, 0777, true);
}

function downloadImage(string $url, string $dest): bool
{
    if (file_exists($dest) && filesize($dest) > 1000) {
        return true;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 25,
            'follow_location' => 1,
            'user_agent' => 'TelefonBerzaSeed/1.0',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false || strlen($bin) < 500) {
        return false;
    }
    return file_put_contents($dest, $bin) !== false;
}

function writePlaceholderJpeg(string $dest, string $label, int $r, int $g, int $b): bool
{
    // Minimalni validan JPEG (1x1) + tekst nije moguć bez GD — zato veći "fake" SVG kao .jpg fallback ne radi.
    // Ako download padne, ostavi prazno; preferiramo picsum.
    return false;
}

$samples = [
    [
        'title' => 'iPhone 14 Pro 128GB Deep Purple',
        'description' => 'Odlično stanje, baterija 91%, Face ID radi, kutija + kabl. Bez iCloud lock-a. Moguć dogovor u centru.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Apple', 'model' => 'iPhone 14 Pro', 'storage' => '128 GB',
        'price' => 720, 'condition_state' => 'Odlično', 'location' => 'Beograd',
        'badge' => 'Hit', 'is_promoted' => 1, 'seed' => 'iphone14pro',
    ],
    [
        'title' => 'Samsung Galaxy S23 256GB crni',
        'description' => 'Polovan telefon u odličnom stanju, malo korišćen, puna oprema. Garantujem ispravnost.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Samsung', 'model' => 'Galaxy S23', 'storage' => '256 GB',
        'price' => 390, 'condition_state' => 'Polovno', 'location' => 'Novi Sad',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'galaxys23',
    ],
    [
        'title' => 'Xiaomi 13T Pro 512GB',
        'description' => 'Skoro nov, kupljen pre 4 meseca, račun i garancija. Kamera odlična, 120W punjenje.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Xiaomi', 'model' => '13T Pro', 'storage' => '512 GB',
        'price' => 340, 'condition_state' => 'Odlično', 'location' => 'Niš',
        'badge' => 'Garancija', 'is_promoted' => 1, 'seed' => 'xiaomi13t',
    ],
    [
        'title' => 'Google Pixel 7 128GB',
        'description' => 'Čist Android, softver do 2028, baterija dobra. Prodajem zbog prelaska na iPhone.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Google', 'model' => 'Pixel 7', 'storage' => '128 GB',
        'price' => 260, 'condition_state' => 'Polovno', 'location' => 'Kragujevac',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'pixel7',
    ],
    [
        'title' => 'iPhone 12 64GB plavi',
        'description' => 'Radni telefon, ogrebotine na aluminijumu, ekran bez pukotina. Baterija 84%.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Apple', 'model' => 'iPhone 12', 'storage' => '64 GB',
        'price' => 250, 'condition_state' => 'Polovno', 'location' => 'Subotica',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'iphone12',
    ],
    [
        'title' => 'Samsung A54 5G 128GB',
        'description' => 'Odličan srednji model, IP67, dobra baterija. U kompletu punjač i maska.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Samsung', 'model' => 'Galaxy A54', 'storage' => '128 GB',
        'price' => 180, 'condition_state' => 'Odlično', 'location' => 'Čačak',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'galaxya54',
    ],
    [
        'title' => 'Huawei P30 Pro 256GB',
        'description' => 'Klasika za foto, lepa baterija, bez Google servisa (HMS). Cena dogovor.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Huawei', 'model' => 'P30 Pro', 'storage' => '256 GB',
        'price' => 140, 'condition_state' => 'Polovno', 'location' => 'Novi Pazar',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'p30pro',
    ],
    [
        'title' => 'iPhone 15 128GB crni',
        'description' => 'Nov, otpakovan, račun iz Srbije. Dynamic Island, USB-C. Hitna prodaja.',
        'ad_type' => 'telefon', 'category_group' => 'phones', 'category' => 'Telefoni',
        'brand' => 'Apple', 'model' => 'iPhone 15', 'storage' => '128 GB',
        'price' => 690, 'condition_state' => 'Novo', 'location' => 'Beograd',
        'badge' => 'Novo', 'is_promoted' => 1, 'seed' => 'iphone15',
    ],
    [
        'title' => 'Ekran iPhone 13 original OLED',
        'description' => 'Original kvalitet, sa Folijom, testiran. Ugradnja moguća u servisu.',
        'ad_type' => 'delovi', 'category_group' => 'iphone_parts', 'category' => 'Delovi',
        'brand' => 'Apple', 'model' => 'iPhone 13', 'storage' => '',
        'price' => 95, 'condition_state' => 'Novo', 'location' => 'Beograd',
        'badge' => 'Deo', 'is_promoted' => 0, 'seed' => 'screen13',
    ],
    [
        'title' => 'Baterija Samsung S22 Ultra',
        'description' => 'Nova baterija, kapacitet kao OEM. Montaža 15 min.',
        'ad_type' => 'delovi', 'category_group' => 'android_parts', 'category' => 'Delovi',
        'brand' => 'Samsung', 'model' => 'Galaxy S22 Ultra', 'storage' => '',
        'price' => 45, 'condition_state' => 'Novo', 'location' => 'Novi Sad',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'battery22',
    ],
    [
        'title' => 'Kamera modul Xiaomi 12',
        'description' => 'Kompletni kamerni modul, original sa telefona. Ispravan.',
        'ad_type' => 'delovi', 'category_group' => 'android_parts', 'category' => 'Delovi',
        'brand' => 'Xiaomi', 'model' => '12', 'storage' => '',
        'price' => 55, 'condition_state' => 'Polovno', 'location' => 'Niš',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'camxiaomi',
    ],
    [
        'title' => 'Konektor punjenja iPhone 11',
        'description' => 'Flex konektor + mikrofon, nova zamena. Radimo i ugradnju.',
        'ad_type' => 'delovi', 'category_group' => 'iphone_parts', 'category' => 'Delovi',
        'brand' => 'Apple', 'model' => 'iPhone 11', 'storage' => '',
        'price' => 25, 'condition_state' => 'Novo', 'location' => 'Kragujevac',
        'badge' => '', 'is_promoted' => 0, 'seed' => 'charge11',
    ],
    [
        'title' => 'Zamena stakla zadnje iPhone 14',
        'description' => 'Profesionalna zamena zadnjeg stakla laserom, garancija 6 meseci.',
        'ad_type' => 'servis', 'category_group' => 'service', 'category' => 'Servisne usluge',
        'brand' => 'Apple', 'model' => 'iPhone 14', 'storage' => '',
        'price' => 70, 'condition_state' => 'Servisirano', 'location' => 'Beograd',
        'badge' => 'Servis', 'is_promoted' => 0, 'seed' => 'glass14',
    ],
    [
        'title' => 'Otključavanje mreže / FRP Android',
        'description' => 'Softversko otključavanje većine modela. Brzo, na licu mesta ili online.',
        'ad_type' => 'servis', 'category_group' => 'service', 'category' => 'Servisne usluge',
        'brand' => 'Ostalo', 'model' => '', 'storage' => '',
        'price' => 30, 'condition_state' => 'Servisirano', 'location' => 'Novi Pazar',
        'badge' => 'Servis', 'is_promoted' => 0, 'seed' => 'frpunlock',
    ],
    [
        'title' => 'Zamena konektora + dijagnostika',
        'description' => 'Kompletna dijagnostika + zamena USB-C / Lightning konektora. Garancija na rad.',
        'ad_type' => 'servis', 'category_group' => 'service', 'category' => 'Servisne usluge',
        'brand' => 'Samsung', 'model' => 'Galaxy S21', 'storage' => '',
        'price' => 40, 'condition_state' => 'Servisirano', 'location' => 'Novi Sad',
        'badge' => 'Garancija', 'is_promoted' => 0, 'seed' => 'usbfix',
    ],
];

$existing = [];
if (file_exists($dataFile)) {
    $decoded = json_decode((string)file_get_contents($dataFile), true);
    if (is_array($decoded)) {
        $existing = $decoded;
    }
}

$maxId = 0;
foreach ($existing as $ad) {
    $maxId = max($maxId, (int)($ad['id'] ?? 0));
}

$now = time();
$created = 0;
$failedImages = 0;

foreach ($samples as $i => $s) {
    $maxId++;
    $adId = $maxId;
    $file = "seed_{$adId}_{$s['seed']}.jpg";
    $dest = $uploads . '/' . $file;
    $urlPath = '/uploads/ads/' . $file;

    // picsum deterministic seed images
    $ok = downloadImage('https://picsum.photos/seed/' . rawurlencode($s['seed']) . '/640/800.jpg', $dest);
    if (!$ok) {
        $ok = downloadImage('https://picsum.photos/640/800?random=' . $adId, $dest);
    }
    if (!$ok) {
        $failedImages++;
        $images = [];
        echo "WARN: nema slike za #{$adId} {$s['title']}\n";
    } else {
        $images = [$urlPath];
        echo "OK image #{$adId} {$file}\n";
    }

    $createdAt = date('Y-m-d H:i:s', $now - ($i * 3600 * 5));
    $existing[] = [
        'id' => $adId,
        'title' => $s['title'],
        'description' => $s['description'],
        'ad_type' => $s['ad_type'],
        'category' => $s['category'],
        'category_group' => $s['category_group'],
        'brand' => $s['brand'],
        'model' => $s['model'],
        'storage' => $s['storage'],
        'price' => $s['price'],
        'condition_state' => $s['condition_state'],
        'location' => $s['location'],
        'country' => 'Srbija',
        'contact_phone' => '0601234567',
        'shop_name' => 'MobilServis Demo',
        'badge' => $s['badge'],
        'images' => $images,
        'is_active' => 1,
        'is_sold' => 0,
        'is_promoted' => (int)$s['is_promoted'],
        'promoted_until' => !empty($s['is_promoted']) ? date('Y-m-d H:i:s', $now + 5 * 86400) : null,
        'views' => random_int(12, 220),
        'created_by' => 1,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'expires_at' => date('Y-m-d H:i:s', strtotime($createdAt) + 30 * 86400),
        'expiry_warned_at' => null,
    ];
    $created++;
}

file_put_contents($dataFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nDodato {$created} oglasa. Ukupno u bazi: " . count($existing) . "\n";
if ($failedImages > 0) {
    echo "Slike nisu skinute za {$failedImages} oglasa (mreža?). Probaj ponovo.\n";
}
