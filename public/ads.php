<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$ads = getAllAds();

$pageTitle = 'Upravljanje oglasima — TelefonBerza';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'ads';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Admin › Oglasi</div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap;">
            <h2 style="font-size:18px;">Upravljanje oglasima</h2>
            <a class="btn-post" href="/ad_form.php" style="width:auto;display:inline-block;">+ Dodaj oglas</a>
        </div>

        <div class="form-card table-scroll" style="padding:0;">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Naziv</th>
                    <th>Tip</th>
                    <th>Cena</th>
                    <th>Lokacija</th>
                    <th>Status</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><?= h((string)$ad['title']) ?></td>
                        <td><?= h(adTypeLabel(getAdType($ad))) ?></td>
                        <td><?= formatPrice((float)$ad['price']) ?></td>
                        <td><?= h((string)$ad['location']) ?></td>
                        <td><?= (int)($ad['is_active'] ?? 0) === 1 ? 'Aktivan' : 'Neaktivan' ?></td>
                        <td>
                            <div class="admin-actions">
                                <a class="btn-sm" href="/oglas.php?id=<?= (int)$ad['id'] ?>">Pogledaj</a>
                                <a class="btn-sm btn-sm-primary" href="/ad_form.php?id=<?= (int)$ad['id'] ?>">Izmeni</a>
                                <a class="btn-sm" href="/ad_toggle.php?id=<?= (int)$ad['id'] ?>&action=sold"><?= !empty($ad['is_sold']) ? 'Vrati' : 'Prodato' ?></a>
                                <a class="btn-sm" href="/ad_toggle.php?id=<?= (int)$ad['id'] ?>&action=promote"><?= !empty($ad['is_promoted']) ? 'Un-TOP' : 'TOP' ?></a>
                                <a class="btn-sm btn-sm-danger" href="/ad_delete.php?id=<?= (int)$ad['id'] ?>" onclick="return confirm('Obrisati oglas?');">Obriši</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
