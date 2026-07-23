<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/dashboard.php' : '/nalog.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $user = findUserByUsername($username);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!empty($user['is_blocked'])) {
            setFlash('danger', 'Ovaj nalog je blokiran' . (!empty($user['blocked_reason']) ? ': ' . $user['blocked_reason'] : '.'));
            header('Location: /login.php');
            exit;
        }
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'is_admin' => !empty($user['is_admin']) || $user['username'] === 'admin',
        ];
        setFlash('success', 'Upešno ste prijavljeni.');
        header('Location: ' . ((!empty($user['is_admin']) || $user['username'] === 'admin') ? '/dashboard.php' : '/nalog.php'));
        exit;
    }

    setFlash('danger', 'Pogrešno korisničko ime ili lozinka.');
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Prijava — TelefonBerza';
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
                <div class="form-group">
                    <label>Korisničko ime</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Lozinka</label>
                    <input type="password" name="password" required>
                </div>
                <button class="btn-call" type="submit">Prijavi se</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                Nemaš nalog? <a href="/register.php">Registruj se</a>
            </p>
            <p style="margin-top:8px;font-size:12px;color:var(--text-light);">Demo: admin / admin123</p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
