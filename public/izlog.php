<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$param = trim((string)($_GET['u'] ?? ''));
$seller = resolveShopUserFromParam($param);

if (!$seller) {
    http_response_code(404);
    $pageTitle = 'Izlog nije pronađen — KupiTelefon';
    $activePage = 'oglasi';
    $showSearch = true;
    require __DIR__ . '/partials/layout-start.php';
    echo '<div class="main-wrap"><main class="content"><div class="form-card"><h2>Izlog nije pronađen</h2><p style="margin-top:10px;color:var(--text-muted);">Proveri link ili se vrati na <a href="/index.php">početnu</a>.</p></div></main></div>';
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

$sellerId = (int)$seller['id'];
$shopLink = shopUrlForUser($seller);
if (trim((string)($_GET['pozovi'] ?? '')) === '1') {
    $bridgePhone = shopSellerCallPhone($seller, getPublicAdsByUserId($sellerId, false));
    $bridgeTel = $bridgePhone !== '' ? adTelHref($bridgePhone) : '';
    shopEmitCallBridge($bridgeTel);
    return;
}
// Stari /izlog/{username} → kanonski slug
if ($param !== '' && rawurldecode($param) !== userShopSlug($seller)) {
    $qs = $_GET;
    unset($qs['u'], $qs['cat_from_path']);
    $target = shopCatalogUrl($seller, $qs);
    header('Location: ' . $target, true, 301);
    exit;
}

$allAds = getPublicAdsByUserId($sellerId, false);
$shopCategories = getShopCategories($seller);
$catFromPath = !empty($_GET['cat_from_path']);
$requestedCat = trim((string)($_GET['cat'] ?? ''));

// Ako Nginx uhvati /izlog/slug/ocene kao "kategoriju", ipak otvori stranicu ocena
if ($requestedCat === 'ocene') {
    require __DIR__ . '/izlog_ocene.php';
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(12, (int)(siteSettings()['items_per_page'] ?? 20));
$filters = shopCatalogSanitizeFilters($_GET);
$filterType = $filters['type'];
$searchQ = $filters['q'];
$sort = $filters['sort'];
$filterCat = $filters['cat'];

$activeCategory = $filterCat !== '' ? findShopCategory($seller, $filterCat) : null;
if ($filterCat !== '' && !$activeCategory) {
    if ($catFromPath) {
        header('Location: ' . $shopLink, true, 301);
        exit;
    }
    $filterCat = '';
    $filters['cat'] = '';
}

// Stari ?cat=slug → /izlog/{shop}/{slug}
$queryString = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
parse_str($queryString, $rawQuery);
if (!$catFromPath && $filterCat !== '' && isset($rawQuery['cat']) && trim((string)$rawQuery['cat']) !== '') {
    $redirectParams = shopCatalogPublicParams(array_merge($filters, [
        'cat' => $filterCat,
        'page' => $page,
    ]));
    header('Location: ' . shopCatalogUrl($seller, $redirectParams), true, 301);
    exit;
}

$typeCounts = shopAdTypeCounts($allAds);
$categoryCounts = shopCategoryCounts($allAds, $shopCategories);
$facetAds = filterShopAds($allAds, [
    'q' => $searchQ,
    'type' => $filterType,
    'shop_category' => $filterCat,
    'sort' => 'newest',
    'hide_sold' => true,
]);
$brandOptions = shopCatalogBrandOptions($facetAds);
if ($filters['brand'] !== '' && !in_array($filters['brand'], $brandOptions, true)) {
    array_unshift($brandOptions, $filters['brand']);
}
$modelOptions = shopCatalogModelOptions($facetAds, $filters['brand']);
$adsFiltered = filterShopAds($allAds, array_merge($filters, [
    'shop_category' => $filterCat,
    'hide_sold' => true,
]));
$pagination = paginateAds($adsFiltered, $page, $perPage);
$ads = $pagination['items'];

$queryBase = shopCatalogPublicParams(array_merge($filters, [
    'cat' => $filterCat,
    'page' => 1,
]));

$shopName = getSellerShopName($seller, $allAds);
$summary = getSellerRatingSummary($sellerId);
$categoryLabel = $activeCategory ? (string)$activeCategory['name'] : null;
$shopSeo = seoShopMeta($seller, $shopName, $categoryLabel);
$pageDescription = $shopSeo['description'];
$canonicalUrl = absoluteUrl(shopCatalogUrl($seller, shopCatalogCanonicalParams(['cat' => $filterCat])));
if (shopCatalogFiltersNeedNoindex(array_merge($filters, ['cat' => $filterCat, 'page' => $page]))) {
    $robotsMeta = 'noindex,follow';
}
$shopCover = trim((string)($seller['shop_page_cover'] ?? ''));
if ($shopCover !== '') {
    $pageImage = absoluteUrl($shopCover);
}
$currentUser = currentUser();
$isOwnShop = isLoggedIn() && (int)$currentUser['id'] === $sellerId;
$initials = mb_strtoupper(mb_substr($shopName, 0, 1) . mb_substr(trim(strrchr($shopName, ' ') ?: ''), 0, 1));
if (mb_strlen($initials) < 1) {
    $initials = '?';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'shop_order') {
    requireCsrf($shopLink);
    requireLogin();
    $siteCheck = siteSettings();
    if (empty($siteCheck['enable_messages'])) {
        setFlash('danger', 'Poruke trenutno nisu omogućene.');
        header('Location: ' . $shopLink);
        exit;
    }

    $orderAdId = (int)($_POST['ad_id'] ?? 0);
    $body = trim((string)($_POST['message'] ?? ''));
    $orderAd = $orderAdId > 0 ? getAdById($orderAdId) : null;
    $fromUserId = (int)currentUser()['id'];

    if (!$orderAd || (int)($orderAd['is_active'] ?? 0) !== 1 || (int)($orderAd['created_by'] ?? 0) !== $sellerId) {
        setFlash('danger', 'Artikal nije pronađen u ovom izlogu.');
        header('Location: ' . $shopLink . '#oglasi');
        exit;
    }
    if (!empty($orderAd['is_sold'])) {
        setFlash('danger', 'Ovaj artikal je označen kao prodat.');
        header('Location: ' . $shopLink . '#oglasi');
        exit;
    }
    if ($fromUserId === $sellerId) {
        setFlash('danger', 'Ne možeš naručiti sa sopstvenog izloga.');
        header('Location: ' . $shopLink . '#oglasi');
        exit;
    }
    if ($body === '') {
        setFlash('danger', 'Unesi poruku uz narudžbinu.');
        header('Location: ' . $shopLink . '#order-' . $orderAdId);
        exit;
    }

    $saved = saveMessage([
        'ad_id' => $orderAdId,
        'from_user_id' => $fromUserId,
        'from_name' => (string)(currentUser()['full_name'] ?? ''),
        'from_phone' => '',
        'to_user_id' => $sellerId,
        'body' => $body,
    ]);

    if ($saved) {
        setFlash('success', 'Poruka / narudžbina je poslata prodavcu. Prati odgovor u Porukama.');
        if (function_exists('queueFacebookPixelEvent')) {
            queueFacebookPixelEvent('Lead', [
                'content_ids' => [(string)$orderAdId],
                'content_name' => (string)($orderAd['title'] ?? ''),
                'content_category' => getAdType($orderAd),
            ]);
        }
        header('Location: /poruke.php?ad=' . $orderAdId . '&with=' . $sellerId);
        exit;
    }

    setFlash('danger', 'Poruka nije poslata. Pokušaj ponovo.');
    header('Location: ' . $shopLink . '#order-' . $orderAdId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate') {
    requireCsrf($shopLink);
    requireLogin();
    // Ocene se šalju na posebnu stranicu
    header('Location: ' . shopReviewsUrl($seller));
    exit;
}

$pageTitle = $shopSeo['title'];
$activePage = 'oglasi';
$showSearch = true;
$bodyClass = 'page-shop';
$callPhone = shopSellerCallPhone($seller, $allAds);
$canCall = $callPhone !== '' && adTelHref($callPhone) !== '';
$callIntentUrl = $canCall ? shopCallIntentUrl($seller) : '';
$extraContacts = shopSellerExtraContactLinks($seller);
$verifiedBadgeHtml = shopHeaderVerifiedBadgeHtml($seller);
$businessBadgeHtml = renderBusinessBadge($seller);
$onlineBadgeHtml = renderOnlineBadge($seller);
$shopBio = trim((string)($seller['shop_bio'] ?? ''));
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'name' => $shopName,
    'url' => $canonicalUrl,
    'mainEntity' => [
        '@type' => 'Person',
        'name' => $shopName,
        'url' => $canonicalUrl,
    ],
];

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb">
            <a href="/index.php">Početna</a> › Izlog ›
            <?php if ($activeCategory): ?>
                <a href="<?= h($shopLink) ?>"><?= h($shopName) ?></a> › <?= h($activeCategory['name']) ?>
            <?php else: ?>
                <?= h($shopName) ?>
            <?php endif; ?>
        </div>

        <div class="shop-header form-card">
            <div class="shop-identity">
                <?= renderShopAvatarHtml($seller, $initials, 'shop-avatar') ?>
                <div class="shop-identity-copy">
                    <h1 class="shop-title"><?= h($shopName) ?> <?= $businessBadgeHtml ?></h1>
                    <?= $verifiedBadgeHtml ?>
                    <p class="shop-meta-line">
                        <?= $onlineBadgeHtml ?>
                        <span><?= (int)$typeCounts['all'] ?> <?= (int)$typeCounts['all'] === 1 ? 'oglas' : 'oglasa' ?></span>
                        <?= renderReputation($summary, shopReviewsUrl($seller)) ?>
                    </p>
                    <?php if ($shopBio !== ''): ?>
                        <p class="shop-bio"><?= nl2br(h($shopBio)) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($canCall): ?>
                    <a class="shop-call" href="<?= h($callIntentUrl) ?>">
                        <span aria-hidden="true">📞</span> Pozovi
                    </a>
                <?php endif; ?>
            </div>
            <div class="shop-more">
                <span class="shop-more-label">Više:</span>
                <button type="button" class="shop-more-link" data-copy-link data-copy-url="<?= h($shopLink) ?>">Kopiraj link</button>
                <?php if (isDirectoryServiceFirm($seller) && trim((string)($seller['location'] ?? '')) !== ''): ?>
                    <span class="shop-more-sep" aria-hidden="true">·</span>
                    <a class="shop-more-link" href="<?= h(directoryServiceUrl($seller)) ?>">Direktorijum firmi</a>
                <?php endif; ?>
                <?php if (storefrontIsActive($seller)): ?>
                    <span class="shop-more-sep" aria-hidden="true">·</span>
                    <a class="shop-more-link" href="<?= h(storefrontUrlForUser($seller)) ?>">Mini sajt</a>
                <?php endif; ?>
                <?php foreach ($extraContacts as $extra): ?>
                    <span class="shop-more-sep" aria-hidden="true">·</span>
                    <a class="shop-more-link" href="<?= h((string)$extra['href']) ?>"<?php if (!empty($extra['external'])): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?= h((string)$extra['label']) ?></a>
                <?php endforeach; ?>
                <?php if (!$isOwnShop): ?>
                    <span class="shop-more-sep" aria-hidden="true">·</span>
                    <a class="shop-more-link" href="/report.php?user=<?= (int)$sellerId ?>">Prijavi korisnika</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card shop-catalog" id="oglasi">
            <?php if ($typeCounts['all'] === 0): ?>
                <div class="shop-catalog-head">
                    <h2>Katalog</h2>
                </div>
                <p class="shop-empty">Ovaj prodavac trenutno nema aktivnih oglasa.</p>
            <?php else: ?>
                <form method="get" class="shop-catalog-form" action="<?= h(shopCatalogUrl($seller, ['cat' => $filterCat])) ?>">
                    <?php if ($filterType !== ''): ?><input type="hidden" name="type" value="<?= h($filterType) ?>"><?php endif; ?>
                    <div class="shop-catalog-head">
                        <h2>Katalog</h2>
                        <div class="shop-catalog-sort">
                            <label for="shop-sort">Sortiraj</label>
                            <select id="shop-sort" name="sort" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnovije</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Cena rastuće</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Cena opadajuće</option>
                            </select>
                        </div>
                    </div>

                    <div class="shop-catalog-search">
                        <label class="visually-hidden" for="shop-q">Pretraga u izlogu</label>
                        <input id="shop-q" type="search" name="q" value="<?= h($searchQ) ?>" placeholder="Pretraži u izlogu…" autocomplete="off">
                        <button type="submit" class="btn-sm btn-sm-primary">Traži</button>
                    </div>

                    <nav class="shop-type-tabs" aria-label="Tip oglasa">
                        <?php
                        $typeTabs = [
                            '' => ['label' => 'Sve', 'count' => $typeCounts['all']],
                            'telefon' => ['label' => 'Telefoni', 'count' => $typeCounts['telefon']],
                            'delovi' => ['label' => 'Delovi / oprema', 'count' => $typeCounts['delovi']],
                            'servis' => ['label' => 'Servis', 'count' => $typeCounts['servis']],
                        ];
                        foreach ($typeTabs as $tKey => $tMeta):
                            if ($tKey !== '' && (int)$tMeta['count'] === 0) {
                                continue;
                            }
                            $href = shopCatalogUrl($seller, array_merge($queryBase, ['type' => $tKey, 'page' => 1]));
                            $active = $filterType === $tKey;
                        ?>
                            <a class="shop-tab <?= $active ? 'is-active' : '' ?>" href="<?= h($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= h($tMeta['label']) ?> <span><?= (int)$tMeta['count'] ?></span></a>
                        <?php endforeach; ?>
                    </nav>

                    <?php if ($shopCategories !== []): ?>
                        <nav class="shop-cat-tabs" aria-label="Kategorije izloga">
                            <?php $catAllHref = shopCatalogUrl($seller, array_merge($queryBase, ['cat' => '', 'page' => 1])); ?>
                            <a class="shop-tab <?= $filterCat === '' ? 'is-active' : '' ?>" href="<?= h($catAllHref) ?>"<?= $filterCat === '' ? ' aria-current="page"' : '' ?>>Sve kategorije</a>
                            <?php foreach ($categoryCounts as $cc):
                                $href = shopCatalogUrl($seller, array_merge($queryBase, ['cat' => $cc['id'], 'page' => 1]));
                            ?>
                                <a class="shop-tab <?= $filterCat === $cc['id'] ? 'is-active' : '' ?>" href="<?= h($href) ?>"<?= $filterCat === $cc['id'] ? ' aria-current="page"' : '' ?>><?= h($cc['name']) ?> <span><?= (int)$cc['count'] ?></span></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <div class="shop-filters">
                        <div class="shop-filter">
                            <label for="shop-brand">Brend</label>
                            <select id="shop-brand" name="brand" onchange="this.form.submit()">
                                <option value="">Svi brendovi</option>
                                <?php foreach ($brandOptions as $brandOption): ?>
                                    <option value="<?= h($brandOption) ?>" <?= $filters['brand'] === $brandOption ? 'selected' : '' ?>><?= h($brandOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="shop-filter">
                            <label for="shop-model">Model</label>
                            <select id="shop-model" name="model" onchange="this.form.submit()">
                                <option value="">Svi modeli</option>
                                <?php
                                $modelChoices = $modelOptions;
                                if ($filters['model'] !== '' && !in_array($filters['model'], $modelChoices, true)) {
                                    array_unshift($modelChoices, $filters['model']);
                                }
                                foreach ($modelChoices as $modelOption):
                                ?>
                                    <option value="<?= h($modelOption) ?>" <?= $filters['model'] === $modelOption ? 'selected' : '' ?>><?= h($modelOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="shop-filter">
                            <span class="shop-filter-label" id="shop-price-label">Cijena</span>
                            <div class="shop-price" role="group" aria-labelledby="shop-price-label">
                                <input id="shop-min-price" type="number" inputmode="decimal" min="0" max="1000000" step="1" name="min_price" value="<?= h(shopCatalogPriceQueryValue($filters['min_price'])) ?>" placeholder="Od" aria-label="Cijena od, u eurima">
                                <span aria-hidden="true">–</span>
                                <input id="shop-max-price" type="number" inputmode="decimal" min="0" max="1000000" step="1" name="max_price" value="<?= h(shopCatalogPriceQueryValue($filters['max_price'])) ?>" placeholder="Do €" aria-label="Cijena do, u eurima">
                            </div>
                        </div>
                        <div class="shop-filter">
                            <label for="shop-condition">Stanje</label>
                            <select id="shop-condition" name="condition" onchange="this.form.submit()">
                                <option value="">Sva stanja</option>
                                <?php foreach (shopCatalogConditionOptions() as $conditionOption): ?>
                                    <option value="<?= h($conditionOption) ?>" <?= $filters['condition'] === $conditionOption ? 'selected' : '' ?>><?= h($conditionOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="shop-filter shop-filter-bh">
                            <label for="shop-battery">Baterija (BH)</label>
                            <select id="shop-battery" name="min_battery" onchange="this.form.submit()">
                                <?php foreach (shopCatalogBatteryOptions() as $batteryValue => $batteryLabel): ?>
                                    <option value="<?= h((string)$batteryValue) ?>" <?= (string)$filters['min_battery'] === (string)$batteryValue || ($batteryValue === '' && $filters['min_battery'] === 0) ? 'selected' : '' ?>><?= h($batteryLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="shop-filter shop-filter-apply">
                            <button type="submit" class="btn-sm btn-sm-primary">Primeni filtere</button>
                        </div>
                    </div>
                </form>

                <?php if (!$ads): ?>
                    <p class="shop-empty">Nema oglasa za izabrane filtere. <a href="<?= h(shopCatalogUrl($seller, $filterCat !== '' ? ['cat' => $filterCat] : [])) ?>">Prikaži sve</a></p>
                <?php else: ?>
                    <div class="listings shop-listings">
                        <?php foreach ($ads as $ad): ?>
                            <?= shopCatalogCardHtml($ad) ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($pagination['pages'] > 1): ?>
                        <div class="pagination">
                            <?php if ($pagination['page'] > 1): ?>
                                <a class="btn-sm" href="<?= h(shopCatalogUrl($seller, array_merge($queryBase, ['page' => $pagination['page'] - 1]))) ?>">← Prethodna</a>
                            <?php endif; ?>
                            <span class="form-hint" style="margin:0;align-self:center;">Strana <?= (int)$pagination['page'] ?> / <?= (int)$pagination['pages'] ?></span>
                            <?php if ($pagination['page'] < $pagination['pages']): ?>
                                <a class="btn-sm btn-sm-primary" href="<?= h(shopCatalogUrl($seller, array_merge($queryBase, ['page' => $pagination['page'] + 1]))) ?>">Sledeća →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
