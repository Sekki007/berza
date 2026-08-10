<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireLogin();

$adId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$return = trim((string)($_GET['return'] ?? $_POST['return'] ?? ''));
$defaultReturn = isAdmin() ? '/ads.php' : '/nalog.php?tab=oglasi';
if ($return === '' || !(str_starts_with($return, '/ads.php') || str_starts_with($return, '/nalog.php'))) {
    $return = $defaultReturn;
}

if ($adId <= 0) {
    setFlash('danger', 'Neispravan ID oglasa.');
    header('Location: ' . $return);
    exit;
}

$ad = getAdById($adId);
$userId = (int)currentUser()['id'];
$allowed = $ad && (isAdmin() || userOwnsAd($ad, $userId));

if (!$allowed) {
    setFlash('danger', 'Oglas nije pronađen ili nemaš dozvolu.');
    header('Location: ' . $return);
    exit;
}

if (deleteAdById($adId)) {
    setFlash('success', 'Oglas je obrisan.');
} else {
    setFlash('danger', 'Oglas nije pronađen.');
}

header('Location: ' . $return);
exit;
