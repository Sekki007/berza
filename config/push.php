<?php

declare(strict_types=1);

/**
 * FCM push notifikacije (Android app).
 *
 * Env:
 *   FCM_ENABLED=true
 *   FCM_PROJECT_ID=your-project-id
 *   FCM_SERVICE_ACCOUNT_JSON=/absolute/path/to/service-account.json
 *   # ili inline JSON (jedan red):
 *   FCM_SERVICE_ACCOUNT_JSON={"type":"service_account",...}
 */

function pushEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('FCM_ENABLED', 'false')));
    if (!in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }
    return fcmProjectId() !== '' && fcmServiceAccount() !== null;
}

function fcmProjectId(): string
{
    return trim((string)envValue('FCM_PROJECT_ID', ''));
}

function fcmServiceAccount(): ?array
{
    static $cached = false;
    static $account = null;
    if ($cached) {
        return $account;
    }
    $cached = true;
    $raw = trim((string)envValue('FCM_SERVICE_ACCOUNT_JSON', ''));
    if ($raw === '') {
        return null;
    }
    if (is_file($raw) && is_readable($raw)) {
        $raw = (string)file_get_contents($raw);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['private_key']) || empty($decoded['client_email'])) {
        return null;
    }
    $account = $decoded;
    return $account;
}

function ensurePushTokensFile(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('push_tokens.json'))) {
        writeJsonFile('push_tokens.json', []);
    }
}

function getAllPushTokens(): array
{
    ensurePushTokensFile();
    $items = readJsonFile('push_tokens.json');
    return is_array($items) ? $items : [];
}

function saveAllPushTokens(array $items): void
{
    ensurePushTokensFile();
    writeJsonFile('push_tokens.json', array_values($items));
}

function getPushTokensForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    return array_values(array_filter(
        getAllPushTokens(),
        static fn($row) => (int)($row['user_id'] ?? 0) === $userId && trim((string)($row['token'] ?? '')) !== ''
    ));
}

function upsertPushToken(int $userId, string $token, string $platform = 'android'): bool
{
    $userId = (int)$userId;
    $token = trim($token);
    $platform = strtolower(trim($platform)) ?: 'android';
    if ($userId <= 0 || $token === '') {
        return false;
    }

    $items = getAllPushTokens();
    $now = date('Y-m-d H:i:s');
    $found = false;
    foreach ($items as &$row) {
        if (hash_equals((string)($row['token'] ?? ''), $token)) {
            $row['user_id'] = $userId;
            $row['platform'] = $platform;
            $row['updated_at'] = $now;
            if (empty($row['created_at'])) {
                $row['created_at'] = $now;
            }
            $found = true;
            break;
        }
    }
    unset($row);

    if (!$found) {
        $items[] = [
            'user_id' => $userId,
            'token' => $token,
            'platform' => $platform,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    // Jedan token = jedan user; očisti iste tokene sa drugih naloga (već urađeno upsert-om).
    // Ograniči na max 5 tokena po useru (stari telefoni).
    $byUser = [];
    $out = [];
    foreach ($items as $row) {
        $uid = (int)($row['user_id'] ?? 0);
        $tok = trim((string)($row['token'] ?? ''));
        if ($uid <= 0 || $tok === '') {
            continue;
        }
        $byUser[$uid] = $byUser[$uid] ?? [];
        $byUser[$uid][] = $row;
    }
    foreach ($byUser as $uid => $rows) {
        usort($rows, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        foreach (array_slice($rows, 0, 5) as $row) {
            $out[] = $row;
        }
    }

    saveAllPushTokens($out);
    return true;
}

function deletePushToken(string $token, ?int $userId = null): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }
    $items = getAllPushTokens();
    $filtered = array_values(array_filter($items, static function ($row) use ($token, $userId) {
        if (!hash_equals((string)($row['token'] ?? ''), $token)) {
            return true;
        }
        if ($userId !== null && (int)($row['user_id'] ?? 0) !== $userId) {
            return true;
        }
        return false;
    }));
    if (count($filtered) === count($items)) {
        return false;
    }
    saveAllPushTokens($filtered);
    return true;
}

function deletePushTokensForUser(int $userId): int
{
    $items = getAllPushTokens();
    $filtered = array_values(array_filter($items, static fn($row) => (int)($row['user_id'] ?? 0) !== $userId));
    $removed = count($items) - count($filtered);
    if ($removed > 0) {
        saveAllPushTokens($filtered);
    }
    return $removed;
}

function userWantsPushNotifications(?array $user): bool
{
    if (!$user) {
        return false;
    }
    if (array_key_exists('notify_push', $user)) {
        return !empty($user['notify_push']);
    }
    return true; // default ON ako ima token
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function fcmAccessToken(): ?string
{
    static $memo = null;
    static $memoExp = 0;
    if (is_string($memo) && $memo !== '' && time() < $memoExp - 60) {
        return $memo;
    }

    $account = fcmServiceAccount();
    if (!$account) {
        return null;
    }

    $cacheFile = dataPath('fcm_token_cache.json');
    if (is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && !empty($cached['access_token'])
            && (int)($cached['expires_at'] ?? 0) > time() + 60
        ) {
            $memo = (string)$cached['access_token'];
            $memoExp = (int)$cached['expires_at'];
            return $memo;
        }
    }

    $now = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '');
    $claim = base64UrlEncode(json_encode([
        'iss' => (string)$account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $unsigned = $header . '.' . $claim;

    $key = openssl_pkey_get_private((string)$account['private_key']);
    if ($key === false) {
        return null;
    }
    $signature = '';
    $ok = openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        return null;
    }
    $jwt = $unsigned . '.' . base64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($resp) || $code < 200 || $code >= 300) {
        return null;
    }
    $data = json_decode($resp, true);
    $token = trim((string)($data['access_token'] ?? ''));
    $expiresIn = (int)($data['expires_in'] ?? 3600);
    if ($token === '') {
        return null;
    }
    $expiresAt = time() + max(60, $expiresIn);
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $token,
        'expires_at' => $expiresAt,
    ], JSON_UNESCAPED_UNICODE));
    $memo = $token;
    $memoExp = $expiresAt;
    return $memo;
}

function sendFcmToToken(string $token, string $title, string $body, string $link = '', array $data = [], int $badgeCount = 0): array
{
    $token = trim($token);
    if ($token === '' || !pushEnabled()) {
        return ['ok' => false, 'error' => 'disabled'];
    }
    $access = fcmAccessToken();
    if ($access === null) {
        return ['ok' => false, 'error' => 'no_access_token'];
    }

    $urlPath = $link;
    if ($urlPath !== '' && !preg_match('#^https?://#i', $urlPath)) {
        $urlPath = absoluteUrl($urlPath);
    }
    if ($urlPath === '') {
        $urlPath = absoluteUrl('/poruke.php');
    }

    $badgeCount = max(0, $badgeCount);

    $dataPayload = array_merge([
        'title' => $title,
        'body' => $body,
        'link' => $urlPath,
        'click_action' => $urlPath,
        'badge' => (string)$badgeCount,
    ], $data);
    // FCM data values must be strings
    foreach ($dataPayload as $k => $v) {
        $dataPayload[$k] = (string)$v;
    }

    $androidNotification = [
        'sound' => 'default',
        'click_action' => 'FCM_PLUGIN_ACTIVITY',
        'channel_id' => 'kupitelefon_messages',
        'default_sound' => true,
        'default_vibrate_timings' => true,
    ];
    if ($badgeCount > 0) {
        $androidNotification['notification_count'] = $badgeCount;
    }

    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $dataPayload,
            'android' => [
                'priority' => 'HIGH',
                'notification' => $androidNotification,
            ],
        ],
    ];

    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode(fcmProjectId()) . '/messages:send';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = is_string($resp) ? json_decode($resp, true) : null;
    $ok = $code >= 200 && $code < 300;
    $err = '';
    if (!$ok) {
        $err = (string)($decoded['error']['status'] ?? $decoded['error']['message'] ?? ('http_' . $code));
        // Invalid token → obriši
        if (in_array($err, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true)
            || str_contains(strtolower((string)($decoded['error']['message'] ?? '')), 'not a valid fcm')
            || str_contains(strtolower((string)($decoded['error']['message'] ?? '')), 'requested entity was not found')
        ) {
            deletePushToken($token);
        }
    }

    return ['ok' => $ok, 'http' => $code, 'error' => $err, 'response' => $decoded];
}

function pushBadgeCountForUser(int $userId): int
{
    // Badge na ikonici = nepročitane poruke (ostaje dok se ne pročitaju)
    return function_exists('getUnreadMessageCount') ? max(0, getUnreadMessageCount($userId)) : 0;
}

function sendPushToUser(int $userId, string $type, string $title, string $body, string $link = ''): int
{
    if ($userId <= 0 || !pushEnabled()) {
        return 0;
    }
    $user = findUserById($userId);
    if (!$user || !userWantsPushNotifications($user)) {
        return 0;
    }

    $tokens = getPushTokensForUser($userId);
    if (!$tokens) {
        return 0;
    }

    $badge = pushBadgeCountForUser($userId);
    if ($badge < 1) {
        $badge = 1; // bar 1 kad stiže nova notifikacija
    }

    $sent = 0;
    foreach ($tokens as $row) {
        $token = trim((string)($row['token'] ?? ''));
        if ($token === '') {
            continue;
        }
        $result = sendFcmToToken($token, $title, $body, $link, [
            'type' => $type,
        ], $badge);
        if (!empty($result['ok'])) {
            $sent++;
        }
    }
    return $sent;
}
