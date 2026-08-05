<?php

declare(strict_types=1);

/**
 * IMEI provera preko DHRU Fusion API-ja (imeicheck.com / checkimei.com).
 *
 * Env:
 *   IMEI_CHECK_ENABLED=true
 *   IMEI_CHECK_URL=https://dhru.checkimei.com
 *   IMEI_CHECK_USERNAME=...
 *   IMEI_CHECK_API_KEY=...
 *   IMEI_CHECK_SERVICE_ID=11   # "IMEI to Brand/Model/Name"
 */

function imeiCheckEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('IMEI_CHECK_ENABLED', 'false')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function imeiCheckApiUrl(): string
{
    $url = trim((string)envValue('IMEI_CHECK_URL', 'https://dhru.checkimei.com'));
    return rtrim($url !== '' ? $url : 'https://dhru.checkimei.com', '/') . '/api/index.php';
}

function imeiCheckUsername(): string
{
    return trim((string)envValue('IMEI_CHECK_USERNAME', ''));
}

function imeiCheckApiKey(): string
{
    return trim((string)envValue('IMEI_CHECK_API_KEY', ''));
}

function imeiCheckServiceId(): string
{
    $id = preg_replace('/\D+/', '', (string)envValue('IMEI_CHECK_SERVICE_ID', '11')) ?? '';
    return $id !== '' ? $id : '11';
}

function imeiCheckConfigured(): bool
{
    return imeiCheckEnabled() && imeiCheckUsername() !== '' && imeiCheckApiKey() !== '';
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
 * Poziv DHRU Fusion API-ja. Dodatni parametri idu kao base64(JSON).
 *
 * @param array<string,string> $parameters
 * @return array{ok:bool,data?:array,detail?:string}
 */
function dhruApiRequest(string $action, array $parameters = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'detail' => 'PHP cURL ekstenzija nije dostupna.'];
    }

    $payload = [
        'username' => imeiCheckUsername(),
        'apiaccesskey' => imeiCheckApiKey(),
        'action' => $action,
        'requestformat' => 'JSON',
    ];
    if ($parameters !== []) {
        $payload['parameters'] = base64_encode((string)json_encode($parameters));
    }

    $ch = curl_init(imeiCheckApiUrl());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'KupiTelefon.rs/1.0',
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $curlError !== '' || $http < 200 || $http >= 300) {
        return [
            'ok' => false,
            'detail' => 'HTTP ' . $http . ($curlError !== '' ? ' - ' . $curlError : ''),
        ];
    }
    if (trim($body) === '') {
        return [
            'ok' => false,
            'detail' => 'Prazan odgovor servisa (najčešće nedovoljan kredit na DHRU nalogu).',
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'detail' => 'Neispravan JSON: ' . mb_substr(trim(strip_tags($body)), 0, 200)];
    }
    if (isset($decoded['ERROR'])) {
        $first = is_array($decoded['ERROR']) ? ($decoded['ERROR'][0] ?? []) : [];
        $message = is_array($first) ? trim((string)($first['MESSAGE'] ?? '')) : '';
        return ['ok' => false, 'detail' => $message !== '' ? $message : 'Servis je vratio grešku bez opisa.'];
    }

    $success = is_array($decoded['SUCCESS'] ?? null) ? ($decoded['SUCCESS'][0] ?? []) : [];
    return ['ok' => true, 'data' => is_array($success) ? $success : []];
}

/**
 * Iz DHRU odgovora (HTML/tekst "Labela: vrednost") izvlači brend, model i naziv.
 *
 * @return array{brand:string,model:string,name:string}
 */
function imeiCheckParseResult(array $data): array
{
    $result = ['brand' => '', 'model' => '', 'name' => ''];

    $text = '';
    foreach (['RESULT', 'result', 'DESCRIPTION', 'MESSAGE'] as $field) {
        if (is_string($data[$field] ?? null) && trim((string)$data[$field]) !== '') {
            $text .= "\n" . $data[$field];
        }
    }
    if (trim($text) === '') {
        return $result;
    }

    $plain = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $text);
    $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    foreach (preg_split('/\r\n|\r|\n/', $plain) ?: [] as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$label, $value] = array_pad(explode(':', $line, 2), 2, '');
        $label = strtolower(trim($label));
        $value = trim($value);
        if ($value === '') {
            continue;
        }
        if ($result['brand'] === '' && in_array($label, ['brand', 'manufacturer', 'brand name', 'vendor'], true)) {
            $result['brand'] = $value;
        } elseif ($result['model'] === '' && in_array($label, ['model', 'model number', 'model no'], true)) {
            $result['model'] = $value;
        } elseif ($result['name'] === '' && in_array($label, ['name', 'model name', 'device', 'device name', 'description'], true)) {
            $result['name'] = $value;
        }
    }

    return $result;
}

/**
 * @return array{ok:bool,result?:array{brand:string,model:string,name:string},error?:string,detail?:string,cached?:bool}
 */
function checkImeiModel(string $imei): array
{
    if (!imeiCheckConfigured()) {
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

    $order = dhruApiRequest('placeimeiorder', [
        'ID' => imeiCheckServiceId(),
        'IMEI' => $imei,
    ]);
    if (empty($order['ok'])) {
        error_log('IMEICheck placeimeiorder failed: ' . (string)($order['detail'] ?? ''));
        return [
            'ok' => false,
            'error' => 'Servis za proveru trenutno ne odgovara. Pokušaj ponovo malo kasnije.',
            'detail' => (string)($order['detail'] ?? ''),
        ];
    }

    $data = is_array($order['data'] ?? null) ? $order['data'] : [];
    $result = imeiCheckParseResult($data);

    // Instant servis obično vrati rezultat odmah; ako nije, dovuci ga po ID-u porudžbine.
    $orderId = trim((string)($data['ID'] ?? $data['REFERENCEID'] ?? ''));
    if ($result['brand'] === '' && $result['model'] === '' && $result['name'] === '' && $orderId !== '') {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            sleep(2);
            $fetched = dhruApiRequest('getimeiorder', ['ID' => $orderId]);
            if (empty($fetched['ok'])) {
                continue;
            }
            $result = imeiCheckParseResult(is_array($fetched['data'] ?? null) ? $fetched['data'] : []);
            if ($result['brand'] !== '' || $result['model'] !== '' || $result['name'] !== '') {
                break;
            }
        }
    }

    if ($result['brand'] === '' && $result['model'] === '' && $result['name'] === '') {
        return [
            'ok' => false,
            'error' => 'IMEI nije pronađen u bazi. Proveri broj i pokušaj ponovo.',
            'detail' => 'Odgovor bez podataka o modelu: ' . mb_substr((string)json_encode($data), 0, 200),
        ];
    }

    recordImeiCheckRateLimit();
    cacheImeiModel($imei, $result);
    return ['ok' => true, 'result' => $result, 'cached' => false];
}

/**
 * Stanje kredita na DHRU nalogu — za admin prikaz.
 *
 * @return array{ok:bool,credit?:string,currency?:string,detail?:string}
 */
function imeiCheckAccountInfo(): array
{
    if (!imeiCheckConfigured()) {
        return ['ok' => false, 'detail' => 'IMEI provera nije podešena.'];
    }

    $info = dhruApiRequest('accountinfo');
    if (empty($info['ok'])) {
        return ['ok' => false, 'detail' => (string)($info['detail'] ?? '')];
    }

    $account = is_array($info['data']['AccoutInfo'] ?? null) ? $info['data']['AccoutInfo'] : [];
    return [
        'ok' => true,
        'credit' => trim((string)($account['credit'] ?? '0.00')),
        'currency' => trim((string)($account['currency'] ?? 'USD')),
    ];
}
