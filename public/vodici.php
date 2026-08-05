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
    ],
];
$activePage = 'oglasi';

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content">
        <section class="form-card">
            <h1>Vodiči</h1>
            <p class="form-hint">Saveti za kupovinu i servis telefona.</p>
            <?php if ($guides === []): ?>
                <p>Vodiči stižu uskoro.</p>
            <?php else: ?>
                <div class="account-list">
                    <?php foreach ($guides as $guide): ?>
                        <article class="account-ad-card">
                            <div class="account-ad-main">
                                <h3><a href="<?= h(guideUrl($guide)) ?>"><?= h((string)($guide['title'] ?? '')) ?></a></h3>
                                <p><?= h((string)($guide['excerpt'] ?? '')) ?></p>
                                <small><?= h((string)($guide['published_at'] ?? '')) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
