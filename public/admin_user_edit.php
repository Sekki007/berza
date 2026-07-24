<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$userId = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
$user = $userId > 0 ? findUserById($userId) : null;
if (!$user) {
    setFlash('danger', 'Korisnik nije pronađen.');
    header('Location: /admin_users.php');
    exit;
}

$isTargetAdmin = !empty($user['is_admin']) || ($user['username'] ?? '') === 'admin';
$formError = '';
$cities = categoriesConfig()['cities'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_user_edit.php?id=' . $userId);
    $result = adminUpdateUser($userId, [
        'full_name' => $_POST['full_name'] ?? '',
        'username' => $_POST['username'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'shop_name' => $_POST['shop_name'] ?? '',
        'location' => $_POST['location'] ?? '',
        'new_password' => $_POST['new_password'] ?? '',
        'account_type' => $_POST['account_type'] ?? 'private',
        'business_kind' => $_POST['business_kind'] ?? '',
        'pib' => $_POST['pib'] ?? '',
        'business_status' => $_POST['business_status'] ?? 'none',
        'verified_seller' => isset($_POST['verified_seller']),
        'is_blocked' => isset($_POST['is_blocked']),
        'blocked_reason' => $_POST['blocked_reason'] ?? '',
        'phone_verified' => isset($_POST['phone_verified']),
    ]);

    if (!empty($result['ok'])) {
        setFlash('success', 'Korisnik je sačuvan.');
        header('Location: /admin_user_edit.php?id=' . $userId);
        exit;
    }
    $formError = (string)($result['error'] ?? 'Čuvanje nije uspelo.');
    $user = array_merge($user, [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'username' => trim((string)($_POST['username'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'shop_name' => trim((string)($_POST['shop_name'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'account_type' => trim((string)($_POST['account_type'] ?? 'private')),
        'business_kind' => trim((string)($_POST['business_kind'] ?? '')),
        'pib' => trim((string)($_POST['pib'] ?? '')),
        'business_status' => trim((string)($_POST['business_status'] ?? 'none')),
        'verified_seller' => isset($_POST['verified_seller']),
        'is_blocked' => isset($_POST['is_blocked']),
        'blocked_reason' => trim((string)($_POST['blocked_reason'] ?? '')),
        'phone_verified_at' => isset($_POST['phone_verified']) ? date('Y-m-d H:i:s') : null,
    ]);
}

$accountType = userAccountType($user);
$bizStatus = userBusinessStatus($user);
$bizKind = userBusinessKind($user);

$pageTitle = 'Izmena korisnika — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'users';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › <a href="/admin_users.php">Korisnici</a> › Izmena</div>
        <h2 style="font-size:18px;margin-bottom:6px;">Izmena korisnika #<?= (int)$user['id'] ?></h2>
        <p class="form-hint" style="margin-bottom:14px;">
            @<?= h((string)$user['username']) ?>
            · <a href="<?= h(shopUrl((string)$user['username'])) ?>">Izlog</a>
            · oglasa: <?= countUserAds((int)$user['id']) ?>
        </p>

        <?php if ($formError !== ''): ?>
            <p class="form-hint ad-form-error"><?= h($formError) ?></p>
        <?php endif; ?>

        <form method="POST" class="form-card" style="max-width:720px;">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

            <h3 style="margin:0 0 12px;font-size:14px;">Osnovno</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Ime i prezime *</label>
                    <input name="full_name" value="<?= h((string)($user['full_name'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Korisničko ime *</label>
                    <input name="username" value="<?= h((string)($user['username'] ?? '')) ?>" required <?= $isTargetAdmin ? 'readonly' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Telefon</label>
                    <input name="phone" value="<?= h((string)($user['phone'] ?? '')) ?>" placeholder="06x…">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= h((string)($user['email'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Naziv izloga</label>
                    <input name="shop_name" value="<?= h((string)($user['shop_name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Grad</label>
                    <select name="location">
                        <option value="">—</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= h($city) ?>" <?= ($user['location'] ?? '') === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Nova lozinka <span class="form-hint-inline">(ostavi prazno da ne menjaš)</span></label>
                <input type="password" name="new_password" autocomplete="new-password" minlength="6" placeholder="Min. 6 karaktera">
            </div>

            <h3 style="margin:18px 0 12px;font-size:14px;">Firma / PIB</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Tip naloga</label>
                    <select name="account_type" id="admin-account-type">
                        <option value="private" <?= $accountType === 'private' ? 'selected' : '' ?>>Privatno</option>
                        <option value="business" <?= $accountType === 'business' ? 'selected' : '' ?>>Firma</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vrsta firme</label>
                    <select name="business_kind">
                        <option value="">—</option>
                        <option value="shop" <?= $bizKind === 'shop' ? 'selected' : '' ?>>Prodavnica</option>
                        <option value="service" <?= $bizKind === 'service' ? 'selected' : '' ?>>Servis</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>PIB</label>
                    <input name="pib" value="<?= h((string)($user['pib'] ?? '')) ?>" maxlength="9" inputmode="numeric" placeholder="9 cifara">
                </div>
                <div class="form-group">
                    <label>Status firme</label>
                    <select name="business_status">
                        <option value="none" <?= $bizStatus === 'none' ? 'selected' : '' ?>>Nema zahteva</option>
                        <option value="pending" <?= $bizStatus === 'pending' ? 'selected' : '' ?>>Čeka potvrdu</option>
                        <option value="approved" <?= $bizStatus === 'approved' ? 'selected' : '' ?>>Potvrđena</option>
                        <option value="rejected" <?= $bizStatus === 'rejected' ? 'selected' : '' ?>>Odbijena</option>
                    </select>
                </div>
            </div>

            <h3 style="margin:18px 0 12px;font-size:14px;">Status</h3>
            <div class="form-group form-checks">
                <label class="type-chip" style="min-width:auto;flex:none;">
                    <input type="checkbox" name="verified_seller" value="1" <?= !empty($user['verified_seller']) ? 'checked' : '' ?>>
                    Proveren prodavac
                </label>
                <label class="type-chip" style="min-width:auto;flex:none;">
                    <input type="checkbox" name="phone_verified" value="1" <?= !empty($user['phone_verified_at']) ? 'checked' : '' ?>>
                    Telefon verifikovan
                </label>
                <?php if (!$isTargetAdmin): ?>
                    <label class="type-chip" style="min-width:auto;flex:none;">
                        <input type="checkbox" name="is_blocked" value="1" <?= !empty($user['is_blocked']) ? 'checked' : '' ?>>
                        Blokiran
                    </label>
                <?php else: ?>
                    <span class="form-hint">Admin nalog je zaštićen od blokiranja.</span>
                <?php endif; ?>
            </div>
            <?php if (!$isTargetAdmin): ?>
                <div class="form-group">
                    <label>Razlog blokade / odbijanja</label>
                    <input name="blocked_reason" value="<?= h((string)($user['blocked_reason'] ?? $user['business_reject_reason'] ?? '')) ?>">
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <button class="btn-call" type="submit" style="width:auto;min-width:160px;">Sačuvaj izmene</button>
                <a class="btn-message" href="/admin_users.php" style="width:auto;min-width:120px;">Nazad</a>
            </div>
        </form>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
