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
$byBrandCounts = [];
if (!empty($bundle['ok'])) {
    foreach ($bundle['models'] as $model) {
        if (($model['specs'] ?? []) !== []) {
            $withSpecs++;
        }
        $slug = (string)($model['brand_slug'] ?? '');
        if ($slug !== '') {
            $byBrandCounts[$slug] = ($byBrandCounts[$slug] ?? 0) + 1;
        }
    }
}

$adGroups = phoneCatalogCollectAdModelGroups();
$adGap = phoneCatalogAdModelGapSummary($adGroups);
$missingGroups = $adGap['missing'];
$noSpecsGroups = array_values(array_filter(
    $adGroups,
    static fn(array $g): bool => ($g['status'] ?? '') === 'linked' && empty($g['has_specs'])
));

$stages = phoneCatalogWikidataBrandStages();
$preview = $_SESSION['phone_catalog_wikidata_preview'] ?? null;
$resultFlash = $_SESSION['phone_catalog_wikidata_result'] ?? null;
$adsResultFlash = $_SESSION['phone_catalog_ads_result'] ?? null;
unset($_SESSION['phone_catalog_wikidata_result'], $_SESSION['phone_catalog_ads_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_phone_catalog.php');
    $action = trim((string)($_POST['action'] ?? ''));
    $brandSlug = phoneCatalogWikidataNormalizeBrandSlug((string)($_POST['brand'] ?? ''));

    if ($action === 'preview' || $action === 'apply') {
        $apply = $action === 'apply';
        $sync = phoneCatalogWikidataSync($apply, null, $brandSlug);
        $brandLabel = $brandSlug ?? 'svi brendovi';
        if (empty($sync['ok'])) {
            setFlash('danger', 'Wikidata ' . ($apply ? 'upis' : 'pregled') . ' nije uspeo (' . $brandLabel . '): ' . (string)($sync['error'] ?? 'greška'));
            if (!$apply) {
                unset($_SESSION['phone_catalog_wikidata_preview']);
            }
        } elseif ($apply) {
            unset($_SESSION['phone_catalog_wikidata_preview']);
            $_SESSION['phone_catalog_wikidata_result'] = $sync['summary'];
            setFlash(
                'success',
                'Sync primenjen (' . $brandLabel . '). Ukupno: ' . (int)$sync['summary']['total']
                . ' (novih ' . (int)$sync['summary']['added']
                . ', ažuriranih ' . (int)$sync['summary']['updated']
                . ', sa Wikidata ' . (int)$sync['summary']['incoming'] . ').'
            );
        } else {
            $_SESSION['phone_catalog_wikidata_preview'] = [
                'at' => gmdate('c'),
                'brand' => $brandSlug ?? 'all',
                'summary' => $sync['summary'],
            ];
            setFlash(
                'success',
                'Pregled (' . $brandLabel . '): Wikidata ' . (int)$sync['summary']['incoming']
                . ', novih ' . (int)$sync['summary']['added']
                . ', ažuriranih ' . (int)$sync['summary']['updated'] . '.'
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

    if ($action === 'add_from_ads' || $action === 'add_from_ads_all') {
        $keys = [];
        if ($action === 'add_from_ads') {
            $rawKeys = $_POST['keys'] ?? [];
            if (!is_array($rawKeys)) {
                $rawKeys = [];
            }
            foreach ($rawKeys as $key) {
                $key = trim((string)$key);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
            if ($keys === []) {
                setFlash('danger', 'Izaberi bar jedan model sa liste.');
                header('Location: /admin_phone_catalog.php#ads-models');
                exit;
            }
        }
        $linkAfter = !empty($_POST['link_after']);
        $result = phoneCatalogAddModelsFromAds($keys, true, $linkAfter);
        if (empty($result['ok'])) {
            setFlash('danger', 'Dodavanje nije uspelo: ' . (string)($result['error'] ?? 'greška'));
        } else {
            $_SESSION['phone_catalog_ads_result'] = $result;
            $s = $result['summary'];
            $msg = 'Dodato u katalog: ' . (int)$s['added']
                . ' (preskočeno postojećih ' . (int)$s['skipped_existing']
                . ', ukupno modela ' . (int)$s['total'] . ').';
            if (!empty($result['linked']) && is_array($result['linked'])) {
                $msg .= ' Povezano oglasa: ' . (int)$result['linked']['linked']
                    . ', već povezano ' . (int)$result['linked']['already']
                    . ', bez poklapanja ' . (int)$result['linked']['unmatched'] . '.';
            }
            setFlash('success', $msg);
        }
        header('Location: /admin_phone_catalog.php#ads-models');
        exit;
    }

    if ($action === 'link_ads') {
        $plan = phoneCatalogPlanLinks(getAllAds(), true, false);
        writeJsonFile('ads.json', $plan['ads']);
        $ls = $plan['summary'];
        setFlash(
            'success',
            'Povezivanje oglasa: novo ' . (int)$ls['linked']
            . ', već ' . (int)$ls['already']
            . ', bez poklapanja ' . (int)$ls['unmatched']
            . ', dvosmisleno ' . (int)$ls['ambiguous'] . '.'
        );
        header('Location: /admin_phone_catalog.php#ads-models');
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
            <div class="stat-card"><div class="label">Modela na oglasima</div><div class="value"><?= (int)$adGap['total'] ?></div></div>
            <div class="stat-card"><div class="label">Nedostaju u katalogu</div><div class="value"><?= (int)count($missingGroups) ?></div></div>
            <div class="stat-card"><div class="label">Skladište</div><div class="value" style="font-size:14px;"><?= h(storageDriver()) ?></div></div>
        </div>

        <div class="form-card" id="ads-models" style="padding:16px;margin-bottom:14px;">
            <h3 style="margin:0 0 8px;font-size:16px;">Modeli sa sajta → katalog</h3>
            <p style="margin:0 0 12px;color:var(--text-muted);font-size:14px;line-height:1.45;">
                Uzima jedinstvene <strong>brand + model</strong> sa telefonskih oglasa.
                Nedostajuće dodaje kao stub (identitet, bez spekova), pa može da poveže oglase.
                Specifikacije se kasnije pune iz Wikidata ili proizvođača — ne pogađaju se.
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:10px 16px;margin-bottom:12px;font-size:13px;color:var(--text-muted);">
                <span>U katalogu: <strong style="color:var(--text);"><?= (int)$adGap['linked'] ?></strong></span>
                <span>Bez spekova: <strong style="color:var(--text);"><?= (int)$adGap['linked_without_specs'] ?></strong></span>
                <span>Nedostaje: <strong style="color:var(--text);"><?= (int)$adGap['unmatched'] ?></strong></span>
                <span>Dvosmisleno: <strong style="color:var(--text);"><?= (int)$adGap['ambiguous'] ?></strong></span>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                <form method="post" action="/admin_phone_catalog.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="link_ads">
                    <button type="submit" class="btn">Poveži postojeće oglase</button>
                </form>
                <?php if ($missingGroups !== []): ?>
                    <form method="post" action="/admin_phone_catalog.php" onsubmit="return confirm('Dodati SVE modele koji nedostaju u katalog (bez spekova)?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_from_ads_all">
                        <label style="display:inline-flex;align-items:center;gap:6px;margin-right:8px;font-size:13px;">
                            <input type="checkbox" name="link_after" value="1" checked>
                            i poveži oglase
                        </label>
                        <button type="submit" class="btn" style="background:var(--kp-green);color:#fff;">Dodaj sve koje nedostaju</button>
                    </form>
                <?php endif; ?>
                <a class="btn" href="/katalog" target="_blank" rel="noopener">Otvori /katalog</a>
            </div>

            <?php if (is_array($adsResultFlash)): ?>
                <?php $as = $adsResultFlash['summary'] ?? []; ?>
                <div style="margin-bottom:12px;background:#e8f5e9;border:1px solid #9ccfa6;border-radius:10px;padding:12px;font-size:14px;">
                    Poslednje dodavanje: novih <?= (int)($as['added'] ?? 0) ?>,
                    preskočeno <?= (int)($as['skipped_existing'] ?? 0) ?>,
                    ukupno modela <?= (int)($as['total'] ?? 0) ?>.
                    <?php if (!empty($as['added_ids']) && is_array($as['added_ids'])): ?>
                        <div style="margin-top:6px;color:var(--text-muted);font-size:13px;">
                            Primer: <?= h(implode(', ', array_slice($as['added_ids'], 0, 12))) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($missingGroups === []): ?>
                <p style="margin:0;font-size:14px;color:var(--text-muted);">
                    Svi modeli sa telefonskih oglasa već postoje u katalogu (ili su poklopljeni).
                </p>
            <?php else: ?>
                <form method="post" action="/admin_phone_catalog.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_from_ads">
                    <div style="overflow:auto;max-height:420px;border:1px solid var(--border);border-radius:10px;">
                        <table class="table" style="margin:0;font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th>Marka</th>
                                    <th>Model (oglas)</th>
                                    <th>Predloženi ID</th>
                                    <th>Oglasa</th>
                                    <th>Aktivnih</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($missingGroups as $group): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="keys[]" value="<?= h((string)$group['key']) ?>" checked>
                                        </td>
                                        <td><?= h((string)$group['brand']) ?></td>
                                        <td><?= h((string)$group['model']) ?></td>
                                        <td><code style="font-size:12px;"><?= h((string)$group['proposed_id']) ?></code></td>
                                        <td><?= (int)$group['ad_count'] ?></td>
                                        <td><?= (int)$group['offer_count'] ?></td>
                                        <td><?= h((string)$group['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" name="link_after" value="1" checked>
                            posle dodavanja poveži oglase
                        </label>
                        <button type="submit" class="btn" style="background:var(--kp-green);color:#fff;">Dodaj izabrane u katalog</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($noSpecsGroups !== []): ?>
                <details style="margin-top:14px;">
                    <summary style="cursor:pointer;font-size:14px;">
                        U katalogu, bez specifikacija (<?= count($noSpecsGroups) ?>)
                    </summary>
                    <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;line-height:1.5;color:var(--text-muted);max-height:220px;overflow:auto;">
                        <?php foreach (array_slice($noSpecsGroups, 0, 80) as $group): ?>
                            <li>
                                <?= h((string)$group['brand']) ?> <?= h((string)$group['model']) ?>
                                <?php if (!empty($group['model_id'])): ?>
                                    — <code><?= h((string)$group['model_id']) ?></code>
                                <?php endif; ?>
                                (<?= (int)$group['offer_count'] ?> aktivnih)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>

        <div class="form-card" style="padding:16px;">
            <h3 style="margin:0 0 8px;font-size:16px;">Wikidata sync</h3>
            <p style="margin:0 0 12px;color:var(--text-muted);font-size:14px;line-height:1.45;">
                Povlači modele sa Wikidata (smartphone + mobile phone), od <?= (int)phoneCatalogWikidataMinYear() ?>. godine.
                Upisuje samo podatke sa stavke — ne izmišlja bateriju/kameru/RAM i ne vuče slike.
                Proizvođačke specifikacije (npr. Apple Support) ostaju.
                Preporuka: sync <strong>po brendu</strong> (etape), da upit ne timeout-uje.
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                <form method="post" action="/admin_phone_catalog.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="preview">
                    <input type="hidden" name="brand" value="all">
                    <button type="submit" class="btn">Pregledaj sve</button>
                </form>
                <form method="post" action="/admin_phone_catalog.php" onsubmit="return confirm('Upisati Wikidata sync za SVE brendove? Bolje po etapama.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="brand" value="all">
                    <button type="submit" class="btn" style="background:var(--kp-green);color:#fff;">Primeni sve</button>
                </form>
            </div>

            <h4 style="margin:0 0 8px;font-size:14px;">Etape po brendu</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:12px;">
                <?php foreach ($stages as $stage):
                    $slug = (string)$stage['slug'];
                    $name = (string)$stage['brand'];
                    $have = (int)($byBrandCounts[$slug] ?? 0);
                ?>
                    <div style="border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:#fff;">
                        <div style="font-weight:700;margin-bottom:2px;"><?= h($name) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">u katalogu: <?= $have ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <form method="post" action="/admin_phone_catalog.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="preview">
                                <input type="hidden" name="brand" value="<?= h($slug) ?>">
                                <button type="submit" class="btn" style="padding:6px 10px;font-size:12px;">Pregled</button>
                            </form>
                            <form method="post" action="/admin_phone_catalog.php" onsubmit="return confirm('Primeni Wikidata sync za <?= h($name) ?>?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="apply">
                                <input type="hidden" name="brand" value="<?= h($slug) ?>">
                                <button type="submit" class="btn" style="padding:6px 10px;font-size:12px;background:var(--kp-green);color:#fff;">Primeni</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (is_array($preview) && is_array($preview['summary'] ?? null)): ?>
                <?php $s = $preview['summary']; ?>
                <div style="background:#f6f8f5;border:1px solid var(--border);border-radius:10px;padding:12px;font-size:14px;">
                    <strong>Poslednji pregled</strong>
                    <span style="color:var(--text-muted);">(<?= h((string)($preview['brand'] ?? ($s['brand'] ?? 'all'))) ?>)</span>
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
                    Poslednji upis (<?= h((string)($resultFlash['brand'] ?? 'all')) ?>):
                    ukupno <?= (int)($resultFlash['total'] ?? 0) ?>,
                    novih <?= (int)($resultFlash['added'] ?? 0) ?>,
                    ažuriranih <?= (int)($resultFlash['updated'] ?? 0) ?>,
                    Wikidata <?= (int)($resultFlash['incoming'] ?? 0) ?>.
                </div>
            <?php endif; ?>
        </div>

        <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
            CLI: <code>php tools/sync_phone_catalog_wikidata.php --brand=apple --apply</code>
            · <code>php tools/link_phone_models.php --apply</code>
        </p>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
