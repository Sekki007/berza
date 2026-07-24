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

function normalizePib(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === null || !preg_match('/^\d{9}$/', $digits)) {
        return null;
    }
    return $digits;
}

/**
 * @return 'private'|'business'
 */
function userAccountType(?array $user): string
{
    $type = (string)($user['account_type'] ?? 'private');
    return $type === 'business' ? 'business' : 'private';
}

/**
 * @return 'shop'|'service'|''
 */
function userBusinessKind(?array $user): string
{
    $kind = (string)($user['business_kind'] ?? '');
    return in_array($kind, ['shop', 'service'], true) ? $kind : '';
}

function businessKindLabel(string $kind): string
{
    return match ($kind) {
        'shop' => 'Prodavnica',
        'service' => 'Servis',
        default => 'Firma',
    };
}

/**
 * @return 'none'|'pending'|'approved'|'rejected'
 */
function userBusinessStatus(?array $user): string
{
    if (!$user || userAccountType($user) !== 'business') {
        return 'none';
    }
    $status = (string)($user['business_status'] ?? 'none');
    return in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'none';
}

function isBusinessVerified(?array $user): bool
{
    return userBusinessStatus($user) === 'approved'
        && normalizePib((string)($user['pib'] ?? '')) !== null
        && userBusinessKind($user) !== '';
}

function renderBusinessBadge(?array $user): string
{
    if (!isBusinessVerified($user)) {
        return '';
    }
    $kind = userBusinessKind($user);
    $label = businessKindLabel($kind);
    $title = $label . ' · PIB ' . h((string)($user['pib'] ?? ''));
    $class = $kind === 'service' ? 'business-badge business-badge-service' : 'business-badge business-badge-shop';
    return '<span class="' . $class . '" title="' . $title . '">' . h($label) . '</span>';
}

function renderSellerBadges(?array $user): string
{
    return renderBusinessBadge($user) . renderVerifiedBadge($user);
}

/**
 * @return array{ok: bool, error?: string}
 */
function requestBusinessVerification(int $userId): array
{
    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }
    if (!isPhoneVerified($user)) {
        return ['ok' => false, 'error' => 'Prvo potvrdi broj telefona.'];
    }
    if (userAccountType($user) !== 'business') {
        return ['ok' => false, 'error' => 'Izaberi tip naloga Prodavnica/Servis.'];
    }
    $kind = userBusinessKind($user);
    if ($kind === '') {
        return ['ok' => false, 'error' => 'Izaberi da li si prodavnica ili servis.'];
    }
    if (trim((string)($user['shop_name'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'Unesi naziv firme / izloga.'];
    }
    $pib = normalizePib((string)($user['pib'] ?? ''));
    if ($pib === null) {
        return ['ok' => false, 'error' => 'PIB mora imati tačno 9 cifara.'];
    }
    if (userBusinessStatus($user) === 'approved') {
        return ['ok' => true];
    }

    $ok = patchUser($userId, [
        'account_type' => 'business',
        'business_kind' => $kind,
        'pib' => $pib,
        'business_status' => 'pending',
        'business_requested_at' => date('Y-m-d H:i:s'),
        'business_verified_at' => null,
        'business_reject_reason' => null,
    ]);

    return $ok
        ? ['ok' => true]
        : ['ok' => false, 'error' => 'Zahtev nije sačuvan.'];
}

function setBusinessVerification(int $userId, bool $approve, string $rejectReason = ''): bool
{
    $user = findUserById($userId);
    if (!$user || userAccountType($user) !== 'business') {
        return false;
    }
    if ($approve) {
        $pib = normalizePib((string)($user['pib'] ?? ''));
        if ($pib === null || userBusinessKind($user) === '') {
            return false;
        }
        return patchUser($userId, [
            'business_status' => 'approved',
            'business_verified_at' => date('Y-m-d H:i:s'),
            'business_reject_reason' => null,
            'pib' => $pib,
        ]);
    }

    return patchUser($userId, [
        'business_status' => 'rejected',
        'business_verified_at' => null,
        'business_reject_reason' => trim($rejectReason) !== '' ? trim($rejectReason) : 'Zahtev odbijen.',
    ]);
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
