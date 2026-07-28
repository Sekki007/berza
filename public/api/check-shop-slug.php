<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$slug = trim((string)($_GET['slug'] ?? ''));
$exceptId = (int)($_GET['except'] ?? 0);
if ($exceptId <= 0 && isLoggedIn()) {
    $exceptId = (int)(currentUser()['id'] ?? 0);
}

if ($slug === '') {
    echo json_encode(['ok' => true, 'available' => null, 'message' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

$normalized = normalizeShopSlug($slug);
if (!isValidShopSlug($normalized)) {
    echo json_encode([
        'ok' => true,
        'available' => false,
        'normalized' => $normalized,
        'message' => 'Dozvoljena su mala slova, brojevi i crtica (3–40).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$taken = shopSlugTaken($normalized, $exceptId);
echo json_encode([
    'ok' => true,
    'available' => !$taken,
    'normalized' => $normalized,
    'message' => $taken ? 'URL je zauzet.' : 'URL je dostupan.',
], JSON_UNESCAPED_UNICODE);
