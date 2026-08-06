<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_credits.php');
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'confirm') {
        $id = (int)($_POST['deposit_id'] ?? 0);
        if ($id > 0 && confirmCreditDeposit($id, (int)currentUser()['id'])) {
            setFlash('success', 'Uplata je potvrđena i krediti su dodati.');
        } else {
            setFlash('danger', 'Uplata nije potvrđena.');
        }
    } elseif ($action === 'reject') {
        $id = (int)($_POST['deposit_id'] ?? 0);
        if ($id > 0 && rejectCreditDeposit($id)) {
            setFlash('success', 'Zahtev je odbijen.');
        } else {
            setFlash('danger', 'Zahtev nije moguće odbiti.');
        }
    } elseif ($action === 'grant') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if ($userId > 0 && $amount > 0 && adminGrantCredits($userId, $amount, $note)) {
            setFlash('success', 'Dodato ' . formatCredits($amount) . ' korisniku.');
        } else {
            setFlash('danger', 'Dodavanje kredita nije uspelo.');
        }
    }

    header('Location: /admin_credits.php');
    exit;
}

$deposits = getCreditDeposits();
$users = getUsers();
$imeiUsage = imeiCreditUsageByUser(200);
$pageTitle = 'Krediti / uplate — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'credits';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Krediti</div>
        <h2 style="font-size:18px;margin-bottom:12px;">Krediti i uplate</h2>
        <p class="form-hint">Kad korisnik uplati na račun (npr. 1.000 din), potvrdi zahtev — krediti mu se dodaju. Njima plaća TOP isticanje.</p>

        <section class="form-card" style="margin-bottom:12px;">
            <h3>Ručna dopuna</h3>
            <form method="POST" class="form-row" style="align-items:end;">
                <input type="hidden" name="action" value="grant">
                <div class="form-group" style="flex:1;">
                    <label>Korisnik</label>
                    <select name="user_id" required>
                        <option value="">Izaberi…</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>">
                                <?= h((string)$u['username']) ?> — saldo <?= formatCredits((int)($u['credits'] ?? 0)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Iznos (din)</label>
                    <input type="number" min="1" name="amount" value="1000" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Napomena</label>
                    <input name="note" placeholder="npr. uplatnica 23.07.">
                </div>
                <button class="btn-call" type="submit" style="width:auto;min-width:140px;">Dodaj kredite</button>
            </form>
        </section>

        <section class="form-card table-scroll">
            <h3 style="padding:16px 16px 0;">Zahtevi za uplatu</h3>
            <?php if (!$deposits): ?>
                <p>Nema zahteva.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Korisnik</th>
                            <th>Iznos</th>
                            <th>Status</th>
                            <th>Datum</th>
                            <th>Svrha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $d): ?>
                            <?php $u = findUserById((int)$d['user_id']); ?>
                            <tr>
                                <td><?= (int)$d['id'] ?></td>
                                <td>
                                    <?= h((string)($u['username'] ?? ('#' . $d['user_id']))) ?>
                                    <div style="font-size:11px;color:var(--text-muted);">saldo: <?= formatCredits((int)($u['credits'] ?? 0)) ?></div>
                                </td>
                                <td><strong><?= formatCredits((int)$d['amount']) ?></strong></td>
                                <td><?= h((string)$d['status']) ?></td>
                                <td><?= h((string)$d['created_at']) ?></td>
                                <td><code>KR-<?= (int)$d['id'] ?></code></td>
                                <td>
                                    <?php if (($d['status'] ?? '') === 'pending'): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="deposit_id" value="<?= (int)$d['id'] ?>">
                                            <button class="btn-sm btn-sm-primary" name="action" value="confirm" type="submit">Potvrdi uplatu</button>
                                            <button class="btn-sm" name="action" value="reject" type="submit">Odbij</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="form-card table-scroll" style="margin-top:12px;">
            <h3 style="padding:16px 16px 0;">IMEI potrošnja kredita po korisniku</h3>
            <?php if (!$imeiUsage): ?>
                <p style="padding:10px 16px 16px;">Još nema IMEI kreditnih transakcija.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Korisnik</th>
                            <th>Provera</th>
                            <th>Potrošeno</th>
                            <th>Refund</th>
                            <th>Neto</th>
                            <th>Poslednja aktivnost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imeiUsage as $row): ?>
                            <tr>
                                <td>
                                    <?= h((string)$row['username']) ?>
                                    <?php if ((string)$row['full_name'] !== ''): ?>
                                        <div style="font-size:11px;color:var(--text-muted);"><?= h((string)$row['full_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$row['checks'] ?></td>
                                <td><?= (int)$row['spent'] ?> kred</td>
                                <td><?= (int)$row['refunded'] ?> kred</td>
                                <td><strong><?= (int)$row['net'] ?> kred</strong></td>
                                <td><?= h((string)($row['last_at'] ?: '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
