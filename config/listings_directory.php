<?php

declare(strict_types=1);

function listingFacetSlug(string $value): string
{
    return normalizeShopSlug($value);
}

function listingTypeFromSlug(string $slug): string
{
    return match ($slug) {
        'telefoni' => 'telefon',
        'delovi' => 'delovi',
        'servis' => 'servis',
        default => '',
    };
}

function listingTypeSlug(string $type): string
{
    return match ($type) {
        'telefon' => 'telefoni',
        'delovi' => 'delovi',
        'servis' => 'servis',
        default => '',
    };
}

function listingFindCityBySlug(string $slug): ?string
{
    foreach ((array)(categoriesConfig()['cities'] ?? []) as $city) {
        $city = trim((string)$city);
        if ($city !== '' && citySlug($city) === $slug) {
            return $city;
        }
    }
    return null;
}

function listingFindBrandBySlug(string $slug): ?string
{
    foreach ((array)(categoriesConfig()['brands'] ?? []) as $brand) {
        $brand = trim((string)$brand);
        if ($brand !== '' && listingFacetSlug($brand) === $slug) {
            return $brand;
        }
    }
    return null;
}

function listingLandingPath(array $filters): string
{
    $type = trim((string)($filters['type'] ?? ''));
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $city = trim((string)($filters['location'] ?? ''));
    $equipmentGroup = trim((string)($filters['equipment_group'] ?? ''));

    if ($brand !== '' && $city !== '') {
        return '/oglasi/' . rawurlencode(listingFacetSlug($brand)) . '/grad/' . rawurlencode(citySlug($city));
    }
    if ($brand !== '' && $model !== '') {
        return '/oglasi/' . rawurlencode(listingFacetSlug($brand)) . '/' . rawurlencode(listingFacetSlug($model));
    }
    if ($city !== '') {
        return '/oglasi/grad/' . rawurlencode(citySlug($city));
    }
    if ($brand !== '') {
        return '/oglasi/' . rawurlencode(listingFacetSlug($brand));
    }
    if ($type === 'delovi' && $equipmentGroup === 'oprema') {
        return '/oglasi/oprema';
    }
    if ($type === 'delovi' && ($equipmentGroup === 'parts' || $equipmentGroup === '')) {
        return '/oglasi/delovi';
    }
    if ($type !== '') {
        return '/oglasi/' . rawurlencode(listingTypeSlug($type));
    }
    return '/';
}

/**
 * 301 na čist URL kada su type/equipment_group već u path-u.
 */
function listingRequestCleanRedirect(string $requestUri): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return null;
    }

    $path = rtrim(parse_url($requestUri, PHP_URL_PATH) ?: '/', '/') ?: '/';
    $queryStr = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
    parse_str($queryStr, $query);

    $equipmentGroup = trim((string)($query['equipment_group'] ?? ''));
    $type = trim((string)($query['type'] ?? ''));

    $keep = array_filter($query, static function ($v, $k) {
        if (in_array($k, ['equipment_group', 'type'], true)) {
            return false;
        }
        return $v !== '' && $v !== null;
    }, ARRAY_FILTER_USE_BOTH);

    $build = static function (string $targetPath) use ($keep): string {
        $qs = $keep !== [] ? ('?' . http_build_query($keep)) : '';
        return $targetPath . $qs;
    };

    if ($path === '/oglasi/delovi') {
        if ($equipmentGroup === 'oprema') {
            $target = $build('/oglasi/oprema');
        } elseif ($equipmentGroup === 'parts' || $type === 'delovi') {
            $target = $build('/oglasi/delovi');
        } else {
            return null;
        }
    } elseif ($path === '/oglasi/oprema' && ($equipmentGroup === 'oprema' || $type === 'delovi')) {
        $target = $build('/oglasi/oprema');
    } else {
        return null;
    }

    $current = $path . ($queryStr !== '' ? '?' . $queryStr : '');
    return $target !== $current ? $target : null;
}

function resolveListingLandingFromPath(string $path): ?array
{
    $path = rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }
    if ($path === '/oglasi') {
        return ['filters' => [], 'path' => '/oglasi'];
    }

    if (!str_starts_with($path, '/oglasi/')) {
        return null;
    }

    $rest = trim(substr($path, 8), '/');
    if ($rest === '') {
        return ['filters' => [], 'path' => '/oglasi'];
    }
    $parts = array_values(array_filter(explode('/', $rest), static fn($p) => $p !== ''));
    if ($parts === []) {
        return ['filters' => [], 'path' => '/oglasi'];
    }

    if ($parts[0] === 'grad' && isset($parts[1])) {
        $city = listingFindCityBySlug(rawurldecode($parts[1]));
        if ($city === null) {
            return ['invalid' => true];
        }
        return ['filters' => ['location' => $city], 'path' => '/oglasi/grad/' . rawurlencode(citySlug($city))];
    }

    $first = rawurldecode($parts[0]);
    if ($first === 'oprema' && count($parts) === 1) {
        return [
            'filters' => ['type' => 'delovi', 'equipment_group' => 'oprema'],
            'path' => '/oglasi/oprema',
        ];
    }

    $type = listingTypeFromSlug($first);
    if ($type === 'delovi' && count($parts) === 1) {
        return [
            'filters' => ['type' => 'delovi', 'equipment_group' => 'parts'],
            'path' => '/oglasi/delovi',
        ];
    }
    if ($type !== '' && count($parts) === 1) {
        return ['filters' => ['type' => $type], 'path' => '/oglasi/' . rawurlencode(listingTypeSlug($type))];
    }

    $brand = listingFindBrandBySlug(rawurldecode($parts[0]));
    if ($brand === null) {
        return ['invalid' => true];
    }

    if (count($parts) === 1) {
        return ['filters' => ['brand' => $brand], 'path' => '/oglasi/' . rawurlencode(listingFacetSlug($brand))];
    }
    if (count($parts) === 3 && rawurldecode($parts[1]) === 'grad') {
        $city = listingFindCityBySlug(rawurldecode($parts[2]));
        if ($city === null) {
            return ['invalid' => true];
        }
        return [
            'filters' => ['brand' => $brand, 'location' => $city],
            'path' => '/oglasi/' . rawurlencode(listingFacetSlug($brand)) . '/grad/' . rawurlencode(citySlug($city)),
        ];
    }
    if (count($parts) === 2) {
        $model = trim(rawurldecode($parts[1]));
        if ($model === '') {
            return ['invalid' => true];
        }
        return [
            'filters' => ['brand' => $brand, 'model' => $model],
            'path' => '/oglasi/' . rawurlencode(listingFacetSlug($brand)) . '/' . rawurlencode(listingFacetSlug($model)),
        ];
    }

    return ['invalid' => true];
}

function listingLandingIndexable(array $filters, array $ads): bool
{
    $count = count($ads);
    if ($count < 3) {
        return false;
    }
    $sellerIds = [];
    foreach ($ads as $ad) {
        $uid = (int)($ad['created_by'] ?? 0);
        if ($uid > 0) {
            $sellerIds[$uid] = true;
        }
    }
    $uniqueSellers = count($sellerIds);

    if (!empty($filters['brand']) && !empty($filters['model'])) {
        return $count >= 5 && $uniqueSellers >= 2;
    }
    if (!empty($filters['brand']) && !empty($filters['location'])) {
        return $count >= 4 && $uniqueSellers >= 2;
    }
    return $count >= 3;
}

function listingLandingCandidatesForSitemap(): array
{
    $out = [];

    foreach (['telefon', 'servis'] as $type) {
        $ads = getPublicAds(['types' => [$type], 'sort' => 'newest']);
        if (listingLandingIndexable(['type' => $type], $ads)) {
            $out[] = listingLandingPath(['type' => $type]);
        }
    }
    $partsAds = getPublicAds(['types' => ['delovi'], 'equipment_group' => 'parts', 'sort' => 'newest']);
    if (listingLandingIndexable(['type' => 'delovi', 'equipment_group' => 'parts'], $partsAds)) {
        $out[] = listingLandingPath(['type' => 'delovi', 'equipment_group' => 'parts']);
    }
    $opremaAds = getPublicAds(['types' => ['delovi'], 'equipment_group' => 'oprema', 'sort' => 'newest']);
    if (listingLandingIndexable(['type' => 'delovi', 'equipment_group' => 'oprema'], $opremaAds)) {
        $out[] = listingLandingPath(['type' => 'delovi', 'equipment_group' => 'oprema']);
    }

    $brands = (array)(categoriesConfig()['brands'] ?? []);
    foreach ($brands as $brand) {
        $brand = trim((string)$brand);
        if ($brand === '') {
            continue;
        }
        $brandAds = getPublicAds(['brand' => $brand, 'sort' => 'newest']);
        if (listingLandingIndexable(['brand' => $brand], $brandAds)) {
            $out[] = listingLandingPath(['brand' => $brand]);
        }
    }

    return array_values(array_unique($out));
}
