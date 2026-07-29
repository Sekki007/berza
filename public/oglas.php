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
$waMsg = 'Zdravo, interesuje me oglas: ' . ($ad['title'] ?? '') . ' — ' . formatAdPrice($ad);
$isFav = isFavorite($id);
$isOwnAd = isLoggedIn() && (int)currentUser()['id'] === (int)($ad['created_by'] ?? 0);
$phone = (string)($ad['contact_phone'] ?? '');
$msgHref = $isOwnAd ? '/poruke.php' : (isLoggedIn() ? '#poruka' : '/login.php');
$price = (float)($ad['price'] ?? 0);
$priceOpen = isAdPriceOpen($ad);

$pageSeo = seoAdMeta($ad);
$pageTitle = $pageSeo['title'];
$activePage = 'oglasi';
$bodyClass = 'page-detail';
$showSearch = true;
$pageDescription = $pageSeo['description'];
$primaryImg = adPrimaryImage($ad);
$pageImage = $primaryImg ? absoluteUrl($primaryImg) : '';
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

require __DIR__ . '/partials/layout-start.php';

$sellerBlock = static function () use (
    $sellerShopUrl,
    $sellerInitials,
    $sellerName,
    $seller,
    $memberSince,
    $sellerLocation,
    $sellerSummary,
    $sellerAdsCount
): void {
    if ($sellerShopUrl !== ''): ?>
        <div class="kp-card kp-seller-card">
            <div class="kp-seller-top">
                <div class="kp-seller-avatar"><?= h($sellerInitials) ?></div>
                <div>
                    <div class="kp-seller-name">
                        <a class="kp-seller-name-link" href="<?= h($sellerShopUrl) ?>"><?= h($sellerName) ?></a>
                        <?= renderSellerBadges($seller) ?>
                    </div>
                </div>
                <a class="kp-seller-chevron" href="<?= h($sellerShopUrl) ?>" aria-label="Otvori izlog prodavca">›</a>
            </div>
            <div class="kp-seller-meta">
                <?php if ($memberSince !== ''): ?>Član od: <?= h($memberSince) ?><?php endif; ?>
                <?php if ($sellerLocation !== ''): ?><?= $memberSince !== '' ? ' · ' : '' ?><?= h($sellerLocation) ?><?php endif; ?>
            </div>
            <div class="kp-seller-rating">
                <span class="kp-thumb-up">👍 <?= (int)($sellerSummary['positive'] ?? 0) ?></span>
                <span class="kp-thumb-down">👎 <?= (int)($sellerSummary['negative'] ?? 0) ?></span>
                <a class="kp-seller-all-link" href="<?= h($sellerShopUrl) ?>">Svi oglasi (<?= $sellerAdsCount ?>)</a>
            </div>
        </div>
    <?php else: ?>
        <div class="kp-card">
            <div class="kp-seller-top">
                <div class="kp-seller-avatar"><?= h($sellerInitials) ?></div>
                <div class="kp-seller-name"><?= h($sellerName) ?></div>
            </div>
            <div class="kp-seller-rating">
                <span class="kp-thumb-up">👍 <?= (int)($sellerSummary['positive'] ?? 0) ?></span>
                <span class="kp-thumb-down">👎 <?= (int)($sellerSummary['negative'] ?? 0) ?></span>
            </div>
        </div>
    <?php endif;
};

$contactBlock = static function (string $formId = 'poruka') use (
    $site,
    $msgHref,
    $isOwnAd,
    $phone,
    $id,
    $waMsg
): void { ?>
    <div class="kp-card kp-contact-card">
        <div class="kp-contact-btns">
            <?php if (!empty($site['enable_messages'])): ?>
                <a class="kp-btn-msg" href="<?= h($msgHref) ?>">
                    💬 <?= $isOwnAd ? 'Otvori poruke' : 'Pošaljite poruku' ?>
                </a>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
                <button type="button" class="kp-btn-tel" data-reveal-phone="<?= h($phone) ?>" data-ad-id="<?= (int)$id ?>">📞 Telefon</button>
            <?php endif; ?>
        </div>
        <p class="kp-reply-hint">
            <?= $isOwnAd
                ? 'Ovo je tvoj oglas — odgovori kupcima iz inboxa.'
                : 'Odgovara na poruke, uglavnom u roku od nekoliko sati.' ?>
        </p>

        <?php if (!empty($site['enable_messages']) && isLoggedIn() && !$isOwnAd): ?>
            <form method="POST" id="<?= h($formId) ?>" class="kp-msg-form" style="margin-top:12px;">
                <div class="form-group">
                    <label>Poruka prodavcu</label>
                    <textarea name="message" rows="3" placeholder="Zdravo, da li je oglas još aktivan?" required></textarea>
                </div>
                <button class="kp-btn-msg" type="submit" style="width:100%;">Pošalji</button>
            </form>
        <?php endif; ?>

        <?php if ($phone !== '' && !empty($site['enable_whatsapp'])): ?>
            <a class="kp-action-link" style="margin-top:10px;display:inline-flex;" href="<?= h(whatsappLink($phone, $waMsg)) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
    </div>
<?php };
?>

<div class="main-wrap kp-detail-wrap" data-ad-id="<?= (int)$id ?>">
    <aside class="kp-detail-filter-col sidebar" aria-label="Filteri">
        <form method="GET" action="/index.php" class="filter-box">
            <div class="filter-head">Filteri</div>
            <div class="filter-body">
                <select class="filter-select" name="type">
                    <option value="">Svi tipovi oglasa</option>
                    <option value="telefon" <?= $type === 'telefon' ? 'selected' : '' ?>>Uređaji</option>
                    <option value="delovi" <?= $type === 'delovi' ? 'selected' : '' ?>>Oprema</option>
                    <option value="servis" <?= $type === 'servis' ? 'selected' : '' ?>>Servisne usluge</option>
                </select>
                <select class="filter-select" name="device_type">
                    <option value="">Tip uređaja (sve)</option>
                    <?php foreach (adFormSchema()['device_types'] as $dtKey => $dtLabel): ?>
                        <option value="<?= h($dtKey) ?>" <?= getAdDeviceType($ad) === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
                    <?php endforeach; ?>
                </select>
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
                <div class="kp-gallery-track" id="gallery-track">
                    <?php foreach ($images as $i => $img): ?>
                        <div class="kp-gallery-slide">
                            <button type="button" class="kp-gallery-zoom" data-lightbox-open="<?= $i ?>" aria-label="Uvećaj sliku">
                                <img src="<?= h((string)$img) ?>" alt="<?= h((string)$ad['title']) ?>" <?= $i === 0 ? 'id="gallery-main"' : '' ?>>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <span class="kp-gallery-counter" data-gallery-counter>1 od <?= $imageCount ?></span>
            <?php else: ?>
                <div class="kp-gallery-slide">
                    <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>" style="width:90px;height:160px;">
                        <?= $type === 'telefon' ? '' : strtoupper(adCategoryLabel($ad)) ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($ad['is_sold'])): ?><span class="listing-badge-sold detail-sold">Prodato</span><?php endif; ?>
            <?php if (!empty($ad['is_promoted'])): ?><span class="listing-badge-promo detail-promo">TOP</span><?php endif; ?>
        </div>

        <div class="kp-detail-head">
            <div class="kp-title-row">
                <h1 class="kp-listing-title"><?= h((string)$ad['title']) ?></h1>
            </div>
            <div class="kp-price-block">
                <div class="kp-price <?= $priceOpen ? 'kp-price-free' : '' ?>"><?= h(formatAdPrice($ad)) ?></div>
                <?php if (!$priceOpen): ?>
                    <?php $rsdHint = formatAdPriceRsd($ad); ?>
                    <?php if ($rsdHint !== ''): ?>
                        <div class="kp-price-rsd"><?= h($rsdHint) ?></div>
                    <?php endif; ?>
                    <span class="kp-price-note"><?= h(adPriceTypeLabel($ad)) ?></span>
                <?php endif; ?>
            </div>
            <div class="kp-action-links">
                <button type="button" class="kp-action-link" data-share-ad data-share-url="<?= h($canonicalUrl) ?>" data-share-title="<?= h((string)$ad['title']) ?>">↗ Podeli</button>
                <button type="button" class="kp-action-link" data-compare-toggle="<?= (int)$ad['id'] ?>" aria-pressed="<?= $inCompare ? 'true' : 'false' ?>">
                    <?= $inCompare ? '✓ U poređenju' : '⇄ Uporedi' ?>
                </button>
                <a class="kp-action-link" href="/report.php?ad=<?= (int)$ad['id'] ?>">⋯ Opcije</a>
            </div>
        </div>

        <div class="kp-card">
            <?php if (!empty($ad['condition_state'])): ?>
                <div class="kp-info-cond"><strong><?= h((string)$ad['condition_state']) ?></strong><?php if (!empty($ad['listing_type'])): ?> · <?= h(listingTypeLabel($ad)) ?><?php endif; ?></div>
            <?php elseif (!empty($ad['listing_type'])): ?>
                <div class="kp-info-cond"><strong><?= h(listingTypeLabel($ad)) ?></strong></div>
            <?php endif; ?>
            <div class="kp-info-stats">
                <span title="Pregledi">👁 <?= (int)($ad['views'] ?? 0) ?></span>
                <span title="Omiljeni"><?= $isFav ? '♥' : '♡' ?></span>
                <span>↻ <?= h(formatRelativeTime((string)$ad['created_at'])) ?></span>
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
            <?php $contactBlock('poruka'); ?>
            <?php $sellerBlock(); ?>
        </div>

        <?php if ($adCategory !== ''): ?>
            <a class="kp-card kp-cat-chip" href="/index.php?type=<?= urlencode($type) ?>">
                <div>
                    <span class="kp-cat-name"><?= h($adCategory) ?></span>
                    <span class="kp-cat-path"><?= h(adCategoryLabel($ad)) ?><?= $adBrand !== '' ? ' · ' . h($adBrand) : '' ?></span>
                </div>
                <span class="kp-cat-chevron">›</span>
            </a>
        <?php endif; ?>

        <div class="kp-card kp-desc-card">
            <h3 class="kp-section-title">Opis oglasa</h3>
            <div class="kp-desc-body"><?= nl2br(h((string)$ad['description'])) ?></div>
        </div>

        <div class="kp-card">
            <strong>Napomena</strong>
            <p style="margin:6px 0 0;font-size:13px;color:#666;line-height:1.45;">Dogovor oko kupovine i plaćanja je između kupca i prodavca. KupiTelefon ne učestvuje u transakciji.</p>
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
            <div class="kp-card kp-seller-heading">
                <strong>Ko je okačio oglas</strong>
            </div>
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

<?php require __DIR__ . '/partials/layout-end.php'; ?>
