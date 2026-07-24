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
    } elseif ($action === 'approve_business') {
        if (setBusinessVerification($userId, true)) {
            $u = findUserById($userId);
            if ($u) {
                notifyUser($userId, 'business_approved', 'Firma potvrđena', 'Admin je potvrdio tvoj nalog kao ' . businessKindLabel(userBusinessKind($u)) . '. Bedž je aktivan.', '/nalog.php?tab=profil', false);
            }
            setFlash('success', 'Firma je potvrđena. Bedž je aktivan.');
        } else {
            setFlash('danger', 'Potvrda nije uspela. Proveri PIB i vrstu naloga.');
        }
    } elseif ($action === 'reject_business') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (setBusinessVerification($userId, false, $reason)) {
            notifyUser($userId, 'business_rejected', 'Zahtev za firmu odbijen', $reason !== '' ? $reason : 'Admin je odbio zahtev za bedž Prodavnica/Servis.', '/nalog.php?tab=profil', false);
            setFlash('success', 'Zahtev za firmu je odbijen.');
        } else {
            setFlash('danger', 'Odbijanje nije uspelo.');
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
usort($users, static function ($a, $b) {
    $ap = userBusinessStatus($a) === 'pending' ? 1 : 0;
    $bp = userBusinessStatus($b) === 'pending' ? 1 : 0;
    if ($ap !== $bp) {
        return $bp <=> $ap;
    }
    return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
});

$pendingBusiness = count(array_filter($users, static fn($u) => userBusinessStatus($u) === 'pending'));

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
        <?php if ($pendingBusiness > 0): ?>
            <p class="form-hint" style="margin-bottom:12px;color:#b45309;">
                Čeka potvrdu firme: <strong><?= (int)$pendingBusiness ?></strong>
            </p>
        <?php endif; ?>

        <div class="form-card table-scroll" style="padding:0;">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Korisnik</th>
                    <th>Telefon</th>
                    <th>Firma</th>
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
                    $bizStatus = userBusinessStatus($u);
                    ?>
                    <tr class="<?= $blocked ? 'row-blocked' : '' ?>" <?= $bizStatus === 'pending' ? 'style="background:#fff8e1;"' : '' ?>>
                        <td>#<?= $uid ?></td>
                        <td>
                            <strong><?= h((string)$u['full_name']) ?></strong><br>
                            <span style="color:var(--text-muted);font-size:12px;">@<?= h((string)$u['username']) ?></span>
                            <?php if ($isAdminUser): ?><span class="vote-tag vote-tag-pos" style="margin-left:6px;">Admin</span><?php endif; ?>
                            <?php if (!empty($u['shop_name'])): ?><div style="font-size:12px;margin-top:2px;"><a href="<?= h(shopUrl((string)$u['username'])) ?>"><?= h((string)$u['shop_name']) ?></a></div><?php endif; ?>
                            <?= renderSellerBadges($u) ?>
                        </td>
                        <td><?= h((string)($u['phone'] ?? '—')) ?></td>
                        <td style="font-size:12px;">
                            <?php if (userAccountType($u) === 'business'): ?>
                                <div><?= h(businessKindLabel(userBusinessKind($u))) ?></div>
                                <div>PIB: <?= h((string)($u['pib'] ?? '—')) ?></div>
                                <?php if ($bizStatus === 'pending'): ?>
                                    <span class="vote-tag vote-tag-neg">Čeka potvrdu</span>
                                <?php elseif ($bizStatus === 'approved'): ?>
                                    <span class="vote-tag vote-tag-pos">Potvrđena</span>
                                <?php elseif ($bizStatus === 'rejected'): ?>
                                    <span class="vote-tag vote-tag-neg">Odbijena</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">Bez zahteva</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-light);">Fizičko lice</span>
                            <?php endif; ?>
                        </td>
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
                                    <a class="btn-sm btn-sm-primary" href="/admin_user_edit.php?id=<?= $uid ?>">Izmeni</a>
                                    <a class="btn-sm" href="<?= h(shopUrl((string)$u['username'])) ?>">Izlog</a>
                                    <?php if ($bizStatus === 'pending' || $bizStatus === 'rejected'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="approve_business">
                                            <button class="btn-sm btn-sm-primary" type="submit">Potvrdi firmu</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($bizStatus === 'pending' || $bizStatus === 'approved'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Odbiti zahtev za firmu?');">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="reject_business">
                                            <input type="hidden" name="reason" value="Zahtev odbijen od administratora.">
                                            <button class="btn-sm" type="submit"><?= $bizStatus === 'approved' ? 'Ukloni firmu' : 'Odbij firmu' ?></button>
                                        </form>
                                    <?php endif; ?>
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
                                <div class="admin-actions">
                                    <a class="btn-sm btn-sm-primary" href="/admin_user_edit.php?id=<?= $uid ?>">Izmeni</a>
                                    <span style="font-size:12px;color:var(--text-light);">Zaštićen</span>
                                </div>
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
