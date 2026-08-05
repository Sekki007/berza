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
    if ($type !== '') {
        return '/oglasi/' . rawurlencode(listingTypeSlug($type));
    }
    return '/';
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

    $type = listingTypeFromSlug(rawurldecode($parts[0]));
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

    foreach (['telefon', 'delovi', 'servis'] as $type) {
        $ads = getPublicAds(['types' => [$type], 'sort' => 'newest']);
        if (listingLandingIndexable(['type' => $type], $ads)) {
            $out[] = listingLandingPath(['type' => $type]);
        }
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
