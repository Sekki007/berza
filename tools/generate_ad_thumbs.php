<?php

declare(strict_types=1);

/**
 * Jednokratno: napravi listing thumb-ove za postojeće oglase.
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
        $before = $img;
        $thumb = adListingThumbUrl($img);
        if ($thumb !== $before && $thumb !== '') {
            $done++;
            echo "OK  {$img} -> {$thumb}\n";
        } elseif ($thumb === $before) {
            $src = adImagePublicPath($img);
            if (!is_file($src)) {
                $fail++;
                echo "MISS {$img}\n";
            } else {
                $skip++;
            }
        }
    }
}

echo "\nDone. created/updated≈{$done}, already_ok≈{$skip}, missing≈{$fail}\n";
