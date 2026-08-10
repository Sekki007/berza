<?php

declare(strict_types=1);

/**
 * Watermark na slikama oglasa (KupiTelefon.rs + KT logo).
 */

function adWatermarkEnabled(): bool
{
    $settings = siteSettings();
    if (array_key_exists('ad_image_watermark', $settings)) {
        return !empty($settings['ad_image_watermark']);
    }
    return true;
}

/** @return 'bottom-right'|'bottom-left' */
function adWatermarkPosition(): string
{
    $pos = strtolower(trim((string)(siteSettings()['ad_image_watermark_position'] ?? 'bottom-right')));
    return $pos === 'bottom-left' ? 'bottom-left' : 'bottom-right';
}

function adWatermarkLogoPath(): string
{
    return dirname(__DIR__) . '/public/assets/watermark-logo.png';
}

function adWatermarkLabel(): string
{
    $label = trim((string)(siteSettings()['ad_image_watermark_label'] ?? 'KupiTelefon.rs'));
    return $label !== '' ? $label : 'KupiTelefon.rs';
}

function adImagePathShouldWatermark(string $path): bool
{
    $norm = str_replace('\\', '/', $path);
    return str_contains($norm, '/uploads/ads/') || str_contains($norm, '\\uploads\\ads\\');
}

/**
 * Nalepi watermark na sačuvanu JPEG sliku oglasa.
 */
function applyAdImageWatermark(string $imagePath): bool
{
    if (!adWatermarkEnabled() || !adImagePathShouldWatermark($imagePath)) {
        return false;
    }
    if (!function_exists('imagecreatetruecolor') || !is_file($imagePath)) {
        return false;
    }

    $src = @imagecreatefromjpeg($imagePath);
    if ($src === false) {
        return false;
    }

    $imgW = imagesx($src);
    $imgH = imagesy($src);
    if ($imgW < 120 || $imgH < 120) {
        imagedestroy($src);
        return false;
    }

    $ok = adDrawWatermarkOnImage($src, $imgW, $imgH);
    if ($ok) {
        $ok = (bool)@imagejpeg($src, $imagePath, 78);
    }
    imagedestroy($src);
    return $ok;
}

function adDrawWatermarkOnImage(\GdImage $src, int $imgW, int $imgH): bool
{
    $label = adWatermarkLabel();
    $position = adWatermarkPosition();
    $margin = max(6, (int)round(min($imgW, $imgH) * 0.012));

    // Diskretan badge ~3.8% visine slike (max 40px), da ne prekriva telefon
    $badgeH = max(22, min(40, (int)round($imgH * 0.038)));
    $logoSize = max(16, $badgeH - 6);
    $fontSize = max(8, min(12, (int)round($badgeH * 0.36)));
    $textW = adWatermarkTextWidth($label, $fontSize);
    $padX = 6;
    $gap = 4;
    $badgeW = $padX + $logoSize + $gap + $textW + $padX;

    $badge = imagecreatetruecolor($badgeW, $badgeH);
    if ($badge === false) {
        return false;
    }
    imagealphablending($badge, false);
    imagesavealpha($badge, true);
    $transparent = imagecolorallocatealpha($badge, 0, 0, 0, 127);
    imagefilledrectangle($badge, 0, 0, $badgeW, $badgeH, $transparent);
    imagealphablending($badge, true);

    $bg = imagecolorallocatealpha($badge, 20, 95, 45, 18);
    $border = imagecolorallocatealpha($badge, 255, 204, 0, 30);
    adDrawRoundedRect($badge, 0, 0, $badgeW - 1, $badgeH - 1, (int)round($badgeH / 2), $bg);
    imagerectangle($badge, 0, 0, $badgeW - 1, $badgeH - 1, $border);

    adWatermarkDrawLogo($badge, $padX, (int)(($badgeH - $logoSize) / 2), $logoSize);
    $textColor = imagecolorallocate($badge, 255, 255, 255);
    $shadow = imagecolorallocatealpha($badge, 0, 0, 0, 60);
    $textX = $padX + $logoSize + $gap;
    $textY = (int)(($badgeH + $fontSize) / 2) - 3;
    adWatermarkDrawText($badge, $label, $textX + 1, $textY + 1, $fontSize, $shadow);
    adWatermarkDrawText($badge, $label, $textX, $textY, $fontSize, $textColor);

    $destX = $position === 'bottom-left'
        ? $margin
        : max($margin, $imgW - $badgeW - $margin);
    $destY = max($margin, $imgH - $badgeH - $margin);

    imagealphablending($src, true);
    imagecopy($src, $badge, $destX, $destY, 0, 0, $badgeW, $badgeH);
    imagedestroy($badge);
    return true;
}

function adWatermarkDrawLogo(\GdImage $canvas, int $x, int $y, int $size): void
{
    $logoPath = adWatermarkLogoPath();
    if (!is_file($logoPath)) {
        return;
    }
    $logo = @imagecreatefrompng($logoPath);
    if ($logo === false) {
        return;
    }
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    if ($lw < 1 || $lh < 1) {
        imagedestroy($logo);
        return;
    }
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $size, $size, $lw, $lh);
    imagedestroy($logo);
}

function adWatermarkFontPath(): ?string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached !== '' ? $cached : null;
    }
    $candidates = [
        dirname(__DIR__) . '/public/assets/fonts/DejaVuSans-Bold.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $cached = $path;
            return $path;
        }
    }
    $cached = '';
    return null;
}

function adWatermarkTextWidth(string $text, int $fontSize): int
{
    $font = adWatermarkFontPath();
    if ($font !== null && function_exists('imagettfbbox')) {
        $box = imagettfbbox($fontSize, 0, $font, $text);
        if (is_array($box)) {
            return max(1, (int)abs($box[2] - $box[0]));
        }
    }
    return max(60, (int)(strlen($text) * ($fontSize * 0.58)));
}

function adWatermarkDrawText(\GdImage $canvas, string $text, int $x, int $y, int $fontSize, int $color): void
{
    $font = adWatermarkFontPath();
    if ($font !== null && function_exists('imagettftext')) {
        imagettftext($canvas, $fontSize, 0, $x, $y, $color, $font, $text);
        return;
    }
    $builtIn = 5;
    imagestring($canvas, $builtIn, $x, max(0, $y - 12), $text, $color);
}

function adDrawRoundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    $radius = max(2, min($radius, (int)(($x2 - $x1) / 2), (int)(($y2 - $y1) / 2)));
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
