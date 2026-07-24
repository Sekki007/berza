<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireLogin();

$user = currentUser();
$userId = (int)$user['id'];
$profile = findUserById($userId) ?? $user;
$site = siteSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/nalog.php');
    $action = trim((string)($_POST['action'] ?? 'profile'));

    if ($action === 'renew') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        if (!adExpiryEnabled()) {
            setFlash('danger', 'Rok trajanja oglasa nije uključen.');
        } elseif ($adId > 0 && renewAd($adId, $userId)) {
            setFlash('success', 'Oglas je produžen za ' . adMaxActiveDays() . ' dana.');
        } else {
            setFlash('danger', 'Oglas nije moguće produžiti.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'obnova') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        $result = $adId > 0 ? renewAdPaid($adId, $userId) : null;
        if (!$result) {
            setFlash('danger', 'Obnova nije uspela.');
        } elseif (empty($result['ok']) && ($result['error'] ?? '') === 'credits') {
            setFlash('danger', 'Nemaš dovoljno kredita za obnovu (' . formatCredits(adRenewalCredits()) . ').');
            header('Location: /nalog.php?tab=krediti');
            exit;
        } elseif (!empty($result['ok'])) {
            $cost = (int)($result['cost'] ?? 0);
            setFlash('success', 'Oglas je obnovljen' . ($cost > 0 ? ' (−' . formatCredits($cost) . ')' : '') . '. Ponovo je pri vrhu liste.');
        } else {
            setFlash('danger', 'Obnova nije uspela.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'highlight') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        $result = $adId > 0 ? activateAdHighlight($adId, $userId, 7) : null;
        if (!$result) {
            setFlash('danger', 'Isticanje nije aktivirano.');
        } elseif (empty($result['ok']) && ($result['error'] ?? '') === 'credits') {
            setFlash('danger', 'Nemaš dovoljno kredita (' . formatCredits(highlightCredits()) . ').');
            header('Location: /nalog.php?tab=krediti');
            exit;
        } elseif (!empty($result['ok'])) {
            setFlash('success', 'Oglas je istaknut plavom bojom (−' . formatCredits((int)$result['cost']) . ').');
        } else {
            setFlash('danger', 'Isticanje nije aktivirano.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'sold') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        $result = $adId > 0 ? toggleAdSold($adId, $userId) : null;
        if ($result === null) {
            setFlash('danger', 'Oglas nije moguće ažurirati.');
        } else {
            setFlash('success', $result ? 'Oglas je označen kao prodato.' : 'Oglas je vraćen u prodaju.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'delete_ad') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        $ad = $adId > 0 ? getAdById($adId) : null;
        if (!$ad || !userOwnsAd($ad, $userId)) {
            setFlash('danger', 'Oglas nije pronađen ili nemaš dozvolu.');
        } elseif (deleteAdById($adId)) {
            setFlash('success', 'Oglas je obrisan.');
        } else {
            setFlash('danger', 'Brisanje nije uspelo.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'buy_top') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        $packageId = trim((string)($_POST['package_id'] ?? ''));
        $pkg = findTopPackage($packageId);
        if (creditsEnabled() && $pkg && getUserCredits($userId) < (int)round((float)$pkg['price'])) {
            setFlash('danger', 'Nemaš dovoljno kredita. Dopuni saldo pa pokušaj ponovo.');
            header('Location: /nalog.php?tab=krediti');
            exit;
        }
        $order = createTopOrder($userId, $adId, $packageId);
        if (!$order) {
            setFlash('danger', 'TOP paket nije moguće naručiti.');
        } elseif (($order['status'] ?? '') === 'paid') {
            $msg = 'TOP isticanje je aktivirano na ' . (int)$order['days'] . ' dana.';
            if (($order['paid_with'] ?? '') === 'credits') {
                $msg .= ' Skinuto ' . formatCredits((int)$order['price']) . '.';
            }
            setFlash('success', $msg);
        } else {
            setFlash('success', 'Porudžbina TOP paketa je poslata. Čeka potvrdu uplate.');
        }
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }

    if ($action === 'request_credits') {
        $amount = (int)($_POST['amount'] ?? 0);
        $deposit = requestCreditDeposit($userId, $amount);
        if (!$deposit) {
            setFlash('danger', 'Zahtev za uplatu nije poslat.');
        } else {
            setFlash('success', 'Zahtev poslat. Uplati ' . formatCredits((int)$deposit['amount']) . ' sa svrhom KR-' . (int)$deposit['id'] . '.');
        }
        header('Location: /nalog.php?tab=krediti');
        exit;
    }

    if ($action === 'mark_notifications') {
        markAllNotificationsRead($userId);
        setFlash('success', 'Obaveštenja su označena kao pročitana.');
        header('Location: /nalog.php?tab=obavestenja');
        exit;
    }

    if ($action === 'verify_email') {
        $profileNow = findUserById($userId) ?? $profile;
        if (isEmailVerified($profileNow)) {
            setFlash('success', 'Email je već potvrđen.');
        } elseif (trim((string)($profileNow['email'] ?? '')) === '') {
            setFlash('danger', 'Prvo unesi email pa sačuvaj profil.');
        } elseif (sendEmailVerification($userId)) {
            setFlash('success', 'Poslat je email sa linkom za potvrdu.');
        } else {
            setFlash('danger', 'Email nije poslat. Proveri adresu i admin podešavanja.');
        }
        header('Location: /nalog.php?tab=profil');
        exit;
    }

    if ($action === 'verify_phone') {
        $profileNow = findUserById($userId) ?? $profile;
        if (isPhoneVerified($profileNow)) {
            setFlash('success', 'Telefon je već potvrđen.');
        } else {
            $_SESSION['pending_phone_verify_user_id'] = $userId;
            $result = sendUserOtp($userId, 'phone_verify');
            if (!empty($result['ok'])) {
                setFlash('success', 'SMS kod je poslat.');
                header('Location: /verify-phone.php');
                exit;
            }
            setFlash('danger', (string)($result['error'] ?? 'SMS nije poslat.'));
        }
        header('Location: /nalog.php?tab=profil');
        exit;
    }

    if ($action === 'request_business') {
        $result = requestBusinessVerification($userId);
        if (!empty($result['ok'])) {
            setFlash('success', 'Zahtev za bedž firme je poslat. Čeka admin potvrdu.');
        } else {
            setFlash('danger', (string)($result['error'] ?? 'Zahtev nije poslat.'));
        }
        header('Location: /nalog.php?tab=profil');
        exit;
    }

    if ($action === 'save_search') {
        $created = createSavedSearch($userId, $_POST, trim((string)($_POST['name'] ?? '')), !empty($_POST['alert_enabled']));
        if ($created) {
            setFlash('success', 'Pretraga je sačuvana' . (!empty($created['alert_enabled']) ? ' sa alertom.' : '.'));
            header('Location: /nalog.php?tab=pretrage');
        } else {
            setFlash('danger', 'Pretraga nije sačuvana. Izaberi bar jedan filter (max 20 pretraga).');
            header('Location: /index.php?' . buildFilterQuery(savedSearchFiltersFromInput($_POST)));
        }
        exit;
    }

    if ($action === 'toggle_search_alert') {
        $sid = (int)($_POST['search_id'] ?? 0);
        $row = findSavedSearch($sid);
        if ($row && (int)($row['user_id'] ?? 0) === $userId) {
            updateSavedSearch($sid, $userId, ['alert_enabled' => empty($row['alert_enabled'])]);
            setFlash('success', 'Alert je ažuriran.');
        } else {
            setFlash('danger', 'Pretraga nije pronađena.');
        }
        header('Location: /nalog.php?tab=pretrage');
        exit;
    }

    if ($action === 'delete_search') {
        $sid = (int)($_POST['search_id'] ?? 0);
        if (deleteSavedSearch($sid, $userId)) {
            setFlash('success', 'Pretraga je obrisana.');
        } else {
            setFlash('danger', 'Brisanje nije uspelo.');
        }
        header('Location: /nalog.php?tab=pretrage');
        exit;
    }

    $phoneInput = trim((string)($_POST['phone'] ?? ''));
    if ($phoneInput !== '' && normalizePhoneRs($phoneInput) === null) {
        setFlash('danger', 'Unesi validan srpski mobilni broj (npr. 06x xxx xxxx).');
        header('Location: /nalog.php?tab=profil');
        exit;
    }

    $accountTypePost = trim((string)($_POST['account_type'] ?? 'private')) === 'business' ? 'business' : 'private';
    $shopName = $accountTypePost === 'business'
        ? trim((string)($_POST['shop_name'] ?? ''))
        : trim((string)($_POST['shop_name_private'] ?? $_POST['shop_name'] ?? ''));
    $shopBio = $accountTypePost === 'business'
        ? trim((string)($_POST['shop_bio'] ?? ''))
        : trim((string)($_POST['shop_bio_private'] ?? $_POST['shop_bio'] ?? ''));

    $ok = updateUserProfile($userId, [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'phone' => $phoneInput,
        'email' => trim((string)($_POST['email'] ?? '')),
        'shop_name' => $shopName,
        'shop_bio' => $shopBio,
        'location' => trim((string)($_POST['location'] ?? '')),
        'notify_email' => !empty($_POST['notify_email']),
        'account_type' => $accountTypePost,
        'business_kind' => trim((string)($_POST['business_kind'] ?? '')),
        'pib' => trim((string)($_POST['pib'] ?? '')),
    ]);
    if (!$ok) {
        setFlash('danger', 'Profil nije ažuriran. Proveri telefon i PIB (9 cifara).');
    } else {
        $fresh = findUserById($userId);
        if ($fresh && !isPhoneVerified($fresh) && normalizePhoneRs((string)($fresh['phone'] ?? '')) !== null) {
            setFlash('success', 'Profil je ažuriran. Potvrdi novi broj SMS kodom.');
        } else {
            setFlash('success', 'Profil je ažuriran.');
        }
    }
    header('Location: /nalog.php?tab=profil');
    exit;
}

$tab = trim((string)($_GET['tab'] ?? 'pregled'));
if (!in_array($tab, ['pregled', 'profil', 'oglasi', 'obavestenja', 'top', 'krediti', 'pretrage', 'statistika'], true)) {
    $tab = 'pregled';
}

$myAds = getAdsByUserId($userId);
$activeAds = array_values(array_filter($myAds, static fn($a) => (int)($a['is_active'] ?? 0) === 1 && empty($a['is_sold'])));
$soldAds = array_values(array_filter($myAds, static fn($a) => !empty($a['is_sold'])));
$inactiveAds = array_values(array_filter($myAds, static fn($a) => (int)($a['is_active'] ?? 0) !== 1));
$unread = !empty($site['enable_messages']) ? getUnreadMessageCount($userId) : 0;
$notifications = getNotificationsForUser($userId);
$unreadNotifs = getUnreadNotificationCount($userId);
$savedSearches = getSavedSearchesForUser($userId);
$sellerStats = sumAdStatsForUser($userId, 30);
$shopLink = shopUrl((string)$profile['username']);
$summary = getSellerRatingSummary($userId);
$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$fullShopUrl = $host . $shopLink;
$displayName = (string)(($profile['shop_name'] ?? '') ?: ($profile['full_name'] ?? $profile['username'] ?? 'Korisnik'));
$initials = mb_strtoupper(mb_substr($displayName, 0, 1) . mb_substr(trim(strrchr($displayName, ' ') ?: ''), 0, 1));
if (mb_strlen($initials) < 1) {
    $initials = '?';
}
$expiryOn = adExpiryEnabled();
$warningDays = adExpiryWarningDays();
$topOn = topPurchaseEnabled();
$topPackages = $topOn ? topPackages() : [];
$topPaymentInfo = (string)(siteSettings()['top_payment_info'] ?? '');
$creditsOn = creditsEnabled();
$userCredits = $creditsOn ? getUserCredits($userId) : 0;
$creditDeposits = $creditsOn ? getCreditDepositsForUser($userId) : [];
$creditTx = $creditsOn ? getCreditTransactionsForUser($userId, 15) : [];
$creditAmounts = $creditsOn ? creditTopupAmounts() : [];
$creditPayInfo = $creditsOn ? creditPaymentInfo() : '';

$pageTitle = 'Moj nalog — TelefonBerza';
$activePage = 'nalog';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content account-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Moj nalog</div>

        <section class="account-hero form-card">
            <div class="account-hero-main">
                <div class="seller-avatar account-avatar" aria-hidden="true"><?= h($initials) ?></div>
                <div class="account-hero-info">
                    <div class="account-name-row">
                        <h1 class="account-name"><?= h($displayName) ?></h1>
                        <div class="account-name-badges"><?= renderSellerBadges($profile) ?></div>
                    </div>
                    <p class="account-username">@<?= h((string)$profile['username']) ?></p>
                    <div class="account-rep"><?= renderReputation($summary, $shopLink) ?></div>
                </div>
            </div>
            <div class="account-hero-actions">
                <div class="account-hero-primary-row">
                    <a class="btn-call account-hero-primary" href="/ad_form.php">+ Novi oglas</a>
                    <a class="btn-message account-hero-izlog" href="<?= h($shopLink) ?>">Moj izlog</a>
                </div>
                <?php if ($creditsOn || $topOn): ?>
                    <div class="account-hero-secondary<?= $creditsOn && $topOn ? ' has-multi' : '' ?>">
                        <?php if ($creditsOn): ?>
                            <a class="btn-message btn-top-cta" href="?tab=krediti">Krediti: <?= number_format($userCredits, 0, ',', '.') ?></a>
                        <?php endif; ?>
                        <?php if ($topOn): ?>
                            <a class="btn-message account-hero-promo" href="?tab=top">⭐ TOP promocije</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php
        $statActive = count($activeAds);
        $statSold = count($soldAds);
        $statTotal = count($myAds);
        $statInactive = max(0, $statTotal - $statActive - $statSold);
        ?>
        <section class="account-overview form-card">
            <div class="account-overview-block">
                <div class="account-overview-head">
                    <h2>Oglasi</h2>
                    <a href="?tab=oglasi">Vidi sve</a>
                </div>
                <div class="account-overview-ads">
                    <a class="account-ov-stat" href="?tab=oglasi">
                        <strong><?= $statActive ?></strong>
                        <span>Aktivni</span>
                    </a>
                    <div class="account-ov-stat">
                        <strong><?= $statSold ?></strong>
                        <span>Prodato</span>
                    </div>
                    <div class="account-ov-stat">
                        <strong><?= $statTotal ?></strong>
                        <span>Ukupno</span>
                    </div>
                </div>
                <?php if ($statInactive > 0): ?>
                    <p class="account-overview-note"><?= $statInactive ?> neaktivnih (isključeni ili istekli)</p>
                <?php endif; ?>
            </div>

            <div class="account-overview-inbox">
                <?php if (!empty($site['enable_messages'])): ?>
                    <a class="account-ov-chip<?= $unread > 0 ? ' has-count' : '' ?>" href="/poruke.php">
                        <span>Poruke</span>
                        <em><?= (int)$unread ?></em>
                    </a>
                <?php endif; ?>
                <a class="account-ov-chip<?= $unreadNotifs > 0 ? ' has-count' : '' ?>" href="?tab=obavestenja">
                    <span>Obaveštenja</span>
                    <em><?= (int)$unreadNotifs ?></em>
                </a>
                <?php if ($creditsOn): ?>
                    <a class="account-ov-chip account-ov-chip-credits" href="?tab=krediti">
                        <span>Krediti</span>
                        <em><?= number_format($userCredits, 0, ',', '.') ?></em>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <nav class="account-tabs" aria-label="Sekcije naloga">
            <a href="?tab=pregled" class="<?= $tab === 'pregled' ? 'active' : '' ?>">Pregled</a>
            <a href="?tab=oglasi" class="<?= $tab === 'oglasi' ? 'active' : '' ?>">Oglasi</a>
            <?php if ($topOn): ?>
                <a href="?tab=top" class="<?= $tab === 'top' ? 'active' : '' ?>">TOP</a>
            <?php endif; ?>
            <?php if ($creditsOn): ?>
                <a href="?tab=krediti" class="<?= $tab === 'krediti' ? 'active' : '' ?>">Krediti</a>
            <?php endif; ?>
            <a href="?tab=obavestenja" class="<?= $tab === 'obavestenja' ? 'active' : '' ?>">Obav.<?= $unreadNotifs > 0 ? ' · ' . $unreadNotifs : '' ?></a>
            <a href="?tab=pretrage" class="<?= $tab === 'pretrage' ? 'active' : '' ?>">Pretrage</a>
            <a href="?tab=statistika" class="<?= $tab === 'statistika' ? 'active' : '' ?>">Stat.</a>
            <a href="?tab=profil" class="<?= $tab === 'profil' ? 'active' : '' ?>">Profil</a>
        </nav>
        <?php if ($tab === 'pregled'): ?>
            <section class="account-quick form-card">
                <button type="button" class="account-quick-toggle" data-account-quick-toggle aria-expanded="false" aria-controls="account-quick-list">
                    <span class="account-quick-toggle-main">
                        <strong>Brze akcije</strong>
                        <small>Oglasi, poruke, izlog i ostalo</small>
                    </span>
                    <span class="account-quick-chevron" aria-hidden="true">▾</span>
                </button>
                <div id="account-quick-list" class="account-quick-panel" data-account-quick-panel hidden>
                    <div class="account-quick-grid">
                        <a class="account-quick-item" href="/ad_form.php">
                            <span class="account-quick-icon">＋</span>
                            <strong>Postavi oglas</strong>
                            <span>Telefon, deo ili servis</span>
                        </a>
                        <?php if ($creditsOn): ?>
                            <a class="account-quick-item account-quick-top" href="?tab=krediti">
                                <span class="account-quick-icon">💰</span>
                                <strong>Dopuna kredita</strong>
                                <span>Saldo: <?= formatCredits($userCredits) ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($topOn): ?>
                            <a class="account-quick-item account-quick-top" href="?tab=oglasi">
                                <span class="account-quick-icon">⭐</span>
                                <strong>Promocije</strong>
                                <span>TOP, obnova, plavo isticanje</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($site['enable_messages'])): ?>
                            <a class="account-quick-item" href="/poruke.php">
                                <span class="account-quick-icon">💬</span>
                                <strong>Poruke<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></strong>
                                <span>Razgovori sa kupcima</span>
                            </a>
                        <?php endif; ?>
                        <a class="account-quick-item" href="?tab=obavestenja">
                            <span class="account-quick-icon">🔔</span>
                            <strong>Obaveštenja<?= $unreadNotifs > 0 ? ' (' . $unreadNotifs . ')' : '' ?></strong>
                            <span>Istek oglasa i ostalo</span>
                        </a>
                        <?php if (!empty($site['enable_favorites'])): ?>
                            <a class="account-quick-item" href="/favorites.php">
                                <span class="account-quick-icon">♡</span>
                                <strong>Omiljeni</strong>
                                <span>Sačuvani oglasi</span>
                            </a>
                        <?php endif; ?>
                        <a class="account-quick-item" href="<?= h($shopLink) ?>">
                            <span class="account-quick-icon">🏪</span>
                            <strong>Izlog</strong>
                            <span>Javni profil prodavca</span>
                        </a>
                        <?php if (isAdmin()): ?>
                            <a class="account-quick-item" href="/dashboard.php">
                                <span class="account-quick-icon">⚙</span>
                                <strong>Admin panel</strong>
                                <span>Prijave, korisnici, oglasi</span>
                            </a>
                        <?php endif; ?>
                        <a class="account-quick-item account-quick-danger" href="/logout.php">
                            <span class="account-quick-icon">↩</span>
                            <strong>Odjava</strong>
                            <span>Izlaz iz naloga</span>
                        </a>
                    </div>
                </div>
            </section>

            <?php if ($topOn && $activeAds): ?>
                <section class="form-card account-top-banner">
                    <div class="account-top-banner-text">
                        <h2>⭐ Istakni oglas (TOP)</h2>
                        <p>Kupi paket pa tvoj oglas ide na vrh liste i u sekciju istaknutih.</p>
                    </div>
                    <a class="btn-call" href="?tab=top" style="width:auto;min-width:160px;">Kupi TOP →</a>
                </section>
            <?php endif; ?>

            <section class="form-card shop-share-card">
                <h2>Podeli izlog</h2>
                <p class="form-hint">Jedan link za sve tvoje oglase i ocene — pošalji kupcu.</p>
                <div class="account-share-row">
                    <input type="text" class="shop-link-input" readonly value="<?= h($fullShopUrl) ?>" data-copy-full>
                    <button type="button" class="btn-message" data-copy-link data-copy-url="<?= h($shopLink) ?>">Kopiraj</button>
                </div>
            </section>

            <section class="form-card">
                <div class="account-section-head">
                    <h2>Nedavni oglasi</h2>
                    <a href="?tab=oglasi">Vidi sve →</a>
                </div>
                <?php if (!$myAds): ?>
                    <div class="account-empty">
                        <p>Još nemaš oglasa.</p>
                        <a class="btn-call" href="/ad_form.php" style="display:inline-block;width:auto;margin-top:10px;">Postavi prvi oglas</a>
                    </div>
                <?php else: ?>
                    <div class="account-ad-list">
                        <?php foreach (array_slice($myAds, 0, 4) as $ad): ?>
                            <?php
                            $type = getAdType($ad);
                            $statusLabel = !empty($ad['is_sold']) ? 'Prodato' : ((int)($ad['is_active'] ?? 0) === 1 ? 'Aktivan' : 'Neaktivan');
                            $statusClass = !empty($ad['is_sold']) ? 'is-sold' : ((int)($ad['is_active'] ?? 0) === 1 ? 'is-active' : 'is-off');
                            $daysLeft = $expiryOn ? adDaysRemaining($ad) : null;
                            ?>
                            <div class="account-ad-row">
                                <div class="account-ad-main">
                                    <a href="/oglas.php?id=<?= (int)$ad['id'] ?>" class="account-ad-title"><?= h((string)$ad['title']) ?></a>
                                    <div class="account-ad-meta">
                                        <span><?= h(adTypeLabel($type)) ?></span>
                                        <span><?= h(formatAdPrice($ad)) ?></span>
                                        <span class="account-ad-status <?= $statusClass ?>"><?= h($statusLabel) ?></span>
                                        <?php if ($daysLeft !== null && (int)($ad['is_active'] ?? 0) === 1 && empty($ad['is_sold'])): ?>
                                            <span class="account-expiry <?= $daysLeft <= $warningDays ? 'is-warn' : '' ?>">
                                                <?= $daysLeft > 0 ? 'Još ' . $daysLeft . ' d.' : 'Ističe danas' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="account-ad-actions">
                                    <a class="btn-sm" href="/oglas.php?id=<?= (int)$ad['id'] ?>">Pogledaj</a>
                                    <a class="btn-sm btn-sm-primary" href="/ad_form.php?id=<?= (int)$ad['id'] ?>">Izmeni</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'oglasi'): ?>
            <section class="form-card">
                <div class="account-section-head">
                    <h2>Moji oglasi (<?= count($myAds) ?>)</h2>
                    <a class="btn-sm btn-sm-primary" href="/ad_form.php">+ Novi</a>
                </div>
                <?php if ($expiryOn): ?>
                    <p class="form-hint">Oglas traje maksimalno <?= adMaxActiveDays() ?> dana. <strong>Obnova</strong> produžava rok i vraća oglas među novije (kao na KP).</p>
                <?php endif; ?>
                <?php if ($topOn): ?>
                    <p class="form-hint">Ispod oglasa klikni <strong>Promocije</strong> da aktiviraš TOP ili plavo isticanje. Kredit: <a href="?tab=krediti"><?= formatCredits($userCredits) ?></a></p>
                <?php endif; ?>
                <?php if (!$myAds): ?>
                    <div class="account-empty">
                        <p>Nemaš objavljenih oglasa.</p>
                        <a class="btn-call" href="/ad_form.php" style="display:inline-block;width:auto;margin-top:10px;">Postavi oglas</a>
                    </div>
                <?php else: ?>
                    <div class="account-ad-list">
                        <?php foreach ($myAds as $ad): ?>
                            <?php
                            $type = getAdType($ad);
                            $statusLabel = !empty($ad['is_sold']) ? 'Prodato' : ((int)($ad['is_active'] ?? 0) === 1 ? 'Aktivan' : 'Neaktivan');
                            $statusClass = !empty($ad['is_sold']) ? 'is-sold' : ((int)($ad['is_active'] ?? 0) === 1 ? 'is-active' : 'is-off');
                            $img = adPrimaryImage($ad);
                            $daysLeft = $expiryOn ? adDaysRemaining($ad) : null;
                            $topActive = isAdTopActive($ad);
                            $hiActive = isAdHighlighted($ad);
                            $canPromote = $topOn && empty($ad['is_sold']) && (int)($ad['is_active'] ?? 0) === 1;
                            $canObnova = empty($ad['is_sold']);
                            $renewCost = adRenewalCredits();
                            $hiCost = highlightCredits();
                            ?>
                            <div class="account-ad-row account-ad-row-full <?= $hiActive ? 'is-highlighted-row' : '' ?>">
                                <div class="account-ad-thumb">
                                    <?php if ($img): ?>
                                        <img src="<?= h($img) ?>" alt="">
                                    <?php else: ?>
                                        <span><?= h(mb_strtoupper(mb_substr(adTypeLabel($type), 0, 1))) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="account-ad-main">
                                    <a href="/oglas.php?id=<?= (int)$ad['id'] ?>" class="account-ad-title"><?= h((string)$ad['title']) ?></a>
                                    <div class="account-ad-meta">
                                        <span><?= h(adTypeLabel($type)) ?></span>
                                        <span><?= h(formatAdPrice($ad)) ?></span>
                                        <span><?= h((string)$ad['location']) ?></span>
                                        <span class="account-ad-status <?= $statusClass ?>"><?= h($statusLabel) ?></span>
                                        <?php if ($topActive): ?>
                                            <span class="account-ad-status is-top">TOP</span>
                                        <?php endif; ?>
                                        <?php if ($hiActive): ?>
                                            <span class="account-ad-status is-hi">Istaknut</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="account-ad-meta">
                                        <span>👁 <?= (int)($ad['views'] ?? 0) ?></span>
                                        <span><?= h(formatRelativeTime((string)$ad['created_at'])) ?></span>
                                        <?php if (!empty($ad['expires_at']) && $expiryOn): ?>
                                            <span class="account-expiry <?= ($daysLeft !== null && $daysLeft <= $warningDays) ? 'is-warn' : '' ?>">
                                                Ističe: <?= h(date('d.m.Y.', strtotime((string)$ad['expires_at']) ?: time())) ?>
                                                <?php if ($daysLeft !== null && (int)($ad['is_active'] ?? 0) === 1): ?>
                                                    (<?= $daysLeft > 0 ? 'još ' . $daysLeft . ' d.' : 'danas' ?>)
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="account-ad-actions kp-ad-actions">
                                        <a class="btn-sm" href="/oglas.php?id=<?= (int)$ad['id'] ?>">Pogledaj</a>
                                        <a class="btn-sm btn-sm-primary" href="/ad_form.php?id=<?= (int)$ad['id'] ?>">Izmeni</a>
                                        <?php if ($canPromote): ?>
                                            <button type="button" class="btn-sm btn-promo" data-promo-toggle aria-expanded="false">Promocije</button>
                                        <?php endif; ?>
                                        <?php if ($canObnova && ($creditsOn || $expiryOn)): ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Obnoviti oglas<?= $creditsOn && $renewCost > 0 ? ' za ' . formatCredits($renewCost) : '' ?>?');">
                                                <input type="hidden" name="action" value="obnova">
                                                <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                                <button type="submit" class="btn-sm">Obnova<?= $creditsOn && $renewCost > 0 ? ' (' . number_format($renewCost, 0, ',', '.') . ')' : '' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="sold">
                                            <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                            <button type="submit" class="btn-sm"><?= !empty($ad['is_sold']) ? 'Vrati u prodaju' : 'Prodato' ?></button>
                                        </form>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Trajno obrisati ovaj oglas? Ova radnja se ne može poništiti.');">
                                            <input type="hidden" name="action" value="delete_ad">
                                            <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                            <button type="submit" class="btn-sm btn-sm-danger">Obriši</button>
                                        </form>
                                    </div>

                                    <?php if ($canPromote): ?>
                                        <div class="promo-panel" data-promo-panel hidden>
                                            <div class="promo-panel-head">Aktiviraj promociju</div>
                                            <p class="form-hint">Saldo: <?= formatCredits($userCredits) ?><?= $userCredits < 300 ? ' · <a href="?tab=krediti">Dopuni kredit</a>' : '' ?></p>
                                            <form method="POST" class="promo-panel-form">
                                                <input type="hidden" name="action" value="buy_top">
                                                <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                                <select name="package_id" required>
                                                    <?php foreach ($topPackages as $pkg): ?>
                                                        <?php $cost = (int)$pkg['price']; $ok = !$creditsOn || $userCredits >= $cost; ?>
                                                        <option value="<?= h((string)$pkg['id']) ?>" <?= $ok ? '' : 'disabled' ?>>
                                                            TOP <?= h((string)$pkg['label']) ?> — <?= $creditsOn ? formatCredits($cost) : formatPrice((float)$cost) ?>
                                                            <?= $ok ? '' : ' (nedovoljno)' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn-sm btn-sm-primary"><?= $topActive ? 'Produži TOP' : 'Aktiviraj' ?></button>
                                            </form>
                                            <?php if ($hiCost > 0 && !$hiActive): ?>
                                                <form method="POST" class="promo-panel-form" style="margin-top:8px;">
                                                    <input type="hidden" name="action" value="highlight">
                                                    <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                                    <button type="submit" class="btn-sm" <?= ($creditsOn && $userCredits < $hiCost) ? 'disabled' : '' ?>>
                                                        + Istakni plavom (<?= formatCredits($hiCost) ?> / 7 dana)
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'top' && $topOn): ?>
            <section class="form-card account-top-buy">
                <h2>⭐ Promocije oglasa</h2>
                <?php if ($creditsOn): ?>
                    <p class="form-hint">Kao na KP: prvo <a href="?tab=krediti">dopuni kredit</a>, pa aktiviraj promociju. Saldo: <strong><?= formatCredits($userCredits) ?></strong>.</p>
                <?php else: ?>
                    <p class="form-hint">Izaberi oglas i paket promocije.</p>
                <?php endif; ?>

                <?php if ($topPackages): ?>
                    <div class="top-pkg-grid">
                        <?php foreach ($topPackages as $pkg): ?>
                            <div class="top-pkg-card">
                                <strong><?= h((string)$pkg['label']) ?></strong>
                                <span class="top-pkg-price"><?= $creditsOn ? formatCredits((int)$pkg['price']) : formatPrice((float)$pkg['price']) ?></span>
                                <span class="top-pkg-days"><?= (int)$pkg['days'] ?> dana na vrhu</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php
                $topEligible = array_values(array_filter($myAds, static fn($a) => empty($a['is_sold']) && (int)($a['is_active'] ?? 0) === 1));
                ?>
                <?php if (!$topEligible): ?>
                    <div class="account-empty">
                        <p>Nemaš aktivan oglas za isticanje.</p>
                        <a class="btn-call" href="/ad_form.php" style="display:inline-block;width:auto;margin-top:10px;">Postavi oglas</a>
                    </div>
                <?php elseif ($creditsOn && $userCredits <= 0): ?>
                    <div class="account-empty">
                        <p>Nemaš kredita. Prvo uplati (npr. 1.000 din) pa istakni oglas.</p>
                        <a class="btn-call" href="?tab=krediti" style="display:inline-block;width:auto;margin-top:10px;">Dopuni kredite</a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="account-top-buy-form">
                        <input type="hidden" name="action" value="buy_top">
                        <div class="form-group">
                            <label>Koji oglas ističeš?</label>
                            <select name="ad_id" required>
                                <?php foreach ($topEligible as $ad): ?>
                                    <option value="<?= (int)$ad['id'] ?>">
                                        <?= h((string)$ad['title']) ?> — <?= h(formatAdPrice($ad)) ?>
                                        <?= isAdTopActive($ad) ? ' (već TOP)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Paket</label>
                            <select name="package_id" required>
                                <?php foreach ($topPackages as $pkg): ?>
                                    <?php $cost = (int)$pkg['price']; $canAfford = !$creditsOn || $userCredits >= $cost; ?>
                                    <option value="<?= h((string)$pkg['id']) ?>" <?= $canAfford ? '' : 'disabled' ?>>
                                        <?= h((string)$pkg['label']) ?> — <?= $creditsOn ? formatCredits($cost) : formatPrice((float)$cost) ?>
                                        <?= $canAfford ? '' : ' (nemaš dovoljno)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-call" type="submit" style="width:auto;min-width:200px;">Plati kreditima i istakni</button>
                    </form>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'krediti' && $creditsOn): ?>
            <section class="form-card">
                <h2>💰 Dopuna kredita</h2>
                <div class="credit-balance-box">
                    <span>Trenutni saldo</span>
                    <strong><?= formatCredits($userCredits) ?></strong>
                </div>
                <p class="form-hint">Uplati na naš račun → pošalji zahtev → admin potvrdi uplatu → krediti se pojave. Kreditima plaćaš TOP isticanje oglasa.</p>

                <?php if (trim($creditPayInfo) !== ''): ?>
                    <pre class="top-payment-box"><?= h($creditPayInfo) ?></pre>
                <?php endif; ?>

                <h3 style="margin:16px 0 10px;font-size:15px;">Dopuni saldo</h3>
                <form method="POST" class="credit-topup-form">
                    <input type="hidden" name="action" value="request_credits">
                    <div class="credit-amount-chips">
                        <?php foreach ($creditAmounts as $amt): ?>
                            <label class="credit-amount-chip">
                                <input type="radio" name="amount" value="<?= (int)$amt ?>" <?= (int)$amt === 1000 ? 'checked' : '' ?> required>
                                <span><?= formatCredits((int)$amt) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn-call" type="submit" style="width:auto;min-width:200px;margin-top:12px;">Pošalji zahtev za uplatu</button>
                </form>
            </section>

            <?php if ($creditDeposits): ?>
                <section class="form-card">
                    <h2>Moji zahtevi</h2>
                    <div class="account-ad-list">
                        <?php foreach ($creditDeposits as $d): ?>
                            <div class="account-ad-row">
                                <div class="account-ad-main">
                                    <strong><?= formatCredits((int)$d['amount']) ?></strong>
                                    <div class="account-ad-meta">
                                        <span>KR-<?= (int)$d['id'] ?></span>
                                        <span><?= h((string)$d['status']) ?></span>
                                        <span><?= h((string)$d['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($creditTx): ?>
                <section class="form-card">
                    <h2>Istorija</h2>
                    <div class="account-ad-list">
                        <?php foreach ($creditTx as $tx): ?>
                            <div class="account-ad-row">
                                <div class="account-ad-main">
                                    <strong style="color:<?= (int)$tx['amount'] >= 0 ? '#1a7a3a' : '#b42318' ?>">
                                        <?= (int)$tx['amount'] >= 0 ? '+' : '' ?><?= formatCredits((int)$tx['amount']) ?>
                                    </strong>
                                    <div class="account-ad-meta">
                                        <span><?= h((string)$tx['type']) ?></span>
                                        <span><?= h((string)($tx['note'] ?? '')) ?></span>
                                        <span><?= h((string)$tx['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php elseif ($tab === 'obavestenja'): ?>
            <section class="form-card">
                <div class="account-section-head">
                    <h2>Obaveštenja</h2>
                    <?php if ($unreadNotifs > 0): ?>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="mark_notifications">
                            <button type="submit" class="btn-sm">Označi pročitano</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!$notifications): ?>
                    <div class="account-empty"><p>Nemaš obaveštenja.</p></div>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($notifications as $n): ?>
                            <div class="notif-item <?= empty($n['is_read']) ? 'is-unread' : '' ?>">
                                <div class="notif-item-head">
                                    <strong><?= h((string)$n['title']) ?></strong>
                                    <span><?= h(formatRelativeTime((string)$n['created_at'])) ?></span>
                                </div>
                                <p><?= h((string)$n['body']) ?></p>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?= h((string)$n['link']) ?>">Otvori →</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'pretrage'): ?>
            <section class="form-card">
                <h2>Sačuvane pretrage</h2>
                <p class="form-hint">Sačuvaj filtere sa početne i uključi alert za nove oglase.</p>
                <?php if (!$savedSearches): ?>
                    <div class="account-empty"><p>Nemaš sačuvanih pretraga. Na početnoj izaberi filtere pa klikni „Sačuvaj pretragu”.</p></div>
                <?php else: ?>
                    <div class="account-ad-list">
                        <?php foreach ($savedSearches as $ss): ?>
                            <?php $q = buildFilterQuery($ss['filters'] ?? []); ?>
                            <div class="account-ad-row">
                                <div class="account-ad-main">
                                    <strong><?= h(savedSearchLabel($ss)) ?></strong>
                                    <div class="account-ad-meta">
                                        <span><?= !empty($ss['alert_enabled']) ? 'Alert uključen' : 'Bez alerta' ?></span>
                                        <span><?= h((string)($ss['created_at'] ?? '')) ?></span>
                                    </div>
                                </div>
                                <div class="account-ad-actions">
                                    <a class="btn-sm btn-sm-primary" href="/index.php<?= $q !== '' ? '?' . h($q) : '' ?>">Pokreni</a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_search_alert">
                                        <input type="hidden" name="search_id" value="<?= (int)$ss['id'] ?>">
                                        <button class="btn-sm" type="submit"><?= !empty($ss['alert_enabled']) ? 'Isključi alert' : 'Uključi alert' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Obrisati pretragu?');">
                                        <input type="hidden" name="action" value="delete_search">
                                        <input type="hidden" name="search_id" value="<?= (int)$ss['id'] ?>">
                                        <button class="btn-sm btn-sm-danger" type="submit">Obriši</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'statistika'): ?>
            <section class="form-card">
                <h2>Statistika oglasa</h2>
                <p class="form-hint">Pregledi (bez duplikata 30 min), otkrivanja telefona i započete poruke. Period: poslednjih <?= (int)$sellerStats['days'] ?> dana u dnevnim bucketima.</p>
                <div class="account-stats" style="margin-bottom:16px;">
                    <div class="account-stat">
                        <span class="account-stat-value"><?= (int)$sellerStats['totals']['views'] ?></span>
                        <span class="account-stat-label">Pregledi (ukupno)</span>
                    </div>
                    <div class="account-stat">
                        <span class="account-stat-value"><?= (int)$sellerStats['totals']['phone_reveals'] ?></span>
                        <span class="account-stat-label">Otkrivanja telefona</span>
                    </div>
                    <div class="account-stat">
                        <span class="account-stat-value"><?= (int)$sellerStats['totals']['messages_started'] ?></span>
                        <span class="account-stat-label">Započete poruke</span>
                    </div>
                </div>
                <?php if (!$sellerStats['ads']): ?>
                    <div class="account-empty"><p>Nemaš oglase za statistiku.</p></div>
                <?php else: ?>
                    <div class="account-ad-list">
                        <?php foreach ($sellerStats['ads'] as $row): ?>
                            <?php $ad = $row['ad']; ?>
                            <div class="account-ad-row">
                                <div class="account-ad-main">
                                    <strong><a href="<?= h(adUrl($ad)) ?>"><?= h((string)$ad['title']) ?></a></strong>
                                    <div class="account-ad-meta">
                                        <span>👁 <?= (int)$row['views'] ?> (<?= (int)$row['period_views'] ?> / <?= (int)$sellerStats['days'] ?>d)</span>
                                        <span>☎ <?= (int)$row['phone_reveals'] ?> (<?= (int)$row['period_phone_reveals'] ?>)</span>
                                        <span>💬 <?= (int)$row['messages_started'] ?> (<?= (int)$row['period_messages'] ?>)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($tab === 'profil'): ?>
            <?php
            $bizStatus = userBusinessStatus($profile);
            $accountType = userAccountType($profile);
            $bizKind = userBusinessKind($profile);
            $phoneOk = isPhoneVerified($profile);
            $emailOk = isEmailVerified($profile);
            $hasEmail = trim((string)($profile['email'] ?? '')) !== '';
            $canRequestBusiness = $accountType === 'business' && !in_array($bizStatus, ['approved', 'pending'], true);
            ?>
            <section class="form-card account-profile-card">
                <div class="profile-head">
                    <div>
                        <h2>Podaci profila</h2>
                        <p class="profile-head-sub">Osnovni podaci, firma i kontakt za oglase.</p>
                    </div>
                    <?php if (isBusinessVerified($profile) || isVerifiedSeller($profile)): ?>
                        <div class="profile-head-badges"><?= renderSellerBadges($profile) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($accountType === 'business'): ?>
                    <div class="profile-status profile-status-<?= h($bizStatus === 'none' ? 'idle' : $bizStatus) ?>">
                        <?php if ($bizStatus === 'approved'): ?>
                            <strong>Firma potvrđena</strong>
                            <span>Bedž <?= h(businessKindLabel($bizKind)) ?> je aktivan na oglasima i izlogu.</span>
                        <?php elseif ($bizStatus === 'pending'): ?>
                            <strong>Čeka admin potvrdu</strong>
                            <span>Zahtev je poslat. Kad admin potvrdi, bedž će se pojaviti automatski.</span>
                        <?php elseif ($bizStatus === 'rejected'): ?>
                            <strong>Zahtev odbijen</strong>
                            <span><?= h((string)($profile['business_reject_reason'] ?? 'Ispravi podatke i pošalji ponovo.')) ?></span>
                        <?php else: ?>
                            <strong>Firma još nije poslata na potvrdu</strong>
                            <span>Popuni naziv, vrstu i PIB, sačuvaj, pa pošalji zahtev ispod.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="account-profile-form" id="account-profile-form">
                    <input type="hidden" name="action" value="profile">

                    <div class="profile-section">
                        <h3 class="profile-section-title">Osnovno</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ime i prezime</label>
                                <input type="text" name="full_name" value="<?= h((string)$profile['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Korisničko ime</label>
                                <input type="text" value="<?= h((string)$profile['username']) ?>" disabled>
                                <p class="form-hint">Ne može se menjati.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tip naloga</label>
                            <select name="account_type" id="account-type-select">
                                <option value="private" <?= $accountType === 'private' ? 'selected' : '' ?>>Fizičko lice</option>
                                <option value="business" <?= $accountType === 'business' ? 'selected' : '' ?>>Firma</option>
                            </select>
                        </div>
                    </div>

                    <div class="profile-section" data-business-block <?= $accountType === 'business' ? '' : 'hidden' ?>>
                        <h3 class="profile-section-title">Firma / izlog</h3>
                        <p class="profile-section-desc">Ovi podaci idu na javni izlog. Bedž firme dobijaš tek posle admin potvrde.</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vrsta firme</label>
                                <select name="business_kind">
                                    <option value="">Izaberi…</option>
                                    <option value="service" <?= $bizKind === 'service' ? 'selected' : '' ?>>Servis</option>
                                    <option value="shop" <?= $bizKind === 'shop' ? 'selected' : '' ?>>Mobile Shop</option>
                                    <option value="both" <?= $bizKind === 'both' ? 'selected' : '' ?>>Servis & Mobile Shop</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>PIB</label>
                                <input type="text" name="pib" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" value="<?= h((string)($profile['pib'] ?? '')) ?>" placeholder="123456789" autocomplete="off">
                                <p class="form-hint">Tačno 9 cifara.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Naziv firme / izloga</label>
                            <input type="text" name="shop_name" value="<?= h((string)($profile['shop_name'] ?? '')) ?>" placeholder="npr. MobilServis XYZ">
                        </div>
                        <div class="form-group">
                            <label>Kratki opis</label>
                            <textarea name="shop_bio" rows="3" placeholder="Šta radiš, gde si, radno vreme..."><?= h((string)($profile['shop_bio'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <div class="profile-section" data-private-shop <?= $accountType === 'business' ? 'hidden' : '' ?>>
                        <h3 class="profile-section-title">Izlog (opciono)</h3>
                        <div class="form-group">
                            <label>Naziv izloga</label>
                            <input type="text" name="shop_name_private" value="<?= h((string)($profile['shop_name'] ?? '')) ?>" placeholder="npr. tvoje ime ili nick za izlog" <?= $accountType === 'business' ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label>Kratki opis</label>
                            <textarea name="shop_bio_private" rows="3" placeholder="Šta prodaješ..." <?= $accountType === 'business' ? 'disabled' : '' ?>><?= h((string)($profile['shop_bio'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">Kontakt</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Telefon</label>
                                <input type="text" name="phone" value="<?= h((string)($profile['phone'] ?? '')) ?>" placeholder="06x xxx xxxx" required>
                                <p class="form-hint">
                                    <?php if ($phoneOk): ?>
                                        <span class="status-ok">Potvrđen</span>
                                    <?php else: ?>
                                        <span class="status-warn">Nije potvrđen</span> — potvrdi SMS-om ispod.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="form-group">
                                <label>Grad za oglase</label>
                                <select name="location">
                                    <option value="">Izaberi grad</option>
                                    <?php foreach (($site['cities'] ?? []) as $city): ?>
                                        <option value="<?= h($city) ?>" <?= (string)($profile['location'] ?? '') === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= h((string)($profile['email'] ?? '')) ?>" placeholder="ime@email.com">
                            <?php if ($hasEmail): ?>
                                <p class="form-hint">
                                    <?php if ($emailOk): ?>
                                        <span class="status-ok">Potvrđen</span>
                                    <?php else: ?>
                                        <span class="status-warn">Nije potvrđen</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <label class="profile-check">
                            <input type="checkbox" name="notify_email" value="1" <?= !isset($profile['notify_email']) || !empty($profile['notify_email']) ? 'checked' : '' ?>>
                            <span>Email obaveštenja (poruke, alerti, istek oglasa)</span>
                        </label>
                    </div>

                    <div class="profile-actions">
                        <button class="btn-call" type="submit">Sačuvaj izmene</button>
                    </div>
                </form>

                <div class="profile-side-actions">
                    <?php if ($canRequestBusiness): ?>
                        <form method="POST" class="profile-action-row">
                            <input type="hidden" name="action" value="request_business">
                            <button class="btn-sm btn-sm-primary" type="submit">Pošalji zahtev za bedž firme</button>
                            <span class="form-hint">Sačuvaj profil pa pošalji zahtev.</span>
                        </form>
                    <?php endif; ?>
                    <?php if ($hasEmail && !$emailOk): ?>
                        <form method="POST" class="profile-action-row">
                            <input type="hidden" name="action" value="verify_email">
                            <button class="btn-sm btn-sm-primary" type="submit">Pošalji link za potvrdu emaila</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$phoneOk && normalizePhoneRs((string)($profile['phone'] ?? '')) !== null): ?>
                        <form method="POST" class="profile-action-row">
                            <input type="hidden" name="action" value="verify_phone">
                            <button class="btn-sm btn-sm-primary" type="submit">Pošalji SMS kod za telefon</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <script>
            (function () {
              var typeSel = document.getElementById('account-type-select');
              var form = document.getElementById('account-profile-form');
              if (!typeSel || !form) return;

              function sync() {
                var biz = typeSel.value === 'business';
                form.querySelectorAll('[data-business-block]').forEach(function (el) {
                  el.hidden = !biz;
                  el.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !biz;
                  });
                });
                form.querySelectorAll('[data-private-shop]').forEach(function (el) {
                  el.hidden = biz;
                  el.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = biz;
                  });
                });
              }

              typeSel.addEventListener('change', sync);
              sync();
            })();
            </script>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
