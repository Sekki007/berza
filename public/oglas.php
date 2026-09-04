<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ad = $id > 0 ? getAdById($id) : null;

if (!$ad || (int)($ad['is_active'] ?? 0) !== 1) {
    http_response_code(404);
    echo 'Oglas nije pronađen.';
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
$similarAds = getSimilarAds($ad);
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
$bodyClass = 'page-detail';
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

require __DIR__ . '/partials/layout-start.php';

$sellerBlock = static function () use (
    $sellerShopUrl,
    $sellerInitials,
    $sellerName,
    $seller,
    $memberSince,
    $sellerLocation,
    $sellerSummary,
    $sellerAdsCount,
    $site,
    $msgHref,
    $isOwnAd,
    $phone,
    $id,
    $waMsg
): void {
    $online = isUserOnline(is_array($seller) ? $seller : null);
    $allAdsHref = $sellerShopUrl !== '' ? $sellerShopUrl : '';
    ?>
        <div class="kp-card kp-seller-profile">
            <?php if (!empty($site['enable_messages'])): ?>
                <a class="kp-seller-msg-btn" href="<?= h($msgHref) ?>">
                    <span class="kp-seller-msg-ico" aria-hidden="true">💬</span>
                    <?= $isOwnAd ? 'Otvori poruke' : 'Pošaljite poruku' ?>
                </a>
            <?php endif; ?>
            <p class="kp-seller-reply">
                <?php if ($isOwnAd): ?>
                    Ovo je tvoj oglas — odgovori kupcima iz inboxa.
                <?php elseif ($online): ?>
                    Trenutno je online i obično brzo odgovara na poruke.
                <?php else: ?>
                    Odgovara na poruke, uglavnom u roku od nekoliko sati.
                <?php endif; ?>
            </p>

            <div class="kp-seller-identity">
                <?= renderShopAvatarHtml($seller, $sellerInitials, 'kp-seller-avatar') ?>
                <div class="kp-seller-id-text">
                    <div class="kp-seller-name-row">
                        <?php if ($sellerShopUrl !== ''): ?>
                            <a class="kp-seller-name-link" href="<?= h($sellerShopUrl) ?>"><?= h($sellerName) ?></a>
                        <?php else: ?>
                            <span class="kp-seller-name-plain"><?= h($sellerName) ?></span>
                        <?php endif; ?>
                        <?= renderSellerBadges($seller) ?>
                    </div>
                    <?= renderOnlineBadge(is_array($seller) ? $seller : null) ?>
                    <?php if ($sellerLocation !== ''): ?>
                        <div class="kp-seller-line"><?= h($sellerLocation) ?></div>
                    <?php endif; ?>
                    <?php if ($memberSince !== ''): ?>
                        <div class="kp-seller-line">Član od <?= h($memberSince) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kp-seller-rating">
                <?= renderReputation($sellerSummary, is_array($seller) ? shopReviewsUrl($seller) : null) ?>
            </div>

            <div class="kp-seller-links">
                <?php if ($allAdsHref !== ''): ?>
                    <a class="kp-seller-link" href="<?= h($allAdsHref) ?>">
                        <span aria-hidden="true">📋</span> Svi oglasi<?= $sellerAdsCount > 0 ? ' (' . (int)$sellerAdsCount . ')' : '' ?>
                    </a>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <button type="button" class="kp-seller-link kp-seller-link-btn" data-reveal-phone="<?= h($phone) ?>" data-ad-id="<?= (int)$id ?>">
                        <span aria-hidden="true">📞</span> Klik za broj telefona
                    </button>
                <?php endif; ?>
                <?php if ($phone !== '' && !empty($site['enable_whatsapp'])): ?>
                    <a class="kp-seller-link" href="<?= h(whatsappLink($phone, $waMsg)) ?>" target="_blank" rel="noopener">
                        <span aria-hidden="true">💬</span> WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php
};

$contactBlock = static function (string $formId = 'poruka') use (
    $site,
    $isOwnAd
): void {
    if (empty($site['enable_messages']) || !isLoggedIn() || $isOwnAd) {
        return;
    }
    ?>
    <div class="kp-card kp-contact-card">
        <form method="POST" id="<?= h($formId) ?>" class="kp-msg-form">
            <div class="form-group">
                <label>Poruka prodavcu</label>
                <textarea name="message" rows="3" placeholder="Zdravo, da li je oglas još aktivan?" required></textarea>
            </div>
            <button class="kp-btn-msg" type="submit" style="width:100%;">Pošalji</button>
        </form>
    </div>
    <?php
};
?>

<div class="main-wrap kp-detail-wrap" data-ad-id="<?= (int)$id ?>">
    <aside class="kp-detail-filter-col sidebar" aria-label="Filteri">
        <form method="GET" action="/index.php" class="filter-box">
            <div class="filter-head">Filteri</div>
            <div class="filter-body">
                <select class="filter-select" name="browse_cat">
                    <option value="">Sve kategorije</option>
                    <option value="telefon" <?= getAdType($ad) === 'telefon' ? 'selected' : '' ?>>Telefoni</option>
                    <option value="parts" <?= getAdType($ad) === 'delovi' && adEquipmentGroup($ad) === 'parts' ? 'selected' : '' ?>>Delovi</option>
                    <option value="oprema" <?= getAdType($ad) === 'delovi' && adEquipmentGroup($ad) !== 'parts' ? 'selected' : '' ?>>Oprema</option>
                    <option value="servis" <?= getAdType($ad) === 'servis' ? 'selected' : '' ?>>Servis</option>
                </select>
                <?php if (getAdType($ad) === 'telefon'): ?>
                <select class="filter-select" name="device_type">
                    <option value="">Tip uređaja (sve)</option>
                    <?php foreach (adFormSchema()['device_types'] as $dtKey => $dtLabel): ?>
                        <option value="<?= h($dtKey) ?>" <?= getAdDeviceType($ad) === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if ($adBrand !== ''): ?>
                    <input type="hidden" name="brand" value="<?= h($adBrand) ?>">
                <?php endif; ?>
                <?php if ($sellerLocation !== ''): ?>
                    <input type="hidden" name="location" value="<?= h($sellerLocation) ?>">
                <?php endif; ?>
                <select class="filter-select" name="sort">
                    <option value="newest">Najnovije</option>
                    <option value="price_asc">Cena rastuće</option>
                    <option value="price_desc">Cena opadajuće</option>
                </select>
                <button class="filter-apply" type="submit">Prikaži oglase</button>
            </div>
        </form>
    </aside>

    <main class="content kp-detail-main">
        <div class="breadcrumb kp-detail-breadcrumb">
            <a href="/index.php">Početna</a>
            <?php if ($adCategory !== ''): ?>
                › <a href="/index.php?type=<?= urlencode($type) ?>"><?= h($adCategory) ?></a>
            <?php endif; ?>
        </div>

        <div class="detail-gallery kp-gallery">
            <?php if ($images): ?>
                <div class="kp-gallery-track" id="gallery-track" style="display:flex;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;">
                    <?php foreach ($images as $i => $img): ?>
                        <?php $displaySrc = $galleryDisplay[$i] ?? (string)$img; ?>
                        <div class="kp-gallery-slide" style="flex:0 0 100%;min-width:100%;scroll-snap-align:start;aspect-ratio:1 / 1;">
                            <button type="button" class="kp-gallery-zoom" data-lightbox-open="<?= $i ?>" aria-label="Uvećaj sliku" style="display:flex;width:100%;height:100%;padding:0;border:none;background:transparent;">
                                <img
                                    src="<?= h($displaySrc) ?>"
                                    alt="<?= h((string)$ad['title']) ?>"
                                    width="800"
                                    height="800"
                                    decoding="async"
                                    <?= $i === 0 ? 'id="gallery-main" fetchpriority="high"' : 'loading="lazy"' ?>
                                >
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($imageCount > 1): ?>
                    <button type="button" class="kp-gallery-nav kp-gallery-prev" data-gallery-prev aria-label="Prethodna slika" onclick="return window.ktGalleryNav ? window.ktGalleryNav(-1) : false;" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);z-index:3;width:38px;height:38px;border:1px solid rgba(255,255,255,.6);border-radius:50%;background:rgba(17,24,39,.45);color:#fff;font-size:24px;line-height:1;">‹</button>
                    <button type="button" class="kp-gallery-nav kp-gallery-next" data-gallery-next aria-label="Sledeća slika" onclick="return window.ktGalleryNav ? window.ktGalleryNav(1) : false;" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);z-index:3;width:38px;height:38px;border:1px solid rgba(255,255,255,.6);border-radius:50%;background:rgba(17,24,39,.45);color:#fff;font-size:24px;line-height:1;">›</button>
                <?php endif; ?>
                <span class="kp-gallery-counter" data-gallery-counter>1 od <?= $imageCount ?></span>
                <?php if ($imageCount > 1): ?>
                    <div class="kp-gallery-thumbs" data-gallery-thumbs style="display:flex;gap:8px;overflow-x:auto;padding:10px;background:#fff;border-top:1px solid #edf0f4;">
                        <?php foreach ($images as $i => $img): ?>
                            <?php $thumbSrc = $galleryDisplay[$i] ?? (string)$img; ?>
                            <button
                                type="button"
                                class="kp-gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                data-gallery-thumb-index="<?= $i ?>"
                                aria-label="Prikaži sliku <?= $i + 1 ?>"
                                onclick="return window.ktGalleryGo ? window.ktGalleryGo(<?= $i ?>) : false;"
                                style="flex:0 0 auto;width:64px;height:64px;padding:0;border:2px solid <?= $i === 0 ? '#1a73e8' : 'transparent' ?>;border-radius:10px;overflow:hidden;background:#f4f6f8;line-height:0;<?= $i === 0 ? 'box-shadow:0 0 0 2px rgba(26,115,232,.16);' : '' ?>"
                            >
                                <img src="<?= h($thumbSrc) ?>" alt="<?= h((string)$ad['title']) ?> — slika <?= $i + 1 ?>" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="kp-gallery-slide">
                    <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>" style="width:90px;height:160px;">
                        <?= $type === 'telefon' ? '' : strtoupper(adCategoryLabel($ad)) ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($ad['is_sold'])): ?><span class="listing-badge-sold detail-sold"><?= $isBuy ? 'Pronađeno' : 'Prodato' ?></span><?php endif; ?>
            <?php if (!empty($ad['is_promoted'])): ?><span class="listing-badge-promo detail-promo">TOP</span><?php endif; ?>
        </div>

        <div class="kp-detail-head <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>">
            <div class="kp-title-row">
                <h1 class="kp-listing-title"><?= h($displayTitle) ?></h1>
            </div>
            <div class="kp-price-block <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>">
                <?php if ($isBuy): ?>
                    <div class="kp-price kp-price-intent"><?= h(adCardPriceMainLabel($ad)) ?></div>
                    <?php if (!$priceOpen && $price > 0): ?>
                        <span class="kp-price-note">Maksimalni budžet</span>
                    <?php else: ?>
                        <span class="kp-price-note">Kupovina — traži se uređaj</span>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="kp-price <?= $priceOpen ? 'kp-price-free' : '' ?>"><?= h($isTrade && $priceOpen ? 'Zamena' : formatAdPrice($ad)) ?></div>
                    <?php if (!$priceOpen): ?>
                        <?php $rsdHint = formatAdPriceRsd($ad); ?>
                        <?php if ($rsdHint !== ''): ?>
                            <div class="kp-price-rsd"><?= h($rsdHint) ?></div>
                        <?php endif; ?>
                        <span class="kp-price-note"><?= h(adPriceTypeLabel($ad)) ?></span>
                    <?php elseif ($isTrade): ?>
                        <span class="kp-price-note">Zamena uređaja</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="kp-action-links">
                <button type="button" class="kp-action-link" data-share-ad data-share-url="<?= h($canonicalUrl) ?>" data-share-title="<?= h((string)$ad['title']) ?>">↗ Podeli</button>
                <button type="button" class="kp-action-link" data-compare-toggle="<?= (int)$ad['id'] ?>" aria-pressed="<?= $inCompare ? 'true' : 'false' ?>">
                    <?= $inCompare ? '✓ U poređenju' : '⇄ Uporedi' ?>
                </button>
                <a class="kp-action-link kp-action-link-report" href="/report.php?ad=<?= (int)$ad['id'] ?>">Prijavi oglas</a>
            </div>
            <div class="kp-head-stats">
                <span title="Pregledi">👁 <?= (int)($ad['views'] ?? 0) ?></span>
                <span title="Omiljeni"><?= $isFav ? '♥' : '♡' ?></span>
                <span>↻ <?= h(formatRelativeTime((string)$ad['created_at'])) ?></span>
            </div>
        </div>

        <div class="kp-card">
            <h3 class="kp-section-title kp-section-title--compact">Osnovne informacije</h3>
            <div class="kp-info-cond">
                <?php if ($intentBadge !== ''): ?>
                    <strong><?= h($intentBadge) ?></strong>
                    <?php if (!empty($ad['condition_state'])): ?> · <?= h((string)$ad['condition_state']) ?><?php endif; ?>
                <?php elseif (!empty($ad['condition_state'])): ?>
                    <strong><?= h((string)$ad['condition_state']) ?></strong>
                    <?php if (!empty($ad['listing_type'])): ?> · <?= h(listingTypeLabel($ad)) ?><?php endif; ?>
                <?php else: ?>
                    <strong><?= h(listingTypeLabel($ad)) ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <?php $attrRows = adAttributeRows($ad); ?>
        <?php if ($attrRows): ?>
            <div class="kp-card">
                <h3 class="kp-section-title">Specifikacije</h3>
                <div class="kp-attr-list">
                    <?php foreach ($attrRows as $row): ?>
                        <div class="kp-attr-row">
                            <span class="kp-attr-label"><?= h($row['label']) ?></span>
                            <span class="kp-attr-value"><?= h($row['value']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="kp-card">
            <div class="kp-logistics">
                <?php
                $pickup = is_array($ad['pickup_methods'] ?? null) ? $ad['pickup_methods'] : ['pickup', 'courier'];
                $schemaPick = adFormSchema()['pickup_methods'];
                if (in_array('courier', $pickup, true)):
                ?>
                <div class="kp-log-row">
                    <span class="kp-log-ico">🚚</span>
                    <span><strong><?= h($schemaPick['courier']) ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if (in_array('pickup', $pickup, true)): ?>
                <div class="kp-log-row">
                    <span class="kp-log-ico">🏪</span>
                    <span><strong><?= h($schemaPick['pickup']) ?></strong><?= $sellerLocation !== '' ? ' — ' . h($sellerLocation) : '' ?></span>
                </div>
                <?php endif; ?>
                <?php if ($pickup === []): ?>
                <div class="kp-log-row">
                    <span class="kp-log-ico">🏪</span>
                    <span><strong>Lično preuzimanje</strong><?= $sellerLocation !== '' ? ' — ' . h($sellerLocation) : '' ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="kp-mobile-only">
            <?php $sellerBlock(); ?>
            <?php $contactBlock('poruka'); ?>
        </div>

        <div class="kp-card kp-desc-card">
            <h3 class="kp-section-title">Opis oglasa</h3>
            <div class="kp-desc-body"><?= nl2br(h(sanitizeAdPublicText((string)($ad['description'] ?? '')))) ?></div>
            <p class="kp-ad-disclaimer">
                KupiTelefon je oglasnik — kupovina i plaćanje su dogovor sa prodavcem.
                <a href="/uslovi">Uslovi</a>
            </p>
        </div>

        <?php
        $detailAd = $ad;
        if ($similarAds):
        ?>
            <div class="kp-card">
                <h3 class="kp-section-title">Slični oglasi</h3>
                <div class="listings compact-list">
                    <?php foreach ($similarAds as $similar): ?>
                        <?php $ad = $similar; require __DIR__ . '/partials/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php
        endif;
        $ad = $detailAd;
        ?>
    </main>

    <aside class="kp-detail-seller-col" aria-label="Prodavac">
        <div class="kp-detail-seller-sticky">
            <?php $sellerBlock(); ?>
            <?php $contactBlock('poruka-desktop'); ?>
            <div class="kp-card kp-detail-browse">
                <div class="filter-head" style="margin:0 0 10px;">Brza pretraga</div>
                <a class="kp-browse-link" href="/index.php?<?= h(http_build_query(array_filter(['type' => $type, 'device_type' => getAdDeviceType($ad) ?: null]))) ?>"><?= h(adCategoryLabel($ad)) ?></a>
                <?php if ($adBrand !== ''): ?>
                    <a class="kp-browse-link" href="/index.php?brand=<?= urlencode($adBrand) ?>"><?= h($adBrand) ?></a>
                <?php endif; ?>
                <?php if ($sellerLocation !== ''): ?>
                    <a class="kp-browse-link" href="/index.php?location=<?= urlencode($sellerLocation) ?>"><?= h($sellerLocation) ?></a>
                <?php endif; ?>
                <?php if ($sellerShopUrl !== ''): ?>
                    <a class="kp-browse-link" href="<?= h($sellerShopUrl) ?>">Svi oglasi prodavca</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<?php if ($images): ?>
<div class="kp-lightbox" data-lightbox hidden>
    <button type="button" class="kp-lightbox-close" data-lightbox-close aria-label="Zatvori">×</button>
    <button type="button" class="kp-lightbox-nav kp-lightbox-prev" data-lightbox-prev aria-label="Prethodna" <?= $imageCount < 2 ? 'hidden' : '' ?>>‹</button>
    <div class="kp-lightbox-stage">
        <img src="" alt="" data-lightbox-img>
    </div>
    <button type="button" class="kp-lightbox-nav kp-lightbox-next" data-lightbox-next aria-label="Sledeća" <?= $imageCount < 2 ? 'hidden' : '' ?>>›</button>
    <div class="kp-lightbox-counter" data-lightbox-counter>1 od <?= $imageCount ?></div>
    <script type="application/json" data-lightbox-sources><?= json_encode(array_values($images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</div>
<?php endif; ?>

<?php if ($images && $imageCount > 1): ?>
<script>
(function () {
  var root = document.querySelector('.kp-gallery');
  if (!root) return;
  var slides = root.querySelectorAll('.kp-gallery-slide');
  var thumbs = root.querySelectorAll('[data-gallery-thumb-index]');
  var counter = root.querySelector('[data-gallery-counter]');
  var index = 0;
  var total = slides.length;
  if (!total) return;

  function paint(i) {
    index = ((i % total) + total) % total;
    for (var s = 0; s < slides.length; s++) {
      slides[s].style.display = s === index ? 'flex' : 'none';
    }
    for (var t = 0; t < thumbs.length; t++) {
      var on = parseInt(thumbs[t].getAttribute('data-gallery-thumb-index') || '-1', 10) === index;
      thumbs[t].classList.toggle('is-active', on);
      thumbs[t].style.borderColor = on ? '#1a73e8' : 'transparent';
      thumbs[t].style.boxShadow = on ? '0 0 0 2px rgba(26,115,232,.16)' : 'none';
    }
    if (counter) counter.textContent = (index + 1) + ' od ' + total;
    window.__ktGalleryNavTs = Date.now();
  }

  window.ktGalleryGo = function (i) { paint(i); return false; };
  window.ktGalleryNav = function (dir) { paint(index + (dir > 0 ? 1 : -1)); return false; };

  var prev = root.querySelector('[data-gallery-prev]');
  var next = root.querySelector('[data-gallery-next]');
  if (prev) prev.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); window.ktGalleryNav(-1); });
  if (next) next.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); window.ktGalleryNav(1); });
  for (var i = 0; i < thumbs.length; i++) {
    thumbs[i].addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var idx = parseInt(this.getAttribute('data-gallery-thumb-index') || '0', 10);
      window.ktGalleryGo(isNaN(idx) ? 0 : idx);
    });
  }

  paint(0);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
