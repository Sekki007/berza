<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!telegramEnabled()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'telegram_disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

$secret = telegramWebhookSecret();
if ($secret !== '') {
    $headerSecret = trim((string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
    if (!hash_equals($secret, $headerSecret)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

handleTelegramWebhookUpdate($payload);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
