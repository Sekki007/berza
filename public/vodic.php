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
$jsonLd = seoGuideJsonLd($guide);
$og = trim((string)($guide['og_image'] ?? ''));
if ($og !== '') {
    $pageImage = absoluteUrl($og);
}
if ($canPreview || (string)($guide['status'] ?? 'draft') !== 'published') {
    $robotsMeta = 'noindex,follow';
}

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content">
        <article class="form-card">
            <?php if ((string)($guide['status'] ?? 'draft') !== 'published'): ?>
                <p class="form-hint">Draft preview (vidljivo samo adminu).</p>
            <?php endif; ?>
            <h1><?= h((string)($guide['title'] ?? '')) ?></h1>
            <?php if (!empty($guide['excerpt'])): ?>
                <p class="form-hint"><?= h((string)$guide['excerpt']) ?></p>
            <?php endif; ?>
            <div class="kp-desc-body"><?= (string)($guide['body_html'] ?? '') ?></div>
            <p style="margin-top:16px;"><a href="/vodici">← Svi vodiči</a></p>
        </article>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
