<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function pushJsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    pushJsonOut(['ok' => false, 'error' => 'login_required'], 401);
}

$user = currentUser();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    pushJsonOut(['ok' => false, 'error' => 'login_required'], 401);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($method === 'GET' && ($action === '' || $action === 'status')) {
    $tokens = getPushTokensForUser($userId);
    pushJsonOut([
        'ok' => true,
        'push_enabled_server' => pushEnabled(),
        'notify_push' => userWantsPushNotifications($user),
        'token_count' => count($tokens),
    ]);
}

if ($method === 'POST') {
    if (!verifyCsrf()) {
        pushJsonOut(['ok' => false, 'error' => 'csrf'], 403);
    }

    $action = $action !== '' ? $action : trim((string)($_POST['action'] ?? 'register'));
    $token = trim((string)($_POST['token'] ?? ''));
    $platform = trim((string)($_POST['platform'] ?? 'android'));

    if ($action === 'register') {
        if ($token === '') {
            pushJsonOut(['ok' => false, 'error' => 'token_required'], 400);
        }
        $ok = upsertPushToken($userId, $token, $platform);
        // Prvi put uključi notify_push ako nije eksplicitno isključen
        $fresh = findUserById($userId);
        if ($fresh && !array_key_exists('notify_push', $fresh)) {
            updateUserProfile($userId, ['notify_push' => true]);
        }
        pushJsonOut(['ok' => $ok, 'registered' => $ok]);
    }

    if ($action === 'unregister') {
        if ($token !== '') {
            deletePushToken($token, $userId);
        } else {
            deletePushTokensForUser($userId);
        }
        pushJsonOut(['ok' => true]);
    }

    pushJsonOut(['ok' => false, 'error' => 'unknown_action'], 400);
}

pushJsonOut(['ok' => false, 'error' => 'method'], 405);
