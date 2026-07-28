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
    unset($_SESSION['password_reset_verified']);
    setFlash('danger', 'Prvo unesi broj telefona da pošaljemo SMS kod.');
    header('Location: /forgot-password.php');
    exit;
}

$codeVerified = !empty($_SESSION['password_reset_verified']) && canResetPasswordWithOtp($userId);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/reset-password.php');
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'resend') {
        $result = sendUserOtp($userId, 'password_reset', null, 'sms');
        if (!empty($result['ok'])) {
            unset($_SESSION['password_reset_verified']);
            $codeVerified = false;
            setFlash('success', 'Novi SMS kod je poslat.');
            header('Location: /reset-password.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'SMS nije poslat.');
    } elseif ($action === 'send_email') {
        $result = sendUserOtp($userId, 'password_reset', null, 'email');
        if (!empty($result['ok'])) {
            unset($_SESSION['password_reset_verified']);
            $codeVerified = false;
            setFlash('success', 'Kod je poslat na email sa naloga.');
            header('Location: /reset-password.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'Email nije poslat.');
    } elseif ($action === 'verify_code') {
        $code = trim((string)($_POST['code'] ?? ''));
        $verified = verifyUserOtp($userId, 'password_reset', $code);
        if (!empty($verified['ok']) && canResetPasswordWithOtp($userId)) {
            $_SESSION['password_reset_verified'] = 1;
            setFlash('success', 'Kod je potvrđen. Sada unesi novu lozinku.');
            header('Location: /reset-password.php');
            exit;
        }
        $error = (string)($verified['error'] ?? 'Kod nije važeći.');
        $codeVerified = false;
        unset($_SESSION['password_reset_verified']);
    } elseif ($action === 'set_password') {
        if (!$codeVerified) {
            $error = 'Prvo potvrdi SMS kod.';
        } else {
            $password = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password_confirm'] ?? '');

            if (strlen($password) < 6) {
                $error = 'Lozinka mora imati najmanje 6 karaktera.';
            } elseif ($password !== $password2) {
                $error = 'Lozinke se ne poklapaju.';
            } elseif (!updateUserPassword($userId, $password)) {
                $error = 'Lozinka nije sačuvana.';
            } else {
                clearUserOtp($userId);
                unset($_SESSION['pending_password_reset_user_id'], $_SESSION['password_reset_verified']);
                setFlash('success', 'Lozinka je promenjena. Prijavi se novom lozinkom.');
                header('Location: /login.php');
                exit;
            }
        }
    }
}

// Re-check after possible POST updates
$codeVerified = !empty($_SESSION['password_reset_verified']) && canResetPasswordWithOtp($userId);
$phoneDisplay = (string)($user['phone'] ?? '');
$usernameDisplay = (string)($user['username'] ?? '');
$smsCount = getOtpSmsSendCount($userId, 'password_reset');
$offerEmail = canOfferOtpEmail($userId, 'password_reset');
$hasAccountEmail = userHasValidEmail($user);
$accountEmailMasked = '';
if ($hasAccountEmail) {
    $em = (string)$user['email'];
    $at = strpos($em, '@');
    $accountEmailMasked = $at !== false
        ? substr($em, 0, 1) . '***' . substr($em, $at)
        : '***';
}

$pageTitle = 'Nova lozinka — KupiTelefon';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Nova lozinka</div>
        <div class="form-card">
            <?php if (!$codeVerified): ?>
                <h2>Unesi kod</h2>
                <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                    Korak 2/3 — unesi kod poslat na <strong><?= h($phoneDisplay) ?></strong>
                    <?php if ($smsCount > 0): ?>
                        <br>SMS pokušaja: <strong><?= (int)$smsCount ?></strong>
                    <?php endif; ?>
                </p>

                <?php if ($error !== ''): ?>
                    <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="verify_code">
                    <div class="form-group">
                        <label>Kod (SMS ili email)</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="123456" autocomplete="one-time-code" autofocus>
                    </div>
                    <button class="btn-call" type="submit">Potvrdi kod</button>
                </form>
                <form method="POST" style="margin-top:12px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend">
                    <button class="btn-sm btn-sm-primary" type="submit">Pošalji SMS ponovo</button>
                </form>

                <?php if ($offerEmail): ?>
                    <?php if ($hasAccountEmail): ?>
                        <form method="POST" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="send_email">
                            <p class="form-hint" style="margin-bottom:10px;">
                                SMS nije stigao? Pošalji kod na email sa naloga (<strong><?= h($accountEmailMasked) ?></strong>).
                            </p>
                            <button class="btn-sm btn-sm-primary" type="submit">Pošalji kod na email</button>
                        </form>
                    <?php else: ?>
                        <p class="form-hint" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                            Email fallback nije dostupan — na nalogu nema sačuvanog emaila.
                            Dodaj email u profilu posle prijave, ili piši na podrška@kupitelefon.rs.
                        </p>
                    <?php endif; ?>
                <?php elseif ($smsCount > 0 && $smsCount < OTP_SMS_BEFORE_EMAIL): ?>
                    <p class="form-hint" style="margin-top:14px;">
                        Ako SMS ne stigne, posle <?= (int)OTP_SMS_BEFORE_EMAIL ?> pokušaja pojaviće se opcija za email.
                        (Još <?= (int)(OTP_SMS_BEFORE_EMAIL - $smsCount) ?>)
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <h2>Nova lozinka</h2>
                <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                    Korak 3/3 — tvoje korisničko ime je
                    <strong style="color:var(--text);"><?= h($usernameDisplay) ?></strong>.
                    Unesi novu lozinku.
                </p>

                <?php if ($error !== ''): ?>
                    <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="set_password">
                    <div class="form-group">
                        <label>Korisničko ime</label>
                        <input type="text" value="<?= h($usernameDisplay) ?>" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label>Nova lozinka</label>
                        <input type="password" name="password" required minlength="6" autocomplete="new-password" autofocus>
                    </div>
                    <div class="form-group">
                        <label>Ponovi lozinku</label>
                        <input type="password" name="password_confirm" required minlength="6" autocomplete="new-password">
                    </div>
                    <button class="btn-call" type="submit">Sačuvaj lozinku</button>
                </form>
            <?php endif; ?>

            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                <a href="/forgot-password.php">Nazad</a> · <a href="/login.php">Prijava</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
