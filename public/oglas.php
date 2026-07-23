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
$sellerName = (string)($ad['shop_name'] ?: ($seller ? getSellerShopName($seller) : 'Prodavac'));
$sellerInitials = mb_strtoupper(mb_substr($sellerName, 0, 1) . mb_substr(trim(strrchr($sellerName, ' ') ?: ''), 0, 1));
if (mb_strlen($sellerInitials) < 1) {
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
$waMsg = 'Zdravo, interesuje me oglas: ' . ($ad['title'] ?? '') . ' — ' . formatPrice((float)$ad['price']);
$isFav = isFavorite($id);
$isOwnAd = isLoggedIn() && (int)currentUser()['id'] === (int)($ad['created_by'] ?? 0);
$phone = (string)($ad['contact_phone'] ?? '');

$pageTitle = (string)$ad['title'] . ' — TelefonBerza';
$activePage = 'oglasi';
$bodyClass = 'page-detail';
$showSearch = true;
$pageDescription = mb_substr(trim((string)($ad['description'] ?? $ad['title'])), 0, 160);
$primaryImg = adPrimaryImage($ad);
$pageImage = $primaryImg ? absoluteUrl($primaryImg) : '';
$canonicalUrl = absoluteUrl(adUrl($ad));
$ogType = 'product';
$inCompare = isInCompare($id);

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap" data-ad-id="<?= (int)$id ?>">
    <main class="content">
        <div class="breadcrumb">
            <a href="/index.php">Početna</a>
            <?php if (!empty($ad['category'])): ?>
                › <a href="/index.php?type=<?= urlencode($type) ?>"><?= h((string)$ad['category']) ?></a>
            <?php endif; ?>
        </div>

        <div class="detail-layout">
            <div class="detail-main">
                <div class="detail-gallery kp-gallery">
                    <?php if ($imageCount > 1): ?>
                        <div class="detail-thumbs kp-thumbs">
                            <?php foreach ($images as $i => $img): ?>
                                <button type="button" class="detail-thumb <?= $i === 0 ? 'active' : '' ?>" data-gallery-thumb="<?= h((string)$img) ?>" data-gallery-index="<?= $i + 1 ?>">
                                    <img src="<?= h((string)$img) ?>" alt="">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="detail-main-img" id="main-image">
                        <?php if ($images): ?>
                            <img src="<?= h((string)$images[0]) ?>" alt="" class="detail-img" id="gallery-main">
                            <?php if ($imageCount > 0): ?>
                                <span class="gallery-counter" data-gallery-counter>1 od <?= $imageCount ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>" style="width:90px;height:160px;">
                                <?= $type === 'telefon' ? '' : strtoupper(adTypeLabel($type)) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ad['is_sold'])): ?><span class="ad-badge-sold detail-sold">Prodato</span><?php endif; ?>
                        <?php if (!empty($ad['is_promoted'])): ?><span class="ad-badge-promo detail-promo">TOP</span><?php endif; ?>
                    </div>
                </div>

                <div class="detail-card kp-ad-head">
                    <div class="kp-title-row">
                        <h1 class="detail-title"><?= h((string)$ad['title']) ?></h1>
                        <div class="kp-title-actions">
                            <?php if (!empty($site['enable_favorites'])): ?>
                                <a class="kp-follow <?= $isFav ? 'active' : '' ?>" href="/favorite.php?id=<?= (int)$ad['id'] ?>">
                                    <?= $isFav ? '♥ Pratite' : '♡ Prati' ?>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn-sm" data-compare-toggle="<?= (int)$ad['id'] ?>" aria-pressed="<?= $inCompare ? 'true' : 'false' ?>">
                                <?= $inCompare ? 'U poređenju' : 'Uporedi' ?>
                            </button>
                            <button type="button" class="btn-sm" data-share-ad data-share-url="<?= h($canonicalUrl) ?>" data-share-title="<?= h((string)$ad['title']) ?>">Podeli</button>
                        </div>
                    </div>
                    <div class="detail-price"><?= formatPrice((float)$ad['price']) ?></div>
                    <div class="kp-stats">
                        <span title="Pregledi">👁 <?= (int)($ad['views'] ?? 0) ?></span>
                        <span title="Omiljeni"><?= $isFav ? '♥' : '♡' ?> <?= $isFav ? '1' : '0' ?></span>
                        <span><?= h(formatRelativeTime((string)$ad['created_at'])) ?></span>
                    </div>
                    <div class="kp-attrs">
                        <?php if (!empty($ad['condition_state'])): ?>
                            <div class="kp-attr"><span class="kp-attr-label">Stanje</span><span><?= h((string)$ad['condition_state']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($ad['brand'])): ?>
                            <div class="kp-attr"><span class="kp-attr-label">Brend</span><span><?= h((string)$ad['brand']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($ad['model'])): ?>
                            <div class="kp-attr"><span class="kp-attr-label">Model</span><span><?= h((string)$ad['model']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($ad['storage'])): ?>
                            <div class="kp-attr"><span class="kp-attr-label">Memorija</span><span><?= h((string)$ad['storage']) ?></span></div>
                        <?php endif; ?>
                        <div class="kp-attr"><span class="kp-attr-label">Tip</span><span><?= h(adTypeLabel($type)) ?></span></div>
                    </div>
                    <div class="kp-ad-footer">
                        <span>ID Oglasa: #<?= (int)$ad['id'] ?></span>
                        <a class="kp-report" href="/report.php?ad=<?= (int)$ad['id'] ?>">Prijavi</a>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-desc">
                        <h3>Opis oglasa</h3>
                        <?= nl2br(h((string)$ad['description'])) ?>
                    </div>
                </div>

                <?php if ($similarAds): ?>
                    <div class="form-card" style="margin-top:12px;">
                        <h2>Slični oglasi</h2>
                        <p class="form-hint" style="margin-top:-6px;">Isti model, brend, grad ili slična cena.</p>
                        <div class="ads-list compact-list">
                            <?php foreach ($similarAds as $similar): ?>
                                <?php $ad = $similar; require __DIR__ . '/partials/ad-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="detail-sidebar">
                <div class="detail-card kp-seller-box">
                    <?php if (!empty($site['enable_messages'])): ?>
                        <?php if ($isOwnAd): ?>
                            <a class="btn-kp-message" href="/poruke.php">Otvori poruke</a>
                            <p class="kp-response-hint">Ovo je tvoj oglas — odgovori kupcima iz inboxa.</p>
                        <?php elseif (isLoggedIn()): ?>
                            <a class="btn-kp-message" href="#poruka">Pošaljite poruku</a>
                            <p class="kp-response-hint">Odgovara na poruke, uglavnom u roku od nekoliko sati.</p>
                        <?php else: ?>
                            <a class="btn-kp-message" href="/login.php">Pošaljite poruku</a>
                            <p class="kp-response-hint">Prijavi se da pošalješ poruku prodavcu.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($sellerUsername !== ''): ?>
                        <a class="kp-seller kp-seller-link" href="<?= h(shopUrl($sellerUsername)) ?>">
                            <div class="seller-avatar"><?= h($sellerInitials) ?></div>
                            <div class="seller-info">
                                <h4><?= h($sellerName) ?> <?= renderVerifiedBadge($seller) ?></h4>
                                <?php if ($sellerLocation !== ''): ?>
                                    <p class="kp-seller-loc"><?= h($sellerLocation) ?></p>
                                <?php endif; ?>
                                <?php if ($memberSince !== ''): ?>
                                    <p class="kp-seller-since">Član od <?= h($memberSince) ?></p>
                                <?php endif; ?>
                                <p class="kp-seller-open">Pogledaj izlog →</p>
                            </div>
                        </a>
                        <div class="kp-seller-reps"><?= renderReputation($sellerSummary, shopUrl($sellerUsername)) ?></div>
                    <?php else: ?>
                        <div class="kp-seller">
                            <div class="seller-avatar"><?= h($sellerInitials) ?></div>
                            <div class="seller-info">
                                <h4><?= h($sellerName) ?> <?= renderVerifiedBadge($seller) ?></h4>
                                <?php if ($sellerLocation !== ''): ?>
                                    <p class="kp-seller-loc"><?= h($sellerLocation) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="kp-seller-reps"><?= renderReputation($sellerSummary) ?></div>
                    <?php endif; ?>

                    <div class="kp-seller-links">
                        <?php if ($sellerUsername !== ''): ?>
                            <a href="<?= h(shopUrl($sellerUsername)) ?>">📄 Svi oglasi (<?= $sellerAdsCount ?>)</a>
                            <a href="<?= h(shopUrl($sellerUsername)) ?>#ocene">Ocene i komentari</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($phone !== ''): ?>
                        <button type="button" class="btn-phone-reveal" data-reveal-phone="<?= h($phone) ?>" data-ad-id="<?= (int)$id ?>">
                            Klik za broj telefona
                        </button>
                        <?php if (!empty($site['enable_whatsapp'])): ?>
                            <a class="btn-message" href="<?= h(whatsappLink($phone, $waMsg)) ?>" target="_blank" rel="noopener">WhatsApp</a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($site['enable_messages']) && isLoggedIn() && !$isOwnAd): ?>
                        <form method="POST" id="poruka" class="kp-msg-form">
                            <div class="form-group">
                                <label>Poruka prodavcu</label>
                                <textarea name="message" rows="3" placeholder="Zdravo, da li je oglas još aktivan?" required></textarea>
                            </div>
                            <button class="btn-kp-message btn-kp-message-secondary" type="submit">Pošalji</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="detail-card kp-disclaimer">
                    <strong>Napomena</strong>
                    <p>Dogovor oko kupovine i plaćanja je između kupca i prodavca. TelefonBerza ne učestvuje u transakciji.</p>
                </div>
            </aside>
        </div>
    </main>
</div>

<div class="sticky-contact">
    <?php if (!empty($site['enable_messages'])): ?>
        <a class="btn-kp-message" href="<?= isLoggedIn() ? '#poruka' : '/login.php' ?>">Poruka</a>
    <?php endif; ?>
    <?php if ($phone !== ''): ?>
        <button type="button" class="btn-call" data-reveal-phone="<?= h($phone) ?>" data-ad-id="<?= (int)$id ?>">Telefon</button>
    <?php endif; ?>
    <?php if (!empty($site['enable_favorites'])): ?>
        <a class="btn-message" href="/favorite.php?id=<?= (int)$id ?>"><?= $isFav ? '♥' : '♡' ?></a>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
