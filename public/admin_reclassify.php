<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$pageTitle = 'Pregled klasifikacije — Admin';
$activePage = 'admin';
$showSearch = false;
$robotsMeta = 'noindex,follow';

$changed = [];
foreach (getAllAds() as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    $suggestion = classifyAd($ad);
    if (empty($suggestion['changed'])) {
        continue;
    }
    $changed[] = ['ad' => $ad, 'suggestion' => $suggestion];
}
$stale = staleImportedAdsReport();
$cityChanges = 0;
foreach (getAllAds() as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    $raw = trim((string)($ad['location'] ?? ''));
    $parsed = normalizeCity($raw);
    if ($parsed['city'] !== '' && $parsed['city'] !== $raw) {
        $cityChanges++;
    }
}

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/">Početna</a> › <a href="/dashboard.php">Admin</a> › Klasifikacija</div>
        <div class="form-card">
            <h1>Pregled kvaliteta oglasa</h1>
            <p>Ovo je samo izveštaj. Ništa se ne briše i ne menja dok Sead ručno ne pokrene CLI sa <code>--apply</code>.</p>
            <ul>
                <li>Sigurni predlozi tipa: <?= count(array_filter($changed, static fn($row) => !empty($row['suggestion']['confident']))) ?></li>
                <li>Svi predlozi (uključujući brend): <?= count($changed) ?></li>
                <li>Gradovi koji nisu u kanonskom obliku: <?= (int)$cityChanges ?></li>
                <li>Podsetnik za stare uvozne oglase je <?= $stale['enabled'] ? 'uključen' : 'isključen' ?>. Pogodilo bi <?= (int)$stale['affected'] ?> oglasa starijih od <?= (int)$stale['days'] ?> dana. Mejl se ne šalje.</li>
            </ul>
            <p><code>php tools/reclassify_ads.php --dry-run</code><br>
            <code>php tools/normalize_cities.php --dry-run</code><br>
            <code>php tools/catalog_cleanup.php --dry-run</code></p>
        </div>
        <div class="form-card">
            <h2>Predlozi</h2>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Naslov</th><th>Sada</th><th>Predlog</th><th>Razlog</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($changed, 0, 200) as $row): ?>
                    <tr>
                        <td><?= (int)$row['ad']['id'] ?></td>
                        <td><?= h((string)$row['ad']['title']) ?></td>
                        <td><?= h((string)($row['ad']['ad_type'] ?? '')) ?> / <?= h((string)($row['ad']['brand'] ?? '')) ?></td>
                        <td><?= h($row['suggestion']['ad_type']) ?> / <?= h($row['suggestion']['brand']) ?> / <?= h($row['suggestion']['listing_type']) ?></td>
                        <td><?= h($row['suggestion']['reason']) ?><?= !empty($row['suggestion']['confident']) ? ' (sigurno)' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
