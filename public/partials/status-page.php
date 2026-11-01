<?php
/** @var string $heading */
/** @var string $message */
/** @var list<array{href:string,label:string}> $links */
/** @var list<array> $statusSimilarAds */
/** @var string $similarHeading */
$links = $links ?? [];
$statusSimilarAds = $statusSimilarAds ?? [];
$similarHeading = $similarHeading ?? 'Slični oglasi';
?>
<div class="main-wrap">
    <main class="content status-page">
        <article class="form-card">
            <h1><?= h($heading) ?></h1>
            <p><?= h($message) ?></p>
            <?php if ($links !== []): ?>
                <nav class="status-links" aria-label="Predlozi">
                    <?php foreach ($links as $link): ?>
                        <a href="<?= h((string)($link['href'] ?? '')) ?>"><?= h((string)($link['label'] ?? '')) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </article>
        <?php if ($statusSimilarAds !== []): ?>
            <section class="status-similar">
                <h2><?= h($similarHeading) ?></h2>
                <div class="listings view-list">
                    <?php foreach ($statusSimilarAds as $ad): ?>
                        <?php require __DIR__ . '/ad-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
