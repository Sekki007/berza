<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_reports.php');
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    $note = trim((string)($_POST['admin_note'] ?? ''));

    if ($reportId > 0 && in_array($action, ['resolve', 'dismiss', 'reopen'], true)) {
        $status = match ($action) {
            'resolve' => 'resolved',
            'dismiss' => 'dismissed',
            default => 'open',
        };
        if (updateReportStatus($reportId, $status, $note)) {
            setFlash('success', 'Prijava je ažurirana.');
        } else {
            setFlash('danger', 'Ažuriranje nije uspelo.');
        }
    }

    // Quick actions from report
    $blockUserId = (int)($_POST['block_user_id'] ?? 0);
    if ($blockUserId > 0 && ($_POST['action'] ?? '') === 'block_from_report') {
        if (setUserBlocked($blockUserId, true, 'Blokiran zbog prijave #' . (int)($_POST['report_id'] ?? 0))) {
            if ($reportId > 0) {
                updateReportStatus($reportId, 'resolved', 'Korisnik blokiran.');
            }
            setFlash('success', 'Korisnik je blokiran i prijava zatvorena.');
        } else {
            setFlash('danger', 'Blokiranje nije uspelo.');
        }
    }

    $deactivateAdId = (int)($_POST['deactivate_ad_id'] ?? 0);
    if ($deactivateAdId > 0 && ($_POST['action'] ?? '') === 'deactivate_ad') {
        $ad = getAdById($deactivateAdId);
        if ($ad) {
            $ad['is_active'] = 0;
            saveAd($ad, $deactivateAdId);
            if ($reportId > 0) {
                updateReportStatus($reportId, 'resolved', 'Oglas deaktiviran.');
            }
            setFlash('success', 'Oglas je deaktiviran.');
        }
    }

    header('Location: /admin_reports.php');
    exit;
}

$filter = trim((string)($_GET['status'] ?? 'open'));
$all = getAllReports();
$reports = $filter === 'all'
    ? $all
    : array_values(array_filter($all, static fn($r) => ($r['status'] ?? 'open') === $filter));

$openCount = getOpenReportsCount();

$pageTitle = 'Prijave — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'reports';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Prijave</div>
        <div class="inbox-head" style="border:none;padding:0;margin-bottom:12px;">
            <h2 style="font-size:18px;margin:0;">Prijave korisnika</h2>
            <?php if ($openCount > 0): ?>
                <span class="inbox-unread-label"><?= $openCount ?> otvorenih</span>
            <?php endif; ?>
        </div>

        <div class="admin-tabs" style="margin-bottom:12px;">
            <a href="?status=open" class="<?= $filter === 'open' ? 'active' : '' ?>">Otvorene</a>
            <a href="?status=resolved" class="<?= $filter === 'resolved' ? 'active' : '' ?>">Rešene</a>
            <a href="?status=dismissed" class="<?= $filter === 'dismissed' ? 'active' : '' ?>">Odbačene</a>
            <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">Sve</a>
        </div>

        <?php if (!$reports): ?>
            <div class="form-card"><p style="color:var(--text-muted);">Nema prijava za ovaj filter.</p></div>
        <?php endif; ?>

        <?php foreach ($reports as $report): ?>
            <?php
            $rid = (int)$report['id'];
            $status = (string)($report['status'] ?? 'open');
            $reasonKey = (string)($report['reason'] ?? 'other');
            $reasonLabel = reportReasons()[$reasonKey] ?? $reasonKey;
            $targetUser = !empty($report['target_user_id']) ? findUserById((int)$report['target_user_id']) : null;
            $targetAd = !empty($report['target_ad_id']) ? getAdById((int)$report['target_ad_id']) : null;
            ?>
            <div class="form-card report-card report-<?= h($status) ?>">
                <div class="report-card-head">
                    <div>
                        <strong>#<?= $rid ?></strong>
                        <span class="vote-tag <?= $status === 'open' ? 'vote-tag-neg' : 'vote-tag-pos' ?>"><?= h($status) ?></span>
                        <span class="tag tag-gray"><?= ($report['type'] ?? '') === 'ad' ? 'Oglas' : 'Korisnik' ?></span>
                    </div>
                    <span style="font-size:12px;color:var(--text-light);"><?= h((string)$report['created_at']) ?></span>
                </div>

                <div class="report-meta">
                    <div><strong>Razlog:</strong> <?= h($reasonLabel) ?></div>
                    <div><strong>Prijavio:</strong> <?= h((string)($report['from_name'] ?? 'Anonimno')) ?><?php if (!empty($report['from_user_id'])): ?> (ID <?= (int)$report['from_user_id'] ?>)<?php endif; ?></div>
                    <?php if ($targetAd): ?>
                        <div><strong>Oglas:</strong> <a href="/oglas.php?id=<?= (int)$targetAd['id'] ?>"><?= h((string)$targetAd['title']) ?></a></div>
                    <?php endif; ?>
                    <?php if ($targetUser): ?>
                        <div><strong>Korisnik:</strong> <?= h((string)$targetUser['full_name']) ?> (@<?= h((string)$targetUser['username']) ?>)
                            · <a href="<?= h(shopUrl((string)$targetUser['username'])) ?>">Izlog</a>
                        </div>
                    <?php elseif (!empty($report['target_user_id'])): ?>
                        <div><strong>Korisnik ID:</strong> <?= (int)$report['target_user_id'] ?> (obrisan?)</div>
                    <?php endif; ?>
                    <?php if (!empty($report['details'])): ?>
                        <div class="report-details"><strong>Opis:</strong> <?= nl2br(h((string)$report['details'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($report['admin_note'])): ?>
                        <div class="report-details"><strong>Admin beleška:</strong> <?= h((string)$report['admin_note']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="admin-actions" style="margin-top:12px;">
                    <?php if ($status === 'open'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?= $rid ?>">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="admin_note" value="Rešeno">
                            <button class="btn-sm btn-sm-primary" type="submit">Označi rešeno</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?= $rid ?>">
                            <input type="hidden" name="action" value="dismiss">
                            <input type="hidden" name="admin_note" value="Odbačeno">
                            <button class="btn-sm" type="submit">Odbaci</button>
                        </form>
                        <?php if (!empty($report['target_user_id'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Blokirati prijavljenog korisnika?');">
                                <input type="hidden" name="report_id" value="<?= $rid ?>">
                                <input type="hidden" name="block_user_id" value="<?= (int)$report['target_user_id'] ?>">
                                <input type="hidden" name="action" value="block_from_report">
                                <button class="btn-sm btn-sm-danger" type="submit">Blokiraj korisnika</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($report['target_ad_id'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deaktivirati oglas?');">
                                <input type="hidden" name="report_id" value="<?= $rid ?>">
                                <input type="hidden" name="deactivate_ad_id" value="<?= (int)$report['target_ad_id'] ?>">
                                <input type="hidden" name="action" value="deactivate_ad">
                                <button class="btn-sm btn-sm-danger" type="submit">Deaktiviraj oglas</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?= $rid ?>">
                            <input type="hidden" name="action" value="reopen">
                            <button class="btn-sm" type="submit">Ponovo otvori</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
