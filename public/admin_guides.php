<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_guides.php');
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'delete_guide') {
        $gid = (int)($_POST['guide_id'] ?? 0);
        if ($gid > 0 && deleteGuide($gid)) {
            setFlash('success', 'Vodič je obrisan.');
        } else {
            setFlash('danger', 'Brisanje nije uspelo.');
        }
    }
    header('Location: /admin_guides.php');
    exit;
}

$guides = getAllGuides();
$pageTitle = 'Admin — Vodiči';
$activePage = 'nalog';
$adminPage = 'guides';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Vodiči</div>
        <section class="form-card">
            <div class="account-section-head">
                <h2>Vodiči</h2>
                <a class="btn-sm btn-sm-primary" href="/admin_guide_edit.php">+ Novi vodič</a>
            </div>
            <?php if ($guides === []): ?>
                <p class="form-hint">Nema vodiča.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Naslov</th>
                            <th>Status</th>
                            <th>Slug</th>
                            <th>Ažurirano</th>
                            <th>Akcije</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($guides as $guide): ?>
                            <tr>
                                <td><strong><?= h((string)($guide['title'] ?? '')) ?></strong></td>
                                <td><?= (string)($guide['status'] ?? 'draft') === 'published' ? 'Objavljen' : 'Nacrt' ?></td>
                                <td>
                                    <a href="<?= h(guideUrl($guide)) ?>" target="_blank" rel="noopener">/vodic/<?= h((string)($guide['slug'] ?? '')) ?></a>
                                </td>
                                <td><?= h((string)($guide['updated_at'] ?? '')) ?></td>
                                <td>
                                    <div class="account-ad-actions">
                                        <a class="btn-sm" href="/admin_guide_edit.php?id=<?= (int)($guide['id'] ?? 0) ?>">Uredi</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Obrisati vodič?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_guide">
                                            <input type="hidden" name="guide_id" value="<?= (int)($guide['id'] ?? 0) ?>">
                                            <button class="btn-sm btn-sm-danger" type="submit">Obriši</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
