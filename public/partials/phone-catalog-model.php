<?php

declare(strict_types=1);

/** @var array $model */

$offers = phoneCatalogOffersByModel()[$model['id']] ?? [];
usort($offers, static function (array $a, array $b): int {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});
$stats = phoneCatalogPriceStats($offers);
$facets = phoneCatalogModelFacets($offers);
$visibleOffers = phoneCatalogFilterOffers($offers, $_GET, $facets);
$filtersOn = phoneCatalogOfferFiltersActive($_GET, $facets);
$meta = phoneCatalogModelMeta($model, $stats);
$pageTitle = $meta['title'];
$pageDescription = $meta['description'];
$canonicalUrl = absoluteUrl(phoneCatalogUrl($model));
$robotsMeta = $filtersOn ? 'noindex,follow' : '';
$activePage = 'katalog';
$bodyClass = 'page-phone-catalog page-phone-model';
$showSearch = true;
$label = $model['brand'] . ' ' . $model['name'];
$sources = phoneCatalogSourcesForModel($model);
$postHref = '/postavi-oglas?katalog=' . rawurlencode((string)$model['id']);
if (!isLoggedIn()) {
    $postHref = '/prijava?next=' . rawurlencode('/postavi-oglas?katalog=' . rawurlencode((string)$model['id']));
}

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $label,
    'brand' => ['@type' => 'Brand', 'name' => $model['brand']],
    'url' => $canonicalUrl,
    'description' => $pageDescription,
    'category' => 'Telefon',
];
if ($stats['fixed'] > 0) {
    $jsonLd['offers'] = [
        '@type' => 'AggregateOffer',
        'lowPrice' => $stats['min'],
        'highPrice' => $stats['max'],
        'offerCount' => $stats['fixed'],
        'priceCurrency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
    ];
}

$thumb = '';
$thumbAlt = $label;
foreach ($offers as $offerAd) {
    $public = phoneCatalogPublicOffer($offerAd);
    if ($public['image'] !== '') {
        $thumb = $public['image'];
        $thumbAlt = 'Fotografija iz oglasa: ' . $public['title'];
        break;
    }
}

$specGroups = [];
foreach ($model['specs'] as $row) {
    $specGroups[$row['group'] !== '' ? $row['group'] : 'Specifikacije'][] = $row;
}

require __DIR__ . '/layout-start.php';
?>
<div class="pcat pcat-model">
    <p class="pcat-bc"><a href="/">Početna</a> · <a href="/katalog">Katalog telefona</a> · <a href="/katalog?<?= h(http_build_query(['brand' => [$model['brand']]])) ?>"><?= h($model['brand']) ?></a> · <?= h($model['name']) ?></p>

    <header class="pcat-model-head">
        <div class="pcat-model-photo">
            <?php if ($thumb !== ''): ?>
                <img src="<?= h($thumb) ?>" alt="<?= h($thumbAlt) ?>" width="640" height="640">
                <p>Fotografija je iz povezanog oglasa, nije službena slika proizvođača.</p>
            <?php else: ?>
                <span class="pcat-placeholder pcat-placeholder-lg" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($model['brand'], 0, 1))) ?></span>
                <p>Nema službene slike ni fotografije iz oglasa.</p>
            <?php endif; ?>
        </div>
        <div class="pcat-model-info">
            <p class="pcat-kicker"><?= h($model['brand']) ?></p>
            <h1><?= h($model['name']) ?></h1>
            <?php if ($model['official_name'] !== '' && $model['official_name'] !== $model['name']): ?>
                <p class="pcat-official">Naziv izvora: <?= h($model['official_name']) ?></p>
            <?php endif; ?>
            <?php if ($model['colors'] !== []): ?>
                <ul class="pcat-colors">
                    <?php foreach ($model['colors'] as $color): ?>
                        <li><?= h($color) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($model['variants'] !== []): ?>
                <ul class="pcat-mem">
                    <?php foreach ($model['variants'] as $variant): ?>
                        <li title="Kapacitet iz navedenog izvora"><?= h($variant['storage']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($model['highlights'] !== []): ?>
                <ul class="pcat-keys">
                    <?php foreach ($model['highlights'] as $highlight): ?>
                        <li>
                            <strong><?= h($highlight['title']) ?></strong>
                            <?php if ($highlight['detail'] !== ''): ?><span><?= h($highlight['detail']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ($model['specs'] === []): ?>
                <p class="pcat-missing">Specifikacije nisu unete. U ovoj verziji za ovaj model imamo samo proveren identitet, bez izmišljenih brojki.</p>
            <?php endif; ?>
        </div>
        <aside class="pcat-stats" aria-label="Aktivni oglasi">
            <?php if ($stats['active'] < 1): ?>
                <p class="pcat-stats-empty"><?= h(phoneCatalogEmptyOffersText($label)) ?></p>
            <?php else: ?>
                <p class="pcat-stats-count"><strong><?= (int)$stats['active'] ?></strong> <span><?= $stats['active'] === 1 ? 'aktivan oglas' : 'aktivnih oglasa' ?></span></p>
                <p class="pcat-stats-sub"><?= h($label) ?> na <?= h(seoSiteName()) ?></p>
                <?php if ($stats['fixed'] > 0): ?>
                    <dl class="pcat-stats-grid">
                        <div><dt>Najniža</dt><dd><?= h(phoneCatalogFormatEur((float)$stats['min'])) ?></dd></div>
                        <div><dt>Medijana</dt><dd><?= h(phoneCatalogFormatEur((float)$stats['median'])) ?></dd></div>
                        <div><dt>Najviša</dt><dd><?= h(phoneCatalogFormatEur((float)$stats['max'])) ?></dd></div>
                    </dl>
                    <?php if ($stats['min'] !== null && $stats['max'] !== null && $stats['min'] < $stats['max'] && $stats['median'] !== null): ?>
                        <?php $scale = (int)round(((float)$stats['median'] - (float)$stats['min']) / ((float)$stats['max'] - (float)$stats['min']) * 100); ?>
                        <div class="pcat-scale" aria-hidden="true"><i style="left:<?= max(0, min(100, $scale)) ?>%"></i></div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="pcat-price pcat-price-open">Po dogovoru</p>
                <?php endif; ?>
                <p class="pcat-stats-note"><?= h(phoneCatalogRecognitionNote((int)$stats['active'], (int)$stats['fixed'])) ?></p>
            <?php endif; ?>
            <?php if ($stats['active'] > 0): ?>
                <a class="pcat-btn" href="#pcat-ads-title">Pogledaj <?= (int)$stats['active'] ?> <?= $stats['active'] === 1 ? 'oglas' : 'oglasa' ?></a>
            <?php endif; ?>
            <a class="pcat-btn<?= $stats['active'] > 0 ? ' pcat-btn-ghost' : '' ?>" href="<?= h($postHref) ?>">Prodaj svoj <?= h($model['name']) ?></a>
            <p class="pcat-stats-note"><?= h(phoneCatalogPriceMethodology()) ?></p>
        </aside>
    </header>

    <div class="pcat-model-main">
        <section class="pcat-ads" aria-labelledby="pcat-ads-title">
            <div class="pcat-results-bar">
                <h2 id="pcat-ads-title">Aktivni oglasi<?php if ($stats['active'] > 0): ?> <span><?= (int)$stats['active'] ?></span><?php endif; ?></h2>
            </div>
            <?php
            $showOfferFilters = $facets['storages'] !== [] || $facets['conditions'] !== [] || $facets['cities'] !== [] || $facets['sims'] !== [] || $facets['battery'] || $facets['warranty'];
            ?>
            <?php if ($showOfferFilters && $stats['active'] > 0): ?>
                <form class="pcat-offer-filters" method="get" action="<?= h(phoneCatalogUrl($model)) ?>">
                    <?php if ($facets['conditions'] !== []): ?>
                        <label>Stanje
                            <select name="condition">
                                <option value="">Sve</option>
                                <?php foreach ($facets['conditions'] as $condition): ?>
                                    <option value="<?= h($condition) ?>" <?= (($_GET['condition'] ?? '') === $condition) ? 'selected' : '' ?>><?= h($condition) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($facets['storages'] !== []): ?>
                        <label>Memorija
                            <select name="storage">
                                <option value="">Sve</option>
                                <?php foreach ($facets['storages'] as $storage): ?>
                                    <option value="<?= h($storage) ?>" <?= phoneCatalogNormalizeStorage((string)($_GET['storage'] ?? '')) === $storage ? 'selected' : '' ?>><?= h($storage) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($facets['battery']): ?>
                        <label>Battery health
                            <select name="min_battery">
                                <option value="">Sve</option>
                                <?php foreach (['80', '85', '90', '95'] as $value): ?>
                                    <option value="<?= $value ?>" <?= (string)($_GET['min_battery'] ?? '') === $value ? 'selected' : '' ?>>od <?= $value ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($facets['warranty']): ?>
                        <label>Garancija
                            <select name="garancija">
                                <option value="">Sve</option>
                                <option value="1" <?= (($_GET['garancija'] ?? '') === '1') ? 'selected' : '' ?>>Sa garancijom</option>
                                <option value="0" <?= (($_GET['garancija'] ?? '') === '0') ? 'selected' : '' ?>>Bez garancije</option>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($facets['cities'] !== []): ?>
                        <label>Grad
                            <select name="grad">
                                <option value="">Svi</option>
                                <?php foreach ($facets['cities'] as $city): ?>
                                    <option value="<?= h($city) ?>" <?= (($_GET['grad'] ?? '') === $city) ? 'selected' : '' ?>><?= h($city) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($facets['sims'] !== []): ?>
                        <label>SIM
                            <select name="sim">
                                <option value="">Sve</option>
                                <?php foreach ($facets['sims'] as $sim): ?>
                                    <option value="<?= h($sim) ?>" <?= (($_GET['sim'] ?? '') === $sim) ? 'selected' : '' ?>><?= h($sim) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <button type="submit">Prikaži</button>
                    <?php if ($filtersOn): ?><a href="<?= h(phoneCatalogUrl($model)) ?>">Poništi</a><?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($stats['active'] < 1): ?>
                <p class="pcat-empty"><?= h(phoneCatalogEmptyOffersText($label)) ?> Možeš prvi da postaviš oglas za ovaj model.</p>
            <?php elseif ($visibleOffers === []): ?>
                <p class="pcat-empty">Nema aktivnih oglasa za izabrane filtere.</p>
            <?php else: ?>
                <ul class="pcat-ad-list">
                    <?php foreach ($visibleOffers as $offerAd): ?>
                        <?php $offer = phoneCatalogPublicOffer($offerAd); ?>
                        <li>
                            <a class="pcat-ad" href="<?= h($offer['href']) ?>">
                                <span class="pcat-ad-photo">
                                    <?php if ($offer['image'] !== ''): ?>
                                        <img src="<?= h($offer['image']) ?>" alt="" width="110" height="110" loading="lazy" decoding="async">
                                    <?php endif; ?>
                                </span>
                                <span class="pcat-ad-copy">
                                    <strong><?= h($offer['title']) ?></strong>
                                    <span class="pcat-tags">
                                        <?php if ($offer['condition'] !== ''): ?><span><?= h($offer['condition']) ?></span><?php endif; ?>
                                        <?php if ($offer['battery'] !== null): ?><span>BH <?= (int)$offer['battery'] ?>%</span><?php endif; ?>
                                        <?php if ($offer['warranty'] === true): ?><span>Garancija</span><?php endif; ?>
                                        <?php if ($offer['storage'] !== ''): ?><span><?= h($offer['storage']) ?></span><?php endif; ?>
                                        <?php if ($offer['sim'] !== ''): ?><span><?= h($offer['sim']) ?></span><?php endif; ?>
                                    </span>
                                    <span class="pcat-ad-meta">
                                        <?php if ($offer['city'] !== ''): ?><?= h($offer['city']) ?><?php endif; ?>
                                        <?php if ($offer['shop'] !== ''): ?> · <?= h($offer['shop']) ?><?php endif; ?>
                                    </span>
                                </span>
                                <span class="pcat-ad-price">
                                    <b><?= h($offer['price_label']) ?></b>
                                    <span>Otvori oglas</span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="pcat-spec" aria-labelledby="pcat-spec-title">
            <h2 id="pcat-spec-title">Specifikacije</h2>
            <?php if ($specGroups === []): ?>
                <p>Za <?= h($label) ?> nema unetih specifikacija. Identitet modela je proveren, a ekran, memorija i ostale brojke nisu pogađane.</p>
            <?php else: ?>
                <?php foreach ($specGroups as $group => $rows): ?>
                    <h3><?= h($group) ?></h3>
                    <dl>
                        <?php foreach ($rows as $row): ?>
                            <div>
                                <dt><?= h($row['label']) ?></dt>
                                <dd><?= h($row['value']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($sources !== []): ?>
                <h3>Izvor</h3>
                <ul class="pcat-sources">
                    <?php foreach ($sources as $source): ?>
                        <li>
                            <a href="<?= h($source['url']) ?>" rel="noopener noreferrer"><?= h($source['publisher'] . ' — ' . $source['title']) ?></a>
                            <?php if ($source['note'] !== ''): ?><span><?= h($source['note']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
