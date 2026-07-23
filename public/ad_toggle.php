<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = trim((string)($_GET['action'] ?? ''));

$ad = $adId > 0 ? getAdById($adId) : null;
if (!$ad) {
    setFlash('danger', 'Oglas nije pronađen.');
    header('Location: /ads.php');
    exit;
}

if ($action === 'sold') {
    $payload = $ad;
    $payload['is_sold'] = empty($ad['is_sold']) ? 1 : 0;
    if (!empty($payload['is_sold'])) {
        $payload['is_promoted'] = 0;
        $payload['promoted_until'] = null;
    }
    saveAd($payload, $adId);
    setFlash('success', $payload['is_sold'] ? 'Oglas označen kao prodato.' : 'Oglas vraćen u prodaju.');
} elseif ($action === 'promote') {
    if (isAdTopActive($ad)) {
        clearAdTop($adId);
        setFlash('success', 'Istaknuti status uklonjen.');
    } else {
        activateAdTop($adId, 7, 'admin');
        setFlash('success', 'Oglas istaknut (TOP) na 7 dana.');
    }
} else {
    header('Location: /ads.php');
    exit;
}

header('Location: /ads.php');
exit;
