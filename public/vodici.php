<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$guides = getPublishedGuides();
$seo = seoGuideHubMeta();
$pageTitle = $seo['title'];
$pageDescription = $seo['description'];
$canonicalUrl = absoluteUrl('/vodici');
$jsonLd = [
    seoWebsiteJsonLd(),
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Vodiči',
        'url' => $canonicalUrl,
        'description' => $pageDescription,
    ],
];
$activePage = 'oglasi';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>
<div class="guides-page">
    <div class="breadcrumb"><a href="/">Početna</a> › Vodiči</div>

    <header class="guides-hero">
        <p class="guides-kicker">Saveti pre kupovine</p>
        <h1>Vodiči</h1>
        <p class="guides-lead">
            Praktični saveti za proveru uređaja, bezbednu kupovinu i odluke oko servisa.
            Kratko, jasno i bez nepotrebnog marketinga.
        </p>
    </header>

    <?php if ($guides === []): ?>
        <div class="guides-empty">
            <strong>Vodiči stižu uskoro</strong>
            <p>Pripremamo praktične tekstove za kupovinu i servis telefona.</p>
        </div>
    <?php else: ?>
        <div class="guides-grid">
            <?php foreach ($guides as $i => $guide): ?>
                <?php
                $url = guideUrl($guide);
                $date = guidePublishedLabel($guide);
                $n = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
                ?>
                <article class="guide-card">
                    <a class="guide-card-link" href="<?= h($url) ?>">
                        <span class="guide-card-num"><?= h($n) ?></span>
                        <h2><?= h((string)($guide['title'] ?? '')) ?></h2>
                        <?php if (!empty($guide['excerpt'])): ?>
                            <p><?= h((string)$guide['excerpt']) ?></p>
                        <?php endif; ?>
                        <span class="guide-card-meta">
                            <?php if ($date !== ''): ?>
                                <time><?= h($date) ?></time>
                            <?php endif; ?>
                            <span class="guide-card-cta">Pročitaj vodič →</span>
                        </span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
