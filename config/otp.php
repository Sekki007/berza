<?php

declare(strict_types=1);

const OTP_TTL_SECONDS = 600;
const OTP_MAX_ATTEMPTS = 5;

function isPhoneVerified(?array $user): bool
{
    if (!$user) {
        return false;
    }
    $phone = normalizePhoneRs((string)($user['phone'] ?? ''));
    return $phone !== null
        && isAllowedSmsPhone($phone)
        && !empty($user['phone_verified_at']);
}

function generateOtpCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * @param 'phone_verify'|'password_reset' $purpose
 * @return array{ok: bool, error?: string, code_sent?: bool}
 */
function sendUserOtp(int $userId, string $purpose, ?string $phoneOverride = null): array
{
    if (!in_array($purpose, ['phone_verify', 'password_reset'], true)) {
        return ['ok' => false, 'error' => 'Nepoznata namena koda.'];
    }

    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }

    $phoneRaw = $phoneOverride !== null ? $phoneOverride : (string)($user['phone'] ?? '');
    $phone = normalizePhoneRs($phoneRaw);
    if ($phone === null || !isAllowedSmsPhone($phone)) {
        return ['ok' => false, 'error' => 'Unesi validan srpski mobilni broj (npr. 06x xxx xxxx).'];
    }

    if ($purpose === 'password_reset' && !isPhoneVerified($user)) {
        return ['ok' => false, 'error' => 'Telefon na nalogu nije verifikovan.'];
    }

    $code = generateOtpCode();
    $now = date('Y-m-d H:i:s');
    $fields = [
        'otp_purpose' => $purpose,
        'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
        'otp_sent_at' => $now,
        'otp_attempts' => 0,
        'phone' => $phone,
    ];

    if ($purpose === 'phone_verify') {
        $oldPhone = normalizePhoneRs((string)($user['phone'] ?? ''));
        if ($oldPhone !== $phone || empty($user['phone_verified_at'])) {
            $fields['phone_verified_at'] = null;
        }
    }

    if (!patchUser($userId, $fields)) {
        return ['ok' => false, 'error' => 'Nije moguće sačuvati kod.'];
    }

    $sent = sendOtpSms($phone, $code, $purpose);
    if (empty($sent['ok'])) {
        return ['ok' => false, 'error' => (string)($sent['error'] ?? 'SMS nije poslat.')];
    }

    return ['ok' => true, 'code_sent' => true];
}

/**
 * @param 'phone_verify'|'password_reset' $purpose
 * @return array{ok: bool, error?: string}
 */
function verifyUserOtp(int $userId, string $purpose, string $code): array
{
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => 'Unesi 6-cifreni kod.'];
    }

    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }

    if ((string)($user['otp_purpose'] ?? '') !== $purpose) {
        return ['ok' => false, 'error' => 'Nema važećeg koda. Zatraži novi SMS.'];
    }

    $hash = (string)($user['otp_hash'] ?? '');
    $sentAt = strtotime((string)($user['otp_sent_at'] ?? ''));
    if ($hash === '' || $sentAt === false) {
        return ['ok' => false, 'error' => 'Nema važećeg koda. Zatraži novi SMS.'];
    }

    if ((time() - $sentAt) > OTP_TTL_SECONDS) {
        clearUserOtp($userId);
        return ['ok' => false, 'error' => 'Kod je istekao. Zatraži novi SMS.'];
    }

    $attempts = (int)($user['otp_attempts'] ?? 0);
    if ($attempts >= OTP_MAX_ATTEMPTS) {
        clearUserOtp($userId);
        return ['ok' => false, 'error' => 'Previše pokušaja. Zatraži novi kod.'];
    }

    if (!password_verify($code, $hash)) {
        patchUser($userId, ['otp_attempts' => $attempts + 1]);
        $left = OTP_MAX_ATTEMPTS - ($attempts + 1);
        return [
            'ok' => false,
            'error' => $left > 0
                ? ('Pogrešan kod. Preostalo pokušaja: ' . $left . '.')
                : 'Previše pokušaja. Zatraži novi kod.',
        ];
    }

    if ($purpose === 'phone_verify') {
        patchUser($userId, [
            'phone_verified_at' => date('Y-m-d H:i:s'),
            'otp_purpose' => null,
            'otp_hash' => null,
            'otp_sent_at' => null,
            'otp_attempts' => 0,
        ]);
    } else {
        // password_reset: keep purpose until password is changed; mark verified via session
        patchUser($userId, [
            'otp_attempts' => 0,
            'otp_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    return ['ok' => true];
}

function clearUserOtp(int $userId): void
{
    patchUser($userId, [
        'otp_purpose' => null,
        'otp_hash' => null,
        'otp_sent_at' => null,
        'otp_attempts' => 0,
        'otp_verified_at' => null,
    ]);
}

/**
 * After OTP verified for password_reset, allow setting new password within TTL.
 */
function canResetPasswordWithOtp(int $userId): bool
{
    $user = findUserById($userId);
    if (!$user) {
        return false;
    }
    if ((string)($user['otp_purpose'] ?? '') !== 'password_reset') {
        return false;
    }
    $verifiedAt = strtotime((string)($user['otp_verified_at'] ?? ''));
    if ($verifiedAt === false) {
        return false;
    }
    return (time() - $verifiedAt) <= OTP_TTL_SECONDS;
}

function findUserByPhone(string $rawPhone): ?array
{
    $normalized = normalizePhoneRs($rawPhone);
    if ($normalized === null) {
        return null;
    }
    foreach (getUsers() as $user) {
        $phone = normalizePhoneRs((string)($user['phone'] ?? ''));
        if ($phone === $normalized) {
            return $user;
        }
    }
    return null;
}
