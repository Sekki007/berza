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
$cardSeller = !empty($ad['created_by']) ? findUserById((int)$ad['created_by']) : null;
$cardShop = $cardSeller
    ? getSellerShopName($cardSeller, [$ad])
    : trim((string)($ad['shop_name'] ?? ''));
$cardShopUrl = $cardSeller ? shopUrlForUser($cardSeller) : '';
$categoryLabel = adCategoryLabel($ad);
$rsdHint = !$priceOpen ? formatAdPriceRsd($ad) : '';
?>
<article class="listing-card kp-list-card <?= $isSold ? 'is-sold listing-sold' : '' ?> <?= $isHighlighted ? 'listing-highlighted' : '' ?>" data-category="<?= h($type) ?>" data-ad-id="<?= $adId ?>">
    <a href="<?= h($adHref) ?>" class="listing-link kp-list-link">
        <div class="listing-inner kp-list-inner">
            <div class="listing-thumb kp-list-thumb">
                <?php if ($img): ?>
                    <img src="<?= h($img) ?>" alt="" loading="lazy" class="listing-thumb-img">
                <?php else: ?>
                    <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>">
                        <?= $type === 'telefon' ? '' : strtoupper($categoryLabel) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isPromoted): ?><span class="listing-badge-promo">TOP</span><?php endif; ?>
                <?php if ($isHighlighted && !$isPromoted): ?><span class="listing-badge-hi">Istaknut</span><?php endif; ?>
                <?php if ($isSold): ?><span class="listing-badge-sold kp-list-badge kp-list-badge-sold">Prodato</span><?php endif; ?>
            </div>
            <div class="listing-body kp-list-body">
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

                <div class="listing-loc-line kp-list-loc">
                    <span class="listing-loc"><?= h((string)($ad['location'] ?? '')) ?></span>
                    <span class="kp-deliv-ok" title="Dostava / dogovor">☑</span>
                </div>

                <div class="listing-stats-line kp-list-stats">
                    <span title="Pregledi">👁 <?= $views ?></span>
                    <span title="Omiljeni"><?= $isFav ? '♥' : '♡' ?></span>
                    <span title="Objavljeno">↻ <?= h(formatRelativeTime((string)($ad['created_at'] ?? ''))) ?></span>
                </div>

                <div class="listing-price-row kp-list-price-row">
                    <div class="listing-price kp-list-price <?= $priceOpen ? 'kp-list-price-free' : '' ?>">
                        <?= h(formatAdPrice($ad)) ?>
                        <?php if ($rsdHint !== ''): ?>
                            <span class="listing-price-rsd"><?= h($rsdHint) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isPromoted): ?><span class="kp-list-badge">TOP</span><?php endif; ?>
                </div>

                <div class="listing-tags listing-tags-compact">
                    <span class="tag <?= $type === 'telefon' ? 'tag-cat-phone' : ($type === 'delovi' ? 'tag-cat-parts' : 'tag-cat-service') ?>">
                        <?= h($categoryLabel) ?>
                    </span>
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
</article>
