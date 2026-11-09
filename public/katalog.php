<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/katalog'), PHP_URL_PATH) ?: '/katalog';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($requestPath === '/katalog.php' || $requestPath === '/phone_catalog.php')) {
    header('Location: /katalog', true, 301);
    exit;
}

$bundle = phoneCatalogBundle();
if (empty($bundle['ok'])) {
    renderPublicStatusPage(
        500,
        'Katalog trenutno nije dostupan',
        'Podaci kataloga nisu prošli proveru. Javni oglasi nisu menjani.',
        [
            ['href' => '/', 'label' => 'Početna'],
            ['href' => '/oglasi', 'label' => 'Oglasi'],
        ]
    );
    exit;
}

$brandSlug = strtolower(trim((string)($_GET['brand_slug'] ?? '')));
$modelSlug = strtolower(trim((string)($_GET['model_slug'] ?? '')));
$isModel = $brandSlug !== '' || $modelSlug !== '';
if ($isModel) {
    $model = phoneCatalogFindBySlug($brandSlug, $modelSlug);
    if ($model === null) {
        renderPublicStatusPage(
            404,
            'Model nije u katalogu',
            'Traženi model nije u ovoj verziji kataloga. Cena i specifikacije se ne izmišljaju.',
            [
                ['href' => '/katalog', 'label' => 'Katalog telefona'],
                ['href' => '/oglasi/telefoni', 'label' => 'Oglasi telefona'],
                ['href' => isLoggedIn() ? '/postavi-oglas' : '/prijava', 'label' => 'Postavi oglas'],
            ]
        );
        exit;
    }
    require __DIR__ . '/partials/phone-catalog-model.php';
    exit;
}

$offersByModel = phoneCatalogOffersByModel();
$filters = phoneCatalogSanitizeIndexQuery($_GET, $offersByModel);
$models = phoneCatalogFilterModels($filters, $offersByModel);
$meta = phoneCatalogIndexMeta();
$pageTitle = $meta['title'];
$pageDescription = $meta['description'];
$canonicalUrl = absoluteUrl('/katalog');
$robotsMeta = phoneCatalogIndexFiltersActive($filters) ? 'noindex,follow' : '';
$activePage = 'katalog';
$bodyClass = 'page-phone-catalog';
$showSearch = true;

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Katalog telefona',
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => seoSiteName(),
        'url' => absoluteUrl('/'),
    ],
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => count($models),
        'itemListElement' => array_values(array_map(static function (array $model, int $index): array {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => absoluteUrl(phoneCatalogUrl($model)),
                'name' => $model['brand'] . ' ' . $model['name'],
            ];
        }, $models, array_keys($models))),
    ],
];

$facets = $filters['facets'];
$hiddenForHero = static function (array $filters): string {
    $html = '';
    foreach ($filters['brands'] as $brand) {
        $html .= '<input type="hidden" name="brand[]" value="' . h($brand) . '">';
    }
    foreach (['price_band', 'condition', 'storage', 'sort'] as $key) {
        if ((string)($filters[$key] ?? '') !== '') {
            $html .= '<input type="hidden" name="' . h($key) . '" value="' . h((string)$filters[$key]) . '">';
        }
    }
    if ((int)$filters['min_battery'] > 0) {
        $html .= '<input type="hidden" name="min_battery" value="' . (int)$filters['min_battery'] . '">';
    }
    if (!empty($filters['with_ads'])) {
        $html .= '<input type="hidden" name="with_ads" value="1">';
    }
    return $html;
};

require __DIR__ . '/partials/layout-start.php';
?>
<div class="pcat">
    <header class="pcat-hero">
        <h1>Katalog telefona</h1>
        <p>Izaberi model, pročitaj samo proverene specifikacije i vidi aktivne oglase povezane sa tim modelom na <?= h(seoSiteName()) ?>.</p>
        <form class="pcat-hero-search" method="get" action="/katalog" role="search">
            <?= $hiddenForHero($filters) ?>
            <label class="visually-hidden" for="pcat-q">Pretraži model</label>
            <input id="pcat-q" type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Pretraži model, npr. „iPhone 15“ ili „Galaxy A54“">
            <button type="submit">Traži</button>
        </form>
        <?php if ($facets['brands'] !== []): ?>
            <div class="pcat-brand-row" role="list">
                <?php
                $allOn = $filters['brands'] === [];
                ?>
                <a role="listitem" class="<?= $allOn ? 'is-on' : '' ?>" href="/katalog">Svi brendovi</a>
                <?php foreach ($facets['brands'] as $brand): ?>
                    <?php $on = $filters['brands'] === [$brand]; ?>
                    <a role="listitem" class="<?= $on ? 'is-on' : '' ?>" href="/katalog?<?= h(http_build_query(['brand' => [$brand]])) ?>"><?= h($brand) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="pcat-layout">
        <details class="pcat-filters">
            <summary>Filteri</summary>
            <form method="get" action="/katalog">
                <?php if ($filters['q'] !== ''): ?>
                    <input type="hidden" name="q" value="<?= h($filters['q']) ?>">
                <?php endif; ?>
                <h2>Filteri</h2>

                <?php if ($facets['brands'] !== []): ?>
                    <fieldset>
                        <legend>Brend</legend>
                        <?php foreach ($facets['brands'] as $brand): ?>
                            <label class="pcat-check">
                                <input type="checkbox" name="brand[]" value="<?= h($brand) ?>" <?= in_array($brand, $filters['brands'], true) ? 'checked' : '' ?>>
                                <span><?= h($brand) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <?php if (!empty($facets['price'])): ?>
                    <fieldset>
                        <legend>Cena u oglasima</legend>
                        <?php
                        $bands = [
                            '' => 'Sve',
                            'lt100' => 'do 100€',
                            '100-300' => '100–300€',
                            '300-600' => '300–600€',
                            'gt600' => '600€+',
                        ];
                        foreach ($bands as $value => $label):
                        ?>
                            <label class="pcat-pill">
                                <input type="radio" name="price_band" value="<?= h($value) ?>" <?= $filters['price_band'] === $value ? 'checked' : '' ?>>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <?php if ($facets['conditions'] !== []): ?>
                    <fieldset>
                        <legend>Stanje</legend>
                        <label class="pcat-pill">
                            <input type="radio" name="condition" value="" <?= $filters['condition'] === '' ? 'checked' : '' ?>>
                            <span>Sve</span>
                        </label>
                        <?php foreach ($facets['conditions'] as $condition): ?>
                            <label class="pcat-pill">
                                <input type="radio" name="condition" value="<?= h($condition) ?>" <?= $filters['condition'] === $condition ? 'checked' : '' ?>>
                                <span><?= h($condition) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <?php if (!empty($facets['battery'])): ?>
                    <fieldset>
                        <legend>Battery health min.</legend>
                        <?php foreach ([['','Sve'], ['80','80%'], ['85','85%'], ['90','90%'], ['95','95%']] as [$value, $label]): ?>
                            <label class="pcat-pill">
                                <input type="radio" name="min_battery" value="<?= h($value) ?>" <?= (string)$filters['min_battery'] === $value ? 'checked' : '' ?>>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <?php if ($facets['storages'] !== []): ?>
                    <fieldset>
                        <legend>Memorija</legend>
                        <label class="pcat-pill">
                            <input type="radio" name="storage" value="" <?= $filters['storage'] === '' ? 'checked' : '' ?>>
                            <span>Sve</span>
                        </label>
                        <?php foreach ($facets['storages'] as $storage): ?>
                            <label class="pcat-pill">
                                <input type="radio" name="storage" value="<?= h($storage) ?>" <?= $filters['storage'] === $storage ? 'checked' : '' ?>>
                                <span><?= h($storage) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <label class="pcat-toggle">
                    <span>Samo modeli sa oglasima</span>
                    <input type="checkbox" name="with_ads" value="1" <?= !empty($filters['with_ads']) ? 'checked' : '' ?>>
                </label>

                <button class="pcat-apply" type="submit">Prikaži modele</button>
                <?php if (phoneCatalogIndexFiltersActive($filters)): ?>
                    <a class="pcat-reset" href="/katalog">Poništi filtere</a>
                <?php endif; ?>
            </form>
        </details>
        <script>
            (function () {
                var box = document.currentScript.previousElementSibling;
                if (box && window.matchMedia('(min-width: 900px)').matches) {
                    box.open = true;
                }
            })();
        </script>

        <section class="pcat-results" aria-labelledby="pcat-results-title">
            <div class="pcat-results-bar">
                <h2 id="pcat-results-title"><?= $filters['with_ads'] ? 'Modeli sa oglasima' : 'Modeli' ?></h2>
                <form method="get" action="/katalog">
                    <?= $hiddenForHero(array_merge($filters, ['sort' => ''])) ?>
                    <label for="pcat-sort">Sortiraj</label>
                    <select id="pcat-sort" name="sort" onchange="this.form.submit()">
                        <option value="ads" <?= $filters['sort'] === 'ads' ? 'selected' : '' ?>>Najviše oglasa</option>
                        <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Najniža cena</option>
                        <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Naziv</option>
                    </select>
                    <noscript><button type="submit">Sortiraj</button></noscript>
                </form>
            </div>

            <?php if ($models === []): ?>
                <p class="pcat-empty">Nema modela za izabrane filtere. Filteri ne izmišljaju oglase ni cene.</p>
            <?php else: ?>
                <div class="pcat-grid">
                    <?php foreach ($models as $card): ?>
                        <?php
                        $stats = $card['_stats'];
                        $offers = $card['_offers'];
                        $href = phoneCatalogUrl($card);
                        $chips = phoneCatalogMemoryChips($card, $offers);
                        $thumb = '';
                        $thumbAlt = $card['brand'] . ' ' . $card['name'];
                        foreach ($offers as $offerAd) {
                            $public = phoneCatalogPublicOffer($offerAd);
                            if ($public['image'] !== '') {
                                $thumb = $public['image'];
                                $thumbAlt = 'Fotografija iz oglasa: ' . $public['title'];
                                break;
                            }
                        }
                        ?>
                        <article class="pcat-card">
                            <a class="pcat-card-media" href="<?= h($href) ?>">
                                <?php if ($thumb !== ''): ?>
                                    <img src="<?= h($thumb) ?>" alt="<?= h($thumbAlt) ?>" width="400" height="400" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="pcat-placeholder" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($card['brand'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="pcat-card-body">
                                <p class="pcat-kicker"><?= h($card['brand']) ?></p>
                                <h3><a href="<?= h($href) ?>"><?= h($card['name']) ?></a></h3>
                                <p class="pcat-count"><?= (int)$stats['active'] === 1 ? '1 aktivan oglas' : ((int)$stats['active'] . ' aktivnih oglasa') ?></p>
                                <?php if ($stats['fixed'] > 0): ?>
                                    <p class="pcat-price"><?= $stats['min'] === $stats['max'] ? h(phoneCatalogFormatEur((float)$stats['min'])) : ('od ' . h(phoneCatalogFormatEur((float)$stats['min']))) ?></p>
                                    <?php if ($stats['min'] !== $stats['max']): ?>
                                        <p class="pcat-range"><?= h(phoneCatalogFormatEur((float)$stats['min'])) ?> – <?= h(phoneCatalogFormatEur((float)$stats['max'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($stats['negotiable'] > 0): ?>
                                        <p class="pcat-range"><?= (int)$stats['negotiable'] ?> po dogovoru, nije u rasponu</p>
                                    <?php endif; ?>
                                <?php elseif ($stats['active'] > 0): ?>
                                    <p class="pcat-price pcat-price-open">Po dogovoru</p>
                                <?php else: ?>
                                    <p class="pcat-price pcat-price-open">Nema aktivnih oglasa</p>
                                <?php endif; ?>
                                <?php if ($chips !== []): ?>
                                    <ul class="pcat-mem">
                                        <?php foreach ($chips as $chip): ?>
                                            <li<?= $chip['source'] === 'listing' ? ' title="Kapacitet iz povezanog oglasa"' : ' title="Kapacitet iz navedenog izvora"' ?>><?= h($chip['label']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <a class="pcat-open" href="<?= h($href) ?>">Otvori model</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="pcat-note"><?= h((string)$bundle['coverage_note']) ?> <?= h(phoneCatalogPriceMethodology()) ?></p>
        </section>
    </div>

    <aside class="pcat-sell">
        <div>
            <h2>Prodaješ telefon?</h2>
            <p>Izaberi model iz kataloga. Brend i naziv se popune, a stanje, cena, grad, opis i fotografije ostaju tvoji.</p>
        </div>
        <a href="<?= isLoggedIn() ? '/postavi-oglas' : '/prijava?next=' . rawurlencode('/postavi-oglas') ?>">Postavite oglas</a>
    </aside>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
