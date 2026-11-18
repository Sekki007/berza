<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function mobileFavoriteCsrf(): string
{
    $token = (string)($_POST['_csrf'] ?? $_POST['csrf'] ?? '');
    if ($token === '') {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    return $token;
}

if ($method === 'GET') {
    $ids = favoriteIds();
    $items = [];
    foreach ($ids as $fid) {
        $ad = getAdById((int)$fid);
        if (!$ad || (int)($ad['is_active'] ?? 0) !== 1) {
            continue;
        }
        $items[] = mobileFormatAdCard($ad);
    }
    mobileApiJson([
        'ok' => true,
        'items' => $items,
        'ids' => array_map('intval', $ids),
        'csrf' => csrfToken(),
        'logged_in' => isLoggedIn(),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!verifyCsrf(mobileFavoriteCsrf())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

$adId = (int)($_POST['ad_id'] ?? $_POST['id'] ?? 0);
if ($adId <= 0 || !getAdById($adId)) {
    mobileApiJson(['ok' => false, 'error' => 'not_found', 'csrf' => csrfToken()], 404);
}

$favorited = toggleFavorite($adId);
mobileApiJson([
    'ok' => true,
    'ad_id' => $adId,
    'favorited' => $favorited,
    'ids' => array_map('intval', favoriteIds()),
    'csrf' => csrfToken(),
]);
