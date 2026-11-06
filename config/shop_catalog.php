<?php

declare(strict_types=1);

function shopCategoriesMax(): int
{
    return 20;
}

function canManageShopCategories(?array $user): bool
{
    return isVerifiedSeller($user);
}

/**
 * @return list<array{id:string,name:string,sort:int}>
 */
function getShopCategories(array $user): array
{
    $raw = $user['shop_categories'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'name' => mb_substr($name, 0, 40),
            'sort' => (int)($row['sort'] ?? 0),
        ];
    }
    usort($out, static fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['name'], $b['name']));
    return array_values($out);
}

function findShopCategory(array $user, string $categoryId): ?array
{
    $categoryId = trim($categoryId);
    if ($categoryId === '') {
        return null;
    }
    foreach (getShopCategories($user) as $cat) {
        if ($cat['id'] === $categoryId) {
            return $cat;
        }
    }
    return null;
}

function normalizeShopCategoryName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    return mb_substr($name, 0, 40);
}

function generateShopCategoryId(string $name, array $existingIds): string
{
    $base = mb_strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'cat';
    }
    $base = mb_substr($base, 0, 28);
    $id = $base;
    $i = 2;
    while (in_array($id, $existingIds, true)) {
        $id = $base . '-' . $i;
        $i++;
    }
    return $id;
}

function saveShopCategories(int $userId, array $categories): bool
{
    $clean = [];
    $seen = [];
    $sort = 0;
    foreach ($categories as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string)($row['id'] ?? ''));
        $name = normalizeShopCategoryName((string)($row['name'] ?? ''));
        if ($id === '' || $name === '' || isset($seen[$id])) {
            continue;
        }
        if (mb_strlen($name) < 2) {
            continue;
        }
        $seen[$id] = true;
        $clean[] = [
            'id' => mb_substr($id, 0, 40),
            'name' => $name,
            'sort' => $sort++,
        ];
        if (count($clean) >= shopCategoriesMax()) {
            break;
        }
    }
    return patchUser($userId, ['shop_categories' => $clean]);
}

function addShopCategory(int $userId, string $name): array
{
    $user = findUserById($userId);
    if (!$user || !canManageShopCategories($user)) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $name = normalizeShopCategoryName($name);
    if (mb_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'name'];
    }
    $cats = getShopCategories($user);
    if (count($cats) >= shopCategoriesMax()) {
        return ['ok' => false, 'error' => 'limit'];
    }
    foreach ($cats as $c) {
        if (mb_strtolower($c['name']) === mb_strtolower($name)) {
            return ['ok' => false, 'error' => 'duplicate'];
        }
    }
    $ids = array_map(static fn($c) => $c['id'], $cats);
    $cats[] = [
        'id' => generateShopCategoryId($name, $ids),
        'name' => $name,
        'sort' => count($cats),
    ];
    if (!saveShopCategories($userId, $cats)) {
        return ['ok' => false, 'error' => 'save'];
    }
    return ['ok' => true];
}

function renameShopCategory(int $userId, string $categoryId, string $name): array
{
    $user = findUserById($userId);
    if (!$user || !canManageShopCategories($user)) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $name = normalizeShopCategoryName($name);
    if (mb_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'name'];
    }
    $cats = getShopCategories($user);
    $found = false;
    foreach ($cats as &$c) {
        if ($c['id'] === $categoryId) {
            $c['name'] = $name;
            $found = true;
        } elseif (mb_strtolower($c['name']) === mb_strtolower($name)) {
            return ['ok' => false, 'error' => 'duplicate'];
        }
    }
    unset($c);
    if (!$found) {
        return ['ok' => false, 'error' => 'missing'];
    }
    if (!saveShopCategories($userId, $cats)) {
        return ['ok' => false, 'error' => 'save'];
    }
    return ['ok' => true];
}

function deleteShopCategory(int $userId, string $categoryId): array
{
    $user = findUserById($userId);
    if (!$user || !canManageShopCategories($user)) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $cats = getShopCategories($user);
    $next = array_values(array_filter($cats, static fn($c) => $c['id'] !== $categoryId));
    if (count($next) === count($cats)) {
        return ['ok' => false, 'error' => 'missing'];
    }
    if (!saveShopCategories($userId, $next)) {
        return ['ok' => false, 'error' => 'save'];
    }
    clearShopCategoryFromUserAds($userId, $categoryId);
    return ['ok' => true];
}

function moveShopCategory(int $userId, string $categoryId, int $direction): array
{
    $user = findUserById($userId);
    if (!$user || !canManageShopCategories($user)) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $cats = getShopCategories($user);
    $index = -1;
    foreach ($cats as $i => $c) {
        if ($c['id'] === $categoryId) {
            $index = $i;
            break;
        }
    }
    if ($index < 0) {
        return ['ok' => false, 'error' => 'missing'];
    }
    $swap = $index + ($direction < 0 ? -1 : 1);
    if ($swap < 0 || $swap >= count($cats)) {
        return ['ok' => true];
    }
    $tmp = $cats[$index];
    $cats[$index] = $cats[$swap];
    $cats[$swap] = $tmp;
    if (!saveShopCategories($userId, $cats)) {
        return ['ok' => false, 'error' => 'save'];
    }
    return ['ok' => true];
}

function clearShopCategoryFromUserAds(int $userId, string $categoryId): void
{
    $categoryId = trim($categoryId);
    if ($userId <= 0 || $categoryId === '') {
        return;
    }
    $ads = readJsonFile('ads.json');
    $changed = false;
    foreach ($ads as &$ad) {
        if ((int)($ad['created_by'] ?? 0) !== $userId) {
            continue;
        }
        if (trim((string)($ad['shop_category_id'] ?? '')) !== $categoryId) {
            continue;
        }
        $ad['shop_category_id'] = '';
        $ad['updated_at'] = date('Y-m-d H:i:s');
        $changed = true;
    }
    unset($ad);
    if ($changed) {
        writeJsonFile('ads.json', $ads);
    }
}

function normalizeAdShopCategoryId(array $user, string $categoryId): string
{
    $categoryId = trim($categoryId);
    if ($categoryId === '' || !canManageShopCategories($user)) {
        return '';
    }
    return findShopCategory($user, $categoryId) ? $categoryId : '';
}

/**
 * Tačke preloma kataloga izloga. 1366px je 4 kolone, uži laptop 3, mobilni 2.
 * BH filter postoji od 768px naviše.
 *
 * @return array{
 *   marker:string,
 *   columns:list<array{min_width:int,columns:int}>,
 *   battery_filter_min_width:int,
 *   thumb_aspect:string
 * }
 */
function shopCatalogLayoutSpec(): array
{
    return [
        'marker' => 'columns=2@0,3@900,4@1280 bh-hidden-below=768 thumb-aspect=1/0.82',
        'columns' => [
            ['min_width' => 0, 'columns' => 2],
            ['min_width' => 900, 'columns' => 3],
            ['min_width' => 1280, 'columns' => 4],
        ],
        'battery_filter_min_width' => 768,
        'thumb_aspect' => '1 / 0.82',
    ];
}

function shopCatalogGridColumns(int $viewportWidth): int
{
    $columns = 2;
    foreach (shopCatalogLayoutSpec()['columns'] as $rule) {
        if ($viewportWidth >= (int)$rule['min_width']) {
            $columns = (int)$rule['columns'];
        }
    }
    return $columns;
}

function shopCatalogBatteryFilterVisible(int $viewportWidth): bool
{
    return $viewportWidth >= (int)shopCatalogLayoutSpec()['battery_filter_min_width'];
}

/**
 * Telefon u tabu „Telefoni“ je samo telefon.
 * Tableti, satovi, AirPods/slušalice i oprema ostaju van tog taba.
 */
function shopAdIsPhoneListing(array $ad): bool
{
    if (getAdType($ad) !== 'telefon') {
        return false;
    }
    return !shopAdIsNonPhoneDevice($ad);
}

function shopAdIsNonPhoneDevice(array $ad): bool
{
    $device = strtolower(trim((string)($ad['device_type'] ?? '')));
    if (in_array($device, ['tablet', 'watch', 'earbuds', 'other'], true)) {
        return true;
    }

    $equipment = trim((string)($ad['equipment_type'] ?? ''));
    if ($equipment !== '' && function_exists('equipmentOpremaTypes') && function_exists('equipmentPartsTypes')) {
        $knownEquipment = array_merge(equipmentOpremaTypes(), equipmentPartsTypes());
        if (in_array($equipment, $knownEquipment, true)) {
            return true;
        }
    }

    $title = mb_strtolower(trim((string)($ad['title'] ?? '') . ' ' . (string)($ad['model'] ?? '')));
    if ($title === '') {
        return false;
    }
    if (preg_match('/\b(airpods|airpod|earbuds)\b/u', $title) === 1) {
        return true;
    }
    if (preg_match('/\b(ipad|galaxy\s+tab|tab\s+[as]\d+|tablet)\b/u', $title) === 1) {
        return true;
    }
    if (preg_match('/\b(apple\s+watch|galaxy\s+watch|huawei\s+watch|pametni\s+sat|smart\s*watch)\b/u', $title) === 1) {
        return true;
    }

    $accessory = preg_match('/\b(slušalice|slusalice|maska|futrola|punjač|punjac|kabl|kabel|powerbank|zaštitno\s+staklo|zastitno\s+staklo)\b/u', $title) === 1;
    if (!$accessory) {
        return false;
    }

    $phoneWithStorage = preg_match('/\b(iphone|galaxy\s+[as]\d+|pixel\s+\d+|redmi|xiaomi)\b/u', $title) === 1
        && preg_match('/\b\d{2,4}\s*gb\b/u', $title) === 1;

    return !$phoneWithStorage;
}

/** @return 'telefon'|'delovi'|'servis'|'other' */
function shopAdCatalogBucket(array $ad): string
{
    $type = getAdType($ad);
    if ($type === 'delovi') {
        return 'delovi';
    }
    if ($type === 'servis') {
        return 'servis';
    }
    if ($type === 'telefon' && shopAdIsPhoneListing($ad)) {
        return 'telefon';
    }
    return 'other';
}

function shopCatalogStripToken(string $value, int $max, string $pattern): string
{
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    $value = trim(preg_replace($pattern, '', $value) ?? '');
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }
    return mb_substr($value, 0, $max);
}

function shopCatalogBoundPrice($value): ?float
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '.', $raw);
    if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $raw) !== 1) {
        return null;
    }
    $number = (float)$raw;
    if ($number < 0 || $number > 1000000) {
        return null;
    }
    return $number;
}

/**
 * Javni filteri izloga. Vrednosti se bijelim listama skraćuju u PHP-u.
 * Katalog se ne filtrira ubacivanjem ovih stringova u SQL.
 *
 * @return array{
 *   q:string,type:string,cat:string,sort:string,brand:string,model:string,
 *   condition:string,min_price:?float,max_price:?float,min_battery:int
 * }
 */
function shopCatalogSanitizeFilters(array $input): array
{
    $type = trim((string)($input['type'] ?? ''));
    if (!in_array($type, ['telefon', 'delovi', 'servis'], true)) {
        $type = '';
    }
    $sort = trim((string)($input['sort'] ?? 'newest'));
    if (!in_array($sort, ['newest', 'price_asc', 'price_desc'], true)) {
        $sort = 'newest';
    }

    $condition = trim((string)($input['condition'] ?? ''));
    $allowedConditions = function_exists('adFormSchema')
        ? array_values(array_map('strval', adFormSchema()['phone_conditions'] ?? []))
        : ['Novo', 'Kao novo', 'Polovno', 'Oštećeno/Za delove'];
    if (!in_array($condition, $allowedConditions, true)) {
        $condition = '';
    }

    $batteryRaw = trim((string)($input['min_battery'] ?? ''));
    $minBattery = in_array($batteryRaw, ['85', '90', '95', '100'], true) ? (int)$batteryRaw : 0;

    $minPrice = shopCatalogBoundPrice($input['min_price'] ?? '');
    $maxPrice = shopCatalogBoundPrice($input['max_price'] ?? '');
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
    }

    $cat = shopCatalogStripToken((string)($input['shop_category'] ?? $input['cat'] ?? ''), 40, '/[^a-z0-9\-]/');

    return [
        'q' => shopCatalogStripToken((string)($input['q'] ?? ''), 80, '/[^\p{L}\p{N} .+\-\/]/u'),
        'type' => $type,
        'cat' => $cat,
        'sort' => $sort,
        'brand' => shopCatalogStripToken((string)($input['brand'] ?? ''), 40, '/[^\p{L}\p{N} .+\-\/&]/u'),
        'model' => shopCatalogStripToken((string)($input['model'] ?? ''), 80, '/[^\p{L}\p{N} .+\-\/]/u'),
        'condition' => $condition,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'min_battery' => $minBattery,
    ];
}

function shopCatalogPriceQueryValue(?float $price): string
{
    if ($price === null) {
        return '';
    }
    if (abs($price - round($price)) < 0.001) {
        return (string)(int)round($price);
    }
    return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
}

/** @return array<string, int|string> */
function shopCatalogPublicParams(array $filters): array
{
    $clean = shopCatalogSanitizeFilters($filters);
    $params = [];
    if ($clean['q'] !== '') {
        $params['q'] = $clean['q'];
    }
    if ($clean['type'] !== '') {
        $params['type'] = $clean['type'];
    }
    if ($clean['sort'] !== 'newest') {
        $params['sort'] = $clean['sort'];
    }
    if ($clean['brand'] !== '') {
        $params['brand'] = $clean['brand'];
    }
    if ($clean['model'] !== '') {
        $params['model'] = $clean['model'];
    }
    $minPrice = shopCatalogPriceQueryValue($clean['min_price']);
    $maxPrice = shopCatalogPriceQueryValue($clean['max_price']);
    if ($minPrice !== '') {
        $params['min_price'] = $minPrice;
    }
    if ($maxPrice !== '') {
        $params['max_price'] = $maxPrice;
    }
    if ($clean['condition'] !== '') {
        $params['condition'] = $clean['condition'];
    }
    if ($clean['min_battery'] > 0) {
        $params['min_battery'] = $clean['min_battery'];
    }
    if ($clean['cat'] !== '') {
        $params['cat'] = $clean['cat'];
    }
    $page = max(1, (int)($filters['page'] ?? 1));
    if ($page > 1) {
        $params['page'] = $page;
    }
    return $params;
}

function shopCatalogFiltersNeedNoindex(array $filters): bool
{
    $clean = shopCatalogSanitizeFilters($filters);
    if ($clean['q'] !== '' || $clean['type'] !== '' || $clean['brand'] !== '' || $clean['model'] !== '' || $clean['condition'] !== '') {
        return true;
    }
    if ($clean['min_price'] !== null || $clean['max_price'] !== null || $clean['min_battery'] > 0) {
        return true;
    }
    if ($clean['sort'] !== 'newest') {
        return true;
    }
    return max(1, (int)($filters['page'] ?? 1)) > 1;
}

/** @return array{cat?:string} */
function shopCatalogCanonicalParams(array $filters): array
{
    $cat = shopCatalogSanitizeFilters($filters)['cat'];
    return $cat !== '' ? ['cat' => $cat] : [];
}

/**
 * @param list<array> $ads
 * @param array{q?:string,type?:string,shop_category?:string,cat?:string,sort?:string,hide_sold?:bool,brand?:string,model?:string,condition?:string,min_price?:mixed,max_price?:mixed,min_battery?:mixed} $filters
 * @return list<array>
 */
function filterShopAds(array $ads, array $filters = []): array
{
    $clean = shopCatalogSanitizeFilters($filters);
    $hideSold = !empty($filters['hide_sold']);
    $q = mb_strtolower($clean['q']);
    $type = $clean['type'];
    $cat = $clean['cat'];
    $brand = mb_strtolower($clean['brand']);
    $model = mb_strtolower($clean['model']);
    $condition = $clean['condition'];
    $minPrice = $clean['min_price'];
    $maxPrice = $clean['max_price'];
    $minBattery = $clean['min_battery'];

    $ads = array_values(array_filter($ads, static function ($ad) use (
        $q, $type, $cat, $hideSold, $brand, $model, $condition, $minPrice, $maxPrice, $minBattery
    ) {
        if (!is_array($ad)) {
            return false;
        }
        if ($hideSold && !empty($ad['is_sold'])) {
            return false;
        }
        if ($type !== '' && shopAdCatalogBucket($ad) !== $type) {
            return false;
        }
        if ($cat !== '' && trim((string)($ad['shop_category_id'] ?? '')) !== $cat) {
            return false;
        }
        if ($brand !== '' && mb_strtolower(trim((string)($ad['brand'] ?? ''))) !== $brand) {
            return false;
        }
        if ($model !== '' && !str_contains(mb_strtolower(trim((string)($ad['model'] ?? ''))), $model)) {
            return false;
        }
        if ($condition !== '' && trim((string)($ad['condition_state'] ?? '')) !== $condition) {
            return false;
        }
        if ($minBattery > 0) {
            if (!shopAdIsPhoneListing($ad)) {
                return false;
            }
            $bh = $ad['battery_health'] ?? null;
            if ($bh === null || $bh === '' || !is_numeric((string)$bh) || (int)$bh < $minBattery) {
                return false;
            }
        }
        if ($minPrice !== null || $maxPrice !== null) {
            if (adPriceType($ad) !== 'fixed') {
                return false;
            }
            $eur = adPriceEur($ad);
            if ($minPrice !== null && $eur < $minPrice) {
                return false;
            }
            if ($maxPrice !== null && $eur > $maxPrice) {
                return false;
            }
        }
        if ($q !== '') {
            $hay = mb_strtolower(
                trim((string)($ad['title'] ?? '')) . ' ' .
                trim((string)($ad['description'] ?? '')) . ' ' .
                trim((string)($ad['brand'] ?? '')) . ' ' .
                trim((string)($ad['model'] ?? ''))
            );
            if (!str_contains($hay, $q)) {
                return false;
            }
        }
        return true;
    }));

    return sortAds($ads, $clean['sort']);
}

/**
 * @param list<array> $ads
 * @return array{all:int,telefon:int,delovi:int,servis:int}
 */
function shopAdTypeCounts(array $ads): array
{
    $counts = ['all' => 0, 'telefon' => 0, 'delovi' => 0, 'servis' => 0];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $counts['all']++;
        $bucket = shopAdCatalogBucket($ad);
        if (isset($counts[$bucket])) {
            $counts[$bucket]++;
        }
    }
    return $counts;
}

/**
 * @param list<array> $ads
 * @return list<string>
 */
function shopCatalogBrandOptions(array $ads): array
{
    $brands = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $brand = trim((string)($ad['brand'] ?? ''));
        if ($brand === '' || mb_strtolower($brand) === 'ostalo') {
            continue;
        }
        $brands[mb_strtolower($brand)] = $brand;
    }
    natcasesort($brands);
    return array_values($brands);
}

/**
 * @param list<array> $ads
 * @return list<string>
 */
function shopCatalogModelOptions(array $ads, string $brand = ''): array
{
    $brand = mb_strtolower(trim($brand));
    $models = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        if ($brand !== '' && mb_strtolower(trim((string)($ad['brand'] ?? ''))) !== $brand) {
            continue;
        }
        $model = trim((string)($ad['model'] ?? ''));
        if ($model === '') {
            continue;
        }
        $models[mb_strtolower($model)] = $model;
    }
    natcasesort($models);
    return array_values($models);
}

/** @return array<string, string> */
function shopCatalogBatteryOptions(): array
{
    return [
        '' => 'Sve',
        '85' => 'Od 85%',
        '90' => 'Od 90%',
        '95' => 'Od 95%',
        '100' => '100%',
    ];
}

/** @return list<string> */
function shopCatalogConditionOptions(): array
{
    $options = function_exists('adFormSchema')
        ? array_values(array_map('strval', adFormSchema()['phone_conditions'] ?? []))
        : [];
    return $options !== [] ? $options : ['Novo', 'Kao novo', 'Polovno', 'Oštećeno/Za delove'];
}

/**
 * @param list<array> $ads
 * @param list<array{id:string,name:string,sort:int}> $categories
 * @return list<array{id:string,name:string,count:int}>
 */
function shopCategoryCounts(array $ads, array $categories): array
{
    $counts = [];
    foreach ($categories as $cat) {
        $counts[$cat['id']] = 0;
    }
    foreach ($ads as $ad) {
        $id = trim((string)($ad['shop_category_id'] ?? ''));
        if ($id !== '' && isset($counts[$id])) {
            $counts[$id]++;
        }
    }
    $out = [];
    foreach ($categories as $cat) {
        $out[] = [
            'id' => $cat['id'],
            'name' => $cat['name'],
            'count' => $counts[$cat['id']] ?? 0,
        ];
    }
    return $out;
}

function shopCatalogQuery(array $params): string
{
    $allowed = ['q', 'type', 'sort', 'page', 'brand', 'model', 'min_price', 'max_price', 'condition', 'min_battery'];
    $clean = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $params)) {
            continue;
        }
        $value = $params[$key];
        if ($value === null || $value === '' || $value === false) {
            continue;
        }
        if ($key === 'page' && (int)$value <= 1) {
            continue;
        }
        if ($key === 'sort' && (string)$value === 'newest') {
            continue;
        }
        if ($key === 'min_battery' && (int)$value <= 0) {
            continue;
        }
        $clean[$key] = $value;
    }
    return $clean === [] ? '' : ('?' . http_build_query($clean));
}

/**
 * Kanonski URL kataloga izloga.
 * Kategorija: /izlog/{slug}/{cat} — ostali filteri ostaju u query-ju.
 */
function shopCatalogUrl(array $user, array $params = []): string
{
    $base = shopUrlForUser($user);
    $cat = trim((string)($params['cat'] ?? ''));
    unset($params['cat']);
    if ($cat !== '' && findShopCategory($user, $cat)) {
        $base .= '/' . rawurlencode($cat);
    }
    return $base . shopCatalogQuery($params);
}

function shopLogoUploadsDir(): string
{
    return dirname(__DIR__) . '/public/uploads/shops';
}

function ensureShopLogoUploadsDir(): void
{
    $dir = shopLogoUploadsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/** Javni URL logoa firme, ili prazan string. */
function userShopLogoUrl(?array $user): string
{
    if (!$user) {
        return '';
    }
    $logo = trim((string)($user['shop_logo'] ?? ''));
    if ($logo === '' || !str_starts_with($logo, '/uploads/shops/')) {
        return '';
    }
    return $logo;
}

function canUploadShopLogo(?array $user): bool
{
    return isVerifiedSeller($user);
}

/**
 * Upload / brisanje logoa izloga.
 *
 * @return array{ok:bool,url:?string,changed:bool,error:?string}
 */
function handleShopLogoUpload(int $userId, ?string $existingLogo = null): array
{
    $existing = $existingLogo && str_starts_with($existingLogo, '/uploads/shops/') ? $existingLogo : null;

    if (!empty($_POST['shop_logo_remove'])) {
        if ($existing) {
            $old = dirname(__DIR__) . '/public' . $existing;
            if (is_file($old)) {
                @unlink($old);
            }
        }
        return ['ok' => true, 'url' => null, 'changed' => true, 'error' => null];
    }

    if (!isset($_FILES['shop_logo']) || !is_array($_FILES['shop_logo'])) {
        return ['ok' => true, 'url' => $existing, 'changed' => false, 'error' => null];
    }

    $fileErr = (int)($_FILES['shop_logo']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileErr === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'url' => $existing, 'changed' => false, 'error' => null];
    }
    if ($fileErr !== UPLOAD_ERR_OK) {
        $msg = match ($fileErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Logo je prevelik (server limit).',
            default => 'Upload logoa nije uspeo (kod ' . $fileErr . ').',
        };
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => $msg];
    }

    $tmp = (string)($_FILES['shop_logo']['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Privremeni fajl logoa nije pronađen.'];
    }
    $type = mime_content_type($tmp) ?: '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($type, $allowed, true)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WebP, GIF.'];
    }

    $size = (int)($_FILES['shop_logo']['size'] ?? 0);
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Logo mora biti do 4 MB.'];
    }

    ensureShopLogoUploadsDir();
    $base = shopLogoUploadsDir();
    if (!is_dir($base) || !is_writable($base)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Folder uploads/shops nije upisiv. Na serveru uradi chmod/chown.'];
    }

    $targetDir = $base . '/' . $userId;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Ne mogu da napravim folder za logo (dozvole).'];
    }
    if (!is_writable($targetDir)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Folder za logo nije upisiv (chmod).'];
    }

    $name = 'logo_' . time() . '.jpg';
    $dest = $targetDir . '/' . $name;
    if (!saveShopLogoImage($tmp, $dest, $type)) {
        return ['ok' => false, 'url' => $existing, 'changed' => false, 'error' => 'Obrada slike nije uspela. Proveri da li PHP ima GD ekstenziju.'];
    }

    if ($existing) {
        $old = dirname(__DIR__) . '/public' . $existing;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    return ['ok' => true, 'url' => '/uploads/shops/' . $userId . '/' . $name, 'changed' => true, 'error' => null];
}

/** Kvadratni logo max 512px, beli background, JPEG. */
function saveShopLogoImage(string $tmpPath, string $destPath, string $mime): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($tmpPath, $destPath);
    }

    $src = match ($mime) {
        'image/png' => @imagecreatefrompng($tmpPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
        'image/gif' => @imagecreatefromgif($tmpPath),
        default => @imagecreatefromjpeg($tmpPath),
    };
    if ($src === false) {
        return move_uploaded_file($tmpPath, $destPath);
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $sx = (int)floor(($w - $side) / 2);
    $sy = (int)floor(($h - $side) / 2);
    $out = 512;
    if ($side < $out) {
        $out = max(64, $side);
    }

    $dst = imagecreatetruecolor($out, $out);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $out, $out, $white);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $out, $out, $side, $side);
    imagedestroy($src);
    $ok = imagejpeg($dst, $destPath, 88);
    imagedestroy($dst);
    return (bool)$ok;
}

/**
 * HTML za avatar izloga (logo ili inicijali).
 */
function renderShopAvatarHtml(?array $user, string $initials, string $extraClass = ''): string
{
    $cls = trim('seller-avatar ' . $extraClass);
    $logo = userShopLogoUrl($user);
    if ($logo !== '') {
        $alt = trim((string)(($user['shop_name'] ?? '') ?: ($user['full_name'] ?? 'Logo')));
        return '<div class="' . h($cls) . ' has-logo"><img src="' . h($logo) . '" alt="' . h($alt) . '" loading="lazy" decoding="async"></div>';
    }
    return '<div class="' . h($cls) . '" aria-hidden="true">' . h($initials) . '</div>';
}

/**
 * Broj za Pozovi: profil ako postoji, inače jedinstveni broj svih aktivnih oglasa.
 * Različiti brojevi se ne spajaju u jedan izmišljeni kontakt.
 *
 * @param list<array> $ads
 */
function shopSellerCallPhone(array $seller, array $ads = []): string
{
    $profile = trim((string)($seller['phone'] ?? ''));
    if (adTelHref($profile) !== '') {
        return $profile;
    }

    $phones = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $phone = trim((string)($ad['contact_phone'] ?? ''));
        $digits = adContactPhoneDigits($phone);
        if ($digits === '' || adTelHref($phone) === '') {
            continue;
        }
        $phones[$digits] = $phone;
    }
    if (count($phones) === 1) {
        return (string)array_values($phones)[0];
    }
    return '';
}

/**
 * Viber i WhatsApp samo ako izlog ima sopstveno polje.
 * Broj za poziv sam po sebi ne uključuje te kanale.
 *
 * @return list<array{key:string,href:string,label:string,external:bool}>
 */
function shopSellerExtraContactLinks(array $seller): array
{
    $links = [];
    $shopName = function_exists('getSellerShopName') ? getSellerShopName($seller) : trim((string)($seller['shop_name'] ?? ''));
    $message = 'Zdravo' . ($shopName !== '' ? ', javljam se sa izloga ' . $shopName . '.' : '.');

    $whatsapp = trim((string)($seller['shop_page_contact_whatsapp'] ?? ''));
    $whatsappOn = function_exists('siteSettings') && !empty(siteSettings()['enable_whatsapp']);
    if ($whatsappOn && $whatsapp !== '') {
        $href = whatsappLink($whatsapp, $message);
        if ($href !== '') {
            $links[] = ['key' => 'whatsapp', 'href' => $href, 'label' => 'WhatsApp', 'external' => true];
        }
    }

    $viber = trim((string)($seller['shop_page_contact_viber'] ?? ''));
    if ($viber !== '') {
        $href = viberChatLink($viber);
        if ($href !== '') {
            $links[] = ['key' => 'viber', 'href' => $href, 'label' => 'Viber', 'external' => false];
        }
    }

    return $links;
}

/**
 * @return array{
 *   title:string,brand:string,condition:string,storage:string,battery:?int,
 *   location:string,price:string,rsd:string,image:string,image_alt:string,
 *   detail_href:string,contact_href:string,promoted:bool
 * }
 */
function shopCatalogCardModel(array $ad): array
{
    $brand = trim((string)($ad['brand'] ?? ''));
    if (mb_strtolower($brand) === 'ostalo') {
        $brand = '';
    }
    $battery = null;
    if (getAdType($ad) === 'telefon') {
        $rawBattery = $ad['battery_health'] ?? null;
        if ($rawBattery !== null && $rawBattery !== '' && is_numeric((string)$rawBattery)) {
            $battery = max(0, min(100, (int)$rawBattery));
        }
    }
    $detail = adUrl($ad);
    $priceOpen = isAdPriceOpen($ad);
    $image = adPrimaryListingThumb($ad) ?? '';

    return [
        'title' => adDisplayTitle($ad),
        'brand' => $brand,
        'condition' => trim((string)($ad['condition_state'] ?? '')),
        'storage' => trim((string)($ad['storage'] ?? '')),
        'battery' => $battery,
        'location' => trim((string)($ad['location'] ?? '')),
        'price' => adCardPriceMainLabel($ad),
        'rsd' => (!$priceOpen && !isBuyListing($ad)) ? formatAdPriceRsd($ad) : '',
        'image' => $image,
        'image_alt' => $image !== '' && function_exists('seoAdImageAlt') ? seoAdImageAlt($ad, 1) : '',
        'detail_href' => $detail,
        'contact_href' => $detail . '#kontakt',
        'promoted' => function_exists('isAdTopActive') && isAdTopActive($ad),
    ];
}

function shopCatalogCardHtml(array $ad): string
{
    $card = shopCatalogCardModel($ad);
    $chips = '';
    if ($card['brand'] !== '') {
        $chips .= '<span class="shop-card-chip shop-card-chip-muted">' . h($card['brand']) . '</span>';
    }
    if ($card['condition'] !== '') {
        $chips .= '<span class="shop-card-chip">' . h($card['condition']) . '</span>';
    }
    if ($card['battery'] !== null) {
        $chips .= '<span class="shop-card-chip shop-card-chip-bh">BH ' . (int)$card['battery'] . '%</span>';
    }
    if ($card['storage'] !== '') {
        $chips .= '<span class="shop-card-chip shop-card-chip-muted">' . h($card['storage']) . '</span>';
    }

    $media = '<div class="shop-card-thumb shop-card-thumb-empty" aria-hidden="true"></div>';
    if ($card['image'] !== '') {
        $media = '<a class="shop-card-thumb" href="' . h($card['detail_href']) . '">'
            . '<img src="' . h($card['image']) . '" alt="' . h($card['image_alt']) . '" width="400" height="328" loading="lazy" decoding="async">'
            . '</a>';
    }

    $badge = $card['promoted'] ? '<span class="shop-card-top">TOP</span>' : '';
    $rsd = $card['rsd'] !== '' ? '<span class="shop-card-rsd">' . h($card['rsd']) . '</span>' : '';
    $location = $card['location'] !== '' ? '<p class="shop-card-loc">' . h($card['location']) . '</p>' : '';
    $chipBlock = $chips !== '' ? '<div class="shop-card-chips">' . $chips . '</div>' : '';

    return '<article class="shop-card" data-shop-card data-ad-id="' . (int)($ad['id'] ?? 0) . '">'
        . $media
        . $badge
        . '<div class="shop-card-body">'
        . '<h2 class="shop-card-title"><a href="' . h($card['detail_href']) . '">' . h($card['title']) . '</a></h2>'
        . $chipBlock
        . '<p class="shop-card-price"><span class="shop-card-eur">' . h($card['price']) . '</span>' . $rsd . '</p>'
        . $location
        . '<div class="shop-card-actions">'
        . '<a class="shop-card-btn shop-card-btn-detail" data-shop-detail href="' . h($card['detail_href']) . '">Detalji</a>'
        . '<a class="shop-card-btn shop-card-btn-contact" data-shop-contact href="' . h($card['contact_href']) . '">Kontakt</a>'
        . '</div></div></article>';
}
