<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$canPreview = isAdmin() && !empty($_GET['preview']);
$guide = getGuideBySlug($slug, $canPreview);

if (!$guide) {
    http_response_code(404);
    echo 'Vodič nije pronađen.';
    exit;
}

if ((string)($guide['status'] ?? 'draft') !== 'published' && !$canPreview) {
    http_response_code(404);
    echo 'Vodič nije pronađen.';
    exit;
}

$seo = seoGuideMeta($guide);
$pageTitle = $seo['title'];
$pageDescription = $seo['description'];
$canonicalUrl = absoluteUrl(guideUrl($guide));
$activePage = 'oglasi';
$showSearch = false;
$jsonLd = seoGuideJsonLd($guide);
$og = trim((string)($guide['og_image'] ?? ''));
if ($og !== '') {
    $pageImage = absoluteUrl($og);
}
if ($canPreview || (string)($guide['status'] ?? 'draft') !== 'published') {
    $robotsMeta = 'noindex,follow';
}

$published = guidePublishedLabel($guide);
$related = array_values(array_filter(
    getPublishedGuides(),
    static fn($g) => (int)($g['id'] ?? 0) !== (int)($guide['id'] ?? 0)
));
$related = array_slice($related, 0, 4);

require __DIR__ . '/partials/layout-start.php';
?>
<div class="guides-page">
    <div class="breadcrumb">
        <a href="/">Početna</a> › <a href="/vodici">Vodiči</a> › <?= h((string)($guide['title'] ?? '')) ?>
    </div>

    <article class="guide-article">
        <?php if ((string)($guide['status'] ?? 'draft') !== 'published'): ?>
            <p class="form-hint">Draft preview (vidljivo samo adminu).</p>
        <?php endif; ?>

        <header class="guide-article-head">
            <p class="guides-kicker">Vodič</p>
            <h1><?= h((string)($guide['title'] ?? '')) ?></h1>
            <?php if (!empty($guide['excerpt'])): ?>
                <p class="guide-article-lead"><?= h((string)$guide['excerpt']) ?></p>
            <?php endif; ?>
            <?php if ($published !== ''): ?>
                <p class="guide-article-date">Objavljeno <?= h($published) ?></p>
            <?php endif; ?>
        </header>

        <div class="guide-body">
            <?= (string)($guide['body_html'] ?? '') ?>
        </div>

        <footer class="guide-article-foot">
            <a class="guide-back" href="/vodici">← Svi vodiči</a>
        </footer>
    </article>

    <?php if ($related !== []): ?>
        <aside class="guide-related">
            <h2>Još vodiča</h2>
            <div class="guides-grid guides-grid--compact">
                <?php foreach ($related as $item): ?>
                    <a class="guide-mini" href="<?= h(guideUrl($item)) ?>">
                        <strong><?= h((string)($item['title'] ?? '')) ?></strong>
                        <?php if (!empty($item['excerpt'])): ?>
                            <span><?= h((string)$item['excerpt']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
