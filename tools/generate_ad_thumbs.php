<?php

declare(strict_types=1);

/**
 * Jednokratno: napravi listing (_t) i gallery (_d) derivate za postojeće oglase.
 * Pokretanje: php tools/generate_ad_thumbs.php
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$ads = getAllAds();
$done = 0;
$skip = 0;
$fail = 0;

foreach ($ads as $ad) {
    $images = (array)($ad['images'] ?? []);
    foreach ($images as $img) {
        if (!is_string($img) || $img === '') {
            continue;
        }
        $src = adImagePublicPath($img);
        if (!is_file($src)) {
            $fail++;
            echo "MISS {$img}\n";
            continue;
        }
        $variants = [
            adListingThumbUrl($img),
            adGalleryDisplayUrl($img),
        ];
        $made = false;
        foreach ($variants as $variant) {
            if ($variant !== $img && $variant !== '') {
                $made = true;
                echo "OK  {$img} -> {$variant}\n";
            }
        }
        if ($made) {
            $done++;
        } else {
            $skip++;
        }
    }
}

echo "\nDone. images_with_variants≈{$done}, already_ok≈{$skip}, missing≈{$fail}\n";
