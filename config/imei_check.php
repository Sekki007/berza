<?php

declare(strict_types=1);

function imeiCheckEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('IMEI_CHECK_ENABLED', 'false')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function imeiCheckApiKey(): string
{
    return trim((string)envValue('IMEI_CHECK_API_KEY', ''));
}

function normalizeImei(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function isValidImei(string $imei): bool
{
    if (preg_match('/^\d{15}$/', $imei) !== 1) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $digit = (int)$imei[$i];
        if ($i % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return $sum % 10 === 0;
}

function maskedImei(string $imei): string
{
    return substr($imei, 0, 4) . ' •••••• ' . substr($imei, -5);
}

/**
 * @return array{ok:bool,error?:string}
 */
function imeiCheckRateLimit(): array
{
    $now = time();
    $state = readJsonFile('imei_state.json');
    $hits = is_array($state['rate_limits'] ?? null) ? $state['rate_limits'] : [];
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    $key = hash('sha256', $ip !== '' ? $ip : '0.0.0.0');
    $recent = array_values(array_filter(
        (array)($hits[$key] ?? []),
        static fn($ts) => is_int($ts) && ($now - $ts) < 3600
    ));
    $limit = isLoggedIn() ? 40 : 20;

    if (count($recent) >= $limit) {
        return ['ok' => false, 'error' => 'Dostigao si limit besplatnih provera za ovaj sat. Pokušaj ponovo kasnije.'];
    }
    return ['ok' => true];
}

function recordImeiCheckRateLimit(): void
{
    $now = time();
    $state = readJsonFile('imei_state.json');
    $hits = is_array($state['rate_limits'] ?? null) ? $state['rate_limits'] : [];
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    $key = hash('sha256', $ip !== '' ? $ip : '0.0.0.0');

    foreach ($hits as $hitKey => $timestamps) {
        $fresh = array_values(array_filter(
            (array)$timestamps,
            static fn($ts) => is_int($ts) && ($now - $ts) < 3600
        ));
        if ($fresh === []) {
            unset($hits[$hitKey]);
        } else {
            $hits[$hitKey] = $fresh;
        }
    }

    $hits[$key] = array_values((array)($hits[$key] ?? []));
    $hits[$key][] = $now;
    $state['rate_limits'] = $hits;
    writeJsonFile('imei_state.json', $state);
}

/**
 * @return array{brand:string,model:string,name:string}|null
 */
function cachedImeiModel(string $imei): ?array
{
    $state = readJsonFile('imei_state.json');
    $cache = is_array($state['cache'] ?? null) ? $state['cache'] : [];
    $entry = $cache[hash('sha256', $imei)] ?? null;
    if (!is_array($entry) || (int)($entry['expires_at'] ?? 0) < time()) {
        return null;
    }

    return [
        'brand' => trim((string)($entry['brand'] ?? '')),
        'model' => trim((string)($entry['model'] ?? '')),
        'name' => trim((string)($entry['name'] ?? '')),
    ];
}

/**
 * @param array{brand:string,model:string,name:string} $result
 */
function cacheImeiModel(string $imei, array $result): void
{
    $state = readJsonFile('imei_state.json');
    $cache = is_array($state['cache'] ?? null) ? $state['cache'] : [];
    $cache[hash('sha256', $imei)] = [
        'brand' => $result['brand'],
        'model' => $result['model'],
        'name' => $result['name'],
        'expires_at' => time() + (30 * 86400),
    ];
    $state['cache'] = $cache;
    writeJsonFile('imei_state.json', $state);
}

/**
 * @return array{ok:bool,result?:array{brand:string,model:string,name:string},error?:string,cached?:bool}
 */
function checkImeiModel(string $imei): array
{
    if (!imeiCheckEnabled() || imeiCheckApiKey() === '') {
        return ['ok' => false, 'error' => 'IMEI provera trenutno nije dostupna.'];
    }
    if (!isValidImei($imei)) {
        return ['ok' => false, 'error' => 'Unesi ispravan IMEI od 15 cifara.'];
    }

    $cached = cachedImeiModel($imei);
    if ($cached !== null) {
        return ['ok' => true, 'result' => $cached, 'cached' => true];
    }

    $rate = imeiCheckRateLimit();
    if (empty($rate['ok'])) {
        return $rate;
    }
    if (!function_exists('curl_init')) {
        error_log('IMEICheck requires PHP cURL extension');
        return ['ok' => false, 'error' => 'Servis za proveru trenutno nije dostupan.'];
    }

    $query = http_build_query([
        'imei' => $imei,
        'format' => 'json',
        'key' => imeiCheckApiKey(),
    ]);
    $ch = curl_init('https://alpha.imeicheck.com/api/modelBrandName?' . $query);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'KupiTelefon.rs IMEI Check/1.0',
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
        error_log('IMEICheck request failed: HTTP ' . $httpCode . ($curlError !== '' ? ' - ' . $curlError : ''));
        return ['ok' => false, 'error' => 'Servis za proveru trenutno ne odgovara. Pokušaj ponovo malo kasnije.'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        error_log('IMEICheck returned invalid JSON');
        return ['ok' => false, 'error' => 'Servis trenutno nije vratio ispravan rezultat. Pokušaj ponovo kasnije.'];
    }

    $object = is_array($decoded['object'] ?? null) ? $decoded['object'] : $decoded;
    $result = [
        'brand' => trim((string)($object['brand'] ?? '')),
        'model' => trim((string)($object['model'] ?? '')),
        'name' => trim((string)($object['name'] ?? $object['model_name'] ?? '')),
    ];
    if ($result['brand'] === '' && $result['model'] === '' && $result['name'] === '') {
        return ['ok' => false, 'error' => trim((string)($decoded['message'] ?? 'IMEI nije pronađen u bazi. Proveri broj i pokušaj ponovo.'))];
    }

    recordImeiCheckRateLimit();
    cacheImeiModel($imei, $result);
    return ['ok' => true, 'result' => $result, 'cached' => false];
}
