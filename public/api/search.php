<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(12, max(1, (int)($_GET['limit'] ?? 8)));

if (mb_strlen($q) < 1) {
    echo json_encode(['suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'suggestions' => searchSuggestions($q, $limit),
], JSON_UNESCAPED_UNICODE);
