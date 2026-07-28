<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$username = trim((string)($_GET['u'] ?? ''));
$seller = $username !== '' ? findUserByUsername($username) : null;

if (!$seller) {
    http_response_code(404);
    echo 'Stranica nije pronađena.';
    exit;
}

$isOwner = isLoggedIn() && (int)(currentUser()['id'] ?? 0) === (int)($seller['id'] ?? 0);
$isActive = storefrontIsActive($seller);
if (!$isActive && !$isOwner && !isAdmin()) {
    http_response_code(404);
    echo 'Stranica nije pronađena.';
    exit;
}

$shopName = getSellerShopName($seller);
$shopLink = shopUrl((string)$seller['username']);
$ads = getPublicAdsByUserId((int)$seller['id'], true);
$pageTitle = $shopName . ' — Usluge';
$activePage = 'oglasi';
$showSearch = true;
$pageDescription = trim((string)($seller['shop_page_tagline'] ?? '')) !== ''
    ? trim((string)$seller['shop_page_tagline'])
    : ('Mini stranica radnje ' . $shopName);

$paymentMap = storefrontPaymentMethodsOptions();
$selectedPayments = array_values(array_filter(
    (array)($seller['shop_page_payment_methods'] ?? []),
    static fn($k) => is_string($k) && isset($paymentMap[$k])
));

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <a href="<?= h($shopLink) ?>">Izlog</a> › Usluge</div>

        <section class="form-card storefront-hero">
            <h1><?= h((string)($seller['shop_page_title'] ?? $shopName)) ?></h1>
            <?php if (!empty($seller['shop_page_tagline'])): ?>
                <p class="storefront-tagline"><?= h((string)$seller['shop_page_tagline']) ?></p>
            <?php endif; ?>
            <p class="form-hint">Objavio: <a href="<?= h($shopLink) ?>"><?= h($shopName) ?></a> <?= renderSellerBadges($seller) ?></p>
            <?php if ($isOwner): ?>
                <p class="form-hint">Uredi podatke na <a href="/nalog.php?tab=mini_sajt">Moj nalog → Mini sajt</a>.</p>
            <?php endif; ?>
        </section>

        <section class="form-card storefront-grid">
            <div class="storefront-box">
                <h2>Podaci o trgovcu</h2>
                <ul class="storefront-list">
                    <li><strong>Naziv:</strong> <?= h($shopName) ?></li>
                    <li><strong>PIB:</strong> <?= h((string)($seller['pib'] ?? '')) ?></li>
                    <?php if (!empty($seller['shop_page_address'])): ?>
                        <li><strong>Adresa:</strong> <?= h((string)$seller['shop_page_address']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($seller['location'])): ?>
                        <li><strong>Grad:</strong> <?= h((string)$seller['location']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="storefront-box">
                <h2>Kontakt</h2>
                <ul class="storefront-list">
                    <?php if (!empty($seller['shop_page_contact_email'])): ?>
                        <li><strong>Email:</strong> <a href="mailto:<?= h((string)$seller['shop_page_contact_email']) ?>"><?= h((string)$seller['shop_page_contact_email']) ?></a></li>
                    <?php elseif (!empty($seller['email'])): ?>
                        <li><strong>Email:</strong> <a href="mailto:<?= h((string)$seller['email']) ?>"><?= h((string)$seller['email']) ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($seller['phone'])): ?>
                        <li><strong>Telefon:</strong> <a href="tel:<?= h((string)$seller['phone']) ?>"><?= h((string)$seller['phone']) ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>

        <?php if (!empty($seller['shop_page_work_hours'])): ?>
            <section class="form-card storefront-box">
                <h2>Radno vreme</h2>
                <div class="storefront-pre"><?= nl2br(h((string)$seller['shop_page_work_hours'])) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($selectedPayments): ?>
            <section class="form-card storefront-box">
                <h2>Uslovi plaćanja</h2>
                <div class="storefront-chips">
                    <?php foreach ($selectedPayments as $pm): ?>
                        <span class="storefront-chip"><?= h($paymentMap[$pm]) ?></span>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($seller['shop_page_description'])): ?>
            <section class="form-card storefront-box">
                <h2>Opis usluga</h2>
                <div class="storefront-pre"><?= nl2br(h((string)$seller['shop_page_description'])) ?></div>
            </section>
        <?php endif; ?>

        <section class="form-card">
            <div class="account-section-head">
                <h2>Svi oglasi radnje (<?= count($ads) ?>)</h2>
                <a href="<?= h($shopLink) ?>">Otvori izlog →</a>
            </div>
            <?php if (!$ads): ?>
                <p class="form-hint">Trenutno nema aktivnih oglasa.</p>
            <?php else: ?>
                <div class="ads-list compact-list">
                    <?php foreach ($ads as $ad): ?>
                        <?php require __DIR__ . '/partials/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
