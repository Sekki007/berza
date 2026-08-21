<?php

declare(strict_types=1);

require_once __DIR__ . '/image_watermark.php';

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

/** Apsolutna putanja fajla u public/ za /uploads/... URL. */
function adImagePublicPath(string $url): string
{
    $url = '/' . ltrim(str_replace('\\', '/', $url), '/');
    return dirname(__DIR__) . '/public' . $url;
}

/**
 * URL derivata slike (WebP/JPEG). $suffix npr. "_t" ili "_d".
 * Ako ne postoji, pravi ga iz originala.
 */
function adImageVariantUrl(string $imageUrl, string $suffix, int $maxEdge = 400, int $quality = 78): string
{
    $imageUrl = trim($imageUrl);
    $suffix = trim($suffix);
    if ($imageUrl === '' || $suffix === '' || !str_starts_with($imageUrl, '/uploads/')) {
        return $imageUrl;
    }

    $info = pathinfo($imageUrl);
    $dir = (string)($info['dirname'] ?? '');
    $base = (string)($info['filename'] ?? '');
    if ($dir === '' || $base === '' || str_ends_with($base, '_t') || str_ends_with($base, '_d')) {
        return $imageUrl;
    }

    $webpUrl = $dir . '/' . $base . $suffix . '.webp';
    $jpgUrl = $dir . '/' . $base . $suffix . '.jpg';
    $fsWebp = adImagePublicPath($webpUrl);
    $fsJpg = adImagePublicPath($jpgUrl);
    if (is_file($fsWebp)) {
        return $webpUrl;
    }
    if (is_file($fsJpg)) {
        return $jpgUrl;
    }

    $srcFs = adImagePublicPath($imageUrl);
    if (!is_file($srcFs)) {
        return $imageUrl;
    }

    $preferWebp = function_exists('imagewebp');
    $destFs = $preferWebp ? $fsWebp : $fsJpg;
    $destUrl = $preferWebp ? $webpUrl : $jpgUrl;
    if (createImageDerivative($srcFs, $destFs, $maxEdge, $quality)) {
        return $destUrl;
    }
    return $imageUrl;
}

/** Listing kartice (~400px). */
function adListingThumbUrl(string $imageUrl): string
{
    return adImageVariantUrl($imageUrl, '_t', 400, 78);
}

/** Galerija na detail stranici (~800px); lightbox i dalje koristi original. */
function adGalleryDisplayUrl(string $imageUrl): string
{
    return adImageVariantUrl($imageUrl, '_d', 800, 80);
}

function adPrimaryListingThumb(array $ad): ?string
{
    $img = adPrimaryImage($ad);
    if ($img === null || $img === '') {
        return null;
    }
    return adListingThumbUrl($img);
}

/**
 * Pravi umanjeni derivat (JPEG ili WebP po ekstenziji destinacije).
 */
function createImageDerivative(string $srcPath, string $destPath, int $maxEdge = 400, int $quality = 78): bool
{
    if (!function_exists('imagecreatetruecolor') || !is_file($srcPath)) {
        return false;
    }

    $mime = mime_content_type($srcPath) ?: '';
    $src = match (true) {
        str_contains($mime, 'png') => @imagecreatefrompng($srcPath),
        str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($srcPath),
        str_contains($mime, 'gif') => @imagecreatefromgif($srcPath),
        default => @imagecreatefromjpeg($srcPath),
    };
    if ($src === false) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        imagedestroy($src);
        return false;
    }

    $scale = min(1.0, $maxEdge / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
        imagedestroy($src);
        return false;
    }
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $ok = false;
    if ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = @imagewebp($dst, $destPath, $quality);
    } else {
        $ok = @imagejpeg($dst, $destPath, $quality);
    }
    imagedestroy($dst);
    return (bool)$ok;
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

        $stamp = time() . '_' . $i;
        $name = 'img_' . $stamp . '.jpg';
        $dest = $targetDir . '/' . $name;
        if (compressAndSaveImage($tmp, $dest, $type)) {
            $url = '/uploads/ads/' . $adId . '/' . $name;
            $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
            createImageDerivative($dest, $targetDir . '/img_' . $stamp . '_t.' . $ext, 400, 78);
            createImageDerivative($dest, $targetDir . '/img_' . $stamp . '_d.' . $ext, 800, 80);
            $images[] = $url;
        }
    }

    return array_values(array_slice($images, 0, 10));
}

/**
 * Kompresuje i smanjuje sliku (max 1200px, JPEG ~78%). Fallback: move_uploaded_file.
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
    $max = 1200;
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

    $ok = imagejpeg($src, $destPath, 78);
    imagedestroy($src);
    if ($ok && function_exists('applyAdImageWatermark') && adImagePathShouldWatermark($destPath)) {
        applyAdImageWatermark($destPath);
    }
    return (bool)$ok;
}

/** Relativna putanja keširanog OG share slike (1200×630). */
function adOgImagePath(int $adId): string
{
    return '/uploads/ads/' . max(1, $adId) . '/og.jpg';
}

function adOgImageFilesystemPath(int $adId): string
{
    return uploadsDir() . '/' . max(1, $adId) . '/og.jpg';
}

/**
 * Učitaj lokalnu upload sliku u GD resource.
 * @return \GdImage|resource|false
 */
function loadImageResourceFromPath(string $path)
{
    if (!is_file($path) || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';
    if ($mime === '') {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
    return match ($mime) {
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        'image/gif' => @imagecreatefromgif($path),
        default => @imagecreatefromjpeg($path),
    };
}

/**
 * Pravi landscape 1200×630 JPEG za Viber/FB/WhatsApp preview.
 * Vraća relativni URL ili prazan string.
 */
function ensureAdOgImage(array $ad, bool $force = false): string
{
    $adId = (int)($ad['id'] ?? 0);
    if ($adId <= 0) {
        return '';
    }

    $primary = adPrimaryImage($ad);
    if ($primary === null || $primary === '') {
        return '';
    }

    $publicPath = adOgImagePath($adId);
    $dest = adOgImageFilesystemPath($adId);
    $srcRel = str_replace('\\', '/', (string)$primary);
    if (!str_starts_with($srcRel, '/uploads/ads/')) {
        return $srcRel;
    }
    $srcFs = dirname(__DIR__) . '/public' . $srcRel;

    if (!$force && is_file($dest) && is_file($srcFs) && filemtime($dest) >= filemtime($srcFs)) {
        return $publicPath;
    }

    if (!function_exists('imagecreatetruecolor') || !is_file($srcFs)) {
        return $srcRel;
    }

    $src = loadImageResourceFromPath($srcFs);
    if ($src === false) {
        return $srcRel;
    }

    $tw = 1200;
    $th = 630;
    $canvas = imagecreatetruecolor($tw, $th);
    if ($canvas === false) {
        imagedestroy($src);
        return $srcRel;
    }

    // Blaga svetla pozadina (brand-friendly, bez ljubičastog AI klompa)
    $bg = imagecolorallocate($canvas, 245, 246, 245);
    imagefilledrectangle($canvas, 0, 0, $tw, $th, $bg);

    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        imagedestroy($src);
        imagedestroy($canvas);
        return $srcRel;
    }

    // Cover: popuni ceo kadar
    $scale = max($tw / $sw, $th / $sh);
    $nw = (int)round($sw * $scale);
    $nh = (int)round($sh * $scale);
    $dx = (int)round(($tw - $nw) / 2);
    $dy = (int)round(($th - $nh) / 2);
    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
    imagedestroy($src);

    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ok = imagejpeg($canvas, $dest, 85);
    imagedestroy($canvas);

    return $ok ? $publicPath : $srcRel;
}

/** Obriši keš OG slike (npr. posle izmene fotografija). */
function invalidateAdOgImage(int $adId): void
{
    $path = adOgImageFilesystemPath($adId);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Hostovi tuđih oglasnika — ne prikazujemo ih u javnom tekstu oglasa.
 *
 * @return list<string>
 */
function competitorMarketplaceHosts(): array
{
    return [
        'kupujemprodajem.com',
        'kupujemprodajem.rs',
        'limundo.com',
        'halooglasi.com',
        'njuskalo.hr',
        'oglasnik.hr',
        'olx.rs',
        'olx.ba',
        'olx.hr',
        'facebook.com/marketplace',
        'fb.com/marketplace',
    ];
}

function competitorMarketplaceRegex(): string
{
    $parts = [];
    foreach (competitorMarketplaceHosts() as $host) {
        $parts[] = preg_quote($host, '~');
    }
    return '(?:www\.)?(?:' . implode('|', $parts) . ')';
}

/** Ukloni linkove i reklamu konkurencije iz opisa oglasa. */
function sanitizeAdPublicText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $host = competitorMarketplaceRegex();

    $text = preg_replace('~https?://' . $host . '[^\s<>"\']*~iu', '', $text) ?? $text;
    $text = preg_replace('~\b' . $host . '[^\s<>"\']*~iu', '', $text) ?? $text;

    $lines = explode("\n", $text);
    $kept = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $kept[] = '';
            continue;
        }
        if (preg_match('~(moj nalog na kupujemprodajem|nalog na kupujemprodajem|link za moj nalog|kupujem.?prodajem)~iu', $trim) === 1) {
            continue;
        }
        $kept[] = $line;
    }
    $text = implode("\n", $kept);
    $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;

    return trim($text);
}

function adListingExcerpt(array $ad, int $maxChars = 140): string
{
    $raw = sanitizeAdPublicText((string)($ad['description'] ?? ''));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (mb_strlen($raw) <= $maxChars) {
        return $raw;
    }
    $cut = mb_substr($raw, 0, $maxChars);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > 40) {
        $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut, " \t.,;:!?") . '…';
}

function isAutomatedViewer(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== '' && $method !== 'GET') {
        return true;
    }

    $ua = strtolower(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));
    if ($ua === '') {
        return true;
    }

    $needles = [
        'facebookexternalhit',
        'facebot',
        'facebookcatalog',
        'meta-externalagent',
        'meta-externalfetcher',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'telegrambot',
        'googlebot',
        'bingbot',
        'yandexbot',
        'baiduspider',
        'duckduckbot',
        'applebot',
        'semrushbot',
        'ahrefsbot',
        'dotbot',
        'mj12bot',
        'petalbot',
        'bytespider',
        'gptbot',
        'claudebot',
        'ia_archiver',
        'embedly',
        'pinterestbot',
        'discordbot',
        'vkshare',
    ];
    foreach ($needles as $needle) {
        if (str_contains($ua, $needle)) {
            return true;
        }
    }
    // WhatsApp/Telegram OG crawler nema Mozilla; in-app pregled korisnika ima.
    if ((str_contains($ua, 'whatsapp') || str_contains($ua, 'telegram')) && !str_contains($ua, 'mozilla')) {
        return true;
    }

    $purpose = strtolower((string)($_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_PURPOSE'] ?? ''));
    if (str_contains($purpose, 'preview')) {
        return true;
    }

    return false;
}

/** Početni pregledi za nov oglas — mlad sajt ne treba da stoji na 0–2 👁. */
function randomAdStartingViews(): int
{
    return random_int(28, 72);
}

/** Koliko sekundi isti posetilac ne sme ponovo da digne pregled (ranije 30 min). */
function adViewCooldownSeconds(): int
{
    return 90;
}

/**
 * Jednokratno: aktivni oglasi sa jako malo pregleda dobiju realističan start.
 * Flag: data/.ad_views_seeded_v1
 */
function maybeBackfillAdStartingViews(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $flag = dataPath('.ad_views_seeded_v1');
    if (is_file($flag)) {
        return;
    }

    $ads = readJsonFile('ads.json');
    if (!is_array($ads) || $ads === []) {
        @file_put_contents($flag, date('c'));
        return;
    }

    $changed = false;
    foreach ($ads as &$ad) {
        if (!is_array($ad)) {
            continue;
        }
        if ((int)($ad['is_active'] ?? 0) !== 1) {
            continue;
        }
        $views = (int)($ad['views'] ?? 0);
        if ($views >= 12) {
            continue;
        }
        $ad['views'] = randomAdStartingViews();
        $changed = true;
    }
    unset($ad);

    if ($changed) {
        writeJsonFile('ads.json', $ads);
    }
    @file_put_contents($flag, date('c'));
}

function incrementAdViews(int $adId): void
{
    if ($adId <= 0) {
        return;
    }
    if (isAutomatedViewer()) {
        return;
    }
    maybeBackfillAdStartingViews();

    if (!isset($_SESSION['ad_view_hits']) || !is_array($_SESSION['ad_view_hits'])) {
        $_SESSION['ad_view_hits'] = [];
    }
    $key = (string)$adId;
    $last = (int)($_SESSION['ad_view_hits'][$key] ?? 0);
    $cooldown = adViewCooldownSeconds();
    if ($last > 0 && (time() - $last) < $cooldown) {
        return;
    }
    $_SESSION['ad_view_hits'][$key] = time();

    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['id'] ?? 0) === $adId) {
            $current = (int)($ad['views'] ?? 0);
            if ($current < 1) {
                $current = randomAdStartingViews();
            }
            $ad['views'] = $current + 1;
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

/**
 * Ograniči broj oglasa istog korisnika u već sortiranoj listi (zadržava redosled).
 * $maxPerUser <= 0 = bez ograničenja.
 *
 * @param list<array<string,mixed>> $ads
 * @return list<array<string,mixed>>
 */
function limitAdsPerUser(array $ads, int $maxPerUser): array
{
    if ($maxPerUser <= 0) {
        return array_values($ads);
    }

    $counts = [];
    $out = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $userId = (int)($ad['created_by'] ?? 0);
        $key = $userId > 0 ? (string)$userId : 'anon:' . (int)($ad['id'] ?? 0);
        $used = (int)($counts[$key] ?? 0);
        if ($used >= $maxPerUser) {
            continue;
        }
        $counts[$key] = $used + 1;
        $out[] = $ad;
    }

    return $out;
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
    $payload['shop_category_id'] = trim((string)($payload['shop_category_id'] ?? ''));
    $payload['country'] = trim((string)($payload['country'] ?? 'Srbija'));
    $payload['category_group'] = trim((string)($payload['category_group'] ?? ''));
    $payload['description'] = sanitizeAdPublicText((string)($payload['description'] ?? ''));
    $payload['currency'] = normalizeAdCurrency((string)($payload['currency'] ?? 'eur'));
    $payload['price_type'] = normalizeAdPriceType((string)($payload['price_type'] ?? 'fixed'));
    if ($payload['price_type'] !== 'fixed') {
        $payload['price'] = 0;
    } else {
        $payload['price'] = max(0, (float)($payload['price'] ?? 0));
    }
    return $payload;
}
