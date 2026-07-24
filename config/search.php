<?php

declare(strict_types=1);

/**
 * Tokenizuje upit: "iphone 13 beograd" → ['iphone','13','beograd']
 * Napomena: ključevi moraju ostati stringovi (PHP pretvara "13" u int u array key).
 *
 * @return list<string>
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
        $part = trim((string)$part);
        if ($part === '') {
            continue;
        }
        $tokens[$part] = $part;
    }
    return array_values($tokens);
}

function adSearchHaystack(array $ad): string
{
    $acc = is_array($ad['accessories'] ?? null) ? implode(' ', $ad['accessories']) : '';
    $svc = is_array($ad['service_types'] ?? null) ? implode(' ', $ad['service_types']) : '';
    $brands = is_array($ad['supported_brands'] ?? null) ? implode(' ', $ad['supported_brands']) : '';

    return mb_strtolower(implode(' ', [
        (string)($ad['title'] ?? ''),
        (string)($ad['description'] ?? ''),
        (string)($ad['brand'] ?? ''),
        (string)($ad['model'] ?? ''),
        (string)($ad['storage'] ?? ''),
        (string)($ad['ram'] ?? ''),
        (string)($ad['color'] ?? ''),
        (string)($ad['location'] ?? ''),
        (string)($ad['shop_name'] ?? ''),
        (string)($ad['condition_state'] ?? ''),
        (string)($ad['equipment_type'] ?? ''),
        (string)($ad['compatible_models'] ?? ''),
        (string)($ad['originality'] ?? ''),
        $acc,
        $svc,
        $brands,
        listingTypeLabel($ad),
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
    $qJoined = implode(' ', array_map('strval', $tokens));

    foreach ($tokens as $token) {
        $token = (string)$token;
        if ($token === '') {
            continue;
        }
        if (!str_contains($haystack, $token)) {
            return -1;
        }
        if (str_contains($title, $token)) {
            $score += 10;
        }
        if (str_contains($model, $token)) {
            $score += 7;
        }
        if (str_contains($brand, $token)) {
            $score += 5;
        }
        if (str_contains($location, $token)) {
            $score += 3;
        }
        $score += 1;
    }

    // Bonus ako ceo upit liči na naslov / model
    if ($qJoined !== '' && str_contains($title, $qJoined)) {
        $score += 20;
    }
    if ($qJoined !== '' && str_contains($model, $qJoined)) {
        $score += 15;
    }
    if (!empty($ad['is_promoted'])) {
        $score += 2;
    }
    return $score;
}

/**
 * Autocomplete: prvo stvarni oglasi iz baze, zatim modeli/brendovi koji postoje u aktivnim oglasima.
 *
 * @return list<array{type:string,label:string,sub?:string,url:string,price?:string}>
 */
function searchSuggestions(string $query, int $limit = 8): array
{
    $query = trim($query);
    if ($query === '' || mb_strlen($query) < 1) {
        return [];
    }

    $tokens = searchTokens($query);
    $qLower = mb_strtolower($query);
    $out = [];
    $seen = [];

    $add = static function (array $item) use (&$out, &$seen, $limit): bool {
        if (count($out) >= $limit) {
            return false;
        }
        $key = ($item['type'] ?? '') . '|' . mb_strtolower((string)($item['label'] ?? '')) . '|' . ($item['url'] ?? '');
        if (isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
        $out[] = $item;
        return count($out) < $limit;
    };

    // 1) Stvarni aktivni oglasi (glavni autocomplete)
    $ads = getPublicAds(['q' => $query, 'sort' => 'relevance']);
    $adLimit = min($limit, 6);
    $adCount = 0;
    foreach ($ads as $ad) {
        if ($adCount >= $adLimit) {
            break;
        }
        $title = trim((string)($ad['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $price = formatAdPrice($ad);
        $loc = trim((string)($ad['location'] ?? ''));
        $sub = $price . ($loc !== '' ? ' · ' . $loc : '');
        if (!$add([
            'type' => 'ad',
            'label' => $title,
            'sub' => $sub,
            'url' => adUrl($ad),
            'price' => $price,
        ])) {
            return $out;
        }
        $adCount++;
    }

    // 2) Jedinstveni modeli / brendovi koji stvarno postoje u rezultatima (ne ceo katalog)
    $modelsSeen = [];
    $brandsSeen = [];
    foreach ($ads as $ad) {
        $model = trim((string)($ad['model'] ?? ''));
        $brand = trim((string)($ad['brand'] ?? ''));
        if ($model !== '' && !isset($modelsSeen[mb_strtolower($model)])) {
            if ($qLower === '' || str_contains(mb_strtolower($model), $qLower) || tokensMatchText($tokens, $model)) {
                $modelsSeen[mb_strtolower($model)] = $model;
            }
        }
        if ($brand !== '' && !isset($brandsSeen[mb_strtolower($brand)])) {
            if ($qLower === '' || str_contains(mb_strtolower($brand), $qLower) || tokensMatchText($tokens, $brand)) {
                $brandsSeen[mb_strtolower($brand)] = $brand;
            }
        }
    }

    foreach ($modelsSeen as $model) {
        if (!$add([
            'type' => 'model',
            'label' => $model,
            'sub' => 'Model u oglasima',
            'url' => '/index.php?' . http_build_query(['q' => $model]),
        ])) {
            return $out;
        }
    }

    foreach ($brandsSeen as $brand) {
        if (!$add([
            'type' => 'brand',
            'label' => $brand,
            'sub' => 'Brend',
            'url' => '/index.php?' . http_build_query(['brand' => $brand]),
        ])) {
            return $out;
        }
    }

    // 3) Uvek ponudi "Pretraži: …" kao poslednju opciju ako ima mesta
    if (count($out) < $limit) {
        $add([
            'type' => 'search',
            'label' => 'Pretraži: ' . $query,
            'sub' => count($ads) . ' rezultat' . (count($ads) === 1 ? '' : 'a'),
            'url' => '/index.php?' . http_build_query(['q' => $query]),
        ]);
    }

    return $out;
}

/**
 * @param list<string> $tokens
 */
function tokensMatchText(array $tokens, string $text): bool
{
    if ($tokens === []) {
        return false;
    }
    $hay = mb_strtolower($text);
    foreach ($tokens as $token) {
        $token = (string)$token;
        if ($token === '' || !str_contains($hay, $token)) {
            return false;
        }
    }
    return true;
}
