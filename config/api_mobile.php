<?php

declare(strict_types=1);

/**
 * Shared helpers for KupiTelefon mobile JSON API.
 * Additive only — does not change website HTML flows.
 */

function mobileApiJson(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mobileApiCors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function mobileFormatAdCard(array $ad): array
{
    $img = function_exists('adPrimaryListingThumb') ? adPrimaryListingThumb($ad) : null;
    $path = function_exists('adUrl') ? adUrl($ad) : ('/oglas.php?id=' . (int)($ad['id'] ?? 0));
    $price = function_exists('formatAdPrice') ? formatAdPrice($ad) : '';
    if (function_exists('adCardPriceMainLabel')) {
        $price = adCardPriceMainLabel($ad);
    }

    return [
        'id' => (int)($ad['id'] ?? 0),
        'title' => (string)(function_exists('adDisplayTitle') ? adDisplayTitle($ad) : ($ad['title'] ?? '')),
        'price' => (string)$price,
        'price_note' => function_exists('formatAdPriceRsd') ? (string)formatAdPriceRsd($ad) : '',
        'location' => trim((string)($ad['location'] ?? '')),
        'image' => ($img !== null && $img !== '') ? absoluteUrl((string)$img) : null,
        'path' => $path,
        'url' => absoluteUrl($path),
        'type' => function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? 'telefon'),
        'listing_type' => function_exists('getAdListingType') ? getAdListingType($ad) : 'sell',
        'is_top' => function_exists('isAdTopActive') ? isAdTopActive($ad) : !empty($ad['is_promoted']),
        'is_sold' => !empty($ad['is_sold']),
        'is_active' => (int)($ad['is_active'] ?? 0) === 1,
        'status' => !empty($ad['is_sold'])
            ? 'sold'
            : ((int)($ad['is_active'] ?? 0) === 1 ? 'active' : 'inactive'),
        'status_label' => !empty($ad['is_sold'])
            ? 'Prodato'
            : ((int)($ad['is_active'] ?? 0) === 1 ? 'Aktivan' : 'Neaktivan'),
        'views' => (int)($ad['views'] ?? 0),
        'created_at' => (string)($ad['created_at'] ?? ''),
        'relative' => function_exists('formatRelativeTime') ? formatRelativeTime((string)($ad['created_at'] ?? '')) : '',
        'favorite' => function_exists('isFavorite') ? isFavorite((int)($ad['id'] ?? 0)) : false,
    ];
}

function mobileCsrfFromRequest(): string
{
    $token = (string)($_POST['_csrf'] ?? $_POST['csrf'] ?? '');
    if ($token === '') {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    return $token;
}

function mobileFormatAdDetail(array $ad): array
{
    $card = mobileFormatAdCard($ad);
    $images = [];
    if (is_array($ad['images'] ?? null)) {
        foreach ($ad['images'] as $img) {
            $img = (string)$img;
            if ($img === '') {
                continue;
            }
            $images[] = absoluteUrl($img);
        }
    }

    $seller = null;
    $partnerId = 0;
    if (!empty($ad['created_by'])) {
        $user = findUserById((int)$ad['created_by']);
        if (is_array($user)) {
            $partnerId = (int)($user['id'] ?? 0);
            $seller = [
                'id' => $partnerId,
                'name' => trim((string)($user['shop_name'] ?? $user['full_name'] ?? $user['username'] ?? 'Prodavac')),
                'username' => (string)($user['username'] ?? ''),
                'location' => trim((string)($user['location'] ?? '')),
                'verified_phone' => function_exists('isPhoneVerified') ? isPhoneVerified($user) : false,
            ];
        }
    }

    $facts = [];
    if (!empty($ad['brand'])) {
        $facts[] = ['label' => 'Brend', 'value' => (string)$ad['brand']];
    }
    if (!empty($ad['model'])) {
        $facts[] = ['label' => 'Model', 'value' => (string)$ad['model']];
    }
    if (!empty($ad['storage'])) {
        $facts[] = ['label' => 'Memorija', 'value' => (string)$ad['storage']];
    }
    if (isset($ad['battery_health']) && $ad['battery_health'] !== '' && $ad['battery_health'] !== null) {
        $facts[] = ['label' => 'BH', 'value' => ((int)$ad['battery_health']) . '%'];
    }
    if (!empty($ad['condition_state'])) {
        $facts[] = ['label' => 'Stanje', 'value' => (string)$ad['condition_state']];
    }

    $contactPhone = trim((string)($ad['contact_phone'] ?? ''));
    $callUrl = $contactPhone !== '' ? ('tel:' . preg_replace('/\s+/', '', $contactPhone)) : null;

    return array_merge($card, [
        'description' => (string)($ad['description'] ?? ''),
        'images' => $images !== [] ? $images : array_values(array_filter([$card['image']])),
        'facts' => $facts,
        'chips' => array_values(array_filter([
            $card['type'] === 'telefon' ? 'Telefon' : ($card['type'] === 'delovi' ? 'Delovi' : ($card['type'] === 'servis' ? 'Servis' : '')),
            (string)($ad['brand'] ?? ''),
            (string)($ad['storage'] ?? ''),
        ])),
        'seller' => $seller,
        'partner_id' => $partnerId,
        'call_url' => $callUrl,
        'message_path' => $partnerId > 0
            ? ('/poruke.php?ad=' . (int)$ad['id'] . '&with=' . $partnerId)
            : '/poruke.php',
    ]);
}

function mobileBuildAdFiltersFromRequest(): array
{
    $type = trim((string)($_GET['type'] ?? ''));
    if (!in_array($type, ['telefon', 'delovi', 'servis', ''], true)) {
        $type = '';
    }
    $equipmentGroup = trim((string)($_GET['equipment_group'] ?? ''));
    if ($type === '' && trim((string)($_GET['category'] ?? '')) === 'oprema') {
        $type = 'delovi';
        $equipmentGroup = 'oprema';
    }

    $sort = trim((string)($_GET['sort'] ?? 'newest'));
    if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'relevance'], true)) {
        $sort = 'newest';
    }

    $filters = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'brand' => trim((string)($_GET['brand'] ?? '')),
        'model' => trim((string)($_GET['model'] ?? '')),
        'location' => trim((string)($_GET['location'] ?? '')),
        'condition' => trim((string)($_GET['condition'] ?? '')),
        'listing_type' => trim((string)($_GET['listing_type'] ?? '')),
        'device_type' => trim((string)($_GET['device_type'] ?? '')),
        'equipment_group' => $equipmentGroup,
        'equipment_type' => trim((string)($_GET['equipment_type'] ?? '')),
        'min_battery' => trim((string)($_GET['min_battery'] ?? '')),
        'min_price' => trim((string)($_GET['min_price'] ?? '')),
        'max_price' => trim((string)($_GET['max_price'] ?? '')),
        'sort' => $sort,
        'types' => $type !== '' ? [$type] : [],
    ];

    return array_filter(
        $filters,
        static fn($v) => !(is_string($v) && $v === '') && !(is_array($v) && $v === [])
    );
}
