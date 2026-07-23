<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adId = (int)($_POST['ad_id'] ?? $_GET['id'] ?? 0);
    $result = toggleCompare($adId);
    $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || ($_POST['ajax'] ?? '') === '1'
        || ($_GET['ajax'] ?? '') === '1';
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'ids' => $result['ids'],
            'count' => count($result['ids']),
            'added' => $result['added'],
            'full' => $result['full'],
            'in_compare' => in_array($adId, $result['ids'], true),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($result['full'])) {
        setFlash('danger', 'Možeš uporediti najviše ' . compareMaxAds() . ' oglasa.');
    }
    $redirect = trim((string)($_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/uporedi.php'));
    if ($redirect === '' || !str_starts_with($redirect, '/')) {
        $redirect = '/uporedi.php';
    }
    header('Location: ' . $redirect);
    exit;
}

if (($_GET['action'] ?? '') === 'clear') {
    clearCompare();
    header('Location: /uporedi.php');
    exit;
}

$ads = getCompareAds();
$pageTitle = 'Uporedi oglase — TelefonBerza';
$activePage = 'oglasi';
$pageDescription = 'Uporedi do 3 oglasa: cena, stanje, grad i prodavac.';
require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Uporedi</div>
        <div class="form-card">
            <div class="account-section-head">
                <h2>Poređenje oglasa</h2>
                <?php if ($ads): ?>
                    <a class="btn-sm" href="/uporedi.php?action=clear">Obriši sve</a>
                <?php endif; ?>
            </div>

            <?php if (!$ads): ?>
                <p style="color:var(--text-muted);">Nema oglasa za poređenje. Na listi ili detalju oglasa klikni „Uporedi”.</p>
                <p style="margin-top:12px;"><a class="btn-sm btn-sm-primary" href="/index.php">Nazad na oglase</a></p>
            <?php else: ?>
                <div class="compare-table-wrap">
                    <table class="compare-table">
                        <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($ads as $ad): ?>
                                <th>
                                    <a href="<?= h(adUrl($ad)) ?>">
                                        <?php $img = adPrimaryImage($ad); ?>
                                        <?php if ($img): ?>
                                            <img class="compare-thumb" src="<?= h($img) ?>" alt="">
                                        <?php endif; ?>
                                        <span><?= h((string)$ad['title']) ?></span>
                                    </a>
                                    <form method="POST" action="/uporedi.php" style="margin-top:8px;">
                                        <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                        <input type="hidden" name="redirect" value="/uporedi.php">
                                        <button class="btn-sm" type="submit">Ukloni</button>
                                    </form>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rows = [
                            'Cena' => static fn($ad) => formatPrice((float)$ad['price']),
                            'Tip' => static fn($ad) => adTypeLabel(getAdType($ad)),
                            'Brend' => static fn($ad) => (string)($ad['brand'] ?? '—'),
                            'Model' => static fn($ad) => (string)($ad['model'] ?? '—'),
                            'Stanje' => static fn($ad) => (string)($ad['condition_state'] ?? '—'),
                            'Grad' => static fn($ad) => (string)($ad['location'] ?? '—'),
                            'Memorija' => static fn($ad) => (string)($ad['storage'] ?? '—'),
                        ];
                        foreach ($rows as $label => $fn):
                        ?>
                            <tr>
                                <th><?= h($label) ?></th>
                                <?php foreach ($ads as $ad): ?>
                                    <td><?= h($fn($ad)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Prodavac</th>
                            <?php foreach ($ads as $ad): ?>
                                <?php
                                $seller = !empty($ad['created_by']) ? findUserById((int)$ad['created_by']) : null;
                                $sName = $seller ? getSellerShopName($seller) : '—';
                                ?>
                                <td>
                                    <?= h($sName) ?>
                                    <?= $seller ? renderVerifiedBadge($seller) : '' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
