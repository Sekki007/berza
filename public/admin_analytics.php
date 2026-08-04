<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$force = isset($_GET['refresh']) && (string)$_GET['refresh'] === '1';
$stats = gaFetchAdminStats($force);
$realtime = gaAnalyticsConfigured() ? gaFetchRealtime($force) : ['ok' => false];

$pageTitle = 'Posete (Google Analytics) — KupiTelefon';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'analytics';

$summary = is_array($stats['summary'] ?? null) ? $stats['summary'] : [];
$daily = is_array($stats['daily'] ?? null) ? $stats['daily'] : [];
$pages = is_array($stats['pages'] ?? null) ? $stats['pages'] : [];
$devices = is_array($stats['devices'] ?? null) ? $stats['devices'] : [];
$rtPages = is_array($realtime['pages'] ?? null) ? $realtime['pages'] : [];
$rtDevices = is_array($realtime['devices'] ?? null) ? $realtime['devices'] : [];
$maxDaily = 1;
foreach ($daily as $d) {
    $maxDaily = max($maxDaily, (int)($d['users'] ?? 0), (int)($d['pageviews'] ?? 0));
}

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <a href="/dashboard.php">Admin</a> › Posete</div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
            <h2 style="font-size:18px;margin:0;">Posete sajta (Google Analytics)</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn-outline" href="/admin_analytics.php?refresh=1">Osveži podatke</a>
                <a class="btn-outline" href="/admin_settings.php?tab=marketing" target="_blank" rel="noopener">Podešavanja GA</a>
                <?php if (googleTagGa4Id() !== ''): ?>
                    <a class="btn-outline" href="https://analytics.google.com/" target="_blank" rel="noopener">Otvori GA4</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($stats['ok'])): ?>
            <div class="form-card" style="padding:16px;">
                <p style="margin:0 0 10px;color:#b45309;"><strong>Statistika još nije dostupna.</strong></p>
                <p style="margin:0 0 10px;color:var(--text-muted);"><?= h((string)($stats['error'] ?? 'Nepoznata greška')) ?></p>
                <ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.6;color:var(--text-muted);">
                    <li>Uključi Google tag i Measurement ID (<code>G-...</code>) — već meri posete na sajtu.</li>
                    <li>U Google Cloud Console uključi <strong>Google Analytics Data API</strong>.</li>
                    <li>Service account (isti kao FCM ili poseban <code>GA_SERVICE_ACCOUNT_JSON</code>) dodaj u GA4 → Admin → Property access management kao <strong>Viewer</strong>.</li>
                    <li>U <a href="/admin_settings.php?tab=marketing">Podešavanja → Marketing</a> unesi <strong>GA4 Property ID</strong> (samo brojevi, ne G- kod).</li>
                </ol>
                <p class="form-hint" style="margin-top:12px;">
                    Status: Property ID
                    <?= ga4PropertyId() !== '' ? '<strong style="color:var(--kp-green-dark);">ok</strong>' : '<strong style="color:#b45309;">nedostaje</strong>' ?>
                    · Service account
                    <?= gaServiceAccount() !== null ? '<strong style="color:var(--kp-green-dark);">ok</strong>' : '<strong style="color:#b45309;">nedostaje</strong>' ?>
                    · Tag merenje
                    <?= googleTagEnabled() && googleTagGa4Id() !== '' ? '<strong style="color:var(--kp-green-dark);">uključeno</strong>' : '<strong style="color:#b45309;">isključeno</strong>' ?>
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($realtime['ok'])): ?>
            <div class="form-card" style="margin-bottom:14px;padding:16px;border-left:4px solid var(--kp-green, #1a7f4b);">
                <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;">
                    <div>
                        <div class="label" style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Uživo na sajtu (poslednjih ~30 min)</div>
                        <div style="font-size:36px;font-weight:700;line-height:1;color:var(--kp-green-dark, #146c3d);">
                            <?= (int)($realtime['active_users'] ?? 0) ?>
                        </div>
                        <div style="font-size:13px;color:var(--text-muted);margin-top:6px;">aktivnih korisnika</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);">
                        realtime <?= h((string)($realtime['cached_at'] ?? '')) ?>
                        <?= !empty($realtime['from_cache']) ? '(keš ~45s)' : '' ?>
                        · <a href="/admin_analytics.php?refresh=1">osveži</a>
                    </div>
                </div>
                <?php if ($rtPages || $rtDevices): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
                    <?php if ($rtPages): ?>
                    <div>
                        <strong style="font-size:13px;">Stranice sada</strong>
                        <ul style="margin:8px 0 0;padding-left:16px;font-size:12px;color:var(--text-muted);line-height:1.5;">
                            <?php foreach ($rtPages as $rp): ?>
                                <li><?= h((string)$rp['page']) ?> — <?= (int)$rp['users'] ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if ($rtDevices): ?>
                    <div>
                        <strong style="font-size:13px;">Uređaji sada</strong>
                        <ul style="margin:8px 0 0;padding-left:16px;font-size:12px;color:var(--text-muted);line-height:1.5;">
                            <?php foreach ($rtDevices as $rd): ?>
                                <li><?= h(ucfirst((string)$rd['device'])) ?> — <?= (int)$rd['users'] ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif (!empty($realtime['error'])): ?>
            <p class="form-hint" style="margin-bottom:12px;color:#b45309;">Realtime: <?= h((string)$realtime['error']) ?></p>
            <?php endif; ?>

            <p class="form-hint" style="margin-bottom:12px;">
                Property <?= h((string)($stats['property_id'] ?? '')) ?>
                <?php if (!empty($stats['measurement_id'])): ?>
                    · <?= h((string)$stats['measurement_id']) ?>
                <?php endif; ?>
                · ažurirano <?= h((string)($stats['cached_at'] ?? '')) ?>
                <?= !empty($stats['from_cache']) ? '(keš 15 min)' : '(sveže)' ?>
            </p>

            <?php
            $periods = [
                'today' => 'Danas',
                'd7' => '7 dana',
                'd30' => '30 dana',
            ];
            foreach ($periods as $key => $label):
                $row = is_array($summary[$key] ?? null) ? $summary[$key] : [];
                ?>
                <h3 style="font-size:15px;margin:16px 0 8px;"><?= h($label) ?></h3>
                <div class="stats-grid">
                    <div class="stat-card"><div class="label">Korisnici</div><div class="value"><?= (int)($row['users'] ?? 0) ?></div></div>
                    <div class="stat-card"><div class="label">Sesije</div><div class="value"><?= (int)($row['sessions'] ?? 0) ?></div></div>
                    <div class="stat-card"><div class="label">Pregledi</div><div class="value"><?= (int)($row['pageviews'] ?? 0) ?></div></div>
                    <div class="stat-card"><div class="label">Bounce rate</div><div class="value" style="font-size:16px;"><?= h((string)($row['bounce_rate'] ?? 0)) ?>%</div></div>
                    <div class="stat-card"><div class="label">Prosečna sesija</div><div class="value" style="font-size:16px;"><?= h(gaFormatDuration((int)($row['avg_session_sec'] ?? 0))) ?></div></div>
                </div>
            <?php endforeach; ?>

            <div class="form-card" style="margin-top:16px;padding:16px;">
                <h3 style="font-size:15px;margin:0 0 12px;">Korisnici po danu (30 dana)</h3>
                <?php if (!$daily): ?>
                    <p style="color:var(--text-muted);margin:0;">Nema dnevnih podataka još.</p>
                <?php else: ?>
                    <div class="ga-chart" style="display:flex;align-items:flex-end;gap:3px;height:140px;overflow-x:auto;padding-bottom:4px;">
                        <?php foreach ($daily as $d):
                            $users = (int)($d['users'] ?? 0);
                            $hPct = max(4, (int)round(($users / $maxDaily) * 100));
                            ?>
                            <div title="<?= h(($d['label'] ?? '') . ': ' . $users . ' korisnika, ' . (int)($d['pageviews'] ?? 0) . ' pregleda') ?>"
                                 style="flex:1 0 8px;min-width:8px;max-width:18px;height:<?= $hPct ?>%;background:var(--kp-green, #1a7f4b);border-radius:3px 3px 0 0;opacity:.85;"></div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-top:6px;">
                        <span><?= h((string)($daily[0]['label'] ?? '')) ?></span>
                        <span><?= h((string)($daily[count($daily) - 1]['label'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <div class="form-card table-scroll" style="margin:0;">
                    <h3 style="padding:16px 16px 0;font-size:15px;">Top stranice (30 dana)</h3>
                    <table class="admin-table">
                        <thead><tr><th>Putanja</th><th>Pregledi</th><th>Korisnici</th></tr></thead>
                        <tbody>
                        <?php if (!$pages): ?>
                            <tr><td colspan="3" style="color:var(--text-muted);">Nema podataka</td></tr>
                        <?php else: ?>
                            <?php foreach ($pages as $p): ?>
                                <tr>
                                    <td style="font-size:12px;word-break:break-all;"><?= h((string)$p['path']) ?></td>
                                    <td><?= (int)$p['pageviews'] ?></td>
                                    <td><?= (int)$p['users'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-card table-scroll" style="margin:0;">
                    <h3 style="padding:16px 16px 0;font-size:15px;">Uređaji (30 dana)</h3>
                    <table class="admin-table">
                        <thead><tr><th>Uređaj</th><th>Korisnici</th><th>Sesije</th></tr></thead>
                        <tbody>
                        <?php if (!$devices): ?>
                            <tr><td colspan="3" style="color:var(--text-muted);">Nema podataka</td></tr>
                        <?php else: ?>
                            <?php foreach ($devices as $dev): ?>
                                <tr>
                                    <td><?= h(ucfirst((string)$dev['device'])) ?></td>
                                    <td><?= (int)$dev['users'] ?></td>
                                    <td><?= (int)$dev['sessions'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <style>
                @media (max-width: 800px) {
                    .content > div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
                }
            </style>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
