<?php

declare(strict_types=1);

/** Jedna kanonska stranica modela je /katalog/{brend}/{model}. */
function listingCatalogRedirectTarget(string $brand, string $model): string
{
    $brand = trim($brand);
    $model = trim($model);
    if ($brand === '' || $model === '' || !function_exists('phoneCatalogFindBySlug')) {
        return '';
    }
    $found = phoneCatalogFindBySlug(listingFacetSlug($brand), listingFacetSlug($model));
    if (is_array($found)) {
        return phoneCatalogUrl($found);
    }
    if (!function_exists('phoneCatalogMatchAd')) {
        return '';
    }
    $pretty = str_replace('-', ' ', $model);
    $match = phoneCatalogMatchAd([
        'brand' => $brand,
        'model' => $pretty,
        'title' => trim($brand . ' ' . $pretty),
        'ad_type' => 'telefon',
        'device_type' => 'phone',
    ]);
    if (($match['status'] ?? '') !== 'linked') {
        return '';
    }
    $row = phoneCatalogModel((string)($match['model_id'] ?? ''));
    return is_array($row) ? phoneCatalogUrl($row) : '';
}

/**
 * @param list<array<string,mixed>> $offers
 * @return list<array{storage:string,condition:string,count:int,min:?float,median:?float,max:?float}>
 */
function modelHubPriceRows(array $offers): array
{
    $groups = [];
    foreach ($offers as $ad) {
        if (!is_array($ad) || (function_exists('isBuyListing') && isBuyListing($ad))) {
            continue;
        }
        if (function_exists('isAdPriceOpen') && isAdPriceOpen($ad)) {
            continue;
        }
        $price = function_exists('adPriceEur') ? adPriceEur($ad) : (float)($ad['price'] ?? 0);
        if ($price <= 0) {
            continue;
        }
        $storage = function_exists('phoneCatalogAdStorage') ? phoneCatalogAdStorage($ad) : trim((string)($ad['storage'] ?? ''));
        if ($storage === '') {
            $storage = 'nepoznata';
        }
        $condition = mb_strtolower(trim((string)($ad['condition_state'] ?? '')));
        $bucket = str_contains($condition, 'novo') && !str_contains($condition, 'kao') ? 'novo' : 'polovno';
        $groups[$storage][$bucket][] = $price;
    }
    $rows = [];
    foreach ($groups as $storage => $byCondition) {
        foreach ($byCondition as $condition => $prices) {
            sort($prices);
            $n = count($prices);
            $rows[] = [
                'storage' => (string)$storage,
                'condition' => (string)$condition,
                'count' => $n,
                'min' => $n >= 3 ? $prices[0] : null,
                'median' => $n >= 3 ? $prices[(int)floor(($n - 1) / 2)] : null,
                'max' => $n >= 3 ? $prices[$n - 1] : null,
            ];
        }
    }
    return $rows;
}

/** @return list<array{name:string,url:string,city:string,count:int}> */
function modelHubCityLinks(array $model, array $offers): array
{
    $counts = [];
    foreach ($offers as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $city = function_exists('normalizeCity') ? normalizeCity((string)($ad['location'] ?? ''))['city'] : trim((string)($ad['location'] ?? ''));
        if ($city === '') {
            continue;
        }
        $counts[$city] = ($counts[$city] ?? 0) + 1;
    }
    $links = [];
    foreach ($counts as $city => $count) {
        if ($count < 3 || !function_exists('citySlug')) {
            continue;
        }
        $links[] = [
            'name' => (string)$model['name'] . ' ' . $city,
            'city' => $city,
            'count' => $count,
            'url' => phoneCatalogUrl($model) . '/' . rawurlencode(citySlug($city)),
        ];
    }
    return $links;
}

/** @return list<string> */
function modelHubBuyingTips(string $brand): array
{
    $brand = mb_strtolower($brand);
    if (str_contains($brand, 'apple')) {
        return [
            'Proveri zdravlje baterije i da li je iCloud nalog odjavljen.',
            'Face ID i originalni delovi se proveravaju uživo, pre plaćanja.',
            'Uporedi IMEI na telefonu i u oglasu.',
        ];
    }
    if (str_contains($brand, 'samsung')) {
        return [
            'Pogledaj burn-in na ekranu i da li je Google nalog (FRP) skinut.',
            'Knox i servisna istorija nisu isto što i „garancija u oglasu”.',
            'Uporedi IMEI pre dogovora.',
        ];
    }
    return [
        'Uporedi IMEI, bateriju i da li je nalog odjavljen.',
        'Dogovor uživo pre plaćanja. Oglasnik ne drži novac.',
    ];
}

/** @return list<array{name:string,url:string}> */
function modelHubRelated(array $model): array
{
    $brand = (string)($model['brand_slug'] ?? '');
    $slug = (string)($model['slug'] ?? '');
    $related = [];
    foreach (phoneCatalogModels() as $other) {
        if ((string)$other['brand_slug'] !== $brand || (string)$other['slug'] === $slug) {
            continue;
        }
        if (!phoneCatalogModelIsVerified($other)) {
            continue;
        }
        $otherSlug = (string)$other['slug'];
        $close = str_contains($otherSlug, preg_replace('/-(pro|max|plus|mini|ultra).*$/', '', $slug) ?: $slug);
        if (!$close) {
            continue;
        }
        $related[] = ['name' => (string)$other['name'], 'url' => phoneCatalogUrl($other)];
        if (count($related) >= 4) {
            break;
        }
    }
    return $related;
}

function modelHubCache(string $key, callable $producer, int $ttl = 900): mixed
{
    $dir = sys_get_temp_dir() . '/kupitelefon-hub';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . sha1($key) . '.json';
    if (is_file($file) && (time() - (int)filemtime($file)) < $ttl) {
        $cached = json_decode((string)file_get_contents($file), true);
        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }
    }
    $value = $producer();
    file_put_contents($file, json_encode(['value' => $value], JSON_UNESCAPED_UNICODE));
    return $value;
}
