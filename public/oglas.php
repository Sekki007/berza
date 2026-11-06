<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ad = $id > 0 ? getAdById($id) : null;
$adIsPublic = is_array($ad)
    && (int)($ad['is_active'] ?? 0) === 1
    && empty($ad['is_sold']);

if (!$adIsPublic) {
    $goneTitle = is_array($ad) ? trim((string)($ad['title'] ?? '')) : '';
    if ($goneTitle !== '') {
        $goneMessage = 'Oglas „' . $goneTitle . '” više nije aktivan. Pogledaj slične oglase istog modela ili postavi svoj oglas besplatno.';
    } else {
        $goneMessage = 'Oglas više nije aktivan. Pogledaj slične oglase ili postavi svoj besplatno.';
    }
    $similar = [];
    if (is_array($ad)) {
        $similar = getSimilarAds($ad, 6);
    } else {
        $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (preg_match('#/oglas/\d+-([a-z0-9-]{2,})#i', rawurldecode($requestPath), $slugMatch) === 1) {
            $guess = trim(str_replace('-', ' ', (string)$slugMatch[1]));
            if ($guess !== '') {
                $similar = array_slice(getPublicAds(['q' => $guess, 'sort' => 'newest']), 0, 6);
            }
        }
    }
    $goneLinks = [
        ['href' => '/oglasi', 'label' => 'Svi oglasi'],
    ];
    if (is_array($ad)) {
        $goneBrand = trim((string)($ad['brand'] ?? ''));
        $goneModel = trim((string)($ad['model'] ?? ''));
        $goneLocation = trim((string)($ad['location'] ?? ''));
        if ($goneModel !== '') {
            $goneLinks[] = [
                'href' => '/oglasi?' . http_build_query(array_filter([
                    'brand' => $goneBrand,
                    'model' => $goneModel,
                ])),
                'label' => 'Isti model: ' . trim($goneBrand . ' ' . $goneModel),
            ];
        } elseif ($goneBrand !== '') {
            $goneLinks[] = [
                'href' => listingLandingPath(['brand' => $goneBrand]),
                'label' => 'Polovni telefoni ' . $goneBrand,
            ];
        }
        if ($goneLocation !== '') {
            $goneLinks[] = [
                'href' => listingLandingPath(['location' => $goneLocation]),
                'label' => 'Polovni telefoni ' . $goneLocation,
            ];
        }
    }
    $goneLinks[] = ['href' => isLoggedIn() ? '/postavi-oglas' : '/prijava', 'label' => 'Besplatno postavljanje oglasa'];
    $goneLinks[] = ['href' => '/', 'label' => 'Početna'];
    if (is_array($ad) && isLoggedIn() && (int)(currentUser()['id'] ?? 0) === (int)($ad['created_by'] ?? 0)) {
        $goneLinks[] = ['href' => '/nalog.php?tab=oglasi', 'label' => 'Moji oglasi'];
    }
    $similarHeading = (is_array($ad) && trim((string)($ad['model'] ?? '')) !== '')
        ? 'Slični oglasi istog modela'
        : 'Slični oglasi';
    renderPublicStatusPage(
        is_array($ad) ? 410 : 404,
        'Oglas više nije aktivan',
        $goneMessage,
        $goneLinks,
        $similar,
        $similarHeading
    );
    exit;
}

incrementAdViews($id);
$ad = getAdById($id) ?? $ad;
$site = siteSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(adUrl($ad));
    requireLogin();
    $siteCheck = siteSettings();
    if (empty($siteCheck['enable_messages'])) {
        setFlash('danger', 'Poruke trenutno nisu omogućene.');
        header('Location: ' . adUrl($ad));
        exit;
    }

    $body = trim((string)($_POST['message'] ?? ''));
    $toUserId = (int)($ad['created_by'] ?? 0);
    $fromUserId = (int)currentUser()['id'];

    if ($toUserId <= 0) {
        setFlash('danger', 'Prodavac nije pronađen.');
        header('Location: ' . adUrl($ad));
        exit;
    }
    if ($toUserId === $fromUserId) {
        setFlash('danger', 'Ne možeš slati poruku na sopstveni oglas. Odgovori iz inboxa ako ti neko piše.');
        header('Location: /poruke.php');
        exit;
    }
    if ($body === '') {
        setFlash('danger', 'Unesi tekst poruke.');
        header('Location: ' . adUrl($ad) . '#poruka');
        exit;
    }

    $saved = saveMessage([
        'ad_id' => (int)$ad['id'],
        'from_user_id' => $fromUserId,
        'from_name' => currentUser()['full_name'],
        'from_phone' => '',
        'to_user_id' => $toUserId,
        'body' => $body,
    ]);

    if ($saved) {
        setFlash('success', 'Poruka je poslata. Otvori inbox da pratiš odgovor.');
        queueFacebookPixelEvent('Lead', [
            'content_ids' => [(string)((int)$ad['id'])],
            'content_name' => (string)($ad['title'] ?? ''),
            'content_category' => getAdType($ad),
        ]);
        queueGoogleTagEvent('generate_lead', [
            'content_id' => (string)((int)$ad['id']),
            'content_name' => (string)($ad['title'] ?? ''),
            'content_category' => getAdType($ad),
        ]);
        header('Location: /poruke.php?ad=' . (int)$ad['id'] . '&with=' . $toUserId);
        exit;
    }

    setFlash('danger', 'Poruka nije poslata.');
    header('Location: ' . adUrl($ad) . '#poruka');
    exit;
}

$type = getAdType($ad);
$seller = findUserById((int)($ad['created_by'] ?? 1));
$sellerUsername = (string)($seller['username'] ?? '');
$sellerShopUrl = $seller ? shopUrlForUser($seller) : '';
$sellerName = $seller
    ? getSellerShopName($seller, [$ad])
    : (trim((string)($ad['shop_name'] ?? '')) ?: 'Prodavac');
$sellerInitials = mb_strtoupper(mb_substr($sellerName, 0, 1));
if ($sellerInitials === '') {
    $sellerInitials = '?';
}
$sellerSummary = $seller ? getSellerRatingSummary((int)$seller['id']) : ['positive' => 0, 'negative' => 0, 'count' => 0];
$sellerAdsCount = count(getPublicAdsByUserId((int)($ad['created_by'] ?? 0)));
$sellerLocation = (string)($ad['location'] ?? '');
$memberSince = '';
if (!empty($seller['created_at'])) {
    $ts = strtotime((string)$seller['created_at']);
    $memberSince = $ts ? date('d.m.Y.', $ts) : '';
}
$images = is_array($ad['images'] ?? null) ? $ad['images'] : [];
$imageCount = count($images);
$similarAds = array_values(array_filter(
    getSimilarAds($ad, 4),
    static fn($item): bool => is_array($item)
        && (int)($item['id'] ?? 0) > 0
        && (int)($item['id'] ?? 0) !== $id
        && (int)($item['is_active'] ?? 0) === 1
        && empty($item['is_sold'])
));
$isBuy = isBuyListing($ad);
$isTrade = isTradeListing($ad);
$intentBadge = adIntentBadgeLabel($ad);
$displayTitle = adDisplayTitle($ad);
$waMsg = $isBuy
    ? ('Zdravo, imam: ' . $displayTitle . (!isAdPriceOpen($ad) && (float)($ad['price'] ?? 0) > 0 ? (' — budžet ' . formatAdPrice($ad)) : '') . '. Da li te interesuje?')
    : ('Zdravo, interesuje me oglas: ' . $displayTitle . ' — ' . formatAdPrice($ad));
$isFav = isFavorite($id);
$isOwnAd = isLoggedIn() && (int)currentUser()['id'] === (int)($ad['created_by'] ?? 0);
$phone = (string)($ad['contact_phone'] ?? '');
$sellerUserId = (int)($ad['created_by'] ?? 0);
$composeUrl = '/poruke.php?ad=' . $id . '&with=' . $sellerUserId;
if ($isOwnAd) {
    $msgHref = '/poruke.php';
} elseif (isLoggedIn()) {
    $msgHref = $composeUrl;
} else {
    $msgHref = '/prijava?next=' . rawurlencode($composeUrl);
}
$price = (float)($ad['price'] ?? 0);
$priceOpen = isAdPriceOpen($ad);

$pageSeo = seoAdMeta($ad);
$pageTitle = $pageSeo['title'];
$activePage = 'oglasi';
$showSearch = true;
$pageDescription = $pageSeo['description'];
$primaryImg = adPrimaryImage($ad);
$ogRel = ensureAdOgImage($ad);
$ogCachedRel = adOgImagePath((int)$ad['id']);
if ($ogRel !== '' && $ogRel === $ogCachedRel) {
    $pageImage = absoluteUrl($ogRel);
    $ogImageWidth = 1200;
    $ogImageHeight = 630;
    $ogImageType = 'image/jpeg';
} elseif ($ogRel !== '') {
    $pageImage = absoluteUrl($ogRel);
} elseif ($primaryImg) {
    $pageImage = absoluteUrl($primaryImg);
} else {
    $pageImage = '';
}
$galleryDisplay = array_map(static fn($img) => adGalleryDisplayUrl((string)$img), $images);
if ($galleryDisplay !== []) {
    $preloadImage = $galleryDisplay[0];
}
$canonicalUrl = absoluteUrl(adUrl($ad));
$ogType = 'product';
$jsonLd = seoAdJsonLd($ad, $seller);
$inCompare = isInCompare($id);
$adBrand = (string)($ad['brand'] ?? '');
$adCategory = (string)($ad['category'] ?? '');

$fbViewParams = [
    'content_ids' => [(string)$id],
    'content_type' => 'product',
    'content_name' => (string)($ad['title'] ?? ''),
    'content_category' => getAdType($ad),
];
if (!isAdPriceOpen($ad) && adPriceEur($ad) > 0) {
    $fbViewParams['value'] = round(adPriceEur($ad), 2);
    $fbViewParams['currency'] = 'EUR';
}
facebookPixelPageEvent('ViewContent', $fbViewParams);
googleTagPageEvent('view_item', [
    'item_id' => (string)$id,
    'item_name' => (string)($ad['title'] ?? ''),
    'item_category' => getAdType($ad),
    'currency' => 'EUR',
    'value' => (!isAdPriceOpen($ad) && adPriceEur($ad) > 0) ? round(adPriceEur($ad), 2) : 0,
]);

$contactLinks = adContactActionLinks($ad, !empty($site['enable_whatsapp']), $waMsg);
$highlightChips = adHighlightChips($ad);
$keyFacts = adKeyFactCards($ad);
$accessoryLabels = adAccessoryLabels($ad);
$pickupMethods = adStoredPickupMethods($ad);
$descriptionText = sanitizeAdPublicText((string)($ad['description'] ?? ''));
$descriptionLong = adDescriptionIsLong($descriptionText);
$descriptionPreview = '';
if ($descriptionLong) {
    $descriptionPreview = mb_substr(trim(preg_replace('/\s+/u', ' ', $descriptionText) ?? $descriptionText), 0, 220);
    if (mb_strlen(trim($descriptionText)) > 220) {
        $descriptionPreview = rtrim($descriptionPreview, " \t.,;:!?") . '…';
    }
}
$attrRows = adAttributeRows($ad);
$subtitleParts = [];
$brandPart = trim((string)($ad['brand'] ?? ''));
if ($brandPart === 'Ostalo') {
    $brandPart = '';
}
foreach ([
    $brandPart,
    trim((string)($ad['model'] ?? '')),
    trim($sellerLocation),
    formatRelativeTime((string)($ad['created_at'] ?? '')),
] as $subtitlePart) {
    if ($subtitlePart !== '' && !in_array($subtitlePart, $subtitleParts, true)) {
        $subtitleParts[] = $subtitlePart;
    }
}
$bodyClass = 'page-detail';

require __DIR__ . '/partials/layout-start.php';

require __DIR__ . '/partials/ad-detail.php';
