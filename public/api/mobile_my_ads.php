<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

if (!isLoggedIn()) {
    mobileApiJson(['ok' => false, 'error' => 'auth_required', 'csrf' => csrfToken()], 401);
}

$userId = (int)(currentUser()['id'] ?? 0);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $filter = trim((string)($_GET['filter'] ?? 'all'));
    $all = getAdsByUserId($userId);
    usort($all, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

    $active = [];
    $sold = [];
    $inactive = [];
    foreach ($all as $ad) {
        if (!empty($ad['is_sold'])) {
            $sold[] = $ad;
        } elseif ((int)($ad['is_active'] ?? 0) === 1) {
            $active[] = $ad;
        } else {
            $inactive[] = $ad;
        }
    }

    $ads = match ($filter) {
        'active' => $active,
        'sold' => $sold,
        'inactive' => $inactive,
        default => $all,
    };

    $items = [];
    foreach ($ads as $ad) {
        $items[] = mobileFormatAdCard($ad);
    }

    mobileApiJson([
        'ok' => true,
        'items' => $items,
        'counts' => [
            'all' => count($all),
            'active' => count($active),
            'sold' => count($sold),
            'inactive' => count($inactive),
        ],
        'csrf' => csrfToken(),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!verifyCsrf(mobileCsrfFromRequest())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

$action = trim((string)($_POST['action'] ?? ''));
$adId = (int)($_POST['ad_id'] ?? $_POST['id'] ?? 0);
$ad = $adId > 0 ? getAdById($adId) : null;
if (!$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
    mobileApiJson(['ok' => false, 'error' => 'not_found', 'csrf' => csrfToken()], 404);
}

if ($action === 'toggle_sold' && function_exists('toggleAdSold')) {
    $result = toggleAdSold($adId, $userId);
    $fresh = getAdById($adId);
    mobileApiJson([
        'ok' => $result !== null,
        'sold' => (bool)$result,
        'ad' => $fresh ? mobileFormatAdCard($fresh) : null,
        'csrf' => csrfToken(),
    ]);
}

mobileApiJson(['ok' => false, 'error' => 'unknown_action', 'csrf' => csrfToken()], 400);
