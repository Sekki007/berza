<?php

declare(strict_types=1);

function seoSiteName(): string
{
    return (string)(siteSettings()['site_name'] ?? 'KupiTelefon');
}

function seoTruncate(string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

function seoHomeMeta(): array
{
    $name = seoSiteName();
    return [
        'title' => $name . ' — telefoni, tableti, satovi i servis',
        'description' => 'Kupi i prodaj telefone, tablete i pametne satove, ili pronađi servis u Srbiji. Oglasi, provereni prodavci i mini sajtovi radnji na ' . $name . '.',
    ];
}

function seoListingMeta(array $filters = []): array
{
    $name = seoSiteName();
    $parts = [];
    $type = (string)($filters['type'] ?? '');
    $brand = trim((string)($filters['brand'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));
    $deviceType = trim((string)($filters['device_type'] ?? ''));
    $q = trim((string)($filters['q'] ?? ''));

    if ($type === 'telefon' || $type === '') {
        // keep broad
    }
    if ($type === 'telefon') {
        $parts[] = 'Uređaji';
    } elseif ($type === 'delovi') {
        $parts[] = 'Oprema i delovi';
    } elseif ($type === 'servis') {
        $parts[] = 'Servisne usluge';
    }
    if ($deviceType !== '') {
        $labels = allowedDeviceTypes();
        $parts[] = (string)($labels[$deviceType] ?? $deviceType);
    }
    if ($brand !== '') {
        $parts[] = $brand;
    }
    if ($location !== '') {
        $parts[] = $location;
    }
    if ($q !== '') {
        $parts[] = $q;
    }

    if ($parts === []) {
        return seoHomeMeta();
    }

    $label = implode(' · ', $parts);
    return [
        'title' => $label . ' — ' . $name,
        'description' => seoTruncate('Pregledaj oglase: ' . $label . '. Kupovina, prodaja i servis na ' . $name . '.'),
    ];
}

function seoAdMeta(array $ad): array
{
    $name = seoSiteName();
    $title = trim((string)($ad['title'] ?? 'Oglas'));
    $location = trim((string)($ad['location'] ?? ''));
    $brand = trim((string)($ad['brand'] ?? ''));
    $price = formatAdPrice($ad);
    $bits = array_filter([$brand, $location, $price !== '' ? $price : null]);
    $descSource = trim((string)($ad['description'] ?? ''));
    if ($descSource === '') {
        $descSource = $title . ($bits ? ' — ' . implode(', ', $bits) : '');
    }
    return [
        'title' => $title . ($location !== '' ? ' · ' . $location : '') . ' — ' . $name,
        'description' => seoTruncate($descSource),
    ];
}

function seoShopMeta(array $user, string $shopName): array
{
    $name = seoSiteName();
    $location = trim((string)($user['location'] ?? ''));
    $bio = trim((string)($user['shop_bio'] ?? ''));
    $desc = $bio !== ''
        ? $bio
        : ('Izlog prodavca ' . $shopName . ($location !== '' ? ' iz ' . $location : '') . ' na ' . $name . '.');
    return [
        'title' => $shopName . ' — Izlog — ' . $name,
        'description' => seoTruncate($desc),
    ];
}

function seoStorefrontMeta(array $user, string $shopName): array
{
    $name = seoSiteName();
    $title = trim((string)($user['shop_page_title'] ?? $shopName));
    $tagline = trim((string)($user['shop_page_tagline'] ?? ''));
    $desc = $tagline !== ''
        ? $tagline
        : ('Mini sajt i usluge radnje ' . $shopName . ' na ' . $name . '.');
    return [
        'title' => $title . ' — Usluge — ' . $name,
        'description' => seoTruncate($desc),
    ];
}

function seoOrganizationJsonLd(): array
{
    $name = seoSiteName();
    $site = siteSettings();
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $name,
        'url' => appBaseUrl() . '/',
        'description' => (string)($site['topbar_text'] ?? 'Telefoni, tableti, satovi i servis'),
    ];
    $email = trim((string)($site['contact_email'] ?? ''));
    $phone = trim((string)($site['contact_phone'] ?? ''));
    if ($email !== '') {
        $data['email'] = $email;
    }
    if ($phone !== '') {
        $data['telephone'] = $phone;
    }
    return $data;
}

function seoWebsiteJsonLd(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => seoSiteName(),
        'url' => appBaseUrl() . '/',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => appBaseUrl() . '/index.php?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function seoAdJsonLd(array $ad, ?array $seller = null): array
{
    $url = absoluteUrl(adUrl($ad));
    $images = [];
    foreach ((array)($ad['images'] ?? []) as $img) {
        if (is_string($img) && $img !== '') {
            $images[] = absoluteUrl($img);
        }
    }
    $price = (float)($ad['price'] ?? 0);
    $availability = !empty($ad['is_sold'])
        ? 'https://schema.org/SoldOut'
        : 'https://schema.org/InStock';

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => (string)($ad['title'] ?? 'Oglas'),
        'description' => seoTruncate((string)($ad['description'] ?? $ad['title'] ?? ''), 300),
        'url' => $url,
        'sku' => (string)((int)($ad['id'] ?? 0)),
        'category' => (string)($ad['category'] ?? getAdType($ad)),
    ];
    if ($images !== []) {
        $data['image'] = $images;
    }
    $brand = trim((string)($ad['brand'] ?? ''));
    if ($brand !== '') {
        $data['brand'] = ['@type' => 'Brand', 'name' => $brand];
    }
    if ($price > 0 && !isAdPriceOpen($ad)) {
        $data['offers'] = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'EUR',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $availability,
            'itemCondition' => 'https://schema.org/UsedCondition',
        ];
    }
    if ($seller) {
        $data['seller'] = [
            '@type' => 'Person',
            'name' => getSellerShopName($seller, [$ad]),
            'url' => absoluteUrl(shopUrlForUser($seller)),
        ];
    }
    return $data;
}

function seoStorefrontJsonLd(array $user, string $shopName): array
{
    $url = absoluteUrl(storefrontUrlForUser($user));
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => trim((string)($user['shop_page_title'] ?? $shopName)),
        'url' => $url,
        'description' => seoTruncate((string)($user['shop_page_tagline'] ?? $user['shop_page_description'] ?? $shopName), 300),
    ];
    $phone = trim((string)($user['phone'] ?? ''));
    $email = trim((string)($user['shop_page_contact_email'] ?? $user['email'] ?? ''));
    $address = trim((string)($user['shop_page_address'] ?? ''));
    $city = trim((string)($user['location'] ?? ''));
    if ($phone !== '') {
        $data['telephone'] = $phone;
    }
    if ($email !== '') {
        $data['email'] = $email;
    }
    if ($address !== '' || $city !== '') {
        $data['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $address !== '' ? $address : null,
            'addressLocality' => $city !== '' ? $city : null,
            'addressCountry' => 'RS',
        ];
        $data['address'] = array_filter($data['address'], static fn($v) => $v !== null && $v !== '');
    }
    $cover = trim((string)($user['shop_page_cover'] ?? ''));
    if ($cover !== '') {
        $data['image'] = absoluteUrl($cover);
    }
    return $data;
}

function seoJsonLdScript(array $payload): string
{
    if ($payload === []) {
        return '';
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return '';
    }
    return '<script type="application/ld+json">' . $json . '</script>';
}
