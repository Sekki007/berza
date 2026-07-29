<?php

declare(strict_types=1);

function telegramEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('TELEGRAM_ENABLED', 'false')));
    if (!in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }
    return telegramBotToken() !== '';
}

function telegramBotToken(): string
{
    return trim((string)envValue('TELEGRAM_BOT_TOKEN', ''));
}

function telegramBotUsername(): string
{
    return ltrim(trim((string)envValue('TELEGRAM_BOT_USERNAME', '')), '@');
}

function telegramWebhookSecret(): string
{
    return trim((string)envValue('TELEGRAM_WEBHOOK_SECRET', ''));
}

function telegramLinkTtlMinutes(): int
{
    return max(5, (int)envValue('TELEGRAM_LINK_TTL_MIN', '15'));
}

function telegramLinkCodeLength(): int
{
    return max(6, min(10, (int)envValue('TELEGRAM_LINK_CODE_LEN', '7')));
}

function telegramApiUrl(string $method): string
{
    return 'https://api.telegram.org/bot' . telegramBotToken() . '/' . ltrim($method, '/');
}

function generateTelegramLinkCode(int $length): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function startTelegramLink(int $userId): ?array
{
    if ($userId <= 0 || !telegramEnabled()) {
        return null;
    }
    $user = findUserById($userId);
    if (!$user) {
        return null;
    }

    $expiresAt = date('Y-m-d H:i:s', time() + telegramLinkTtlMinutes() * 60);
    $code = generateTelegramLinkCode(telegramLinkCodeLength());
    for ($i = 0; $i < 6; $i++) {
        $exists = false;
        foreach (getUsers() as $row) {
            if (strcasecmp((string)($row['telegram_link_code'] ?? ''), $code) === 0) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            break;
        }
        $code = generateTelegramLinkCode(telegramLinkCodeLength());
    }

    patchUser($userId, [
        'telegram_link_code' => $code,
        'telegram_link_expires_at' => $expiresAt,
    ]);

    $botUser = telegramBotUsername();
    $botLink = $botUser !== '' ? ('https://t.me/' . $botUser . '?start=link_' . rawurlencode($code)) : '';

    return [
        'code' => $code,
        'expires_at' => $expiresAt,
        'bot_link' => $botLink,
    ];
}

function clearTelegramLinkCode(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    patchUser($userId, [
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
    ]);
}

function unlinkTelegram(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    return patchUser($userId, [
        'telegram_chat_id' => null,
        'telegram_username' => null,
        'telegram_linked_at' => null,
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
        'notify_telegram' => false,
    ]);
}

function findUserByTelegramLinkCode(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $now = time();
    foreach (getUsers() as $user) {
        if (strcasecmp((string)($user['telegram_link_code'] ?? ''), $code) !== 0) {
            continue;
        }
        $exp = strtotime((string)($user['telegram_link_expires_at'] ?? ''));
        if ($exp === false || $exp < $now) {
            continue;
        }
        return $user;
    }
    return null;
}

function linkTelegramChatToUser(int $userId, int|string $chatId, string $username = ''): bool
{
    if ($userId <= 0 || (string)$chatId === '') {
        return false;
    }
    return patchUser($userId, [
        'telegram_chat_id' => (string)$chatId,
        'telegram_username' => ltrim(trim($username), '@'),
        'telegram_linked_at' => date('Y-m-d H:i:s'),
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
        'notify_telegram' => true,
    ]);
}

function telegramSendMessage(string $chatId, string $text): bool
{
    if (!telegramEnabled() || trim($chatId) === '' || trim($text) === '') {
        return false;
    }
    if (!function_exists('curl_init')) {
        return false;
    }

    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init(telegramApiUrl('sendMessage'));
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $ok = false;
    if ($raw !== false) {
        $decoded = json_decode((string)$raw, true);
        $ok = is_array($decoded) && !empty($decoded['ok']);
    }
    curl_close($ch);
    return $ok;
}

function telegramPreferenceKeyForType(string $type): string
{
    if ($type === 'new_message') {
        return 'notify_telegram_messages';
    }
    if (in_array($type, ['ad_expiry_warning', 'ad_expired', 'saved_search_match'], true)) {
        return 'notify_telegram_alerts';
    }
    return 'notify_telegram_system';
}

function userWantsTelegramType(?array $user, string $type): bool
{
    if (!$user || empty($user['telegram_chat_id'])) {
        return false;
    }
    if (array_key_exists('notify_telegram', $user) && empty($user['notify_telegram'])) {
        return false;
    }
    $prefKey = telegramPreferenceKeyForType($type);
    if (array_key_exists($prefKey, $user)) {
        return !empty($user[$prefKey]);
    }
    return true;
}

function sendUserTelegramNotification(int $userId, string $type, string $title, string $body, string $link = ''): bool
{
    $user = findUserById($userId);
    if (!userWantsTelegramType($user, $type)) {
        return false;
    }
    $chatId = trim((string)($user['telegram_chat_id'] ?? ''));
    if ($chatId === '') {
        return false;
    }

    $parts = ['🔔 ' . trim($title), trim($body)];
    $path = trim($link);
    if ($path !== '') {
        $absolute = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : rtrim(appBaseUrl(), '/') . '/' . ltrim($path, '/');
        $parts[] = 'Otvori: ' . $absolute;
    }
    $text = trim(implode("\n\n", array_filter($parts, static fn($v) => trim((string)$v) !== '')));
    return telegramSendMessage($chatId, $text);
}

function parseTelegramLinkCodeFromText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/^\/start\s+link_([A-Za-z0-9]+)$/', $text, $m)) {
        return strtoupper((string)$m[1]);
    }
    if (preg_match('/^\/start\s+([A-Za-z0-9]+)$/', $text, $m)) {
        return strtoupper((string)$m[1]);
    }
    if (preg_match('/^[A-Za-z0-9]{6,10}$/', $text) === 1) {
        return strtoupper($text);
    }
    return '';
}

function handleTelegramWebhookUpdate(array $update): void
{
    $msg = $update['message'] ?? $update['edited_message'] ?? null;
    if (!is_array($msg)) {
        return;
    }
    $chatId = (string)($msg['chat']['id'] ?? '');
    $text = trim((string)($msg['text'] ?? ''));
    if ($chatId === '' || $text === '') {
        return;
    }
    $code = parseTelegramLinkCodeFromText($text);
    $tgUser = trim((string)($msg['from']['username'] ?? ''));

    if ($code === '') {
        telegramSendMessage(
            $chatId,
            "Pošalji kod za povezivanje iz KupiTelefon naloga.\n\nPrimer: AB12CD3"
        );
        return;
    }

    $user = findUserByTelegramLinkCode($code);
    if (!$user) {
        telegramSendMessage(
            $chatId,
            'Kod nije važeći ili je istekao. U nalogu generiši novi Telegram kod.'
        );
        return;
    }

    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0 || !linkTelegramChatToUser($uid, $chatId, $tgUser)) {
        telegramSendMessage($chatId, 'Povezivanje nije uspelo. Pokušaj ponovo.');
        return;
    }

    $name = trim((string)($user['full_name'] ?? $user['username'] ?? 'korisniče'));
    telegramSendMessage(
        $chatId,
        "Uspešno povezano sa nalogom {$name}.\nTelegram obaveštenja su uključena."
    );
}
