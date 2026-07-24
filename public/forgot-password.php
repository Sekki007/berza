<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /nalog.php');
    exit;
}

$error = '';
$phoneValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/forgot-password.php');

    $phoneValue = trim((string)($_POST['phone'] ?? ''));
    $user = $phoneValue !== '' ? findUserByPhone($phoneValue) : null;

    if ($user && isPhoneVerified($user)) {
        $result = sendUserOtp((int)$user['id'], 'password_reset');
        if (!empty($result['ok'])) {
            $_SESSION['pending_password_reset_user_id'] = (int)$user['id'];
            unset($_SESSION['password_reset_verified']);
            setFlash('success', 'SMS kod je poslat. Unesi ga na sledećem koraku.');
            header('Location: /reset-password.php');
            exit;
        }
        $error = (string)($result['error'] ?? 'SMS nije poslat.');
    } elseif ($user && !isPhoneVerified($user)) {
        $_SESSION['pending_phone_verify_user_id'] = (int)$user['id'];
        setFlash('danger', 'Prvo potvrdi telefon, pa možeš resetovati lozinku.');
        header('Location: /verify-phone.php');
        exit;
    } else {
        // Anti-enumeration: same UX, but no SMS
        $error = 'Ako postoji nalog sa ovim brojem, SMS će stići uskoro. Proveri broj i pokušaj ponovo ako ne stigne.';
    }
}

$pageTitle = 'Zaboravljena lozinka — TelefonBerza';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Zaboravljena lozinka</div>
        <div class="form-card">
            <h2>Zaboravljena lozinka</h2>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Korak 1/3 — unesi broj telefona sa naloga. Poslaćemo SMS kod.
            </p>

            <?php if ($error !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Broj telefona</label>
                    <input type="text" name="phone" required placeholder="06x xxx xxxx" value="<?= h($phoneValue) ?>" autocomplete="tel">
                </div>
                <button class="btn-call" type="submit">Pošalji SMS kod</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                <a href="/login.php">Nazad na prijavu</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
