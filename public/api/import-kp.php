<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/kp_import.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function kpImportJsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    kpImportJsonOut(['ok' => false, 'error' => 'method'], 405);
}

if (!isLoggedIn()) {
    kpImportJsonOut(['ok' => false, 'error' => 'login_required', 'message' => 'Prijavi se da uvezeš oglas.'], 401);
}

if (!verifyCsrf()) {
    kpImportJsonOut(['ok' => false, 'error' => 'csrf', 'message' => 'Sesija je istekla. Osveži stranicu.'], 403);
}

$userId = (int)(currentUser()['id'] ?? 0);
if ($userId <= 0) {
    kpImportJsonOut(['ok' => false, 'error' => 'login_required'], 401);
}

if (!kpImportRateLimitOk($userId, 5)) {
    kpImportJsonOut([
        'ok' => false,
        'error' => 'rate_limit',
        'message' => 'Previše pokušaja. Sačekaj minut pa probaj ponovo.',
    ], 429);
}

$owned = !empty($_POST['confirm_owned']) && (string)$_POST['confirm_owned'] !== '0';
if (!$owned) {
    kpImportJsonOut([
        'ok' => false,
        'error' => 'ownership',
        'message' => 'Potvrdi da si vlasnik oglasa / sadržaja pre uvoza.',
    ], 400);
}

$url = trim((string)($_POST['url'] ?? ''));
try {
    $result = kpImportFromUrl($url, $userId);
} catch (Throwable $e) {
    kpImportJsonOut([
        'ok' => false,
        'error' => 'exception',
        'message' => 'Greška pri uvozu: ' . $e->getMessage(),
    ], 500);
}

if (empty($result['ok'])) {
    kpImportJsonOut([
        'ok' => false,
        'error' => 'import_failed',
        'message' => (string)($result['error'] ?? 'KP nije vratio oglas; nalepi ručno ili pokušaj kasnije.'),
    ], 422);
}

kpImportJsonOut([
    'ok' => true,
    'draft' => $result['draft'],
    'images' => $result['images'] ?? [],
    'message' => 'Podaci su uvezeni u formu — proveri i objavi.',
]);
