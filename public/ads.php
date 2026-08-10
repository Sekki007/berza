<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$q = trim((string)($_GET['q'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));
$filterType = trim((string)($_GET['type'] ?? 'all'));
$filterTop = trim((string)($_GET['top'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if (!in_array($filterStatus, ['all', 'active', 'inactive', 'sold'], true)) {
    $filterStatus = 'all';
}
if (!in_array($filterType, ['all', 'telefon', 'delovi', 'servis'], true)) {
    $filterType = 'all';
}
if (!in_array($filterTop, ['all', 'yes', 'no'], true)) {
    $filterTop = 'all';
}

/**
 * @param array<string, mixed> $overrides
 */
function adminAdsFilterQuery(array $overrides = []): string
{
    $params = [
        'q' => array_key_exists('q', $overrides) ? (string)$overrides['q'] : trim((string)($_GET['q'] ?? '')),
        'status' => array_key_exists('status', $overrides) ? (string)$overrides['status'] : trim((string)($_GET['status'] ?? 'all')),
        'type' => array_key_exists('type', $overrides) ? (string)$overrides['type'] : trim((string)($_GET['type'] ?? 'all')),
        'top' => array_key_exists('top', $overrides) ? (string)$overrides['top'] : trim((string)($_GET['top'] ?? 'all')),
        'page' => array_key_exists('page', $overrides) ? (int)$overrides['page'] : max(1, (int)($_GET['page'] ?? 1)),
    ];

    foreach (['q', 'status', 'type', 'top'] as $k) {
        if ($params[$k] === '' || $params[$k] === 'all') {
            unset($params[$k]);
        }
    }
    if ((int)($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }

    if ($params === []) {
        return '/ads.php';
    }
    return '/ads.php?' . http_build_query($params);
}

$allAds = getAllAds();

$activeCount = 0;
$inactiveCount = 0;
$soldCount = 0;
$topCount = 0;
foreach ($allAds as $ad) {
    if (!is_array($ad)) {
        continue;
    }
    if (!empty($ad['is_sold'])) {
        $soldCount++;
    } elseif ((int)($ad['is_active'] ?? 0) === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if (function_exists('isAdTopActive') && isAdTopActive($ad)) {
        $topCount++;
    }
}

$userCache = [];
$filtered = array_values(array_filter($allAds, static function ($ad) use ($q, $filterStatus, $filterType, $filterTop, &$userCache): bool {
    if (!is_array($ad)) {
        return false;
    }

    $isSold = !empty($ad['is_sold']);
    $isActive = (int)($ad['is_active'] ?? 0) === 1;
    if ($filterStatus === 'active' && (!$isActive || $isSold)) {
        return false;
    }
    if ($filterStatus === 'inactive' && ($isActive || $isSold)) {
        return false;
    }
    if ($filterStatus === 'sold' && !$isSold) {
        return false;
    }

    $type = function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? 'telefon');
    if ($filterType !== 'all' && $type !== $filterType) {
        return false;
    }

    $isTop = function_exists('isAdTopActive') && isAdTopActive($ad);
    if ($filterTop === 'yes' && !$isTop) {
        return false;
    }
    if ($filterTop === 'no' && $isTop) {
        return false;
    }

    if ($q === '') {
        return true;
    }

    $userId = (int)($ad['created_by'] ?? 0);
    $username = '';
    $fullName = '';
    if ($userId > 0) {
        if (!array_key_exists($userId, $userCache)) {
            $userCache[$userId] = findUserById($userId);
        }
        $u = $userCache[$userId];
        if (is_array($u)) {
            $username = (string)($u['username'] ?? '');
            $fullName = (string)($u['full_name'] ?? ($u['shop_name'] ?? ''));
        }
    }

    $hay = mb_strtolower(implode(' ', [
        (string)($ad['id'] ?? ''),
        (string)($ad['title'] ?? ''),
        (string)($ad['brand'] ?? ''),
        (string)($ad['model'] ?? ''),
        (string)($ad['location'] ?? ''),
        (string)($ad['kp_source_id'] ?? ''),
        (string)($ad['description'] ?? ''),
        $username,
        $fullName,
        '#' . $userId,
    ]), 'UTF-8');

    $needle = mb_strtolower($q, 'UTF-8');
    return $needle !== '' && mb_strpos($hay, $needle) !== false;
}));

$pagination = paginateAds($filtered, $page, $perPage);
$ads = $pagination['items'];
$page = (int)$pagination['page'];

$returnUrl = adminAdsFilterQuery([
    'q' => $q,
    'status' => $filterStatus,
    'type' => $filterType,
    'top' => $filterTop,
    'page' => $page,
]);
$hasFilters = $q !== '' || $filterStatus !== 'all' || $filterType !== 'all' || $filterTop !== 'all';

$pageTitle = 'Upravljanje oglasima — KupiTelefon';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'ads';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Oglasi</div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap;">
            <h2 style="font-size:18px;margin:0;">
                Upravljanje oglasima
                <span style="color:var(--text-muted);font-weight:normal;">
                    (<?= (int)$pagination['total'] ?><?= $hasFilters ? ' / ' . count($allAds) : '' ?>)
                </span>
            </h2>
            <a class="btn-post" href="/ad_form.php" style="width:auto;display:inline-block;">+ Dodaj oglas</a>
        </div>

        <div class="admin-user-stats">
            <a class="admin-user-stat<?= $filterStatus === 'active' ? ' is-on' : '' ?>" href="<?= h(adminAdsFilterQuery(['status' => 'active', 'type' => 'all', 'top' => 'all', 'q' => '', 'page' => 1])) ?>">
                Aktivni <strong><?= (int)$activeCount ?></strong>
            </a>
            <a class="admin-user-stat<?= $filterStatus === 'inactive' ? ' is-on' : '' ?>" href="<?= h(adminAdsFilterQuery(['status' => 'inactive', 'type' => 'all', 'top' => 'all', 'q' => '', 'page' => 1])) ?>">
                Neaktivni <strong><?= (int)$inactiveCount ?></strong>
            </a>
            <a class="admin-user-stat<?= $filterStatus === 'sold' ? ' is-on' : '' ?>" href="<?= h(adminAdsFilterQuery(['status' => 'sold', 'type' => 'all', 'top' => 'all', 'q' => '', 'page' => 1])) ?>">
                Prodato <strong><?= (int)$soldCount ?></strong>
            </a>
            <a class="admin-user-stat<?= $filterTop === 'yes' ? ' is-on' : '' ?>" href="<?= h(adminAdsFilterQuery(['top' => 'yes', 'status' => 'all', 'type' => 'all', 'q' => '', 'page' => 1])) ?>">
                TOP <strong><?= (int)$topCount ?></strong>
            </a>
            <?php if ($hasFilters): ?>
                <a class="admin-user-stat" href="/ads.php">Prikaži sve</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="/ads.php" class="form-card admin-user-filters" id="admin-ads-filters">
            <div class="admin-user-filters-row">
                <div class="form-group" style="margin:0;flex:1 1 260px;">
                    <label for="admin-ads-q">Brza pretraga</label>
                    <input type="search" name="q" id="admin-ads-q" value="<?= h($q) ?>" placeholder="Naslov, ID, brend, grad, korisnik, KP ID…" autocomplete="off" autofocus>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-ads-status">Status</label>
                    <select name="status" id="admin-ads-status">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Svi</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Aktivni</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Neaktivni</option>
                        <option value="sold" <?= $filterStatus === 'sold' ? 'selected' : '' ?>>Prodato</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-ads-type">Tip</label>
                    <select name="type" id="admin-ads-type">
                        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Svi</option>
                        <option value="telefon" <?= $filterType === 'telefon' ? 'selected' : '' ?>>Telefoni</option>
                        <option value="delovi" <?= $filterType === 'delovi' ? 'selected' : '' ?>>Delovi/Oprema</option>
                        <option value="servis" <?= $filterType === 'servis' ? 'selected' : '' ?>>Servis</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="admin-ads-top">TOP</label>
                    <select name="top" id="admin-ads-top">
                        <option value="all" <?= $filterTop === 'all' ? 'selected' : '' ?>>Svi</option>
                        <option value="yes" <?= $filterTop === 'yes' ? 'selected' : '' ?>>Samo TOP</option>
                        <option value="no" <?= $filterTop === 'no' ? 'selected' : '' ?>>Bez TOP</option>
                    </select>
                </div>
                <div class="admin-user-filters-actions">
                    <button class="btn-call" type="submit">Traži</button>
                    <?php if ($hasFilters): ?>
                        <a class="btn-message" href="/ads.php">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
            <p class="form-hint" style="margin:10px 0 0;">Strana <?= (int)$page ?> / <?= (int)$pagination['pages'] ?> · <?= (int)$perPage ?> po strani</p>
        </form>

        <div class="form-card table-scroll" style="padding:0;">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Naziv</th>
                    <th>Korisnik</th>
                    <th>Tip</th>
                    <th>Cena</th>
                    <th>Lokacija</th>
                    <th>Status</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($ads === []): ?>
                    <tr>
                        <td colspan="8" style="padding:18px;color:var(--text-muted);">Nema oglasa za ovaj filter.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($ads as $ad): ?>
                    <?php
                    $id = (int)($ad['id'] ?? 0);
                    $userId = (int)($ad['created_by'] ?? 0);
                    if ($userId > 0 && !array_key_exists($userId, $userCache)) {
                        $userCache[$userId] = findUserById($userId);
                    }
                    $owner = $userId > 0 ? ($userCache[$userId] ?? null) : null;
                    $ownerLabel = is_array($owner)
                        ? trim((string)($owner['username'] ?? ($owner['full_name'] ?? '')))
                        : '';
                    if ($ownerLabel === '') {
                        $ownerLabel = $userId > 0 ? ('#' . $userId) : '—';
                    }
                    $isSold = !empty($ad['is_sold']);
                    $isActive = (int)($ad['is_active'] ?? 0) === 1;
                    $isTop = function_exists('isAdTopActive') && isAdTopActive($ad);
                    $toggleBase = '/ad_toggle.php?id=' . $id . '&return=' . rawurlencode($returnUrl);
                    $deleteHref = '/ad_delete.php?id=' . $id . '&return=' . rawurlencode($returnUrl);
                    ?>
                    <tr>
                        <td>#<?= $id ?></td>
                        <td>
                            <strong><?= h(mb_strimwidth((string)($ad['title'] ?? ''), 0, 72, '…')) ?></strong>
                            <?php if ($isTop): ?>
                                <span class="vote-tag vote-tag-pos" style="margin-left:6px;">TOP</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?php if ($userId > 0): ?>
                                <a href="/admin_users.php?q=<?= (int)$userId ?>"><?= h($ownerLabel) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= h(adCategoryLabel($ad)) ?></td>
                        <td><?= h(formatAdPrice($ad)) ?></td>
                        <td><?= h((string)($ad['location'] ?? '')) ?></td>
                        <td style="font-size:12px;">
                            <?php if ($isSold): ?>
                                <span class="vote-tag vote-tag-neg">Prodato</span>
                            <?php elseif ($isActive): ?>
                                <span class="vote-tag vote-tag-pos">Aktivan</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">Neaktivan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a class="btn-sm" href="/oglas.php?id=<?= $id ?>">Pogledaj</a>
                                <a class="btn-sm btn-sm-primary" href="/ad_form.php?id=<?= $id ?>">Izmeni</a>
                                <a class="btn-sm" href="<?= h($toggleBase) ?>&action=sold"><?= $isSold ? 'Vrati' : 'Prodato' ?></a>
                                <a class="btn-sm" href="<?= h($toggleBase) ?>&action=promote"><?= $isTop ? 'Un-TOP' : 'TOP' ?></a>
                                <a class="btn-sm btn-sm-danger" href="<?= h($deleteHref) ?>" onclick="return confirm('Obrisati oglas #<?= $id ?>?');">Obriši</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int)$pagination['pages'] > 1): ?>
            <div class="pagination" style="margin-top:14px;">
                <?php if ($page > 1): ?>
                    <a class="btn-sm" href="<?= h(adminAdsFilterQuery(['page' => $page - 1])) ?>">← Prethodna</a>
                <?php endif; ?>
                <span class="form-hint" style="margin:0;align-self:center;">Strana <?= (int)$page ?> / <?= (int)$pagination['pages'] ?></span>
                <?php if ($page < (int)$pagination['pages']): ?>
                    <a class="btn-sm btn-sm-primary" href="<?= h(adminAdsFilterQuery(['page' => $page + 1])) ?>">Sledeća →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
(function () {
  var form = document.getElementById('admin-ads-filters');
  if (!form) return;
  form.querySelectorAll('select').forEach(function (el) {
    el.addEventListener('change', function () { form.submit(); });
  });
})();
</script>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
