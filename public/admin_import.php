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
        ];

        header('Location: /admin_import.php?step=preview');
        exit;
    }

    if ($action === 'import') {
        if (!is_array($importSession) || empty($importSession['mappings'])) {
            setFlash('danger', 'Nema učitane liste za uvoz. Počni ponovo.');
            header('Location: /admin_import.php');
            exit;
        }

        $posted = $_POST['import'] ?? [];
        if (!is_array($posted)) {
            setFlash('danger', 'Neispravan oblik forme.');
            header('Location: /admin_import.php?step=preview');
            exit;
        }

        $rows = [];
        foreach ($importSession['mappings'] as $i => $default) {
            $row = is_array($posted[$i] ?? null) ? $posted[$i] : [];
            $merged = array_merge($default, [
                'selected' => !empty($row['selected']) ? 1 : 0,
                'title' => trim((string)($row['title'] ?? $default['title'] ?? '')),
                'description' => trim((string)($row['description'] ?? $default['description'] ?? '')),
                'ad_type' => trim((string)($row['ad_type'] ?? $default['ad_type'] ?? 'telefon')),
                'category_group' => trim((string)($row['category_group'] ?? $default['category_group'] ?? '')),
                'brand' => trim((string)($row['brand'] ?? $default['brand'] ?? '')),
                'model' => trim((string)($row['model'] ?? $default['model'] ?? '')),
                'device_type' => trim((string)($row['device_type'] ?? $default['device_type'] ?? 'phone')),
                'equipment_type' => trim((string)($row['equipment_type'] ?? $default['equipment_type'] ?? '')),
                'condition_state' => trim((string)($row['condition_state'] ?? $default['condition_state'] ?? '')),
                'listing_type' => trim((string)($row['listing_type'] ?? $default['listing_type'] ?? 'sell')),
                'price' => (float)($row['price'] ?? $default['price'] ?? 0),
                'price_type' => trim((string)($row['price_type'] ?? $default['price_type'] ?? 'fixed')),
                'currency' => trim((string)($row['currency'] ?? $default['currency'] ?? 'eur')),
                'location' => trim((string)($row['location'] ?? $default['location'] ?? '')),
                'shop_category_id' => trim((string)($row['shop_category_id'] ?? $default['shop_category_id'] ?? '')),
                'is_active' => !empty($row['is_active']) ? 1 : 0,
                'download_images' => !empty($row['download_images']) ? 1 : 0,
                'source_id' => (string)($default['source_id'] ?? ''),
                'source_url' => (string)($default['source_url'] ?? ''),
                'image_urls' => is_array($default['image_urls'] ?? null) ? $default['image_urls'] : [],
            ]);
            $rows[] = $merged;
        }

        $batch = kpImportBatch($rows, (int)$importSession['target_user_id']);
        unset($_SESSION['kp_import']);

        $_SESSION['kp_import_result'] = $batch;
        $msg = 'Uvezeno ' . $batch['imported'] . ' oglasa.';
        if ($batch['skipped'] > 0) {
            $msg .= ' Preskočeno: ' . $batch['skipped'] . '.';
        }
        if ($batch['errors'] !== []) {
            $msg .= ' Greške: ' . count($batch['errors']) . '.';
            setFlash('warning', $msg);
        } else {
            setFlash('success', $msg);
        }

        header('Location: /admin_import.php?step=done');
        exit;
    }
}

$targetUser = null;
$shopCategories = [];
$seller = [];
$adsCount = 0;
$mappings = [];

if (is_array($importSession)) {
    $targetUser = findUserById((int)($importSession['target_user_id'] ?? 0));
    if ($targetUser) {
        $shopCategories = getShopCategories($targetUser);
    }
    $seller = is_array($importSession['data']['seller'] ?? null) ? $importSession['data']['seller'] : [];
    $adsCount = count($importSession['data']['ads'] ?? []);
    $mappings = is_array($importSession['mappings'] ?? null) ? $importSession['mappings'] : [];
}

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
                    <div><strong>Oglasa u JSON:</strong> <?= (int)$adsCount ?></div>
                </div>
                <div class="kp-import-bulk" style="margin-top:12px;">
                    <label>Bulk tip
                        <select id="bulkAdType">
                            <option value="">—</option>
                            <option value="telefon">Telefoni</option>
                            <option value="delovi">Delovi</option>
                            <option value="servis">Servis</option>
                        </select>
                    </label>
                    <button type="button" class="btn-sm" id="btnApplyType">Primeni tip na označene</button>
                    <button type="button" class="btn-sm" id="btnSelectAll">Označi sve</button>
                    <button type="button" class="btn-sm" id="btnSelectNone">Poništi sve</button>
                    <button type="button" class="btn-sm" id="btnActivateAll">Aktiviraj označene</button>
                </div>
            </section>

            <form method="POST" id="kpImportForm">
                <input type="hidden" name="action" value="import">
                <div class="form-card table-scroll kp-import-table-wrap">
                    <table class="admin-table kp-import-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th style="width:56px;">Slika</th>
                                <th>Naslov / opis</th>
                                <th style="width:110px;">Tip</th>
                                <th style="width:160px;">Kategorija</th>
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
                                ?>
                                <tr class="kp-import-row<?= $dupId ? ' kp-import-dup' : '' ?>" data-index="<?= (int)$i ?>">
                                    <td>
                                        <input type="checkbox" name="import[<?= (int)$i ?>][selected]" value="1"
                                            <?= !empty($map['selected']) ? 'checked' : '' ?>>
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
                                        <input class="kp-field kp-field-title" name="import[<?= (int)$i ?>][title]"
                                            value="<?= h((string)($map['title'] ?? '')) ?>" required>
                                        <textarea class="kp-field kp-field-desc" name="import[<?= (int)$i ?>][description]" rows="2"><?= h((string)($map['description'] ?? '')) ?></textarea>
                                        <?php if ($dupId): ?>
                                            <div class="kp-import-warn">Već uvezen (#<?= $dupId ?>)</div>
                                        <?php endif; ?>
                                        <?php if (!empty($map['source_url'])): ?>
                                            <a href="<?= h((string)$map['source_url']) ?>" target="_blank" rel="noopener" style="font-size:11px;">KP link</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="kp-ad-type" name="import[<?= (int)$i ?>][ad_type]">
                                            <option value="telefon" <?= ($map['ad_type'] ?? '') === 'telefon' ? 'selected' : '' ?>>Telefon</option>
                                            <option value="delovi" <?= ($map['ad_type'] ?? '') === 'delovi' ? 'selected' : '' ?>>Delovi</option>
                                            <option value="servis" <?= ($map['ad_type'] ?? '') === 'servis' ? 'selected' : '' ?>>Servis</option>
                                        </select>
                                        <select name="import[<?= (int)$i ?>][listing_type]" style="margin-top:4px;width:100%;">
                                            <?php foreach ($schema['listing_types'] as $k => $label): ?>
                                                <option value="<?= h($k) ?>" <?= ($map['listing_type'] ?? 'sell') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="kp-category-group" name="import[<?= (int)$i ?>][category_group]"
                                            data-selected="<?= h((string)($map['category_group'] ?? '')) ?>">
                                        </select>
                                        <?php if ($shopCategories !== []): ?>
                                            <select name="import[<?= (int)$i ?>][shop_category_id]" style="margin-top:4px;width:100%;">
                                                <option value="">Izlog kat.</option>
                                                <?php foreach ($shopCategories as $sc): ?>
                                                    <option value="<?= h((string)$sc['id']) ?>"
                                                        <?= ($map['shop_category_id'] ?? '') === (string)$sc['id'] ? 'selected' : '' ?>>
                                                        <?= h((string)$sc['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input name="import[<?= (int)$i ?>][brand]" value="<?= h((string)($map['brand'] ?? '')) ?>" list="brandList">
                                        <input name="import[<?= (int)$i ?>][model]" value="<?= h((string)($map['model'] ?? '')) ?>" placeholder="Model" style="margin-top:4px;width:100%;">
                                        <select name="import[<?= (int)$i ?>][device_type]" class="kp-device-type" style="margin-top:4px;width:100%;">
                                            <?php foreach ($schema['device_types'] as $k => $label): ?>
                                                <option value="<?= h($k) ?>" <?= ($map['device_type'] ?? 'phone') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="1" name="import[<?= (int)$i ?>][price]"
                                            value="<?= h((string)(int)($map['price'] ?? 0)) ?>" style="width:100%;">
                                        <select name="import[<?= (int)$i ?>][currency]" style="margin-top:4px;width:100%;">
                                            <option value="eur" <?= ($map['currency'] ?? 'eur') === 'eur' ? 'selected' : '' ?>>EUR</option>
                                            <option value="rsd" <?= ($map['currency'] ?? '') === 'rsd' ? 'selected' : '' ?>>RSD</option>
                                        </select>
                                        <select name="import[<?= (int)$i ?>][price_type]" style="margin-top:4px;width:100%;">
                                            <option value="fixed" <?= ($map['price_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fiksna</option>
                                            <option value="negotiable" <?= ($map['price_type'] ?? '') === 'negotiable' ? 'selected' : '' ?>>Po dogovoru</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input name="import[<?= (int)$i ?>][location]" value="<?= h((string)($map['location'] ?? '')) ?>" required>
                                        <select name="import[<?= (int)$i ?>][condition_state]" style="margin-top:4px;width:100%;">
                                            <option value="">—</option>
                                            <?php foreach ($schema['phone_conditions'] as $cond): ?>
                                                <option value="<?= h($cond) ?>" <?= ($map['condition_state'] ?? '') === $cond ? 'selected' : '' ?>><?= h($cond) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label class="kp-check-line">
                                            <input type="checkbox" name="import[<?= (int)$i ?>][is_active]" value="1"
                                                <?= !empty($map['is_active']) ? 'checked' : '' ?>> Aktivan
                                        </label>
                                        <label class="kp-check-line">
                                            <input type="checkbox" name="import[<?= (int)$i ?>][download_images]" value="1"
                                                <?= !empty($map['download_images']) ? 'checked' : '' ?>> Slike
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-card" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn-call" style="width:auto;min-width:180px;">Uvezi označene oglase</button>
                    <span class="form-hint">Uvoz može potrajati (preuzimanje slika). Ne zatvaraj stranicu.</span>
                </div>
            </form>
            <form method="POST" style="margin-top:-8px;margin-bottom:12px;">
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
.kp-import-table-wrap { padding: 0; }
.kp-import-table { min-width: 1100px; }
.kp-import-table input, .kp-import-table select, .kp-import-table textarea {
    width: 100%; font-size: 12px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 3px;
}
.kp-field-title { font-weight: bold; margin-bottom: 4px; }
.kp-field-desc { min-height: 44px; resize: vertical; color: #555; }
.kp-import-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }
.kp-import-noimg { color: #aaa; }
.kp-import-imgmeta { font-size: 10px; color: #888; margin-top: 2px; }
.kp-import-warn { font-size: 11px; color: #b45309; margin-top: 4px; }
.kp-import-dup { background: #fffbeb; }
.kp-check-line { display: flex; align-items: center; gap: 4px; font-size: 11px; margin: 2px 0; white-space: nowrap; }
</style>

<?php if ($step === 'preview'): ?>
<script>
(function () {
    var groups = <?= $categoryGroupsJson ?>;

    function fillCategorySelect(select) {
        var adType = select.closest('tr').querySelector('.kp-ad-type').value;
        var selected = select.getAttribute('data-selected') || select.value;
        select.innerHTML = '';
        (groups[adType] || []).forEach(function (g) {
            var opt = document.createElement('option');
            opt.value = g.key;
            opt.textContent = g.label;
            if (g.key === selected) opt.selected = true;
            select.appendChild(opt);
        });
        if (select.options.length === 1) {
            select.options[0].selected = true;
        }
    }

    document.querySelectorAll('.kp-category-group').forEach(fillCategorySelect);

    document.querySelectorAll('.kp-ad-type').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var row = sel.closest('tr');
            var cat = row.querySelector('.kp-category-group');
            cat.setAttribute('data-selected', '');
            fillCategorySelect(cat);
        });
    });

    document.getElementById('btnSelectAll').addEventListener('click', function () {
        document.querySelectorAll('.kp-import-row input[type=checkbox][name*="[selected]"]').forEach(function (cb) {
            cb.checked = true;
        });
    });
    document.getElementById('btnSelectNone').addEventListener('click', function () {
        document.querySelectorAll('.kp-import-row input[type=checkbox][name*="[selected]"]').forEach(function (cb) {
            cb.checked = false;
        });
    });
    document.getElementById('btnActivateAll').addEventListener('click', function () {
        document.querySelectorAll('.kp-import-row').forEach(function (row) {
            var sel = row.querySelector('input[name*="[selected]"]');
            if (sel && sel.checked) {
                var active = row.querySelector('input[name*="[is_active]"]');
                if (active) active.checked = true;
            }
        });
    });
    document.getElementById('btnApplyType').addEventListener('click', function () {
        var type = document.getElementById('bulkAdType').value;
        if (!type) return;
        document.querySelectorAll('.kp-import-row').forEach(function (row) {
            var sel = row.querySelector('input[name*="[selected]"]');
            if (!sel || !sel.checked) return;
            var adType = row.querySelector('.kp-ad-type');
            adType.value = type;
            adType.dispatchEvent(new Event('change'));
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
