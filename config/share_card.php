<?php

declare(strict_types=1);

/** Kvadratna 1080×1080 slika za Facebook grupu (bolji reach od samog linka). */
function adShareCardPath(int $adId): string
{
    return '/uploads/ads/' . max(1, $adId) . '/fb.jpg';
}

function adShareCardFilesystemPath(int $adId): string
{
    return uploadsDir() . '/' . max(1, $adId) . '/fb.jpg';
}

function adShareCardCaption(array $ad): string
{
    $title = trim(adDisplayTitle($ad));
    $price = formatAdPrice($ad);
    $loc = trim((string)($ad['location'] ?? ''));
    $url = function_exists('absoluteUrl') ? absoluteUrl(adUrl($ad)) : adUrl($ad);
    $lines = [$title];
    $meta = $price;
    if ($loc !== '') {
        $meta .= ' · ' . $loc;
    }
    $lines[] = $meta;
    $lines[] = '';
    $lines[] = $url;
    return implode("\n", $lines);
}

function shareCardFontPath(bool $bold = true): ?string
{
    static $cache = [];
    $key = $bold ? 'b' : 'r';
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $candidates = $bold
        ? [
            dirname(__DIR__) . '/public/assets/fonts/DejaVuSans-Bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ]
        : [
            dirname(__DIR__) . '/public/assets/fonts/DejaVuSans.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $cache[$key] = $path;
            return $path;
        }
    }
    if ($bold) {
        $cache[$key] = null;
        return null;
    }
    $cache[$key] = shareCardFontPath(true);
    return $cache[$key];
}

function shareCardTextWidth(string $text, int $size, bool $bold = true): int
{
    $font = shareCardFontPath($bold);
    if ($font !== null && function_exists('imagettfbbox')) {
        $box = imagettfbbox($size, 0, $font, $text);
        if (is_array($box)) {
            return max(1, (int)abs($box[2] - $box[0]));
        }
    }
    return max(8, (int)round(mb_strlen($text) * $size * 0.55));
}

function shareCardDrawText(\GdImage $img, string $text, int $x, int $y, int $size, int $color, bool $bold = true): void
{
    $font = shareCardFontPath($bold);
    if ($font !== null && function_exists('imagettftext')) {
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
        return;
    }
    imagestring($img, 5, $x, max(0, $y - 14), $text, $color);
}

/** @return list<string> */
function shareCardWrapLines(string $text, int $size, int $maxWidth, int $maxLines, bool $bold = true): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return [];
    }
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $try = $current === '' ? $word : $current . ' ' . $word;
        if (shareCardTextWidth($try, $size, $bold) <= $maxWidth) {
            $current = $try;
            continue;
        }
        if ($current !== '') {
            $lines[] = $current;
            if (count($lines) >= $maxLines) {
                $current = '';
                break;
            }
        }
        $current = $word;
    }
    if ($current !== '' && count($lines) < $maxLines) {
        $lines[] = $current;
    }
    if ($lines === []) {
        return [$text];
    }
    $used = implode(' ', $lines);
    if (mb_strlen($text) > mb_strlen($used) && isset($lines[$maxLines - 1])) {
        $last = $lines[$maxLines - 1];
        while (shareCardTextWidth($last . '…', $size, $bold) > $maxWidth && mb_strlen($last) > 4) {
            $last = trim(mb_substr($last, 0, -1));
        }
        $lines[$maxLines - 1] = $last . '…';
    }
    return $lines;
}

/**
 * Napravi/keširaj 1080×1080 JPEG. Vraća relativni URL ili prazan string.
 */
function ensureAdShareCard(array $ad, bool $force = false): string
{
    $adId = (int)($ad['id'] ?? 0);
    if ($adId <= 0 || !function_exists('imagecreatetruecolor')) {
        return '';
    }

    $primary = adPrimaryImage($ad);
    $publicPath = adShareCardPath($adId);
    $dest = adShareCardFilesystemPath($adId);

    $srcFs = '';
    if (is_string($primary) && $primary !== '' && str_starts_with(str_replace('\\', '/', $primary), '/uploads/ads/')) {
        $srcFs = dirname(__DIR__) . '/public' . str_replace('\\', '/', $primary);
        if (!is_file($srcFs)) {
            $srcFs = '';
        }
    }

    $stamp = max(
        $srcFs !== '' ? (int)filemtime($srcFs) : 0,
        strtotime((string)($ad['updated_at'] ?? $ad['created_at'] ?? '')) ?: 0
    );
    if (!$force && is_file($dest) && filemtime($dest) >= $stamp && filesize($dest) > 4000) {
        return $publicPath;
    }

    $size = 1080;
    $headerH = 96;
    $footerH = 250;
    $photoH = $size - $headerH - $footerH;

    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) {
        return '';
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $green = imagecolorallocate($canvas, 45, 122, 62);
    $greenDark = imagecolorallocate($canvas, 28, 90, 44);
    $yellow = imagecolorallocate($canvas, 245, 197, 24);
    $text = imagecolorallocate($canvas, 26, 26, 26);
    $muted = imagecolorallocate($canvas, 90, 98, 110);
    $priceC = imagecolorallocate($canvas, 26, 107, 48);
    $photoBg = imagecolorallocate($canvas, 236, 238, 237);

    imagefilledrectangle($canvas, 0, 0, $size, $headerH, $green);
    imagefilledrectangle($canvas, 0, $headerH - 8, $size, $headerH, $yellow);

    $logoSize = 64;
    $logoX = 36;
    $logoY = (int)(($headerH - 8 - $logoSize) / 2);
    $logoFile = dirname(__DIR__) . '/public/assets/img/pwa-512.png';
    if (!is_file($logoFile)) {
        $logoFile = dirname(__DIR__) . '/public/assets/watermark-logo.png';
    }
    if (is_file($logoFile)) {
        $logo = @imagecreatefrompng($logoFile);
        if ($logo !== false) {
            imagecopyresampled($canvas, $logo, $logoX, $logoY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
    }
    shareCardDrawText($canvas, 'KupiTelefon.rs', $logoX + $logoSize + 18, 62, 36, $white, true);

    imagefilledrectangle($canvas, 0, $headerH, $size, $headerH + $photoH, $photoBg);

    if ($srcFs !== '') {
        $src = loadImageResourceFromPath($srcFs);
        if ($src !== false) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            if ($sw > 0 && $sh > 0) {
                $scale = max($size / $sw, $photoH / $sh);
                $nw = (int)round($sw * $scale);
                $nh = (int)round($sh * $scale);
                $dx = (int)round(($size - $nw) / 2);
                $dy = $headerH + (int)round(($photoH - $nh) / 2);
                imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
            }
            imagedestroy($src);
        }
    } else {
        shareCardDrawText($canvas, 'KupiTelefon', 360, $headerH + (int)($photoH / 2), 42, $muted, true);
    }

    imagefilledrectangle($canvas, 0, $headerH + $photoH, $size, $size, $white);
    imagefilledrectangle($canvas, 0, $size - 14, $size, $size, $yellow);

    $pad = 40;
    $y = $headerH + $photoH + 58;
    $price = formatAdPrice($ad);
    shareCardDrawText($canvas, $price, $pad, $y, 52, $priceC, true);

    $title = adDisplayTitle($ad);
    $titleLines = shareCardWrapLines($title, 28, $size - ($pad * 2), 2, true);
    $ty = $y + 48;
    foreach ($titleLines as $line) {
        shareCardDrawText($canvas, $line, $pad, $ty, 28, $text, true);
        $ty += 38;
    }

    $loc = trim((string)($ad['location'] ?? ''));
    $foot = $loc !== '' ? $loc . '  ·  kupitelefon.rs' : 'kupitelefon.rs';
    shareCardDrawText($canvas, $foot, $pad, $size - 36, 22, $muted, false);

    if (!empty($ad['is_sold'])) {
        $overlay = imagecolorallocatealpha($canvas, 255, 255, 255, 55);
        imagefilledrectangle($canvas, 0, $headerH, $size, $headerH + $photoH, $overlay);
        $red = imagecolorallocate($canvas, 211, 47, 47);
        $label = isBuyListing($ad) ? 'PRONAĐENO' : 'PRODATO';
        $lw = shareCardTextWidth($label, 64, true);
        shareCardDrawText($canvas, $label, (int)(($size - $lw) / 2), $headerH + (int)($photoH / 2) + 20, 64, $red, true);
    }

    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ok = imagejpeg($canvas, $dest, 86);
    imagedestroy($canvas);
    return $ok ? $publicPath : '';
}
