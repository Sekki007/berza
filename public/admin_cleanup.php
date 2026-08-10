<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$csrfPath = '/admin_cleanup.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf($csrfPath);
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'scan') {
        $dupGroups = findDuplicateAdGroups();
        $misc = findMiscategorizedAds();
        $allowedDupIds = cleanupDuplicateRemoveIds($dupGroups);
        $allowedCat = [];
        foreach ($misc as $row) {
            $id = (int)($row['ad']['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $allowedCat[$id] = [
                'suggested_ad_type' => (string)$row['suggested_ad_type'],
                'suggested_category_group' => (string)$row['suggested_category_group'],
            ];
        }
        $_SESSION['ads_cleanup_scan'] = [
            'at' => date('Y-m-d H:i:s'),
            'dup_groups' => $dupGroups,
            'misc' => $misc,
            'allowed_dup_ids' => $allowedDupIds,
            'allowed_cat' => $allowedCat,
        ];
        setFlash(
            'success',
            'Sken završen: ' . count($allowedDupIds) . ' kandidata za brisanje, '
            . count($allowedCat) . ' za ispravku kategorije.'
        );
        header('Location: /admin_cleanup.php');
        exit;
    }

    if ($action === 'clear_scan') {
        unset($_SESSION['ads_cleanup_scan']);
        setFlash('success', 'Rezultat skena je obrisan.');
        header('Location: /admin_cleanup.php');
        exit;
    }

    $scan = $_SESSION['ads_cleanup_scan'] ?? null;
    if (!is_array($scan)) {
        setFlash('danger', 'Prvo pokreni sken.');
        header('Location: /admin_cleanup.php');
        exit;
    }

    if ($action === 'delete_dups') {
        $ids = array_map('intval', (array)($_POST['dup_ids'] ?? []));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            setFlash('danger', 'Nijedan duplikat nije označen.');
            header('Location: /admin_cleanup.php');
            exit;
        }
        $allowed = array_map('intval', (array)($scan['allowed_dup_ids'] ?? []));
        $result = cleanupDeleteDuplicateAds($ids, $allowed);
        unset($_SESSION['ads_cleanup_scan']);
        $msg = 'Obrisano: ' . (int)$result['deleted'] . '.';
        if ((int)$result['skipped'] > 0) {
            $msg .= ' Preskočeno: ' . (int)$result['skipped'] . '.';
        }
        if (!empty($result['errors'])) {
            $msg .= ' ' . implode(' ', $result['errors']);
            setFlash('danger', $msg);
        } else {
            setFlash('success', $msg . ' Pokreni novi sken za ažuriran pregled.');
        }
        header('Location: /admin_cleanup.php');
        exit;
    }

    if ($action === 'fix_categories') {
        $ids = array_map('intval', (array)($_POST['cat_ids'] ?? []));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            setFlash('danger', 'Nijedna kategorija nije označena.');
            header('Location: /admin_cleanup.php');
            exit;
        }
        $allowed = (array)($scan['allowed_cat'] ?? []);
        $fixes = [];
        foreach ($ids as $id) {
            if (!isset($allowed[$id])) {
                continue;
            }
            $fixes[$id] = [
                'ad_type' => (string)($allowed[$id]['suggested_ad_type'] ?? ''),
                'category_group' => (string)($allowed[$id]['suggested_category_group'] ?? ''),
            ];
        }
        $result = applyCategoryFixes($fixes, $allowed);
        unset($_SESSION['ads_cleanup_scan']);
        $msg = 'Ispravljeno: ' . (int)$result['fixed'] . '.';
        if ((int)$result['skipped'] > 0) {
            $msg .= ' Preskočeno: ' . (int)$result['skipped'] . '.';
        }
        if (!empty($result['errors'])) {
            $msg .= ' ' . implode(' ', $result['errors']);
            setFlash('danger', $msg);
        } else {
            setFlash('success', $msg . ' Pokreni novi sken za ažuriran pregled.');
        }
        header('Location: /admin_cleanup.php');
        exit;
    }

    setFlash('danger', 'Nepoznata akcija.');
    header('Location: /admin_cleanup.php');
    exit;
}

$scan = $_SESSION['ads_cleanup_scan'] ?? null;
$dupGroups = is_array($scan) ? (array)($scan['dup_groups'] ?? []) : [];
$misc = is_array($scan) ? (array)($scan['misc'] ?? []) : [];
$scanAt = is_array($scan) ? (string)($scan['at'] ?? '') : '';
$dupRemoveCount = 0;
foreach ($dupGroups as $g) {
    $dupRemoveCount += count($g['remove'] ?? []);
}

$pageTitle = 'Čišćenje — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'cleanup';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Čišćenje</div>
        <div class="inbox-head" style="border:none;padding:0;margin-bottom:12px;">
            <h2 style="font-size:18px;margin:0;">Duplikati i kategorije</h2>
        </div>

        <p class="muted" style="margin:0 0 16px;max-width:52rem;">
            Skenira oglase istog korisnika (isti KP ID ili isti naslov) i predlaže ispravku tipa/kategorije
            preko postojećih heuristika. Ništa se ne briše ni ne menja dok ne označiš i potvrdiš.
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:20px;">
            <form method="post" action="/admin_cleanup.php" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="scan">
                <button type="submit" class="btn btn-primary">Skeniraj</button>
            </form>
            <?php if (is_array($scan)): ?>
                <form method="post" action="/admin_cleanup.php" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="clear_scan">
                    <button type="submit" class="btn btn-ghost">Obriši rezultat skena</button>
                </form>
                <span class="muted" style="font-size:13px;">Sken: <?= h($scanAt) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!is_array($scan)): ?>
            <div class="card" style="padding:16px;">
                <p style="margin:0;" class="muted">Pokreni sken da vidiš predloge.</p>
            </div>
        <?php else: ?>

            <section style="margin-bottom:28px;">
                <h3 style="font-size:16px;margin:0 0 8px;">Duplikati</h3>
                <p class="muted" style="margin:0 0 12px;font-size:13px;">
                    Grupa = isti korisnik + isti KP source ili isti normalizovan naslov.
                    Zadržava se aktivan, zatim noviji. Kandidata za brisanje: <?= (int)$dupRemoveCount ?>.
                </p>

                <?php if ($dupRemoveCount === 0): ?>
                    <p style="margin:0;">Nema pronađenih duplikata.</p>
                <?php else: ?>
                    <form method="post" action="/admin_cleanup.php" onsubmit="return confirm('Obrisati označene duplikate? Ovo se ne može poništiti.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_dups">
                        <div style="margin-bottom:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="btn-sm" onclick="cleanupToggleAll('dup_ids[]', true)">Označi sve</button>
                            <button type="button" class="btn-sm" onclick="cleanupToggleAll('dup_ids[]', false)">Skini oznake</button>
                            <button type="submit" class="btn" style="background:#c0392b;color:#fff;border-color:#c0392b;">Obriši označene duplikate</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%;font-size:13px;">
                                <thead>
                                <tr>
                                    <th></th>
                                    <th>ID</th>
                                    <th>Korisnik</th>
                                    <th>Naslov</th>
                                    <th>Razlog</th>
                                    <th>Zadržava se</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dupGroups as $g): ?>
                                    <?php
                                    $keep = $g['keep'] ?? [];
                                    $keepId = (int)($keep['id'] ?? 0);
                                    $userLabel = cleanupUsername((int)($g['user_id'] ?? 0));
                                    $reason = (string)($g['reason'] ?? '');
                                    foreach (($g['remove'] ?? []) as $ad):
                                        $id = (int)($ad['id'] ?? 0);
                                        if ($id <= 0) {
                                            continue;
                                        }
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" name="dup_ids[]" value="<?= $id ?>" checked></td>
                                            <td><a href="/ad.php?id=<?= $id ?>" target="_blank">#<?= $id ?></a></td>
                                            <td><?= h($userLabel) ?></td>
                                            <td><?= h(mb_strimwidth((string)($ad['title'] ?? ''), 0, 60, '…')) ?></td>
                                            <td><?= h($reason) ?></td>
                                            <td><a href="/ad.php?id=<?= $keepId ?>" target="_blank">#<?= $keepId ?></a>
                                                <?= h(mb_strimwidth((string)($keep['title'] ?? ''), 0, 40, '…')) ?></td>
                                            <td><?= !empty($ad['is_active']) ? 'aktivan' : 'neaktivan' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section>
                <h3 style="font-size:16px;margin:0 0 8px;">Pogrešne kategorije</h3>
                <p class="muted" style="margin:0 0 12px;font-size:13px;">
                    Predlog iz naslova/opisa (`kpGuessCategoryGroup`). Kandidata: <?= count($misc) ?>.
                </p>

                <?php if ($misc === []): ?>
                    <p style="margin:0;">Nema predloga za ispravku.</p>
                <?php else: ?>
                    <form method="post" action="/admin_cleanup.php" onsubmit="return confirm('Ispraviti tip/kategoriju za označene oglase?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="fix_categories">
                        <div style="margin-bottom:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="btn-sm" onclick="cleanupToggleAll('cat_ids[]', true)">Označi sve</button>
                            <button type="button" class="btn-sm" onclick="cleanupToggleAll('cat_ids[]', false)">Skini oznake</button>
                            <button type="submit" class="btn btn-primary">Ispravi označene kategorije</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%;font-size:13px;">
                                <thead>
                                <tr>
                                    <th></th>
                                    <th>ID</th>
                                    <th>Naslov</th>
                                    <th>Trenutno</th>
                                    <th>Predlog</th>
                                    <th>Razlog</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($misc as $row): ?>
                                    <?php
                                    $ad = $row['ad'] ?? [];
                                    $id = (int)($ad['id'] ?? 0);
                                    if ($id <= 0) {
                                        continue;
                                    }
                                    $cur = (string)$row['current_ad_type']
                                        . (!empty($row['current_category_group']) ? ' / ' . $row['current_category_group'] : '');
                                    $sug = (string)$row['suggested_ad_type']
                                        . ' / ' . (string)$row['suggested_category_group'];
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="cat_ids[]" value="<?= $id ?>" checked></td>
                                        <td><a href="/ad.php?id=<?= $id ?>" target="_blank">#<?= $id ?></a></td>
                                        <td><?= h(mb_strimwidth((string)($ad['title'] ?? ''), 0, 70, '…')) ?></td>
                                        <td><?= h($cur) ?></td>
                                        <td><?= h($sug) ?></td>
                                        <td><?= h((string)($row['reason'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

        <?php endif; ?>
    </main>
</div>

<script>
function cleanupToggleAll(name, on) {
    document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function (el) {
        el.checked = !!on;
    });
}
</script>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
