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
 * @param list<array> $ads
 * @param array{q?:string,type?:string,shop_category?:string,sort?:string,hide_sold?:bool} $filters
 * @return list<array>
 */
function filterShopAds(array $ads, array $filters = []): array
{
    $q = trim((string)($filters['q'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));
    $cat = trim((string)($filters['shop_category'] ?? ''));
    $sort = trim((string)($filters['sort'] ?? 'newest'));
    $hideSold = !empty($filters['hide_sold']);

    if (!in_array($type, ['telefon', 'delovi', 'servis', ''], true)) {
        $type = '';
    }
    if (!in_array($sort, ['newest', 'price_asc', 'price_desc'], true)) {
        $sort = 'newest';
    }

    $ads = array_values(array_filter($ads, static function ($ad) use ($q, $type, $cat, $hideSold) {
        if ($hideSold && !empty($ad['is_sold'])) {
            return false;
        }
        if ($type !== '' && getAdType($ad) !== $type) {
            return false;
        }
        if ($cat !== '' && trim((string)($ad['shop_category_id'] ?? '')) !== $cat) {
            return false;
        }
        if ($q !== '') {
            $hay = mb_strtolower(
                trim((string)($ad['title'] ?? '')) . ' ' .
                trim((string)($ad['description'] ?? '')) . ' ' .
                trim((string)($ad['brand'] ?? '')) . ' ' .
                trim((string)($ad['model'] ?? ''))
            );
            if (!str_contains($hay, mb_strtolower($q))) {
                return false;
            }
        }
        return true;
    }));

    return sortAds($ads, $sort);
}

/**
 * @param list<array> $ads
 * @return array{all:int,telefon:int,delovi:int,servis:int}
 */
function shopAdTypeCounts(array $ads): array
{
    $counts = ['all' => 0, 'telefon' => 0, 'delovi' => 0, 'servis' => 0];
    foreach ($ads as $ad) {
        $counts['all']++;
        $t = getAdType($ad);
        if (isset($counts[$t])) {
            $counts[$t]++;
        }
    }
    return $counts;
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
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || $v === false) {
            continue;
        }
        if ($k === 'page' && (int)$v <= 1) {
            continue;
        }
        $clean[$k] = $v;
    }
    return $clean === [] ? '' : ('?' . http_build_query($clean));
}
