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
$activePage = 'admin';
$adminPage = 'guides';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>
<div class="admin-layout">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="admin-main">
        <section class="form-card">
            <div class="account-section-head">
                <h2>Vodiči</h2>
                <a class="btn-call" href="/admin_guide_edit.php">+ Novi vodič</a>
            </div>
            <?php if ($guides === []): ?>
                <p class="form-hint">Nema vodiča.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Naslov</th>
                            <th>Status</th>
                            <th>Slug</th>
                            <th>Ažurirano</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($guides as $guide): ?>
                            <tr>
                                <td><strong><?= h((string)($guide['title'] ?? '')) ?></strong></td>
                                <td><?= h((string)($guide['status'] ?? 'draft')) ?></td>
                                <td>/vodic/<?= h((string)($guide['slug'] ?? '')) ?></td>
                                <td><?= h((string)($guide['updated_at'] ?? '')) ?></td>
                                <td>
                                    <a class="btn-sm" href="/admin_guide_edit.php?id=<?= (int)($guide['id'] ?? 0) ?>">Uredi</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Obrisati vodič?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_guide">
                                        <input type="hidden" name="guide_id" value="<?= (int)($guide['id'] ?? 0) ?>">
                                        <button class="btn-sm btn-sm-danger" type="submit">Obriši</button>
                                    </form>
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
