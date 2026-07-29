<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$userId = (int)($_SESSION['pending_phone_verify_user_id'] ?? 0);
if ($userId <= 0 && isLoggedIn()) {
    $userId = (int)(currentUser()['id'] ?? 0);
}

$user = $userId > 0 ? findUserById($userId) : null;
if (!$user) {
    setFlash('danger', 'Nema naloga za verifikaciju. Registruj se ili se prijavi.');
    header('Location: /register.php');
    exit;
}

if (isPhoneVerified($user)) {
    unset($_SESSION['pending_phone_verify_user_id']);
    setFlash('success', 'Telefon je već potvrđen. Možeš se prijaviti.');
    header('Location: /login.php');
    exit;
}

$_SESSION['pending_phone_verify_user_id'] = $userId;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/verify-phone.php');
    $action = trim((string)($_POST['action'] ?? 'verify'));

    if ($action === 'send' || $action === 'resend') {
        $phoneInput = trim((string)($_POST['phone'] ?? (string)($user['phone'] ?? '')));
        $result = sendUserOtp($userId, 'phone_verify', $phoneInput !== '' ? $phoneInput : null, 'sms');
        if (!empty($result['ok'])) {
            setFlash('success', 'SMS kod je poslat. Unesi ga ispod.');
            header('Location: /verify-phone.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'SMS nije poslat.');
        $user = findUserById($userId) ?? $user;
    } elseif ($action === 'send_email') {
        $emailInput = trim((string)($_POST['email'] ?? (string)($user['email'] ?? '')));
        $result = sendUserOtp($userId, 'phone_verify', null, 'email', $emailInput !== '' ? $emailInput : null);
        if (!empty($result['ok'])) {
            setFlash('success', 'Kod je poslat na email. Unesi ga ispod.');
            header('Location: /verify-phone.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'Email nije poslat.');
        $user = findUserById($userId) ?? $user;
    } elseif ($action === 'verify') {
        $code = trim((string)($_POST['code'] ?? ''));
        $result = verifyUserOtp($userId, 'phone_verify', $code);
        if (!empty($result['ok'])) {
            unset($_SESSION['pending_phone_verify_user_id']);
            $verifiedUser = findUserById($userId);
            if ($verifiedUser) {
                $isAdminUser = !empty($verifiedUser['is_admin']);
                $_SESSION['user'] = [
                    'id' => (int)$verifiedUser['id'],
                    'username' => $verifiedUser['username'],
                    'full_name' => $verifiedUser['full_name'],
                    'is_admin' => $isAdminUser,
                ];
                setFlash('success', 'Telefon je potvrđen. Dobrodošao!');
                queueFacebookPixelEvent('CompleteRegistration', ['status' => 'verified']);
                queueGoogleTagEvent('sign_up', ['method' => 'phone_otp', 'status' => 'verified']);
                header('Location: ' . ($isAdminUser ? '/dashboard.php' : '/nalog.php'));
            } else {
                setFlash('success', 'Telefon je potvrđen. Sada se možeš prijaviti.');
                queueFacebookPixelEvent('CompleteRegistration', ['status' => 'verified']);
                queueGoogleTagEvent('sign_up', ['method' => 'phone_otp', 'status' => 'verified']);
                header('Location: /login.php');
            }
            exit;
        }
        $error = (string)($result['error'] ?? 'Verifikacija nije uspela.');
        $user = findUserById($userId) ?? $user;
    }
}

$phoneDisplay = (string)($user['phone'] ?? '');
$emailDisplay = trim((string)($user['email'] ?? ''));
$hasPhone = normalizePhoneRs($phoneDisplay) !== null;
$otpPending = (string)($user['otp_purpose'] ?? '') === 'phone_verify' && !empty($user['otp_sent_at']);
$smsCount = getOtpSmsSendCount($userId, 'phone_verify');
$offerEmail = canOfferOtpEmail($userId, 'phone_verify');

$pageTitle = 'Verifikacija telefona — KupiTelefon';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Verifikacija telefona</div>
        <div class="form-card">
            <h2>Verifikacija telefona</h2>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Poslaćemo ti 6-cifreni kod SMS-om na srpski mobilni broj (+381).
                <?php if ($smsCount > 0): ?>
                    <br>SMS pokušaja: <strong><?= (int)$smsCount ?></strong><?= $offerEmail ? ' — možeš zatražiti kod i na email.' : '' ?>
                <?php endif; ?>
            </p>

            <?php if ($error !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
            <?php endif; ?>

            <?php if ($hasPhone): ?>
                <p class="form-hint" style="margin-bottom:14px;">Broj: <strong><?= h($phoneDisplay) ?></strong></p>
            <?php endif; ?>

            <?php if (!$otpPending || !$hasPhone): ?>
                <form method="POST" style="margin-bottom:18px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send">
                    <?php if (!$hasPhone): ?>
                        <div class="form-group">
                            <label>Mobilni telefon</label>
                            <input type="text" name="phone" required placeholder="06x xxx xxxx" value="<?= h($phoneDisplay) ?>">
                        </div>
                    <?php endif; ?>
                    <button class="btn-call" type="submit">Pošalji SMS kod</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="verify">
                    <div class="form-group">
                        <label>Kod (SMS ili email)</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="123456" autocomplete="one-time-code">
                    </div>
                    <button class="btn-call" type="submit">Potvrdi kod</button>
                </form>
                <form method="POST" style="margin-top:12px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend">
                    <button class="btn-sm btn-sm-primary" type="submit">Pošalji SMS ponovo</button>
                </form>

                <?php if ($offerEmail): ?>
                    <form method="POST" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="send_email">
                        <p class="form-hint" style="margin-bottom:10px;">SMS nije stigao? Pošalji isti tip koda na email.</p>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required value="<?= h($emailDisplay) ?>" placeholder="tvoj@email.com" autocomplete="email">
                        </div>
                        <button class="btn-sm btn-sm-primary" type="submit">Pošalji kod na email</button>
                    </form>
                <?php elseif ($smsCount > 0 && $smsCount < OTP_SMS_BEFORE_EMAIL): ?>
                    <p class="form-hint" style="margin-top:14px;">
                        Ako SMS ne stigne, posle <?= (int)OTP_SMS_BEFORE_EMAIL ?> pokušaja pojaviće se opcija za email.
                        (Još <?= (int)(OTP_SMS_BEFORE_EMAIL - $smsCount) ?>)
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                <a href="/login.php">Nazad na prijavu</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
