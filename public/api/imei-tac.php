<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function imeiTacJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    imeiTacJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (!isLoggedIn()) {
    imeiTacJson(['ok' => false, 'error' => 'login_required'], 401);
}
if (!verifyCsrf()) {
    imeiTacJson(['ok' => false, 'error' => 'csrf'], 403);
}

$imei = normalizeImei((string)($_POST['imei'] ?? ''));
if (!isValidImei($imei)) {
    imeiTacJson(['ok' => false, 'error' => 'Unesi ispravan IMEI od 15 cifara.'], 422);
}

$check = checkImeiModel($imei);
if (empty($check['ok']) || !is_array($check['result'] ?? null)) {
    imeiTacJson([
        'ok' => false,
        'error' => (string)($check['error'] ?? 'IMEI provera trenutno nije uspela.'),
    ], 422);
}

$result = $check['result'];
imeiTacJson([
    'ok' => true,
    'brand' => trim((string)($result['brand'] ?? '')),
    'model' => trim((string)($result['model'] ?? $result['name'] ?? '')),
    'name' => trim((string)($result['name'] ?? '')),
]);
