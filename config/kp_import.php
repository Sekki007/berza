<?php

declare(strict_types=1);

/**
 * Import draft-a sa KupujemProdajem oglasa (javni HTML / JSON-LD).
 * Nije zvanični KP API — može prestati da radi ako KP promeni sajt.
 */

function kpImportTempRoot(): string
{
    return dirname(__DIR__) . '/public/uploads/tmp/kp-import';
}

function kpImportTempDir(int $userId): string
{
    return kpImportTempRoot() . '/' . max(1, $userId);
}

function kpImportEnsureTempDir(int $userId): string
{
    $dir = kpImportTempDir($userId);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function kpImportClearTempDir(int $userId): void
{
    $dir = kpImportTempDir($userId);
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function kpImportRateLimitOk(int $userId, int $maxPerMinute = 5): bool
{
    if (!isset($_SESSION['kp_import_hits']) || !is_array($_SESSION['kp_import_hits'])) {
        $_SESSION['kp_import_hits'] = [];
    }
    $now = time();
    $key = (string)$userId;
    $hits = array_values(array_filter(
        $_SESSION['kp_import_hits'][$key] ?? [],
        static fn($t) => is_int($t) && ($now - $t) < 60
    ));
    if (count($hits) >= $maxPerMinute) {
        $_SESSION['kp_import_hits'][$key] = $hits;
        return false;
    }
    $hits[] = $now;
    $_SESSION['kp_import_hits'][$key] = $hits;
    return true;
}

/** Validira i normalizuje KP URL. Null ako nije validan. */
function kpImportNormalizeUrl(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    // Često se nalepi tekst oko linka
    if (preg_match('~https?://[^\s<>"\']+~i', $url, $m)) {
        $url = $m[0];
    }
    $url = rtrim($url, '.,);]');
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
        return null;
    }
    $host = strtolower((string)$parts['host']);
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $host = preg_replace('/^m\./', '', $host) ?? $host;
    if ($host !== 'kupujemprodajem.com') {
        return null;
    }
    $path = (string)$parts['path'];
    if (!preg_match('~/oglas/(\d+)/?~', $path, $m)) {
        return null;
    }
    $adId = $m[1];
    // Prefer canonical path from paste; fallback /oglas/{id}
    if (preg_match('~^(/[^?#]*?/oglas/' . preg_quote($adId, '~') . ')/?~', $path, $pm)) {
        $canon = $pm[1];
    } else {
        $canon = '/oglas/' . $adId;
    }
    return 'https://www.kupujemprodajem.com' . preg_replace('~/+~', '/', $canon);
}

function kpImportFetchHtml(string $url): array
{
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: sr-RS,sr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Upgrade-Insecure-Requests: 1',
    ];

    $html = '';
    $status = 0;
    $err = '';

    if (function_exists('curl_init')) {
        $cookie = sys_get_temp_dir() . '/kp_import_cookies_' . md5($url) . '.txt';
        $attempts = [
            ['verify' => true],
            ['verify' => false], // shared hosting često nema ažuran CA bundle
        ];
        foreach ($attempts as $attempt) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 8,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 35,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_ENCODING => '',
                CURLOPT_COOKIEJAR => $cookie,
                CURLOPT_COOKIEFILE => $cookie,
                CURLOPT_SSL_VERIFYPEER => !empty($attempt['verify']),
                CURLOPT_SSL_VERIFYHOST => !empty($attempt['verify']) ? 2 : 0,
            ]);
            if (defined('CURL_IPRESOLVE_V4')) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = (string)curl_error($ch);
            curl_close($ch);

            if ($body !== false && $body !== '' && $status > 0 && $status < 500) {
                $html = (string)$body;
                $err = '';
                break;
            }
            $err = $cerr !== '' ? $cerr : ('http_' . $status);
            $html = is_string($body) ? $body : '';
        }
        @unlink($cookie);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$ua}\r\nAccept: text/html\r\nAccept-Language: sr-RS,sr;q=0.9\r\n",
                'timeout' => 35,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            $err = 'fetch_failed';
        } else {
            $html = (string)$body;
            $status = 200;
        }
    }

    return ['html' => $html, 'status' => $status, 'error' => $err];
}

function kpImportExtractJsonLdProducts(string $html): array
{
    $out = [];
    if (!preg_match_all('~<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $matches)) {
        return $out;
    }
    foreach ($matches[1] as $raw) {
        $raw = html_entity_decode(trim((string)$raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            continue;
        }
        $isList = function_exists('array_is_list') ? array_is_list($data) : (array_keys($data) === range(0, count($data) - 1));
        $nodes = isset($data['@type']) ? [$data] : ($isList ? $data : [$data]);
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = $node['@type'] ?? '';
            if (is_array($type)) {
                $type = implode(',', $type);
            }
            if (stripos((string)$type, 'Product') === false) {
                continue;
            }
            $out[] = $node;
        }
    }
    return $out;
}

/** @return array<string,mixed>|null */
function kpImportExtractNextDataAd(string $html, ?string $adId = null): ?array
{
    if (!preg_match('~<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>~is', $html, $m)) {
        return null;
    }
    $data = json_decode((string)$m[1], true);
    if (!is_array($data)) {
        return null;
    }
    $byId = $data['props']['initialReduxState']['ad']['byId'] ?? null;
    if (!is_array($byId) || $byId === []) {
        return null;
    }
    if ($adId !== null && $adId !== '' && isset($byId[$adId]) && is_array($byId[$adId])) {
        return $byId[$adId];
    }
    // numeric keys may be int-cast in JSON
    foreach ($byId as $key => $ad) {
        if ($adId !== null && (string)$key === (string)$adId && is_array($ad)) {
            return $ad;
        }
    }
    foreach ($byId as $ad) {
        if (is_array($ad) && !empty($ad['name'])) {
            return $ad;
        }
    }
    return null;
}

function kpImportStripHtml(string $html): string
{
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('~<br\s*/?>~i', "\n", $text) ?? $text;
    $text = preg_replace('~</p>\s*<p[^>]*>~i', "\n\n", $text) ?? $text;
    $text = strip_tags($text);
    $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
    return trim($text);
}

function kpImportExtractNextDataLocation(string $html): string
{
    $ad = kpImportExtractNextDataAd($html);
    if (is_array($ad)) {
        $loc = trim((string)($ad['location'] ?? ''));
        if ($loc !== '') {
            return $loc;
        }
        $userLoc = trim((string)($ad['user']['userLocation'] ?? ''));
        if ($userLoc !== '') {
            return $userLoc;
        }
    }
    if (preg_match('~"location"\s*:\s*"([^"]{2,80})"~u', $html, $loc)) {
        return trim(stripcslashes($loc[1]));
    }
    return '';
}

function kpImportMetaContent(string $html, string $property): string
{
    $prop = preg_quote($property, '~');
    if (preg_match('~<meta[^>]+(?:property|name)=["\']' . $prop . '["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']' . $prop . '["\']~i', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function kpImportGuessBrandModelFromUrl(string $url): array
{
    $brand = '';
    $model = '';
    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    // /mobilni-telefoni/apple-iphone/iphone-13-pro/oglas/123
    if (preg_match('~/mobilni-telefoni/([^/]+)/([^/]+)/oglas/~i', $path, $m)) {
        $group = strtolower($m[1]);
        $slug = $m[2];
        $map = [
            'apple-iphone' => 'Apple',
            'apple' => 'Apple',
            'samsung' => 'Samsung',
            'xiaomi' => 'Xiaomi',
            'huawei' => 'Huawei',
            'google' => 'Google',
            'motorola' => 'Motorola',
        ];
        foreach ($map as $key => $label) {
            if (str_contains($group, $key) || str_starts_with($group, explode('-', $key)[0])) {
                $brand = $label;
                break;
            }
        }
        if ($brand === '' && str_contains($group, 'apple')) {
            $brand = 'Apple';
        }
        $model = trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', $slug)) ?? '');
        if ($model !== '') {
            $model = mb_convert_case($model, MB_CASE_TITLE, 'UTF-8');
            // iPhone casing
            $model = preg_replace('/\bIphone\b/u', 'iPhone', $model) ?? $model;
            $model = preg_replace('/\bIpod\b/u', 'iPod', $model) ?? $model;
            $model = preg_replace('/\bIpad\b/u', 'iPad', $model) ?? $model;
        }
    }
    return ['brand' => $brand, 'model' => $model];
}

function kpImportMapCondition(?string $schemaCondition, string $text): string
{
    $schema = strtolower((string)$schemaCondition);
    if (str_contains($schema, 'newcondition')) {
        return 'Novo';
    }
    if (str_contains($schema, 'refurbished')) {
        return 'Servisirano';
    }
    if (str_contains($schema, 'usedcondition') || str_contains($schema, 'damaged')) {
        return 'Polovno';
    }
    $t = mb_strtolower($text, 'UTF-8');
    if (preg_match('/\bnovo\b|nekorišćen|nekoriscen|factory sealed/u', $t)) {
        return 'Novo';
    }
    if (preg_match('/kao novo|odličan|odlican/u', $t)) {
        return 'Kao novo';
    }
    if (preg_match('/za delove|oštećen|ostecen/u', $t)) {
        return 'Oštećeno/Za delove';
    }
    return 'Polovno';
}

function kpImportExtractBatteryHealth(string $text): ?int
{
    if (preg_match('/\b(?:bh|bater(?:y|ija)?\s*h(?:ealth|elth)?|battery\s*health)\s*[:=]?\s*(\d{2,3})\s*%?/iu', $text, $m)) {
        $v = (int)$m[1];
        if ($v >= 0 && $v <= 100) {
            return $v;
        }
    }
    if (preg_match('/\b(\d{2,3})\s*%\s*(?:bh|bater)/iu', $text, $m)) {
        $v = (int)$m[1];
        if ($v >= 50 && $v <= 100) {
            return $v;
        }
    }
    return null;
}

function kpImportExtractStorage(string $text): string
{
    $allowed = ['64GB', '128GB', '256GB', '512GB', '1TB'];
    if (!preg_match_all('/\b(\d+)\s*(gb|tb)\b/iu', $text, $matches, PREG_SET_ORDER)) {
        return '';
    }
    foreach ($matches as $m) {
        $n = (int)$m[1];
        $u = strtoupper($m[2]);
        $label = $u === 'TB' ? ($n . 'TB') : ($n . 'GB');
        if ($label === '1024GB') {
            $label = '1TB';
        }
        if (in_array($label, $allowed, true)) {
            return $label;
        }
    }
    return '';
}

function kpImportGuessAccessories(string $text): array
{
    $t = mb_strtolower($text, 'UTF-8');
    $out = [];
    if (preg_match('/kutij/u', $t)) {
        $out[] = 'box';
    }
    if (preg_match('/punja[cč]|charger/u', $t)) {
        $out[] = 'charger';
    }
    if (preg_match('/kabl|cable/u', $t)) {
        $out[] = 'cable';
    }
    if (preg_match('/stakl|folij|glass/u', $t)) {
        $out[] = 'glass';
    }
    if (preg_match('/maska|case|futrola/u', $t)) {
        $out[] = 'case';
    }
    return $out;
}

function kpImportMatchCity(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    // "Beograd | Voždovac" → Beograd
    $first = trim(explode('|', $raw)[0] ?? $raw);
    $cities = siteSettings()['cities'] ?? [];
    if (!is_array($cities)) {
        return '';
    }
    foreach ($cities as $city) {
        if (mb_strtolower((string)$city, 'UTF-8') === mb_strtolower($first, 'UTF-8')) {
            return (string)$city;
        }
    }
    // partial: opis može imati "Kragujevac"
    foreach ($cities as $city) {
        if ($city !== '' && mb_stripos($raw, (string)$city) !== false) {
            return (string)$city;
        }
    }
    return '';
}

/**
 * Parsira KP HTML u draft polja za našu formu.
 *
 * @return array{ok:bool,error?:string,draft?:array}
 */
function kpImportParseHtml(string $html, string $url): array
{
    if ($html === '' || strlen($html) < 400) {
        return ['ok' => false, 'error' => 'KP nije vratio oglas (prazan odgovor). Proveri link ili pokušaj kasnije.'];
    }

    $adId = null;
    if (preg_match('~/oglas/(\d+)~', $url, $um)) {
        $adId = $um[1];
    }

    $title = '';
    $description = '';
    $price = 0.0;
    $currency = 'eur';
    $images = [];
    $conditionSchema = '';
    $conditionText = '';
    $locationRaw = '';

    // 1) NEXT_DATA (najkompletnije)
    $nextAd = kpImportExtractNextDataAd($html, $adId);
    if (is_array($nextAd)) {
        $title = trim((string)($nextAd['name'] ?? $nextAd['formattedName'] ?? ''));
        $description = kpImportStripHtml((string)($nextAd['description'] ?? ''));
        $price = (float)($nextAd['priceNumber'] ?? $nextAd['price'] ?? 0);
        $cur = strtolower(trim((string)($nextAd['currencyAcronym'] ?? 'eur')));
        $currency = $cur === 'rsd' ? 'rsd' : 'eur';
        $conditionText = trim((string)($nextAd['condition'] ?? ''));
        $condId = strtolower(trim((string)($nextAd['conditionId'] ?? '')));
        if ($condId === 'new') {
            $conditionSchema = 'https://schema.org/NewCondition';
        } elseif ($condId === 'used') {
            $conditionSchema = 'https://schema.org/UsedCondition';
        }
        $locationRaw = trim((string)($nextAd['location'] ?? ''));
        $photos = $nextAd['photos'] ?? [];
        if (is_array($photos)) {
            foreach ($photos as $ph) {
                if (!is_array($ph)) {
                    continue;
                }
                $img = trim((string)($ph['original'] ?? $ph['fullscreen'] ?? ''));
                if ($img !== '' && str_starts_with($img, 'http') && !str_contains($img, 'undefined')) {
                    $images[] = $img;
                }
            }
        }
    }

    // 2) JSON-LD Product
    if ($title === '' || $price <= 0 || $images === []) {
        $products = kpImportExtractJsonLdProducts($html);
        $product = $products[0] ?? null;
        if (is_array($product)) {
            if ($title === '') {
                $title = trim((string)($product['name'] ?? ''));
            }
            if ($description === '') {
                $description = trim((string)($product['description'] ?? ''));
            }
            if ($images === []) {
                $imgs = $product['image'] ?? [];
                if (is_string($imgs)) {
                    $imgs = [$imgs];
                }
                if (is_array($imgs)) {
                    foreach ($imgs as $img) {
                        $img = trim((string)$img);
                        if ($img !== '' && str_starts_with($img, 'http')) {
                            $images[] = $img;
                        }
                    }
                }
            }
            $offer = $product['offers'] ?? null;
            if (is_array($offer)) {
                if (isset($offer[0]) && is_array($offer[0])) {
                    $offer = $offer[0];
                }
                if ($price <= 0) {
                    $price = (float)($offer['price'] ?? 0);
                }
                $cur = strtolower(trim((string)($offer['priceCurrency'] ?? 'EUR')));
                if ($cur === 'rsd') {
                    $currency = 'rsd';
                }
                if ($conditionSchema === '') {
                    $conditionSchema = (string)($offer['itemCondition'] ?? '');
                }
            }
        }
    }

    // 3) Open Graph fallback
    if ($title === '') {
        $og = kpImportMetaContent($html, 'og:title');
        $title = trim(preg_replace('/\s*-\s*KupujemProdajem\s*$/u', '', $og) ?? $og);
    }
    if ($description === '') {
        $description = kpImportMetaContent($html, 'og:description');
        if ($description === '') {
            $description = kpImportMetaContent($html, 'description');
        }
    }
    if ($images === []) {
        $ogImg = kpImportMetaContent($html, 'og:image');
        if ($ogImg !== '' && !str_contains($ogImg, 'undefined')) {
            $images[] = $ogImg;
        }
    }

    $images = array_values(array_unique(array_filter($images, static function ($u) {
        return is_string($u) && $u !== '' && !str_contains($u, 'tmb-') && !str_contains($u, 'undefined');
    })));
    $images = array_slice($images, 0, 10);

    if ($title === '') {
        $hint = (str_contains($html, 'captcha') || str_contains($html, 'Cloudflare') || str_contains($html, 'cf-challenge'))
            ? 'KP blokira automatski pristup sa servera.'
            : 'Stranica nema podatke oglasa.';
        return ['ok' => false, 'error' => $hint . ' Nalepi ručno ili pokušaj kasnije.'];
    }

    $blob = $title . "\n" . $description;
    $bm = kpImportGuessBrandModelFromUrl($url);
    if ($locationRaw === '') {
        $locationRaw = kpImportExtractNextDataLocation($html);
    }
    if ($locationRaw === '') {
        $locationRaw = $description;
    }

    $brands = siteSettings()['brands'] ?? ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Google', 'Motorola', 'Ostalo'];
    $brand = $bm['brand'];
    if ($brand !== '' && is_array($brands) && !in_array($brand, $brands, true)) {
        $brand = 'Ostalo';
    }
    if ($brand === '') {
        $brand = 'Ostalo';
    }

    $model = $bm['model'];
    if ($model === '' && preg_match('/^(iphone[\w\s]+)/iu', $title, $tm)) {
        $model = trim($tm[1]);
    }

    $condition = kpImportMapCondition($conditionSchema, $conditionText !== '' ? $conditionText : $blob);

    $draft = [
        'ad_type' => 'telefon',
        'category_group' => 'phones',
        'listing_type' => 'sell',
        'device_type' => 'phone',
        'title' => mb_substr($title, 0, 120),
        'description' => $description,
        'price_type' => $price > 0 ? 'fixed' : 'negotiable',
        'price' => $price > 0 ? $price : 0,
        'currency' => $currency,
        'condition_state' => $condition,
        'brand' => $brand,
        'model' => mb_substr($model, 0, 80),
        'storage' => kpImportExtractStorage($blob),
        'battery_health' => kpImportExtractBatteryHealth($blob),
        'accessories' => kpImportGuessAccessories($blob),
        'location' => kpImportMatchCity($locationRaw),
        'source_url' => $url,
        'remote_images' => $images,
    ];

    return ['ok' => true, 'draft' => $draft];
}

/**
 * Skida remote slike u temp folder korisnika.
 *
 * @param list<string> $urls
 * @return list<string> public paths /uploads/tmp/kp-import/{userId}/...
 */
function kpImportDownloadImages(int $userId, array $urls): array
{
    kpImportClearTempDir($userId);
    $dir = kpImportEnsureTempDir($userId);
    $saved = [];
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $i = 0;
    foreach ($urls as $url) {
        if (count($saved) >= 10) {
            break;
        }
        $url = trim((string)$url);
        if ($url === '' || !preg_match('~^https://~i', $url)) {
            continue;
        }
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if (!str_ends_with($host, 'kupujemprodajem.com')) {
            continue;
        }

        $bin = false;
        if (function_exists('curl_init')) {
            foreach ([true, false] as $verify) {
                $ch = curl_init($url);
                $opts = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_USERAGENT => $ua,
                    CURLOPT_SSL_VERIFYPEER => $verify,
                    CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                ];
                if (defined('CURL_IPRESOLVE_V4')) {
                    $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
                }
                curl_setopt_array($ch, $opts);
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($body !== false && $code >= 200 && $code < 300 && strlen((string)$body) >= 100) {
                    $bin = $body;
                    break;
                }
            }
        } else {
            $bin = @file_get_contents($url);
        }
        if ($bin === false || $bin === '' || strlen((string)$bin) < 100) {
            continue;
        }

        $tmp = $dir . '/_dl_' . $i . '.bin';
        if (file_put_contents($tmp, $bin) === false) {
            continue;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: '') : '';
        if ($mime === '') {
            $head = substr((string)$bin, 0, 16);
            if (str_starts_with($head, "\xFF\xD8")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($head, "\x89PNG")) {
                $mime = 'image/png';
            } elseif (str_starts_with($head, 'RIFF') && str_contains($head, 'WEBP')) {
                $mime = 'image/webp';
            } elseif (str_starts_with($head, 'GIF8')) {
                $mime = 'image/gif';
            }
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            @unlink($tmp);
            continue;
        }
        $name = 'img_' . time() . '_' . $i . '.jpg';
        $dest = $dir . '/' . $name;
        $ok = function_exists('compressAndSaveImage') && compressAndSaveImage($tmp, $dest, $mime);
        if (!$ok && is_file($tmp)) {
            $ok = @copy($tmp, $dest);
        }
        if ($ok && is_file($dest)) {
            $saved[] = '/uploads/tmp/kp-import/' . $userId . '/' . $name;
        }
        @unlink($tmp);
        $i++;
    }
    return $saved;
}

/**
 * Validira da je putanja temp import slika ovog korisnika.
 */
function kpImportIsValidTempPath(int $userId, string $path): bool
{
    $path = str_replace('\\', '/', $path);
    $prefix = '/uploads/tmp/kp-import/' . $userId . '/';
    if (!str_starts_with($path, $prefix)) {
        return false;
    }
    $base = basename($path);
    if ($base === '' || $base === '.' || $base === '..' || str_contains($base, '/') || str_contains($base, '\\')) {
        return false;
    }
    if (!preg_match('/^img_\d+_\d+\.jpg$/', $base)) {
        return false;
    }
    $full = kpImportTempDir($userId) . '/' . $base;
    return is_file($full);
}

/**
 * Prebacuje odobrene temp KP slike u uploads/ads/{adId}/.
 *
 * @param list<string> $tempPaths
 * @param list<string> $existing
 * @return list<string>
 */
function kpImportPromoteImages(int $userId, int $adId, array $tempPaths, array $existing = []): array
{
    ensureUploadsDir();
    $images = array_values($existing);
    $targetDir = uploadsDir() . '/' . $adId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $i = 0;
    foreach ($tempPaths as $path) {
        if (count($images) >= 10) {
            break;
        }
        $path = (string)$path;
        if (!kpImportIsValidTempPath($userId, $path)) {
            continue;
        }
        $src = kpImportTempDir($userId) . '/' . basename($path);
        $name = 'img_' . time() . '_kp_' . $i . '.jpg';
        $dest = $targetDir . '/' . $name;
        if (@rename($src, $dest) || @copy($src, $dest)) {
            if (is_file($src)) {
                @unlink($src);
            }
            $images[] = '/uploads/ads/' . $adId . '/' . $name;
            $i++;
        }
    }

    return array_values(array_slice($images, 0, 10));
}

/**
 * Glavni import: fetch + parse + download slika.
 *
 * @return array{ok:bool,error?:string,draft?:array,images?:list<string>}
 */
function kpImportFromUrl(string $url, int $userId): array
{
    $normalized = kpImportNormalizeUrl($url);
    if ($normalized === null) {
        return ['ok' => false, 'error' => 'Unesi validan link KP oglasa (…/oglas/123…).'];
    }

    $fetched = kpImportFetchHtml($normalized);
    if ($fetched['html'] === '') {
        $detail = trim((string)($fetched['error'] ?? ''));
        $msg = 'Ne mogu da dostignem KupujemProdajem sa servera';
        if ($detail !== '') {
            $msg .= ' (' . $detail . ')';
        }
        $msg .= '. Probaj kasnije ili nalepi podatke ručno.';
        return ['ok' => false, 'error' => $msg];
    }
    if ((int)$fetched['status'] === 404) {
        return ['ok' => false, 'error' => 'KP kaže da oglas ne postoji (404). Proveri link.'];
    }
    if ((int)$fetched['status'] >= 400) {
        return ['ok' => false, 'error' => 'KP je vratio grešku HTTP ' . (int)$fetched['status'] . '. Pokušaj kasnije.'];
    }

    $parsed = kpImportParseHtml($fetched['html'], $normalized);
    if (empty($parsed['ok']) || empty($parsed['draft']) || !is_array($parsed['draft'])) {
        return ['ok' => false, 'error' => (string)($parsed['error'] ?? 'KP nije vratio oglas; nalepi ručno ili pokušaj kasnije.')];
    }

    $draft = $parsed['draft'];
    $remote = is_array($draft['remote_images'] ?? null) ? $draft['remote_images'] : [];
    unset($draft['remote_images']);

    $localImages = [];
    try {
        if ($remote !== []) {
            $localImages = kpImportDownloadImages($userId, $remote);
        }
    } catch (Throwable $e) {
        // Slike su bonus — draft i dalje vraćamo
        $localImages = [];
    }

    return [
        'ok' => true,
        'draft' => $draft,
        'images' => $localImages,
    ];
}
