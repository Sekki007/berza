<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Normalize Serbian mobile input to E.164 (+3816…).
 * Accepts: 06x…, 6x…, 3816…, +3816…
 */
function normalizePhoneRs(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === null || $digits === '') {
        return null;
    }

    if (str_starts_with($digits, '381')) {
        $digits = substr($digits, 3);
    } elseif (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    if (!preg_match('/^6\d{7,8}$/', $digits)) {
        return null;
    }

    return '+381' . $digits;
}

function isAllowedSmsPhone(string $e164): bool
{
    return (bool)preg_match('/^\+3816\d{7,8}$/', $e164);
}

function smsEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('SMS_ENABLED', 'false')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function smsClientIp(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return $ip !== '' ? $ip : '0.0.0.0';
}

/**
 * @return array{ok: bool, error?: string}
 */
function smsRateLimitCheck(string $phoneE164, string $ip): array
{
    $now = time();
    $limits = readJsonFile('sms_rate_limits.json');
    if (!is_array($limits)) {
        $limits = [];
    }

    $phoneKey = 'phone:' . $phoneE164;
    $ipKey = 'ip:' . $ip;

    $phoneHits = array_values(array_filter(
        (array)($limits[$phoneKey] ?? []),
        static fn($ts) => is_int($ts) && ($now - $ts) < 3600
    ));
    $ipHits = array_values(array_filter(
        (array)($limits[$ipKey] ?? []),
        static fn($ts) => is_int($ts) && ($now - $ts) < 3600
    ));

    if ($phoneHits !== [] && ($now - max($phoneHits)) < 60) {
        return ['ok' => false, 'error' => 'Sačekaj minut pre ponovnog slanja SMS-a.'];
    }
    if (count($phoneHits) >= 5) {
        return ['ok' => false, 'error' => 'Previše SMS-ova na ovaj broj. Pokušaj kasnije.'];
    }
    if (count($ipHits) >= 10) {
        return ['ok' => false, 'error' => 'Previše zahteva sa tvoje adrese. Pokušaj kasnije.'];
    }

    return ['ok' => true];
}

function smsRateLimitRecord(string $phoneE164, string $ip): void
{
    $now = time();
    $limits = readJsonFile('sms_rate_limits.json');
    if (!is_array($limits)) {
        $limits = [];
    }

    $phoneKey = 'phone:' . $phoneE164;
    $ipKey = 'ip:' . $ip;

    $prune = static function (array $hits) use ($now): array {
        return array_values(array_filter(
            $hits,
            static fn($ts) => is_int($ts) && ($now - $ts) < 3600
        ));
    };

    $phoneHits = $prune((array)($limits[$phoneKey] ?? []));
    $ipHits = $prune((array)($limits[$ipKey] ?? []));
    $phoneHits[] = $now;
    $ipHits[] = $now;

    $limits[$phoneKey] = $phoneHits;
    $limits[$ipKey] = $ipHits;

    // Drop stale keys
    foreach ($limits as $key => $hits) {
        if (!is_array($hits) || $hits === []) {
            unset($limits[$key]);
            continue;
        }
        $fresh = $prune($hits);
        if ($fresh === []) {
            unset($limits[$key]);
        } else {
            $limits[$key] = $fresh;
        }
    }

    writeJsonFile('sms_rate_limits.json', $limits);
}

/**
 * Low-level send — only +3816 mobiles, fixed body from callers.
 *
 * @return array{ok: bool, error?: string, http_code?: int}
 */
function sendSmsRaw(string $phoneE164, string $text): array
{
    if (!smsEnabled()) {
        return ['ok' => false, 'error' => 'SMS nije uključen.'];
    }

    if (!isAllowedSmsPhone($phoneE164)) {
        return ['ok' => false, 'error' => 'SMS se šalje samo na srpske mobilne brojeve (+3816…).'];
    }

    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 160) {
        return ['ok' => false, 'error' => 'Neispravan sadržaj SMS-a.'];
    }

    $ip = smsClientIp();
    $rate = smsRateLimitCheck($phoneE164, $ip);
    if (empty($rate['ok'])) {
        return ['ok' => false, 'error' => (string)($rate['error'] ?? 'Rate limit.')];
    }

    $url = trim((string)envValue('SMS_GATEWAY_URL', ''));
    $user = trim((string)envValue('SMS_GATEWAY_USER', ''));
    $pass = (string)envValue('SMS_GATEWAY_PASS', '');

    if ($url === '' || $user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'SMS gateway nije konfigurisan.'];
    }

    $payload = json_encode([
        'phoneNumbers' => [$phoneE164],
        'textMessage' => ['text' => $text],
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['ok' => false, 'error' => 'Greška pri pripremi SMS-a.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'SMS klijent nije dostupan.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => 'SMS gateway nije dostupan.'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'SMS nije poslat (HTTP ' . $httpCode . ').', 'http_code' => $httpCode];
    }

    smsRateLimitRecord($phoneE164, $ip);

    return ['ok' => true, 'http_code' => $httpCode];
}

/**
 * Build OTP SMS body from admin template. Requires {code}. Max 160 chars.
 *
 * @param 'phone_verify'|'password_reset' $purpose
 */
function buildOtpSmsText(string $purpose, string $code): string
{
    $defaults = defaultSiteSettings();
    $settings = function_exists('siteSettings') ? siteSettings() : $defaults;

    $key = $purpose === 'password_reset' ? 'sms_template_password_reset' : 'sms_template_phone_verify';
    $fallback = (string)($defaults[$key] ?? 'TelefonBerza kod: {code}. Vazi 10 min.');
    $tpl = trim((string)($settings[$key] ?? $fallback));

    if ($tpl === '' || !str_contains($tpl, '{code}')) {
        $tpl = $fallback;
    }

    $text = str_replace('{code}', $code, $tpl);
    $text = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $text) ?? $text;
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

    if (mb_strlen($text) > 160) {
        $text = mb_substr($text, 0, 160);
    }

    return $text;
}

/**
 * OTP SMS from admin template (or default). Only {code} is substituted.
 *
 * @param 'phone_verify'|'password_reset' $purpose
 * @return array{ok: bool, error?: string}
 */
function sendOtpSms(string $phoneE164, string $code, string $purpose = 'phone_verify'): array
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => 'Neispravan kod.'];
    }

    if (!in_array($purpose, ['phone_verify', 'password_reset'], true)) {
        $purpose = 'phone_verify';
    }

    return sendSmsRaw($phoneE164, buildOtpSmsText($purpose, $code));
}
