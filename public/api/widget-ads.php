<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/widget.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=180');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$limit = (int)($_GET['limit'] ?? 3);
$type = trim((string)($_GET['type'] ?? ''));
$ref = resolveWidgetRef(trim((string)($_GET['ref'] ?? '')));

$ads = fetchWidgetAds($limit, $type, $ref);

echo json_encode([
    'ok' => true,
    'count' => count($ads),
    'ref' => $ref !== '' ? $ref : 'partner',
    'ads' => $ads,
    'home' => absoluteUrl('/') . '?' . http_build_query([
        'utm_source' => 'widget',
        'utm_medium' => 'embed',
        'utm_campaign' => $ref !== '' ? $ref : 'partner',
    ]),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
