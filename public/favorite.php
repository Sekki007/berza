<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($adId <= 0) {
    header('Location: /index.php');
    exit;
}

$added = toggleFavorite($adId);
setFlash('success', $added ? 'Oglas je dodat u omiljene.' : 'Oglas je uklonjen iz omiljenih.');

$redirect = $_SERVER['HTTP_REFERER'] ?? '/favorites.php';
header('Location: ' . $redirect);
exit;
