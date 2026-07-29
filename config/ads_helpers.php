<?php

declare(strict_types=1);

function categoriesConfig(): array
{
    static $config = null;
    if ($config === null) {
        $base = require __DIR__ . '/categories.php';
        $settings = siteSettings();
        $base['brands'] = $settings['brands'];
        $base['cities'] = $settings['cities'];
        $base['conditions'] = $settings['conditions'];
        $config = $base;
    }
    return $config;
}

function uploadsDir(): string
{
    return dirname(__DIR__) . '/public/uploads/ads';
}

function ensureUploadsDir(): void
{
    $dir = uploadsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function adPrimaryImage(array $ad): ?string
{
    $images = $ad['images'] ?? [];
    if (is_array($images) && !empty($images[0])) {
        return (string)$images[0];
    }
    return null;
}

function handleAdImageUploads(int $adId, array $existing = []): array
{
    ensureUploadsDir();
    $images = $existing;

    $order = $_POST['image_order'] ?? null;
    if (is_array($order) && $order !== []) {
        $ordered = [];
        foreach ($order as $url) {
            $url = (string)$url;
            if (in_array($url, $images, true) && !in_array($url, $ordered, true)) {
                $ordered[] = $url;
            }
        }
        foreach ($images as $url) {
            if (!in_array($url, $ordered, true)) {
                $ordered[] = $url;
            }
        }
        $images = $ordered;
    }

    $keep = $_POST['keep_images'] ?? [];
    if (is_array($keep)) {
        $images = array_values(array_filter($images, static fn($img) => in_array($img, $keep, true)));
    }

    $cover = trim((string)($_POST['cover_image'] ?? ''));
    if ($cover !== '' && in_array($cover, $images, true)) {
        $images = array_values(array_filter($images, static fn($img) => $img !== $cover));
        array_unshift($images, $cover);
    }

    // KP import: privremene slike → uploads/ads/{id}/
    $kpTemps = $_POST['kp_import_images'] ?? [];
    if (is_array($kpTemps) && $kpTemps !== [] && function_exists('kpImportPromoteImages')) {
        $userId = (int)(currentUser()['id'] ?? 0);
        if ($userId > 0) {
            $images = kpImportPromoteImages($userId, $adId, $kpTemps, $images);
        }
    }

    if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
        return array_values(array_slice($images, 0, 10));
    }

    $targetDir = uploadsDir() . '/' . $adId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $count = count($_FILES['images']['name']);

    for ($i = 0; $i < $count && count($images) < 10; $i++) {
        if ((($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            continue;
        }
        $tmp = $_FILES['images']['tmp_name'][$i];
        $type = mime_content_type($tmp) ?: '';
        if (!in_array($type, $allowed, true)) {
            continue;
        }

        $name = 'img_' . time() . '_' . $i . '.jpg';
        $dest = $targetDir . '/' . $name;
        if (compressAndSaveImage($tmp, $dest, $type)) {
            $images[] = '/uploads/ads/' . $adId . '/' . $name;
        }
    }

    return array_values(array_slice($images, 0, 10));
}

/**
 * Kompresuje i smanjuje sliku (max 1600px, JPEG ~82%). Fallback: move_uploaded_file.
 */
function compressAndSaveImage(string $tmpPath, string $destPath, string $mime): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($tmpPath, $destPath);
    }

    $src = match ($mime) {
        'image/png' => @imagecreatefrompng($tmpPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
        'image/gif' => @imagecreatefromgif($tmpPath),
        default => @imagecreatefromjpeg($tmpPath),
    };
    if ($src === false) {
        return move_uploaded_file($tmpPath, $destPath);
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $max = 1600;
    if ($w > $max || $h > $max) {
        $ratio = min($max / max(1, $w), $max / max(1, $h));
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $ok = imagejpeg($src, $destPath, 82);
    imagedestroy($src);
    return (bool)$ok;
}

function incrementAdViews(int $adId): void
{
    if ($adId <= 0) {
        return;
    }
    if (!isset($_SESSION['ad_view_hits']) || !is_array($_SESSION['ad_view_hits'])) {
        $_SESSION['ad_view_hits'] = [];
    }
    $key = (string)$adId;
    $last = (int)($_SESSION['ad_view_hits'][$key] ?? 0);
    if ($last > 0 && (time() - $last) < 1800) {
        return;
    }
    $_SESSION['ad_view_hits'][$key] = time();

    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['id'] ?? 0) === $adId) {
            $ad['views'] = (int)($ad['views'] ?? 0) + 1;
            writeJsonFile('ads.json', $ads);
            if (function_exists('bumpAdStat')) {
                bumpAdStat($adId, 'views');
            }
            return;
        }
    }
}

function getSimilarAds(array $ad, int $limit = 6): array
{
    $type = getAdType($ad);
    $model = mb_strtolower((string)($ad['model'] ?? ''));
    $brand = mb_strtolower((string)($ad['brand'] ?? ''));
    $location = mb_strtolower((string)($ad['location'] ?? ''));
    $storage = mb_strtolower((string)($ad['storage'] ?? ''));
    $price = adPriceEur($ad);
    $adId = (int)($ad['id'] ?? 0);

    $candidates = array_values(array_filter(getPublicAds(), static fn($item) => (int)($item['id'] ?? 0) !== $adId));

    $scored = [];
    foreach ($candidates as $item) {
        $s = 0;
        if (getAdType($item) === $type) {
            $s += 3;
        }
        $itemModel = mb_strtolower((string)($item['model'] ?? ''));
        $itemBrand = mb_strtolower((string)($item['brand'] ?? ''));
        if ($model !== '' && $itemModel === $model) {
            $s += 8;
        } elseif ($model !== '' && $itemModel !== '' && (str_contains($itemModel, $model) || str_contains($model, $itemModel))) {
            $s += 4;
        }
        if ($brand !== '' && $itemBrand === $brand) {
            $s += 3;
        }
        if ($location !== '' && mb_strtolower((string)($item['location'] ?? '')) === $location) {
            $s += 2;
        }
        if ($storage !== '' && mb_strtolower((string)($item['storage'] ?? '')) === $storage) {
            $s += 1;
        }
        if ($price > 0 && adPriceType($item) === 'fixed') {
            $itemEur = adPriceEur($item);
            $diff = abs($itemEur - $price);
            if ($diff <= $price * 0.25) {
                $s += 2;
            } elseif ($diff <= $price * 0.5) {
                $s += 1;
            }
        }
        if ($s < 3) {
            continue;
        }
        $scored[] = ['ad' => $item, 'score' => $s];
    }

    usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_map(static fn($row) => $row['ad'], array_slice($scored, 0, $limit));
}

function toggleAdSold(int $adId, int $userId): ?bool
{
    $ad = getAdById($adId);
    if (!$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
        return null;
    }
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$row) {
        if ((int)($row['id'] ?? 0) !== $adId) {
            continue;
        }
        $row['is_sold'] = empty($row['is_sold']) ? 1 : 0;
        if (!empty($row['is_sold'])) {
            $row['is_promoted'] = 0;
        }
        $row['updated_at'] = date('Y-m-d H:i:s');
        writeJsonFile('ads.json', $ads);
        return !empty($row['is_sold']);
    }
    return null;
}

function userOwnsAd(array $ad, int $userId): bool
{
    return (int)($ad['created_by'] ?? 0) === $userId || isAdmin();
}

function sortAds(array $ads, string $sort): array
{
    usort($ads, static function ($a, $b) use ($sort) {
        $aTop = function_exists('isAdTopActive') ? isAdTopActive($a) : !empty($a['is_promoted']);
        $bTop = function_exists('isAdTopActive') ? isAdTopActive($b) : !empty($b['is_promoted']);
        if ($aTop && !$bTop) {
            return -1;
        }
        if (!$aTop && $bTop) {
            return 1;
        }

        $aTime = (string)($a['bumped_at'] ?? $a['created_at'] ?? '');
        $bTime = (string)($b['bumped_at'] ?? $b['created_at'] ?? '');

        return match ($sort) {
            'price_asc' => (adPriceType($a) === 'fixed' ? adPriceEur($a) : PHP_FLOAT_MAX)
                <=> (adPriceType($b) === 'fixed' ? adPriceEur($b) : PHP_FLOAT_MAX),
            'price_desc' => (adPriceType($b) === 'fixed' ? adPriceEur($b) : -1.0)
                <=> (adPriceType($a) === 'fixed' ? adPriceEur($a) : -1.0),
            default => strcmp($bTime, $aTime),
        };
    });
    return $ads;
}

function paginateAds(array $ads, int $page, int $perPage = 20): array
{
    $total = count($ads);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice($ads, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ];
}

function favoriteIds(): array
{
    if (!isset($_SESSION['favorites']) || !is_array($_SESSION['favorites'])) {
        $_SESSION['favorites'] = [];
    }
    return array_map('intval', $_SESSION['favorites']);
}

function toggleFavorite(int $adId): bool
{
    $favorites = favoriteIds();
    if (in_array($adId, $favorites, true)) {
        $_SESSION['favorites'] = array_values(array_filter($favorites, static fn($id) => $id !== $adId));
        return false;
    }
    $favorites[] = $adId;
    $_SESSION['favorites'] = $favorites;
    return true;
}

function isFavorite(int $adId): bool
{
    return in_array($adId, favoriteIds(), true);
}

function getFavoriteAds(): array
{
    $ids = favoriteIds();
    if ($ids === []) {
        return [];
    }
    return array_values(array_filter(getAllAds(), static fn($ad) => in_array((int)($ad['id'] ?? 0), $ids, true)));
}

function whatsappLink(string $phone, string $message): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '381' . substr($digits, 1);
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

/**
 * Gradovi sa aktivnim oglasima (nisu prodati), sortirani po broju oglasa.
 * Blaga dnevna rotacija među sličnim gradovima da chipovi nisu uvek isti.
 *
 * @return list<string>
 */
function getCitiesWithActiveAds(int $limit = 8, string $preferCity = ''): array
{
    $limit = max(1, $limit);
    $counts = [];
    foreach (getAllAds() as $ad) {
        if ((int)($ad['is_active'] ?? 0) !== 1 || !empty($ad['is_sold'])) {
            continue;
        }
        $city = trim((string)($ad['location'] ?? ''));
        if ($city === '') {
            continue;
        }
        $counts[$city] = ($counts[$city] ?? 0) + 1;
    }
    if ($counts === []) {
        return [];
    }

    $cities = array_keys($counts);
    $daySeed = (int)date('Ymd');
    usort($cities, static function (string $a, string $b) use ($counts, $daySeed): int {
        $ca = $counts[$a] ?? 0;
        $cb = $counts[$b] ?? 0;
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        return (($daySeed + crc32($a)) % 97) <=> (($daySeed + crc32($b)) % 97);
    });

    $out = array_slice($cities, 0, $limit);
    $preferCity = trim($preferCity);
    if ($preferCity !== '' && isset($counts[$preferCity]) && !in_array($preferCity, $out, true)) {
        array_unshift($out, $preferCity);
        $out = array_values(array_unique($out));
        $out = array_slice($out, 0, $limit);
    }

    return $out;
}

function buildFilterQuery(array $params): string
{
    $filtered = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== []);
    return http_build_query($filtered);
}

function normalizeAdDefaults(array $payload): array
{
    $payload['images'] = $payload['images'] ?? [];
    $payload['views'] = (int)($payload['views'] ?? 0);
    $payload['is_sold'] = (int)($payload['is_sold'] ?? 0);
    $payload['is_promoted'] = (int)($payload['is_promoted'] ?? 0);
    $payload['shop_name'] = trim((string)($payload['shop_name'] ?? ''));
    $payload['country'] = trim((string)($payload['country'] ?? 'Srbija'));
    $payload['category_group'] = trim((string)($payload['category_group'] ?? ''));
    $payload['currency'] = normalizeAdCurrency((string)($payload['currency'] ?? 'eur'));
    $payload['price_type'] = normalizeAdPriceType((string)($payload['price_type'] ?? 'fixed'));
    if ($payload['price_type'] !== 'fixed') {
        $payload['price'] = 0;
    } else {
        $payload['price'] = max(0, (float)($payload['price'] ?? 0));
    }
    return $payload;
}
