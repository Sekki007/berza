<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$brand = trim((string)($_GET['brand'] ?? ''));
$modelId = trim((string)($_GET['model'] ?? ''));
$storage = trim((string)($_GET['storage'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? 'Polovno'));
$stats = null;
$picked = null;
if ($modelId !== '') {
    $picked = phoneCatalogModel($modelId);
    $stats = priceStats($modelId, $storage !== '' ? $storage : null, $condition);
}

$pageTitle = 'Koliko vredi moj telefon | KupiTelefon.rs';
$pageDescription = 'Informativna procena iz oglasa na KupiTelefon.rs. Nije otkup i ne čuva lične podatke.';
$canonicalUrl = absoluteUrl('/koliko-vredi-moj-telefon');
$robotsMeta = $modelId !== '' ? 'noindex,follow' : '';
$activePage = 'cene';

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/">Početna</a> › <a href="/cene-polovnih-telefona">Cene</a> › Procena</div>
        <h1>Koliko vredi moj telefon?</h1>
        <p class="listing-intro">Procena je informativna, iz oglasa na sajtu. Ništa se ne čuva.</p>
        <form method="get" action="/koliko-vredi-moj-telefon" class="form-card">
            <label>Model iz kataloga
                <select name="model">
                    <option value="">Izaberi</option>
                    <?php foreach (phoneCatalogModels() as $model): ?>
                        <?php if (!phoneCatalogModelIsVerified($model)) continue; ?>
                        <option value="<?= h((string)$model['id']) ?>" <?= $modelId === (string)$model['id'] ? 'selected' : '' ?>><?= h($model['brand'] . ' ' . $model['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Memorija <input name="storage" value="<?= h($storage) ?>" placeholder="128GB"></label>
            <label>Stanje
                <select name="condition">
                    <?php foreach (['Polovno', 'Novo', 'Kao novo'] as $state): ?>
                        <option <?= $condition === $state ? 'selected' : '' ?>><?= h($state) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn-sm btn-sm-primary" type="submit">Procena</button>
        </form>
        <?php if ($picked && $stats): ?>
            <?php
            $prefill = '/postavi-oglas?' . http_build_query([
                'brand' => $picked['brand'],
                'model' => $picked['name'],
                'storage' => $storage,
                'price' => (int)round($stats['median']),
            ]);
            ?>
            <p>Raspon <?= h(number_format($stats['p25'], 0, ',', '.')) ?>–<?= h(number_format($stats['p75'], 0, ',', '.')) ?> €, medijana <?= h(number_format($stats['median'], 0, ',', '.')) ?> € (<?= (int)$stats['count'] ?> cena).</p>
            <p><a class="btn-sm btn-sm-primary" href="<?= h($prefill) ?>">Postavi oglas za <?= h((string)$picked['name']) ?> besplatno</a></p>
        <?php elseif ($modelId !== ''): ?>
            <p>Premalo podataka za ovaj model.</p>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
