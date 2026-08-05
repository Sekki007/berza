<?php
/** @var string $adminPage */
$adminPage = $adminPage ?? 'dashboard';
$openReports = function_exists('getOpenReportsCount') ? getOpenReportsCount() : 0;
$pendingTop = function_exists('getPendingTopOrdersCount') ? getPendingTopOrdersCount() : 0;
$pendingCredits = function_exists('getPendingCreditDepositsCount') ? getPendingCreditDepositsCount() : 0;

$adminLinks = [
    ['id' => 'dashboard', 'href' => '/dashboard.php', 'label' => 'Dashboard'],
    ['id' => 'reports', 'href' => '/admin_reports.php', 'label' => 'Prijave' . ($openReports > 0 ? " ($openReports)" : '')],
    ['id' => 'users', 'href' => '/admin_users.php', 'label' => 'Korisnici'],
    ['id' => 'ads', 'href' => '/ads.php', 'label' => 'Oglasi'],
    ['id' => 'top', 'href' => '/admin_top.php', 'label' => 'TOP' . ($pendingTop > 0 ? " ($pendingTop)" : '')],
    ['id' => 'credits', 'href' => '/admin_credits.php', 'label' => 'Krediti' . ($pendingCredits > 0 ? " ($pendingCredits)" : '')],
    ['id' => 'analytics', 'href' => '/admin_analytics.php', 'label' => 'Posete (GA)'],
    ['id' => 'guides', 'href' => '/admin_guides.php', 'label' => 'Vodiči'],
    ['id' => 'settings', 'href' => '/admin_settings.php', 'label' => 'Podešavanja'],
    ['id' => 'widget', 'href' => '/admin_widget.php', 'label' => 'Widget kodovi'],
    ['id' => 'new-ad', 'href' => '/ad_form.php', 'label' => '+ Novi oglas'],
];
?>
<nav class="admin-mobile-nav" aria-label="Admin meni">
    <?php foreach ($adminLinks as $link): ?>
        <a href="<?= h($link['href']) ?>" class="<?= $adminPage === $link['id'] ? 'active' : '' ?>"><?= h($link['label']) ?></a>
    <?php endforeach; ?>
    <a href="/index.php">← Sajt</a>
</nav>
<aside class="admin-sidebar">
    <div class="admin-sidebar-head">Admin panel</div>
    <nav class="admin-sidebar-nav">
        <?php foreach ($adminLinks as $link): ?>
            <a href="<?= h($link['href']) ?>" class="<?= $adminPage === $link['id'] ? 'active' : '' ?>"><?= h($link['label']) ?></a>
        <?php endforeach; ?>
        <a href="/poruke.php">Poruke</a>
        <a href="/index.php">← Nazad na sajt</a>
    </nav>
</aside>
