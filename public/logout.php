<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$logoutUserId = (int)(currentUser()['id'] ?? 0);
if ($logoutUserId > 0) {
    clearRememberLoginForUser($logoutUserId);
} else {
    clearRememberCookie();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => !empty($params['secure']),
        'httponly' => !empty($params['httponly']),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: /login.php');
exit;
