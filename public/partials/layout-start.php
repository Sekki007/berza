<?php
/** @var string $pageTitle */
/** @var string $activePage */
/** @var string $bodyClass */
/** @var bool $minimalHeader */
/** @var bool $showSearch */

$user = currentUser();
$flash = getFlash();
$site = siteSettings();
$pageTitle = $pageTitle ?? ($site['site_name'] ?? 'TelefonBerza');
$activePage = $activePage ?? 'oglasi';
$bodyClass = trim($bodyClass ?? '');
$minimalHeader = $minimalHeader ?? false;
$showSearch = $showSearch ?? true;
$searchValue = $searchValue ?? '';
$pageDescription = $pageDescription ?? ((string)($site['topbar_text'] ?? '') . ' — ' . (string)($site['site_name'] ?? 'TelefonBerza'));
$pageImage = $pageImage ?? '';
$canonicalUrl = $canonicalUrl ?? absoluteUrl(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$unreadMessages = ($user && !empty($site['enable_messages'])) ? getUnreadMessageCount((int)$user['id']) : 0;
$unreadNotifications = $user ? getUnreadNotificationCount((int)$user['id']) : 0;
$compareCount = count(compareIds());
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfMetaTag() ?>
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    <meta property="og:type" content="<?= h($ogType ?? 'website') ?>">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= h((string)($site['site_name'] ?? 'TelefonBerza')) ?>">
    <?php if ($pageImage !== ''): ?>
        <meta property="og:image" content="<?= h($pageImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?= h($pageImage) ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= h($pageTitle) ?>">
    <meta name="twitter:description" content="<?= h($pageDescription) ?>">
    <link rel="stylesheet" href="/assets/css/style.css?v=20260725j">
</head>
<body class="<?= h($bodyClass) ?>" data-page="<?= h($activePage) ?>" data-unread-messages="<?= (int)$unreadMessages ?>" data-compare-count="<?= (int)$compareCount ?>">
<?php if (!$minimalHeader): ?>
    <div class="topbar">
        <div class="topbar-inner">
            <span><?= h((string)$site['topbar_text']) ?></span>
            <div class="topbar-links">
                <a href="/kako-radi.php">Kako radi</a>
                <?php if (!empty($site['enable_favorites'])): ?><a href="/favorites.php">Omiljeni</a><?php endif; ?>
                <?php if ($user): ?>
                    <?php if (!empty($site['enable_messages'])): ?>
                        <a class="nav-with-badge" href="/poruke.php">Poruke<?= renderUnreadBadge($unreadMessages) ?></a>
                    <?php endif; ?>
                    <a class="nav-with-badge" href="/nalog.php?tab=obavestenja">Obaveštenja<?= renderUnreadBadge($unreadNotifications) ?></a>
                    <a href="/logout.php">Odjava</a>
                <?php else: ?>
                    <a href="/login.php">Prijava</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="header-inner">
            <div class="header-row">
                <a href="/index.php" class="logo">
                    <span class="logo-telefon"><?= h((string)$site['logo_telefon']) ?></span><span class="logo-berza"><?= h((string)$site['logo_berza']) ?></span>
                </a>
                <div class="header-user-mobile">
                    <button type="button" class="mobile-menu-btn nav-with-badge" data-open-account-menu aria-label="Meni" aria-expanded="false" aria-controls="mobile-account-menu">
                        <span class="mobile-menu-btn-icon" aria-hidden="true">☰</span>
                        <span class="mobile-menu-btn-label">Meni</span>
                        <?= $user ? renderUnreadBadge($unreadMessages + $unreadNotifications) : '' ?>
                    </button>
                </div>
            </div>

            <?php if ($showSearch): ?>
                <form class="search-area" method="GET" action="/index.php" data-search-form autocomplete="off" id="search">
                    <div class="search-wrap">
                        <input class="search-input" type="search" name="q" value="<?= h($searchValue) ?>" placeholder="<?= h((string)$site['search_placeholder']) ?>" data-search-input autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-suggest">
                        <div id="search-suggest" class="search-suggest" data-search-suggest hidden role="listbox"></div>
                    </div>
                    <button class="search-btn" type="submit">Pretraži</button>
                </form>
            <?php endif; ?>

            <a class="btn-post" href="<?= $user ? '/ad_form.php' : '/login.php' ?>">Postavite oglas</a>
            <div class="header-user">
                <?php if ($user): ?>
                    <span class="header-user-name" title="<?= h((string)$user['full_name']) ?>"><?= h((string)$user['full_name']) ?></span>
                    <nav class="header-nav" aria-label="Nalog">
                        <?php if (!empty($site['enable_messages'])): ?>
                            <a class="header-nav-link nav-with-badge" href="/poruke.php" title="Poruke">
                                <svg class="header-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H9.2L5.4 19.1a.75.75 0 0 1-1.2-.6V5.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                </svg>
                                <span class="header-nav-label">Poruke</span>
                                <?= renderUnreadBadge($unreadMessages) ?>
                            </a>
                        <?php endif; ?>
                        <a class="header-nav-link nav-with-badge" href="/nalog.php" title="Moj nalog">
                            <svg class="header-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <circle cx="12" cy="8.2" r="3.2" fill="none" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M5.5 19.2c1.4-3 3.7-4.5 6.5-4.5s5.1 1.5 6.5 4.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <span class="header-nav-label">Moj nalog</span>
                            <?= renderUnreadBadge($unreadNotifications) ?>
                        </a>
                        <?php if (isAdmin()): ?>
                            <a class="header-nav-link header-nav-admin" href="/dashboard.php" title="Admin panel">
                                <svg class="header-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 3.5 19.5 7v4.2c0 4.6-3 7.8-7.5 9.3-4.5-1.5-7.5-4.7-7.5-9.3V7L12 3.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                    <path d="M9.2 12.1 11 13.9l3.8-3.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="header-nav-label">Admin</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php else: ?>
                    <nav class="header-nav" aria-label="Prijava">
                        <a class="header-nav-link" href="/login.php" title="Prijava">
                            <svg class="header-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M10 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <path d="M10.5 12H20m0 0-3.2-3.2M20 12l-3.2 3.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="header-nav-label">Prijava</span>
                        </a>
                        <?php if (!empty($site['enable_registration'])): ?>
                            <a class="header-nav-link header-nav-primary" href="/register.php" title="Registracija">
                                <span class="header-nav-label">Registracija</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>
<?php else: ?>
    <header class="header">
        <div class="header-inner">
            <div class="header-row">
                <a href="/index.php" class="logo">
                    <span class="logo-telefon"><?= h((string)$site['logo_telefon']) ?></span><span class="logo-berza"><?= h((string)$site['logo_berza']) ?></span>
                </a>
            </div>
        </div>
    </header>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="container" style="padding-top:12px;">
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    </div>
<?php endif; ?>

<?php if ($user && $unreadMessages > 0 && ($activePage ?? '') !== 'poruke'): ?>
    <div class="msg-toast" data-msg-toast>
        <div class="msg-toast-inner">
            <strong>Nova poruka<?= $unreadMessages > 1 ? 'e' : '' ?></strong>
            <span>Imaš <?= (int)$unreadMessages ?> nepročitanih poruka.</span>
            <a href="/poruke.php">Otvori inbox</a>
            <button type="button" class="msg-toast-close" data-msg-toast-close aria-label="Zatvori">×</button>
        </div>
    </div>
<?php endif; ?>
