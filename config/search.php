<?php

declare(strict_types=1);

/**
 * Tokenizuje upit: "iphone 13 beograd" → ['iphone','13','beograd']
 */
function searchTokens(string $query): array
{
    $query = mb_strtolower(trim($query));
    if ($query === '') {
        return [];
    }
    $parts = preg_split('/[\s,;.\/|+]+/u', $query) ?: [];
    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && mb_strlen($part) >= 1) {
            $tokens[$part] = true;
        }
    }
    return array_keys($tokens);
}

function adSearchHaystack(array $ad): string
{
    return mb_strtolower(implode(' ', [
        (string)($ad['title'] ?? ''),
        (string)($ad['description'] ?? ''),
        (string)($ad['brand'] ?? ''),
        (string)($ad['model'] ?? ''),
        (string)($ad['storage'] ?? ''),
        (string)($ad['location'] ?? ''),
        (string)($ad['shop_name'] ?? ''),
        (string)($ad['condition_state'] ?? ''),
        adTypeLabel(getAdType($ad)),
    ]));
}

function scoreAdAgainstTokens(array $ad, array $tokens): int
{
    if ($tokens === []) {
        return 0;
    }
    $haystack = adSearchHaystack($ad);
    $title = mb_strtolower((string)($ad['title'] ?? ''));
    $model = mb_strtolower((string)($ad['model'] ?? ''));
    $brand = mb_strtolower((string)($ad['brand'] ?? ''));
    $location = mb_strtolower((string)($ad['location'] ?? ''));
    $score = 0;
    foreach ($tokens as $token) {
        if ($token === '') {
            continue;
        }
        if (!str_contains($haystack, $token)) {
            return -1;
        }
        if (str_contains($title, $token)) {
            $score += 8;
        }
        if (str_contains($model, $token)) {
            $score += 6;
        }
        if (str_contains($brand, $token)) {
            $score += 4;
        }
        if (str_contains($location, $token)) {
            $score += 3;
        }
        $score += 1;
    }
    if (!empty($ad['is_promoted'])) {
        $score += 2;
    }
    return $score;
}

/**
 * Predlozi za autocomplete: modeli, brendovi, gradovi, aktivni oglasi.
 * @return list<array{type:string,label:string,sub?:string,url:string,price?:string}>
 */
function searchSuggestions(string $query, int $limit = 8): array
{
    $query = trim($query);
    $tokens = searchTokens($query);
    $settings = siteSettings();
    $out = [];
    $seen = [];

    $add = static function (array $item) use (&$out, &$seen, $limit): bool {
        if (count($out) >= $limit) {
            return false;
        }
        $key = ($item['type'] ?? '') . '|' . ($item['label'] ?? '') . '|' . ($item['url'] ?? '');
        if (isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
        $out[] = $item;
        return count($out) < $limit;
    };

    $qLower = mb_strtolower($query);

    foreach (($settings['brands'] ?? []) as $brand) {
        if ($qLower !== '' && !str_contains(mb_strtolower((string)$brand), $qLower)) {
            continue;
        }
        if (!$add([
            'type' => 'brand',
            'label' => (string)$brand,
            'sub' => 'Brend',
            'url' => '/index.php?' . http_build_query(['brand' => $brand]),
        ])) {
            return $out;
        }
    }

    foreach (($settings['cities'] ?? []) as $city) {
        if ($qLower !== '' && !str_contains(mb_strtolower((string)$city), $qLower)) {
            continue;
        }
        if (!$add([
            'type' => 'city',
            'label' => (string)$city,
            'sub' => 'Grad',
            'url' => '/index.php?' . http_build_query(['location' => $city]),
        ])) {
            return $out;
        }
    }

    $cfg = categoriesConfig();
    foreach ($cfg['groups'] ?? [] as $group) {
        foreach ($group['models'] ?? [] as $model) {
            if ($qLower !== '' && !str_contains(mb_strtolower((string)$model), $qLower)) {
                continue;
            }
            if (!$add([
                'type' => 'model',
                'label' => (string)$model,
                'sub' => 'Model',
                'url' => '/index.php?' . http_build_query(['q' => $model]),
            ])) {
                return $out;
            }
        }
    }

    $ads = getPublicAds(['q' => $query, 'sort' => 'newest']);
    foreach (array_slice($ads, 0, $limit) as $ad) {
        if (!$add([
            'type' => 'ad',
            'label' => (string)($ad['title'] ?? 'Oglas'),
            'sub' => formatPrice((float)($ad['price'] ?? 0)) . ' · ' . (string)($ad['location'] ?? ''),
            'url' => '/oglas.php?id=' . (int)($ad['id'] ?? 0),
            'price' => formatPrice((float)($ad['price'] ?? 0)),
        ])) {
            return $out;
        }
    }

    if ($query !== '' && count($out) < $limit) {
        $add([
            'type' => 'search',
            'label' => 'Pretraži: ' . $query,
            'sub' => 'Svi rezultati',
            'url' => '/index.php?' . http_build_query(['q' => $query]),
        ]);
    }

    return $out;
}
