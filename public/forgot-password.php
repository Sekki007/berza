<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /nalog.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string)($_POST['identity'] ?? ''));
    $user = null;

    if ($identity !== '') {
        $user = findUserByUsername($identity);
        if (!$user) {
            $user = findUserByPhone($identity);
        }
    }

    // Same response whether found or not (anti-enumeration), but only send if valid
    if ($user && isPhoneVerified($user)) {
        $result = sendUserOtp((int)$user['id'], 'password_reset');
        if (!empty($result['ok'])) {
            $_SESSION['pending_password_reset_user_id'] = (int)$user['id'];
            setFlash('success', 'Ako nalog postoji, SMS kod je poslat. Unesi kod i novu lozinku.');
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
        // Fake success path delay-ish message
        setFlash('success', 'Ako nalog postoji i ima verifikovan telefon, SMS kod je poslat.');
        header('Location: /forgot-password.php');
        exit;
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
                Unesi korisničko ime ili broj telefona. Poslaćemo SMS kod na verifikovani +381 broj.
            </p>

            <?php if ($error !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($error) ?></p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Korisničko ime ili telefon</label>
                    <input type="text" name="identity" required placeholder="username ili 06x…">
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
