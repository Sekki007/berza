<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
    requireCsrf('/admin_users.php');
    if ($action === 'block') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (setUserBlocked($userId, true, $reason !== '' ? $reason : 'Blokiran od administratora')) {
            setFlash('success', 'Korisnik je blokiran.');
        } else {
            setFlash('danger', 'Korisnik nije blokiran (admin se ne može blokirati).');
        }
    } elseif ($action === 'unblock') {
        if (setUserBlocked($userId, false)) {
            setFlash('success', 'Korisnik je odblokiran.');
        } else {
            setFlash('danger', 'Odblokiranje nije uspelo.');
        }
    } elseif ($action === 'verify_seller') {
        if (setVerifiedSellerFlag($userId, true)) {
            setFlash('success', 'Korisnik je označen kao proveren prodavac.');
        } else {
            setFlash('danger', 'Nije uspelo.');
        }
    } elseif ($action === 'unverify_seller') {
        if (setVerifiedSellerFlag($userId, false)) {
            setFlash('success', 'Bedž proverenog prodavca je uklonjen.');
        } else {
            setFlash('danger', 'Nije uspelo.');
        }
    } elseif ($action === 'delete') {
        if (deleteUserById($userId)) {
            setFlash('success', 'Korisnik je obrisan. Njegovi oglasi su deaktivirani.');
        } else {
            setFlash('danger', 'Brisanje nije uspelo (admin se ne briše).');
        }
    }
    header('Location: /admin_users.php');
    exit;
}

$users = getUsers();
usort($users, static fn($a, $b) => ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0)));

$pageTitle = 'Korisnici — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'users';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Korisnici</div>
        <h2 style="font-size:18px;margin-bottom:12px;">Upravljanje korisnicima (<?= count($users) ?>)</h2>

        <div class="form-card table-scroll" style="padding:0;">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Korisnik</th>
                    <th>Telefon</th>
                    <th>Oglasi</th>
                    <th>Status</th>
                    <th>Registrovan</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uid = (int)$u['id'];
                    $isAdminUser = !empty($u['is_admin']) || ($u['username'] ?? '') === 'admin';
                    $blocked = !empty($u['is_blocked']);
                    ?>
                    <tr class="<?= $blocked ? 'row-blocked' : '' ?>">
                        <td>#<?= $uid ?></td>
                        <td>
                            <strong><?= h((string)$u['full_name']) ?></strong><br>
                            <span style="color:var(--text-muted);font-size:12px;">@<?= h((string)$u['username']) ?></span>
                            <?php if ($isAdminUser): ?><span class="vote-tag vote-tag-pos" style="margin-left:6px;">Admin</span><?php endif; ?>
                            <?php if (!empty($u['shop_name'])): ?><div style="font-size:12px;margin-top:2px;"><a href="<?= h(shopUrl((string)$u['username'])) ?>"><?= h((string)$u['shop_name']) ?></a></div><?php endif; ?>
                            <?= renderVerifiedBadge($u) ?>
                        </td>
                        <td><?= h((string)($u['phone'] ?? '—')) ?></td>
                        <td><?= countUserAds($uid) ?></td>
                        <td>
                            <?php if ($blocked): ?>
                                <span class="vote-tag vote-tag-neg">Blokiran</span>
                                <?php if (!empty($u['blocked_reason'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= h((string)$u['blocked_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="vote-tag vote-tag-pos">Aktivan</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;"><?= h((string)($u['created_at'] ?? '')) ?></td>
                        <td>
                            <?php if (!$isAdminUser): ?>
                                <div class="admin-actions">
                                    <a class="btn-sm" href="<?= h(shopUrl((string)$u['username'])) ?>">Izlog</a>
                                    <?php if (!empty($u['verified_seller'])): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="unverify_seller">
                                            <button class="btn-sm" type="submit">Ukloni Proveren</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="verify_seller">
                                            <button class="btn-sm btn-sm-primary" type="submit">Proveren</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($blocked): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="unblock">
                                            <button class="btn-sm btn-sm-primary" type="submit">Odblokiraj</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Blokirati korisnika?');">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="block">
                                            <input type="hidden" name="reason" value="Blokiran od administratora">
                                            <button class="btn-sm" type="submit">Blokiraj</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Trajno obrisati korisnika? Oglasi će biti deaktivirani.');">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn-sm btn-sm-danger" type="submit">Obriši</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="font-size:12px;color:var(--text-light);">Zaštićen</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
