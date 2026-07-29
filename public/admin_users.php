<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));
$filterFirma = trim((string)($_GET['firma'] ?? 'all'));
$filterVerified = trim((string)($_GET['verified'] ?? 'all'));
$filterKind = trim((string)($_GET['kind'] ?? 'all'));

if (!in_array($filterStatus, ['all', 'active', 'blocked'], true)) {
    $filterStatus = 'all';
}
if (!in_array($filterFirma, ['all', 'private', 'business', 'pending', 'approved', 'rejected'], true)) {
    $filterFirma = 'all';
}
if (!in_array($filterVerified, ['all', 'yes', 'no'], true)) {
    $filterVerified = 'all';
}
if (!in_array($filterKind, ['all', 'service', 'shop', 'both'], true)) {
    $filterKind = 'all';
}

function adminUsersFilterQuery(array $overrides = []): string
{
    $params = [
        'q' => $overrides['q'] ?? trim((string)($_GET['q'] ?? '')),
        'status' => $overrides['status'] ?? trim((string)($_GET['status'] ?? 'all')),
        'firma' => $overrides['firma'] ?? trim((string)($_GET['firma'] ?? 'all')),
        'verified' => $overrides['verified'] ?? trim((string)($_GET['verified'] ?? 'all')),
        'kind' => $overrides['kind'] ?? trim((string)($_GET['kind'] ?? 'all')),
    ];
    foreach ($params as $k => $v) {
        if ($v === '' || $v === 'all') {
            unset($params[$k]);
        }
    }
    if ($params === []) {
        return '/admin_users.php';
    }
    return '/admin_users.php?' . http_build_query($params);
}

function adminUsersReturnUrl(): string
{
    $return = trim((string)($_POST['return'] ?? ''));
    if ($return !== '' && str_starts_with($return, '/admin_users.php')) {
        return $return;
    }
    return '/admin_users.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
    requireCsrf(adminUsersReturnUrl());
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
            notifyUser($userId, 'business_rejected', 'Zahtev za firmu odbijen', $reason !== '' ? $reason : 'Admin je odbio zahtev za bedž firme.', '/nalog.php?tab=profil', false);
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
    header('Location: ' . adminUsersReturnUrl());
    exit;
}

$allUsers = getUsers();
$pendingBusiness = count(array_filter($allUsers, static fn($u) => userBusinessStatus($u) === 'pending'));
$approvedBusiness = count(array_filter($allUsers, static fn($u) => userBusinessStatus($u) === 'approved'));
$blockedCount = count(array_filter($allUsers, static fn($u) => !empty($u['is_blocked'])));

$users = array_values(array_filter($allUsers, static function (array $u) use ($q, $filterStatus, $filterFirma, $filterVerified, $filterKind): bool {
    if ($filterStatus === 'active' && !empty($u['is_blocked'])) {
        return false;
    }
    if ($filterStatus === 'blocked' && empty($u['is_blocked'])) {
        return false;
    }

    $bizStatus = userBusinessStatus($u);
    $accountType = userAccountType($u);
    if ($filterFirma === 'private' && $accountType === 'business') {
        return false;
    }
    if ($filterFirma === 'business' && $accountType !== 'business') {
        return false;
    }
    if (in_array($filterFirma, ['pending', 'approved', 'rejected'], true) && $bizStatus !== $filterFirma) {
        return false;
    }

    if ($filterVerified === 'yes' && empty($u['verified_seller'])) {
        return false;
    }
    if ($filterVerified === 'no' && !empty($u['verified_seller'])) {
        return false;
    }

    if ($filterKind !== 'all') {
        if ($accountType !== 'business' || userBusinessKind($u) !== $filterKind) {
            return false;
        }
    }

    if ($q === '') {
        return true;
    }

    $hay = mb_strtolower(implode(' ', [
        (string)($u['id'] ?? ''),
        (string)($u['full_name'] ?? ''),
        (string)($u['username'] ?? ''),
        (string)($u['phone'] ?? ''),
        (string)($u['email'] ?? ''),
        (string)($u['shop_name'] ?? ''),
        (string)($u['shop_slug'] ?? ''),
        (string)($u['pib'] ?? ''),
        (string)($u['location'] ?? ''),
    ]), 'UTF-8');

    $needle = mb_strtolower($q, 'UTF-8');
    return $needle !== '' && mb_strpos($hay, $needle) !== false;
}));

usort($users, static function ($a, $b) {
    $ap = userBusinessStatus($a) === 'pending' ? 1 : 0;
    $bp = userBusinessStatus($b) === 'pending' ? 1 : 0;
    if ($ap !== $bp) {
        return $bp <=> $ap;
    }
    return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
});

$returnUrl = adminUsersFilterQuery([
    'q' => $q,
    'status' => $filterStatus,
    'firma' => $filterFirma,
    'verified' => $filterVerified,
    'kind' => $filterKind,
]);
$hasFilters = $q !== '' || $filterStatus !== 'all' || $filterFirma !== 'all' || $filterVerified !== 'all' || $filterKind !== 'all';

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
        <h2 style="font-size:18px;margin-bottom:12px;">
            Upravljanje korisnicima
            <span style="color:var(--text-muted);font-weight:normal;">
                (<?= count($users) ?><?= $hasFilters ? ' / ' . count($allUsers) : '' ?>)
            </span>
        </h2>

        <div class="admin-user-stats">
            <a class="admin-user-stat<?= $filterFirma === 'pending' ? ' is-on' : '' ?>" href="<?= h(adminUsersFilterQuery(['firma' => 'pending', 'status' => 'all', 'verified' => 'all', 'kind' => 'all', 'q' => ''])) ?>">
                Čeka firmu <strong><?= (int)$pendingBusiness ?></strong>
            </a>
            <a class="admin-user-stat<?= $filterFirma === 'approved' ? ' is-on' : '' ?>" href="<?= h(adminUsersFilterQuery(['firma' => 'approved', 'status' => 'all', 'verified' => 'all', 'kind' => 'all', 'q' => ''])) ?>">
                Potvrđene firme <strong><?= (int)$approvedBusiness ?></strong>
            </a>
            <a class="admin-user-stat<?= $filterStatus === 'blocked' ? ' is-on' : '' ?>" href="<?= h(adminUsersFilterQuery(['status' => 'blocked', 'firma' => 'all', 'verified' => 'all', 'kind' => 'all', 'q' => ''])) ?>">
                Blokirani <strong><?= (int)$blockedCount ?></strong>
            </a>
            <?php if ($hasFilters): ?>
                <a class="admin-user-stat" href="/admin_users.php">Prikaži sve</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="/admin_users.php" class="form-card admin-user-filters">
            <div class="admin-user-filters-row">
                <div class="form-group" style="margin:0;flex:1 1 220px;">
                    <label for="admin-user-q">Pretraga</label>
                    <input type="search" name="q" id="admin-user-q" value="<?= h($q) ?>" placeholder="Ime, @user, telefon, PIB, izlog, email, ID…" autocomplete="off">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-user-status">Status</label>
                    <select name="status" id="admin-user-status">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Svi</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Aktivni</option>
                        <option value="blocked" <?= $filterStatus === 'blocked' ? 'selected' : '' ?>>Blokirani</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-user-firma">Firma</label>
                    <select name="firma" id="admin-user-firma">
                        <option value="all" <?= $filterFirma === 'all' ? 'selected' : '' ?>>Sve</option>
                        <option value="private" <?= $filterFirma === 'private' ? 'selected' : '' ?>>Fizičko lice</option>
                        <option value="business" <?= $filterFirma === 'business' ? 'selected' : '' ?>>Sve firme</option>
                        <option value="pending" <?= $filterFirma === 'pending' ? 'selected' : '' ?>>Čeka potvrdu</option>
                        <option value="approved" <?= $filterFirma === 'approved' ? 'selected' : '' ?>>Potvrđene</option>
                        <option value="rejected" <?= $filterFirma === 'rejected' ? 'selected' : '' ?>>Odbijene</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-user-kind">Vrsta firme</label>
                    <select name="kind" id="admin-user-kind">
                        <option value="all" <?= $filterKind === 'all' ? 'selected' : '' ?>>Sve</option>
                        <option value="shop" <?= $filterKind === 'shop' ? 'selected' : '' ?>>Mobile Shop</option>
                        <option value="service" <?= $filterKind === 'service' ? 'selected' : '' ?>>Servis</option>
                        <option value="both" <?= $filterKind === 'both' ? 'selected' : '' ?>>Servis & Shop</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-user-verified">Proveren</label>
                    <select name="verified" id="admin-user-verified">
                        <option value="all" <?= $filterVerified === 'all' ? 'selected' : '' ?>>Svi</option>
                        <option value="yes" <?= $filterVerified === 'yes' ? 'selected' : '' ?>>Da</option>
                        <option value="no" <?= $filterVerified === 'no' ? 'selected' : '' ?>>Ne</option>
                    </select>
                </div>
                <div class="admin-user-filters-actions">
                    <button class="btn-call" type="submit">Filtriraj</button>
                    <?php if ($hasFilters): ?>
                        <a class="btn-message" href="/admin_users.php">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

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
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="8" style="padding:18px;color:var(--text-muted);">Nema korisnika za ovaj filter.</td>
                    </tr>
                <?php endif; ?>
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
                            <?php if (!empty($u['shop_name'])): ?><div style="font-size:12px;margin-top:2px;"><a href="<?= h(shopUrlForUser($u)) ?>"><?= h((string)$u['shop_name']) ?></a></div><?php endif; ?>
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
                                    <a class="btn-sm" href="<?= h(shopUrlForUser($u)) ?>">Izlog</a>
                                    <?php if ($bizStatus === 'pending' || $bizStatus === 'rejected'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="approve_business">
                                            <button class="btn-sm btn-sm-primary" type="submit">Potvrdi firmu</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($bizStatus === 'pending' || $bizStatus === 'approved'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Odbiti zahtev za firmu?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="reject_business">
                                            <input type="hidden" name="reason" value="Zahtev odbijen od administratora.">
                                            <button class="btn-sm" type="submit"><?= $bizStatus === 'approved' ? 'Ukloni firmu' : 'Odbij firmu' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($u['verified_seller'])): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="unverify_seller">
                                            <button class="btn-sm" type="submit">Ukloni Proveren</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="verify_seller">
                                            <button class="btn-sm btn-sm-primary" type="submit">Proveren</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($blocked): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="unblock">
                                            <button class="btn-sm btn-sm-primary" type="submit">Odblokiraj</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Blokirati korisnika?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="block">
                                            <input type="hidden" name="reason" value="Blokiran od administratora">
                                            <button class="btn-sm" type="submit">Blokiraj</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Trajno obrisati korisnika? Oglasi će biti deaktivirani.');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
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
