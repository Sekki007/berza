<?php
/** @var array $ad */
$type = getAdType($ad);
$img = adPrimaryImage($ad);
$isSold = !empty($ad['is_sold']);
$isPromoted = function_exists('isAdTopActive') ? isAdTopActive($ad) : !empty($ad['is_promoted']);
$isHighlighted = function_exists('isAdHighlighted') ? isAdHighlighted($ad) : !empty($ad['is_highlighted']);
$adHref = adUrl($ad);
$adId = (int)($ad['id'] ?? 0);
$inCompare = isInCompare($adId);
$isFav = function_exists('isFavorite') ? isFavorite($adId) : false;
$favEnabled = !empty(siteSettings()['enable_favorites']);
$views = (int)($ad['views'] ?? 0);
$priceOpen = isAdPriceOpen($ad);
$cardShop = trim((string)($ad['shop_name'] ?? ''));
$cardSeller = !empty($ad['created_by']) ? findUserById((int)$ad['created_by']) : null;
$cardShopUrl = $cardSeller ? shopUrl((string)$cardSeller['username']) : '';
if ($cardShop === '' && $cardSeller) {
    $cardShop = getSellerShopName($cardSeller);
}
?>
<article class="ad-item kp-list-card <?= $isSold ? 'is-sold ad-sold' : '' ?> <?= $isHighlighted ? 'ad-highlighted' : '' ?>" data-category="<?= h($type) ?>" data-ad-id="<?= $adId ?>">
    <a href="<?= h($adHref) ?>" class="ad-item-link kp-list-link">
        <div class="ad-item-inner kp-list-inner">
            <div class="ad-thumb kp-list-thumb">
                <?php if ($img): ?>
                    <img src="<?= h($img) ?>" alt="" loading="lazy" class="ad-thumb-img">
                <?php else: ?>
                    <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>">
                        <?= $type === 'telefon' ? '' : strtoupper(adTypeLabel($type)) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isPromoted): ?><span class="ad-badge-promo">TOP</span><?php endif; ?>
                <?php if ($isHighlighted && !$isPromoted): ?><span class="ad-badge-hi">Istaknut</span><?php endif; ?>
                <?php if ($isSold): ?><span class="ad-badge-sold kp-list-badge kp-list-badge-sold">Prodato</span><?php endif; ?>
            </div>
            <div class="ad-body kp-list-body">
                <h2 class="ad-title kp-list-title"><?= h((string)$ad['title']) ?></h2>
                <div class="ad-loc-line kp-list-loc">
                    <span class="ad-loc"><?= h((string)($ad['location'] ?? '')) ?></span>
                    <span class="kp-deliv-ok" title="Dostava / dogovor">☑</span>
                </div>
                <div class="ad-stats-line kp-list-stats">
                    <span title="Pregledi">👁 <?= $views ?></span>
                    <span title="Omiljeni"><?= $isFav ? '♥' : '♡' ?></span>
                    <span title="Objavljeno">↻ <?= h(formatRelativeTime((string)($ad['created_at'] ?? ''))) ?></span>
                </div>
                <div class="ad-price-row kp-list-price-row">
                    <div class="ad-price kp-list-price <?= $priceOpen ? 'kp-list-price-free' : '' ?>"><?= h(formatAdPrice($ad)) ?></div>
                    <?php if ($isPromoted): ?><span class="kp-list-badge">TOP</span><?php endif; ?>
                </div>
                <div class="ad-desktop-extra">
                    <?php if ($cardShop !== ''): ?>
                        <?php if ($cardShopUrl !== ''): ?>
                            <div class="ad-shop">
                                <span class="ad-shop-link" data-shop-url="<?= h($cardShopUrl) ?>"><?= h($cardShop) ?></span>
                                <?= $cardSeller ? renderSellerBadges($cardSeller) : '' ?>
                            </div>
                        <?php else: ?>
                            <div class="ad-shop"><?= h($cardShop) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="ad-desc"><?= h((string)($ad['description'] ?? '')) ?></p>
                    <div class="ad-tags">
                        <span class="tag <?= $type === 'telefon' ? 'tag-cat-phone' : ($type === 'delovi' ? 'tag-cat-parts' : 'tag-cat-service') ?>">
                            <?= h(adTypeLabel($type)) ?>
                        </span>
                        <?php if (!empty($ad['badge'])): ?>
                            <span class="tag tag-green"><?= h((string)$ad['badge']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ad['model'])): ?>
                            <span class="tag tag-gray"><?= h((string)$ad['model']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </a>
    <?php if ($favEnabled): ?>
        <a class="ad-fav-btn kp-list-fav <?= $isFav ? 'active' : '' ?>" href="/favorite.php?id=<?= $adId ?>" title="<?= $isFav ? 'Ukloni iz omiljenih' : 'Dodaj u omiljene' ?>" aria-label="Omiljeni">
            <?= $isFav ? '♥' : '♡' ?>
        </a>
    <?php endif; ?>
    <button type="button"
            class="ad-compare-btn kp-list-cmp <?= $inCompare ? 'active is-in-compare' : '' ?>"
            data-compare-toggle="<?= $adId ?>"
            aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
            title="Uporedi">
        <?= $inCompare ? '✓' : '⇄' ?>
    </button>
</article>
