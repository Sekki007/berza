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
    $items = [];
    foreach (getNotificationsForUser($userId) as $n) {
        $items[] = [
            'id' => (int)($n['id'] ?? 0),
            'type' => (string)($n['type'] ?? ''),
            'title' => (string)($n['title'] ?? ''),
            'body' => (string)($n['body'] ?? ''),
            'link' => (string)($n['link'] ?? ''),
            'is_read' => !empty($n['is_read']),
            'created_at' => (string)($n['created_at'] ?? ''),
            'relative' => function_exists('formatRelativeTime')
                ? formatRelativeTime((string)($n['created_at'] ?? ''))
                : '',
        ];
    }
    mobileApiJson([
        'ok' => true,
        'items' => $items,
        'unread' => getUnreadNotificationCount($userId),
        'csrf' => csrfToken(),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!verifyCsrf(mobileCsrfFromRequest())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

$action = trim((string)($_POST['action'] ?? 'mark_all_read'));
if ($action === 'mark_all_read') {
    markAllNotificationsRead($userId);
    mobileApiJson(['ok' => true, 'unread' => 0, 'csrf' => csrfToken()]);
}

if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = $id > 0 ? markNotificationRead($id, $userId) : false;
    mobileApiJson([
        'ok' => $ok,
        'unread' => getUnreadNotificationCount($userId),
        'csrf' => csrfToken(),
    ]);
}

mobileApiJson(['ok' => false, 'error' => 'unknown_action', 'csrf' => csrfToken()], 400);
