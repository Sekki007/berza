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

/**
 * Plaćeni php-api servis (npr. "Brand + Model IMEI Check"). Prazno = koristi se
 * besplatni TAC endpoint modelBrandName.
 */
function imeiCheckServiceId(): string
{
    return preg_replace('/\D+/', '', (string)envValue('IMEI_CHECK_SERVICE_ID', '')) ?? '';
}

/**
 * @return array{body:string,http:int,error:string}
 */
function imeiCheckHttpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KupiTelefon/1.0; +https://kupitelefon.rs)',
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'body' => is_string($body) ? $body : '',
        'http' => $http,
        'error' => $error,
    ];
}

/**
 * Iz odgovora izvlači brend/model bez obzira na oblik (TAC ili php-api).
 *
 * @return array{brand:string,model:string,name:string}
 */
function imeiCheckExtractResult(array $decoded): array
{
    $object = is_array($decoded['object'] ?? null) ? $decoded['object'] : $decoded;
    $result = [
        'brand' => trim((string)($object['brand'] ?? '')),
        'model' => trim((string)($object['model'] ?? '')),
        'name' => trim((string)($object['name'] ?? $object['model_name'] ?? '')),
    ];
    if ($result['brand'] !== '' || $result['model'] !== '' || $result['name'] !== '') {
        return $result;
    }

    // php-api vraća tekstualni izveštaj u "response".
    $text = (string)($decoded['response'] ?? '');
    if ($text !== '') {
        $plain = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $text)));
        foreach (preg_split('/\r\n|\r|\n/', $plain) ?: [] as $line) {
            [$label, $value] = array_pad(explode(':', $line, 2), 2, '');
            $label = strtolower(trim($label));
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if ($result['brand'] === '' && in_array($label, ['brand', 'manufacturer', 'brand name'], true)) {
                $result['brand'] = $value;
            } elseif ($result['model'] === '' && in_array($label, ['model', 'model number'], true)) {
                $result['model'] = $value;
            } elseif ($result['name'] === '' && in_array($label, ['name', 'model name', 'device', 'description'], true)) {
                $result['name'] = $value;
            }
        }
    }

    return $result;
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

    $key = imeiCheckApiKey();
    $serviceId = imeiCheckServiceId();
    $endpoint = $serviceId !== ''
        ? 'https://alpha.imeicheck.com/api/php-api/create?' . http_build_query([
            'key' => $key,
            'service' => $serviceId,
            'imei' => $imei,
        ])
        : 'https://alpha.imeicheck.com/api/modelBrandName?' . http_build_query([
            'imei' => $imei,
            'format' => 'json',
            'key' => $key,
        ]);

    $response = imeiCheckHttpGet($endpoint);
    $body = $response['body'];
    $httpCode = $response['http'];

    if ($body === '' || $response['error'] !== '' || $httpCode < 200 || $httpCode >= 300) {
        $detail = 'HTTP ' . $httpCode . ($response['error'] !== '' ? ' - ' . $response['error'] : '');
        if ($httpCode === 403) {
            $detail .= ' (Cloudflare blokira zahtev — traži lični API ključ koji zaobilazi CAPTCHA)';
        }
        error_log('IMEICheck request failed: ' . $detail);
        return [
            'ok' => false,
            'error' => 'Servis za proveru trenutno ne odgovara. Pokušaj ponovo malo kasnije.',
            'detail' => $detail,
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        error_log('IMEICheck returned invalid JSON');
        return [
            'ok' => false,
            'error' => 'Servis trenutno nije vratio ispravan rezultat. Pokušaj ponovo kasnije.',
            'detail' => 'Neispravan JSON: ' . mb_substr(trim(strip_tags($body)), 0, 200),
        ];
    }

    if (strtolower(trim((string)($decoded['status'] ?? ''))) === 'error') {
        $apiMessage = trim(strip_tags((string)($decoded['response'] ?? $decoded['message'] ?? '')));
        error_log('IMEICheck API error: ' . $apiMessage);
        return [
            'ok' => false,
            'error' => 'Provera trenutno nije moguća. Pokušaj ponovo malo kasnije.',
            'detail' => $apiMessage !== '' ? $apiMessage : 'Servis je vratio grešku bez opisa.',
        ];
    }

    $result = imeiCheckExtractResult($decoded);
    if ($result['brand'] === '' && $result['model'] === '' && $result['name'] === '') {
        return [
            'ok' => false,
            'error' => trim((string)($decoded['message'] ?? 'IMEI nije pronađen u bazi. Proveri broj i pokušaj ponovo.')),
            'detail' => 'Odgovor bez podataka o modelu: ' . mb_substr(trim(strip_tags($body)), 0, 200),
        ];
    }

    recordImeiCheckRateLimit();
    cacheImeiModel($imei, $result);
    return ['ok' => true, 'result' => $result, 'cached' => false];
}
