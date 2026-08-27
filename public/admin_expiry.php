<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$defaultDays = adMaxActiveDays();
$withinDays = isset($_GET['within']) ? (int)$_GET['within'] : 7;
if (!in_array($withinDays, [3, 7, 14, 30], true)) {
    $withinDays = 7;
}
$includeExpired = !isset($_GET['expired']) || (string)$_GET['expired'] !== '0';

$listUrl = '/admin_expiry.php?within=' . $withinDays . ($includeExpired ? '' : '&expired=0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf($listUrl);
    $action = trim((string)($_POST['action'] ?? ''));
    $days = max(1, min(365, (int)($_POST['days'] ?? $defaultDays)));
    $bump = !empty($_POST['bump']);

    if ($action === 'renew_one') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        if ($adId > 0 && adminRenewAd($adId, $days, $bump)) {
            setFlash('success', 'Oglas #' . $adId . ' obnovljen za ' . $days . ' dana' . ($bump ? ' i podignut na vrh.' : '.'));
        } else {
            setFlash('danger', 'Obnova nije uspela.');
        }
    } elseif ($action === 'renew_bulk') {
        $ids = $_POST['ad_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ok = 0;
        foreach ($ids as $rawId) {
            $adId = (int)$rawId;
            if ($adId > 0 && adminRenewAd($adId, $days, $bump)) {
                $ok++;
            }
        }
        if ($ok > 0) {
            setFlash('success', 'Obnovljeno oglasa: ' . $ok . ' (po ' . $days . ' dana).');
        } else {
            setFlash('danger', 'Nijedan oglas nije obnovljen. Izaberi bar jedan.');
        }
    }

    header('Location: ' . $listUrl);
    exit;
}

$items = getAdsExpiringSoon($withinDays, $includeExpired);
$pageTitle = 'Istek oglasa — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'expiry';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Istek oglasa</div>
        <h2 style="font-size:18px;margin-bottom:8px;">Oglasi koji ističu</h2>
        <p class="form-hint" style="margin-bottom:14px;">
            Pregled oglasa koji uskoro gube rok<?= adExpiryEnabled() ? '' : ' <strong>(istek je trenutno isključen u Podešavanjima)</strong>' ?>.
            Obnova je besplatna za admina — biraš broj dana i opciono bump na vrh liste.
        </p>

        <div class="form-card" style="margin-bottom:12px;">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
                <div class="form-group" style="margin:0;min-width:140px;">
                    <label>Prikaži u narednih</label>
                    <select name="within">
                        <option value="3" <?= $withinDays === 3 ? 'selected' : '' ?>>3 dana</option>
                        <option value="7" <?= $withinDays === 7 ? 'selected' : '' ?>>7 dana</option>
                        <option value="14" <?= $withinDays === 14 ? 'selected' : '' ?>>14 dana</option>
                        <option value="30" <?= $withinDays === 30 ? 'selected' : '' ?>>30 dana</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                        <input type="hidden" name="expired" value="0">
                        <input type="checkbox" name="expired" value="1" <?= $includeExpired ? 'checked' : '' ?>>
                        Uključi već istekle
                    </label>
                </div>
                <button class="btn-sm btn-sm-primary" type="submit">Filtriraj</button>
            </form>
        </div>

        <?php if ($items === []): ?>
            <div class="form-card"><p>Nema oglasa u ovom periodu.</p></div>
        <?php else: ?>
            <form method="POST" id="admin-expiry-bulk">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="renew_bulk">

                <div class="form-card" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
                    <div class="form-group" style="margin:0;min-width:120px;">
                        <label for="bulk-days">Obnovi za (dana)</label>
                        <input type="number" id="bulk-days" name="days" min="1" max="365" value="<?= (int)$defaultDays ?>" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                            <input type="checkbox" name="bump" value="1" checked>
                            Podigni na vrh liste
                        </label>
                    </div>
                    <button class="btn-sm btn-sm-primary" type="submit">Obnovi izabrane</button>
                    <span class="form-hint" style="margin:0;">Ukupno: <strong><?= count($items) ?></strong></span>
                </div>

                <div class="form-card table-scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" data-expiry-check-all aria-label="Izaberi sve"></th>
                                <th>#</th>
                                <th>Oglas</th>
                                <th>Prodavac</th>
                                <th>Ističe</th>
                                <th>Status</th>
                                <th>Brza obnova</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $ad): ?>
                                <?php
                                $adId = (int)($ad['id'] ?? 0);
                                $owner = findUserById((int)($ad['created_by'] ?? 0));
                                $status = (string)($ad['_expiry_status'] ?? 'soon');
                                $daysLeft = $ad['_days_left'] ?? null;
                                $statusLabel = match ($status) {
                                    'expired' => 'Istekao',
                                    'urgent' => 'Uskoro (' . (int)$daysLeft . 'd)',
                                    'no_date' => 'Bez datuma',
                                    default => 'Za ' . (int)$daysLeft . 'd',
                                };
                                $statusClass = match ($status) {
                                    'expired' => 'color:#b91c1c',
                                    'urgent' => 'color:#c2410c',
                                    'no_date' => 'color:#64748b',
                                    default => 'color:#a16207',
                                };
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="ad_ids[]" value="<?= $adId ?>" data-expiry-row></td>
                                    <td><?= $adId ?></td>
                                    <td>
                                        <a href="<?= h(adUrl($ad)) ?>" target="_blank" rel="noopener"><?= h((string)($ad['title'] ?? 'Oglas')) ?></a>
                                        <?php if ((int)($ad['is_active'] ?? 0) !== 1): ?>
                                            <span class="form-hint" style="display:block;margin:2px 0 0;">neaktivan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($owner): ?>
                                            <?= h((string)($owner['username'] ?? $owner['full_name'] ?? ('#' . ($owner['id'] ?? '')))) ?>
                                        <?php else: ?>
                                            #<?= (int)($ad['created_by'] ?? 0) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($ad['expires_at'])): ?>
                                            <?= h(date('d.m.Y. H:i', strtotime((string)$ad['expires_at']) ?: time())) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td style="<?= $statusClass ?>;font-weight:600;"><?= h($statusLabel) ?></td>
                                    <td>
                                        <button class="btn-sm" type="submit" formaction="<?= h($listUrl) ?>" formmethod="post" name="action" value="renew_one" data-renew-one="<?= $adId ?>">Obnovi</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <script>
            (function () {
              var form = document.getElementById('admin-expiry-bulk');
              if (!form) return;
              var all = form.querySelector('[data-expiry-check-all]');
              var rows = form.querySelectorAll('[data-expiry-row]');
              if (all) {
                all.addEventListener('change', function () {
                  rows.forEach(function (cb) { cb.checked = !!all.checked; });
                });
              }
              form.querySelectorAll('[data-renew-one]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                  e.preventDefault();
                  var adId = btn.getAttribute('data-renew-one');
                  var actionInput = form.querySelector('input[name="action"]');
                  if (actionInput) actionInput.value = 'renew_one';
                  var old = form.querySelector('input[name="ad_id"]');
                  if (old) old.remove();
                  var hid = document.createElement('input');
                  hid.type = 'hidden';
                  hid.name = 'ad_id';
                  hid.value = adId;
                  form.appendChild(hid);
                  form.submit();
                });
              });
            })();
            </script>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
