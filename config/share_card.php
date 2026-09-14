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
    $meta = $price;
    if ($loc !== '') {
        $meta .= ' · ' . $loc;
    }
    return implode("\n", [
        'Oglas je na KupiTelefon.rs',
        $title,
        $meta,
        '',
        $url,
    ]);
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

/** @return list<string> */
function shareCardAdImageFiles(array $ad): array
{
    $out = [];
    $images = $ad['images'] ?? [];
    if (!is_array($images)) {
        $images = [];
    }
    $root = dirname(__DIR__) . '/public';
    foreach ($images as $img) {
        $rel = str_replace('\\', '/', trim((string)$img));
        if ($rel === '' || !str_starts_with($rel, '/uploads/ads/')) {
            continue;
        }
        $fs = $root . $rel;
        if (is_file($fs)) {
            $out[] = $fs;
        }
    }
    return array_values(array_unique($out));
}

function shareCardPasteCover(\GdImage $canvas, \GdImage $src, int $dx, int $dy, int $dw, int $dh): void
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1 || $dw < 1 || $dh < 1) {
        return;
    }
    $srcAspect = $sw / $sh;
    $dstAspect = $dw / $dh;
    if ($srcAspect > $dstAspect) {
        $cropW = max(1, (int)round($sh * $dstAspect));
        $cropH = $sh;
        $cropX = (int)round(($sw - $cropW) / 2);
        $cropY = 0;
    } else {
        $cropW = $sw;
        $cropH = max(1, (int)round($sw / $dstAspect));
        $cropX = 0;
        $cropY = (int)round(($sh - $cropH) / 2);
    }
    imagecopyresampled($canvas, $src, $dx, $dy, $cropX, $cropY, $dw, $dh, $cropW, $cropH);
}

/**
 * @return list<array{x:int,y:int,w:int,h:int}>
 */
function shareCardCollageLayout(int $n, int $x, int $y, int $w, int $h, int $gap): array
{
    $n = max(1, min(6, $n));
    if ($n === 1) {
        return [['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h]];
    }
    if ($n === 2) {
        $cw = (int)floor(($w - $gap) / 2);
        return [
            ['x' => $x, 'y' => $y, 'w' => $cw, 'h' => $h],
            ['x' => $x + $cw + $gap, 'y' => $y, 'w' => $w - $cw - $gap, 'h' => $h],
        ];
    }
    if ($n === 3) {
        $left = (int)floor($w * 0.58);
        $right = $w - $left - $gap;
        $rh = (int)floor(($h - $gap) / 2);
        return [
            ['x' => $x, 'y' => $y, 'w' => $left, 'h' => $h],
            ['x' => $x + $left + $gap, 'y' => $y, 'w' => $right, 'h' => $rh],
            ['x' => $x + $left + $gap, 'y' => $y + $rh + $gap, 'w' => $right, 'h' => $h - $rh - $gap],
        ];
    }
    if ($n === 4) {
        $cw = (int)floor(($w - $gap) / 2);
        $ch = (int)floor(($h - $gap) / 2);
        $cells = [];
        for ($r = 0; $r < 2; $r++) {
            for ($c = 0; $c < 2; $c++) {
                $cx = $x + $c * ($cw + $gap);
                $cy = $y + $r * ($ch + $gap);
                $cells[] = [
                    'x' => $cx,
                    'y' => $cy,
                    'w' => $c === 1 ? $w - $cw - $gap : $cw,
                    'h' => $r === 1 ? $h - $ch - $gap : $ch,
                ];
            }
        }
        return $cells;
    }
    if ($n === 5) {
        $topH = (int)floor(($h - $gap) * 0.56);
        $botH = $h - $topH - $gap;
        $tw = (int)floor(($w - $gap) / 2);
        $bw = (int)floor(($w - 2 * $gap) / 3);
        return [
            ['x' => $x, 'y' => $y, 'w' => $tw, 'h' => $topH],
            ['x' => $x + $tw + $gap, 'y' => $y, 'w' => $w - $tw - $gap, 'h' => $topH],
            ['x' => $x, 'y' => $y + $topH + $gap, 'w' => $bw, 'h' => $botH],
            ['x' => $x + $bw + $gap, 'y' => $y + $topH + $gap, 'w' => $bw, 'h' => $botH],
            ['x' => $x + 2 * ($bw + $gap), 'y' => $y + $topH + $gap, 'w' => $w - 2 * ($bw + $gap), 'h' => $botH],
        ];
    }
    $cw = (int)floor(($w - 2 * $gap) / 3);
    $ch = (int)floor(($h - $gap) / 2);
    $cells = [];
    for ($r = 0; $r < 2; $r++) {
        for ($c = 0; $c < 3; $c++) {
            $cells[] = [
                'x' => $x + $c * ($cw + $gap),
                'y' => $y + $r * ($ch + $gap),
                'w' => $c === 2 ? $w - 2 * ($cw + $gap) : $cw,
                'h' => $r === 1 ? $h - $ch - $gap : $ch,
            ];
        }
    }
    return $cells;
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

    $files = shareCardAdImageFiles($ad);
    $publicPath = adShareCardPath($adId);
    $dest = adShareCardFilesystemPath($adId);

    $imgStamp = 0;
    foreach ($files as $fs) {
        $imgStamp = max($imgStamp, (int)filemtime($fs));
    }
    $stamp = max(
        $imgStamp,
        strtotime((string)($ad['updated_at'] ?? $ad['created_at'] ?? '')) ?: 0,
        (int)@filemtime(__FILE__)
    );
    if (!$force && is_file($dest) && filemtime($dest) >= $stamp && filesize($dest) > 4000) {
        return $publicPath;
    }

    $size = 1080;
    $headerH = 88;
    $ctaH = 78;
    $infoH = 168;
    $photoH = $size - $headerH - $infoH - $ctaH;

    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) {
        return '';
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $green = imagecolorallocate($canvas, 45, 122, 62);
    $greenDark = imagecolorallocate($canvas, 28, 90, 44);
    $yellow = imagecolorallocate($canvas, 245, 197, 24);
    $text = imagecolorallocate($canvas, 22, 22, 22);
    $muted = imagecolorallocate($canvas, 90, 98, 110);
    $priceC = imagecolorallocate($canvas, 26, 107, 48);
    $photoBg = imagecolorallocate($canvas, 18, 22, 20);
    $ctaWhite = imagecolorallocate($canvas, 255, 255, 255);

    imagefilledrectangle($canvas, 0, 0, $size, $headerH, $green);
    imagefilledrectangle($canvas, 0, $headerH - 7, $size, $headerH, $yellow);

    $logoSize = 56;
    $logoX = 32;
    $logoY = (int)(($headerH - 7 - $logoSize) / 2);
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
    shareCardDrawText($canvas, 'KupiTelefon.rs', $logoX + $logoSize + 16, 42, 32, $white, true);
    shareCardDrawText($canvas, 'Berza telefona u Srbiji', $logoX + $logoSize + 16, 70, 16, $yellow, false);

    imagefilledrectangle($canvas, 0, $headerH, $size, $headerH + $photoH, $photoBg);

    $totalPhotos = count($files);
    $shown = min(6, $totalPhotos);
    $gap = 6;
    if ($shown > 0) {
        $cells = shareCardCollageLayout($shown, 0, $headerH, $size, $photoH, $gap);
        foreach ($cells as $i => $cell) {
            $src = loadImageResourceFromPath($files[$i]);
            if ($src === false) {
                continue;
            }
            shareCardPasteCover($canvas, $src, $cell['x'], $cell['y'], $cell['w'], $cell['h']);
            imagedestroy($src);
        }
        $extra = $totalPhotos - $shown;
        if ($extra > 0 && $cells !== []) {
            $last = $cells[count($cells) - 1];
            $overlay = imagecolorallocatealpha($canvas, 0, 0, 0, 70);
            imagefilledrectangle(
                $canvas,
                $last['x'],
                $last['y'],
                $last['x'] + $last['w'] - 1,
                $last['y'] + $last['h'] - 1,
                $overlay
            );
            $more = '+' . $extra;
            $mw = shareCardTextWidth($more, 48, true);
            shareCardDrawText(
                $canvas,
                $more,
                $last['x'] + (int)(($last['w'] - $mw) / 2),
                $last['y'] + (int)($last['h'] / 2) + 16,
                48,
                $ctaWhite,
                true
            );
        }
    } else {
        shareCardDrawText($canvas, 'KupiTelefon.rs', 340, $headerH + (int)($photoH / 2), 36, $muted, true);
    }

    $infoTop = $headerH + $photoH;
    imagefilledrectangle($canvas, 0, $infoTop, $size, $infoTop + $infoH, $white);

    $pad = 36;
    $price = formatAdPrice($ad);
    shareCardDrawText($canvas, $price, $pad, $infoTop + 58, 48, $priceC, true);

    $loc = trim((string)($ad['location'] ?? ''));
    if ($loc !== '') {
        $pw = shareCardTextWidth($price, 48, true);
        shareCardDrawText($canvas, '·  ' . $loc, $pad + $pw + 18, $infoTop + 54, 22, $muted, false);
    }

    $title = adDisplayTitle($ad);
    $titleLines = shareCardWrapLines($title, 26, $size - ($pad * 2), 2, true);
    $ty = $infoTop + 102;
    foreach ($titleLines as $line) {
        shareCardDrawText($canvas, $line, $pad, $ty, 26, $text, true);
        $ty += 36;
    }

    $ctaTop = $size - $ctaH;
    imagefilledrectangle($canvas, 0, $ctaTop, $size, $size, $greenDark);
    imagefilledrectangle($canvas, 0, $ctaTop, $size, $ctaTop + 6, $yellow);
    $cta = 'Oglas se nalazi na kupitelefon.rs';
    $cw = shareCardTextWidth($cta, 26, true);
    shareCardDrawText($canvas, $cta, (int)(($size - $cw) / 2), $ctaTop + 50, 26, $ctaWhite, true);

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
    $ok = imagejpeg($canvas, $dest, 88);
    imagedestroy($canvas);
    return $ok ? $publicPath : '';
}
