<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$adId = isset($_GET['ad']) ? (int)$_GET['ad'] : 0;
$userId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$ad = $adId > 0 ? getAdById($adId) : null;
$targetUser = $userId > 0 ? findUserById($userId) : null;

if ($ad && !$targetUser) {
    $targetUser = findUserById((int)($ad['created_by'] ?? 0));
    $userId = (int)($targetUser['id'] ?? 0);
}

if (!$ad && !$targetUser) {
    setFlash('danger', 'Prijava nije validna — nedostaje oglas ili korisnik.');
    header('Location: /index.php');
    exit;
}

$type = $ad ? 'ad' : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/report.php' . ($adId > 0 ? '?ad=' . $adId : ($userId > 0 ? '?user=' . $userId : '')));
    $reason = trim((string)($_POST['reason'] ?? 'other'));
    $details = trim((string)($_POST['details'] ?? ''));
    $from = currentUser();

    $saved = saveReport([
        'type' => $type,
        'target_ad_id' => $ad ? (int)$ad['id'] : null,
        'target_user_id' => $userId > 0 ? $userId : (int)($ad['created_by'] ?? 0),
        'from_user_id' => $from ? (int)$from['id'] : 0,
        'from_name' => $from ? (string)$from['full_name'] : trim((string)($_POST['from_name'] ?? 'Anonimno')),
        'reason' => $reason,
        'details' => $details,
    ]);

    if ($saved) {
        setFlash('success', 'Prijava je poslata. Hvala — admin će je pregledati.');
    } else {
        setFlash('danger', 'Prijava nije sačuvana.');
    }

    if ($ad) {
        header('Location: /oglas.php?id=' . (int)$ad['id']);
    } elseif ($targetUser) {
        header('Location: ' . shopUrl((string)$targetUser['username']));
    } else {
        header('Location: /index.php');
    }
    exit;
}

$pageTitle = 'Prijavi — KupiTelefon';
$activePage = 'oglasi';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Prijava</div>
        <div class="form-card">
            <h2>Prijavi <?= $ad ? 'oglas' : 'korisnika' ?></h2>
            <?php if ($ad): ?>
                <p class="form-hint">Oglas: <strong><?= h((string)$ad['title']) ?></strong> (#<?= (int)$ad['id'] ?>)</p>
            <?php endif; ?>
            <?php if ($targetUser): ?>
                <p class="form-hint">Korisnik: <strong><?= h((string)$targetUser['full_name']) ?></strong> (@<?= h((string)$targetUser['username']) ?>)</p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Razlog</label>
                    <select name="reason" required>
                        <?php foreach (reportReasons() as $key => $label): ?>
                            <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!isLoggedIn()): ?>
                    <div class="form-group">
                        <label>Tvoje ime (opciono)</label>
                        <input type="text" name="from_name" placeholder="Ime">
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Dodatni opis</label>
                    <textarea name="details" rows="4" maxlength="1000" placeholder="Objasni ukratko zašto prijavljuješ..."></textarea>
                </div>
                <button class="btn-call" type="submit">Pošalji prijavu</button>
                <a class="btn-message" style="display:inline-block;margin-top:10px;" href="<?= $ad ? '/oglas.php?id=' . (int)$ad['id'] : ($targetUser ? h(shopUrl((string)$targetUser['username'])) : '/index.php') ?>">Otkaži</a>
            </form>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
