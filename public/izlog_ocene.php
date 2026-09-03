<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$param = trim((string)($_GET['u'] ?? ''));
$seller = resolveShopUserFromParam($param);

if (!$seller) {
    http_response_code(404);
    $pageTitle = 'Ocene — izlog nije pronađen — KupiTelefon';
    $activePage = 'oglasi';
    $showSearch = true;
    require __DIR__ . '/partials/layout-start.php';
    echo '<div class="main-wrap"><main class="content"><div class="form-card"><h2>Izlog nije pronađen</h2><p style="margin-top:10px;color:var(--text-muted);">Proveri link ili se vrati na <a href="/">početnu</a>.</p></div></main></div>';
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

$sellerId = (int)$seller['id'];
$shopLink = shopUrlForUser($seller);
$reviewsLink = shopReviewsUrl($seller);

if ($param !== '' && rawurldecode($param) !== userShopSlug($seller)) {
    $filterQ = trim((string)($_GET['filter'] ?? ''));
    $target = shopReviewsUrl($seller, in_array($filterQ, ['positive', 'negative'], true) ? $filterQ : '');
    header('Location: ' . $target, true, 301);
    exit;
}

$filter = trim((string)($_GET['filter'] ?? ''));
if (!in_array($filter, ['positive', 'negative', ''], true)) {
    $filter = '';
}

$allAds = getPublicAdsByUserId($sellerId, true);
$shopName = getSellerShopName($seller, $allAds);
$summary = getSellerRatingSummary($sellerId);
$ratings = getSellerRatings($sellerId);
$positiveRatings = array_values(array_filter(
    $ratings,
    static fn($r) => normalizeRatingVote($r['vote'] ?? $r['score'] ?? '') === 'positive'
));
$negativeRatings = array_values(array_filter(
    $ratings,
    static fn($r) => normalizeRatingVote($r['vote'] ?? $r['score'] ?? '') === 'negative'
));

$currentUser = currentUser();
$isOwnShop = isLoggedIn() && (int)$currentUser['id'] === $sellerId;
$eligibility = isLoggedIn() && !$isOwnShop
    ? getRatingEligibility((int)$currentUser['id'], $sellerId)
    : ['allowed' => false, 'reasons' => [], 'eligible' => [], 'rules' => []];
$canRate = !empty($eligibility['allowed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate') {
    requireCsrf($reviewsLink);
    requireLogin();
    $vote = trim((string)($_POST['vote'] ?? ''));
    $comment = (string)($_POST['comment'] ?? '');
    $conversationKey = trim((string)($_POST['conversation_key'] ?? ''));
    $adId = isset($_POST['ad_id']) && $_POST['ad_id'] !== '' ? (int)$_POST['ad_id'] : null;

    if (saveSellerRating($sellerId, (int)currentUser()['id'], $vote, $comment, $adId, $conversationKey !== '' ? $conversationKey : null)) {
        setFlash('success', 'Ocena je sačuvana. Hvala!');
        $redirFilter = normalizeRatingVote($vote) === 'negative' ? 'negative' : 'positive';
        header('Location: ' . shopReviewsUrl($seller, $redirFilter));
        exit;
    }

    $fail = getRatingEligibility((int)currentUser()['id'], $sellerId);
    $msg = $fail['reasons'][0] ?? 'Ocena nije sačuvana. Proveri pravila ocenjivanja.';
    setFlash('danger', $msg);
    header('Location: ' . $reviewsLink);
    exit;
}

$filterLabel = $filter === 'positive' ? 'Pozitivne ocene' : ($filter === 'negative' ? 'Negativne ocene' : 'Ocene');
$pageTitle = $filterLabel . ' — ' . $shopName . ' — KupiTelefon';
$pageDescription = 'Ocene kupaca za ' . $shopName . ' na KupiTelefon.rs. Pozitivne: ' . (int)$summary['positive'] . ', negativne: ' . (int)$summary['negative'] . '.';
$canonicalUrl = absoluteUrl(shopReviewsUrl($seller, $filter));
$activePage = 'oglasi';
$showSearch = true;

require __DIR__ . '/partials/layout-start.php';

$renderRatingItem = static function (array $rating): void {
    $from = findUserById((int)($rating['from_user_id'] ?? 0));
    $fromName = (string)($from['full_name'] ?? 'Korisnik');
    $vote = normalizeRatingVote($rating['vote'] ?? $rating['score'] ?? '');
    $fromShop = $from ? shopUrlForUser($from) : '';
    $adId = (int)($rating['ad_id'] ?? 0);
    $ad = $adId > 0 ? getAdById($adId) : null;
    ?>
    <article class="rating-item rating-item-card">
        <div class="rating-item-head">
            <?php if ($fromShop !== '' && $fromShop !== '/izlog.php'): ?>
                <strong><a href="<?= h($fromShop) ?>"><?= h($fromName) ?></a></strong>
            <?php else: ?>
                <strong><?= h($fromName) ?></strong>
            <?php endif; ?>
            <?php if ($vote === 'negative'): ?>
                <span class="vote-tag vote-tag-neg">− Negativna</span>
            <?php else: ?>
                <span class="vote-tag vote-tag-pos">+ Pozitivna</span>
            <?php endif; ?>
            <span class="rating-date"><?= h(formatRelativeTime((string)($rating['updated_at'] ?? $rating['created_at'] ?? ''))) ?></span>
        </div>
        <?php if (!empty($rating['comment'])): ?>
            <p class="rating-comment"><?= nl2br(h((string)$rating['comment'])) ?></p>
        <?php else: ?>
            <p class="rating-comment rating-comment-empty">Bez komentara</p>
        <?php endif; ?>
        <?php if ($ad): ?>
            <p class="rating-ad-ref">U vezi oglasa: <a href="<?= h(adUrl($ad)) ?>"><?= h((string)$ad['title']) ?></a></p>
        <?php endif; ?>
    </article>
    <?php
};
?>

<div class="main-wrap">
    <main class="content reviews-page">
        <div class="breadcrumb">
            <a href="/">Početna</a> ›
            <a href="<?= h($shopLink) ?>"><?= h($shopName) ?></a> ›
            Ocene
        </div>

        <header class="reviews-hero form-card">
            <div class="reviews-hero-top">
                <div>
                    <p class="reviews-kicker">Ocene oglašivača</p>
                    <h1><?= h($shopName) ?></h1>
                    <div class="shop-rating reviews-hero-summary"><?= renderReputation($summary) ?></div>
                </div>
                <div class="reviews-hero-actions">
                    <a class="btn-sm" href="<?= h($shopLink) ?>">← Nazad na izlog</a>
                    <?php if (!$isOwnShop && !empty(siteSettings()['enable_messages'])): ?>
                        <a class="btn-sm btn-sm-primary" href="/poruke.php?with=<?= (int)$sellerId ?>">Pošalji poruku</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="reviews-stat-row">
                <a class="reviews-stat <?= $filter === '' ? 'is-active' : '' ?>" href="<?= h(shopReviewsUrl($seller)) ?>">
                    <span class="reviews-stat-n"><?= count($ratings) ?></span>
                    <span class="reviews-stat-l">Sve</span>
                </a>
                <a class="reviews-stat reviews-stat-pos <?= $filter === 'positive' ? 'is-active' : '' ?>" href="<?= h(shopReviewsUrl($seller, 'positive')) ?>">
                    <span class="reviews-stat-n">👍 <?= count($positiveRatings) ?></span>
                    <span class="reviews-stat-l">Pozitivne</span>
                </a>
                <a class="reviews-stat reviews-stat-neg <?= $filter === 'negative' ? 'is-active' : '' ?>" href="<?= h(shopReviewsUrl($seller, 'negative')) ?>">
                    <span class="reviews-stat-n">👎 <?= count($negativeRatings) ?></span>
                    <span class="reviews-stat-l">Negativne</span>
                </a>
            </div>
        </header>

        <?php if ($filter === ''): ?>
            <div class="reviews-split">
                <section class="form-card reviews-col reviews-col-pos">
                    <h2>👍 Pozitivne ocene <span class="reviews-count">(<?= count($positiveRatings) ?>)</span></h2>
                    <?php if ($positiveRatings === []): ?>
                        <p class="form-hint">Još nema pozitivnih ocena.</p>
                    <?php else: ?>
                        <div class="ratings-list">
                            <?php foreach ($positiveRatings as $rating): ?>
                                <?php $renderRatingItem($rating); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <section class="form-card reviews-col reviews-col-neg">
                    <h2>👎 Negativne ocene <span class="reviews-count">(<?= count($negativeRatings) ?>)</span></h2>
                    <?php if ($negativeRatings === []): ?>
                        <p class="form-hint">Nema negativnih ocena.</p>
                    <?php else: ?>
                        <div class="ratings-list">
                            <?php foreach ($negativeRatings as $rating): ?>
                                <?php $renderRatingItem($rating); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php else: ?>
            <section class="form-card">
                <h2><?= $filter === 'negative' ? '👎 Negativne ocene' : '👍 Pozitivne ocene' ?></h2>
                <?php
                $list = $filter === 'negative' ? $negativeRatings : $positiveRatings;
                if ($list === []):
                    ?>
                    <p class="form-hint" style="margin-top:12px;">Nema ocena u ovom filteru. <a href="<?= h(shopReviewsUrl($seller)) ?>">Prikaži sve</a></p>
                <?php else: ?>
                    <div class="ratings-list">
                        <?php foreach ($list as $rating): ?>
                            <?php $renderRatingItem($rating); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="form-card" style="margin-top:12px;" id="ostavi-ocenu">
            <h2>Ostavi ocenu</h2>
            <div class="rating-rules">
                <strong>Ocena je dostupna ako:</strong>
                <ul>
                    <li>nalog ima barem 1 dan,</li>
                    <li>imate obostranu konverzaciju porukama vezanu za oglas,</li>
                    <li>niste već ocenili istu konverzaciju,</li>
                    <li>niste ocenili ovog korisnika u poslednja 3 dana.</li>
                </ul>
            </div>

            <?php if ($isOwnShop): ?>
                <p class="form-hint">Ovo je tvoj izlog — ne možeš oceniti sebe.</p>
            <?php elseif (!isLoggedIn()): ?>
                <p class="form-hint"><a href="/prijava">Prijavi se</a> da bi ocenio/la ovog oglašivača.</p>
            <?php elseif ($canRate): ?>
                <form method="POST" class="rating-form" action="<?= h($reviewsLink) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="rate">
                    <?php if (count($eligibility['eligible']) === 1): ?>
                        <input type="hidden" name="conversation_key" value="<?= h((string)$eligibility['eligible'][0]['key']) ?>">
                        <input type="hidden" name="ad_id" value="<?= (int)$eligibility['eligible'][0]['ad_id'] ?>">
                        <p class="form-hint">Ocena se vezuje za: <strong><?= h((string)$eligibility['eligible'][0]['title']) ?></strong></p>
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
                        <label>Komentar (nije obavezno)</label>
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
                    <p class="form-hint" style="margin-top:8px;">Pošalji poruku preko oglasa — kada razmenite barem par poruka, ocena će biti dostupna.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
