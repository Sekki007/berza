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

/**
 * Namera iz upita: parts | service | phone | general
 *
 * @param list<string> $tokens
 */
function searchQueryIntent(array $tokens): string
{
    $joined = ' ' . implode(' ', array_map('strval', $tokens)) . ' ';

    if (preg_match('/\b(servis|popravka|deblok|dekod|flash)\b/u', $joined)) {
        return 'service';
    }

    // Delovi / oprema (lcd 15 pro, baterija s23, maska iphone…)
    if (preg_match(
        '/\b(lcd|oled|ekran|displej|screen|baterija|battery|maska|futrola|case|punjač|punjac|charger|kabl|cable|flex|staklo|glass|kućište|kuciste|maticn|touch|service\s*pack|org\s*sh|sh\s*delovi|deo|delovi|parts?)\b/u',
        $joined
    )) {
        return 'parts';
    }

    if (preg_match('/\b(iphone|samsung|galaxy|xiaomi|redmi|honor|huawei|pixel|telefon|tablet|watch|sat)\b/u', $joined)) {
        return 'phone';
    }

    return 'general';
}

function adTitleHasPartsKeyword(string $title): bool
{
    $title = mb_strtolower($title);
    if ($title === '') {
        return false;
    }
    return (bool)preg_match(
        '/\b(lcd|oled|ekran|displej|screen|baterija|battery|maska|futrola|case|punjac|punjač|charger|kabl|cable|flex|staklo|glass|kućište|kuciste|touch|service\s*pack|org\s*sh|za\s+delov|deo|delovi)\b/u',
        $title
    );
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
        getAdType($ad) === 'telefon' ? deviceTypeLabel(getAdDeviceType($ad)) : '',
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
    $equipment = mb_strtolower((string)($ad['equipment_type'] ?? ''));
    $score = 0;
    $qJoined = implode(' ', array_map('strval', $tokens));
    $intent = searchQueryIntent($tokens);
    $adType = getAdType($ad);

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
        if ($equipment !== '' && str_contains($equipment, $token)) {
            $score += 8;
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

    // Namera: "lcd 15 pro" → delovi gore, telefoni (gde je lcd samo u opisu) van rezultata
    if ($intent === 'parts') {
        $partsInTitle = adTitleHasPartsKeyword($title);
        if ($adType === 'delovi') {
            $score += 45;
            if ($partsInTitle) {
                $score += 25;
            }
        } elseif ($adType === 'servis') {
            $score += 10;
        } elseif ($adType === 'telefon') {
            // Telefon „za delove“ ili naslov koji je stvarno deo — ostavi; inače isključi
            if ($partsInTitle || preg_match('/\bza\s+delov/u', $haystack)) {
                $score += 15;
            } else {
                return -1;
            }
        }
    } elseif ($intent === 'service') {
        if ($adType === 'servis') {
            $score += 40;
        } elseif ($adType === 'telefon') {
            $score -= 15;
        }
    } elseif ($intent === 'phone') {
        if ($adType === 'telefon') {
            $score += 20;
        } elseif ($adType === 'delovi' && !adTitleHasPartsKeyword($title)) {
            // Oglas delova koji samo pominje model u kompatibilnosti — blago niže
            $score -= 8;
        }
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
            'url' => '/index.php?' . http_build_query(['model' => $model]),
        ])) {
            return $out;
        }
    }

    foreach ($brandsSeen as $brand) {
        if (!$add([
            'type' => 'brand',
            'label' => $brand,
            'sub' => 'Brend',
            'url' => listingLandingPath(['brand' => $brand]),
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
