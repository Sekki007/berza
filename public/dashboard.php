<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$stats = getDashboardStats();

$pageTitle = 'Dashboard — TelefonBerza';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'dashboard';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Admin panel</div>

        <h2 style="font-size:18px;margin-bottom:12px;">Dashboard</h2>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">Ukupno oglasa</div><div class="value"><?= (int)$stats['adsTotal'] ?></div></div>
            <div class="stat-card"><div class="label">Aktivni</div><div class="value"><?= (int)$stats['activeTotal'] ?></div></div>
            <div class="stat-card"><div class="label">Otvorene prijave</div><div class="value" style="color:<?= (int)$stats['openReports'] > 0 ? '#b42318' : 'inherit' ?>;"><?= (int)$stats['openReports'] ?></div></div>
            <div class="stat-card"><div class="label">Blokirani</div><div class="value"><?= (int)$stats['blockedUsers'] ?></div></div>
            <div class="stat-card"><div class="label">Korisnici</div><div class="value"><?= (int)$stats['usersTotal'] ?></div></div>
            <div class="stat-card"><div class="label">Poruke</div><div class="value"><?= (int)$stats['messagesTotal'] ?></div></div>
            <div class="stat-card"><div class="label">Telefoni / Delovi / Servisi</div><div class="value" style="font-size:16px;"><?= (int)($stats['byType']['telefon'] ?? 0) ?> / <?= (int)($stats['byType']['delovi'] ?? 0) ?> / <?= (int)($stats['byType']['servis'] ?? 0) ?></div></div>
            <div class="stat-card"><div class="label">Prosečna cena</div><div class="value" style="font-size:16px;"><?= formatPrice((float)$stats['avgPrice']) ?></div></div>
        </div>

        <div class="account-menu" style="margin-bottom:12px;">
            <a href="/admin_reports.php">Prijave<?= (int)$stats['openReports'] > 0 ? ' (' . (int)$stats['openReports'] . ' otvorenih)' : '' ?></a>
            <a href="/admin_users.php">Upravljanje korisnicima</a>
            <a href="/ads.php">Upravljanje oglasima</a>
            <a href="/admin_settings.php">Podešavanja sajta</a>
        </div>

        <div class="form-card table-scroll" style="margin-top:12px;">
            <h2 style="padding:16px 16px 0;">Poslednji oglasi</h2>
            <table class="admin-table">
                <thead>
                <tr><th>Naziv</th><th>Tip</th><th>Cena</th><th>Grad</th><th>Datum</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stats['latestAds'] as $ad): ?>
                    <tr>
                        <td><a href="/oglas.php?id=<?= (int)$ad['id'] ?>"><?= h((string)$ad['title']) ?></a></td>
                        <td><?= h(adCategoryLabel($ad)) ?></td>
                        <td><?= h(formatAdPrice($ad)) ?></td>
                        <td><?= h((string)$ad['location']) ?></td>
                        <td><?= h((string)$ad['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
