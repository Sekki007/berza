<?php
/** @var array $ad */
$type = getAdType($ad);
$img = adPrimaryImage($ad);
$isSold = !empty($ad['is_sold']);
$isPromoted = function_exists('isAdTopActive') ? isAdTopActive($ad) : !empty($ad['is_promoted']);
$isHighlighted = function_exists('isAdHighlighted') ? isAdHighlighted($ad) : !empty($ad['is_highlighted']);
$adHref = adUrl($ad);
$inCompare = isInCompare((int)$ad['id']);
?>
<article class="ad-item <?= $isSold ? 'ad-sold' : '' ?> <?= $isHighlighted ? 'ad-highlighted' : '' ?>" data-category="<?= h($type) ?>" data-ad-id="<?= (int)$ad['id'] ?>">
    <a href="<?= h($adHref) ?>" class="ad-item-link">
        <div class="ad-item-inner">
            <div class="ad-thumb">
                <?php if ($img): ?>
                    <img src="<?= h($img) ?>" alt="" loading="lazy" class="ad-thumb-img">
                <?php else: ?>
                    <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>">
                        <?= $type === 'telefon' ? '' : strtoupper(adTypeLabel($type)) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isPromoted): ?><span class="ad-badge-promo">TOP</span><?php endif; ?>
                <?php if ($isHighlighted && !$isPromoted): ?><span class="ad-badge-hi">Istaknut</span><?php endif; ?>
                <?php if ($isSold): ?><span class="ad-badge-sold">Prodato</span><?php endif; ?>
            </div>
            <div class="ad-body">
                <div class="ad-top-row">
                    <h2 class="ad-title"><?= h((string)$ad['title']) ?></h2>
                    <div class="ad-price"><?= formatPrice((float)$ad['price']) ?></div>
                </div>
                <?php
                $cardShop = trim((string)($ad['shop_name'] ?? ''));
                $cardSeller = !empty($ad['created_by']) ? findUserById((int)$ad['created_by']) : null;
                $cardShopUrl = $cardSeller ? shopUrl((string)$cardSeller['username']) : '';
                if ($cardShop === '' && $cardSeller) {
                    $cardShop = getSellerShopName($cardSeller);
                }
                ?>
                <?php if ($cardShop !== ''): ?>
                    <?php if ($cardShopUrl !== ''): ?>
                        <div class="ad-shop">
                            <span class="ad-shop-link" data-shop-url="<?= h($cardShopUrl) ?>"><?= h($cardShop) ?></span>
                            <?= $cardSeller ? renderVerifiedBadge($cardSeller) : '' ?>
                        </div>
                    <?php else: ?>
                        <div class="ad-shop"><?= h($cardShop) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <p class="ad-desc"><?= h((string)$ad['description']) ?></p>
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
                <div class="ad-footer">
                    <span><?= h((string)$ad['location']) ?></span>
                    <span><?= h(formatRelativeTime((string)$ad['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </a>
    <button type="button"
            class="ad-compare-btn <?= $inCompare ? 'active' : '' ?>"
            data-compare-toggle="<?= (int)$ad['id'] ?>"
            aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
            title="Uporedi">
        <?= $inCompare ? 'U poređenju' : 'Uporedi' ?>
    </button>
</article>
