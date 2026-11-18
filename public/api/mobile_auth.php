<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'status'));

function mobileAuthUserPayload(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $sessionUser = currentUser();
    if (!is_array($sessionUser)) {
        return null;
    }
    $full = findUserById((int)($sessionUser['id'] ?? 0)) ?? $sessionUser;
    return [
        'id' => (int)($full['id'] ?? $sessionUser['id'] ?? 0),
        'username' => (string)($full['username'] ?? $sessionUser['username'] ?? ''),
        'name' => trim((string)($full['shop_name'] ?? $full['full_name'] ?? $sessionUser['full_name'] ?? $sessionUser['username'] ?? '')),
        'email' => (string)($full['email'] ?? ''),
        'location' => trim((string)($full['location'] ?? '')),
        'is_admin' => isAdmin(),
        'verified_phone' => function_exists('isPhoneVerified') ? isPhoneVerified($full) : false,
    ];
}

function mobileCsrfFromRequest(): string
{
    $token = (string)($_POST['_csrf'] ?? $_POST['csrf'] ?? '');
    if ($token === '') {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    return $token;
}

if ($method === 'GET' || $action === 'status' || $action === 'bootstrap') {
    mobileApiJson([
        'ok' => true,
        'logged_in' => isLoggedIn(),
        'user' => mobileAuthUserPayload(),
        'csrf' => csrfToken(),
        'favorites_count' => count(favoriteIds()),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!verifyCsrf(mobileCsrfFromRequest())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

if ($action === 'logout') {
    $logoutUserId = (int)(currentUser()['id'] ?? 0);
    if ($logoutUserId > 0) {
        clearRememberLoginForUser($logoutUserId);
    } else {
        clearRememberCookie();
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    mobileApiJson([
        'ok' => true,
        'logged_in' => false,
        'user' => null,
        'csrf' => csrfToken(),
    ]);
}

if ($action === 'login') {
    $username = trim((string)($_POST['username'] ?? $_POST['login'] ?? $_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $rememberMe = !isset($_POST['remember_me']) || !empty($_POST['remember_me']);

    if ($username === '' || $password === '') {
        mobileApiJson(['ok' => false, 'error' => 'missing_credentials', 'csrf' => csrfToken()], 422);
    }

    $user = findUserByUsername($username);
    if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
        mobileApiJson(['ok' => false, 'error' => 'invalid_credentials', 'csrf' => csrfToken()], 401);
    }

    if (!empty($user['is_blocked'])) {
        mobileApiJson([
            'ok' => false,
            'error' => 'blocked',
            'message' => 'Ovaj nalog je blokiran.',
            'csrf' => csrfToken(),
        ], 403);
    }

    $isAdminUser = !empty($user['is_admin']) || (($user['username'] ?? '') === 'admin');
    if (!$isAdminUser && function_exists('isPhoneVerified') && !isPhoneVerified($user)) {
        $_SESSION['pending_phone_verify_user_id'] = (int)$user['id'];
        mobileApiJson([
            'ok' => false,
            'error' => 'phone_verify_required',
            'message' => 'Prvo potvrdi broj telefona SMS kodom na sajtu.',
            'csrf' => csrfToken(),
        ], 403);
    }

    session_regenerate_id(true);
    setSessionUserFromProfile($user);
    issueRememberLogin($user, $rememberMe);

    mobileApiJson([
        'ok' => true,
        'logged_in' => true,
        'user' => mobileAuthUserPayload(),
        'csrf' => csrfToken(),
        'favorites_count' => count(favoriteIds()),
    ]);
}

mobileApiJson(['ok' => false, 'error' => 'unknown_action', 'csrf' => csrfToken()], 400);
