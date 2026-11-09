<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$pageTitle = 'Katalog telefona — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'phone-catalog';

$bundle = phoneCatalogBundle();
$currentCount = !empty($bundle['ok']) ? count($bundle['models']) : 0;
$withSpecs = 0;
if (!empty($bundle['ok'])) {
    foreach ($bundle['models'] as $model) {
        if (($model['specs'] ?? []) !== []) {
            $withSpecs++;
        }
    }
}

$preview = $_SESSION['phone_catalog_wikidata_preview'] ?? null;
$resultFlash = $_SESSION['phone_catalog_wikidata_result'] ?? null;
unset($_SESSION['phone_catalog_wikidata_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_phone_catalog.php');
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'preview') {
        $sync = phoneCatalogWikidataSync(false);
        if (empty($sync['ok'])) {
            setFlash('danger', 'Wikidata pregled nije uspeo: ' . (string)($sync['error'] ?? 'greška'));
            unset($_SESSION['phone_catalog_wikidata_preview']);
        } else {
            $_SESSION['phone_catalog_wikidata_preview'] = [
                'at' => gmdate('c'),
                'summary' => $sync['summary'],
            ];
            setFlash('success', 'Pregled spreman: +' . (int)$sync['summary']['added'] . ' novih, ' . (int)$sync['summary']['updated'] . ' ažuriranih.');
        }
        header('Location: /admin_phone_catalog.php');
        exit;
    }

    if ($action === 'apply') {
        $sync = phoneCatalogWikidataSync(true);
        unset($_SESSION['phone_catalog_wikidata_preview']);
        if (empty($sync['ok'])) {
            setFlash('danger', 'Wikidata upis nije uspeo: ' . (string)($sync['error'] ?? 'greška'));
        } else {
            $_SESSION['phone_catalog_wikidata_result'] = $sync['summary'];
            setFlash(
                'success',
                'Katalog ažuriran. Ukupno modela: ' . (int)$sync['summary']['total']
                . ' (novih ' . (int)$sync['summary']['added']
                . ', ažuriranih ' . (int)$sync['summary']['updated'] . ').'
            );
        }
        header('Location: /admin_phone_catalog.php');
        exit;
    }

    if ($action === 'clear_preview') {
        unset($_SESSION['phone_catalog_wikidata_preview']);
        setFlash('success', 'Pregled obrisan.');
        header('Location: /admin_phone_catalog.php');
        exit;
    }

    setFlash('danger', 'Nepoznata akcija.');
    header('Location: /admin_phone_catalog.php');
    exit;
}

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <a href="/dashboard.php">Admin</a> › Katalog telefona</div>
        <h2 style="font-size:18px;margin-bottom:12px;">Katalog telefona</h2>

        <div class="stats-grid" style="margin-bottom:12px;">
            <div class="stat-card"><div class="label">Modela u katalogu</div><div class="value"><?= (int)$currentCount ?></div></div>
            <div class="stat-card"><div class="label">Sa specifikacijama</div><div class="value"><?= (int)$withSpecs ?></div></div>
            <div class="stat-card"><div class="label">Skladište</div><div class="value" style="font-size:14px;"><?= h(storageDriver()) ?></div></div>
        </div>

        <div class="form-card" style="padding:16px;">
            <h3 style="margin:0 0 8px;font-size:16px;">Wikidata sync</h3>
            <p style="margin:0 0 12px;color:var(--text-muted);font-size:14px;line-height:1.45;">
                Povlači modele pametnih telefona sa Wikidata (brendovi: Apple, Samsung, Google, Xiaomi, Huawei, OnePlus, Motorola, Oppo, Realme, Honor, Nothing, Asus),
                od <?= (int)phoneCatalogWikidataMinYear() ?>. godine. Upisuje samo podatke koji postoje na stavci — ne izmišlja bateriju, kameru ni RAM.
                Proizvođačke specifikacije (npr. Apple Support za iPhone 15) ostaju.
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <form method="post" action="/admin_phone_catalog.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="preview">
                    <button type="submit" class="btn">1. Pregledaj sync</button>
                </form>
                <form method="post" action="/admin_phone_catalog.php" onsubmit="return confirm('Upisati Wikidata sync u katalog?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="apply">
                    <button type="submit" class="btn" style="background:var(--kp-green);color:#fff;">2. Primeni sync</button>
                </form>
                <a class="btn" href="/katalog" target="_blank" rel="noopener">Otvori /katalog</a>
            </div>

            <?php if (is_array($preview) && is_array($preview['summary'] ?? null)): ?>
                <?php $s = $preview['summary']; ?>
                <div style="background:#f6f8f5;border:1px solid var(--border);border-radius:10px;padding:12px;font-size:14px;">
                    <strong>Poslednji pregled</strong>
                    <ul style="margin:8px 0 0;padding-left:18px;line-height:1.5;">
                        <li>Wikidata modela: <?= (int)($s['incoming'] ?? 0) ?> (sa specifikacijama <?= (int)($s['incoming_with_specs'] ?? 0) ?>)</li>
                        <li>Novih: <?= (int)($s['added'] ?? 0) ?></li>
                        <li>Ažuriranih: <?= (int)($s['updated'] ?? 0) ?></li>
                        <li>Ukupno posle spajanja: <?= (int)($s['total'] ?? 0) ?></li>
                    </ul>
                    <?php if (!empty($s['added_ids']) && is_array($s['added_ids'])): ?>
                        <p style="margin:8px 0 0;color:var(--text-muted);font-size:13px;">
                            Primer novih: <?= h(implode(', ', array_slice($s['added_ids'], 0, 12))) ?>
                        </p>
                    <?php endif; ?>
                    <form method="post" action="/admin_phone_catalog.php" style="margin-top:10px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="clear_preview">
                        <button type="submit" class="btn" style="background:#fff;">Obriši pregled</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (is_array($resultFlash)): ?>
                <div style="margin-top:12px;background:#e8f5e9;border:1px solid #9ccfa6;border-radius:10px;padding:12px;font-size:14px;">
                    Poslednji upis: ukupno <?= (int)($resultFlash['total'] ?? 0) ?>,
                    novih <?= (int)($resultFlash['added'] ?? 0) ?>,
                    ažuriranih <?= (int)($resultFlash['updated'] ?? 0) ?>.
                </div>
            <?php endif; ?>
        </div>

        <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
            CLI: <code>php tools/sync_phone_catalog_wikidata.php</code> pa
            <code>php tools/sync_phone_catalog_wikidata.php --apply</code>
        </p>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
