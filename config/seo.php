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
    $tag = siteTagline();
    return [
        'title' => $name . ($tag !== '' ? ' — ' . $tag : ''),
        'description' => 'Kupi i prodaj telefone, tablete i pametne satove, ili pronađi servis u Srbiji. Oglasi, provereni prodavci i mini sajtovi radnji na ' . $name . '.',
    ];
}

function seoListingMeta(array $filters = []): array
{
    $name = seoSiteName();
    $parts = [];
    $type = (string)($filters['type'] ?? '');
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
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
    if ($model !== '') {
        $parts[] = $model;
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
    $title = function_exists('adDisplayTitle') ? adDisplayTitle($ad) : trim((string)($ad['title'] ?? 'Oglas'));
    $location = trim((string)($ad['location'] ?? ''));
    $brand = trim((string)($ad['brand'] ?? ''));
    $priceLabel = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $bits = array_filter([$brand, $location, $priceLabel !== '' ? $priceLabel : null]);
    $descSource = trim((string)($ad['description'] ?? ''));
    if ($descSource === '') {
        $descSource = $title . ($bits ? ' — ' . implode(', ', $bits) : '');
    } elseif ($priceLabel !== '') {
        // Cena / namera na početku — bolji preview u Viber/FB
        $descSource = $priceLabel . ' · ' . $descSource;
    }
    return [
        'title' => $title . ($priceLabel !== '' ? ' · ' . $priceLabel : '') . ($location !== '' ? ' · ' . $location : '') . ' — ' . $name,
        'description' => seoTruncate($descSource),
    ];
}

function seoListingHeading(array $filters = []): string
{
    $type = (string)($filters['type'] ?? '');
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));

    $subject = match ($type) {
        'delovi' => 'Delovi i oprema',
        'servis' => 'Servisne usluge',
        'telefon' => 'Telefoni i uređaji',
        default => 'Oglasi',
    };
    if ($brand !== '' && $model !== '') {
        $subject = $brand . ' ' . $model;
    } elseif ($brand !== '') {
        $subject = $brand . ' — telefoni i oprema';
    }

    return $subject . ($location !== '' ? ' — ' . $location : '');
}

function seoListingCollectionJsonLd(array $filters, array $ads, string $url, string $description = '', int $limit = 10): array
{
    $items = [];
    $position = 0;
    foreach ($ads as $ad) {
        if ($position >= $limit) {
            break;
        }
        $position++;
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'url' => absoluteUrl(adUrl($ad)),
            'name' => (string)($ad['title'] ?? 'Oglas'),
        ];
    }

    return array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => seoListingHeading($filters),
        'url' => $url,
        'description' => $description !== '' ? seoTruncate($description) : null,
        'mainEntity' => $items === [] ? null : [
            '@type' => 'ItemList',
            'numberOfItems' => count($ads),
            'itemListElement' => $items,
        ],
    ], static fn($v) => $v !== null && $v !== '');
}

function seoShopMeta(array $user, string $shopName, ?string $categoryName = null): array
{
    $name = seoSiteName();
    $location = trim((string)($user['location'] ?? ''));
    $bio = trim((string)($user['shop_bio'] ?? ''));
    $categoryName = $categoryName !== null ? trim($categoryName) : '';
    if ($categoryName !== '') {
        $desc = $categoryName . ' u izlogu ' . $shopName
            . ($location !== '' ? ' · ' . $location : '')
            . ' na ' . $name . '.';
        return [
            'title' => $categoryName . ' — ' . $shopName . ' — Izlog — ' . $name,
            'description' => seoTruncate($desc),
        ];
    }
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
        'description' => siteTagline(),
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
    $logo = userShopLogoUrl($user);
    if ($cover !== '') {
        $data['image'] = absoluteUrl($cover);
    } elseif ($logo !== '') {
        $data['image'] = absoluteUrl($logo);
    }
    return $data;
}

function seoGuideHubMeta(): array
{
    $name = seoSiteName();
    return [
        'title' => 'Vodiči za kupovinu i servis telefona — ' . $name,
        'description' => seoTruncate('Praktični vodiči za bezbednu kupovinu telefona, proveru uređaja i odluke oko servisa.'),
    ];
}

function seoGuideMeta(array $guide): array
{
    $name = seoSiteName();
    $title = trim((string)($guide['seo_title'] ?? ''));
    if ($title === '') {
        $title = trim((string)($guide['title'] ?? 'Vodič')) . ' — ' . $name;
    }
    $description = trim((string)($guide['seo_description'] ?? ''));
    if ($description === '') {
        $description = trim((string)($guide['excerpt'] ?? ''));
    }
    return [
        'title' => $title,
        'description' => seoTruncate($description !== '' ? $description : 'Vodič na ' . $name . '.', 170),
    ];
}

function seoGuideJsonLd(array $guide): array
{
    $url = absoluteUrl(guideUrl($guide));
    $author = findUserById((int)($guide['author_id'] ?? 0));
    $authorName = $author ? (string)($author['full_name'] ?? $author['username'] ?? seoSiteName()) : seoSiteName();
    $image = trim((string)($guide['og_image'] ?? ''));
    return array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => (string)($guide['title'] ?? 'Vodič'),
        'description' => seoTruncate((string)($guide['excerpt'] ?? ''), 220),
        'url' => $url,
        'datePublished' => (string)($guide['published_at'] ?? $guide['created_at'] ?? ''),
        'dateModified' => (string)($guide['updated_at'] ?? $guide['published_at'] ?? ''),
        'author' => [
            '@type' => 'Person',
            'name' => $authorName,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => seoSiteName(),
            'url' => appBaseUrl() . '/',
        ],
        'image' => $image !== '' ? absoluteUrl($image) : null,
    ], static fn($v) => $v !== null && $v !== '');
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
