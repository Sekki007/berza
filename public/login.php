<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/dashboard.php' : '/nalog.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/login.php');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);
    $user = findUserByUsername($username);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!empty($user['is_blocked'])) {
            setFlash('danger', 'Ovaj nalog je blokiran' . (!empty($user['blocked_reason']) ? ': ' . $user['blocked_reason'] : '.'));
            header('Location: /login.php');
            exit;
        }

        $isAdminUser = !empty($user['is_admin']) || $user['username'] === 'admin';
        if (!$isAdminUser && !isPhoneVerified($user)) {
            $_SESSION['pending_phone_verify_user_id'] = (int)$user['id'];
            setFlash('danger', 'Prvo potvrdi broj telefona SMS kodom.');
            header('Location: /verify-phone.php');
            exit;
        }

        session_regenerate_id(true);
        setSessionUserFromProfile($user);
        issueRememberLogin($user, $rememberMe);
        setFlash('success', 'Upešno ste prijavljeni.');
        header('Location: ' . ($isAdminUser ? '/dashboard.php' : '/nalog.php'));
        exit;
    }

    setFlash('danger', 'Pogrešno korisničko ime ili lozinka.');
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Prijava — KupiTelefon';
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Prijava</div>
        <div class="form-card">
            <h2>Prijava</h2>
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Korisničko ime</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Lozinka</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group" style="margin-top:-6px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer;">
                        <input type="checkbox" name="remember_me" value="1" checked>
                        <span>Zapamti me na ovom uređaju</span>
                    </label>
                </div>
                <button class="btn-call" type="submit">Prijavi se</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                Nemaš nalog? <a href="/register.php">Registruj se</a>
            </p>
            <p style="margin-top:8px;font-size:13px;color:var(--text-muted);">
                <a href="/forgot-password.php">Zaboravljena lozinka?</a>
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
