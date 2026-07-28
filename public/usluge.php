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

<div class="main-wrap storefront-page">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <a href="<?= h($shopLink) ?>">Izlog</a> › Usluge</div>

        <section class="form-card storefront-hero">
            <?php if (!empty($seller['shop_page_cover'])): ?>
                <div class="storefront-hero-cover">
                    <img src="<?= h((string)$seller['shop_page_cover']) ?>" alt="<?= h($shopName) ?>">
                </div>
            <?php endif; ?>
            <div class="storefront-kicker">Usluge za kupce</div>
            <h1><?= h((string)($seller['shop_page_title'] ?? $shopName)) ?></h1>
            <?php if (!empty($seller['shop_page_tagline'])): ?>
                <p class="storefront-tagline"><?= h((string)$seller['shop_page_tagline']) ?></p>
            <?php endif; ?>
            <p class="form-hint">Objavio: <a href="<?= h($shopLink) ?>"><?= h($shopName) ?></a> <?= renderSellerBadges($seller) ?></p>
            <?php if ($isOwner): ?>
                <p class="form-hint">Uredi podatke na <a href="/nalog.php?tab=mini_sajt">Moj nalog → Mini sajt</a>.</p>
            <?php endif; ?>
        </section>

        <section class="storefront-layout">
            <article class="form-card storefront-section">
                <h2>Podaci o trgovcu</h2>
                <div class="storefront-rows">
                    <div class="storefront-row"><span>Pun naziv trgovca:</span><strong><?= h($shopName) ?></strong></div>
                    <?php if (!empty($seller['shop_page_legal_name'])): ?>
                        <div class="storefront-row"><span>Pravno lice:</span><strong><?= h((string)$seller['shop_page_legal_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($seller['shop_page_registration_no'])): ?>
                        <div class="storefront-row"><span>Matični broj:</span><strong><?= h((string)$seller['shop_page_registration_no']) ?></strong></div>
                    <?php endif; ?>
                    <div class="storefront-row"><span>PIB:</span><strong><?= h((string)($seller['pib'] ?? '')) ?></strong></div>
                    <?php if (!empty($seller['shop_page_address'])): ?>
                        <div class="storefront-row"><span>Adresa sedišta:</span><strong><?= h((string)$seller['shop_page_address']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($seller['location'])): ?>
                        <div class="storefront-row"><span>Grad:</span><strong><?= h((string)$seller['location']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="form-card storefront-section">
                <h2>Kontakt</h2>
                <div class="storefront-rows">
                    <?php if (!empty($seller['shop_page_contact_email'])): ?>
                        <div class="storefront-row"><span>E-mail:</span><strong><a href="mailto:<?= h((string)$seller['shop_page_contact_email']) ?>"><?= h((string)$seller['shop_page_contact_email']) ?></a></strong></div>
                    <?php elseif (!empty($seller['email'])): ?>
                        <div class="storefront-row"><span>E-mail:</span><strong><a href="mailto:<?= h((string)$seller['email']) ?>"><?= h((string)$seller['email']) ?></a></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($seller['phone'])): ?>
                        <div class="storefront-row"><span>Telefon:</span><strong><a href="tel:<?= h((string)$seller['phone']) ?>"><?= h((string)$seller['phone']) ?></a></strong></div>
                    <?php endif; ?>
                </div>
            </article>
        </section>

        <section class="storefront-layout">
            <?php if (!empty($seller['shop_page_work_hours'])): ?>
                <article class="form-card storefront-section">
                    <h2>Radno vreme</h2>
                    <div class="storefront-pre"><?= nl2br(h((string)$seller['shop_page_work_hours'])) ?></div>
                </article>
            <?php endif; ?>

            <?php if ($selectedPayments): ?>
                <article class="form-card storefront-section">
                    <h2>Uslovi plaćanja</h2>
                    <div class="storefront-chips">
                        <?php foreach ($selectedPayments as $pm): ?>
                            <span class="storefront-chip"><?= h($paymentMap[$pm]) ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>

        <?php if (!empty($seller['shop_page_description'])): ?>
            <section class="form-card storefront-section">
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
