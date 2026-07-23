<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function jsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    jsonOut(['ok' => false, 'error' => 'login_required'], 401);
}

$site = siteSettings();
if (empty($site['enable_messages'])) {
    jsonOut(['ok' => false, 'error' => 'disabled'], 403);
}

$user = currentUser();
$userId = (int)$user['id'];
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'thread'));

if ($action === 'unread') {
    jsonOut([
        'ok' => true,
        'unread' => getUnreadMessageCount($userId),
    ]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adId = (int)($_POST['ad_id'] ?? 0);
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $body = trim((string)($_POST['message'] ?? ''));

    if ($adId <= 0 || $toUserId <= 0 || $body === '') {
        jsonOut(['ok' => false, 'error' => 'invalid'], 400);
    }
    if ($toUserId === $userId) {
        jsonOut(['ok' => false, 'error' => 'self'], 400);
    }

    $savedId = saveMessage([
        'ad_id' => $adId,
        'from_user_id' => $userId,
        'from_name' => (string)$user['full_name'],
        'from_phone' => '',
        'to_user_id' => $toUserId,
        'body' => $body,
    ]);

    if (!$savedId) {
        jsonOut(['ok' => false, 'error' => 'save_failed'], 500);
    }

    $messages = getThreadMessages($userId, $adId, $toUserId);
    $created = null;
    foreach ($messages as $msg) {
        if ((int)($msg['id'] ?? 0) === $savedId) {
            $created = $msg;
            break;
        }
    }

    jsonOut([
        'ok' => true,
        'message' => formatMessageForApi($created ?? [
            'id' => $savedId,
            'ad_id' => $adId,
            'from_user_id' => $userId,
            'to_user_id' => $toUserId,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ], $userId),
        'unread' => getUnreadMessageCount($userId),
    ]);
}

if ($action === 'threads') {
    $threads = getMessageThreads($userId);
    jsonOut([
        'ok' => true,
        'unread' => getUnreadMessageCount($userId),
        'threads' => array_map(static function ($t) {
            return [
                'key' => (string)$t['key'],
                'ad_id' => (int)$t['ad_id'],
                'partner_id' => (int)$t['partner_id'],
                'partner_name' => (string)$t['partner_name'],
                'ad_title' => (string)$t['ad_title'],
                'last_body' => (string)$t['last_body'],
                'last_at' => (string)$t['last_at'],
                'relative' => formatRelativeTime((string)$t['last_at']),
                'unread' => (int)$t['unread'],
                'total' => (int)$t['total'],
            ];
        }, $threads),
    ]);
}

if ($action === 'thread') {
    $adId = (int)($_GET['ad'] ?? 0);
    $withId = (int)($_GET['with'] ?? 0);
    $afterId = (int)($_GET['after_id'] ?? 0);

    if ($adId <= 0 || $withId <= 0) {
        jsonOut(['ok' => false, 'error' => 'invalid'], 400);
    }

    markThreadRead($userId, $adId, $withId);
    $messages = getThreadMessages($userId, $adId, $withId);
    if ($afterId > 0) {
        $messages = array_values(array_filter($messages, static fn($m) => (int)($m['id'] ?? 0) > $afterId));
    }

    jsonOut([
        'ok' => true,
        'messages' => array_map(static fn($m) => formatMessageForApi($m, $userId), $messages),
        'unread' => getUnreadMessageCount($userId),
        'last_id' => $messages ? (int)($messages[count($messages) - 1]['id'] ?? $afterId) : $afterId,
    ]);
}

jsonOut(['ok' => false, 'error' => 'unknown_action'], 400);

function formatMessageForApi(array $msg, int $userId): array
{
    return [
        'id' => (int)($msg['id'] ?? 0),
        'ad_id' => (int)($msg['ad_id'] ?? 0),
        'from_user_id' => (int)($msg['from_user_id'] ?? 0),
        'to_user_id' => (int)($msg['to_user_id'] ?? 0),
        'body' => (string)($msg['body'] ?? ''),
        'created_at' => (string)($msg['created_at'] ?? ''),
        'relative' => formatRelativeTime((string)($msg['created_at'] ?? '')),
        'mine' => (int)($msg['from_user_id'] ?? 0) === $userId,
    ];
}
