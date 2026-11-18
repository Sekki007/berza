<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $ad = getAdById($id);
    if (!$ad || (!(isAdmin() || (int)($ad['is_active'] ?? 0) === 1))) {
        mobileApiJson(['ok' => false, 'error' => 'not_found'], 404);
    }
    if (function_exists('incrementAdViews')) {
        incrementAdViews($id);
        $ad = getAdById($id) ?: $ad;
    }
    mobileApiJson([
        'ok' => true,
        'ad' => mobileFormatAdDetail($ad),
        'csrf' => csrfToken(),
        'auth' => [
            'logged_in' => isLoggedIn(),
            'user_id' => (int)(currentUser()['id'] ?? 0),
        ],
    ]);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(48, max(1, (int)($_GET['per_page'] ?? 24)));
$filters = mobileBuildAdFiltersFromRequest();
$ads = getPublicAds($filters);
$paged = paginateAds($ads, $page, $perPage);

$items = [];
foreach ($paged['items'] as $ad) {
    $items[] = mobileFormatAdCard($ad);
}

$totalPages = (int)($paged['pages'] ?? 1);
$currentPage = (int)($paged['page'] ?? $page);

mobileApiJson([
    'ok' => true,
    'items' => $items,
    'page' => $currentPage,
    'per_page' => $perPage,
    'total' => (int)($paged['total'] ?? count($ads)),
    'total_pages' => $totalPages,
    'has_more' => $currentPage < $totalPages,
    'csrf' => csrfToken(),
    'auth' => [
        'logged_in' => isLoggedIn(),
        'user_id' => (int)(currentUser()['id'] ?? 0),
    ],
]);
