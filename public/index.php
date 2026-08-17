<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Radi i bez nginx rewrite pravila: nepoznate putanje stižu ovde preko try_files.
if (preg_match('#^/(?:vodici|blog)/?$#', $requestPath) === 1) {
    require __DIR__ . '/vodici.php';
    exit;
}
if (preg_match('#^/(?:vodic|blog)/([^/]+)/?$#', $requestPath, $guideMatch) === 1) {
    $_GET['slug'] = rawurldecode((string)$guideMatch[1]);
    require __DIR__ . '/vodic.php';
    exit;
}
if (preg_match('#^/provera-imei/?$#', $requestPath) === 1) {
    require __DIR__ . '/provera-imei.php';
    exit;
}
if (preg_match('#^/(prijava|login)/?$#', $requestPath) === 1) {
    require __DIR__ . '/login.php';
    exit;
}
if (preg_match('#^/(registracija|registracij|register)/?$#', $requestPath) === 1) {
    require __DIR__ . '/register.php';
    exit;
}
if (preg_match('#^/(postavi-oglas|dodaj-oglas)/?$#', $requestPath) === 1) {
    require __DIR__ . '/ad_form.php';
    exit;
}
if (preg_match('#^/kako-radi/?$#', $requestPath) === 1) {
    require __DIR__ . '/kako-radi.php';
    exit;
}

$cfg = categoriesConfig();
$settings = siteSettings();
$landing = resolveListingLandingFromPath($requestPath);
if (is_array($landing) && !empty($landing['invalid'])) {
    http_response_code(404);
    echo 'Stranica nije pronađena.';
    exit;
}
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
$equipmentGroup = trim((string)($_GET['equipment_group'] ?? ''));
$equipmentType = trim((string)($_GET['equipment_type'] ?? ''));
$minBattery = trim((string)($_GET['min_battery'] ?? ''));
$listingType = trim((string)($_GET['listing_type'] ?? ''));
$schema = adFormSchema();
if (!in_array($type, ['telefon', 'delovi', 'servis'], true)) {
    $type = '';
}
if (!in_array($listingType, ['sell', 'buy', 'trade'], true)) {
    $listingType = '';
}
if ($deviceType !== '' && !in_array($deviceType, allowedDeviceTypes(), true)) {
    $deviceType = '';
}
if (!in_array($equipmentGroup, ['parts', 'oprema'], true)) {
    $equipmentGroup = '';
}
$allowedEquipTypes = array_map('strval', $schema['equipment_types'] ?? []);
if ($equipmentType !== '' && !in_array($equipmentType, $allowedEquipTypes, true)) {
    $equipmentType = '';
}
if ($minBattery !== '' && !in_array($minBattery, ['85', '90', '95', '100'], true)) {
    $minBattery = '';
}
if (is_array($landing) && !empty($landing['filters'])) {
    $landingFilters = (array)$landing['filters'];
    if (!empty($landingFilters['brand'])) {
        $brand = (string)$landingFilters['brand'];
    }
    if (!empty($landingFilters['model'])) {
        $model = (string)$landingFilters['model'];
    }
    if (!empty($landingFilters['location'])) {
        $location = (string)$landingFilters['location'];
    }
    if (!empty($landingFilters['type'])) {
        $type = (string)$landingFilters['type'];
    }
}
// Filter forma šalje browse_cat (Telefoni/Delovi/Oprema/Servis)
if (array_key_exists('browse_cat', $_GET)) {
    $mapped = browseCategoryToFilters(trim((string)$_GET['browse_cat']));
    $type = $mapped['type'];
    $equipmentGroup = $mapped['equipment_group'];
}
if ($equipmentGroup !== '' && $type === '') {
    $type = 'delovi';
}
if ($equipmentType !== '' && $type === '') {
    $type = 'delovi';
}
if ($equipmentType !== '' && in_array($equipmentType, equipmentPartsTypes(), true)) {
    $equipmentGroup = 'parts';
} elseif ($equipmentType !== '' && in_array($equipmentType, equipmentOpremaTypes(), true)) {
    $equipmentGroup = 'oprema';
}
if ($minBattery !== '' && $type === '') {
    $type = 'telefon';
}
// Ne prikazuj tip uređaja / nameru van konteksta
if ($type === 'servis') {
    $listingType = '';
    $condition = '';
    $deviceType = '';
} elseif ($type === 'delovi') {
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
    'equipment_group' => $equipmentGroup,
    'equipment_type' => $equipmentType,
    'min_battery' => $minBattery,
    'listing_type' => $listingType,
    'types' => $type !== '' ? [$type] : [],
    'sort' => $sort,
];

$allAds = getPublicAds($filters);
$resultsTotal = count($allAds);
$maxPerUser = (int)($settings['max_ads_per_user_homepage'] ?? 0);
if ($maxPerUser > 0) {
    $allAds = limitAdsPerUser($allAds, $maxPerUser);
}
$pagination = paginateAds($allAds, $page, $perPage);
$ads = $pagination['items'];
$maxPromoted = max(1, (int)$settings['max_promoted_ads']);
$promotedAds = [];
if (!empty($settings['show_promoted_section'])) {
    $promotedAds = array_values(array_filter(getPublicAds(['sort' => 'newest']), static fn($a) => isAdTopActive($a) && empty($a['is_sold'])));
    if ($maxPerUser > 0) {
        $promotedAds = limitAdsPerUser($promotedAds, $maxPerUser);
    }
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
    'equipment_group' => $equipmentGroup,
    'equipment_type' => $equipmentType,
    'min_battery' => $minBattery,
    'listing_type' => $listingType,
    'type' => $type,
    'sort' => $sort,
], static fn($v) => $v !== '');

$seoMeta = seoListingMeta([
    'q' => $search,
    'brand' => $brand,
    'model' => $model,
    'location' => $location,
    'type' => $type,
    'device_type' => $deviceType,
]);
$pageTitle = $seoMeta['title'];
$pageDescription = $seoMeta['description'];
$canonicalBasePath = is_array($landing) && !empty($landing['path']) ? (string)$landing['path'] : '/';
$canonicalUrl = absoluteUrl($canonicalBasePath === '/' ? '/' : $canonicalBasePath);
$landingHeading = '';
$indexableLanding = true;
if (is_array($landing)) {
    $landingFacets = (array)($landing['filters'] ?? []);
    $landingHeading = seoListingHeading($landingFacets !== [] ? $landingFacets : ['type' => $type]);
    $jsonLd = seoListingCollectionJsonLd(
        $landingFacets !== [] ? $landingFacets : ['type' => $type],
        $allAds,
        absoluteUrl($canonicalBasePath === '/' ? '/oglasi' : $canonicalBasePath),
        $pageDescription
    );
    $indexableLanding = listingLandingIndexable((array)($landing['filters'] ?? []), $allAds);
    if (!$indexableLanding) {
        $parentFilters = (array)($landing['filters'] ?? []);
        unset($parentFilters['model'], $parentFilters['location']);
        if ($parentFilters === [] && !empty($landing['filters']['location'])) {
            $parentFilters = ['type' => 'telefon'];
        }
        $canonicalUrl = absoluteUrl(listingLandingPath($parentFilters));
        $robotsMeta = 'noindex,follow';
    }
}
if (!is_array($landing) && $queryBase !== []) {
    $canonicalUrl = absoluteUrl(listingLandingPath([
        'type' => $type,
        'brand' => $brand,
        'model' => $model,
        'location' => $location,
    ]));
    if ($search !== '' || $minPrice !== '' || $maxPrice !== '' || $condition !== '' || $categoryGroup !== '' || $deviceType !== '' || $equipmentGroup !== '' || $sort !== 'newest' || $page > 1) {
        $robotsMeta = 'noindex,follow';
    }
}
if ($queryBase === [] && !is_array($landing)) {
    $canonicalUrl = absoluteUrl('/');
    $jsonLd = [seoOrganizationJsonLd(), seoWebsiteJsonLd()];
}
$activePage = 'oglasi';
$searchValue = $search;

// LCP: prva listing slika (ili prvi TOP oglas) — preload + eager
$lcpAd = $promotedAds[0] ?? ($ads[0] ?? null);
$lcpThumb = $lcpAd ? adPrimaryListingThumb($lcpAd) : null;
if ($lcpThumb) {
    $preloadImage = $lcpThumb;
}

$typeLabels = [
    'telefon' => 'Telefoni',
    'delovi' => 'Delovi',
    'servis' => 'Servis',
];
$equipmentGroupLabels = [
    'parts' => 'Delovi',
    'oprema' => 'Oprema',
];
$hasFilters = $search !== '' || $brand !== '' || $model !== '' || $location !== '' || $condition !== '' || $type !== '' || $listingType !== '' || $deviceType !== '' || $equipmentGroup !== '' || $equipmentType !== '' || $minBattery !== '' || $minPrice !== '' || $maxPrice !== '' || $categoryGroup !== '';
$activeFilterCount = 0;
foreach ([$brand, $model, $location, $condition, $type, $listingType, $deviceType, $equipmentGroup, $equipmentType, $minBattery, $minPrice, $maxPrice, $categoryGroup] as $fv) {
    if ($fv !== '') {
        $activeFilterCount++;
    }
}
$resetFiltersUrl = '/index.php' . ($search !== '' ? ('?' . http_build_query(['q' => $search])) : '');
$activeChips = [];
$chipDefs = [];
$listingTypeLabels = [
    'sell' => 'Prodajem',
    'buy' => 'Tražim',
    'trade' => 'Zamena',
];
if ($equipmentGroup !== '') {
    $chipDefs[] = ['key' => 'equipment_group', 'value' => $equipmentGroup, 'label' => $equipmentGroupLabels[$equipmentGroup] ?? $equipmentGroup, 'also_unset' => ['type']];
} elseif ($type !== '') {
    $chipDefs[] = ['key' => 'type', 'value' => $type, 'label' => $typeLabels[$type] ?? $type];
}
$chipDefs = array_merge($chipDefs, [
    ['key' => 'listing_type', 'value' => $listingType, 'label' => $listingTypeLabels[$listingType] ?? $listingType],
    ['key' => 'device_type', 'value' => $deviceType, 'label' => (string)($schema['device_types'][$deviceType] ?? $deviceType)],
    ['key' => 'equipment_type', 'value' => $equipmentType, 'label' => $equipmentType],
    ['key' => 'min_battery', 'value' => $minBattery, 'label' => $minBattery !== '' ? ('BH ' . $minBattery . '%+') : ''],
    ['key' => 'brand', 'value' => $brand, 'label' => $brand],
    ['key' => 'model', 'value' => $model, 'label' => $model],
    ['key' => 'location', 'value' => $location, 'label' => $location],
    ['key' => 'condition', 'value' => $condition, 'label' => $condition],
]);
foreach ($chipDefs as $chip) {
    if ($chip['value'] === '') {
        continue;
    }
    $params = $queryBase;
    unset($params[$chip['key']], $params['page']);
    foreach ((array)($chip['also_unset'] ?? []) as $extraKey) {
        unset($params[$extraKey]);
    }
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

// Predlozi filtera po nameri upita (npr. „lcd 15 pro“ → Samo delovi)
$searchIntentHints = [];
if ($search !== '') {
    $searchIntent = searchQueryIntent(searchTokens($search));
    $hintParams = $queryBase;
    unset($hintParams['page'], $hintParams['browse_cat']);
    $qLower = mb_strtolower($search);

    if ($searchIntent === 'parts') {
        $wantOprema = (bool)preg_match('/\b(maska|futrola|case|punjač|punjac|charger|kabl|cable|slušalice|airpods)\b/u', $qLower)
            && !preg_match('/\b(lcd|oled|ekran|displej|screen|baterija|flex|deo|delovi)\b/u', $qLower);

        if ($wantOprema) {
            if ($equipmentGroup !== 'oprema') {
                $params = array_merge($hintParams, ['type' => 'delovi', 'equipment_group' => 'oprema']);
                unset($params['device_type']);
                $qs = buildFilterQuery($params);
                $searchIntentHints[] = [
                    'label' => 'Samo oprema',
                    'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
                ];
            }
        } elseif (!($type === 'delovi' && $equipmentGroup === 'parts')) {
            $params = array_merge($hintParams, ['type' => 'delovi', 'equipment_group' => 'parts']);
            unset($params['device_type']);
            $qs = buildFilterQuery($params);
            $searchIntentHints[] = [
                'label' => 'Samo delovi',
                'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
            ];
        }
    } elseif ($searchIntent === 'phone' && $type !== 'telefon') {
        $params = array_merge($hintParams, ['type' => 'telefon']);
        unset($params['equipment_group'], $params['device_type']);
        $qs = buildFilterQuery($params);
        $searchIntentHints[] = [
            'label' => 'Samo telefoni',
            'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
        ];
    } elseif ($searchIntent === 'service' && $type !== 'servis') {
        $params = array_merge($hintParams, ['type' => 'servis']);
        unset($params['equipment_group'], $params['device_type'], $params['brand'], $params['model']);
        $qs = buildFilterQuery($params);
        $searchIntentHints[] = [
            'label' => 'Samo servis',
            'href' => '/index.php' . ($qs !== '' ? ('?' . $qs) : ''),
        ];
    }
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

$homeCats = [
    [
        'type' => 'telefon',
        'equipment_group' => '',
        'label' => 'Telefoni',
        'class' => 'is-phone',
        'icon' => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><rect x="10" y="6" width="18" height="32" rx="3" fill="none" stroke="currentColor" stroke-width="2.4"/><rect x="20" y="12" width="18" height="32" rx="3" fill="currentColor" opacity=".92"/><rect x="25" y="16" width="8" height="2.2" rx="1" fill="#fff" opacity=".9"/></svg>',
    ],
    [
        'type' => 'delovi',
        'equipment_group' => 'parts',
        'label' => 'Delovi',
        'class' => 'is-parts',
        'icon' => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><rect x="8" y="8" width="14" height="26" rx="2" fill="none" stroke="currentColor" stroke-width="2.3"/><path d="M28 14h12v6H28zm2 10h8v14h-8z" fill="currentColor"/><circle cx="15" cy="37" r="2.2" fill="currentColor"/></svg>',
    ],
    [
        'type' => 'delovi',
        'equipment_group' => 'oprema',
        'label' => 'Oprema',
        'class' => 'is-gear',
        'icon' => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M14 10h12a3 3 0 0 1 3 3v22a3 3 0 0 1-3 3H14a3 3 0 0 1-3-3V13a3 3 0 0 1 3-3z" fill="none" stroke="currentColor" stroke-width="2.3"/><path d="M30 18h10.5a2.5 2.5 0 0 1 2.5 2.5V24H46v6h-3v3.5a2.5 2.5 0 0 1-2.5 2.5H30" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/><path d="M30 22h8v10h-8z" fill="currentColor"/></svg>',
    ],
    [
        'type' => 'servis',
        'equipment_group' => '',
        'label' => 'Servis',
        'class' => 'is-service',
        'icon' => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M18 11a7 7 0 0 1 9 6.2L36 26.2 31.2 31 22.4 22.2A7 7 0 1 1 18 11Z" fill="currentColor"/><path d="M14 33.5 20.5 27l3.8 3.8-6.5 6.5H14v-3.8Z" fill="currentColor" opacity=".9"/><rect x="29" y="10" width="3.2" height="14" rx="1.2" transform="rotate(45 30.6 17)" fill="currentColor"/></svg>',
    ],
];
?>

<div class="home-cat-wrap">
    <nav class="home-cat-tiles" aria-label="Kategorije">
        <?php foreach ($homeCats as $cat):
            $catHref = listingLandingPath(['type' => $cat['type']]);
            if ($cat['equipment_group'] !== '') {
                $catHref .= '?' . http_build_query(['equipment_group' => $cat['equipment_group']]);
            }
            $isActive = $type === $cat['type']
                && (($cat['equipment_group'] === '' && $equipmentGroup === '')
                    || $equipmentGroup === $cat['equipment_group']);
        ?>
            <a class="home-cat-tile <?= h($cat['class']) ?><?= $isActive ? ' is-active' : '' ?>" href="<?= h($catHref) ?>">
                <span class="home-cat-tile-icon"><?= $cat['icon'] ?></span>
                <span class="home-cat-tile-label"><?= h($cat['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

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
                <button class="filter-apply" type="submit">Prikaži <?= (int)$resultsTotal ?> oglasa</button>
            </div>
        </form>
    </aside>

    <main class="content">
        <?php if ($landingHeading !== ''): ?>
            <header class="listing-landing-head">
                <h1 class="listing-landing-title"><?= h($landingHeading) ?></h1>
                <p class="listing-landing-sub"><?= h($pageDescription) ?></p>
            </header>
        <?php endif; ?>
        <div class="listing-controls">
            <div class="results-meta">
                <span class="results-count"><strong data-results-count data-results-total="<?= (int)$resultsTotal ?>"><?= (int)$resultsTotal ?></strong> oglasa</span>
                <?php if ($hasFilters): ?>
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="/nalog.php" class="save-search-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_search">
                            <input type="hidden" name="q" value="<?= h($search) ?>">
                            <input type="hidden" name="brand" value="<?= h($brand) ?>">
                            <input type="hidden" name="model" value="<?= h($model) ?>">
                            <input type="hidden" name="location" value="<?= h($location) ?>">
                            <input type="hidden" name="condition" value="<?= h($condition) ?>">
                            <input type="hidden" name="type" value="<?= h($type) ?>">
                            <input type="hidden" name="device_type" value="<?= h($deviceType) ?>">
                            <input type="hidden" name="equipment_group" value="<?= h($equipmentGroup) ?>">
                            <input type="hidden" name="equipment_type" value="<?= h($equipmentType) ?>">
                            <input type="hidden" name="min_battery" value="<?= h($minBattery) ?>">
                            <input type="hidden" name="min_price" value="<?= h($minPrice) ?>">
                            <input type="hidden" name="max_price" value="<?= h($maxPrice) ?>">
                            <input type="hidden" name="category_group" value="<?= h($categoryGroup) ?>">
                            <input type="hidden" name="alert_enabled" value="1">
                            <button class="results-save-link" type="submit">Sačuvaj pretragu</button>
                        </form>
                    <?php else: ?>
                        <a class="results-save-link" href="/login.php">Sačuvaj pretragu</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
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
                <div class="view-toggle" data-view-toggle aria-label="Prikaz oglasa">
                    <button type="button" class="view-toggle-btn active" data-view="list" title="Lista" aria-label="Lista" aria-pressed="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                    </button>
                    <button type="button" class="view-toggle-btn" data-view="grid" title="Mreža" aria-label="Mreža" aria-pressed="false">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($activeChips !== [] || $searchIntentHints !== []): ?>
            <div class="active-filters" aria-label="Filteri pretrage">
                <?php if ($searchIntentHints !== []): ?>
                    <span class="search-intent-label">Predlog:</span>
                    <?php foreach ($searchIntentHints as $hint): ?>
                        <a class="search-intent-chip" href="<?= h($hint['href']) ?>"><?= h($hint['label']) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php foreach ($activeChips as $chip): ?>
                    <a class="active-filter-chip" href="<?= h($chip['href']) ?>"><?= h($chip['label']) ?> <span aria-hidden="true">×</span></a>
                <?php endforeach; ?>
                <?php if ($activeChips !== []): ?>
                    <a class="active-filter-clear" href="<?= h($resetFiltersUrl) ?>">Poništi sve</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($promotedAds && $page === 1 && $search === '' && $brand === '' && $location === ''): ?>
            <div class="promo-section">
                <div class="promo-section-head">⭐ Istaknuti oglasi</div>
                <div class="listings compact-list">
                    <?php foreach ($promotedAds as $pi => $ad): ?>
                        <?php $cardImgPriority = $pi < 2; require __DIR__ . '/partials/ad-card.php'; $cardImgPriority = false; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="listings view-list" data-listings>
            <?php foreach ($ads as $ai => $ad): ?>
                <?php $cardImgPriority = $ai < 3 && $promotedAds === []; require __DIR__ . '/partials/ad-card.php'; $cardImgPriority = false; ?>
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
            <button class="filter-apply" type="submit">Prikaži <?= (int)$resultsTotal ?> oglasa</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
