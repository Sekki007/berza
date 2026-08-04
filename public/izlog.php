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
// Stari /izlog/{username} → kanonski slug
if ($param !== '' && rawurldecode($param) !== userShopSlug($seller)) {
    $qs = $_GET;
    unset($qs['u'], $qs['cat_from_path']);
    $target = shopCatalogUrl($seller, $qs);
    header('Location: ' . $target, true, 301);
    exit;
}

$allAds = getPublicAdsByUserId($sellerId, true);
$shopCategories = getShopCategories($seller);
$searchQ = trim((string)($_GET['q'] ?? ''));
$filterType = trim((string)($_GET['type'] ?? ''));
$filterCat = trim((string)($_GET['cat'] ?? ''));
$catFromPath = !empty($_GET['cat_from_path']);
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(12, (int)(siteSettings()['items_per_page'] ?? 20));

if (!in_array($filterType, ['telefon', 'delovi', 'servis', ''], true)) {
    $filterType = '';
}
if (!in_array($sort, ['newest', 'price_asc', 'price_desc'], true)) {
    $sort = 'newest';
}

$activeCategory = $filterCat !== '' ? findShopCategory($seller, $filterCat) : null;
if ($filterCat !== '' && !$activeCategory) {
    if ($catFromPath) {
        header('Location: ' . $shopLink, true, 301);
        exit;
    }
    $filterCat = '';
}

// Stari ?cat=slug → /izlog/{shop}/{slug}
$queryString = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
parse_str($queryString, $rawQuery);
if (!$catFromPath && $filterCat !== '' && isset($rawQuery['cat']) && trim((string)$rawQuery['cat']) !== '') {
    $redirectParams = [
        'cat' => $filterCat,
        'q' => $searchQ,
        'type' => $filterType,
        'sort' => $sort,
        'page' => $page,
    ];
    header('Location: ' . shopCatalogUrl($seller, $redirectParams), true, 301);
    exit;
}

$typeCounts = shopAdTypeCounts($allAds);
$categoryCounts = shopCategoryCounts($allAds, $shopCategories);
$adsFiltered = filterShopAds($allAds, [
    'q' => $searchQ,
    'type' => $filterType,
    'shop_category' => $filterCat,
    'sort' => $sort,
    'hide_sold' => false,
]);
$pagination = paginateAds($adsFiltered, $page, $perPage);
$ads = $pagination['items'];

$queryBase = array_filter([
    'q' => $searchQ,
    'type' => $filterType,
    'cat' => $filterCat,
    'sort' => $sort,
], static fn($v) => $v !== '');

$shopName = getSellerShopName($seller, $allAds);
$summary = getSellerRatingSummary($sellerId);
$ratings = getSellerRatings($sellerId);
$categoryLabel = $activeCategory ? (string)$activeCategory['name'] : null;
$shopSeo = seoShopMeta($seller, $shopName, $categoryLabel);
$pageDescription = $shopSeo['description'];
$canonicalUrl = absoluteUrl(shopCatalogUrl($seller, $filterCat !== '' ? ['cat' => $filterCat] : []));
$shopCover = trim((string)($seller['shop_page_cover'] ?? ''));
if ($shopCover !== '') {
    $pageImage = absoluteUrl($shopCover);
}
$currentUser = currentUser();
$isOwnShop = isLoggedIn() && (int)$currentUser['id'] === $sellerId;
$eligibility = isLoggedIn() && !$isOwnShop
    ? getRatingEligibility((int)$currentUser['id'], $sellerId)
    : ['allowed' => false, 'reasons' => [], 'eligible' => [], 'rules' => []];
$canRate = !empty($eligibility['allowed']);
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
    $vote = trim((string)($_POST['vote'] ?? ''));
    $comment = (string)($_POST['comment'] ?? '');
    $conversationKey = trim((string)($_POST['conversation_key'] ?? ''));
    $adId = isset($_POST['ad_id']) && $_POST['ad_id'] !== '' ? (int)$_POST['ad_id'] : null;

    if (saveSellerRating($sellerId, (int)currentUser()['id'], $vote, $comment, $adId, $conversationKey !== '' ? $conversationKey : null)) {
        setFlash('success', 'Ocena je sačuvana. Hvala!');
    } else {
        $fail = getRatingEligibility((int)currentUser()['id'], $sellerId);
        $msg = $fail['reasons'][0] ?? 'Ocena nije sačuvana. Proveri pravila ocenjivanja.';
        setFlash('danger', $msg);
    }
    header('Location: ' . $shopLink . '#ocene');
    exit;
}

$pageTitle = $shopSeo['title'];
$activePage = 'oglasi';
$showSearch = true;
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
            <div class="shop-header-main">
                <?= renderShopAvatarHtml($seller, $initials, 'shop-avatar') ?>
                <div class="shop-header-info">
                    <h1 class="shop-title"><?= h($shopName) ?> <?= renderSellerBadges($seller) ?></h1>
                    <p class="shop-meta">
                        <?= (int)$typeCounts['all'] ?> <?= (int)$typeCounts['all'] === 1 ? 'oglas' : 'oglasa' ?>
                    </p>
                    <div class="shop-rating"><?= renderReputation($summary, $shopLink) ?></div>
                    <?php if (!empty($seller['shop_bio'])): ?>
                        <p class="shop-bio"><?= nl2br(h((string)$seller['shop_bio'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($seller['phone'])): ?>
                        <p class="shop-phone"><a href="tel:<?= h((string)$seller['phone']) ?>"><?= h((string)$seller['phone']) ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="shop-actions">
                <button type="button" class="btn-message" data-copy-link data-copy-url="<?= h($shopLink) ?>">Kopiraj link izloga</button>
                <?php if (isDirectoryServiceFirm($seller) && trim((string)($seller['location'] ?? '')) !== ''): ?>
                    <a class="btn-message" href="<?= h(directoryServiceUrl($seller)) ?>" style="text-align:center;">Direktorijum firmi</a>
                <?php endif; ?>
                <?php if (storefrontIsActive($seller)): ?>
                    <a class="btn-message" href="<?= h(storefrontUrlForUser($seller)) ?>" style="text-align:center;">Mini sajt radnje</a>
                <?php endif; ?>
                <?php if (!$isOwnShop): ?>
                    <a class="btn-message" href="/report.php?user=<?= (int)$sellerId ?>" style="text-align:center;">Prijavi korisnika</a>
                <?php endif; ?>
                <input type="text" class="shop-link-input" readonly value="<?= h((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : '') . $shopLink) ?>" data-copy-full>
            </div>
        </div>

        <div class="form-card shop-catalog" style="margin-top:12px;" id="oglasi">
            <div class="shop-catalog-head">
                <h2>Katalog</h2>
                <span class="shop-catalog-count"><?= (int)$pagination['total'] ?> <?= (int)$pagination['total'] === 1 ? 'oglas' : 'oglasa' ?></span>
            </div>

            <?php if ($typeCounts['all'] === 0): ?>
                <p style="color:var(--text-muted);margin-top:8px;">Ovaj prodavac trenutno nema aktivnih oglasa.</p>
            <?php else: ?>
                <form method="GET" class="shop-catalog-toolbar" action="<?= h(shopCatalogUrl($seller, ['cat' => $filterCat])) ?>">
                    <div class="shop-catalog-search">
                        <input type="search" name="q" value="<?= h($searchQ) ?>" placeholder="Pretraži u izlogu…" aria-label="Pretraga u izlogu">
                        <button type="submit" class="btn-sm btn-sm-primary">Traži</button>
                    </div>
                    <?php if ($filterType !== ''): ?><input type="hidden" name="type" value="<?= h($filterType) ?>"><?php endif; ?>
                    <div class="shop-catalog-sort">
                        <label for="shop-sort">Sortiraj</label>
                        <select id="shop-sort" name="sort" onchange="this.form.submit()">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnovije</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Cena rastuće</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Cena opadajuće</option>
                        </select>
                    </div>
                </form>

                <nav class="shop-type-tabs" aria-label="Tip oglasa">
                    <?php
                    $typeTabs = [
                        '' => ['label' => 'Sve', 'count' => $typeCounts['all']],
                        'telefon' => ['label' => 'Uređaji', 'count' => $typeCounts['telefon']],
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
                        <a class="shop-tab <?= $active ? 'is-active' : '' ?>" href="<?= h($href) ?>"><?= h($tMeta['label']) ?> <span><?= (int)$tMeta['count'] ?></span></a>
                    <?php endforeach; ?>
                </nav>

                <?php if ($shopCategories !== []): ?>
                    <nav class="shop-cat-tabs" aria-label="Kategorije izloga">
                        <?php
                        $catAllHref = shopCatalogUrl($seller, array_merge($queryBase, ['cat' => '', 'page' => 1]));
                        ?>
                        <a class="shop-tab <?= $filterCat === '' ? 'is-active' : '' ?>" href="<?= h($catAllHref) ?>">Sve kategorije</a>
                        <?php foreach ($categoryCounts as $cc):
                            $href = shopCatalogUrl($seller, array_merge($queryBase, ['cat' => $cc['id'], 'page' => 1]));
                        ?>
                            <a class="shop-tab <?= $filterCat === $cc['id'] ? 'is-active' : '' ?>" href="<?= h($href) ?>"><?= h($cc['name']) ?> <span><?= (int)$cc['count'] ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <?php if (!$ads): ?>
                    <p style="color:var(--text-muted);margin-top:12px;">Nema oglasa za izabrane filtere. <a href="<?= h($shopLink) ?>">Prikaži sve</a></p>
                <?php else: ?>
                    <div class="listings shop-listings" style="margin-top:12px;">
                        <?php foreach ($ads as $ad): ?>
                            <?php
                            $shopCatalogMode = true;
                            $shopCatalogOwn = $isOwnShop;
                            require __DIR__ . '/partials/ad-card.php';
                            ?>
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

        <div class="form-card" style="margin-top:12px;" id="ocene">
            <h2>Ocene oglašivača</h2>

            <div class="rating-rules">
                <strong>Korisnika ne možete oceniti:</strong>
                <ul>
                    <li>ako ste se nedavno registrovali,</li>
                    <li>ako se iz Vaše konverzacije porukama ne može utvrditi da je do kupoprodaje došlo,</li>
                    <li>ako ste ga već ocenili pre manje od 7 dana,</li>
                    <li>ako je konverzacija starija od 30 dana,</li>
                    <li>ako ste korisnika već ocenili iz iste konverzacije.</li>
                </ul>
            </div>

            <?php if ($isOwnShop): ?>
                <p class="form-hint">Ovo je tvoj izlog — ne možeš oceniti sebe.</p>
            <?php elseif (!isLoggedIn()): ?>
                <p class="form-hint"><a href="/login.php">Prijavi se</a> da bi ocenio/la ovog oglašivača (uz ispunjene uslove).</p>
            <?php elseif ($canRate): ?>
                <form method="POST" class="rating-form">
                    <input type="hidden" name="action" value="rate">
                    <?php if (count($eligibility['eligible']) === 1): ?>
                        <input type="hidden" name="conversation_key" value="<?= h((string)$eligibility['eligible'][0]['key']) ?>">
                        <input type="hidden" name="ad_id" value="<?= (int)$eligibility['eligible'][0]['ad_id'] ?>">
                        <p class="form-hint">Ocena se vezuje za konverzaciju: <strong><?= h((string)$eligibility['eligible'][0]['title']) ?></strong></p>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Konverzacija / oglas</label>
                            <select name="conversation_key" required>
                                <?php foreach ($eligibility['eligible'] as $thread): ?>
                                    <option value="<?= h((string)$thread['key']) ?>"><?= h((string)$thread['title']) ?> (<?= (int)$thread['message_count'] ?> poruka)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Tvoja ocena</label>
                        <div class="vote-options">
                            <label class="vote-option vote-positive">
                                <input type="radio" name="vote" value="positive" required>
                                <span class="vote-icon">👍</span>
                                <span>Pozitivna</span>
                            </label>
                            <label class="vote-option vote-negative">
                                <input type="radio" name="vote" value="negative" required>
                                <span class="vote-icon">👎</span>
                                <span>Negativna</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Komentar (opciono)</label>
                        <textarea name="comment" rows="3" maxlength="500" placeholder="Kako je prošao kontakt / kupovina?"></textarea>
                    </div>
                    <button class="btn-call" type="submit">Pošalji ocenu</button>
                </form>
            <?php else: ?>
                <div class="rating-blocked">
                    <strong>Trenutno ne možeš oceniti ovog korisnika:</strong>
                    <ul>
                        <?php foreach ($eligibility['reasons'] as $reason): ?>
                            <li><?= h($reason) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="form-hint" style="margin-top:8px;">Pošalji poruku preko oglasa — kada iz razgovora bude jasno da je došlo do kupoprodaje, ocena će biti dostupna.</p>
                </div>
            <?php endif; ?>

            <?php if ($ratings): ?>
                <?php
                $positiveRatings = array_values(array_filter($ratings, static fn($r) => normalizeRatingVote($r['vote'] ?? $r['score'] ?? '') === 'positive'));
                $negativeRatings = array_values(array_filter($ratings, static fn($r) => normalizeRatingVote($r['vote'] ?? $r['score'] ?? '') === 'negative'));
                ?>
                <div class="ratings-filter-tabs">
                    <a href="#ocene" class="ratings-tab" data-ratings-tab="all">Sve (<?= count($ratings) ?>)</a>
                    <a href="#ocene-positive" class="ratings-tab" data-ratings-tab="positive">👍 Pozitivne (<?= count($positiveRatings) ?>)</a>
                    <a href="#ocene-negative" class="ratings-tab" data-ratings-tab="negative">👎 Negativne (<?= count($negativeRatings) ?>)</a>
                </div>

                <div class="ratings-list" id="ocene-all" data-ratings-panel="all">
                    <?php foreach ($ratings as $rating): ?>
                        <?php
                        $from = findUserById((int)($rating['from_user_id'] ?? 0));
                        $fromName = (string)($from['full_name'] ?? 'Korisnik');
                        $vote = normalizeRatingVote($rating['vote'] ?? $rating['score'] ?? '');
                        ?>
                        <div class="rating-item" data-vote="<?= h($vote) ?>">
                            <div class="rating-item-head">
                                <strong><?= h($fromName) ?></strong>
                                <?php if ($vote === 'positive'): ?>
                                    <span class="vote-tag vote-tag-pos">+ Pozitivna</span>
                                <?php else: ?>
                                    <span class="vote-tag vote-tag-neg">− Negativna</span>
                                <?php endif; ?>
                                <span class="rating-date"><?= h(formatRelativeTime((string)($rating['updated_at'] ?? $rating['created_at'] ?? ''))) ?></span>
                            </div>
                            <?php if (!empty($rating['comment'])): ?>
                                <p class="rating-comment"><?= nl2br(h((string)$rating['comment'])) ?></p>
                            <?php else: ?>
                                <p class="rating-comment rating-comment-empty">Bez komentara</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ratings-section" id="ocene-positive">
                    <h3 class="ratings-section-title">👍 Pozitivne ocene</h3>
                    <?php if (!$positiveRatings): ?>
                        <p class="form-hint">Nema pozitivnih ocena.</p>
                    <?php else: ?>
                        <div class="ratings-list">
                            <?php foreach ($positiveRatings as $rating): ?>
                                <?php
                                $from = findUserById((int)($rating['from_user_id'] ?? 0));
                                $fromName = (string)($from['full_name'] ?? 'Korisnik');
                                ?>
                                <div class="rating-item">
                                    <div class="rating-item-head">
                                        <strong><?= h($fromName) ?></strong>
                                        <span class="vote-tag vote-tag-pos">+ Pozitivna</span>
                                        <span class="rating-date"><?= h(formatRelativeTime((string)($rating['updated_at'] ?? $rating['created_at'] ?? ''))) ?></span>
                                    </div>
                                    <?php if (!empty($rating['comment'])): ?>
                                        <p class="rating-comment"><?= nl2br(h((string)$rating['comment'])) ?></p>
                                    <?php else: ?>
                                        <p class="rating-comment rating-comment-empty">Bez komentara</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ratings-section" id="ocene-negative">
                    <h3 class="ratings-section-title">👎 Negativne ocene</h3>
                    <?php if (!$negativeRatings): ?>
                        <p class="form-hint">Nema negativnih ocena.</p>
                    <?php else: ?>
                        <div class="ratings-list">
                            <?php foreach ($negativeRatings as $rating): ?>
                                <?php
                                $from = findUserById((int)($rating['from_user_id'] ?? 0));
                                $fromName = (string)($from['full_name'] ?? 'Korisnik');
                                ?>
                                <div class="rating-item">
                                    <div class="rating-item-head">
                                        <strong><?= h($fromName) ?></strong>
                                        <span class="vote-tag vote-tag-neg">− Negativna</span>
                                        <span class="rating-date"><?= h(formatRelativeTime((string)($rating['updated_at'] ?? $rating['created_at'] ?? ''))) ?></span>
                                    </div>
                                    <?php if (!empty($rating['comment'])): ?>
                                        <p class="rating-comment"><?= nl2br(h((string)$rating['comment'])) ?></p>
                                    <?php else: ?>
                                        <p class="rating-comment rating-comment-empty">Bez komentara</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p style="color:var(--text-muted);margin-top:12px;">Još nema ocena za ovog oglašivača.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
