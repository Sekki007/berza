<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireLogin();

$adId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($adId <= 0) {
    setFlash('danger', 'Neispravan ID oglasa.');
    header('Location: ' . (isAdmin() ? '/ads.php' : '/nalog.php?tab=oglasi'));
    exit;
}

$ad = getAdById($adId);
$userId = (int)currentUser()['id'];
$allowed = $ad && (isAdmin() || userOwnsAd($ad, $userId));

if (!$allowed) {
    setFlash('danger', 'Oglas nije pronađen ili nemaš dozvolu.');
    header('Location: ' . (isAdmin() ? '/ads.php' : '/nalog.php?tab=oglasi'));
    exit;
}

if (deleteAdById($adId)) {
    setFlash('success', 'Oglas je obrisan.');
} else {
    setFlash('danger', 'Oglas nije pronađen.');
}

header('Location: ' . (isAdmin() ? '/ads.php' : '/nalog.php?tab=oglasi'));
exit;
