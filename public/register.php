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

    if (registerUser($username, $password, $fullName, $phone)) {
        setFlash('success', 'Nalog je kreiran. Možeš se prijaviti.');
        header('Location: /login.php');
        exit;
    }

    setFlash('danger', 'Registracija nije uspela. Korisničko ime možda već postoji.');
    header('Location: /register.php');
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
                    <label>Telefon</label>
                    <input type="text" name="phone" placeholder="06x xxx xxxx">
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
