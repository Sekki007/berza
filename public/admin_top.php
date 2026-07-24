<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_top.php');
    $action = trim((string)($_POST['action'] ?? ''));
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($action === 'confirm' && $orderId > 0) {
        confirmTopOrder($orderId);
        setFlash('success', 'TOP porudžbina potvrđena.');
    } elseif ($action === 'reject' && $orderId > 0) {
        rejectTopOrder($orderId);
        setFlash('success', 'TOP porudžbina odbijena.');
    }
    header('Location: /admin_top.php');
    exit;
}

$orders = getTopOrders();
$pageTitle = 'TOP porudžbine — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'top';

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › TOP porudžbine</div>
        <h2 style="font-size:18px;margin-bottom:12px;">TOP porudžbine</h2>
        <p class="form-hint">Kada je auto-aktivacija isključena, ovde potvrđuješ uplate. Paketi: Admin → Podešavanja → Funkcije.</p>

        <?php if (!$orders): ?>
            <div class="form-card"><p>Nema porudžbina.</p></div>
        <?php else: ?>
            <div class="form-card table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Oglas</th>
                            <th>Korisnik</th>
                            <th>Paket</th>
                            <th>Cena</th>
                            <th>Status</th>
                            <th>Datum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $ad = getAdById((int)$order['ad_id']);
                            $u = findUserById((int)$order['user_id']);
                            $status = (string)($order['status'] ?? '');
                            ?>
                            <tr>
                                <td><?= (int)$order['id'] ?></td>
                                <td>
                                    <?php if ($ad): ?>
                                        <a href="/oglas.php?id=<?= (int)$ad['id'] ?>"><?= h((string)$ad['title']) ?></a>
                                    <?php else: ?>
                                        #<?= (int)$order['ad_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string)($u['username'] ?? ('#' . $order['user_id']))) ?></td>
                                <td><?= (int)$order['days'] ?> dana</td>
                                <td><?= creditsEnabled() ? formatCredits((int)$order['price']) : formatPrice((float)$order['price']) ?></td>
                                <td><?= h($status) ?></td>
                                <td><?= h((string)$order['created_at']) ?></td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <button class="btn-sm btn-sm-primary" name="action" value="confirm" type="submit">Potvrdi</button>
                                            <button class="btn-sm" name="action" value="reject" type="submit">Odbij</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
