<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$ads = getFavoriteAds();

$pageTitle = 'Omiljeni oglasi — KupiTelefon';
$activePage = 'nalog';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Omiljeni oglasi</div>
        <div class="form-card">
            <h2>Omiljeni oglasi (<?= count($ads) ?>)</h2>
            <?php if (!$ads): ?>
                <p style="color:var(--text-muted);">Nema sačuvanih oglasa. Klikni ☆ na detalju oglasa.</p>
            <?php else: ?>
                <div class="listings">
                    <?php foreach ($ads as $ad): ?>
                        <?php require __DIR__ . '/partials/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
