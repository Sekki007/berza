<?php

declare(strict_types=1);

const OTP_TTL_SECONDS = 600;
const OTP_MAX_ATTEMPTS = 5;
/** Posle ovoliko uspešnih SMS-ova ponuda „Pošalji na email”. */
const OTP_SMS_BEFORE_EMAIL = 2;

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

function otpFlowSessionKey(int $userId, string $purpose): string
{
    return 'otp_sms_sends_' . $userId . '_' . $purpose;
}

function getOtpSmsSendCount(int $userId, string $purpose): int
{
    return max(0, (int)($_SESSION[otpFlowSessionKey($userId, $purpose)] ?? 0));
}

function bumpOtpSmsSendCount(int $userId, string $purpose): void
{
    $key = otpFlowSessionKey($userId, $purpose);
    $_SESSION[$key] = getOtpSmsSendCount($userId, $purpose) + 1;
}

function resetOtpSmsSendCount(int $userId, string $purpose): void
{
    unset($_SESSION[otpFlowSessionKey($userId, $purpose)]);
}

function userHasValidEmail(?array $user): bool
{
    $email = trim((string)($user['email'] ?? ''));
    return $email !== '' && (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function canOfferOtpEmail(int $userId, string $purpose): bool
{
    if (!function_exists('mailIsConfigured') || !mailIsConfigured()) {
        return false;
    }
    return getOtpSmsSendCount($userId, $purpose) >= OTP_SMS_BEFORE_EMAIL;
}

/**
 * @param 'phone_verify'|'password_reset' $purpose
 * @param 'sms'|'email' $channel
 * @return array{ok: bool, error?: string, code_sent?: bool, channel?: string}
 */
function sendUserOtp(int $userId, string $purpose, ?string $phoneOverride = null, string $channel = 'sms', ?string $emailOverride = null): array
{
    if (!in_array($purpose, ['phone_verify', 'password_reset'], true)) {
        return ['ok' => false, 'error' => 'Nepoznata namena koda.'];
    }
    $channel = $channel === 'email' ? 'email' : 'sms';

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

    $emailForSend = '';
    if ($channel === 'email') {
        if (!canOfferOtpEmail($userId, $purpose)) {
            return [
                'ok' => false,
                'error' => 'Email kod je dostupan tek posle ' . OTP_SMS_BEFORE_EMAIL . ' SMS pokušaja.',
            ];
        }
        if (!function_exists('mailIsConfigured') || !mailIsConfigured()) {
            return ['ok' => false, 'error' => 'Email slanje nije podešeno na serveru.'];
        }

        $emailForSend = trim((string)($emailOverride ?? ''));
        if ($emailForSend === '') {
            $emailForSend = trim((string)($user['email'] ?? ''));
        }
        if ($emailForSend === '' || !filter_var($emailForSend, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Unesi validan email da pošaljemo kod.'];
        }

        // Za reset lozinke: samo email koji je već na nalogu (anti-hijack)
        if ($purpose === 'password_reset') {
            $accountEmail = trim((string)($user['email'] ?? ''));
            if ($accountEmail === '' || !filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Na nalogu nema sačuvanog emaila. Dodaj email u profilu ili kontaktiraj podršku.'];
            }
            if (strcasecmp($accountEmail, $emailForSend) !== 0) {
                return ['ok' => false, 'error' => 'Kod može ići samo na email sa naloga.'];
            }
            $emailForSend = $accountEmail;
        }
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
        if ($channel === 'email' && $emailForSend !== '') {
            $fields['email'] = $emailForSend;
        }
    }

    if (!patchUser($userId, $fields)) {
        return ['ok' => false, 'error' => 'Nije moguće sačuvati kod.'];
    }

    if ($channel === 'email') {
        $tplKey = $purpose === 'password_reset' ? 'otp_password_reset' : 'otp_phone_verify';
        $name = trim((string)($user['full_name'] ?? $user['username'] ?? ''));
        $rendered = renderEmailTemplate($tplKey, [
            'code' => $code,
            'name' => $name,
        ]);
        $sent = sendRawEmail(
            $emailForSend,
            $rendered['subject'] !== '' ? $rendered['subject'] : ('KupiTelefon: kod'),
            $rendered['body'] !== '' ? $rendered['body'] : ("Kod: {$code}"),
            $name !== '' ? $name : null
        );
        if (!$sent) {
            return ['ok' => false, 'error' => 'Email nije poslat. Proveri SMTP podešavanja.'];
        }
        return ['ok' => true, 'code_sent' => true, 'channel' => 'email'];
    }

    // Brojimo pokušaj SMS-a i kad gateway padne — da email fallback ipak postane dostupan
    bumpOtpSmsSendCount($userId, $purpose);

    $sent = sendOtpSms($phone, $code, $purpose);
    if (empty($sent['ok'])) {
        return ['ok' => false, 'error' => (string)($sent['error'] ?? 'SMS nije poslat.')];
    }

    return ['ok' => true, 'code_sent' => true, 'channel' => 'sms'];
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
        return ['ok' => false, 'error' => 'Nema važećeg koda. Zatraži novi.'];
    }

    $hash = (string)($user['otp_hash'] ?? '');
    $sentAt = strtotime((string)($user['otp_sent_at'] ?? ''));
    if ($hash === '' || $sentAt === false) {
        return ['ok' => false, 'error' => 'Nema važećeg koda. Zatraži novi.'];
    }

    if ((time() - $sentAt) > OTP_TTL_SECONDS) {
        clearUserOtp($userId);
        return ['ok' => false, 'error' => 'Kod je istekao. Zatraži novi.'];
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
        resetOtpSmsSendCount($userId, $purpose);
    } else {
        patchUser($userId, [
            'otp_attempts' => 0,
            'otp_verified_at' => date('Y-m-d H:i:s'),
        ]);
        resetOtpSmsSendCount($userId, $purpose);
    }

    return ['ok' => true];
}

function clearUserOtp(int $userId): void
{
    $user = findUserById($userId);
    $purpose = (string)($user['otp_purpose'] ?? '');
    patchUser($userId, [
        'otp_purpose' => null,
        'otp_hash' => null,
        'otp_sent_at' => null,
        'otp_attempts' => 0,
        'otp_verified_at' => null,
    ]);
    if ($purpose !== '' && $userId > 0) {
        resetOtpSmsSendCount($userId, $purpose);
    }
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
