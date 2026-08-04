<?php

declare(strict_types=1);

/**
 * Google Analytics 4 — čitanje statistike u admin panelu (Data API).
 *
 * Env:
 *   GA_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
 *   # ili inline JSON; ako je prazno, koristi se FCM_SERVICE_ACCOUNT_JSON
 *
 * Podešavanja sajta:
 *   ga4_property_id — numerički Property ID iz GA4 (Admin → Property settings)
 *
 * Setup:
 *   1) Google Cloud → omogući "Google Analytics Data API"
 *   2) Service account email dodaj u GA4 → Admin → Property access management (Viewer)
 *   3) Unesi Property ID u admin podešavanja
 */

function ga4PropertyId(): string
{
    return preg_replace('/\D+/', '', (string)(siteSettings()['ga4_property_id'] ?? '')) ?? '';
}

function gaServiceAccount(): ?array
{
    static $cached = false;
    static $account = null;
    if ($cached) {
        return $account;
    }
    $cached = true;

    $raw = trim((string)envValue('GA_SERVICE_ACCOUNT_JSON', ''));
    if ($raw === '') {
        $raw = trim((string)envValue('FCM_SERVICE_ACCOUNT_JSON', ''));
    }
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

function gaAnalyticsConfigured(): bool
{
    return ga4PropertyId() !== '' && gaServiceAccount() !== null;
}

function gaAccessToken(): ?string
{
    static $memo = null;
    static $memoExp = 0;
    if (is_string($memo) && $memo !== '' && time() < $memoExp - 60) {
        return $memo;
    }

    $account = gaServiceAccount();
    if (!$account) {
        return null;
    }

    $cacheFile = dataPath('ga_token_cache.json');
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

    if (!function_exists('base64UrlEncode')) {
        return null;
    }

    $now = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '');
    $claim = base64UrlEncode(json_encode([
        'iss' => (string)$account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
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
    if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
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

/**
 * @return array{ok:bool,data?:array,error?:string,http?:int}
 */
function gaRunReport(array $body): array
{
    $propertyId = ga4PropertyId();
    if ($propertyId === '') {
        return ['ok' => false, 'error' => 'Nije podešen GA4 Property ID.'];
    }
    $token = gaAccessToken();
    if ($token === null) {
        return ['ok' => false, 'error' => 'Nema Google access tokena (proveri service account JSON).'];
    }

    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($propertyId) . ':runReport';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 25,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($resp)) {
        return ['ok' => false, 'error' => 'GA API greška: ' . ($curlErr !== '' ? $curlErr : 'nema odgovora'), 'http' => $code];
    }
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) ? (string)($data['error']['message'] ?? $resp) : $resp;
        return ['ok' => false, 'error' => $msg, 'http' => $code, 'data' => is_array($data) ? $data : []];
    }
    return ['ok' => true, 'data' => is_array($data) ? $data : [], 'http' => $code];
}

function gaMetricValue(array $report, int $rowIndex, int $metricIndex): float
{
    $rows = $report['rows'] ?? [];
    if (!isset($rows[$rowIndex]['metricValues'][$metricIndex]['value'])) {
        return 0.0;
    }
    return (float)$rows[$rowIndex]['metricValues'][$metricIndex]['value'];
}

/**
 * @return array{ok:bool,error?:string,cached_at?:string,summary?:array,daily?:array,pages?:array,devices?:array}
 */
function gaFetchAdminStats(bool $forceRefresh = false): array
{
    if (!gaAnalyticsConfigured()) {
        return [
            'ok' => false,
            'error' => 'Google Analytics nije povezan. Unesi Property ID u Podešavanja i podesi GA_SERVICE_ACCOUNT_JSON.',
        ];
    }

    $cacheFile = dataPath('ga_stats_cache.json');
    $ttl = 15 * 60;
    if (!$forceRefresh && is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && !empty($cached['ok'])
            && (int)($cached['fetched_at'] ?? 0) > time() - $ttl
        ) {
            $cached['from_cache'] = true;
            return $cached;
        }
    }

    $ranges = [
        'today' => ['startDate' => 'today', 'endDate' => 'today'],
        'd7' => ['startDate' => '7daysAgo', 'endDate' => 'today'],
        'd30' => ['startDate' => '30daysAgo', 'endDate' => 'today'],
    ];

    $summary = [];
    foreach ($ranges as $key => $range) {
        $res = gaRunReport([
            'dateRanges' => [$range],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
            ],
        ]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => (string)($res['error'] ?? 'GA API greška')];
        }
        $report = $res['data'] ?? [];
        $summary[$key] = [
            'users' => (int)round(gaMetricValue($report, 0, 0)),
            'sessions' => (int)round(gaMetricValue($report, 0, 1)),
            'pageviews' => (int)round(gaMetricValue($report, 0, 2)),
            'bounce_rate' => round(gaMetricValue($report, 0, 3) * 100, 1),
            'avg_session_sec' => (int)round(gaMetricValue($report, 0, 4)),
        ];
    }

    $dailyRes = gaRunReport([
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'date']],
        'metrics' => [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
        'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
    ]);
    if (!$dailyRes['ok']) {
        return ['ok' => false, 'error' => (string)($dailyRes['error'] ?? 'GA API greška')];
    }
    $daily = [];
    foreach (($dailyRes['data']['rows'] ?? []) as $row) {
        $dateRaw = (string)($row['dimensionValues'][0]['value'] ?? '');
        $dateLabel = strlen($dateRaw) === 8
            ? substr($dateRaw, 6, 2) . '.' . substr($dateRaw, 4, 2) . '.'
            : $dateRaw;
        $daily[] = [
            'date' => $dateRaw,
            'label' => $dateLabel,
            'users' => (int)round((float)($row['metricValues'][0]['value'] ?? 0)),
            'sessions' => (int)round((float)($row['metricValues'][1]['value'] ?? 0)),
            'pageviews' => (int)round((float)($row['metricValues'][2]['value'] ?? 0)),
        ];
    }

    $pagesRes = gaRunReport([
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'pagePath']],
        'metrics' => [
            ['name' => 'screenPageViews'],
            ['name' => 'activeUsers'],
        ],
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit' => 15,
    ]);
    if (!$pagesRes['ok']) {
        return ['ok' => false, 'error' => (string)($pagesRes['error'] ?? 'GA API greška')];
    }
    $pages = [];
    foreach (($pagesRes['data']['rows'] ?? []) as $row) {
        $pages[] = [
            'path' => (string)($row['dimensionValues'][0]['value'] ?? '/'),
            'pageviews' => (int)round((float)($row['metricValues'][0]['value'] ?? 0)),
            'users' => (int)round((float)($row['metricValues'][1]['value'] ?? 0)),
        ];
    }

    $devicesRes = gaRunReport([
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'deviceCategory']],
        'metrics' => [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
        ],
        'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
    ]);
    if (!$devicesRes['ok']) {
        return ['ok' => false, 'error' => (string)($devicesRes['error'] ?? 'GA API greška')];
    }
    $devices = [];
    foreach (($devicesRes['data']['rows'] ?? []) as $row) {
        $devices[] = [
            'device' => (string)($row['dimensionValues'][0]['value'] ?? ''),
            'users' => (int)round((float)($row['metricValues'][0]['value'] ?? 0)),
            'sessions' => (int)round((float)($row['metricValues'][1]['value'] ?? 0)),
        ];
    }

    $payload = [
        'ok' => true,
        'fetched_at' => time(),
        'cached_at' => date('Y-m-d H:i:s'),
        'property_id' => ga4PropertyId(),
        'measurement_id' => googleTagGa4Id(),
        'summary' => $summary,
        'daily' => $daily,
        'pages' => $pages,
        'devices' => $devices,
        'from_cache' => false,
    ];
    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE));
    return $payload;
}

function gaFormatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m > 0) {
        return $m . 'm ' . $s . 's';
    }
    return $s . 's';
}
