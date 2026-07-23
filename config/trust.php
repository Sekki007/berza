<?php

declare(strict_types=1);

function isEmailVerified(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return !empty($user['email_verified_at']) && trim((string)($user['email'] ?? '')) !== '';
}

function userAccountAgeDays(?array $user): int
{
    if (!$user) {
        return 0;
    }
    $ts = strtotime((string)($user['created_at'] ?? ''));
    if ($ts === false) {
        return 0;
    }
    return max(0, (int)floor((time() - $ts) / 86400));
}

/**
 * Admin override OR (email verified + account ≥14 days + ≥3 ratings with ≥80% positive).
 */
function isVerifiedSeller(?array $user): bool
{
    if (!$user) {
        return false;
    }
    if (!empty($user['verified_seller'])) {
        return true;
    }
    if (!isEmailVerified($user)) {
        return false;
    }
    if (userAccountAgeDays($user) < 14) {
        return false;
    }
    $summary = getSellerRatingSummary((int)($user['id'] ?? 0));
    $count = (int)($summary['count'] ?? 0);
    $positive = (int)($summary['positive'] ?? 0);
    if ($count < 3) {
        return false;
    }
    return ($positive / $count) >= 0.8;
}

function renderVerifiedBadge(?array $user): string
{
    if (!isVerifiedSeller($user)) {
        return '';
    }
    return '<span class="verified-badge" title="Proveren prodavac">✓ Proveren</span>';
}

function patchUser(int $userId, array $fields): bool
{
    if ($userId <= 0 || $fields === []) {
        return false;
    }
    $users = getUsers();
    foreach ($users as &$user) {
        if ((int)($user['id'] ?? 0) !== $userId) {
            continue;
        }
        foreach ($fields as $key => $value) {
            $user[$key] = $value;
        }
        writeJsonFile('users.json', $users);
        if (isLoggedIn() && (int)(currentUser()['id'] ?? 0) === $userId) {
            foreach ($fields as $key => $value) {
                $_SESSION['user'][$key] = $value;
            }
            if (isset($fields['full_name'])) {
                $_SESSION['user']['full_name'] = $fields['full_name'];
            }
        }
        return true;
    }
    return false;
}

function setVerifiedSellerFlag(int $userId, bool $verified): bool
{
    return patchUser($userId, [
        'verified_seller' => $verified,
        'verified_seller_at' => $verified ? date('Y-m-d H:i:s') : null,
    ]);
}

function sendEmailVerification(int $userId): bool
{
    $user = findUserById($userId);
    if (!$user) {
        return false;
    }
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (isEmailVerified($user)) {
        return true;
    }

    $token = bin2hex(random_bytes(24));
    patchUser($userId, [
        'email_verify_token' => $token,
        'email_verify_sent_at' => date('Y-m-d H:i:s'),
    ]);

    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $link = $host . '/verify-email.php?token=' . urlencode($token);

    return sendRawEmail(
        $email,
        'TelefonBerza: potvrdi email',
        "Zdravo,\n\nPotvrdi svoj email klikom na link:\n{$link}\n\nAko nisi tražio/la ovo, ignoriši poruku.\n\nTelefonBerza"
    );
}

function confirmEmailVerification(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    foreach (getUsers() as $user) {
        if ((string)($user['email_verify_token'] ?? '') !== $token) {
            continue;
        }
        $userId = (int)($user['id'] ?? 0);
        patchUser($userId, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verify_token' => null,
            'email_verify_sent_at' => null,
        ]);
        return findUserById($userId);
    }
    return null;
}
