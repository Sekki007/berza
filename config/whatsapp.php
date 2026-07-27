<?php

declare(strict_types=1);

/**
 * TextMeBot WhatsApp API — obaveštenja (poruke, istek oglasa, alerti).
 * https://textmebot.com/
 */

function whatsappNotificationsEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('WHATSAPP_ENABLED', 'false')));
    if (!in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }
    if (trim((string)envValue('WHATSAPP_API_KEY', '')) === '') {
        return false;
    }
    $settings = siteSettings();
    if (array_key_exists('enable_whatsapp_notifications', $settings)) {
        return !empty($settings['enable_whatsapp_notifications']);
    }
    return true;
}

function userWantsWhatsappNotifications(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return !empty($user['notify_whatsapp']);
}

/** Tipovi koje šaljemo na WhatsApp (prvi korak). */
function whatsappNotifiableTypes(): array
{
    return [
        'new_message',
        'ad_expiry_warning',
        'ad_expired',
        'saved_search_match',
    ];
}

function whatsappRecipientDigits(string $e164OrRaw): ?string
{
    $normalized = normalizePhoneRs($e164OrRaw);
    if ($normalized === null) {
        return null;
    }
    // TextMeBot: 3816… bez +
    return ltrim($normalized, '+');
}

/**
 * @return array{ok:bool,error?:string,http?:int,raw?:string}
 */
function sendWhatsappText(string $recipientDigits, string $text): array
{
    $text = trim($text);
    if ($text === '' || $recipientDigits === '') {
        return ['ok' => false, 'error' => 'Prazan broj ili poruka.'];
    }
    $apiKey = trim((string)envValue('WHATSAPP_API_KEY', ''));
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'WHATSAPP_API_KEY nije podešen.'];
    }
    $endpoint = trim((string)envValue('WHATSAPP_API_URL', 'https://api.textmebot.com/send.php'));
    if ($endpoint === '') {
        $endpoint = 'https://api.textmebot.com/send.php';
    }

    $url = $endpoint . '?' . http_build_query([
        'recipient' => $recipientDigits,
        'apikey' => $apiKey,
        'text' => $text,
        'json' => 'yes',
    ]);

    if (!function_exists('curl_init')) {
        $raw = @file_get_contents($url);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'Slanje nije uspelo (file_get_contents).'];
        }
        return ['ok' => true, 'raw' => $raw];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'cURL greška', 'http' => $http];
    }

    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        $status = strtolower((string)($decoded['status'] ?? $decoded['result'] ?? ''));
        if ($status !== '' && in_array($status, ['error', 'fail', 'failed'], true)) {
            return [
                'ok' => false,
                'error' => (string)($decoded['message'] ?? $decoded['error'] ?? 'API greška'),
                'http' => $http,
                'raw' => (string)$raw,
            ];
        }
    }

    if ($http >= 400) {
        return ['ok' => false, 'error' => 'HTTP ' . $http, 'http' => $http, 'raw' => (string)$raw];
    }

    return ['ok' => true, 'http' => $http, 'raw' => (string)$raw];
}

function whatsappThrottleOk(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $state = readJsonFile('whatsapp_send_state.json');
    if (!is_array($state)) {
        $state = [];
    }
    $last = (int)($state['users'][(string)$userId] ?? 0);
    $minGap = max(5, (int)envValue('WHATSAPP_MIN_GAP_SEC', '8'));
    return $last <= 0 || (time() - $last) >= $minGap;
}

function whatsappThrottleRecord(int $userId): void
{
    $state = readJsonFile('whatsapp_send_state.json');
    if (!is_array($state)) {
        $state = [];
    }
    if (!isset($state['users']) || !is_array($state['users'])) {
        $state['users'] = [];
    }
    $state['users'][(string)$userId] = time();
    // keep last ~500
    if (count($state['users']) > 500) {
        asort($state['users']);
        $state['users'] = array_slice($state['users'], -400, null, true);
    }
    writeJsonFile('whatsapp_send_state.json', $state);
}

/**
 * Pošalji WA obaveštenje ako je tip dozvoljen, korisnik opt-in, telefon verifikovan.
 */
function maybeSendWhatsappNotification(int $userId, string $type, string $title, string $body, string $link = ''): bool
{
    if (!whatsappNotificationsEnabled()) {
        return false;
    }
    if (!in_array($type, whatsappNotifiableTypes(), true)) {
        return false;
    }
    $user = findUserById($userId);
    if (!$user || !userWantsWhatsappNotifications($user)) {
        return false;
    }
    if (!isPhoneVerified($user)) {
        return false;
    }
    $digits = whatsappRecipientDigits((string)($user['phone'] ?? ''));
    if ($digits === null) {
        return false;
    }
    if (!whatsappThrottleOk($userId)) {
        return false;
    }

    $text = "TelefonBerza\n{$title}\n{$body}";
    if ($link !== '') {
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!str_starts_with($link, 'http')) {
            $text .= "\n" . $host . $link;
        } else {
            $text .= "\n" . $link;
        }
    }

    // WhatsApp poruke: drži razumno kratko
    if (mb_strlen($text) > 900) {
        $text = mb_substr($text, 0, 897) . '…';
    }

    $result = sendWhatsappText($digits, $text);
    if (!empty($result['ok'])) {
        whatsappThrottleRecord($userId);
        return true;
    }
    return false;
}
