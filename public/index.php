<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$cfg = categoriesConfig();
$settings = siteSettings();
$search = trim((string)($_GET['q'] ?? ''));
$brand = trim((string)($_GET['brand'] ?? ''));
$model = trim((string)($_GET['model'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$maxPrice = trim((string)($_GET['max_price'] ?? ''));
$minPrice = trim((string)($_GET['min_price'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? ''));
$categoryGroup = trim((string)($_GET['category_group'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$deviceType = trim((string)($_GET['device_type'] ?? ''));
$schema = adFormSchema();
if (!in_array($type, ['telefon', 'delovi', 'servis'], true)) {
    $type = '';
}
if ($deviceType !== '' && !in_array($deviceType, allowedDeviceTypes(), true)) {
    $deviceType = '';
}
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)$settings['items_per_page']);

$filters = [
    'q' => $search,
    'brand' => $brand,
    'model' => $model,
    'location' => $location,
    'max_price' => $maxPrice,
    'min_price' => $minPrice,
    'condition' => $condition,
    'category_group' => $categoryGroup,
    'device_type' => $deviceType,
    'types' => $type !== '' ? [$type] : [],
    'sort' => $sort,
];

$allAds = getPublicAds($filters);
$pagination = paginateAds($allAds, $page, $perPage);
$ads = $pagination['items'];
$maxPromoted = max(1, (int)$settings['max_promoted_ads']);
$promotedAds = [];
if (!empty($settings['show_promoted_section'])) {
    $promotedAds = array_values(array_filter(getPublicAds(['sort' => 'newest']), static fn($a) => isAdTopActive($a) && empty($a['is_sold'])));
    $promotedAds = array_slice($promotedAds, 0, $maxPromoted);
}

$queryBase = array_filter([
    'q' => $search,
    'brand' => $brand,
    'model' => $model,
    'location' => $location,
    'max_price' => $maxPrice,
    'min_price' => $minPrice,
    'condition' => $condition,
    'category_group' => $categoryGroup,
    'device_type' => $deviceType,
    'type' => $type,
    'sort' => $sort,
], static fn($v) => $v !== '');

$seoMeta = seoListingMeta([
    'q' => $search,
    'brand' => $brand,
    'location' => $location,
    'type' => $type,
    'device_type' => $deviceType,
]);
$pageTitle = $seoMeta['title'];
$pageDescription = $seoMeta['description'];
$canonicalUrl = absoluteUrl('/' . ($queryBase === [] ? '' : ('index.php?' . http_build_query($queryBase))));
if ($queryBase === []) {
    $canonicalUrl = absoluteUrl('/');
    $jsonLd = [seoOrganizationJsonLd(), seoWebsiteJsonLd()];
}
$activePage = 'oglasi';
$searchValue = $search;

$typeLabels = [
    'telefon' => 'Uređaji',
    'delovi' => 'Oprema',
    'servis' => 'Servis',
];
$hasFilters = $search !== '' || $brand !== '' || $model !== '' || $location !== '' || $condition !== '' || $type !== '' || $deviceType !== '' || $minPrice !== '' || $maxPrice !== '' || $categoryGroup !== '';
$activeFilterCount = 0;
foreach ([$brand, $model, $location, $condition, $type, $deviceType, $minPrice, $maxPrice, $categoryGroup] as $fv) {
    if ($fv !== '') {
        $activeFilterCount++;
    }
}
$resetFiltersUrl = '/index.php' . ($search !== '' ? ('?' . http_build_query(['q' => $search])) : '');
$activeChips = [];
$chipDefs = [
    ['key' => 'type', 'value' => $type, 'label' => $typeLabels[$type] ?? $type],
    ['key' => 'device_type', 'value' => $deviceType, 'label' => (string)($schema['device_types'][$deviceType] ?? $deviceType)],
    ['key' => 'brand', 'value' => $brand, 'label' => $brand],
    ['key' => 'model', 'value' => $model, 'label' => $model],
    ['key' => 'location', 'value' => $location, 'label' => $location],
    ['key' => 'condition', 'value' => $condition, 'label' => $condition],
];
foreach ($chipDefs as $chip) {
    if ($chip['value'] === '') {
        continue;
    }
    $params = $queryBase;
    unset($params[$chip['key']], $params['page']);
    $qs = buildFilterQuery($params);
    $activeChips[] = [
        'label' => $chip['label'],
        'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
    ];
}
if ($minPrice !== '' || $maxPrice !== '') {
    $priceLabel = ($minPrice !== '' ? $minPrice : '0') . '–' . ($maxPrice !== '' ? $maxPrice . '€' : '…€');
    if ($minPrice !== '' && $maxPrice === '') {
        $priceLabel = 'od ' . $minPrice . '€';
    } elseif ($minPrice === '' && $maxPrice !== '') {
        $priceLabel = 'do ' . $maxPrice . '€';
    }
    $params = $queryBase;
    unset($params['min_price'], $params['max_price'], $params['page']);
    $qs = buildFilterQuery($params);
    $activeChips[] = [
        'label' => $priceLabel,
        'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
    ];
}

if ($search !== '') {
    facebookPixelPageEvent('Search', [
        'search_string' => $search,
        'content_category' => $type !== '' ? $type : 'all',
    ]);
    googleTagPageEvent('search', [
        'search_term' => $search,
        'content_category' => $type !== '' ? $type : 'all',
    ]);
}

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <aside class="sidebar">
        <form method="GET" class="filter-box filter-panel" data-filter-form>
            <div class="filter-head">
                <span>Filteri</span>
                <?php if ($activeFilterCount > 0): ?>
                    <a class="filter-reset-link" href="<?= h($resetFiltersUrl) ?>">Poništi</a>
                <?php endif; ?>
            </div>
            <div class="filter-body">
                <?php $filterLayout = 'sidebar'; require __DIR__ . '/partials/filter-fields.php'; ?>
            </div>
            <div class="filter-panel-actions">
                <button class="filter-apply" type="submit">Prikaži <?= (int)$pagination['total'] ?> oglasa</button>
            </div>
        </form>
    </aside>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Oglasi (<?= (int)$pagination['total'] ?>)</div>
        <div class="listing-toolbar">
            <button class="mobile-filter-btn" type="button" data-open-filters>
                Filteri<?php if ($activeFilterCount > 0): ?> <span class="filter-btn-badge"><?= (int)$activeFilterCount ?></span><?php endif; ?>
            </button>
            <form method="GET" class="sort-bar" aria-label="Sortiranje">
                <?php foreach ($queryBase as $k => $v): if ($k === 'sort' || $k === 'page') continue; ?>
                    <input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>">
                <?php endforeach; ?>
                <label class="sort-bar-label" for="listing-sort">Sortiraj</label>
                <select class="sort-select" id="listing-sort" name="sort" onchange="this.form.submit()">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnovije</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Cena rastuće</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Cena opadajuće</option>
                </select>
            </form>
        </div>

        <?php if ($activeChips !== []): ?>
            <div class="active-filters" aria-label="Aktivni filteri">
                <?php foreach ($activeChips as $chip): ?>
                    <a class="active-filter-chip" href="<?= h($chip['href']) ?>"><?= h($chip['label']) ?> <span aria-hidden="true">×</span></a>
                <?php endforeach; ?>
                <a class="active-filter-clear" href="<?= h($resetFiltersUrl) ?>">Poništi sve</a>
            </div>
        <?php endif; ?>

        <?php if ($promotedAds && $page === 1 && $search === '' && $brand === '' && $location === ''): ?>
            <div class="promo-section">
                <div class="promo-section-head">⭐ Istaknuti oglasi</div>
                <div class="listings compact-list">
                    <?php foreach ($promotedAds as $ad): ?>
                        <?php require __DIR__ . '/partials/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($search === '' && $brand === '' && $page === 1): ?>
            <?php
            $chipCities = getCitiesWithActiveAds(8, $location);
            if ($chipCities !== []):
            ?>
            <div class="quick-city-chips">
                <?php foreach ($chipCities as $city): ?>
                    <a class="quick-chip <?= $location === $city ? 'active' : '' ?>" href="/index.php?<?= h(buildFilterQuery(array_merge($queryBase, ['location' => $city, 'page' => null]))) ?>"><?= h($city) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="results-bar">
            <span class="results-count">Pronađeno: <strong data-results-count><?= (int)$pagination['total'] ?></strong> oglasa</span>
            <div class="results-bar-right">
                <?php if ($hasFilters): ?>
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="/nalog.php" class="save-search-form">
                            <input type="hidden" name="action" value="save_search">
                            <input type="hidden" name="q" value="<?= h($search) ?>">
                            <input type="hidden" name="brand" value="<?= h($brand) ?>">
                            <input type="hidden" name="model" value="<?= h($model) ?>">
                            <input type="hidden" name="location" value="<?= h($location) ?>">
                            <input type="hidden" name="condition" value="<?= h($condition) ?>">
                            <input type="hidden" name="type" value="<?= h($type) ?>">
                            <input type="hidden" name="device_type" value="<?= h($deviceType) ?>">
                            <input type="hidden" name="min_price" value="<?= h($minPrice) ?>">
                            <input type="hidden" name="max_price" value="<?= h($maxPrice) ?>">
                            <input type="hidden" name="category_group" value="<?= h($categoryGroup) ?>">
                            <input type="hidden" name="alert_enabled" value="1">
                            <button class="btn-sm" type="submit" title="Sačuvaj pretragu i alert">Sačuvaj pretragu</button>
                        </form>
                    <?php else: ?>
                        <a class="btn-sm" href="/login.php">Sačuvaj pretragu</a>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="view-toggle" data-view-toggle aria-label="Prikaz oglasa">
                    <button type="button" class="view-toggle-btn active" data-view="list" title="Lista" aria-pressed="true">Lista</button>
                    <button type="button" class="view-toggle-btn" data-view="grid" title="Mreža" aria-pressed="false">Mreža</button>
                </div>
                <span class="results-page">Strana <?= (int)$pagination['page'] ?> / <?= (int)$pagination['pages'] ?></span>
            </div>
        </div>

        <div class="listings view-list" data-listings>
            <?php foreach ($ads as $ad): ?>
                <?php require __DIR__ . '/partials/ad-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <div class="empty-state">Nema oglasa za izabranu kombinaciju filtera.</div>

        <?php if ($pagination['pages'] > 1): ?>
            <div class="pagination">
                <?php if ($pagination['page'] > 1): ?>
                    <a class="btn-sm" href="/index.php?<?= h(buildFilterQuery(array_merge($queryBase, ['page' => $pagination['page'] - 1]))) ?>">← Prethodna</a>
                <?php endif; ?>
                <?php if ($pagination['page'] < $pagination['pages']): ?>
                    <a class="btn-sm btn-sm-primary" href="/index.php?<?= h(buildFilterQuery(array_merge($queryBase, ['page' => $pagination['page'] + 1]))) ?>">Učitaj još</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<div class="filter-overlay"></div>
<div class="filter-drawer" role="dialog" aria-modal="true" aria-labelledby="filter-drawer-title">
    <form method="GET" class="filter-drawer-form" data-filter-form>
        <div class="filter-drawer-handle" aria-hidden="true"></div>
        <div class="filter-drawer-head">
            <h3 id="filter-drawer-title">Filteri</h3>
            <div class="filter-drawer-head-actions">
                <?php if ($activeFilterCount > 0): ?>
                    <a class="filter-reset-link" href="<?= h($resetFiltersUrl) ?>">Poništi sve</a>
                <?php endif; ?>
                <button class="filter-drawer-close" type="button" data-close-filters aria-label="Zatvori">×</button>
            </div>
        </div>
        <div class="filter-drawer-body">
            <?php $filterLayout = 'drawer'; require __DIR__ . '/partials/filter-fields.php'; ?>
        </div>
        <div class="filter-drawer-footer">
            <a class="filter-drawer-reset" href="<?= h($resetFiltersUrl) ?>">Poništi</a>
            <button class="filter-apply" type="submit">Prikaži <?= (int)$pagination['total'] ?> oglasa</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
