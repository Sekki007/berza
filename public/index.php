<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$cfg = categoriesConfig();
$schema = adFormSchema();
$settings = siteSettings();
$search = trim((string)($_GET['q'] ?? ''));
$brand = trim((string)($_GET['brand'] ?? ''));
$model = trim((string)($_GET['model'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$maxPrice = trim((string)($_GET['max_price'] ?? ''));
$minPrice = trim((string)($_GET['min_price'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? ''));
$categoryGroup = trim((string)($_GET['category_group'] ?? ''));
$storage = trim((string)($_GET['storage'] ?? ''));
$listingType = trim((string)($_GET['listing_type'] ?? ''));
$equipmentType = trim((string)($_GET['equipment_type'] ?? ''));
$onlyPriced = !empty($_GET['only_priced']);
$onlyPhotos = !empty($_GET['only_photos']);
$type = trim((string)($_GET['type'] ?? ''));
if (!in_array($type, ['telefon', 'delovi', 'servis'], true)) {
    $type = '';
}
if (!in_array($listingType, ['sell', 'buy', 'trade', 'service'], true)) {
    $listingType = '';
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
    'storage' => $storage,
    'listing_type' => $listingType,
    'equipment_type' => $equipmentType,
    'only_priced' => $onlyPriced,
    'only_photos' => $onlyPhotos,
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
    'storage' => $storage,
    'listing_type' => $listingType,
    'equipment_type' => $equipmentType,
    'only_priced' => $onlyPriced ? '1' : '',
    'only_photos' => $onlyPhotos ? '1' : '',
    'type' => $type,
    'sort' => $sort,
], static fn($v) => $v !== '' && $v !== null);

$pageTitle = 'TelefonBerza — Oglasi';
$activePage = 'oglasi';
$searchValue = $search;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <aside class="sidebar">
        <form method="GET" class="filter-box">
            <div class="filter-head">Filteri</div>
            <div class="filter-body">
                <?php
                $includeHiddenQ = false;
                require __DIR__ . '/partials/ads-filters.php';
                ?>
                <button class="filter-apply" type="submit">Primeni filtere</button>
                <a href="/index.php" class="btn-message" style="display:block;text-align:center;margin-top:8px;">Poništi filtere</a>
            </div>
        </form>
    </aside>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Oglasi (<?= (int)$pagination['total'] ?>)</div>
        <button class="mobile-filter-btn" data-open-filters>Filteri i sortiranje</button>

        <?php if ($promotedAds && $page === 1 && $search === '' && $brand === '' && $location === ''): ?>
            <div class="promo-section">
                <div class="promo-section-head">⭐ Istaknuti oglasi</div>
                <div class="ads-list compact-list">
                    <?php foreach ($promotedAds as $ad): ?>
                        <?php require __DIR__ . '/partials/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($search === '' && $brand === '' && $page === 1): ?>
            <div class="quick-city-chips">
                <?php foreach (array_slice($cfg['cities'], 0, 8) as $city): ?>
                    <a class="quick-chip <?= $location === $city ? 'active' : '' ?>" href="/index.php?<?= h(buildFilterQuery(array_merge($queryBase, ['location' => $city, 'page' => null]))) ?>"><?= h($city) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="results-bar">
            <span class="results-count">Pronađeno: <strong data-results-count><?= (int)$pagination['total'] ?></strong> oglasa</span>
            <div class="results-bar-right">
                <?php
                $hasFilters = $search !== '' || $brand !== '' || $model !== '' || $location !== '' || $condition !== '' || $type !== ''
                    || $minPrice !== '' || $maxPrice !== '' || $categoryGroup !== '' || $storage !== '' || $listingType !== ''
                    || $equipmentType !== '' || $onlyPriced || $onlyPhotos;
                if ($hasFilters):
                ?>
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="/nalog.php" class="save-search-form">
                            <input type="hidden" name="action" value="save_search">
                            <input type="hidden" name="q" value="<?= h($search) ?>">
                            <input type="hidden" name="brand" value="<?= h($brand) ?>">
                            <input type="hidden" name="model" value="<?= h($model) ?>">
                            <input type="hidden" name="location" value="<?= h($location) ?>">
                            <input type="hidden" name="condition" value="<?= h($condition) ?>">
                            <input type="hidden" name="type" value="<?= h($type) ?>">
                            <input type="hidden" name="min_price" value="<?= h($minPrice) ?>">
                            <input type="hidden" name="max_price" value="<?= h($maxPrice) ?>">
                            <input type="hidden" name="category_group" value="<?= h($categoryGroup) ?>">
                            <input type="hidden" name="storage" value="<?= h($storage) ?>">
                            <input type="hidden" name="listing_type" value="<?= h($listingType) ?>">
                            <input type="hidden" name="equipment_type" value="<?= h($equipmentType) ?>">
                            <?php if ($onlyPriced): ?><input type="hidden" name="only_priced" value="1"><?php endif; ?>
                            <?php if ($onlyPhotos): ?><input type="hidden" name="only_photos" value="1"><?php endif; ?>
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

        <div class="ads-list view-list" data-ads-list>
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
<div class="filter-drawer">
    <div class="filter-drawer-head"><h3>Filteri</h3><button class="filter-drawer-close" data-close-filters>×</button></div>
    <div class="filter-drawer-body">
        <form method="GET">
            <?php
            $includeHiddenQ = true;
            require __DIR__ . '/partials/ads-filters.php';
            ?>
            <button class="filter-apply" type="submit">Primeni filtere</button>
            <a href="/index.php" class="btn-message" style="display:block;text-align:center;margin-top:8px;">Poništi</a>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
