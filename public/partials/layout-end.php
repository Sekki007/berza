<?php
/** @var string $activePage */
/** @var bool $hideMobileBar */

$user = currentUser();
$site = siteSettings();
$activePage = $activePage ?? 'oglasi';
$hideMobileBar = $hideMobileBar ?? false;
$unreadMessages = ($user && !empty($site['enable_messages'])) ? getUnreadMessageCount((int)$user['id']) : 0;
$unreadNotifications = $user ? getUnreadNotificationCount((int)$user['id']) : 0;
$menuDisplayName = '';
$menuShopLink = '';
if ($user) {
    $menuProfile = findUserById((int)$user['id']) ?? $user;
    $menuDisplayName = (string)(($menuProfile['shop_name'] ?? '') ?: ($menuProfile['full_name'] ?? $user['full_name'] ?? ''));
    $menuShopLink = shopUrlForUser($menuProfile);
}
?>
    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-brand">
                <a href="/index.php" class="site-footer-logo" aria-label="<?= h((string)($site['site_name'] ?? 'KupiTelefon')) ?>">
                    <img class="logo-mark logo-mark-footer" src="/assets/img/logo-mark.png" alt="" width="32" height="32" decoding="async">
                    <span class="logo-text">
                        <span class="logo-telefon"><?= h((string)$site['logo_telefon']) ?></span><span class="logo-berza"><?= h((string)$site['logo_berza']) ?></span>
                    </span>
                </a>
                <p class="site-footer-tag"><?= h(siteTagline()) ?></p>
                <?php
                $footerPhone = trim((string)($site['contact_phone'] ?? ''));
                $footerEmail = trim((string)($site['contact_email'] ?? ''));
                if ($footerEmail === '' || !filter_var($footerEmail, FILTER_VALIDATE_EMAIL)) {
                    $footerEmail = 'podrska@kupitelefon.rs';
                }
                ?>
                    <div class="site-footer-contact">
                        <strong class="site-footer-contact-label">Kontakt</strong>
                        <?php if ($footerPhone !== ''): ?>
                            <a href="tel:<?= h(preg_replace('/\s+/', '', $footerPhone) ?? $footerPhone) ?>">Tel: <?= h($footerPhone) ?></a>
                        <?php endif; ?>
                        <a href="mailto:<?= h($footerEmail) ?>">E-mail: <?= h($footerEmail) ?></a>
                    </div>
            </div>

            <nav class="site-footer-nav" aria-label="Footer">
                <div class="site-footer-col">
                    <h4>Istraži</h4>
                    <a href="/index.php">Početna</a>
                    <a href="/index.php?type=telefon">Uređaji</a>
                    <a href="/index.php?type=delovi">Oprema</a>
                    <a href="/index.php?type=servis">Servis</a>
                </div>
                <div class="site-footer-col">
                    <h4>Prodaja</h4>
                    <a href="/ad_form.php">Postavi oglas</a>
                    <a href="/kako-radi.php">Kako radi</a>
                    <?php if (empty($user)): ?>
                        <a href="/register.php">Registracija</a>
                        <a href="/login.php">Prijava</a>
                    <?php else: ?>
                        <a href="/nalog.php?tab=oglasi">Moji oglasi</a>
                        <?php if (topPurchaseEnabled()): ?><a href="/nalog.php?tab=top">TOP isticanje</a><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="site-footer-col">
                    <h4>Nalog</h4>
                    <?php if ($user): ?>
                        <a href="/nalog.php">Moj profil</a>
                        <?php if (!empty($site['enable_messages'])): ?><a href="/poruke.php">Poruke</a><?php endif; ?>
                        <?php if (!empty($site['enable_favorites'])): ?><a href="/favorites.php">Omiljeni</a><?php endif; ?>
                        <a href="/nalog.php?tab=obavestenja">Obaveštenja</a>
                    <?php else: ?>
                        <a href="/login.php">Prijava</a>
                        <a href="/register.php">Napravi nalog</a>
                        <a href="/kako-radi.php">Pomoć</a>
                    <?php endif; ?>
                    <a href="mailto:podrska@kupitelefon.rs">Podrška</a>
                </div>
            </nav>
        </div>
        <div class="site-footer-bottom">
            <div class="site-footer-bottom-inner">
                <span><?= h((string)$site['footer_copyright']) ?></span>
            </div>
        </div>
    </footer>

    <div class="mobile-menu-overlay" data-account-menu-overlay></div>
    <aside id="mobile-account-menu" class="mobile-account-menu" data-account-menu aria-hidden="true" role="dialog" aria-label="Meni naloga">
        <div class="mobile-account-menu-handle" aria-hidden="true"></div>
        <div class="mobile-account-menu-head">
            <div>
                <strong><?= $user ? h($menuDisplayName !== '' ? $menuDisplayName : 'Moj nalog') : 'Dobrodošli' ?></strong>
                <span><?= $user ? 'Prijavljen' : 'Prijavi se da upravljaš oglasima' ?></span>
            </div>
            <button type="button" class="mobile-account-menu-close" data-close-account-menu aria-label="Zatvori">×</button>
        </div>
        <nav class="mobile-account-menu-nav">
            <?php if ($user): ?>
                <a href="/nalog.php" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">👤</span>
                    <span class="mobile-account-menu-text">
                        <strong>Moj nalog</strong>
                        <small>Pregled, statistika, brze akcije</small>
                    </span>
                </a>
                <a href="/nalog.php?tab=oglasi" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">📋</span>
                    <span class="mobile-account-menu-text">
                        <strong>Moji oglasi</strong>
                        <small>Izmena, produženje, status</small>
                    </span>
                </a>
                <?php if (topPurchaseEnabled()): ?>
                    <a href="/nalog.php?tab=top" class="mobile-account-menu-item mobile-account-menu-item-accent">
                        <span class="mobile-account-menu-icon">⭐</span>
                        <span class="mobile-account-menu-text">
                            <strong>Kupi TOP isticanje</strong>
                            <small>Plaća se kreditima</small>
                        </span>
                    </a>
                <?php endif; ?>
                <?php if (creditsEnabled()): ?>
                    <a href="/nalog.php?tab=krediti" class="mobile-account-menu-item mobile-account-menu-item-accent">
                        <span class="mobile-account-menu-icon">💰</span>
                        <span class="mobile-account-menu-text">
                            <strong>Krediti</strong>
                            <small>Saldo: <?= formatCredits(getUserCredits((int)$user['id'])) ?></small>
                        </span>
                    </a>
                <?php endif; ?>
                <a href="/nalog.php?tab=obavestenja" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">🔔</span>
                    <span class="mobile-account-menu-text">
                        <strong>Obaveštenja</strong>
                        <small>Istek oglasa i ostalo</small>
                    </span>
                    <?= renderUnreadBadge($unreadNotifications) ?>
                </a>
                <?php if (!empty($site['enable_messages'])): ?>
                    <a href="/poruke.php" class="mobile-account-menu-item">
                        <span class="mobile-account-menu-icon">💬</span>
                        <span class="mobile-account-menu-text">
                            <strong>Poruke</strong>
                            <small>Razgovori sa kupcima</small>
                        </span>
                        <?= renderUnreadBadge($unreadMessages) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($site['enable_favorites'])): ?>
                    <a href="/favorites.php" class="mobile-account-menu-item">
                        <span class="mobile-account-menu-icon">♡</span>
                        <span class="mobile-account-menu-text">
                            <strong>Omiljeni</strong>
                            <small>Sačuvani oglasi</small>
                        </span>
                    </a>
                <?php endif; ?>
                <a href="<?= h($menuShopLink) ?>" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">🏪</span>
                    <span class="mobile-account-menu-text">
                        <strong>Moj izlog</strong>
                        <small>Javni profil prodavca</small>
                    </span>
                </a>
                <a href="/nalog.php?tab=profil" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">✎</span>
                    <span class="mobile-account-menu-text">
                        <strong>Profil</strong>
                        <small>Ime, telefon, email</small>
                    </span>
                </a>
                <a href="/ad_form.php" class="mobile-account-menu-item mobile-account-menu-item-accent">
                    <span class="mobile-account-menu-icon">＋</span>
                    <span class="mobile-account-menu-text">
                        <strong>Novi oglas</strong>
                        <small>Telefon, deo ili servis</small>
                    </span>
                </a>
                <?php if (isAdmin()): ?>
                    <a href="/dashboard.php" class="mobile-account-menu-item">
                        <span class="mobile-account-menu-icon">⚙</span>
                        <span class="mobile-account-menu-text">
                            <strong>Admin panel</strong>
                            <small>Prijave, korisnici, podešavanja</small>
                        </span>
                    </a>
                <?php endif; ?>
                <a href="/logout.php" class="mobile-account-menu-item mobile-account-menu-item-danger">
                    <span class="mobile-account-menu-icon">↩</span>
                    <span class="mobile-account-menu-text">
                        <strong>Odjava</strong>
                        <small>Izlaz iz naloga</small>
                    </span>
                </a>
            <?php else: ?>
                <a href="/login.php" class="mobile-account-menu-item mobile-account-menu-item-accent">
                    <span class="mobile-account-menu-icon">🔑</span>
                    <span class="mobile-account-menu-text">
                        <strong>Prijava</strong>
                        <small>Uđi u nalog</small>
                    </span>
                </a>
                <?php if (!empty($site['enable_registration'])): ?>
                    <a href="/register.php" class="mobile-account-menu-item">
                        <span class="mobile-account-menu-icon">＋</span>
                        <span class="mobile-account-menu-text">
                            <strong>Registracija</strong>
                            <small>Napravi nalog</small>
                        </span>
                    </a>
                <?php endif; ?>
                <a href="/ad_form.php" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">📋</span>
                    <span class="mobile-account-menu-text">
                        <strong>Postavi oglas</strong>
                        <small>Potrebna je prijava</small>
                    </span>
                </a>
                <a href="/kako-radi.php" class="mobile-account-menu-item">
                    <span class="mobile-account-menu-icon">ℹ</span>
                    <span class="mobile-account-menu-text">
                        <strong>Kako radi</strong>
                        <small>Kratko uputstvo</small>
                    </span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <?php if (!$hideMobileBar): ?>
        <nav class="mobile-bar">
            <a href="/index.php" data-nav="oglasi" class="<?= $activePage === 'oglasi' ? 'active' : '' ?>"><span class="mobile-bar-icon">🏠</span><span>Oglasi</span></a>
            <a href="/index.php#search" data-nav="pretraga" data-focus-search class="<?= $activePage === 'pretraga' ? 'active' : '' ?>"><span class="mobile-bar-icon">🔎</span><span>Pretraga</span></a>
            <a href="<?= $user ? '/ad_form.php' : '/login.php' ?>" data-nav="dodaj" class="<?= $activePage === 'dodaj' ? 'active' : '' ?>"><span class="mobile-bar-icon">＋</span><span>Dodaj</span></a>
            <a href="<?= $user ? '/poruke.php' : '/login.php' ?>" data-nav="poruke" class="nav-with-badge <?= $activePage === 'poruke' ? 'active' : '' ?>"><span class="mobile-bar-icon">💬</span><span>Poruke</span><?= $user ? renderUnreadBadge($unreadMessages) : '' ?></a>
            <button type="button" data-nav="nalog" data-open-account-menu class="mobile-bar-account nav-with-badge <?= $activePage === 'nalog' ? 'active' : '' ?>" aria-label="Nalog meni" aria-expanded="false" aria-controls="mobile-account-menu">
                <span class="mobile-bar-icon">👤</span>
                <span>Nalog</span>
                <?= $user ? renderUnreadBadge($unreadNotifications) : '' ?>
            </button>
        </nav>
    <?php endif; ?>

    <a class="compare-bar" href="/uporedi.php" data-compare-bar <?= count(compareIds()) > 0 ? '' : 'hidden' ?>>
        <span data-compare-bar-label>Uporedi (<span data-compare-count><?= count(compareIds()) ?></span>)</span>
        <span class="btn-sm btn-sm-primary">Otvori</span>
    </a>

    <script src="/assets/js/app.js?v=20260728h"></script>
    <?= renderFacebookPixelBootstrap() ?>
</body>
</html>
