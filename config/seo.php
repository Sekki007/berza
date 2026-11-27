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

function seoStripRepeatedSiteName(string $text, string $siteName): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    $siteName = trim($siteName);
    if ($text === '' || $siteName === '') {
        return $text;
    }
    $quoted = preg_quote($siteName, '/');
    $previous = null;
    while ($previous !== $text) {
        $previous = $text;
        $text = trim((string)preg_replace('/^(?:' . $quoted . ')\s*[|\-–—:]+\s*/iu', '', $text));
        $text = trim((string)preg_replace('/\s*[|\-–—:]+\s*(?:' . $quoted . ')\s*$/iu', '', $text));
    }
    if (mb_strtolower($text) === mb_strtolower($siteName)) {
        return '';
    }
    return $text;
}

function seoTitleBrand(): string
{
    $name = trim(seoSiteName());
    if ($name === '') {
        return 'KupiTelefon.rs';
    }
    if (preg_match('/kupitelefon/iu', $name) === 1 && !str_contains(mb_strtolower($name), '.rs')) {
        return 'KupiTelefon.rs';
    }
    return $name;
}

/** Naslov do $max znakova, sa sufiksom „ | brend” kad stane. */
function seoFitTitle(string $lead, int $max = 60): string
{
    $lead = trim(preg_replace('/\s+/u', ' ', $lead) ?? $lead);
    $suffix = ' | ' . seoTitleBrand();
    $full = $lead . $suffix;
    if (mb_strlen($full) <= $max) {
        return $full;
    }
    $room = $max - mb_strlen($suffix);
    if ($room < 12) {
        return seoTruncate($full, $max);
    }
    return rtrim(mb_substr($lead, 0, $room - 1)) . '…' . $suffix;
}

function seoFitDescription(string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return seoTruncate($text, $max);
}

function seoAppendPageSuffix(string $title, int $page, int $max = 60): string
{
    if ($page <= 1) {
        return $title;
    }
    $pageSuffix = ' — strana ' . $page;
    if (preg_match('/^(.*)( \| .+)$/u', $title, $m) === 1) {
        $full = $m[1] . $pageSuffix . $m[2];
        if (mb_strlen($full) <= $max) {
            return $full;
        }
        $room = $max - mb_strlen($m[2]) - mb_strlen($pageSuffix);
        if ($room < 8) {
            return seoTruncate($full, $max);
        }
        return rtrim(mb_substr($m[1], 0, $room - 1)) . '…' . $pageSuffix . $m[2];
    }
    return seoTruncate($title . $pageSuffix, $max);
}

function seoHomeMeta(): array
{
    return [
        'title' => seoFitTitle('Polovni telefoni – kupi i prodaj besplatno'),
        'description' => seoFitDescription('Besplatno postavi oglas za polovni iPhone, Samsung ili Xiaomi. Kupovina i prodaja u Beogradu, Novom Sadu i drugim gradovima, uz imenik servisa telefona.'),
        'h1' => 'Polovni telefoni, tableti i servis u Srbiji',
    ];
}

/** Datum za sitemap lastmod (YYYY-MM-DD) iz stvarnog zapisa, nikad "danas" kao zamena. */
function seoLastmodDate(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];
        if ($year >= 2000 && $year <= 2100 && checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    $ts = strtotime($raw);
    if ($ts === false || $ts < strtotime('2000-01-01')) {
        return '';
    }
    return date('Y-m-d', $ts);
}

/**
 * @param array{count?:int,min?:float|null,max?:float|null} $context
 */
function seoListingMeta(array $filters = [], array $context = []): array
{
    $name = seoSiteName();
    $parts = [];
    $type = (string)($filters['type'] ?? '');
    $equipmentGroup = trim((string)($filters['equipment_group'] ?? ''));
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));
    $deviceType = trim((string)($filters['device_type'] ?? ''));
    $q = trim((string)($filters['q'] ?? ''));
    $count = (int)($context['count'] ?? 0);
    $countLabel = $count === 1 ? '1 oglas' : ($count . ' oglasa');

    if ($type === 'telefon' && $brand === '' && $model === '' && $location === '' && $q === '' && $deviceType === '' && $equipmentGroup === '') {
        $countBit = $count > 0 ? ($countLabel . ' za polovne mobilne telefone. ') : '';
        return [
            'title' => seoFitTitle('Polovni mobilni telefoni – oglasi i cene'),
            'description' => seoFitDescription($countBit . 'iPhone, Samsung, Xiaomi i drugi brendovi, sa cenama i gradovima. Oglas se postavlja besplatno na ' . seoTitleBrand() . '.'),
            'h1' => 'Polovni mobilni telefoni',
        ];
    }
    if ($location !== '' && $brand === '' && $model === '' && $q === '' && $type === '' && $deviceType === '' && $equipmentGroup === '') {
        $lead = $count > 0
            ? ('Polovni telefoni ' . $location . ' – ' . $countLabel)
            : ('Polovni telefoni ' . $location);
        return [
            'title' => seoFitTitle($lead),
            'description' => seoFitDescription('Polovni telefoni ' . $location . ($count > 0 ? ': ' . $countLabel : '') . '. Aktuelne cene, prodavci i besplatno postavljanje oglasa na ' . seoTitleBrand() . '.'),
            'h1' => 'Polovni telefoni ' . $location,
        ];
    }
    if ($brand !== '' && $location !== '' && $model === '' && $q === '' && $type === '' && $deviceType === '' && $equipmentGroup === '') {
        return [
            'title' => seoFitTitle('Polovni ' . $brand . ' telefoni ' . $location),
            'description' => seoFitDescription('Polovni ' . $brand . ' telefoni u gradu ' . $location . ($count > 0 ? ' — ' . $countLabel : '') . '. Cene, modeli i oglasi na ' . seoTitleBrand() . '.'),
            'h1' => 'Polovni ' . $brand . ' telefoni ' . $location,
        ];
    }
    if ($brand !== '' && $model === '' && $location === '' && $q === '' && $type === '' && $deviceType === '' && $equipmentGroup === '') {
        $lead = $count > 0
            ? ('Polovni ' . $brand . ' telefoni – ' . $countLabel . ' i cene')
            : ('Polovni ' . $brand . ' telefoni');
        return [
            'title' => seoFitTitle($lead),
            'description' => seoFitDescription('Polovni ' . $brand . ' telefoni' . ($count > 0 ? ': ' . $countLabel : '') . '. Modeli, memorija i cene po gradovima Srbije na ' . seoTitleBrand() . '.'),
            'h1' => 'Polovni ' . $brand . ' telefoni',
        ];
    }

    if ($type === 'telefon' || $type === '') {
        // keep broad
    }
    if ($type === 'telefon') {
        $parts[] = 'Telefoni';
    } elseif ($type === 'delovi') {
        $parts[] = $equipmentGroup === 'oprema' ? 'Oprema' : ($equipmentGroup === 'parts' ? 'Delovi' : 'Delovi i oprema');
    } elseif ($type === 'servis') {
        $parts[] = 'Servis';
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

function seoAdHeadline(array $ad): string
{
    $type = function_exists('getAdType') ? getAdType($ad) : '';
    $brand = trim((string)($ad['brand'] ?? ''));
    $model = trim((string)($ad['model'] ?? ''));
    $storage = trim((string)($ad['storage'] ?? ''));
    if (($type === 'telefon' || $type === '') && $model !== '') {
        $bits = [];
        if ($brand !== '' && $brand !== 'Ostalo' && mb_stripos($model, $brand) !== 0) {
            $bits[] = $brand;
        }
        $bits[] = $model;
        $compactModel = mb_strtolower((string)preg_replace('/\s+/u', '', $model));
        $compactStorage = mb_strtolower((string)preg_replace('/\s+/u', '', $storage));
        if ($storage !== '' && ($compactStorage === '' || !str_contains($compactModel, $compactStorage))) {
            $bits[] = $storage;
        }
        $head = trim(implode(' ', $bits));
        if ($head !== '') {
            return $head;
        }
    }
    $title = function_exists('adDisplayTitle') ? adDisplayTitle($ad) : trim((string)($ad['title'] ?? ''));
    return $title !== '' ? $title : 'Oglas';
}

function seoAdImageAlt(array $ad, int $photoNumber): string
{
    $brand = trim((string)($ad['brand'] ?? ''));
    $model = trim((string)($ad['model'] ?? ''));
    if ($model !== '') {
        $name = ($brand !== '' && $brand !== 'Ostalo' && mb_stripos($model, $brand) !== 0)
            ? $brand . ' ' . $model
            : $model;
    } else {
        $name = seoAdHeadline($ad);
    }
    return $name . ' — slika ' . max(1, $photoNumber);
}

function seoAdMeta(array $ad): array
{
    $name = seoSiteName();
    $headline = seoAdHeadline($ad);
    $location = trim((string)($ad['location'] ?? ''));
    $brand = trim((string)($ad['brand'] ?? ''));
    $priceLabel = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $titleParts = array_values(array_filter(
        [$headline, $priceLabel !== '' ? $priceLabel : null, $location !== '' ? $location : null],
        static fn($part) => $part !== null && $part !== ''
    ));
    $title = implode(' · ', $titleParts) . ' | ' . $name;
    $bits = array_filter([$brand, $location, $priceLabel !== '' ? $priceLabel : null]);
    $descSource = trim((string)($ad['description'] ?? ''));
    if (function_exists('sanitizeAdPublicText')) {
        $descSource = sanitizeAdPublicText($descSource);
    }
    if ($descSource === '') {
        $descSource = $headline . ($bits ? ' — ' . implode(', ', $bits) : '');
    } elseif ($priceLabel !== '') {
        // Cena / namera na početku — bolji preview u Viber/FB
        $descSource = $priceLabel . ' · ' . $descSource;
    }
    return [
        'title' => $title,
        'description' => seoTruncate($descSource),
    ];
}

function seoListingHeading(array $filters = []): string
{
    $type = (string)($filters['type'] ?? '');
    $equipmentGroup = trim((string)($filters['equipment_group'] ?? ''));
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));

    if ($type === 'telefon' && $brand === '' && $model === '' && $location === '' && $equipmentGroup === '') {
        return 'Polovni mobilni telefoni';
    }
    if ($location !== '' && $brand === '' && $model === '' && $type === '' && $equipmentGroup === '') {
        return 'Polovni telefoni ' . $location;
    }
    if ($brand !== '' && $location !== '' && $model === '' && $type === '' && $equipmentGroup === '') {
        return 'Polovni ' . $brand . ' telefoni ' . $location;
    }
    if ($brand !== '' && $model === '' && $location === '' && $type === '' && $equipmentGroup === '') {
        return 'Polovni ' . $brand . ' telefoni';
    }

    $subject = match ($type) {
        'delovi' => $equipmentGroup === 'oprema' ? 'Oprema' : ($equipmentGroup === 'parts' ? 'Delovi' : 'Delovi i oprema'),
        'servis' => 'Servis',
        'telefon' => 'Telefoni',
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
            'target' => appBaseUrl() . '/oglasi?q={search_term_string}',
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

/**
 * @param list<array{href:string,label:string}> $links
 * @param list<array> $similarAds
 */
function renderPublicStatusPage(
    int $status,
    string $heading,
    string $message,
    array $links = [],
    array $similarAds = [],
    string $similarHeading = 'Slični oglasi'
): void {
    http_response_code($status);
    $pageTitle = $heading . ' — ' . seoSiteName();
    $pageDescription = seoTruncate($message);
    $robotsMeta = 'noindex,follow';
    $activePage = 'oglasi';
    $showSearch = true;
    $bodyClass = 'page-status';
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $canonicalUrl = absoluteUrl($path === '' ? '/' : $path);
    $statusSimilarAds = $similarAds;
    require dirname(__DIR__) . '/public/partials/layout-start.php';
    require dirname(__DIR__) . '/public/partials/status-page.php';
    require dirname(__DIR__) . '/public/partials/layout-end.php';
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

function seoJoinList(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $items), static fn($v) => $v !== ''));
    $n = count($items);
    if ($n === 0) {
        return '';
    }
    if ($n === 1) {
        return $items[0];
    }
    if ($n === 2) {
        return $items[0] . ' i ' . $items[1];
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' i ' . $last;
}

/** @return array{min:?float,max:?float} */
function seoAdsPriceExtent(array $ads): array
{
    $prices = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        if (function_exists('isAdPriceOpen') && isAdPriceOpen($ad)) {
            continue;
        }
        $currency = strtolower(trim((string)($ad['currency'] ?? 'eur')));
        if ($currency !== '' && $currency !== 'eur' && $currency !== '€') {
            continue;
        }
        $price = (float)($ad['price'] ?? 0);
        if ($price <= 0) {
            continue;
        }
        $prices[] = $price;
    }
    if ($prices === []) {
        return ['min' => null, 'max' => null];
    }
    return ['min' => min($prices), 'max' => max($prices)];
}

/** @return list<string> */
function seoTopAdValues(array $ads, string $field, int $limit = 3): array
{
    $counts = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $value = trim((string)($ad[$field] ?? ''));
        if ($value === '' || $value === 'Ostalo') {
            continue;
        }
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    arsort($counts);
    return array_slice(array_keys($counts), 0, $limit);
}

function seoMoneyEur(?float $amount): string
{
    if ($amount === null) {
        return '';
    }
    $rounded = abs($amount - round($amount)) < 0.05 ? (string)(int)round($amount) : number_format($amount, 0, ',', '.');
    return $rounded . ' €';
}

/**
 * 2–3 rečenice iz stvarnih oglasa, za brend, grad i brend+grad.
 */
function seoListingIntro(array $filters, array $ads): string
{
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));
    if ($model !== '' || $type !== '' || ($brand === '' && $location === '')) {
        return '';
    }
    $count = count($ads);
    if ($count < 1) {
        return '';
    }
    $countLabel = $count === 1 ? '1 aktivan oglas' : ($count . ' aktivnih oglasa');
    $extent = seoAdsPriceExtent($ads);
    $priceSentence = '';
    if ($extent['min'] !== null && $extent['max'] !== null) {
        $priceSentence = $extent['min'] === $extent['max']
            ? ('Cena u oglasima je ' . seoMoneyEur($extent['min']) . '.')
            : ('Cene se kreću od ' . seoMoneyEur($extent['min']) . ' do ' . seoMoneyEur($extent['max']) . '.');
    }
    if ($brand !== '' && $location !== '') {
        $models = seoJoinList(seoTopAdValues($ads, 'model', 3));
        $text = 'Polovni ' . $brand . ' telefoni u gradu ' . $location . ': ' . $countLabel . '.';
        if ($models !== '') {
            $text .= ' Najčešći modeli su ' . $models . '.';
        }
        if ($priceSentence !== '') {
            $text .= ' ' . $priceSentence;
        }
        return $text;
    }
    if ($brand !== '') {
        $models = seoJoinList(seoTopAdValues($ads, 'model', 3));
        $cities = seoJoinList(seoTopAdValues($ads, 'location', 3));
        $text = $brand . ' ima ' . $countLabel . ' na ' . seoTitleBrand() . '.';
        if ($priceSentence !== '') {
            $text .= ' ' . $priceSentence;
        }
        if ($models !== '') {
            $text .= ' Najčešći modeli su ' . $models . '.';
        }
        if ($cities !== '') {
            $text .= ' Najviše oglasa je u gradovima: ' . $cities . '.';
        }
        return $text;
    }
    $brands = seoJoinList(seoTopAdValues($ads, 'brand', 3));
    $text = 'U gradu ' . $location . ' trenutno ima ' . $countLabel . ' za polovne telefone.';
    if ($brands !== '') {
        $text .= ' Najzastupljeniji brendovi su ' . $brands . '.';
    }
    if ($priceSentence !== '') {
        $text .= ' ' . $priceSentence;
    }
    return $text;
}

function seoListingSectionHeading(array $filters): string
{
    $brand = trim((string)($filters['brand'] ?? ''));
    $location = trim((string)($filters['location'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));
    if ($brand !== '' && $model !== '') {
        return trim($brand . ' ' . $model) . ' oglasi';
    }
    if ($brand !== '') {
        return $brand . ' oglasi';
    }
    if ($location !== '') {
        return 'Oglasi u gradu ' . $location;
    }
    if ($type === 'delovi') {
        return 'Oglasi delova i opreme';
    }
    if ($type === 'servis') {
        return 'Oglasi servisa';
    }
    return 'Najnoviji oglasi';
}

/**
 * 301 za stare URL-ove. Vraća true ako je redirekcija poslata.
 * POST na oglas se ne preusmerava (poruka prodavcu).
 *
 * @param array<string,mixed> $query
 */
function seoTryLegacyRedirect(string $path, string $method = 'GET', ?array $query = null): bool
{
    $path = rawurldecode($path);
    $path = rtrim($path, '/') ?: '/';
    $method = strtoupper($method);
    $query ??= $_GET;
    $safe = $method === 'GET' || $method === 'HEAD';

    if (!$safe && ($path === '/oglas.php' || str_starts_with($path, '/oglas/'))) {
        return false;
    }
    if ($path === '/index.php' && $safe) {
        header('Location: ' . listingLegacyIndexTarget($query), true, 301);
        return true;
    }
    if ($path === '/oglas.php' && $safe) {
        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        $ad = getAdById($id);
        if (!is_array($ad)) {
            return false;
        }
        $canonical = adUrl($ad);
        $expectedSlug = '';
        if (preg_match('#^/oglas/\d+-(.*)$#', $canonical, $slugMatch) === 1) {
            $expectedSlug = (string)$slugMatch[1];
        }
        // Nginx rewrite šalje tačan slug u query. Tada je URL već kanonski i ne sme 301 (petlja).
        if (array_key_exists('slug', $query) && (string)$query['slug'] === $expectedSlug) {
            return false;
        }
        header('Location: ' . $canonical, true, 301);
        return true;
    }
    if ($safe && preg_match('#^/oglas/(\d+)(?:-.*)?$#', $path, $m) === 1) {
        $ad = getAdById((int)$m[1]);
        if (!is_array($ad)) {
            return false;
        }
        $canonical = adUrl($ad);
        if ($path !== $canonical) {
            header('Location: ' . $canonical, true, 301);
            return true;
        }
        return false;
    }
    if ($path === '/izlog_ocene.php' && $safe) {
        $user = trim((string)($query['u'] ?? ''));
        if ($user === '') {
            return false;
        }
        $rest = $query;
        unset($rest['u']);
        $target = '/izlog/' . rawurlencode($user) . '/ocene';
        if ($rest !== []) {
            $target .= '?' . http_build_query($rest);
        }
        header('Location: ' . $target, true, 301);
        return true;
    }
    return false;
}
