<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($adId <= 0) {
    setFlash('danger', 'Neispravan ID oglasa.');
    header('Location: /ads.php');
    exit;
}

if (deleteAdById($adId)) {
    setFlash('success', 'Oglas je obrisan.');
} else {
    setFlash('danger', 'Oglas nije pronađen.');
}
header('Location: /ads.php');
exit;
