<?php
/** @var array $ad */
$type = getAdType($ad);
$img = adPrimaryListingThumb($ad);
$isSold = !empty($ad['is_sold']);
$isBuy = isBuyListing($ad);
$isTrade = isTradeListing($ad);
$intentBadge = adIntentBadgeLabel($ad);
$displayTitle = adDisplayTitle($ad);
$cardImgPriority = !empty($cardImgPriority);
$isPromoted = function_exists('isAdTopActive') ? isAdTopActive($ad) : !empty($ad['is_promoted']);
$isHighlighted = function_exists('isAdHighlighted') ? isAdHighlighted($ad) : !empty($ad['is_highlighted']);
$adHref = adUrl($ad);
$adId = (int)($ad['id'] ?? 0);
$inCompare = isInCompare($adId);
$isFav = function_exists('isFavorite') ? isFavorite($adId) : false;
$favEnabled = !empty(siteSettings()['enable_favorites']);
$views = (int)($ad['views'] ?? 0);
$priceOpen = isAdPriceOpen($ad);
$cardSeller = !empty($ad['created_by']) ? findUserById((int)$ad['created_by']) : null;
$cardShop = $cardSeller
    ? getSellerShopName($cardSeller, [$ad])
    : trim((string)($ad['shop_name'] ?? ''));
$cardShopUrl = $cardSeller ? shopUrlForUser($cardSeller) : '';
$cardRatingSummary = $cardSeller ? getSellerRatingSummary((int)($cardSeller['id'] ?? 0)) : ['count' => 0, 'positive' => 0, 'negative' => 0];
$categoryLabel = adCategoryLabel($ad);
$showBudgetHint = $isBuy && !$priceOpen && (float)($ad['price'] ?? 0) > 0;
$rsdHint = (!$priceOpen && !$isBuy) ? formatAdPriceRsd($ad) : '';
$location = trim((string)($ad['location'] ?? ''));
$relativeTime = formatRelativeTime((string)($ad['created_at'] ?? ''));
$imageCount = is_array($ad['images'] ?? null) ? count($ad['images']) : 0;
$soldLabel = $isBuy ? 'Pronađeno' : 'Prodato';

$shopCatalogMode = !empty($shopCatalogMode);
$shopCatalogOwn = !empty($shopCatalogOwn);
$messagesOn = !empty(siteSettings()['enable_messages']);
// Kad kartica ima traku za naručivanje, meta podaci idu u nju umesto preko dugmadi.
$hasOrderBar = $shopCatalogMode && $messagesOn && !$isSold && !$shopCatalogOwn;
?>
<article class="listing-card kp-list-card <?= $hasOrderBar ? 'kp-list-card--order' : '' ?> <?= $isSold ? 'is-sold listing-sold' : '' ?> <?= $isHighlighted ? 'listing-highlighted' : '' ?> <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>" data-category="<?= h($type) ?>" data-listing-type="<?= h(getAdListingType($ad)) ?>" data-ad-id="<?= $adId ?>">
    <a href="<?= h($adHref) ?>" class="listing-link kp-list-link">
        <div class="listing-inner kp-list-inner">
            <div class="kp-list-media">
                <div class="listing-thumb kp-list-thumb <?= $isBuy && !$img ? 'kp-list-thumb--seek' : '' ?>">
                    <?php if ($img): ?>
                        <img
                            src="<?= h($img) ?>"
                            alt=""
                            width="400"
                            height="400"
                            loading="<?= $cardImgPriority ? 'eager' : 'lazy' ?>"
                            decoding="async"
                            <?php if ($cardImgPriority): ?>fetchpriority="high"<?php endif; ?>
                            class="listing-thumb-img"
                        >
                    <?php elseif ($isBuy): ?>
                        <div class="kp-list-seek-placeholder" aria-hidden="true">
                            <span>Tražim</span>
                        </div>
                    <?php else: ?>
                        <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>">
                            <?= $type === 'telefon' ? '' : strtoupper($categoryLabel) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($imageCount > 1): ?>
                        <span class="kp-list-photo-count" title="<?= $imageCount ?> fotografija">
                            <span aria-hidden="true">▣</span> <?= $imageCount ?>
                        </span>
                    <?php endif; ?>
                    <span class="kp-list-view-count" title="<?= $views ?> pregleda">
                        <span aria-hidden="true">👁</span> <?= $views ?>
                    </span>
                    <?php if ($isPromoted): ?><span class="listing-badge-promo">TOP</span><?php endif; ?>
                    <?php if ($isHighlighted && !$isPromoted): ?><span class="listing-badge-hi">Istaknut</span><?php endif; ?>
                    <?php if ($isSold): ?><span class="listing-badge-sold kp-list-badge kp-list-badge-sold"><?= h($soldLabel) ?></span><?php endif; ?>
                </div>
                <?php if ($location !== '' && !$hasOrderBar): ?>
                    <div class="kp-list-thumb-meta">
                        <span class="kp-list-meta-loc" title="Grad"><?= h($location) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="listing-body kp-list-body">
                <div class="kp-list-main">
                    <h2 class="listing-title kp-list-title"><?= h($displayTitle) ?></h2>

                    <?php if ($cardShop !== ''): ?>
                        <div class="listing-shop kp-list-shop">
                            <?php if ($cardShopUrl !== ''): ?>
                                <span class="listing-shop-link" data-shop-url="<?= h($cardShopUrl) ?>"><?= h($cardShop) ?></span>
                            <?php else: ?>
                                <span class="listing-shop-name"><?= h($cardShop) ?></span>
                            <?php endif; ?>
                            <?= $cardSeller ? renderVerifiedBadge($cardSeller) : '' ?>
                            <span class="listing-shop-biz"><?= $cardSeller ? renderBusinessBadge($cardSeller) : '' ?></span>
                            <?php if ((int)($cardRatingSummary['count'] ?? 0) > 0): ?>
                                <span class="shop-rating-sm"><?= renderReputation($cardRatingSummary) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="listing-tags listing-tags-compact">
                        <?php if ($intentBadge !== ''): ?>
                            <span class="tag tag-intent-<?= h(getAdListingType($ad)) ?>"><?= h($intentBadge) ?></span>
                        <?php endif; ?>
                        <span class="tag <?= $type === 'telefon' ? 'tag-cat-phone' : ($type === 'delovi' ? 'tag-cat-parts' : 'tag-cat-service') ?>">
                            <?= h($categoryLabel) ?>
                        </span>
                        <?php
                        $bhCard = $ad['battery_health'] ?? null;
                        if ($type === 'telefon' && $bhCard !== null && $bhCard !== ''):
                            ?>
                            <span class="tag tag-bh">BH <?= (int)$bhCard ?>%</span>
                        <?php endif; ?>
                        <?php
                        $eqCard = trim((string)($ad['equipment_type'] ?? ''));
                        if ($type === 'delovi' && $eqCard !== '' && $eqCard !== 'Ostalo'):
                            ?>
                            <span class="tag tag-gray"><?= h($eqCard) ?></span>
                        <?php endif; ?>
                        <?php if ($isPromoted): ?><span class="kp-list-badge">TOP</span><?php endif; ?>
                    </div>

                    <div class="listing-desktop-extra">
                        <?php $cardExcerpt = adListingExcerpt($ad); ?>
                        <?php if ($cardExcerpt !== ''): ?>
                            <p class="listing-desc"><?= h($cardExcerpt) ?></p>
                        <?php endif; ?>
                        <div class="listing-tags">
                            <?php if (!empty($ad['badge'])): ?>
                                <span class="tag tag-green"><?= h((string)$ad['badge']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($ad['model'])): ?>
                                <span class="tag tag-gray"><?= h((string)$ad['model']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="kp-list-price-badge <?= $priceOpen ? 'is-open' : '' ?> <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>">
                    <span class="kp-list-price-main"><?= h(adCardPriceMainLabel($ad)) ?></span>
                    <?php if ($showBudgetHint): ?>
                        <span class="listing-price-rsd">budžet</span>
                    <?php elseif ($rsdHint !== ''): ?>
                        <span class="listing-price-rsd"><?= h($rsdHint) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </a>
    <?php if ($favEnabled): ?>
        <a class="listing-fav-btn kp-list-fav <?= $isFav ? 'active' : '' ?>" href="/favorite.php?id=<?= $adId ?>" title="<?= $isFav ? 'Ukloni iz omiljenih' : 'Dodaj u omiljene' ?>" aria-label="Omiljeni">
            <?= $isFav ? '♥' : '♡' ?>
        </a>
    <?php endif; ?>
    <?php if ($relativeTime !== '' && !$hasOrderBar): ?>
        <span class="kp-list-time" title="Objavljeno"><?= h($relativeTime) ?></span>
    <?php endif; ?>
    <?php if (!$hasOrderBar): ?>
        <button type="button"
                class="listing-compare-btn kp-list-cmp <?= $inCompare ? 'active is-in-compare' : '' ?>"
                data-compare-toggle="<?= $adId ?>"
                aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
                title="Uporedi">
            <?= $inCompare ? '✓' : '⇄' ?>
        </button>
    <?php endif; ?>
    <?php
    if ($hasOrderBar):
        $orderDefault = 'Zdravo, zainteresovan/a sam za „' . (string)($ad['title'] ?? 'artikal') . '”'
            . (!$priceOpen ? (' (' . formatAdPrice($ad) . ')') : '')
            . '. Da li je još dostupno i kako mogu da naručim / preuzmem?';
        $orderLoginHref = '/login.php';
    ?>
        <div class="shop-order-bar" id="order-<?= $adId ?>">
            <a class="btn-sm" href="<?= h($adHref) ?>">Detalji</a>
            <?php if (isLoggedIn()): ?>
                <details class="shop-order-details">
                    <summary class="btn-sm btn-sm-primary shop-order-toggle">Naruči / Pošalji poruku</summary>
                    <form method="POST" class="shop-order-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="shop_order">
                        <input type="hidden" name="ad_id" value="<?= $adId ?>">
                        <label class="shop-order-label" for="shop-order-msg-<?= $adId ?>">Poruka prodavcu</label>
                        <textarea id="shop-order-msg-<?= $adId ?>" name="message" rows="3" required maxlength="2000"><?= h($orderDefault) ?></textarea>
                        <button class="btn-sm btn-sm-primary" type="submit">Pošalji narudžbinu</button>
                    </form>
                </details>
            <?php else: ?>
                <a class="btn-sm btn-sm-primary" href="<?= h($orderLoginHref) ?>">Naruči / Prijavi se</a>
            <?php endif; ?>
            <div class="shop-order-meta">
                <?php if ($location !== ''): ?>
                    <span class="shop-order-loc" title="Grad"><?= h($location) ?></span>
                <?php endif; ?>
                <?php if ($relativeTime !== ''): ?>
                    <span class="shop-order-time" title="Objavljeno"><?= h($relativeTime) ?></span>
                <?php endif; ?>
                <button type="button"
                        class="listing-compare-btn kp-list-cmp <?= $inCompare ? 'active is-in-compare' : '' ?>"
                        data-compare-toggle="<?= $adId ?>"
                        aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
                        title="Uporedi">
                    <?= $inCompare ? '✓' : '⇄' ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</article>
