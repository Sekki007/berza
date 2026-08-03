<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$cityParam = trim((string)($_GET['city'] ?? ''));
$slugParam = trim((string)($_GET['slug'] ?? ''));
$activePage = 'servisi';
$showSearch = true;

// --- Detalj firme ---
if ($cityParam !== '' && $slugParam !== '') {
    $user = findDirectoryService($cityParam, $slugParam);
    if (!$user) {
        http_response_code(404);
        $pageTitle = 'Servis nije pronađen — KupiTelefon';
        require __DIR__ . '/partials/layout-start.php';
        echo '<div class="main-wrap"><main class="content"><div class="form-card"><h1>Servis nije pronađen</h1><p style="margin-top:10px;color:var(--text-muted);">Proveri link ili pogledaj <a href="/servisi">sve servise</a>.</p></div></main></div>';
        require __DIR__ . '/partials/layout-end.php';
        exit;
    }

    $cityName = trim((string)($user['location'] ?? ''));
    $canonicalPath = directoryServiceUrl($user, $cityName);
    $requestPath = '/servisi/' . rawurlencode(citySlug($cityParam)) . '/' . rawurlencode(normalizeShopSlug($slugParam));
    if ($canonicalPath !== $requestPath && citySlug($cityName) !== '' && userShopSlug($user) !== '') {
        header('Location: ' . $canonicalPath, true, 301);
        exit;
    }

    $shopName = directoryServiceName($user);
    $seo = seoDirectoryServiceMeta($user, $cityName);
    $pageTitle = $seo['title'];
    $pageDescription = $seo['description'];
    $canonicalUrl = absoluteUrl($canonicalPath);
    $jsonLd = seoDirectoryServiceJsonLd($user, $cityName);
    $logoUrl = userShopLogoUrl($user);
    if ($logoUrl !== '') {
        $pageImage = absoluteUrl($logoUrl);
    }
    $initials = mb_strtoupper(mb_substr($shopName, 0, 1));
    $kind = businessKindLabel(userBusinessKind($user));
    $phone = trim((string)($user['phone'] ?? ''));
    $bio = trim((string)($user['shop_bio'] ?? ''));
    $address = trim((string)($user['shop_page_address'] ?? ''));
    $shopLink = shopUrlForUser($user);
    $storefrontActive = storefrontIsActive($user);
    $storefrontLink = $storefrontActive ? storefrontUrlForUser($user) : '';
    $summary = getSellerRatingSummary((int)$user['id']);
    $ads = array_slice(getPublicAdsByUserId((int)$user['id'], true), 0, 8);

    require __DIR__ . '/partials/layout-start.php';
    ?>
    <div class="main-wrap">
        <main class="content dir-page">
            <div class="breadcrumb">
                <a href="/index.php">Početna</a> ›
                <a href="/servisi">Servisi</a> ›
                <a href="<?= h(directoryCityUrl($cityName)) ?>"><?= h($cityName) ?></a> ›
                <?= h($shopName) ?>
            </div>

            <article class="form-card dir-service-hero">
                <div class="dir-service-top">
                    <?= renderShopAvatarHtml($user, $initials, 'shop-avatar dir-service-avatar') ?>
                    <div class="dir-service-info">
                        <h1 class="dir-service-title"><?= h($shopName) ?> <?= renderSellerBadges($user) ?></h1>
                        <p class="dir-service-meta"><?= h($kind) ?> · <?= h($cityName) ?></p>
                        <div class="shop-rating"><?= renderReputation($summary, $shopLink) ?></div>
                        <?php if ($bio !== ''): ?>
                            <p class="shop-bio"><?= nl2br(h($bio)) ?></p>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                            <p class="dir-service-address"><?= h($address) ?></p>
                        <?php endif; ?>
                        <?php if ($phone !== ''): ?>
                            <p class="shop-phone"><a href="tel:<?= h(preg_replace('/\s+/', '', $phone) ?? $phone) ?>"><?= h($phone) ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shop-actions dir-service-actions">
                    <a class="btn-call" href="<?= h($shopLink) ?>">Otvori izlog</a>
                    <?php if ($storefrontLink !== ''): ?>
                        <a class="btn-message" href="<?= h($storefrontLink) ?>">Mini sajt</a>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <a class="btn-message" href="tel:<?= h(preg_replace('/\s+/', '', $phone) ?? $phone) ?>">Pozovi</a>
                    <?php endif; ?>
                    <?php if (isLoggedIn() && (int)currentUser()['id'] !== (int)$user['id']): ?>
                        <a class="btn-message" href="/poruke.php?with=<?= (int)$user['id'] ?>">Pošalji poruku</a>
                    <?php endif; ?>
                </div>
            </article>

            <?php if ($ads !== []): ?>
                <section class="dir-section">
                    <h2 class="dir-section-title">Oglasi firme</h2>
                    <div class="listings">
                        <?php
                        $shopCatalogMode = false;
                        foreach ($ads as $ad) {
                            require __DIR__ . '/partials/ad-card.php';
                        }
                        ?>
                    </div>
                    <p class="dir-more"><a href="<?= h($shopLink) ?>">Svi oglasi na izlogu →</a></p>
                </section>
            <?php endif; ?>

            <section class="dir-section form-card">
                <h2 class="dir-section-title">Još servisa u <?= h($cityName) ?></h2>
                <?php
                $siblings = array_values(array_filter(
                    listDirectoryServices($cityName),
                    static fn(array $u): bool => (int)($u['id'] ?? 0) !== (int)$user['id']
                ));
                $siblings = array_slice($siblings, 0, 8);
                ?>
                <?php if ($siblings === []): ?>
                    <p class="dir-empty">Trenutno nema drugih verifikovanih servisa u ovom gradu.</p>
                <?php else: ?>
                    <div class="dir-card-grid">
                        <?php foreach ($siblings as $sib): ?>
                            <?php
                            $sibName = directoryServiceName($sib);
                            $sibInit = mb_strtoupper(mb_substr($sibName, 0, 1));
                            ?>
                            <a class="dir-card" href="<?= h(directoryServiceUrl($sib, $cityName)) ?>">
                                <?= renderShopAvatarHtml($sib, $sibInit, 'dir-card-avatar') ?>
                                <div>
                                    <strong><?= h($sibName) ?></strong>
                                    <span><?= h(businessKindLabel(userBusinessKind($sib))) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

// --- Grad ---
if ($cityParam !== '') {
    $cityName = findCityBySlug($cityParam);
    if ($cityName === null) {
        // Dozvoli grad iz podataka firmi i ako nije u settings listi
        foreach (listDirectoryServices(null) as $u) {
            $loc = trim((string)($u['location'] ?? ''));
            if ($loc !== '' && citySlug($loc) === citySlug($cityParam)) {
                $cityName = $loc;
                break;
            }
        }
    }
    if ($cityName === null) {
        http_response_code(404);
        $pageTitle = 'Grad nije pronađen — KupiTelefon';
        require __DIR__ . '/partials/layout-start.php';
        echo '<div class="main-wrap"><main class="content"><div class="form-card"><h1>Grad nije pronađen</h1><p style="margin-top:10px;color:var(--text-muted);"><a href="/servisi">Nazad na servise</a></p></div></main></div>';
        require __DIR__ . '/partials/layout-end.php';
        exit;
    }

    if (citySlug($cityParam) !== citySlug($cityName)) {
        header('Location: ' . directoryCityUrl($cityName), true, 301);
        exit;
    }

    $services = listDirectoryServices($cityName);
    $seo = seoDirectoryCityMeta($cityName, count($services));
    $pageTitle = $seo['title'];
    $pageDescription = $seo['description'];
    $canonicalUrl = absoluteUrl(directoryCityUrl($cityName));
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Mobilni servisi u ' . $cityName,
        'url' => $canonicalUrl,
        'description' => $pageDescription,
    ];

    require __DIR__ . '/partials/layout-start.php';
    ?>
    <div class="main-wrap">
        <main class="content dir-page">
            <div class="breadcrumb">
                <a href="/index.php">Početna</a> ›
                <a href="/servisi">Servisi</a> ›
                <?= h($cityName) ?>
            </div>

            <header class="dir-hub-head form-card">
                <h1>Mobilni servisi u <?= h($cityName) ?></h1>
                <p>Verifikovane firme koje nude servis mobilnih telefona<?= count($services) > 0 ? ' — ' . count($services) . ' ' . (count($services) === 1 ? 'firma' : 'firmi') : '' ?>.</p>
            </header>

            <?php if ($services === []): ?>
                <div class="form-card">
                    <p class="dir-empty">Još nema verifikovanih servisa za <?= h($cityName) ?>. Pogledaj <a href="/servisi">druge gradove</a> ili <a href="/index.php?type=servis&amp;location=<?= h(rawurlencode($cityName)) ?>">oglase usluga</a>.</p>
                </div>
            <?php else: ?>
                <div class="dir-card-grid dir-card-grid-lg">
                    <?php foreach ($services as $svc): ?>
                        <?php
                        $svcName = directoryServiceName($svc);
                        $svcInit = mb_strtoupper(mb_substr($svcName, 0, 1));
                        $svcPhone = trim((string)($svc['phone'] ?? ''));
                        $svcBio = trim((string)($svc['shop_bio'] ?? ''));
                        ?>
                        <a class="dir-card dir-card-lg" href="<?= h(directoryServiceUrl($svc, $cityName)) ?>">
                            <?= renderShopAvatarHtml($svc, $svcInit, 'dir-card-avatar') ?>
                            <div class="dir-card-body">
                                <strong><?= h($svcName) ?></strong>
                                <span class="dir-card-kind"><?= h(businessKindLabel(userBusinessKind($svc))) ?></span>
                                <?php if ($svcBio !== ''): ?>
                                    <span class="dir-card-bio"><?= h(seoTruncate($svcBio, 110)) ?></span>
                                <?php endif; ?>
                                <?php if ($svcPhone !== ''): ?>
                                    <span class="dir-card-phone"><?= h($svcPhone) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <?php
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

// --- Hub ---
$cityStats = directoryCityStats();
$allServices = listDirectoryServices(null);
$seo = seoDirectoryHubMeta();
$pageTitle = $seo['title'];
$pageDescription = $seo['description'];
$canonicalUrl = absoluteUrl('/servisi');
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Mobilni servisi u Srbiji',
    'url' => $canonicalUrl,
    'description' => $pageDescription,
];

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content dir-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Servisi</div>

        <header class="dir-hub-head form-card">
            <h1>Mobilni servisi u Srbiji</h1>
            <p>Direktorijum verifikovanih firmi za popravku i servis telefona. Izaberi grad pa otvori profil servisa.</p>
            <p class="dir-hub-count"><?= count($allServices) ?> <?= count($allServices) === 1 ? 'servis' : 'servisa' ?> · <?= count($cityStats) ?> <?= count($cityStats) === 1 ? 'grad' : 'gradova' ?></p>
        </header>

        <?php if ($cityStats === []): ?>
            <div class="form-card">
                <p class="dir-empty">Još nema javnih verifikovanih servisa u direktorijumu. Uskoro će biti dostupni po gradovima.</p>
                <p style="margin-top:10px;"><a href="/index.php?type=servis">Pogledaj oglase usluga →</a></p>
            </div>
        <?php else: ?>
            <div class="dir-city-grid">
                <?php foreach ($cityStats as $row): ?>
                    <a class="dir-city-card" href="<?= h($row['url']) ?>">
                        <strong><?= h($row['city']) ?></strong>
                        <span><?= (int)$row['count'] ?> <?= (int)$row['count'] === 1 ? 'servis' : 'servisa' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <section class="dir-section">
                <h2 class="dir-section-title">Svi servisi</h2>
                <div class="dir-card-grid dir-card-grid-lg">
                    <?php foreach ($allServices as $svc): ?>
                        <?php
                        $svcName = directoryServiceName($svc);
                        $svcInit = mb_strtoupper(mb_substr($svcName, 0, 1));
                        $svcCity = trim((string)($svc['location'] ?? ''));
                        ?>
                        <a class="dir-card dir-card-lg" href="<?= h(directoryServiceUrl($svc, $svcCity)) ?>">
                            <?= renderShopAvatarHtml($svc, $svcInit, 'dir-card-avatar') ?>
                            <div class="dir-card-body">
                                <strong><?= h($svcName) ?></strong>
                                <span class="dir-card-kind"><?= h(businessKindLabel(userBusinessKind($svc))) ?> · <?= h($svcCity) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php
require __DIR__ . '/partials/layout-end.php';
