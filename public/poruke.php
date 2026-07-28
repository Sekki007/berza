<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireLogin();

$site = siteSettings();
if (empty($site['enable_messages'])) {
    setFlash('danger', 'Poruke trenutno nisu omogućene.');
    header('Location: /nalog.php');
    exit;
}

$user = currentUser();
$userId = (int)$user['id'];
$adId = isset($_GET['ad']) ? (int)$_GET['ad'] : 0;
$withId = isset($_GET['with']) ? (int)$_GET['with'] : 0;
$viewThread = $adId > 0 && $withId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/poruke.php');
    $replyAd = (int)($_POST['ad_id'] ?? 0);
    $replyTo = (int)($_POST['to_user_id'] ?? 0);
    $body = trim((string)($_POST['message'] ?? ''));

    if ($replyAd <= 0 || $replyTo <= 0 || $body === '') {
        setFlash('danger', 'Poruka nije poslata. Proveri unos.');
        header('Location: /poruke.php');
        exit;
    }

    if ($replyTo === $userId) {
        setFlash('danger', 'Ne možeš slati poruku sebi.');
        header('Location: /poruke.php');
        exit;
    }

    $saved = saveMessage([
        'ad_id' => $replyAd,
        'from_user_id' => $userId,
        'from_name' => (string)$user['full_name'],
        'from_phone' => '',
        'to_user_id' => $replyTo,
        'body' => $body,
    ]);

    if ($saved) {
        setFlash('success', 'Poruka je poslata.');
    } else {
        setFlash('danger', 'Poruka nije poslata.');
    }
    header('Location: /poruke.php?ad=' . $replyAd . '&with=' . $replyTo);
    exit;
}

$pageTitle = 'Poruke — KupiTelefon';
$activePage = 'poruke';
$bodyClass = $viewThread ? 'page-chat' : '';
$hideMobileBar = $viewThread;
$showSearch = false;

if ($viewThread) {
    $partner = findUserById($withId);
    $ad = getAdById($adId);
    if (!$partner || !$ad) {
        setFlash('danger', 'Konverzacija nije pronađena.');
        header('Location: /poruke.php');
        exit;
    }

    markThreadRead($userId, $adId, $withId);
    $threadMessages = getThreadMessages($userId, $adId, $withId);
    $partnerName = (string)($partner['full_name'] ?? 'Korisnik');

    require __DIR__ . '/partials/layout-start.php';
    ?>
    <div class="main-wrap">
        <main class="content">
            <div class="breadcrumb">
                <a href="/index.php">Početna</a> ›
                <a href="/poruke.php">Poruke</a> ›
                <?= h($partnerName) ?>
            </div>

            <div class="chat-shell form-card">
                <div class="chat-head">
                    <div>
                        <h2><?= h($partnerName) ?></h2>
                        <a class="chat-ad-link" href="/oglas.php?id=<?= $adId ?>"><?= h((string)$ad['title']) ?></a>
                    </div>
                    <a class="btn-sm" href="/poruke.php">← Inbox</a>
                </div>

                <div class="chat-thread" data-chat-thread data-user-id="<?= $userId ?>">
                    <?php if (!$threadMessages): ?>
                        <p class="chat-empty" data-chat-empty>Još nema poruka u ovoj konverzaciji. Napiši prvu.</p>
                    <?php endif; ?>
                    <?php
                    $lastMsgId = 0;
                    foreach ($threadMessages as $msg):
                        $mine = (int)($msg['from_user_id'] ?? 0) === $userId;
                        $lastMsgId = max($lastMsgId, (int)($msg['id'] ?? 0));
                        ?>
                        <div class="chat-bubble <?= $mine ? 'mine' : 'theirs' ?>" data-msg-id="<?= (int)$msg['id'] ?>">
                            <div class="chat-bubble-body"><?= nl2br(h((string)$msg['body'])) ?></div>
                            <div class="chat-bubble-meta"><?= h(formatRelativeTime((string)$msg['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="chat-compose" data-live-chat data-ad-id="<?= $adId ?>" data-with-id="<?= $withId ?>" data-last-id="<?= $lastMsgId ?>">
                    <input type="hidden" name="ad_id" value="<?= $adId ?>">
                    <input type="hidden" name="to_user_id" value="<?= $withId ?>">
                    <textarea name="message" rows="2" placeholder="Napiši poruku..." required data-chat-input></textarea>
                    <button class="btn-kp-message" type="submit" data-chat-send>Pošalji</button>
                </form>
                <div class="chat-live-hint" data-chat-status>Uživo · nove poruke stižu automatski</div>
            </div>
        </main>
    </div>
    <?php
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

$threads = getMessageThreads($userId);
$unreadTotal = getUnreadMessageCount($userId);

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Poruke</div>

        <div class="form-card">
            <div class="inbox-head">
                <h2>Poruke</h2>
                <?php if ($unreadTotal > 0): ?>
                    <span class="inbox-unread-label"><?= $unreadTotal ?> nepročitanih</span>
                <?php endif; ?>
            </div>

            <?php if (!$threads): ?>
                <p style="color:var(--text-muted);">Nema poruka. Pošalji poruku sa stranice oglasa.</p>
            <?php endif; ?>

            <div class="inbox-list" data-live-inbox>
                <?php foreach ($threads as $thread): ?>
                    <a class="msg-item <?= $thread['unread'] > 0 ? 'unread' : '' ?>" href="/poruke.php?ad=<?= (int)$thread['ad_id'] ?>&with=<?= (int)$thread['partner_id'] ?>" data-thread-key="<?= h((string)$thread['key']) ?>">
                        <div class="msg-avatar"><?= h(mb_strtoupper(mb_substr($thread['partner_name'], 0, 1))) ?></div>
                        <div class="msg-preview">
                            <strong>
                                <?= h($thread['partner_name']) ?>
                                <span data-thread-badge><?php if ($thread['unread'] > 0): ?><?= renderUnreadBadge((int)$thread['unread']) ?><?php endif; ?></span>
                            </strong>
                            <span class="msg-ad"><?= h($thread['ad_title']) ?></span>
                            <span data-thread-preview><?= h($thread['last_body']) ?></span>
                        </div>
                        <div class="msg-time" data-thread-time><?= h(formatRelativeTime((string)$thread['last_at'])) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
