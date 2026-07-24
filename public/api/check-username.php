<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$username = trim((string)($_GET['username'] ?? ''));

if ($username === '') {
    echo json_encode([
        'ok' => true,
        'available' => null,
        'message' => '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($username) < 3) {
    echo json_encode([
        'ok' => true,
        'available' => false,
        'message' => 'Korisničko ime mora imati najmanje 3 karaktera.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[\p{L}\p{N}_.-]{3,40}$/u', $username)) {
    echo json_encode([
        'ok' => true,
        'available' => false,
        'message' => 'Dozvoljena su slova, brojevi, _ . - (3–40 karaktera).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$taken = findUserByUsername($username) !== null;

echo json_encode([
    'ok' => true,
    'available' => !$taken,
    'message' => $taken ? 'Korisničko ime je zauzeto.' : 'Korisničko ime je dostupno.',
], JSON_UNESCAPED_UNICODE);
