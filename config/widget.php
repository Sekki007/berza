<?php

declare(strict_types=1);

/**
 * Popularne IAB / native dimenzije za embed.
 *
 * @return array<string, array{
 *   label:string,
 *   use:string,
 *   width:string,
 *   height:int,
 *   limit:int,
 *   layout:string
 * }>
 */
function widgetSizePresets(): array
{
    return [
        'native' => [
            'label' => 'Native kartica (u članku)',
            'use' => 'Unutar teksta članka — horizontalna kartica, puna širina kolone',
            'width' => '100%',
            'height' => 148,
            'limit' => 1,
            'layout' => 'native',
        ],
        '300x250' => [
            'label' => 'Medium Rectangle 300×250',
            'use' => 'Najčešći format u člancima / sidebar',
            'width' => '300px',
            'height' => 250,
            'limit' => 1,
            'layout' => 'rect',
        ],
        '336x280' => [
            'label' => 'Large Rectangle 336×280',
            'use' => 'Članak / sadržaj, malo veći od 300×250',
            'width' => '336px',
            'height' => 280,
            'limit' => 1,
            'layout' => 'rect',
        ],
        '300x600' => [
            'label' => 'Half Page 300×600',
            'use' => 'Sidebar / vertikalni banner',
            'width' => '300px',
            'height' => 600,
            'limit' => 3,
            'layout' => 'sky',
        ],
        '160x600' => [
            'label' => 'Wide Skyscraper 160×600',
            'use' => 'Uzak sidebar',
            'width' => '160px',
            'height' => 600,
            'limit' => 3,
            'layout' => 'sky-narrow',
        ],
        '728x90' => [
            'label' => 'Leaderboard 728×90',
            'use' => 'Iznad / ispod članka (desktop)',
            'width' => '728px',
            'height' => 90,
            'limit' => 1,
            'layout' => 'leader',
        ],
        '970x90' => [
            'label' => 'Super Leaderboard 970×90',
            'use' => 'Široki header banner (desktop)',
            'width' => '970px',
            'height' => 90,
            'limit' => 1,
            'layout' => 'leader',
        ],
        '320x50' => [
            'label' => 'Mobile Banner 320×50',
            'use' => 'Mobilni sticky / iznad sadržaja',
            'width' => '320px',
            'height' => 50,
            'limit' => 1,
            'layout' => 'mobile-sm',
        ],
        '320x100' => [
            'label' => 'Large Mobile 320×100',
            'use' => 'Mobilni banner u članku',
            'width' => '320px',
            'height' => 100,
            'limit' => 1,
            'layout' => 'mobile-lg',
        ],
    ];
}

function widgetPreset(string $size): ?array
{
    $presets = widgetSizePresets();
    $size = strtolower(trim($size));
    return $presets[$size] ?? null;
}

function widgetEmbedCode(string $size, string $baseUrl = ''): string
{
    $preset = widgetPreset($size);
    if ($preset === null) {
        return '';
    }
    $baseUrl = rtrim($baseUrl !== '' ? $baseUrl : appBaseUrl(), '/');
    $w = $preset['width'];
    $h = (int)$preset['height'];
    $limit = (int)$preset['limit'];
    $src = $baseUrl . '/widget.php?size=' . rawurlencode($size) . '&limit=' . $limit;
    $styleW = $w === '100%' ? 'width:100%;max-width:720px' : ('width:' . $w);
    return '<iframe' . "\n"
        . '  src="' . $src . '"' . "\n"
        . '  style="' . $styleW . ';height:' . $h . 'px;border:0;overflow:hidden;border-radius:12px;display:block"' . "\n"
        . '  loading="lazy"' . "\n"
        . '  title="KupiTelefon oglasi"' . "\n"
        . '></iframe>';
}

/**
 * Nasumični javni oglasi za partner embed widget.
 *
 * @return list<array{id:int,title:string,price:string,location:string,image:?string,url:string,type:string}>
 */
function fetchWidgetAds(int $limit = 3, string $type = '', string $ref = ''): array
{
    require_once __DIR__ . '/ads_helpers.php';
    require_once __DIR__ . '/promotion.php';

    $limit = max(1, min(6, $limit));
    $type = trim($type);
    if (!in_array($type, ['telefon', 'delovi', 'servis', ''], true)) {
        $type = '';
    }
    $ref = resolveWidgetRef($ref);

    $filters = ['sort' => 'newest'];
    if ($type !== '') {
        $filters['types'] = [$type];
    }

    $all = array_values(getPublicAds($filters));
    if ($all === []) {
        return [];
    }

    $promoted = [];
    $regular = [];
    foreach ($all as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        if (isAdTopActive($ad) || isAdHighlighted($ad)) {
            $promoted[] = $ad;
        } else {
            $regular[] = $ad;
        }
    }

    shuffle($promoted);
    shuffle($regular);
    $picked = array_slice(array_merge($promoted, $regular), 0, $limit);

    $out = [];
    foreach ($picked as $ad) {
        $id = (int)($ad['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $path = adUrl($ad);
        $sep = str_contains($path, '?') ? '&' : '?';
        $query = http_build_query([
            'utm_source' => 'widget',
            'utm_medium' => 'embed',
            'utm_campaign' => $ref !== '' ? $ref : 'partner',
        ]);
        $img = adPrimaryListingThumb($ad);
        $out[] = [
            'id' => $id,
            'title' => (string)($ad['title'] ?? ''),
            'price' => formatAdPrice($ad),
            'location' => trim((string)($ad['location'] ?? '')),
            'image' => $img !== null && $img !== '' ? absoluteUrl($img) : null,
            'url' => absoluteUrl($path) . $sep . $query,
            'type' => getAdType($ad),
        ];
    }

    return $out;
}

function normalizeWidgetRef(string $ref): string
{
    $ref = strtolower(trim($ref));
    $ref = preg_replace('/[^a-z0-9_-]+/', '-', $ref) ?? '';
    $ref = trim($ref, '-_');
    return mb_substr($ref, 0, 40);
}

/**
 * Ako partner ne pošalje ref, uzmi hostname sa Referer-a (sajt gde je embed).
 */
function resolveWidgetRef(string $ref = ''): string
{
    $ref = normalizeWidgetRef($ref);
    if ($ref !== '') {
        return $ref;
    }

    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return '';
    }

    $host = parse_url($referer, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    $host = strtolower($host);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    $ownHost = parse_url(appBaseUrl(), PHP_URL_HOST);
    if (is_string($ownHost) && $ownHost !== '') {
        $ownHost = strtolower($ownHost);
        if (str_starts_with($ownHost, 'www.')) {
            $ownHost = substr($ownHost, 4);
        }
        if ($host === $ownHost || str_ends_with($host, '.' . $ownHost)) {
            return '';
        }
    }

    return normalizeWidgetRef($host);
}

function widgetPriceIsOpen(string $price): bool
{
    $p = mb_strtolower($price);
    return str_contains($p, 'dogovoru') || str_contains($p, 'kontakt');
}
