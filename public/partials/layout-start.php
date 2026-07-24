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
    <link rel="stylesheet" href="/assets/css/style.css?v=20260724b">
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
                    <span><?= h($user['full_name']) ?></span>
                    <?php if (!empty($site['enable_messages'])): ?>
                        <a class="nav-with-badge" href="/poruke.php">Poruke<?= renderUnreadBadge($unreadMessages) ?></a>
                    <?php endif; ?>
                    <a class="nav-with-badge" href="/nalog.php">Moj nalog<?= renderUnreadBadge($unreadNotifications) ?></a>
                    <?php if (isAdmin()): ?><a href="/dashboard.php">Admin</a><?php endif; ?>
                <?php else: ?>
                    <a href="/login.php">Ulogujte se</a>
                    <?php if (!empty($site['enable_registration'])): ?><a href="/register.php">Registrujte se</a><?php endif; ?>
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
