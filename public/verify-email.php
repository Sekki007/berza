<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$user = $token !== '' ? confirmEmailVerification($token) : null;

if ($user) {
    setFlash('success', 'Email je potvrđen. Hvala!');
    if (isLoggedIn()) {
        header('Location: /nalog.php?tab=profil');
    } else {
        header('Location: /login.php');
    }
} else {
    setFlash('danger', 'Link za potvrdu nije važeći ili je već iskorišćen.');
    header('Location: ' . (isLoggedIn() ? '/nalog.php?tab=profil' : '/login.php'));
}
exit;
