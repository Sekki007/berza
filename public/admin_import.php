<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$step = trim((string)($_GET['step'] ?? 'upload'));
$users = getUsers();
$schema = adFormSchema();
$categoryGroupsJson = kpImportCategoryGroupsJson();
$importSession = $_SESSION['kp_import'] ?? null;
$result = $_SESSION['kp_import_result'] ?? null;
unset($_SESSION['kp_import_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_import.php' . ($step !== 'upload' ? '?step=' . urlencode($step) : ''));
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'cancel') {
        unset($_SESSION['kp_import']);
        setFlash('success', 'Uvoz je otkazan.');
        header('Location: /admin_import.php');
        exit;
    }

    if ($action === 'parse') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId <= 0 || !findUserById($targetUserId)) {
            setFlash('danger', 'Izaberi korisnika za uvoz.');
            header('Location: /admin_import.php');
            exit;
        }

        $jsonText = trim((string)($_POST['json_text'] ?? ''));
        if (!empty($_FILES['json_file']['tmp_name']) && is_uploaded_file($_FILES['json_file']['tmp_name'])) {
            $jsonText = (string)file_get_contents($_FILES['json_file']['tmp_name']);
        }

        $parsed = kpParseImportJson($jsonText);
        if (!$parsed['ok']) {
            setFlash('danger', (string)$parsed['error']);
            header('Location: /admin_import.php');
            exit;
        }

        $targetUser = findUserById($targetUserId);
        $_SESSION['kp_import'] = [
            'target_user_id' => $targetUserId,
            'data' => $parsed['data'],
            'mappings' => kpBuildDefaultMappings(
                $parsed['data']['ads'],
                $targetUser,
                $parsed['data']['seller'] ?? null
            ),
            'progress' => [
                'imported' => 0,
                'blocked_duplicates' => 0,
                'skipped' => 0,
                'errors' => [],
                'ad_ids' => [],
                'stages' => 0,
            ],
        ];

        header('Location: /admin_import.php?step=preview');
        exit;
    }

    if ($action === 'bulk_session') {
        if (!is_array($importSession) || empty($importSession['mappings'])) {
            setFlash('danger', 'Nema učitane liste za uvoz.');
            header('Location: /admin_import.php');
            exit;
        }
        $mode = trim((string)($_POST['bulk_mode'] ?? ''));
        $adType = trim((string)($_POST['bulk_ad_type'] ?? ''));
        foreach ($importSession['mappings'] as $i => &$map) {
            if (!is_array($map) || !empty($map['imported'])) {
                continue;
            }
            $isDup = !empty($map['blocked_duplicate']) || (int)($map['duplicate_ad_id'] ?? 0) > 0;
            if ($mode === 'select_new' && !$isDup) {
                $map['selected'] = 1;
            } elseif ($mode === 'select_none') {
                $map['selected'] = 0;
            } elseif ($mode === 'activate_selected' && !empty($map['selected']) && !$isDup) {
                $map['is_active'] = 1;
            } elseif ($mode === 'apply_type' && !empty($map['selected']) && !$isDup && in_array($adType, ['telefon', 'delovi', 'servis'], true)) {
                $map['ad_type'] = $adType;
                if ($adType === 'telefon') {
                    $map['category_group'] = 'phones';
                } elseif ($adType === 'servis') {
                    $map['category_group'] = 'service';
                }
            }
        }
        unset($map);
        $_SESSION['kp_import'] = $importSession;
        setFlash('success', 'Bulk podešavanje sačuvano.');
        header('Location: /admin_import.php?step=preview');
        exit;
    }

    if ($action === 'import_stage') {
        if (!is_array($importSession) || empty($importSession['mappings'])) {
            setFlash('danger', 'Nema učitane liste za uvoz. Počni ponovo.');
            header('Location: /admin_import.php');
            exit;
        }

        @set_time_limit(300);
        $batchSize = (int)($_POST['batch_size'] ?? 25);
        $autoContinue = !empty($_POST['auto_continue']);

        $stage = kpImportSessionStage($importSession, $batchSize);
        $_SESSION['kp_import'] = $stage['session'];
        $progress = $stage['session']['progress'] ?? [];

        if ($stage['done']) {
            $_SESSION['kp_import_result'] = [
                'imported' => (int)($progress['imported'] ?? 0),
                'skipped' => (int)($progress['skipped'] ?? 0),
                'blocked_duplicates' => (int)($progress['blocked_duplicates'] ?? 0),
                'errors' => is_array($progress['errors'] ?? null) ? $progress['errors'] : [],
                'ad_ids' => is_array($progress['ad_ids'] ?? null) ? $progress['ad_ids'] : [],
            ];
            unset($_SESSION['kp_import']);
            setFlash(
                'success',
                'Uvoz završen po etapama: ' . (int)($progress['imported'] ?? 0) . ' oglasa' .
                ((int)($progress['blocked_duplicates'] ?? 0) > 0
                    ? ', blokirano duplikata ' . (int)$progress['blocked_duplicates']
                    : '') . '.'
            );
            header('Location: /admin_import.php?step=done');
            exit;
        }

        $msg = 'Etapa ' . (int)($progress['stages'] ?? 0) . ': uvezeno +' .
            (int)($progress['last_batch_imported'] ?? 0) .
            ' (ukupno ' . (int)($progress['imported'] ?? 0) . '). Ostalo: ' .
            (int)$stage['pending_left'] . '.';
        setFlash('success', $msg);

        if ($autoContinue && $stage['pending_left'] > 0) {
            header('Location: /admin_import.php?step=preview&auto=1&batch=' . max(1, min(100, $batchSize)));
            exit;
        }

        header('Location: /admin_import.php?step=preview');
        exit;
    }
}

$targetUser = null;
$shopCategories = [];
$seller = [];
$adsCount = 0;
$mappings = [];
$dupCount = 0;
$readyCount = 0;
$importedCount = 0;
$pendingCount = 0;
$progress = [];

if (is_array($importSession)) {
    $targetUser = findUserById((int)($importSession['target_user_id'] ?? 0));
    if ($targetUser) {
        $shopCategories = getShopCategories($targetUser);
    }
    $seller = is_array($importSession['data']['seller'] ?? null) ? $importSession['data']['seller'] : [];
    $adsCount = count($importSession['data']['ads'] ?? []);
    $mappings = is_array($importSession['mappings'] ?? null) ? $importSession['mappings'] : [];
    $progress = is_array($importSession['progress'] ?? null) ? $importSession['progress'] : [];
    foreach ($mappings as $map) {
        if (!empty($map['imported'])) {
            $importedCount++;
            continue;
        }
        if (!empty($map['blocked_duplicate']) || (int)($map['duplicate_ad_id'] ?? 0) > 0) {
            $dupCount++;
        } elseif (!empty($map['selected'])) {
            $readyCount++;
        }
    }
    $pendingCount = count(kpPendingImportIndexes($mappings));
}

$autoContinue = isset($_GET['auto']) && (string)$_GET['auto'] === '1';
$autoBatch = max(1, min(100, (int)($_GET['batch'] ?? 25)));

if ($step === 'preview' && (!is_array($importSession) || $mappings === [])) {
    header('Location: /admin_import.php');
    exit;
}

$pageTitle = 'KP uvoz oglasa — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'import';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › KP uvoz</div>
        <h2 style="font-size:18px;margin-bottom:8px;">Uvoz oglasa sa KupujemProdajem</h2>
        <p class="form-hint" style="margin-bottom:14px;">
            Učitaj JSON iz Chrome ekstenzije, pregledaj mapiranje kategorija, pa uvezi oglase i slike za izabranog korisnika.
        </p>

        <?php if ($step === 'done' && is_array($result)): ?>
            <section class="form-card">
                <h3>Rezultat uvoza</h3>
                <p><strong>Uvezeno:</strong> <?= (int)$result['imported'] ?> ·
                    <strong>Blokirano duplikata:</strong> <?= (int)($result['blocked_duplicates'] ?? 0) ?> ·
                    <strong>Preskočeno:</strong> <?= (int)$result['skipped'] ?></p>
                <?php if (!empty($result['ad_ids'])): ?>
                    <p style="font-size:13px;">ID novih oglasa: <?= h(implode(', ', array_map('strval', $result['ad_ids']))) ?></p>
                <?php endif; ?>
                <?php if (!empty($result['errors'])): ?>
                    <ul style="margin-top:10px;color:#a33;font-size:13px;">
                        <?php foreach ($result['errors'] as $err): ?>
                            <li><?= h((string)$err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p style="margin-top:14px;">
                    <a class="btn-call" href="/admin_import.php" style="display:inline-block;width:auto;padding:8px 16px;">Novi uvoz</a>
                    <a class="btn-sm" href="/ads.php">Svi oglasi</a>
                </p>
            </section>

        <?php elseif ($step === 'preview' && is_array($importSession) && $targetUser): ?>
            <section class="form-card" style="margin-bottom:12px;">
                <h3>Pregled pre uvoza</h3>
                <div class="kp-import-summary">
                    <div><strong>Korisnik:</strong> <?= h((string)$targetUser['username']) ?>
                        <?php if (!empty($targetUser['shop_name'])): ?>
                            (<?= h((string)$targetUser['shop_name']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php if ($seller !== []): ?>
                        <div><strong>KP prodavac:</strong> <?= h((string)($seller['display_name'] ?? $seller['username'] ?? '')) ?>
                            <?php if (!empty($seller['reviews_positive'])): ?>
                                · 👍 <?= (int)$seller['reviews_positive'] ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div><strong>Oglasa u JSON:</strong> <?= (int)$adsCount ?>
                        · spremno <?= (int)$pendingCount ?>
                        <?php if ($importedCount > 0): ?>
                            · uvezeno u sesiji <?= (int)$importedCount ?>
                        <?php endif; ?>
                        <?php if ($dupCount > 0): ?>
                            · <span style="color:#b45309;">duplikata <?= (int)$dupCount ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($progress['stages'])): ?>
                        <div><strong>Napredak:</strong> etapa <?= (int)$progress['stages'] ?> ·
                            ukupno uvezeno <?= (int)($progress['imported'] ?? 0) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($dupCount > 0): ?>
                    <p class="form-hint" style="margin-top:8px;color:#b45309;">
                        Duplikati su blokirani. Veliki uvoz (npr. 600+) radi se <strong>po etapama</strong> — ne šalje celu tabelu odjednom (to ruši sesiju).
                    </p>
                <?php else: ?>
                    <p class="form-hint" style="margin-top:8px;">
                        Za velike liste koristi uvoz po etapama ispod (25–50 po koraku). Cela tabela se ne šalje u jednom POST-u.
                    </p>
                <?php endif; ?>

                <form method="POST" class="kp-import-bulk" style="margin-top:12px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="bulk_session">
                    <label>Bulk tip
                        <select name="bulk_ad_type" id="bulkAdType">
                            <option value="">—</option>
                            <option value="telefon">Telefoni</option>
                            <option value="delovi">Delovi</option>
                            <option value="servis">Servis</option>
                        </select>
                    </label>
                    <button type="submit" class="btn-sm" name="bulk_mode" value="apply_type">Primeni tip na označene</button>
                    <button type="submit" class="btn-sm" name="bulk_mode" value="select_new">Označi nove</button>
                    <button type="submit" class="btn-sm" name="bulk_mode" value="select_none">Poništi oznake</button>
                    <button type="submit" class="btn-sm" name="bulk_mode" value="activate_selected">Aktiviraj označene</button>
                </form>

                <form method="POST" id="kpStageForm" class="kp-stage-box" style="margin-top:14px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="import_stage">
                    <strong>Uvoz po etapama</strong>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:8px;">
                        <label>Po etapi
                            <select name="batch_size" id="batchSize">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>
                        <label class="kp-check-line" style="margin:0;">
                            <input type="checkbox" name="auto_continue" value="1" id="autoContinue" checked>
                            Nastavi automatski do kraja
                        </label>
                        <button type="submit" class="btn-call" style="width:auto;min-width:200px;" <?= $pendingCount <= 0 ? 'disabled' : '' ?>>
                            Uvezi sledeću etapu (<?= (int)$pendingCount ?> ostalo)
                        </button>
                    </div>
                    <p class="form-hint" style="margin-top:8px;">
                        Preporuka za 696 oglasa: <strong>25</strong> po etapi + auto nastavak. Ne zatvaraj tab dok radi.
                    </p>
                </form>
            </section>

            <div class="form-card table-scroll kp-import-table-wrap">
                    <table class="admin-table kp-import-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th style="width:56px;">Slika</th>
                                <th>Naslov / opis</th>
                                <th style="width:110px;">Tip</th>
                                <th style="width:100px;">Brend</th>
                                <th style="width:120px;">Cena</th>
                                <th style="width:110px;">Lokacija</th>
                                <th style="width:90px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappings as $i => $map): ?>
                                <?php
                                $thumb = '';
                                foreach ($map['image_urls'] ?? [] as $imgUrl) {
                                    $thumb = (string)$imgUrl;
                                    break;
                                }
                                $dupId = (int)($map['duplicate_ad_id'] ?? 0);
                                $isImported = !empty($map['imported']);
                                $isDup = !$isImported && (!empty($map['blocked_duplicate']) || $dupId > 0 || trim((string)($map['duplicate_reason'] ?? '')) !== '');
                                $dupReason = trim((string)($map['duplicate_reason'] ?? ''));
                                if ($dupReason === '' && $dupId > 0) {
                                    $dupReason = 'već uvezen (#' . $dupId . ')';
                                }
                                $rowClass = $isImported ? ' kp-import-done' : ($isDup ? ' kp-import-dup' : '');
                                ?>
                                <tr class="kp-import-row<?= $rowClass ?>" data-index="<?= (int)$i ?>">
                                    <td>
                                        <?php if ($isImported): ?>
                                            ✓
                                        <?php elseif ($isDup): ?>
                                            ⛔
                                        <?php elseif (!empty($map['selected'])): ?>
                                            ●
                                        <?php else: ?>
                                            ○
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($thumb !== ''): ?>
                                            <img src="<?= h($thumb) ?>" alt="" class="kp-import-thumb" loading="lazy">
                                        <?php else: ?>
                                            <span class="kp-import-noimg">—</span>
                                        <?php endif; ?>
                                        <div class="kp-import-imgmeta"><?= (int)($map['image_count'] ?? 0) ?> sl.</div>
                                    </td>
                                    <td>
                                        <div class="kp-field-title"><?= h((string)($map['title'] ?? '')) ?></div>
                                        <div class="kp-field-desc" style="max-height:40px;overflow:hidden;"><?= h(mb_substr((string)($map['description'] ?? ''), 0, 160)) ?></div>
                                        <?php if ($isImported): ?>
                                            <div class="kp-import-ok">Uvezeno</div>
                                        <?php elseif ($isDup): ?>
                                            <div class="kp-import-warn">⛔ <?= h($dupReason) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($map['source_url'])): ?>
                                            <a href="<?= h((string)$map['source_url']) ?>" target="_blank" rel="noopener" style="font-size:11px;">KP link</a>
                                        <?php endif; ?>
                                        <?php if (!empty($map['source_id'])): ?>
                                            <span style="font-size:10px;color:#888;"> · KP #<?= h((string)$map['source_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= h((string)($map['ad_type'] ?? '')) ?><br>
                                        <?= h((string)($map['listing_type'] ?? '')) ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= h((string)($map['brand'] ?? '')) ?><br>
                                        <?= h((string)($map['model'] ?? '')) ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= h((string)(int)($map['price'] ?? 0)) ?>
                                        <?= h(strtoupper((string)($map['currency'] ?? 'eur'))) ?>
                                    </td>
                                    <td style="font-size:12px;"><?= h((string)($map['location'] ?? '')) ?></td>
                                    <td style="font-size:11px;">
                                        <?= !empty($map['is_active']) ? 'aktivan' : 'neaktivan' ?><br>
                                        <?= !empty($map['download_images']) ? 'slike' : 'bez slika' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>

            <form method="POST" style="margin-top:12px;margin-bottom:12px;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn-sm">Otkaži uvoz</button>
            </form>

            <datalist id="brandList">
                <?php foreach ($schema['phone_brands'] as $brand): ?>
                    <option value="<?= h($brand) ?>">
                <?php endforeach; ?>
            </datalist>

        <?php else: ?>
            <section class="form-card">
                <h3>1. JSON fajl i korisnik</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="parse">
                    <div class="form-group">
                        <label>Korisnik (vlasnik oglasa)</label>
                        <select name="target_user_id" required>
                            <option value="">Izaberi korisnika…</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>">
                                    <?= h((string)$u['username']) ?>
                                    <?php if (!empty($u['shop_name'])): ?> — <?= h((string)$u['shop_name']) ?><?php endif; ?>
                                    <?php if (!empty($u['phone'])): ?> · <?= h((string)$u['phone']) ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Korisnik mora imati telefon u profilu — koristi se kao kontakt na oglasima.</p>
                    </div>
                    <div class="form-group">
                        <label>JSON fajl iz ekstenzije</label>
                        <input type="file" name="json_file" accept=".json,application/json">
                    </div>
                    <div class="form-group">
                        <label>Ili nalepi JSON</label>
                        <textarea name="json_text" rows="8" placeholder='{"seller":{...},"ads":[...]}'></textarea>
                    </div>
                    <button type="submit" class="btn-call" style="width:auto;min-width:160px;">Učitaj i pregledaj</button>
                </form>
            </section>

            <section class="form-card">
                <h3>Kako radi</h3>
                <ol style="font-size:13px;line-height:1.6;padding-left:18px;">
                    <li>Chrome ekstenzija → Pokupi oglase → Preuzmi JSON</li>
                    <li>Ovde izaberi korisnika i učitaj JSON</li>
                    <li>Pregledaj listu — podesi tip, kategoriju, brend, cenu</li>
                    <li>Uvezi — slike se preuzimaju sa KP u <code>/uploads/ads/</code></li>
                </ol>
            </section>
        <?php endif; ?>
    </main>
</div>

<style>
.kp-import-summary { font-size: 13px; line-height: 1.7; }
.kp-import-bulk { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 13px; }
.kp-stage-box { padding: 12px; border: 1px solid #cfe8d4; background: #f3faf5; border-radius: 8px; }
.kp-import-table-wrap { padding: 0; margin-top: 12px; }
.kp-import-table { min-width: 900px; }
.kp-field-title { font-weight: bold; margin-bottom: 4px; font-size: 13px; }
.kp-field-desc { font-size: 12px; color: #555; }
.kp-import-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }
.kp-import-noimg { color: #aaa; }
.kp-import-imgmeta { font-size: 10px; color: #888; margin-top: 2px; }
.kp-import-warn { font-size: 11px; color: #b45309; margin-top: 4px; font-weight: 600; }
.kp-import-ok { font-size: 11px; color: #157a3a; margin-top: 4px; font-weight: 600; }
.kp-import-dup { background: #fff7ed; opacity: 0.92; }
.kp-import-done { background: #f0fdf4; }
.kp-check-line { display: flex; align-items: center; gap: 4px; font-size: 11px; margin: 2px 0; white-space: nowrap; }
</style>

<?php if ($step === 'preview' && $autoContinue && $pendingCount > 0): ?>
<script>
(function () {
    var form = document.getElementById('kpStageForm');
    if (!form) return;
    var size = form.querySelector('#batchSize');
    if (size) size.value = '<?= (int)$autoBatch ?>';
    var auto = form.querySelector('#autoContinue');
    if (auto) auto.checked = true;
    var btn = form.querySelector('button[type=submit]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Uvozim etapu… sačekaj';
    }
    setTimeout(function () { form.submit(); }, 800);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
