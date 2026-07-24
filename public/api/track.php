<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

if (!verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$adId = (int)($_POST['ad_id'] ?? 0);
if ($adId <= 0 || !getAdById($adId)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if (!isset($_SESSION['phone_reveal_hits']) || !is_array($_SESSION['phone_reveal_hits'])) {
    $_SESSION['phone_reveal_hits'] = [];
}
$key = (string)$adId;
$last = (int)($_SESSION['phone_reveal_hits'][$key] ?? 0);
if ($last === 0 || (time() - $last) >= 1800) {
    $_SESSION['phone_reveal_hits'][$key] = time();
    bumpAdStat($adId, 'phone_reveals');
}

echo json_encode(['ok' => true]);
