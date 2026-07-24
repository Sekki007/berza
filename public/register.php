<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /nalog.php');
    exit;
}

$site = siteSettings();
if (empty($site['enable_registration'])) {
    setFlash('danger', 'Registracija trenutno nije omogućena.');
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    $normalized = normalizePhoneRs($phone);
    if ($normalized === null || !isAllowedSmsPhone($normalized)) {
        setFlash('danger', 'Unesi validan srpski mobilni broj (npr. 06x xxx xxxx).');
        header('Location: /register.php');
        exit;
    }

    if (strlen($password) < 6) {
        setFlash('danger', 'Lozinka mora imati najmanje 6 karaktera.');
        header('Location: /register.php');
        exit;
    }

    if (findUserByPhone($normalized)) {
        setFlash('danger', 'Ovaj broj telefona je već registrovan.');
        header('Location: /register.php');
        exit;
    }

    $userId = registerUser($username, $password, $fullName, $phone);
    if ($userId === false) {
        setFlash('danger', 'Registracija nije uspela. Korisničko ime možda već postoji.');
        header('Location: /register.php');
        exit;
    }

    $_SESSION['pending_phone_verify_user_id'] = $userId;

    if (!smsEnabled()) {
        patchUser($userId, ['phone_verified_at' => date('Y-m-d H:i:s')]);
        unset($_SESSION['pending_phone_verify_user_id']);
        setFlash('success', 'Nalog je kreiran (SMS je isključen — telefon automatski označen kao potvrđen).');
        header('Location: /login.php');
        exit;
    }

    $otp = sendUserOtp($userId, 'phone_verify');
    if (!empty($otp['ok'])) {
        setFlash('success', 'Nalog je kreiran. Unesi SMS kod koji smo poslali.');
    } else {
        setFlash('danger', 'Nalog je kreiran, ali SMS nije poslat: ' . (string)($otp['error'] ?? 'greška') . '. Možeš zatražiti kod ponovo.');
    }
    header('Location: /verify-phone.php');
    exit;
}

$pageTitle = 'Registracija — TelefonBerza';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Registracija</div>
        <div class="form-card">
            <h2>Registracija</h2>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Poslaćemo SMS kod na tvoj mobilni broj radi potvrde.
            </p>
            <form method="POST">
                <div class="form-group">
                    <label>Ime i prezime</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Korisničko ime</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mobilni telefon</label>
                    <input type="text" name="phone" required placeholder="06x xxx xxxx">
                    <p class="form-hint" style="margin-top:6px;">Samo srpski mobilni brojevi (+3816…).</p>
                </div>
                <div class="form-group">
                    <label>Lozinka</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <button class="btn-call" type="submit">Kreiraj nalog</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                Već imaš nalog? <a href="/login.php">Prijavi se</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
