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

/** Grad i brend+grad ulaze u sitemap tek od ovog broja aktivnih oglasa. */
const LISTING_SITEMAP_MIN_ADS = 3;

function listingLandingIndexable(array $filters, array $ads): bool
{
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));
    $hasNarrowType = trim((string)($filters['type'] ?? '')) !== ''
        || trim((string)($filters['equipment_group'] ?? '')) !== '';

    $count = count($ads);
    // Prazan grad ili brend (0 oglasa) ostaje stranica za ljude, ali noindex.
    if ($count < 1) {
        return false;
    }

    // Bazni grad i brend sa bar jednim oglasom ostaju indeksabilni.
    if ($location !== '' && $brand === '' && $model === '' && !$hasNarrowType) {
        return true;
    }
    if ($brand !== '' && $model === '' && $location === '' && !$hasNarrowType) {
        return true;
    }

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

/**
 * @param list<array>|null $ads već učitani javni oglasi (jedan prolaz, bez ponovnog čitanja)
 * @return list<string>
 */
function listingLandingCandidatesForSitemap(?array $ads = null): array
{
    if ($ads === null) {
        $ads = getPublicAds(['sort' => 'newest']);
    }

    $groups = [
        'telefon' => [],
        'servis' => [],
        'parts' => [],
        'oprema' => [],
    ];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $type = getAdType($ad);
        if ($type === 'telefon' || $type === 'servis') {
            $groups[$type][] = $ad;
            continue;
        }
        if ($type === 'delovi') {
            $group = function_exists('adEquipmentGroup') ? adEquipmentGroup($ad) : '';
            if ($group === 'oprema') {
                $groups['oprema'][] = $ad;
            } else {
                $groups['parts'][] = $ad;
            }
        }
    }

    $out = [];
    if (listingLandingIndexable(['type' => 'telefon'], $groups['telefon'])) {
        $out[] = listingLandingPath(['type' => 'telefon']);
    }
    if (listingLandingIndexable(['type' => 'servis'], $groups['servis'])) {
        $out[] = listingLandingPath(['type' => 'servis']);
    }
    if (listingLandingIndexable(['type' => 'delovi', 'equipment_group' => 'parts'], $groups['parts'])) {
        $out[] = listingLandingPath(['type' => 'delovi', 'equipment_group' => 'parts']);
    }
    if (listingLandingIndexable(['type' => 'delovi', 'equipment_group' => 'oprema'], $groups['oprema'])) {
        $out[] = listingLandingPath(['type' => 'delovi', 'equipment_group' => 'oprema']);
    }

    $byBrand = [];
    $byCity = [];
    $byBrandCity = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $brand = trim((string)($ad['brand'] ?? ''));
        $city = trim((string)($ad['location'] ?? ''));
        if ($brand !== '' && $brand !== 'Ostalo') {
            $byBrand[$brand][] = $ad;
        }
        if ($city !== '') {
            $byCity[$city][] = $ad;
        }
        if ($brand !== '' && $brand !== 'Ostalo' && $city !== '') {
            $byBrandCity[$brand . "\0" . $city][] = $ad;
        }
    }

    foreach ((array)(categoriesConfig()['brands'] ?? []) as $brand) {
        $brand = trim((string)$brand);
        if ($brand === '' || $brand === 'Ostalo') {
            continue;
        }
        $brandAds = $byBrand[$brand] ?? [];
        if (listingLandingIndexable(['brand' => $brand], $brandAds)) {
            $out[] = listingLandingPath(['brand' => $brand]);
        }
    }
    foreach ((array)(categoriesConfig()['cities'] ?? []) as $city) {
        $city = trim((string)$city);
        if ($city === '') {
            continue;
        }
        $cityAds = $byCity[$city] ?? [];
        if (count($cityAds) >= LISTING_SITEMAP_MIN_ADS && listingLandingIndexable(['location' => $city], $cityAds)) {
            $out[] = listingLandingPath(['location' => $city]);
        }
    }
    foreach ((array)(categoriesConfig()['brands'] ?? []) as $brand) {
        $brand = trim((string)$brand);
        if ($brand === '' || $brand === 'Ostalo') {
            continue;
        }
        foreach ((array)(categoriesConfig()['cities'] ?? []) as $city) {
            $city = trim((string)$city);
            if ($city === '') {
                continue;
            }
            $combo = $byBrandCity[$brand . "\0" . $city] ?? [];
            if (count($combo) < LISTING_SITEMAP_MIN_ADS) {
                continue;
            }
            if (!listingLandingIndexable(['brand' => $brand, 'location' => $city], $combo)) {
                continue;
            }
            $out[] = listingLandingPath(['brand' => $brand, 'location' => $city]);
        }
    }

    return array_values(array_unique($out));
}

/**
 * Čist URL za filtere sa starog /index.php (i sačuvanih pretraga).
 * Poznati tip, brend, model i grad idu na landing. Ostali parametri ostaju u query-ju.
 *
 * @param array<string,mixed> $query
 */
function listingLegacyIndexTarget(array $query): string
{
    $type = trim((string)($query['type'] ?? ''));
    $brandRaw = trim((string)($query['brand'] ?? ''));
    $model = trim((string)($query['model'] ?? ''));
    $locationRaw = trim((string)($query['location'] ?? ''));
    $equipment = trim((string)($query['equipment_group'] ?? ''));

    if (array_key_exists('browse_cat', $query) && function_exists('browseCategoryToFilters')) {
        $mapped = browseCategoryToFilters(trim((string)$query['browse_cat']));
        if ($type === '') {
            $type = (string)($mapped['type'] ?? '');
        }
        if ($equipment === '') {
            $equipment = (string)($mapped['equipment_group'] ?? '');
        }
    }

    $filters = [];
    if (in_array($type, ['telefon', 'delovi', 'servis'], true)) {
        $filters['type'] = $type;
    }
    $brand = '';
    if ($brandRaw !== '') {
        $found = listingFindBrandBySlug(listingFacetSlug($brandRaw));
        if ($found !== null) {
            $brand = $found;
            $filters['brand'] = $found;
        }
    }
    $city = '';
    if ($locationRaw !== '') {
        $foundCity = listingFindCityBySlug(citySlug($locationRaw));
        if ($foundCity !== null) {
            $city = $foundCity;
            $filters['location'] = $foundCity;
        }
    }
    if ($model !== '' && $brand !== '') {
        $filters['model'] = $model;
    }
    if ($equipment !== '' && ($filters['type'] ?? '') === 'delovi') {
        $filters['equipment_group'] = $equipment;
    }

    $rest = [];
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || is_array($value)) {
            continue;
        }
        $key = (string)$key;
        if ($key === 'type' && isset($filters['type'])) {
            continue;
        }
        if ($key === 'equipment_group' && isset($filters['equipment_group'])) {
            continue;
        }
        if ($key === 'browse_cat' && (isset($filters['type']) || isset($filters['equipment_group']))) {
            continue;
        }
        if ($key === 'brand' && isset($filters['brand'])) {
            continue;
        }
        if ($key === 'location' && isset($filters['location'])) {
            continue;
        }
        if ($key === 'model' && isset($filters['model'])) {
            continue;
        }
        if ($key === 'sort' && (string)$value === 'newest') {
            continue;
        }
        if ($key === 'page' && (int)$value <= 1) {
            continue;
        }
        $rest[$key] = (string)$value;
    }

    $unmapped = ($brandRaw !== '' && $brand === '')
        || ($locationRaw !== '' && $city === '')
        || ($model !== '' && !isset($filters['model']));

    if ($filters === [] && $rest === [] && !$unmapped) {
        return '/';
    }
    if ($filters === [] && array_keys($rest) === ['page']) {
        return '/?page=' . max(2, (int)$rest['page']);
    }

    $path = $filters === [] ? '/oglasi' : listingLandingPath($filters);
    if ($filters === [] && $unmapped) {
        $path = '/oglasi';
    }
    if ($rest === []) {
        return $path;
    }
    return $path . '?' . http_build_query($rest);
}

/** Paginacija koja sme da bude indeksabilna sa self-canonical URL-om. */
function listingPageIsSelfCanonical(string $path): bool
{
    $path = rtrim($path, '/') ?: '/';
    if ($path === '/' || $path === '/oglasi/telefoni') {
        return true;
    }
    if (preg_match('#^/oglasi/grad/([^/]+)$#', $path) === 1) {
        return true;
    }
    if (preg_match('#^/oglasi/([^/]+)$#', $path, $m) === 1) {
        return listingFindBrandBySlug(rawurldecode($m[1])) !== null;
    }
    return false;
}

/**
 * Robots i canonical za listu oglasa.
 *
 * @param array{
 *   landing:?array,
 *   filters:array<string,mixed>,
 *   ads:list<array>,
 *   page:int,
 *   sort:string,
 *   has_extra:bool
 * } $opts
 * @return array{robots:string,canonical_path:string,title_suffix:string,indexable:bool}
 */
function listingSeoDecision(array $opts): array
{
    $landing = $opts['landing'] ?? null;
    $filters = (array)($opts['filters'] ?? []);
    $ads = (array)($opts['ads'] ?? []);
    $page = max(1, (int)($opts['page'] ?? 1));
    $sort = (string)($opts['sort'] ?? 'newest');
    $hasExtra = (bool)($opts['has_extra'] ?? false) || $sort !== 'newest';
    $isHome = !is_array($landing);

    $path = '/';
    if (is_array($landing) && !empty($landing['path'])) {
        $path = (string)$landing['path'];
    }

    $bare = trim((string)($filters['type'] ?? '')) === ''
        && trim((string)($filters['brand'] ?? '')) === ''
        && trim((string)($filters['model'] ?? '')) === ''
        && trim((string)($filters['location'] ?? '')) === ''
        && trim((string)($filters['equipment_group'] ?? '')) === '';
    if ($isHome && $bare) {
        $facetPath = '/';
    } elseif (is_array($landing) && !empty($landing['path']) && !$hasExtra) {
        $facetPath = (string)$landing['path'];
    } else {
        $facetPath = listingLandingPath($filters);
        if ($facetPath === '/' && !$bare) {
            $facetPath = '/oglasi';
        }
    }

    $indexable = true;
    if (is_array($landing) && empty($landing['invalid'])) {
        $indexable = listingLandingIndexable((array)($landing['filters'] ?? []), $ads);
    }

    $robots = '';
    $canonical = $facetPath;
    $suffix = '';

    if (is_array($landing) && !$indexable) {
        $parent = (array)($landing['filters'] ?? []);
        unset($parent['model'], $parent['location']);
        if ($parent === [] && !empty($landing['filters']['location'])) {
            $parent = ['type' => 'telefon'];
        }
        if ($parent === [] && !empty($landing['filters']['brand'])) {
            $parent = ['brand' => (string)$landing['filters']['brand']];
        }
        $canonical = listingLandingPath($parent === [] ? ['type' => 'telefon'] : $parent);
        $robots = 'noindex,follow';
    } elseif ($hasExtra) {
        $robots = 'noindex,follow';
        $canonical = $facetPath === '/' && !$isHome ? (string)$path : $facetPath;
        if (!$isHome && $canonical === '/') {
            $canonical = (string)$path;
        }
    } elseif ($page > 1 && listingPageIsSelfCanonical($isHome ? '/' : $path) && ($isHome || $indexable)) {
        $canonical = ($isHome ? '/' : $path) . '?page=' . $page;
        $suffix = ' — strana ' . $page;
    } elseif ($page > 1) {
        $robots = 'noindex,follow';
        $canonical = $isHome ? '/' : $path;
    }

    return [
        'robots' => $robots,
        'canonical_path' => $canonical,
        'title_suffix' => $suffix,
        'indexable' => $indexable,
    ];
}
