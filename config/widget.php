<?php

declare(strict_types=1);

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

    // Ne koristi naš domen kao "partner"
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
