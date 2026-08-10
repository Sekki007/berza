<?php

declare(strict_types=1);

/**
 * Uvoz oglasa iz KP Chrome ekstenzije (JSON).
 */

function kpParseImportJson(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['ok' => false, 'error' => 'JSON fajl je prazan.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Neispravan JSON format.'];
    }

    if (!isset($data['ads']) || !is_array($data['ads'])) {
        return ['ok' => false, 'error' => 'JSON mora sadržati niz "ads".'];
    }

    if ($data['ads'] === []) {
        return ['ok' => false, 'error' => 'Nema oglasa u JSON fajlu.'];
    }

    $data['seller'] = is_array($data['seller'] ?? null) ? $data['seller'] : [];
    $data['meta'] = is_array($data['meta'] ?? null) ? $data['meta'] : [];

    return ['ok' => true, 'data' => $data];
}

function kpExistingSourceIds(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    foreach (readJsonFile('ads.json') as $ad) {
        $sid = trim((string)($ad['kp_source_id'] ?? ''));
        if ($sid !== '') {
            $cache[$sid] = (int)($ad['id'] ?? 0);
        }
    }
    return $cache;
}

function kpGuessBrand(string $text): string
{
    $brands = adPhoneBrands();
    $lower = mb_strtolower($text);
    foreach ($brands as $brand) {
        if ($brand === 'Ostalo') {
            continue;
        }
        if (str_contains($lower, mb_strtolower($brand))) {
            return $brand;
        }
    }
    if (preg_match('/\biphone\b/i', $text)) {
        return 'Apple';
    }
    if (preg_match('/\bgalaxy\b/i', $text)) {
        return 'Samsung';
    }
    return '';
}

function kpGuessDeviceType(string $text): string
{
    $lower = mb_strtolower($text);
    if (preg_match('/\b(airpods|slušalice|slusalice|earbuds)\b/u', $lower)) {
        return 'earbuds';
    }
    if (preg_match('/\b(tablet|ipad)\b/u', $lower)) {
        return 'tablet';
    }
    if (preg_match('/\b(watch|sat|apple watch|galaxy watch)\b/u', $lower)) {
        return 'watch';
    }
    return 'phone';
}

function kpGuessCategoryGroup(string $text): array
{
    $lower = mb_strtolower($text);

    if (preg_match('/\b(servis|zamena ekrana|popravka|repair|deblok|dekod|flash|software)\b/u', $lower)) {
        return ['ad_type' => 'servis', 'category_group' => 'service'];
    }

    $partsKeywords = '/\b(ekran|displej|lcd|baterija|maska|futrola|punjač|punjac|kabl|staklo|deo|delovi|flex|kamera module|touch)\b/u';
    if (preg_match($partsKeywords, $lower)) {
        $group = 'other_parts';
        if (preg_match('/\b(iphone|apple|ipad)\b/u', $lower)) {
            $group = 'iphone_parts';
        } elseif (preg_match('/\b(samsung|galaxy)\b/u', $lower)) {
            $group = 'samsung_parts';
        } elseif (preg_match('/\b(xiaomi|redmi|poco)\b/u', $lower)) {
            $group = 'xiaomi_parts';
        } elseif (preg_match('/\b(huawei|honor)\b/u', $lower)) {
            $group = 'huawei_honor_parts';
        } elseif (preg_match('/\b(punjač|punjac|kabl|charger|cable)\b/u', $lower)) {
            $group = 'chargers_cables';
        } elseif (preg_match('/\b(maska|futrola|case)\b/u', $lower)) {
            $group = 'cases_protection';
        } elseif (preg_match('/\b(airpods|slušalice|slusalice|earbuds)\b/u', $lower)) {
            $group = 'audio_accessories';
        }
        return ['ad_type' => 'delovi', 'category_group' => $group];
    }

    return ['ad_type' => 'telefon', 'category_group' => 'phones'];
}

function kpMapCondition(string $kpCondition, string $adType): string
{
    $t = mb_strtolower(trim($kpCondition));
    if ($adType === 'delovi') {
        if (str_contains($t, 'nov')) {
            return 'Novo';
        }
        return 'Polovno';
    }
    if ($adType === 'servis') {
        return '';
    }
    if (str_contains($t, 'nov') && !str_contains($t, 'kao')) {
        return 'Novo';
    }
    if (str_contains($t, 'kao nov')) {
        return 'Kao novo';
    }
    if (str_contains($t, 'ošteć') || str_contains($t, 'ostec') || str_contains($t, 'delov')) {
        return 'Oštećeno/Za delove';
    }
    return 'Polovno';
}

function kpExtractModel(string $title, string $brand): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }
    if ($brand !== '' && $brand !== 'Ostalo') {
        $stripped = trim(preg_replace('/\b' . preg_quote($brand, '/') . '\b/i', '', $title) ?? $title);
        if ($stripped !== '') {
            return $stripped;
        }
    }
    return $title;
}

function kpGuessEquipmentType(string $text, string $categoryGroup): string
{
    $cfg = categoriesConfig()['groups'][$categoryGroup] ?? null;
    if ($cfg && !empty($cfg['equipment_type'])) {
        return (string)$cfg['equipment_type'];
    }

    $lower = mb_strtolower($text);
    if (preg_match('/\b(maska|futrola)\b/u', $lower)) {
        return 'Maska/Futrola';
    }
    if (preg_match('/\b(staklo)\b/u', $lower)) {
        return 'Zaštitno staklo';
    }
    if (preg_match('/\b(punjač|punjac|kabl)\b/u', $lower)) {
        return 'Punjač/Kabl';
    }
    if (preg_match('/\b(slušalice|slusalice|airpods)\b/u', $lower)) {
        return 'Slušalice';
    }
    return 'Rezervni delovi';
}

/**
 * @return array<string, mixed>
 */
function kpBuildDefaultMapping(array $kpAd, ?array $targetUser, ?array $seller = null): array
{
    $title = trim((string)($kpAd['title'] ?? ''));
    $desc = trim((string)($kpAd['description'] ?? $kpAd['description_short'] ?? ''));
    $blob = $title . ' ' . $desc;

    $guess = kpGuessCategoryGroup($blob);
    $adType = $guess['ad_type'];
    $categoryGroup = $guess['category_group'];
    $brand = kpGuessBrand($blob);
    if ($adType === 'delovi' && $brand === '') {
        $groupCfg = categoriesConfig()['groups'][$categoryGroup] ?? null;
        $brand = (string)($groupCfg['brand'] ?? '');
    }

    $listingType = (string)($kpAd['listing_type'] ?? 'sell');
    if ($listingType === 'buy') {
        $listingType = 'buy';
    } elseif ($adType === 'servis') {
        $listingType = 'service';
    } else {
        $listingType = 'sell';
    }

    $price = $kpAd['price'] ?? null;
    $currencyRaw = strtoupper(trim((string)($kpAd['currency'] ?? 'EUR')));
    $currency = normalizeAdCurrency($currencyRaw === 'RSD' ? 'rsd' : 'eur');
    $priceType = 'fixed';
    if ($listingType === 'buy' || $price === null || $price === '' || (float)$price <= 0) {
        $priceType = 'negotiable';
        $price = 0;
    }

    $location = trim((string)($kpAd['location'] ?? ''));
    if ($location === '' && is_array($seller)) {
        $location = trim((string)($seller['location'] ?? ''));
    }
    if ($location === '' && is_array($targetUser)) {
        $location = trim((string)($targetUser['location'] ?? ''));
    }

    $sourceId = trim((string)($kpAd['source_id'] ?? ''));
    $existing = kpExistingSourceIds();
    $duplicateAdId = $sourceId !== '' ? ($existing[$sourceId] ?? 0) : 0;

    $images = [];
    foreach ($kpAd['images'] ?? [] as $img) {
        $img = trim((string)$img);
        if ($img !== '') {
            $images[] = $img;
        }
    }

    return [
        'selected' => $duplicateAdId === 0 ? 1 : 0,
        'source_id' => $sourceId,
        'source_url' => trim((string)($kpAd['source_url'] ?? '')),
        'duplicate_ad_id' => $duplicateAdId,
        'title' => $title,
        'description' => $desc,
        'ad_type' => $adType,
        'category_group' => $categoryGroup,
        'brand' => $brand,
        'model' => kpExtractModel($title, $brand),
        'device_type' => kpGuessDeviceType($blob),
        'equipment_type' => kpGuessEquipmentType($blob, $categoryGroup),
        'condition_state' => kpMapCondition((string)($kpAd['condition'] ?? ''), $adType),
        'listing_type' => $listingType,
        'price' => (float)$price,
        'price_type' => $priceType,
        'currency' => $currency,
        'location' => $location,
        'shop_category_id' => '',
        'is_active' => 0,
        'download_images' => count($images) > 0 ? 1 : 0,
        'image_urls' => $images,
        'image_count' => count($images),
    ];
}

/**
 * @param array<int, array<string, mixed>> $kpAds
 * @return array<int, array<string, mixed>>
 */
function kpBuildDefaultMappings(array $kpAds, ?array $targetUser, ?array $seller = null): array
{
    $out = [];
    foreach ($kpAds as $i => $kpAd) {
        if (!is_array($kpAd)) {
            continue;
        }
        $out[$i] = kpBuildDefaultMapping($kpAd, $targetUser, $seller);
    }
    return $out;
}

function kpNextAdId(): int
{
    $maxId = 0;
    foreach (readJsonFile('ads.json') as $ad) {
        $maxId = max($maxId, (int)($ad['id'] ?? 0));
    }
    return $maxId + 1;
}

/**
 * @param list<string> $urls
 * @return list<string>
 */
function kpDownloadRemoteAdImages(int $adId, array $urls): array
{
    require_once __DIR__ . '/ads_helpers.php';
    ensureUploadsDir();

    $targetDir = uploadsDir() . '/' . $adId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $images = [];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 25,
            'user_agent' => 'KupiTelefon-Import/1.0',
            'header' => "Accept: image/*\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    foreach ($urls as $i => $url) {
        if (count($images) >= 10) {
            break;
        }
        $url = trim((string)$url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }

        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || strlen($data) < 200) {
            continue;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kpimg');
        if ($tmp === false) {
            continue;
        }
        file_put_contents($tmp, $data);
        $type = mime_content_type($tmp) ?: 'image/jpeg';
        if (!in_array($type, $allowed, true)) {
            @unlink($tmp);
            continue;
        }

        $stamp = time() . '_' . $i;
        $name = 'img_' . $stamp . '.jpg';
        $dest = $targetDir . '/' . $name;
        if (compressAndSaveImage($tmp, $dest, $type)) {
            $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
            createImageDerivative($dest, $targetDir . '/img_' . $stamp . '_t.' . $ext, 400, 78);
            createImageDerivative($dest, $targetDir . '/img_' . $stamp . '_d.' . $ext, 800, 80);
            $images[] = '/uploads/ads/' . $adId . '/' . $name;
        }
        @unlink($tmp);
    }

    return $images;
}

function kpUpdateAdImages(int $adId, array $images): void
{
    require_once __DIR__ . '/ads_helpers.php';
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['id'] ?? 0) !== $adId) {
            continue;
        }
        $ad['images'] = array_values(array_slice($images, 0, 10));
        $ad['updated_at'] = date('Y-m-d H:i:s');
        invalidateAdOgImage($adId);
        ensureAdOgImage($ad, true);
        break;
    }
    unset($ad);
    writeJsonFile('ads.json', $ads);
}

/**
 * @param array<string, mixed> $row
 * @return array{ok:bool, ad_id?:int, error?:string}
 */
function kpImportSingleAd(array $row, int $targetUserId, ?array $targetUser): array
{
    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'Naslov je prazan.'];
    }

    $adType = trim((string)($row['ad_type'] ?? 'telefon'));
    if (!in_array($adType, ['telefon', 'delovi', 'servis'], true)) {
        $adType = 'telefon';
    }

    $categoryGroup = trim((string)($row['category_group'] ?? ''));
    if ($adType === 'telefon') {
        $categoryGroup = 'phones';
    } elseif ($adType === 'servis') {
        $categoryGroup = 'service';
    } elseif ($categoryGroup === '' || !isset(categoriesConfig()['groups'][$categoryGroup])) {
        $categoryGroup = 'iphone_parts';
    }

    $location = trim((string)($row['location'] ?? ''));
    $phone = trim((string)($targetUser['phone'] ?? ''));
    if ($location === '') {
        return ['ok' => false, 'error' => 'Lokacija je prazna za: ' . $title];
    }
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Korisnik nema telefon u profilu.'];
    }

    $priceType = normalizeAdPriceType((string)($row['price_type'] ?? 'fixed'));
    $price = $priceType === 'fixed' ? max(0, (float)($row['price'] ?? 0)) : 0;
    if ($adType === 'telefon' && $priceType === 'fixed' && $price <= 0) {
        $priceType = 'negotiable';
    }

    $listingType = normalizeListingType((string)($row['listing_type'] ?? 'sell'), $adType);
    $brand = trim((string)($row['brand'] ?? ''));
    if ($adType === 'servis') {
        $brand = '';
    }

    $extras = [
        'listing_type' => $listingType,
        'contact_methods' => ['call', 'message'],
        'pickup_methods' => ['pickup'],
        'device_type' => '',
        'equipment_type' => '',
        'service_types' => [],
        'supported_brands' => [],
    ];

    if ($adType === 'telefon') {
        $extras['device_type'] = normalizeDeviceType((string)($row['device_type'] ?? 'phone'));
    }
    if ($adType === 'delovi') {
        $extras['equipment_type'] = trim((string)($row['equipment_type'] ?? 'Rezervni delovi'));
    }

    $payload = array_merge([
        'title' => $title,
        'description' => trim((string)($row['description'] ?? '')),
        'ad_type' => $adType,
        'category_group' => $categoryGroup,
        'brand' => $brand,
        'model' => trim((string)($row['model'] ?? '')),
        'storage' => '',
        'price' => $price,
        'currency' => normalizeAdCurrency((string)($row['currency'] ?? 'eur')),
        'price_type' => $priceType,
        'condition_state' => trim((string)($row['condition_state'] ?? '')),
        'location' => $location,
        'country' => 'Srbija',
        'contact_phone' => $phone,
        'shop_name' => trim((string)($targetUser['shop_name'] ?? '')),
        'shop_category_id' => normalizeAdShopCategoryId($targetUser, (string)($row['shop_category_id'] ?? '')),
        'is_active' => !empty($row['is_active']) ? 1 : 0,
        'is_sold' => 0,
        'is_promoted' => 0,
        'images' => [],
        'created_by' => $targetUserId,
        '_images_final' => true,
        'kp_source_id' => trim((string)($row['source_id'] ?? '')),
        'kp_source_url' => trim((string)($row['source_url'] ?? '')),
        'views' => 0,
    ], $extras);

    if ($adType === 'telefon' && empty($row['download_images']) && empty($row['image_urls'])) {
        return ['ok' => false, 'error' => 'Telefon mora imati bar jednu sliku: ' . $title];
    }

    try {
        $adId = saveAd($payload);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    if (!empty($row['download_images'])) {
        $urls = is_array($row['image_urls'] ?? null) ? $row['image_urls'] : [];
        $images = kpDownloadRemoteAdImages($adId, $urls);
        if ($adType === 'telefon' && $images === []) {
            deleteAdById($adId);
            return ['ok' => false, 'error' => 'Slike nisu preuzete za: ' . $title];
        }
        if ($images !== []) {
            kpUpdateAdImages($adId, $images);
        }
    }

    return ['ok' => true, 'ad_id' => $adId];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array{imported:int, skipped:int, errors:list<string>, ad_ids:list<int>}
 */
function kpImportBatch(array $rows, int $targetUserId): array
{
    $targetUser = findUserById($targetUserId);
    if (!$targetUser) {
        return ['imported' => 0, 'skipped' => 0, 'errors' => ['Korisnik nije pronađen.'], 'ad_ids' => []];
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $adIds = [];

    foreach ($rows as $row) {
        if (empty($row['selected'])) {
            $skipped++;
            continue;
        }
        $result = kpImportSingleAd($row, $targetUserId, $targetUser);
        if ($result['ok']) {
            $imported++;
            $adIds[] = (int)$result['ad_id'];
        } else {
            $errors[] = (string)($result['error'] ?? 'Nepoznata greška.');
        }
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'ad_ids' => $adIds,
    ];
}

function kpImportCategoryGroupsJson(): string
{
    $cfg = categoriesConfig();
    $out = [
        'telefon' => [['key' => 'phones', 'label' => 'Uređaji']],
        'servis' => [['key' => 'service', 'label' => 'Servisne usluge']],
        'delovi' => [],
    ];
    foreach ($cfg['groups'] as $key => $group) {
        if ((string)($group['ad_type'] ?? '') !== 'delovi') {
            continue;
        }
        $out['delovi'][] = [
            'key' => $key,
            'label' => (string)($group['label'] ?? $key),
        ];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
