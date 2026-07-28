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
$firstAdId = (int)($ads[0]['id'] ?? 0);
$messageThreadUrl = '/poruke.php?with=' . (int)$seller['id'] . '&ad_id=' . ($firstAdId > 0 ? $firstAdId : 0);
$loginNext = '/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? storefrontUrlForUser($seller));
$canQuickMessage = isLoggedIn() && !$isOwner && $firstAdId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'shop_contact_send') {
    csrfVerify();
    if (!isLoggedIn()) {
        setFlash('danger', 'Prijavi se da pošalješ poruku.');
        header('Location: /login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? storefrontUrlForUser($seller)));
        exit;
    }
    $viewer = currentUser();
    $fromId = (int)($viewer['id'] ?? 0);
    $toId = (int)($seller['id'] ?? 0);
    $body = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1200);
    $redirectTo = storefrontUrlForUser($seller);
    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId || $firstAdId <= 0 || $body === '') {
        setFlash('danger', 'Poruka nije poslata. Proveri da li si uneo tekst.');
    } else {
        $saved = saveMessage([
            'from_user_id' => $fromId,
            'from_name' => (string)($viewer['name'] ?? $viewer['username'] ?? 'Kupac'),
            'from_phone' => (string)($viewer['phone'] ?? ''),
            'to_user_id' => $toId,
            'ad_id' => $firstAdId,
            'body' => '[Mini sajt upit] ' . $body,
        ]);
        setFlash($saved ? 'success' : 'danger', $saved ? 'Poruka je poslata.' : 'Poruka nije poslata.');
        if ($saved) {
            $redirectTo = $messageThreadUrl;
        }
    }
    header('Location: ' . $redirectTo);
    exit;
}

$openStatus = storefrontOpenStatus($seller);
$hours = storefrontWeeklyHoursForUser($seller);
$dayLabels = storefrontWeeklyDayLabels();
$services = array_values(array_filter((array)($seller['shop_page_services'] ?? []), static fn($row) => is_array($row) && trim((string)($row['name'] ?? '')) !== ''));
$faq = array_values(array_filter((array)($seller['shop_page_faq'] ?? []), static fn($row) => is_array($row) && trim((string)($row['name'] ?? '')) !== ''));
$gallery = array_values(array_filter((array)($seller['shop_page_gallery'] ?? []), static fn($img) => is_string($img) && $img !== ''));
$mapQueryRaw = trim((string)($seller['shop_page_address'] ?? '')) . ', ' . trim((string)($seller['location'] ?? ''));
$mapQuery = trim(trim($mapQueryRaw), ',');
$mapEmbed = $mapQuery !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($mapQuery) . '&output=embed' : '';
$ratingSummary = function_exists('getSellerRatingSummary') ? getSellerRatingSummary((int)$seller['id']) : ['avg' => 0, 'count' => 0];
$ratings = function_exists('getSellerRatings') ? getSellerRatings((int)$seller['id']) : [];
usort($ratings, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$ratings = array_slice($ratings, 0, 5);

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
            <div class="storefront-hero-meta">
                <span class="storefront-open-badge <?= !empty($openStatus['open']) ? 'open' : 'closed' ?>"><?= h((string)$openStatus['label']) ?></span>
                <p class="form-hint storefront-hero-owner">Objavio: <a href="<?= h($shopLink) ?>"><?= h($shopName) ?></a> <?= renderSellerBadges($seller) ?></p>
            </div>
            <div class="storefront-cta">
                <?php if (!empty($seller['phone'])): ?>
                    <a class="btn-call" href="tel:<?= h((string)$seller['phone']) ?>">Pozovi odmah</a>
                <?php endif; ?>
                <?php if ($canQuickMessage): ?>
                    <button type="button" class="btn-outline js-open-shop-contact">Pošalji poruku</button>
                <?php elseif (!isLoggedIn()): ?>
                    <a class="btn-outline" href="<?= h($loginNext) ?>">Pošalji poruku</a>
                <?php elseif (!$isOwner && $firstAdId > 0): ?>
                    <a class="btn-outline" href="<?= h($messageThreadUrl) ?>">Pošalji poruku</a>
                <?php endif; ?>
            </div>
            <?php if ($isOwner): ?>
                <p class="form-hint storefront-hero-edit">Uredi podatke na <a href="/nalog.php?tab=mini_sajt">Moj nalog → Mini sajt</a>.</p>
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
                    <?php if (!empty($seller['shop_page_website'])): ?>
                        <div class="storefront-row"><span>Website:</span><strong><a href="<?= h((string)$seller['shop_page_website']) ?>" target="_blank" rel="noopener">Otvori sajt</a></strong></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($seller['shop_page_instagram']) || !empty($seller['shop_page_facebook']) || !empty($seller['shop_page_tiktok'])): ?>
                    <div class="storefront-socials">
                        <?php if (!empty($seller['shop_page_instagram'])): ?><a class="btn-outline" href="<?= h((string)$seller['shop_page_instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
                        <?php if (!empty($seller['shop_page_facebook'])): ?><a class="btn-outline" href="<?= h((string)$seller['shop_page_facebook']) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
                        <?php if (!empty($seller['shop_page_tiktok'])): ?><a class="btn-outline" href="<?= h((string)$seller['shop_page_tiktok']) ?>" target="_blank" rel="noopener">TikTok</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="storefront-layout">
            <article class="form-card storefront-section">
                <h2>Radno vreme</h2>
                <?php if (!empty($seller['shop_page_work_hours'])): ?>
                    <div class="storefront-pre" style="margin-bottom:10px;"><?= nl2br(h((string)$seller['shop_page_work_hours'])) ?></div>
                <?php endif; ?>
                <table class="storefront-hours-table">
                    <?php foreach ($dayLabels as $dayKey => $dayLabel): ?>
                        <?php $day = $hours[$dayKey] ?? ['closed' => true, 'open' => '00:00', 'close' => '00:00']; ?>
                        <tr>
                            <td><?= h($dayLabel) ?></td>
                            <td><?= !empty($day['closed']) ? 'Neradno' : (h((string)$day['open']) . ' - ' . h((string)$day['close'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </article>

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

        <?php if ($services): ?>
            <section class="form-card storefront-section">
                <h2>Istaknute usluge</h2>
                <div class="storefront-services">
                    <?php foreach ($services as $srv): ?>
                        <div class="storefront-service">
                            <strong><?= h((string)$srv['name']) ?></strong>
                            <?php if (trim((string)($srv['value'] ?? '')) !== ''): ?>
                                <span class="storefront-service-price"><?= h((string)$srv['value']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($gallery): ?>
            <section class="form-card storefront-section">
                <h2>Portfolio radova</h2>
                <div class="storefront-gallery">
                    <?php foreach ($gallery as $img): ?>
                        <a href="<?= h($img) ?>" target="_blank" rel="noopener">
                            <img src="<?= h($img) ?>" alt="<?= h($shopName) ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($faq): ?>
            <section class="form-card storefront-section">
                <h2>Česta pitanja</h2>
                <div class="storefront-faq">
                    <?php foreach ($faq as $item): ?>
                        <details>
                            <summary><?= h((string)$item['name']) ?></summary>
                            <?php if (trim((string)($item['value'] ?? '')) !== ''): ?>
                                <p><?= h((string)$item['value']) ?></p>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="storefront-layout">
            <article class="form-card storefront-section">
                <h2>Poverenje i ocene</h2>
                <div class="storefront-rows">
                    <div class="storefront-row"><span>PIB verifikacija:</span><strong><?= !empty($seller['pib_verified']) ? 'Verifikovano' : 'Nije verifikovano' ?></strong></div>
                    <div class="storefront-row"><span>Broj oglasa:</span><strong><?= count($ads) ?></strong></div>
                    <div class="storefront-row"><span>Ocene kupaca:</span><strong>👍 <?= (int)($ratingSummary['positive'] ?? 0) ?> / 👎 <?= (int)($ratingSummary['negative'] ?? 0) ?></strong></div>
                </div>
            </article>
            <?php if ($mapEmbed !== ''): ?>
                <article class="form-card storefront-section storefront-map">
                    <h2>Lokacija</h2>
                    <iframe src="<?= h($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </article>
            <?php endif; ?>
        </section>

        <?php if ($ratings): ?>
            <section class="form-card storefront-section">
                <h2>Mini recenzije</h2>
                <div class="storefront-faq">
                    <?php foreach ($ratings as $rv): ?>
                        <?php $author = findUserById((int)($rv['from_user_id'] ?? 0)); ?>
                        <div class="storefront-service">
                            <div>
                                <strong><?= h((string)($author['username'] ?? 'Kupac')) ?></strong>
                                <?php if (!empty($rv['comment'])): ?>
                                    <div style="color:#475569;margin-top:6px;"><?= h((string)$rv['comment']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="storefront-service-price"><?= (($rv['vote'] ?? '') === 'negative') ? '👎' : '👍' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($seller['shop_page_description'])): ?>
            <section class="form-card storefront-section">
                <h2>Opis usluga</h2>
                <div class="storefront-pre"><?= nl2br(h((string)$seller['shop_page_description'])) ?></div>
            </section>
        <?php endif; ?>

        <section class="form-card storefront-section">
            <h2>Kontakt forma</h2>
            <?php if (!isLoggedIn()): ?>
                <p class="form-hint">Za slanje poruke je potrebna prijava. <a href="/login.php?next=<?= h(rawurlencode($_SERVER['REQUEST_URI'] ?? storefrontUrlForUser($seller))) ?>">Prijavi se</a>.</p>
            <?php elseif ($firstAdId <= 0): ?>
                <p class="form-hint">Prodavac trenutno nema aktivan oglas za povezivanje poruke.</p>
            <?php else: ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="shop_contact_send">
                    <div class="form-group">
                        <label>Vaša poruka</label>
                        <textarea name="message" rows="4" required placeholder="Npr. Da li je moguća ugradnja u subotu?"></textarea>
                    </div>
                    <button type="submit" class="btn-call" style="width:auto;">Pošalji upit</button>
                </form>
            <?php endif; ?>
        </section>

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
        <?php if (!empty($seller['phone'])): ?>
            <div class="storefront-sticky-contact">
                <a class="call" href="tel:<?= h((string)$seller['phone']) ?>">Pozovi</a>
                <?php if ($canQuickMessage): ?>
                    <button type="button" class="msg js-open-shop-contact">Poruka</button>
                <?php elseif (!isLoggedIn()): ?>
                    <a class="msg" href="<?= h($loginNext) ?>">Poruka</a>
                <?php elseif (!$isOwner && $firstAdId > 0): ?>
                    <a class="msg" href="<?= h($messageThreadUrl) ?>">Poruka</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($canQuickMessage): ?>
    <div class="storefront-modal" id="shopContactModal" hidden>
        <div class="storefront-modal-backdrop js-close-shop-contact"></div>
        <div class="storefront-modal-card" role="dialog" aria-modal="true" aria-labelledby="shopContactTitle">
            <button type="button" class="storefront-modal-close js-close-shop-contact" aria-label="Zatvori">×</button>
            <h3 id="shopContactTitle">Pošalji poruku prodavcu</h3>
            <p class="form-hint">Poruka ide direktno korisniku <strong><?= h($shopName) ?></strong>.</p>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="shop_contact_send">
                <div class="form-group">
                    <label for="shopContactMessage">Vaša poruka</label>
                    <textarea id="shopContactMessage" name="message" rows="5" required placeholder="Npr. Da li je moguća ugradnja u subotu?"></textarea>
                </div>
                <div class="storefront-modal-actions">
                    <button type="button" class="btn-outline js-close-shop-contact">Otkaži</button>
                    <button type="submit" class="btn-call">Pošalji</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            var modal = document.getElementById('shopContactModal');
            if (!modal) return;
            var openers = document.querySelectorAll('.js-open-shop-contact');
            var closers = modal.querySelectorAll('.js-close-shop-contact');
            var messageField = document.getElementById('shopContactMessage');
            var lastFocus = null;

            function openModal() {
                lastFocus = document.activeElement;
                modal.hidden = false;
                document.body.classList.add('modal-open');
                setTimeout(function () {
                    if (messageField) messageField.focus();
                }, 0);
            }
            function closeModal() {
                modal.hidden = true;
                document.body.classList.remove('modal-open');
                if (lastFocus && typeof lastFocus.focus === 'function') {
                    lastFocus.focus();
                }
            }

            openers.forEach(function (btn) {
                btn.addEventListener('click', openModal);
            });
            closers.forEach(function (btn) {
                btn.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.hidden) closeModal();
            });
        })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
