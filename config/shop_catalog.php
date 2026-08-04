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
        // Kategorija ide u path (/izlog/slug/cat), ne u query
        if ($k === 'cat') {
            continue;
        }
        if ($k === 'page' && (int)$v <= 1) {
            continue;
        }
        if ($k === 'sort' && (string)$v === 'newest') {
            continue;
        }
        $clean[$k] = $v;
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
