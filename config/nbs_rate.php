<?php

declare(strict_types=1);

function nbsRateCachePath(): string
{
    return dirname(__DIR__) . '/data/nbs_rate_cache.json';
}

/**
 * @return array{rate:float,date:string,fetched_at:string,source:string}|null
 */
function readNbsRateCache(): ?array
{
    $path = nbsRateCachePath();
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['rate'])) {
        return null;
    }
    return [
        'rate' => (float)$data['rate'],
        'date' => (string)($data['date'] ?? ''),
        'fetched_at' => (string)($data['fetched_at'] ?? ''),
        'source' => (string)($data['source'] ?? 'nbs'),
    ];
}

function writeNbsRateCache(float $rate, string $date, string $source = 'nbs'): void
{
    $dir = dirname(nbsRateCachePath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $payload = [
        'rate' => round($rate, 4),
        'date' => $date,
        'fetched_at' => date('Y-m-d H:i:s'),
        'source' => $source,
    ];
    @file_put_contents(nbsRateCachePath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function nbsRateCacheFresh(int $maxAgeHours = 12): bool
{
    $cache = readNbsRateCache();
    if (!$cache || ($cache['rate'] ?? 0) <= 0) {
        return false;
    }
    $ts = strtotime((string)$cache['fetched_at']);
    if ($ts === false) {
        return false;
    }
    return (time() - $ts) < ($maxAgeHours * 3600);
}

/**
 * Vuče srednji EUR/RSD kurs (podaci NBS-a preko javnog JSON API-ja).
 *
 * @return array{ok:bool,rate?:float,date?:string,error?:string}
 */
function fetchNbsEurRsdRate(): array
{
    $url = 'https://kurs.resenje.org/api/v1/currencies/eur/rates/today';
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'PHP curl nije dostupan.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: TelefonBerza/1.0',
        ],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $body === false || $http < 200 || $http >= 300) {
        return ['ok' => false, 'error' => 'NBS kurs nije dostupan (HTTP ' . $http . ').'];
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Neispravan odgovor kursa.'];
    }

    $rate = (float)($json['exchange_middle'] ?? 0);
    if ($rate <= 0) {
        return ['ok' => false, 'error' => 'Srednji kurs nije pronađen.'];
    }

    $date = (string)($json['date'] ?? date('Y-m-d'));
    writeNbsRateCache($rate, $date, 'nbs');

    return ['ok' => true, 'rate' => $rate, 'date' => $date];
}

/** Osveži keš ako je zastareo. */
function refreshNbsEurRsdRateIfStale(int $maxAgeHours = 12): void
{
    $settings = siteSettings();
    if (empty($settings['eur_rsd_auto_nbs'])) {
        return;
    }
    if (nbsRateCacheFresh($maxAgeHours)) {
        return;
    }
    fetchNbsEurRsdRate();
}
