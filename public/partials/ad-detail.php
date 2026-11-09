<?php

declare(strict_types=1);

/**
 * Prikaz jednog oglasa. Očekuje promenljive iz oglas.php.
 */

$renderContactActions = static function () use ($contactLinks, $id): void {
    if ($contactLinks === []) {
        return;
    }
    ?>
    <div class="kp-cta">
        <?php foreach ($contactLinks as $link): ?>
            <?php
            $channel = (string)$link['key'];
            $href = (string)$link['href'];
            $external = str_starts_with($href, 'https://');
            ?>
            <a
                class="kp-cta-btn kp-cta-<?= h($channel) ?>"
                href="<?= h($href) ?>"
                data-track-phone
                data-ad-id="<?= (int)$id ?>"
                data-contact-channel="<?= h($channel) ?>"
                <?php if ($external): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
            ><?php if ($channel === 'call'): ?><span aria-hidden="true">📞</span> <?php endif; ?><?= h((string)$link['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php
};

$renderPlatformMessage = static function () use ($site, $isOwnAd, $msgHref): void {
    if (empty($site['enable_messages'])) {
        return;
    }
    if (!isLoggedIn() || $isOwnAd) {
        ?>
        <a class="kp-platform-msg" href="<?= h($msgHref) ?>">
            <span aria-hidden="true">💬</span>
            <?= $isOwnAd ? 'Otvori poruke' : 'Pošalji poruku na platformi' ?>
        </a>
        <?php
        return;
    }
    ?>
    <form method="POST" id="poruka" class="kp-msg-form">
        <?= csrfField() ?>
        <label class="kp-msg-label" for="ad-message">Poruka na platformi</label>
        <textarea id="ad-message" name="message" rows="3" placeholder="Zdravo, da li je oglas još aktivan?" required></textarea>
        <button class="kp-platform-msg kp-platform-msg-submit" type="submit">Pošalji poruku</button>
    </form>
    <?php
};
?>

<div class="main-wrap kp-detail-wrap" data-ad-id="<?= (int)$id ?>">
    <div class="breadcrumb kp-detail-breadcrumb">
        <a href="/index.php">Početna</a>
        <?php if ($adCategory !== ''): ?>
            › <a href="/index.php?type=<?= urlencode($type) ?>"><?= h($adCategory) ?></a>
        <?php endif; ?>
        <?php if ($adBrand !== '' && $adBrand !== 'Ostalo'): ?>
            › <a href="/index.php?brand=<?= urlencode($adBrand) ?>"><?= h($adBrand) ?></a>
        <?php endif; ?>
    </div>

    <div class="kp-detail-layout" data-detail-layout>
        <div class="kp-slot-gallery">
            <div class="detail-gallery kp-gallery">
                <?php if ($images): ?>
                    <div class="kp-gallery-stage">
                        <div class="kp-gallery-track" id="gallery-track">
                            <?php foreach ($images as $i => $img): ?>
                                <?php
                                $imgUrl = (string)$img;
                                $displaySrc = $galleryDisplay[$i] ?? $imgUrl;
                                $thumbSrc = adListingThumbUrl($imgUrl);
                                $srcset = [];
                                if ($thumbSrc !== '' && $thumbSrc !== $displaySrc) {
                                    $srcset[] = $thumbSrc . ' 400w';
                                }
                                if ($displaySrc !== '') {
                                    $srcset[] = $displaySrc . ' 800w';
                                }
                                ?>
                                <div class="kp-gallery-slide">
                                    <button type="button" class="kp-gallery-zoom" data-lightbox-open="<?= (int)$i ?>" aria-label="Uvećaj sliku <?= (int)($i + 1) ?>">
                                        <img
                                            src="<?= h($displaySrc) ?>"
                                            <?php if (count($srcset) > 1): ?>srcset="<?= h(implode(', ', $srcset)) ?>" sizes="(min-width: 980px) 720px, 100vw"<?php endif; ?>
                                            alt="<?= h(seoAdImageAlt($ad, $i + 1)) ?>"
                                            width="800"
                                            height="600"
                                            decoding="async"
                                            <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                                        >
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($imageCount > 1): ?>
                            <button type="button" class="kp-gallery-nav kp-gallery-prev" data-gallery-prev aria-label="Prethodna slika">‹</button>
                            <button type="button" class="kp-gallery-nav kp-gallery-next" data-gallery-next aria-label="Sledeća slika">›</button>
                        <?php endif; ?>
                        <?php if (trim((string)($ad['condition_state'] ?? '')) !== ''): ?>
                            <span class="kp-gallery-badge"><?= h((string)$ad['condition_state']) ?></span>
                        <?php elseif ($intentBadge !== ''): ?>
                            <span class="kp-gallery-badge"><?= h($intentBadge) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ad['is_promoted'])): ?><span class="listing-badge-promo detail-promo">TOP</span><?php endif; ?>
                        <?php if (!empty($ad['is_sold'])): ?><span class="listing-sold-stamp listing-sold-stamp--detail" aria-label="<?= $isBuy ? 'Pronađeno' : 'Prodato' ?>"><?= $isBuy ? 'Pronađeno' : 'Prodato' ?></span><?php endif; ?>
                        <?php if ($imageCount > 1): ?>
                            <span class="kp-gallery-counter" data-gallery-counter>1 od <?= (int)$imageCount ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($imageCount > 1): ?>
                        <div class="kp-gallery-thumbs" data-gallery-thumbs>
                            <?php foreach ($images as $i => $img): ?>
                                <?php $thumbSrc = adListingThumbUrl((string)$img); ?>
                                <button
                                    type="button"
                                    class="kp-gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                    data-gallery-thumb-index="<?= (int)$i ?>"
                                    aria-label="Prikaži sliku <?= (int)($i + 1) ?>"
                                >
                                    <img src="<?= h($thumbSrc) ?>" alt="" width="72" height="72" loading="lazy" decoding="async">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="kp-gallery-stage">
                        <div class="kp-gallery-slide">
                            <div class="<?= $type === 'telefon' ? 'phone-silhouette' : 'parts-icon' ?>">
                                <?= $type === 'telefon' ? '' : h(strtoupper(adCategoryLabel($ad))) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="kp-detail-rail">
            <aside class="kp-card kp-seller-profile kp-slot-seller" data-seller-card aria-label="Prodavac">
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
                            <?= renderOnlineBadge(is_array($seller) ? $seller : null) ?>
                        </div>
                        <?php
                        $sellerMeta = [];
                        if (trim($sellerLocation) !== '') {
                            $sellerMeta[] = trim($sellerLocation);
                        }
                        if ($memberSince !== '') {
                            $sellerMeta[] = 'Član od ' . $memberSince;
                        }
                        ?>
                        <?php if ($sellerMeta !== []): ?>
                            <p class="kp-seller-meta-line"><?= h(implode(' · ', $sellerMeta)) ?></p>
                        <?php endif; ?>
                        <div class="kp-seller-rating">
                            <?= renderReputation($sellerSummary, is_array($seller) ? shopReviewsUrl($seller) : null) ?>
                        </div>
                        <?php if ($isOwnAd): ?>
                            <p class="kp-seller-reply">Ovo je tvoj oglas — odgovori kupcima iz inboxa.</p>
                        <?php endif; ?>
                        <?php if ($sellerShopUrl !== ''): ?>
                            <a class="kp-seller-link" href="<?= h($sellerShopUrl) ?>">Svi oglasi<?= $sellerAdsCount > 0 ? ' (' . (int)$sellerAdsCount . ')' : '' ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <section class="kp-card kp-offer kp-slot-offer <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>" data-offer aria-labelledby="ad-title">
                <h1 id="ad-title" class="kp-listing-title"><?= h($displayTitle) ?></h1>
                <?php $catalogHref = function_exists('phoneCatalogUrlForAd') ? phoneCatalogUrlForAd($ad) : ''; ?>
                <?php if ($catalogHref !== ''): ?>
                    <p class="kp-catalog-link"><a href="<?= h($catalogHref) ?>">Ovaj model u katalogu telefona</a></p>
                <?php endif; ?>
                <?php if ($subtitleParts !== []): ?>
                    <p class="kp-offer-sub"><?= h(implode(' · ', $subtitleParts)) ?></p>
                <?php endif; ?>

                <div class="kp-price-row <?= $isBuy ? 'is-buy' : '' ?> <?= $isTrade ? 'is-trade' : '' ?>">
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
                                <span class="kp-price-rsd"><?= h($rsdHint) ?></span>
                            <?php endif; ?>
                            <?php if (adPriceType($ad) === 'fixed'): ?>
                                <span class="kp-price-type"><?= h(adPriceTypeLabel($ad)) ?></span>
                            <?php endif; ?>
                        <?php elseif ($isTrade): ?>
                            <span class="kp-price-note">Zamena uređaja</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($highlightChips !== []): ?>
                    <ul class="kp-chips">
                        <?php foreach ($highlightChips as $chip): ?>
                            <li><?= h($chip) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div id="kontakt">
                    <?php $renderContactActions(); ?>
                    <?php $renderPlatformMessage(); ?>
                </div>

                <div class="kp-action-links">
                    <button type="button" class="kp-action-link" data-share-ad data-share-url="<?= h($canonicalUrl) ?>" data-share-title="<?= h((string)$ad['title']) ?>">Podeli</button>
                    <?php if (isAdmin()): ?>
                        <a
                            class="kp-action-link kp-action-link-fb"
                            href="/oglas_share.php?id=<?= (int)$ad['id'] ?>&download=1"
                            data-fb-card
                            data-fb-caption="<?= h(adShareCardCaption($ad)) ?>"
                        >Slika za FB</a>
                    <?php endif; ?>
                    <button type="button" class="kp-action-link" data-compare-toggle="<?= (int)$ad['id'] ?>" aria-pressed="<?= $inCompare ? 'true' : 'false' ?>">
                        <?= $inCompare ? 'U poređenju' : 'Uporedi' ?>
                    </button>
                    <?php if (!empty($site['enable_favorites'])): ?>
                        <a class="kp-action-link" href="/favorite.php?id=<?= (int)$ad['id'] ?>"><?= $isFav ? 'Sačuvano' : 'Sačuvaj' ?></a>
                    <?php endif; ?>
                    <a class="kp-action-link kp-action-link-report" href="/report.php?ad=<?= (int)$ad['id'] ?>">Prijavi oglas</a>
                </div>
                <div class="kp-head-stats">
                    <span title="Pregledi">👁 <?= (int)($ad['views'] ?? 0) ?></span>
                </div>
            </section>

            <div class="kp-card kp-rail-links">
                <div class="filter-head">Brza pretraga</div>
                <a class="kp-browse-link" href="/index.php?<?= h(http_build_query(array_filter(['type' => $type, 'device_type' => getAdDeviceType($ad) ?: null]))) ?>"><?= h(adCategoryLabel($ad)) ?></a>
                <?php if ($adBrand !== '' && $adBrand !== 'Ostalo'): ?>
                    <a class="kp-browse-link" href="/index.php?brand=<?= urlencode($adBrand) ?>"><?= h($adBrand) ?></a>
                <?php endif; ?>
                <?php if (trim($sellerLocation) !== ''): ?>
                    <a class="kp-browse-link" href="/index.php?location=<?= urlencode($sellerLocation) ?>"><?= h($sellerLocation) ?></a>
                <?php endif; ?>
                <?php if ($sellerShopUrl !== ''): ?>
                    <a class="kp-browse-link" href="<?= h($sellerShopUrl) ?>">Svi oglasi prodavca</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="kp-slot-main">
            <?php if ($keyFacts !== []): ?>
                <section class="kp-card kp-keyfacts" aria-labelledby="keyfacts-title">
                    <h2 id="keyfacts-title" class="kp-section-title">Ključne informacije</h2>
                    <div class="kp-keyfact-grid">
                        <?php foreach ($keyFacts as $fact): ?>
                            <div class="kp-keyfact">
                                <small><?= h((string)$fact['label']) ?></small>
                                <b><?= h((string)$fact['value']) ?></b>
                                <?php if (trim((string)($fact['note'] ?? '')) !== ''): ?>
                                    <span class="kp-keyfact-note"><?= h((string)$fact['note']) ?></span>
                                <?php endif; ?>
                                <?php if (isset($fact['meter'])): ?>
                                    <?php
                                    $meter = (int)$fact['meter'];
                                    $tone = $meter >= 85 ? 'good' : ($meter >= 70 ? 'mid' : 'low');
                                    ?>
                                    <span class="kp-bat" aria-hidden="true"><span class="kp-bat-fill is-<?= h($tone) ?>" style="width: <?= $meter ?>%"></span></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($descriptionText !== '' || $accessoryLabels !== [] || $attrRows !== []): ?>
                <section class="kp-card kp-desc-card" aria-labelledby="desc-title">
                    <h2 id="desc-title" class="kp-section-title"><?= ($descriptionText !== '' || $accessoryLabels !== []) ? 'Opis oglasa' : 'Specifikacije' ?></h2>
                    <?php if ($descriptionText !== ''): ?>
                        <?php if ($descriptionLong): ?>
                            <details class="kp-desc-fold">
                                <summary>
                                    <span class="kp-desc-preview"><?= h($descriptionPreview) ?></span>
                                    <span class="kp-fold-more">Prikaži ceo opis</span>
                                    <span class="kp-fold-less">Sakrij opis</span>
                                </summary>
                                <div class="kp-desc-body"><?= nl2br(h($descriptionText)) ?></div>
                            </details>
                        <?php else: ?>
                            <div class="kp-desc-body"><?= nl2br(h($descriptionText)) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($accessoryLabels !== []): ?>
                        <ul class="kp-acc-list">
                            <?php foreach ($accessoryLabels as $label): ?>
                                <li><?= h($label) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($attrRows !== []): ?>
                        <details class="kp-spec-fold">
                            <summary>Sve specifikacije</summary>
                            <table class="kp-spec-table">
                                <tbody>
                                    <?php foreach ($attrRows as $row): ?>
                                        <tr>
                                            <th scope="row"><?= h((string)$row['label']) ?></th>
                                            <td><?= h((string)$row['value']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <div class="kp-slot-tail">
            <section class="kp-card kp-safe" aria-labelledby="safe-title">
                <h2 id="safe-title" class="kp-section-title">Sigurna kupovina</h2>
                <ul>
                    <li>KupiTelefon je oglasnik — kupovina i plaćanje su dogovor sa prodavcem. <a href="/uslovi">Uslovi</a></li>
                    <?php if ($type === 'telefon'): ?>
                        <li>Pre kupovine možeš da koristiš <a href="/provera-imei">besplatnu IMEI proveru</a>.</li>
                    <?php endif; ?>
                    <?php if (in_array('pickup', $pickupMethods, true)): ?>
                        <li>Oglas nudi lično preuzimanje<?= trim($sellerLocation) !== '' ? ' (' . h($sellerLocation) . ')' : '' ?> — dogovor proveri uživo pre plaćanja.</li>
                    <?php endif; ?>
                    <?php if (in_array('courier', $pickupMethods, true)): ?>
                        <li>Prodavac nudi slanje kurirskom službom. Slanje i plaćanje dogovaraju se direktno sa prodavcem.</li>
                    <?php endif; ?>
                </ul>
            </section>

            <?php if ($similarAds !== []): ?>
                <?php $detailAd = $ad; ?>
                <section class="kp-card kp-similar" aria-labelledby="similar-title">
                    <h2 id="similar-title" class="kp-section-title">Slični oglasi</h2>
                    <div class="listings kp-similar-list">
                        <?php foreach ($similarAds as $similar): ?>
                            <?php $ad = $similar; require __DIR__ . '/ad-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php $ad = $detailAd; ?>
            <?php endif; ?>

            <details class="kp-card kp-detail-filters">
                <summary>Filteri</summary>
                <form method="GET" action="/index.php" class="filter-box">
                    <div class="filter-body">
                        <select class="filter-select" name="browse_cat" aria-label="Kategorija">
                            <option value="">Sve kategorije</option>
                            <option value="telefon" <?= getAdType($ad) === 'telefon' ? 'selected' : '' ?>>Telefoni</option>
                            <option value="parts" <?= getAdType($ad) === 'delovi' && adEquipmentGroup($ad) === 'parts' ? 'selected' : '' ?>>Delovi</option>
                            <option value="oprema" <?= getAdType($ad) === 'delovi' && adEquipmentGroup($ad) !== 'parts' ? 'selected' : '' ?>>Oprema</option>
                            <option value="servis" <?= getAdType($ad) === 'servis' ? 'selected' : '' ?>>Servis</option>
                        </select>
                        <?php if (getAdType($ad) === 'telefon'): ?>
                            <select class="filter-select" name="device_type" aria-label="Tip uređaja">
                                <option value="">Tip uređaja (sve)</option>
                                <?php foreach (adFormSchema()['device_types'] as $dtKey => $dtLabel): ?>
                                    <option value="<?= h($dtKey) ?>" <?= getAdDeviceType($ad) === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if ($adBrand !== ''): ?>
                            <input type="hidden" name="brand" value="<?= h($adBrand) ?>">
                        <?php endif; ?>
                        <?php if (trim($sellerLocation) !== ''): ?>
                            <input type="hidden" name="location" value="<?= h($sellerLocation) ?>">
                        <?php endif; ?>
                        <select class="filter-select" name="sort" aria-label="Sortiranje">
                            <option value="newest">Najnovije</option>
                            <option value="price_asc">Cena rastuće</option>
                            <option value="price_desc">Cena opadajuće</option>
                        </select>
                        <button class="filter-apply" type="submit">Prikaži oglase</button>
                    </div>
                </form>
            </details>
        </div>
    </div>
</div>

<?php if ($images): ?>
<div class="kp-lightbox" data-lightbox hidden>
    <button type="button" class="kp-lightbox-close" data-lightbox-close aria-label="Zatvori">×</button>
    <button type="button" class="kp-lightbox-nav kp-lightbox-prev" data-lightbox-prev aria-label="Prethodna" <?= $imageCount < 2 ? 'hidden' : '' ?>>‹</button>
    <div class="kp-lightbox-stage">
        <img src="" alt="" data-lightbox-img>
    </div>
    <button type="button" class="kp-lightbox-nav kp-lightbox-next" data-lightbox-next aria-label="Sledeća" <?= $imageCount < 2 ? 'hidden' : '' ?>>›</button>
    <div class="kp-lightbox-counter" data-lightbox-counter>1 od <?= (int)$imageCount ?></div>
    <script type="application/json" data-lightbox-sources><?= json_encode(array_values($images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout-end.php'; ?>
