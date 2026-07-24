<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /nalog.php');
    exit;
}

$userId = (int)($_SESSION['pending_password_reset_user_id'] ?? 0);
$user = $userId > 0 ? findUserById($userId) : null;

if (!$user) {
    setFlash('danger', 'Prvo zatraži SMS kod za reset lozinke.');
    header('Location: /forgot-password.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'reset'));

    if ($action === 'resend') {
        $result = sendUserOtp($userId, 'password_reset');
        if (!empty($result['ok'])) {
            setFlash('success', 'Novi SMS kod je poslat.');
            header('Location: /reset-password.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'SMS nije poslat.');
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 6) {
            $error = 'Lozinka mora imati najmanje 6 karaktera.';
        } elseif ($password !== $password2) {
            $error = 'Lozinke se ne poklapaju.';
        } else {
            $verified = verifyUserOtp($userId, 'password_reset', $code);
            if (empty($verified['ok'])) {
                $error = (string)($verified['error'] ?? 'Kod nije važeći.');
            } elseif (!updateUserPassword($userId, $password)) {
                $error = 'Lozinka nije sačuvana.';
            } else {
                clearUserOtp($userId);
                unset($_SESSION['pending_password_reset_user_id']);
                setFlash('success', 'Lozinka je promenjena. Prijavi se novom lozinkom.');
                header('Location: /login.php');
                exit;
            }
        }
    }
}

$phoneDisplay = (string)($user['phone'] ?? '');

$pageTitle = 'Nova lozinka — TelefonBerza';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Nova lozinka</div>
        <div class="form-card">
            <h2>Nova lozinka</h2>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Unesi SMS kod poslat na <strong><?= h($phoneDisplay) ?></strong> i izaberi novu lozinku.
            </p>

            <?php if ($error !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="reset">
                <div class="form-group">
                    <label>SMS kod</label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="123456" autocomplete="one-time-code">
                </div>
                <div class="form-group">
                    <label>Nova lozinka</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Ponovi lozinku</label>
                    <input type="password" name="password_confirm" required minlength="6">
                </div>
                <button class="btn-call" type="submit">Sačuvaj lozinku</button>
            </form>
            <form method="POST" style="margin-top:12px;">
                <input type="hidden" name="action" value="resend">
                <button class="btn-sm btn-sm-primary" type="submit">Pošalji kod ponovo</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                <a href="/login.php">Nazad na prijavu</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
