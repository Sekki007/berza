<?php
/** @var array $ad */
$type = getAdType($ad);
$img = adPrimaryListingThumb($ad);
$isSold = !empty($ad['is_sold']);
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
$categoryLabel = adCategoryLabel($ad);
$rsdHint = !$priceOpen ? formatAdPriceRsd($ad) : '';
$location = trim((string)($ad['location'] ?? ''));
$relativeTime = formatRelativeTime((string)($ad['created_at'] ?? ''));
$imageCount = is_array($ad['images'] ?? null) ? count($ad['images']) : 0;
?>
<article class="listing-card kp-list-card <?= $isSold ? 'is-sold listing-sold' : '' ?> <?= $isHighlighted ? 'listing-highlighted' : '' ?>" data-category="<?= h($type) ?>" data-ad-id="<?= $adId ?>">
    <a href="<?= h($adHref) ?>" class="listing-link kp-list-link">
        <div class="listing-inner kp-list-inner">
            <div class="kp-list-media">
                <div class="listing-thumb kp-list-thumb">
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
                    <?php if ($isPromoted): ?><span class="listing-badge-promo">TOP</span><?php endif; ?>
                    <?php if ($isHighlighted && !$isPromoted): ?><span class="listing-badge-hi">Istaknut</span><?php endif; ?>
                    <?php if ($isSold): ?><span class="listing-badge-sold kp-list-badge kp-list-badge-sold">Prodato</span><?php endif; ?>
                </div>
                <div class="kp-list-thumb-meta">
                    <?php if ($location !== ''): ?>
                        <span class="kp-list-meta-loc" title="Grad"><?= h($location) ?></span>
                        <span class="kp-list-meta-sep" aria-hidden="true">·</span>
                    <?php endif; ?>
                    <span title="Pregledi">👁 <?= $views ?></span>
                    <span class="kp-list-meta-sep" aria-hidden="true">·</span>
                    <span title="Objavljeno"><?= h($relativeTime) ?></span>
                </div>
            </div>
            <div class="listing-body kp-list-body">
                <div class="kp-list-main">
                    <h2 class="listing-title kp-list-title"><?= h((string)$ad['title']) ?></h2>

                    <?php if ($cardShop !== ''): ?>
                        <div class="listing-shop kp-list-shop">
                            <?php if ($cardShopUrl !== ''): ?>
                                <span class="listing-shop-link" data-shop-url="<?= h($cardShopUrl) ?>"><?= h($cardShop) ?></span>
                            <?php else: ?>
                                <span class="listing-shop-name"><?= h($cardShop) ?></span>
                            <?php endif; ?>
                            <?= $cardSeller ? renderVerifiedBadge($cardSeller) : '' ?>
                            <span class="listing-shop-biz"><?= $cardSeller ? renderBusinessBadge($cardSeller) : '' ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="listing-tags listing-tags-compact">
                        <span class="tag <?= $type === 'telefon' ? 'tag-cat-phone' : ($type === 'delovi' ? 'tag-cat-parts' : 'tag-cat-service') ?>">
                            <?= h($categoryLabel) ?>
                        </span>
                        <?php if ($isPromoted): ?><span class="kp-list-badge">TOP</span><?php endif; ?>
                    </div>

                    <div class="listing-desktop-extra">
                        <p class="listing-desc"><?= h((string)($ad['description'] ?? '')) ?></p>
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

                <div class="kp-list-price-badge <?= $priceOpen ? 'is-open' : '' ?>">
                    <span class="kp-list-price-main"><?= $priceOpen ? 'Po dogovoru' : h(formatAdPrice($ad)) ?></span>
                    <?php if ($rsdHint !== ''): ?>
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
    <button type="button"
            class="listing-compare-btn kp-list-cmp <?= $inCompare ? 'active is-in-compare' : '' ?>"
            data-compare-toggle="<?= $adId ?>"
            aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
            title="Uporedi">
        <?= $inCompare ? '✓' : '⇄' ?>
    </button>
    <?php
    $shopCatalogMode = !empty($shopCatalogMode);
    $shopCatalogOwn = !empty($shopCatalogOwn);
    $messagesOn = !empty(siteSettings()['enable_messages']);
    if ($shopCatalogMode && $messagesOn && !$isSold && !$shopCatalogOwn):
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
        </div>
    <?php endif; ?>
</article>
