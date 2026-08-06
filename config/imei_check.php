<?php

declare(strict_types=1);

/**
 * IMEI provera.
 *
 * Osnovna provera (brand/model) je preko free TAC endpointa.
 * Proširene provere (DHRU servisi) korisnik bira iz liste, a cene i dostupnost podešava admin.
 */

function imeiCheckEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('IMEI_CHECK_ENABLED', 'false')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function imeiCheckApiUrl(): string
{
    $url = trim((string)envValue('IMEI_CHECK_URL', 'https://alpha.imeicheck.com/api/php-api'));
    return rtrim($url !== '' ? $url : 'https://alpha.imeicheck.com/api/php-api', '/');
}

function imeiCheckFreeUrl(): string
{
    return trim((string)envValue('IMEI_CHECK_FREE_URL', 'https://alpha.imeicheck.com/api/free_with_key/modelBrandName'));
}

function imeiCheckUsername(): string
{
    return trim((string)envValue('IMEI_CHECK_USERNAME', ''));
}

function imeiCheckApiKey(): string
{
    return trim((string)envValue('IMEI_CHECK_API_KEY', ''));
}

function imeiCheckDhruApiKey(): string
{
    $dhru = trim((string)envValue('IMEI_CHECK_DHRU_API_KEY', ''));
    if ($dhru !== '') {
        return $dhru;
    }
    // Backward compatibility: ako novi env nije setovan, koristi stari ključ.
    return trim((string)envValue('IMEI_CHECK_API_KEY', ''));
}

function imeiCheckDhruConfigured(): bool
{
    return imeiCheckDhruApiKey() !== '';
}

function imeiCheckServiceBlacklist(): string
{
    $id = preg_replace('/\D+/', '', (string)envValue('IMEI_CHECK_SERVICE_BLACKLIST', '5')) ?? '';
    return $id !== '' ? $id : '5';
}

function imeiCheckServiceFmi(): string
{
    $id = preg_replace('/\D+/', '', (string)envValue('IMEI_CHECK_SERVICE_FMI', '1')) ?? '';
    return $id !== '' ? $id : '1';
}

function imeiCheckServiceIcloud(): string
{
    $id = preg_replace('/\D+/', '', (string)envValue('IMEI_CHECK_SERVICE_ICLOUD', '4')) ?? '';
    return $id !== '' ? $id : '4';
}

function imeiExtendedDailyLimit(): int
{
    return max(0, (int)(siteSettings()['imei_free_checks_per_day'] ?? 5));
}

function imeiCheckConfigured(): bool
{
    if (!imeiCheckEnabled()) {
        return false;
    }
    if (imeiCheckApiKey() === '') {
        return false;
    }
    return true;
}

/**
 * @return list<array{key:string,service_id:string,label:string,price:int,enabled:bool,apple_only:bool}>
 */
function imeiServiceCatalog(): array
{
    $raw = siteSettings()['imei_services'] ?? [];
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = trim((string)($item['key'] ?? ''));
        $serviceId = preg_replace('/\D+/', '', (string)($item['service_id'] ?? '')) ?? '';
        if ($key === '' || $serviceId === '') {
            continue;
        }
        $out[] = [
            'key' => $key,
            'service_id' => $serviceId,
            'label' => trim((string)($item['label'] ?? strtoupper($key))),
            'price' => max(0, (int)($item['price'] ?? 0)),
            'enabled' => !empty($item['enabled']),
            'apple_only' => !empty($item['apple_only']),
        ];
    }
    return $out;
}

/**
 * @return list<array{key:string,service_id:string,label:string,price:int,enabled:bool,apple_only:bool}>
 */
function imeiEnabledServices(): array
{
    return array_values(array_filter(imeiServiceCatalog(), static fn($s) => !empty($s['enabled'])));
}

/**
 * @return array{key:string,service_id:string,label:string,price:int,enabled:bool,apple_only:bool}|null
 */
function imeiServiceByKey(string $key): ?array
{
    foreach (imeiServiceCatalog() as $service) {
        if ($service['key'] === $key) {
            return $service;
        }
    }
    return null;
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
 * Poziv alpha.imeicheck.com php-api endpointa.
 *
 * @param array<string,string> $parameters
 * @return array{ok:bool,data?:array,detail?:string}
 */
function instantApiRequest(string $endpoint, array $parameters = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'detail' => 'PHP cURL ekstenzija nije dostupna.'];
    }

    $payload = array_merge(['key' => imeiCheckDhruApiKey()], $parameters);
    $url = imeiCheckApiUrl() . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url . '?' . http_build_query($payload));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'KupiTelefon.rs/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
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
        return ['ok' => false, 'detail' => 'Neispravan JSON: ' . mb_substr(trim((string)$body), 0, 200)];
    }

    $statusRaw = (string)($decoded['status'] ?? $decoded['Status'] ?? '');
    $status = strtolower(trim($statusRaw));
    if (in_array($status, ['failed', 'error'], true)) {
        $detail = trim((string)(
            $decoded['result']
            ?? $decoded['Result']
            ?? $decoded['message']
            ?? $decoded['Message']
            ?? $decoded['error']
            ?? $decoded['Error']
            ?? $decoded['detail']
            ?? $decoded['Detail']
            ?? ''
        ));
        if ($detail === '') {
            $detail = 'Provider je vratio grešku: ' . mb_substr((string)json_encode($decoded, JSON_UNESCAPED_UNICODE), 0, 500);
        }
        return ['ok' => false, 'detail' => $detail !== '' ? $detail : 'Provider je vratio grešku.'];
    }
    $softError = trim((string)($decoded['error'] ?? $decoded['Error'] ?? ''));
    if ($softError !== '') {
        return ['ok' => false, 'detail' => $softError];
    }
    return ['ok' => true, 'data' => $decoded];
}

/**
 * @return array{ok:bool,result?:array{brand:string,model:string,name:string},detail?:string}
 */
function freeTacApiRequest(string $imei): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'detail' => 'PHP cURL ekstenzija nije dostupna.'];
    }

    $query = http_build_query([
        'key' => imeiCheckApiKey(),
        'imei' => $imei,
        'format' => 'json',
    ]);
    $url = rtrim(imeiCheckFreeUrl(), '?') . '?' . $query;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'KupiTelefon.rs/1.0',
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $curlError !== '' || $http < 200 || $http >= 300) {
        return ['ok' => false, 'detail' => 'HTTP ' . $http . ($curlError !== '' ? ' - ' . $curlError : '')];
    }
    if (trim($body) === '') {
        return ['ok' => false, 'detail' => 'Prazan odgovor free TAC servisa.'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'detail' => 'Neispravan JSON free TAC servisa.'];
    }

    $status = strtolower(trim((string)($decoded['status'] ?? '')));
    if ($status !== '' && !in_array($status, ['succes', 'success', 'ok'], true)) {
        return ['ok' => false, 'detail' => trim((string)($decoded['result'] ?? $decoded['message'] ?? 'Free TAC servis je vratio grešku.'))];
    }

    $object = is_array($decoded['object'] ?? null) ? $decoded['object'] : [];
    $result = [
        'brand' => trim((string)($object['brand'] ?? '')),
        'model' => trim((string)($object['model'] ?? '')),
        'name' => trim((string)($object['name'] ?? '')),
    ];
    if ($result['brand'] === '' && $result['model'] === '' && $result['name'] === '') {
        return ['ok' => false, 'detail' => trim((string)($decoded['result'] ?? 'Free TAC odgovor nema brand/model podatke.'))];
    }

    return ['ok' => true, 'result' => $result];
}

/**
 * Iz DHRU odgovora (HTML/tekst "Labela: vrednost") izvlači brend, model i naziv.
 *
 * @return array{brand:string,model:string,name:string}
 */
function imeiCheckParseResult(array $data): array
{
    $result = ['brand' => '', 'model' => '', 'name' => ''];

    $text = instantResponseText($data);
    if ($text === '') {
        return $result;
    }

    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
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

function instantResponseText(array $data): string
{
    $text = '';
    foreach (['result', 'RESULT', 'message', 'MESSAGE'] as $field) {
        if (is_string($data[$field] ?? null) && trim((string)$data[$field]) !== '') {
            $text .= "\n" . $data[$field];
        }
    }
    if (trim($text) === '') {
        return '';
    }

    $plain = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $text);
    return trim(html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function imeiExtendedChecksUsedToday(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $state = readJsonFile('imei_state.json');
    $bucket = is_array($state['extended_daily'][$userId] ?? null) ? $state['extended_daily'][$userId] : [];
    $today = date('Y-m-d');
    if ((string)($bucket['date'] ?? '') !== $today) {
        return 0;
    }
    return (int)($bucket['count'] ?? 0);
}

function imeiExtendedChecksRemaining(?int $userId = null): int
{
    $userId = $userId ?? (int)(currentUser()['id'] ?? 0);
    if ($userId <= 0) {
        return 0;
    }
    return max(0, imeiExtendedDailyLimit() - imeiExtendedChecksUsedToday($userId));
}

function recordImeiExtendedCheck(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $state = readJsonFile('imei_state.json');
    $daily = is_array($state['extended_daily'] ?? null) ? $state['extended_daily'] : [];
    $today = date('Y-m-d');
    $bucket = is_array($daily[$userId] ?? null) ? $daily[$userId] : [];
    if ((string)($bucket['date'] ?? '') !== $today) {
        $bucket = ['date' => $today, 'count' => 0];
    }
    $bucket['count'] = (int)($bucket['count'] ?? 0) + 1;
    $daily[$userId] = $bucket;
    $state['extended_daily'] = $daily;
    writeJsonFile('imei_state.json', $state);
}

function chargeableImeiFreeChecksRemaining(int $userId): int
{
    return max(0, imeiExtendedDailyLimit() - imeiExtendedChecksUsedToday($userId));
}

function imeiExtendedCacheKey(string $imei, array $serviceKeys): string
{
    sort($serviceKeys);
    return hash('sha256', $imei . '|' . implode(',', $serviceKeys));
}

/**
 * @return array{services:array<string,array{level:string,label:string,detail:string}>}|null
 */
function cachedImeiExtended(string $imei, array $serviceKeys): ?array
{
    $state = readJsonFile('imei_state.json');
    $cache = is_array($state['extended_cache'] ?? null) ? $state['extended_cache'] : [];
    $entry = $cache[imeiExtendedCacheKey($imei, $serviceKeys)] ?? null;
    if (!is_array($entry) || (int)($entry['expires_at'] ?? 0) < time()) {
        return null;
    }
    return ['services' => is_array($entry['services'] ?? null) ? $entry['services'] : []];
}

/**
 * @param array{services:array<string,array{level:string,label:string,detail:string}>} $extended
 */
function cacheImeiExtended(string $imei, array $serviceKeys, array $extended): void
{
    $state = readJsonFile('imei_state.json');
    $cache = is_array($state['extended_cache'] ?? null) ? $state['extended_cache'] : [];
    $cache[imeiExtendedCacheKey($imei, $serviceKeys)] = [
        'services' => $extended['services'],
        'expires_at' => time() + 86400,
    ];
    $state['extended_cache'] = $cache;
    writeJsonFile('imei_state.json', $state);
}

function isAppleDevice(string $brand, string $name = ''): bool
{
    $hay = strtolower($brand . ' ' . $name);
    return str_contains($hay, 'apple')
        || str_contains($hay, 'iphone')
        || str_contains($hay, 'ipad');
}

/**
 * @return array{level:string,label:string,detail:string}
 */
function interpretBlacklistStatus(string $text): array
{
    $lower = strtolower($text);
    if (preg_match('/\b(clean|not blacklisted|not listed|no blacklist)\b/i', $text)) {
        return ['level' => 'good', 'label' => 'Čist', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    if (preg_match('/\b(blacklist|blocked|stolen|lost|barred)\b/i', $text)) {
        return ['level' => 'bad', 'label' => 'Na listi', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    return ['level' => 'unknown', 'label' => 'Nepoznato', 'detail' => $lower !== '' ? mb_substr(trim($text), 0, 280) : 'Nema podataka'];
}

/**
 * @return array{level:string,label:string,detail:string}
 */
function interpretFmiStatus(string $text): array
{
    $lower = strtolower($text);
    if (preg_match('/\b(off|disabled|deactivated)\b/i', $text)) {
        return ['level' => 'good', 'label' => 'Isključen', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    if (preg_match('/\b(on|enabled|active)\b/i', $text)) {
        return ['level' => 'bad', 'label' => 'Uključen', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    return ['level' => 'unknown', 'label' => 'Nepoznato', 'detail' => $lower !== '' ? mb_substr(trim($text), 0, 280) : 'Nema podataka'];
}

/**
 * @return array{level:string,label:string,detail:string}
 */
function interpretIcloudStatus(string $text): array
{
    $lower = strtolower($text);
    if (preg_match('/\b(clean)\b/i', $text)) {
        return ['level' => 'good', 'label' => 'Clean', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    if (preg_match('/\b(lost)\b/i', $text)) {
        return ['level' => 'bad', 'label' => 'Lost', 'detail' => mb_substr(trim($text), 0, 280)];
    }
    return ['level' => 'unknown', 'label' => 'Nepoznato', 'detail' => $lower !== '' ? mb_substr(trim($text), 0, 280) : 'Nema podataka'];
}

/**
 * @return array{level:string,label:string,detail:string}
 */
function interpretGenericStatus(string $text): array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return ['level' => 'unknown', 'label' => 'Nepoznato', 'detail' => 'Nema podataka'];
    }
    return ['level' => 'unknown', 'label' => 'Rezultat', 'detail' => mb_substr($trimmed, 0, 280)];
}

/**
 * @return array{ok:bool,text?:string,detail?:string,object?:array}
 */
function instantCreateOrder(string $serviceId, string $imei): array
{
    if (!imeiCheckDhruConfigured()) {
        return ['ok' => false, 'detail' => 'IMEI instant API nije podešen.'];
    }

    $order = instantApiRequest('create', [
        'service' => $serviceId,
        'imei' => $imei,
    ]);
    if (empty($order['ok'])) {
        return ['ok' => false, 'detail' => (string)($order['detail'] ?? 'Instant order failed')];
    }

    $data = is_array($order['data'] ?? null) ? $order['data'] : [];
    $status = strtolower(trim((string)($data['status'] ?? '')));
    if (in_array($status, ['failed', 'error'], true)) {
        $detail = trim((string)($data['result'] ?? $data['message'] ?? 'Provider je odbio zahtev.'));
        return ['ok' => false, 'detail' => $detail !== '' ? $detail : 'Provider je odbio zahtev.'];
    }

    $text = instantResponseText($data);
    $orderId = trim((string)($data['orderId'] ?? ''));
    if ($text === '' && $orderId !== '') {
        $history = instantApiRequest('history', ['orderId' => $orderId]);
        if (!empty($history['ok']) && is_array($history['data'] ?? null)) {
            $text = instantResponseText((array)$history['data']);
        }
    }

    if ($text === '') {
        return ['ok' => false, 'detail' => 'Prazan odgovor servisa (proveri API key, Linked IP i kredit).'];
    }

    return [
        'ok' => true,
        'text' => $text,
        'object' => is_array($data['object'] ?? null) ? $data['object'] : [],
    ];
}

/**
 * @param list<string> $requestedKeys
 * @return array{ok:bool,extended?:array{services:array<string,array{level:string,label:string,detail:string}>},error?:string,detail?:string,cached?:bool,charged_credits?:int,used_free?:bool}
 */
function checkImeiExtended(string $imei, array $requestedKeys, string $brand = '', string $name = ''): array
{
    if (!isLoggedIn()) {
        return ['ok' => false, 'error' => 'Proširena provera je dostupna samo prijavljenim korisnicima.'];
    }
    if (!imeiCheckDhruConfigured()) {
        return ['ok' => false, 'error' => 'Proširena provera trenutno nije dostupna.'];
    }
    if (!isValidImei($imei)) {
        return ['ok' => false, 'error' => 'Unesi ispravan IMEI od 15 cifara.'];
    }

    $requestedKeys = array_values(array_unique(array_map('trim', $requestedKeys)));
    $enabled = imeiEnabledServices();
    $enabledByKey = [];
    foreach ($enabled as $svc) {
        $enabledByKey[$svc['key']] = $svc;
    }

    $services = [];
    $isApple = isAppleDevice($brand, $name);
    foreach ($requestedKeys as $key) {
        if (!isset($enabledByKey[$key])) {
            continue;
        }
        $svc = $enabledByKey[$key];
        if (!empty($svc['apple_only']) && !$isApple) {
            continue;
        }
        $services[] = $svc;
    }
    if ($services === []) {
        return ['ok' => false, 'error' => 'Izaberi bar jedan dostupan servis za proširenu proveru.'];
    }

    $serviceKeys = array_values(array_map(static fn($s) => (string)$s['key'], $services));
    $userId = (int)(currentUser()['id'] ?? 0);
    $cached = cachedImeiExtended($imei, $serviceKeys);
    if ($cached !== null) {
        return ['ok' => true, 'extended' => $cached, 'cached' => true];
    }

    $totalPrice = 0;
    foreach ($services as $service) {
        $totalPrice += (int)$service['price'];
    }
    $freeRemaining = chargeableImeiFreeChecksRemaining($userId);
    $usedFree = false;
    $chargedCredits = 0;
    if ($freeRemaining > 0) {
        $usedFree = true;
    } elseif ($totalPrice > 0) {
        if (!creditsEnabled()) {
            return ['ok' => false, 'error' => 'Besplatne provere su potrošene, a kredit sistem je isključen.'];
        }
        if (getUserCredits($userId) < $totalPrice) {
            return ['ok' => false, 'error' => 'Nemaš dovoljno kredita. Potrebno: ' . $totalPrice . ', saldo: ' . getUserCredits($userId) . '.'];
        }
        if (!adjustUserCredits($userId, -$totalPrice, 'imei_check', 'IMEI proširena provera')) {
            return ['ok' => false, 'error' => 'Skidanje kredita nije uspelo. Pokušaj ponovo.'];
        }
        $chargedCredits = $totalPrice;
    }

    $serviceResults = [];
    foreach ($services as $service) {
        $order = instantCreateOrder((string)$service['service_id'], $imei);
        if (empty($order['ok'])) {
            if ($chargedCredits > 0) {
                adjustUserCredits($userId, $chargedCredits, 'imei_refund', 'Povraćaj kredita: IMEI neuspešna provera');
            }
            return [
                'ok' => false,
                'error' => 'Proširena provera trenutno nije uspela.',
                'detail' => (string)($order['detail'] ?? ''),
            ];
        }
        $text = (string)($order['text'] ?? '');
        $key = (string)$service['key'];
        if ($key === 'blacklist') {
            $serviceResults[$key] = interpretBlacklistStatus($text);
        } elseif ($key === 'fmi') {
            $serviceResults[$key] = interpretFmiStatus($text);
        } elseif ($key === 'icloud') {
            $serviceResults[$key] = interpretIcloudStatus($text);
        } else {
            $serviceResults[$key] = interpretGenericStatus($text);
        }
    }

    if ($usedFree) {
        recordImeiExtendedCheck($userId);
    }
    $extended = ['services' => $serviceResults];
    cacheImeiExtended($imei, $serviceKeys, $extended);
    return [
        'ok' => true,
        'extended' => $extended,
        'cached' => false,
        'charged_credits' => $chargedCredits,
        'used_free' => $usedFree,
    ];
}

/**
 * @return list<string>
 */
function normalizeRequestedImeiServiceKeys(array $input): array
{
    $keys = [];
    foreach ($input as $value) {
        $key = trim((string)$value);
        if ($key !== '') {
            $keys[] = $key;
        }
    }
    return array_values(array_unique($keys));
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

    $free = freeTacApiRequest($imei);
    if (!empty($free['ok']) && is_array($free['result'] ?? null)) {
        $result = $free['result'];
        recordImeiCheckRateLimit();
        cacheImeiModel($imei, $result);
        return ['ok' => true, 'result' => $result, 'cached' => false];
    }

    if (empty($free['ok']) || !is_array($free['result'] ?? null)) {
        $detail = (string)($free['detail'] ?? 'Nema podataka o modelu.');
        return [
            'ok' => false,
            'error' => 'IMEI nije pronađen u bazi. Proveri broj i pokušaj ponovo.',
            'detail' => $detail,
        ];
    }
    return ['ok' => false, 'error' => 'IMEI provera nije uspela.'];
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

    if (!imeiCheckDhruConfigured()) {
        return ['ok' => true, 'credit' => 'FREE', 'currency' => 'TAC', 'provider' => 'free_with_key'];
    }

    $info = instantApiRequest('balance');
    if (empty($info['ok'])) {
        return ['ok' => false, 'detail' => (string)($info['detail'] ?? '')];
    }

    $data = is_array($info['data'] ?? null) ? $info['data'] : [];
    return [
        'ok' => true,
        'credit' => trim((string)($data['credit'] ?? $data['balance'] ?? '0.00')),
        'currency' => trim((string)($data['currency'] ?? 'USD')),
    ];
}
