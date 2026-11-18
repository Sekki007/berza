<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'register'));

if ($method === 'GET' && ($action === 'status' || $action === 'bootstrap')) {
    $site = siteSettings();
    mobileApiJson([
        'ok' => true,
        'registration_enabled' => !empty($site['enable_registration']),
        'sms_enabled' => function_exists('smsEnabled') ? smsEnabled() : false,
        'csrf' => csrfToken(),
        'logged_in' => isLoggedIn(),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!verifyCsrf(mobileCsrfFromRequest())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

if ($action === 'register') {
    if (isLoggedIn()) {
        mobileApiJson(['ok' => false, 'error' => 'already_logged_in', 'csrf' => csrfToken()], 400);
    }

    $site = siteSettings();
    if (empty($site['enable_registration'])) {
        mobileApiJson(['ok' => false, 'error' => 'disabled', 'message' => 'Registracija trenutno nije omogućena.', 'csrf' => csrfToken()], 403);
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $acceptedTerms = !empty($_POST['accept_terms']);

    $normalized = normalizePhoneRs($phone);
    if ($normalized === null || !isAllowedSmsPhone($normalized)) {
        mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Unesi validan srpski mobilni broj (npr. 06x xxx xxxx).', 'csrf' => csrfToken()], 422);
    }
    if (strlen($password) < 6) {
        mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Lozinka mora imati najmanje 6 karaktera.', 'csrf' => csrfToken()], 422);
    }
    if (mb_strlen($username) < 3) {
        mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Korisničko ime mora imati najmanje 3 karaktera.', 'csrf' => csrfToken()], 422);
    }
    if ($fullName === '') {
        mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Unesi ime i prezime.', 'csrf' => csrfToken()], 422);
    }
    if (!$acceptedTerms) {
        mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Potvrdi Uslove i Politiku privatnosti.', 'csrf' => csrfToken()], 422);
    }
    if (findUserByUsername($username)) {
        mobileApiJson(['ok' => false, 'error' => 'username_taken', 'message' => 'Korisničko ime je zauzeto.', 'csrf' => csrfToken()], 422);
    }
    if (findUserByPhone($normalized)) {
        mobileApiJson(['ok' => false, 'error' => 'phone_taken', 'message' => 'Ovaj broj telefona je već registrovan.', 'csrf' => csrfToken()], 422);
    }

    $userId = registerUser($username, $password, $fullName, $phone);
    if ($userId === false) {
        mobileApiJson(['ok' => false, 'error' => 'register_failed', 'message' => 'Registracija nije uspela.', 'csrf' => csrfToken()], 500);
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        patchUser($userId, ['email' => $email]);
    }

    $_SESSION['pending_phone_verify_user_id'] = $userId;

    if (!smsEnabled()) {
        patchUser($userId, ['phone_verified_at' => date('Y-m-d H:i:s')]);
        unset($_SESSION['pending_phone_verify_user_id']);
        $user = findUserById($userId);
        if ($user) {
            session_regenerate_id(true);
            setSessionUserFromProfile($user);
            issueRememberLogin($user, true);
        }
        mobileApiJson([
            'ok' => true,
            'logged_in' => isLoggedIn(),
            'phone_verify_required' => false,
            'auto_verified' => true,
            'user_id' => $userId,
            'csrf' => csrfToken(),
            'message' => 'Nalog je kreiran.',
        ]);
    }

    $otp = sendUserOtp($userId, 'phone_verify');
    $otpOk = !empty($otp['ok']);
    $channel = 'sms';
    if (!$otpOk && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && function_exists('mailIsConfigured') && mailIsConfigured()) {
        $emailOtp = sendUserOtp($userId, 'phone_verify', null, 'email', $email);
        $otpOk = !empty($emailOtp['ok']);
        $channel = $otpOk ? 'email' : 'sms';
    }

    mobileApiJson([
        'ok' => true,
        'logged_in' => false,
        'phone_verify_required' => true,
        'otp_sent' => $otpOk,
        'otp_channel' => $channel,
        'user_id' => $userId,
        'phone' => $normalized,
        'csrf' => csrfToken(),
        'message' => $otpOk
            ? ($channel === 'email' ? 'Nalog kreiran. Unesi kod sa emaila.' : 'Nalog kreiran. Unesi SMS kod.')
            : 'Nalog kreiran. Kod nije poslat — možeš ga ponovo poslati.',
    ]);
}

if ($action === 'verify' || $action === 'resend' || $action === 'send_email') {
    $userId = (int)($_SESSION['pending_phone_verify_user_id'] ?? 0);
    if ($userId <= 0) {
        $userId = (int)($_POST['user_id'] ?? 0);
    }
    $user = $userId > 0 ? findUserById($userId) : null;
    if (!$user) {
        mobileApiJson(['ok' => false, 'error' => 'no_pending', 'message' => 'Nema naloga za verifikaciju.', 'csrf' => csrfToken()], 400);
    }
    $_SESSION['pending_phone_verify_user_id'] = $userId;

    if (function_exists('isPhoneVerified') && isPhoneVerified($user)) {
        unset($_SESSION['pending_phone_verify_user_id']);
        mobileApiJson(['ok' => true, 'already_verified' => true, 'csrf' => csrfToken()]);
    }

    if ($action === 'resend' || $action === 'send') {
        $phoneInput = trim((string)($_POST['phone'] ?? (string)($user['phone'] ?? '')));
        $result = sendUserOtp($userId, 'phone_verify', $phoneInput !== '' ? $phoneInput : null, 'sms');
        mobileApiJson([
            'ok' => !empty($result['ok']),
            'otp_sent' => !empty($result['ok']),
            'otp_channel' => 'sms',
            'message' => !empty($result['ok']) ? 'SMS kod je poslat.' : (string)($result['error'] ?? 'SMS nije poslat.'),
            'csrf' => csrfToken(),
            'user_id' => $userId,
        ], !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'send_email') {
        $emailInput = trim((string)($_POST['email'] ?? (string)($user['email'] ?? '')));
        $result = sendUserOtp($userId, 'phone_verify', null, 'email', $emailInput !== '' ? $emailInput : null);
        mobileApiJson([
            'ok' => !empty($result['ok']),
            'otp_sent' => !empty($result['ok']),
            'otp_channel' => 'email',
            'message' => !empty($result['ok']) ? 'Kod je poslat na email.' : (string)($result['error'] ?? 'Email nije poslat.'),
            'csrf' => csrfToken(),
            'user_id' => $userId,
        ], !empty($result['ok']) ? 200 : 422);
    }

    // verify
    $code = trim((string)($_POST['code'] ?? ''));
    $result = verifyUserOtp($userId, 'phone_verify', $code);
    if (empty($result['ok'])) {
        mobileApiJson([
            'ok' => false,
            'error' => 'invalid_code',
            'message' => (string)($result['error'] ?? 'Verifikacija nije uspela.'),
            'csrf' => csrfToken(),
        ], 422);
    }

    unset($_SESSION['pending_phone_verify_user_id']);
    $verifiedUser = findUserById($userId);
    if ($verifiedUser) {
        session_regenerate_id(true);
        setSessionUserFromProfile($verifiedUser);
        issueRememberLogin($verifiedUser, true);
    }

    mobileApiJson([
        'ok' => true,
        'logged_in' => isLoggedIn(),
        'user_id' => $userId,
        'csrf' => csrfToken(),
        'message' => 'Telefon je potvrđen.',
    ]);
}

mobileApiJson(['ok' => false, 'error' => 'unknown_action', 'csrf' => csrfToken()], 400);
