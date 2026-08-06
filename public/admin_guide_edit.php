<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$guideId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$guide = $guideId > 0 ? getGuideById($guideId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_guide_edit.php' . ($guideId > 0 ? '?id=' . $guideId : ''));
    $savedId = saveGuide($_POST, $guideId > 0 ? $guideId : null);
    if ($savedId !== null) {
        setFlash('success', 'Vodič je sačuvan.');
        header('Location: /admin_guide_edit.php?id=' . $savedId);
        exit;
    }
    setFlash('danger', 'Sačuvavanje nije uspelo. Naslov je obavezan.');
    $guide = array_merge((array)$guide, $_POST);
}

$pageTitle = 'Admin — Uredi vodič';
$activePage = 'nalog';
$adminPage = 'guides';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › <a href="/admin_guides.php">Vodiči</a> › <?= $guideId > 0 ? 'Uredi' : 'Novi' ?></div>
        <section class="form-card">
            <div class="account-section-head">
                <h2><?= $guideId > 0 ? 'Uredi vodič' : 'Novi vodič' ?></h2>
                <a href="/admin_guides.php">← Svi vodiči</a>
            </div>
            <form method="POST" class="account-profile-form">
                <?= csrfField() ?>
                <?php if ($guideId > 0): ?><input type="hidden" name="id" value="<?= $guideId ?>"><?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Naslov *</label>
                        <input type="text" name="title" required maxlength="180" value="<?= h((string)($guide['title'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Slug (nije obavezno)</label>
                        <input type="text" name="slug" maxlength="180" value="<?= h((string)($guide['slug'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Opis (excerpt)</label>
                    <textarea name="excerpt" rows="3"><?= h((string)($guide['excerpt'] ?? '')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Sadržaj (HTML)</label>
                    <textarea name="body_html" rows="16" required><?= h((string)($guide['body_html'] ?? '')) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SEO title</label>
                        <input type="text" name="seo_title" maxlength="180" value="<?= h((string)($guide['seo_title'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>SEO description</label>
                        <input type="text" name="seo_description" maxlength="240" value="<?= h((string)($guide['seo_description'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>OG slika URL</label>
                        <input type="text" name="og_image" value="<?= h((string)($guide['og_image'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <?php $status = (string)($guide['status'] ?? 'draft'); ?>
                        <select name="status">
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Nacrt</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Objavljen</option>
                        </select>
                    </div>
                </div>
                <button class="btn-call" type="submit">Sačuvaj vodič</button>
                <?php if ($guideId > 0): ?>
                    <a class="btn-sm" href="<?= h(guideUrl((array)$guide)) ?>?preview=1" target="_blank" rel="noopener">Preview</a>
                <?php endif; ?>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
