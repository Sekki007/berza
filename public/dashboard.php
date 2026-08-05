<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$stats = getDashboardStats();

$allUsers = getUsers();
$telegramLinkedUsers = array_values(array_filter($allUsers, static fn($u) => trim((string)($u['telegram_chat_id'] ?? '')) !== ''));
$telegramEnabledUsers = array_values(array_filter($telegramLinkedUsers, static fn($u) => !empty($u['notify_telegram'])));
usort($telegramLinkedUsers, static fn($a, $b) => strcmp((string)($b['telegram_linked_at'] ?? ''), (string)($a['telegram_linked_at'] ?? '')));

$pageTitle = 'Dashboard — KupiTelefon';
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

        <?php
        $gaQuick = gaAnalyticsConfigured() ? gaFetchAdminStats(false) : ['ok' => false];
        $gaLive = gaAnalyticsConfigured() ? gaFetchRealtime(false) : ['ok' => false];
        if (!empty($gaQuick['ok']) && is_array($gaQuick['summary']['today'] ?? null)):
            $gaToday = $gaQuick['summary']['today'];
            $ga7 = $gaQuick['summary']['d7'] ?? [];
            ?>
        <div class="form-card" style="margin-top:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 16px 0;gap:8px;flex-wrap:wrap;">
                <h2 style="font-size:16px;margin:0;">Posete sajta</h2>
                <a href="/admin_analytics.php" style="font-size:13px;">Detaljnije →</a>
            </div>
            <div class="stats-grid" style="padding:12px 16px 16px;gap:8px;">
                <?php if (!empty($gaLive['ok'])): ?>
                <div class="stat-card" style="border-left:3px solid var(--kp-green, #1a7f4b);">
                    <div class="label">Uživo sada</div>
                    <div class="value"><?= (int)($gaLive['active_users'] ?? 0) ?></div>
                </div>
                <?php endif; ?>
                <div class="stat-card"><div class="label">Danas — korisnici</div><div class="value"><?= (int)($gaToday['users'] ?? 0) ?></div></div>
                <div class="stat-card"><div class="label">Danas — pregledi</div><div class="value"><?= (int)($gaToday['pageviews'] ?? 0) ?></div></div>
                <div class="stat-card"><div class="label">7 dana — korisnici</div><div class="value"><?= (int)($ga7['users'] ?? 0) ?></div></div>
                <div class="stat-card"><div class="label">7 dana — sesije</div><div class="value"><?= (int)($ga7['sessions'] ?? 0) ?></div></div>
            </div>
        </div>
        <?php else: ?>
        <div class="account-menu" style="margin-top:12px;margin-bottom:0;">
            <a href="/admin_analytics.php">Posete sajta (Google Analytics)</a>
        </div>
        <?php endif; ?>

        <?php if (imeiCheckConfigured()): ?>
            <?php $imeiAccount = imeiCheckAccountInfo(); ?>
            <div class="form-card" style="margin-top:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 16px 0;gap:8px;flex-wrap:wrap;">
                    <h2 style="font-size:16px;margin:0;">IMEI provera</h2>
                    <a href="/provera-imei" style="font-size:13px;">Otvori stranicu →</a>
                </div>
                <div class="stats-grid" style="padding:12px 16px 16px;gap:8px;">
                    <?php if (!empty($imeiAccount['ok'])): ?>
                        <?php $imeiCredit = (float)($imeiAccount['credit'] ?? 0); ?>
                        <div class="stat-card" style="border-left:3px solid <?= $imeiCredit > 0 ? 'var(--kp-green, #1a7f4b)' : '#c0392b' ?>;">
                            <div class="label">Stanje kredita</div>
                            <div class="value"><?= h((string)($imeiAccount['credit'] ?? '0.00')) ?> <?= h((string)($imeiAccount['currency'] ?? 'USD')) ?></div>
                        </div>
                        <?php if ($imeiCredit <= 0): ?>
                            <div class="stat-card">
                                <div class="label">Status</div>
                                <div class="value" style="font-size:13px;color:#c0392b;">Dopuni kredit — provera ne radi</div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="stat-card">
                            <div class="label">Status</div>
                            <div class="value" style="font-size:13px;color:#c0392b;"><?= h(mb_substr((string)($imeiAccount['detail'] ?? 'Nedostupno'), 0, 90)) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="account-menu" style="margin-bottom:12px;">
            <a href="/admin_reports.php">Prijave<?= (int)$stats['openReports'] > 0 ? ' (' . (int)$stats['openReports'] . ' otvorenih)' : '' ?></a>
            <a href="/admin_users.php">Upravljanje korisnicima</a>
            <a href="/ads.php">Upravljanje oglasima</a>
            <a href="/admin_settings.php">Podešavanja sajta</a>
        </div>

        <?php if (telegramEnabled()): ?>
        <div class="form-card" style="margin-top:12px;">
            <h2 style="padding:16px 16px 0;font-size:16px;">Telegram notifikacije</h2>
            <div class="stats-grid" style="padding:12px 16px 16px;gap:8px;">
                <div class="stat-card">
                    <div class="label">Povezanih naloga</div>
                    <div class="value"><?= count($telegramLinkedUsers) ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Notifikacije uključene</div>
                    <div class="value"><?= count($telegramEnabledUsers) ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Bot</div>
                    <div class="value" style="font-size:13px;">@<?= h(telegramBotUsername()) ?></div>
                </div>
            </div>
            <?php if ($telegramLinkedUsers): ?>
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Korisnik</th>
                                <th>Telegram</th>
                                <th>Chat ID</th>
                                <th>Notif.</th>
                                <th>Poruke</th>
                                <th>Alerti</th>
                                <th>Sistem</th>
                                <th>Povezano</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($telegramLinkedUsers as $tu): ?>
                            <tr>
                                <td><a href="/admin_user_edit.php?id=<?= (int)$tu['id'] ?>"><?= h((string)($tu['full_name'] ?? $tu['username'] ?? '—')) ?></a><br><small style="color:var(--text-muted)">@<?= h((string)$tu['username']) ?></small></td>
                                <td><?= trim((string)($tu['telegram_username'] ?? '')) !== '' ? '@' . h((string)$tu['telegram_username']) : '—' ?></td>
                                <td style="font-size:12px;color:var(--text-muted)"><?= h((string)($tu['telegram_chat_id'] ?? '')) ?></td>
                                <td style="text-align:center"><?= !empty($tu['notify_telegram']) ? '✓' : '—' ?></td>
                                <td style="text-align:center"><?= !array_key_exists('notify_telegram_messages', $tu) || !empty($tu['notify_telegram_messages']) ? '✓' : '—' ?></td>
                                <td style="text-align:center"><?= !array_key_exists('notify_telegram_alerts', $tu) || !empty($tu['notify_telegram_alerts']) ? '✓' : '—' ?></td>
                                <td style="text-align:center"><?= !array_key_exists('notify_telegram_system', $tu) || !empty($tu['notify_telegram_system']) ? '✓' : '—' ?></td>
                                <td style="font-size:12px"><?= h((string)($tu['telegram_linked_at'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="padding:0 16px 16px;color:var(--text-muted)">Još nijedan korisnik nije povezao Telegram nalog.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
