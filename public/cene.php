<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$month = ['januar','februar','mart','april','maj','jun','jul','avgust','septembar','oktobar','novembar','decembar'][(int)date('n') - 1];
$pageTitle = seoFitTitle('Cene polovnih telefona – ' . $month . ' ' . date('Y'));
$pageDescription = seoFitDescription('Medijana i raspon cena polovnih telefona iz oglasa na KupiTelefon.rs. Prikazuju se samo modeli sa bar 3 cene. Informativno, nije otkup.');
$canonicalUrl = absoluteUrl('/cene-polovnih-telefona');
$activePage = 'cene';
$robotsMeta = '';

$byModel = [];
foreach (priceEventLog() as $row) {
    $modelId = trim((string)($row['model_id'] ?? ''));
    if ($modelId === '' || (float)($row['price'] ?? 0) <= 0) {
        continue;
    }
    $byModel[$modelId][] = (float)$row['price'];
}
$rows = [];
foreach ($byModel as $modelId => $prices) {
    $stats = priceStatsFromValues($prices);
    if ($stats === null) {
        continue;
    }
    $model = function_exists('phoneCatalogModel') ? phoneCatalogModel($modelId) : null;
    if (!is_array($model)) {
        continue;
    }
    $rows[] = ['model' => $model, 'stats' => $stats];
}

$jsonLd = seoBreadcrumbJsonLd([
    ['name' => 'Početna', 'url' => '/'],
    ['name' => 'Cene', 'url' => '/cene-polovnih-telefona'],
]);

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/">Početna</a> › Cene</div>
        <header class="listing-landing-head">
            <h1>Cene polovnih telefona u Srbiji – <?= h($month . ' ' . date('Y')) ?></h1>
            <p class="listing-intro">Medijana i raspon računaju se iz oglasa na KupiTelefon.rs. Model sa manje od 3 cena nije u tabeli. Cene 0 i ekstremi (ispod 20% ili iznad 300% medijane) se izbacuju. Ovo nije ponuda otkupa.</p>
        </header>
        <?php if ($rows === []): ?>
            <p>Još nema dovoljno događaja cena. Posle backfill-a tabela se popunjava.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Model</th><th>Medijana</th><th>Raspon</th><th>Oglasa</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= h(phoneCatalogUrl($row['model'])) ?>"><?= h($row['model']['brand'] . ' ' . $row['model']['name']) ?></a></td>
                        <td><?= h(number_format($row['stats']['median'], 0, ',', '.')) ?> €</td>
                        <td><?= h(number_format($row['stats']['min'], 0, ',', '.')) ?>–<?= h(number_format($row['stats']['max'], 0, ',', '.')) ?> €</td>
                        <td><?= (int)$row['stats']['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p><a class="btn-sm btn-sm-primary" href="/koliko-vredi-moj-telefon">Koliko vredi moj telefon?</a>
        <a class="btn-sm" href="/postavi-oglas">Prodaj telefon besplatno</a></p>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
