<?php

declare(strict_types=1);

function defaultTopPackages(): array
{
    // price = cena u kreditima (din)
    return [
        ['id' => 'd3', 'days' => 3, 'price' => 300, 'label' => '3 dana'],
        ['id' => 'd7', 'days' => 7, 'price' => 600, 'label' => '7 dana'],
        ['id' => 'd14', 'days' => 14, 'price' => 1000, 'label' => '14 dana'],
    ];
}

function topPackages(): array
{
    $stored = siteSettings()['top_packages'] ?? null;
    if (!is_array($stored) || $stored === []) {
        return defaultTopPackages();
    }
    $out = [];
    foreach ($stored as $pkg) {
        if (!is_array($pkg)) {
            continue;
        }
        $id = trim((string)($pkg['id'] ?? ''));
        $days = max(1, (int)($pkg['days'] ?? 0));
        $price = max(0, (float)($pkg['price'] ?? 0));
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'days' => $days,
            'price' => $price,
            'label' => trim((string)($pkg['label'] ?? ($days . ' dana'))),
        ];
    }
    return $out !== [] ? $out : defaultTopPackages();
}

function findTopPackage(string $packageId): ?array
{
    foreach (topPackages() as $pkg) {
        if ($pkg['id'] === $packageId) {
            return $pkg;
        }
    }
    return null;
}

function topPurchaseEnabled(): bool
{
    return !empty(siteSettings()['enable_top_purchase']);
}

function topAutoActivate(): bool
{
    return !empty(siteSettings()['top_auto_activate']);
}

function adRenewalCredits(): int
{
    return max(0, (int)(siteSettings()['ad_renewal_credits'] ?? 200));
}

function highlightCredits(): int
{
    return max(0, (int)(siteSettings()['highlight_credits'] ?? 150));
}

function isAdHighlighted(array $ad): bool
{
    if (empty($ad['is_highlighted'])) {
        return false;
    }
    if (empty($ad['highlighted_until'])) {
        return true;
    }
    $ts = strtotime((string)$ad['highlighted_until']);
    return $ts !== false && $ts > time();
}

function isAdTopActive(array $ad): bool
{
    if (empty($ad['is_promoted'])) {
        return false;
    }
    if (empty($ad['promoted_until'])) {
        return true;
    }
    $ts = strtotime((string)$ad['promoted_until']);
    return $ts !== false && $ts > time();
}

/**
 * KP-stil Obnova: produžava rok, vraća oglas među novije, naplaćuje kredite.
 */
function renewAdPaid(int $adId, int $userId): ?array
{
    $ad = getAdById($adId);
    if (!$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
        return null;
    }
    if (!empty($ad['is_sold'])) {
        return null;
    }

    $cost = adRenewalCredits();
    if (creditsEnabled() && $cost > 0 && getUserCredits($userId) < $cost) {
        return ['ok' => false, 'error' => 'credits'];
    }

    if (creditsEnabled() && $cost > 0) {
        if (!adjustUserCredits($userId, -$cost, 'renewal', 'Obnova oglasa #' . $adId, $adId)) {
            return ['ok' => false, 'error' => 'credits'];
        }
    }

    $ads = readJsonFile('ads.json');
    foreach ($ads as &$row) {
        if ((int)($row['id'] ?? 0) !== $adId) {
            continue;
        }
        $now = date('Y-m-d H:i:s');
        $row['is_active'] = 1;
        $row['bumped_at'] = $now;
        $row['updated_at'] = $now;
        $row['expiry_warned_at'] = null;
        if (function_exists('adExpiryEnabled') && adExpiryEnabled()) {
            $row['expires_at'] = computeAdExpiresAt();
        }
        writeJsonFile('ads.json', $ads);

        notifyUser(
            $userId,
            'ad_renewed',
            'Oglas je obnovljen',
            'Oglas „' . (string)($row['title'] ?? '') . '” je obnovljen'
            . ($cost > 0 && creditsEnabled() ? ' (−' . formatCredits($cost) . ')' : '') . '.',
            '/oglas.php?id=' . $adId
        );

        return ['ok' => true, 'cost' => $cost];
    }
    return null;
}

/**
 * Dodatak: istaknut plavom bojom u listi (KP-stil).
 */
function activateAdHighlight(int $adId, int $userId, int $days = 7): ?array
{
    $ad = getAdById($adId);
    if (!$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
        return null;
    }
    if (!empty($ad['is_sold']) || (int)($ad['is_active'] ?? 0) !== 1) {
        return null;
    }

    $cost = highlightCredits();
    if (creditsEnabled() && $cost > 0 && getUserCredits($userId) < $cost) {
        return ['ok' => false, 'error' => 'credits'];
    }
    if (creditsEnabled() && $cost > 0) {
        if (!adjustUserCredits($userId, -$cost, 'highlight', 'Istaknuti oglas #' . $adId, $adId)) {
            return ['ok' => false, 'error' => 'credits'];
        }
    }

    $ads = readJsonFile('ads.json');
    foreach ($ads as &$row) {
        if ((int)($row['id'] ?? 0) !== $adId) {
            continue;
        }
        $base = time();
        if (!empty($row['highlighted_until'])) {
            $existing = strtotime((string)$row['highlighted_until']);
            if ($existing !== false && $existing > $base) {
                $base = $existing;
            }
        }
        $row['is_highlighted'] = 1;
        $row['highlighted_until'] = date('Y-m-d H:i:s', $base + max(1, $days) * 86400);
        $row['updated_at'] = date('Y-m-d H:i:s');
        writeJsonFile('ads.json', $ads);
        return ['ok' => true, 'cost' => $cost, 'until' => $row['highlighted_until']];
    }
    return null;
}

function ensureTopOrdersFile(): void
{
    if (!file_exists(dataPath('top_orders.json'))) {
        writeJsonFile('top_orders.json', []);
    }
}

function getTopOrders(): array
{
    ensureTopOrdersFile();
    $items = readJsonFile('top_orders.json');
    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $items;
}

function getPendingTopOrdersCount(): int
{
    return count(array_filter(getTopOrders(), static fn($o) => ($o['status'] ?? '') === 'pending'));
}

function getTopOrderById(int $id): ?array
{
    foreach (getTopOrders() as $order) {
        if ((int)($order['id'] ?? 0) === $id) {
            return $order;
        }
    }
    return null;
}

function activateAdTop(int $adId, int $days, string $packageId = ''): bool
{
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['id'] ?? 0) !== $adId) {
            continue;
        }
        $base = time();
        if (!empty($ad['promoted_until'])) {
            $existing = strtotime((string)$ad['promoted_until']);
            if ($existing !== false && $existing > $base) {
                $base = $existing;
            }
        }
        $ad['is_promoted'] = 1;
        $ad['promoted_until'] = date('Y-m-d H:i:s', $base + $days * 86400);
        $ad['top_package'] = $packageId;
        $ad['updated_at'] = date('Y-m-d H:i:s');
        writeJsonFile('ads.json', $ads);
        return true;
    }
    return false;
}

function clearAdTop(int $adId): bool
{
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['id'] ?? 0) !== $adId) {
            continue;
        }
        $ad['is_promoted'] = 0;
        $ad['promoted_until'] = null;
        $ad['updated_at'] = date('Y-m-d H:i:s');
        writeJsonFile('ads.json', $ads);
        return true;
    }
    return false;
}

function createTopOrder(int $userId, int $adId, string $packageId): ?array
{
    if (!topPurchaseEnabled()) {
        return null;
    }
    $pkg = findTopPackage($packageId);
    $ad = getAdById($adId);
    if (!$pkg || !$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
        return null;
    }
    if (!empty($ad['is_sold']) || (int)($ad['is_active'] ?? 0) !== 1) {
        return null;
    }

    $cost = (int)round((float)$pkg['price']);
    $useCredits = creditsEnabled();

    if ($useCredits) {
        if (getUserCredits($userId) < $cost) {
            return null;
        }
    }

    ensureTopOrdersFile();
    $orders = readJsonFile('top_orders.json');
    $maxId = 0;
    foreach ($orders as $order) {
        $maxId = max($maxId, (int)($order['id'] ?? 0));
    }

    $order = [
        'id' => $maxId + 1,
        'user_id' => $userId,
        'ad_id' => $adId,
        'package_id' => $pkg['id'],
        'days' => $pkg['days'],
        'price' => $cost,
        'paid_with' => $useCredits ? 'credits' : 'manual',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => null,
    ];

    // Sa kreditima: odmah skinuti saldo i aktivirati.
    // Bez kredita: staro ponašanje (auto ili čekanje admina).
    if ($useCredits) {
        if (!adjustUserCredits($userId, -$cost, 'top_spend', 'TOP ' . $pkg['label'] . ' — oglas #' . $adId, $order['id'])) {
            return null;
        }
        activateAdTop($adId, (int)$pkg['days'], (string)$pkg['id']);
        $order['status'] = 'paid';
        $order['paid_at'] = date('Y-m-d H:i:s');
    } elseif (topAutoActivate()) {
        activateAdTop($adId, (int)$pkg['days'], (string)$pkg['id']);
        $order['status'] = 'paid';
        $order['paid_at'] = date('Y-m-d H:i:s');
        $order['auto'] = true;
    }

    $orders[] = $order;
    writeJsonFile('top_orders.json', $orders);

    if ($order['status'] === 'paid') {
        $msg = 'Oglas „' . (string)($ad['title'] ?? '') . '” je istaknut na ' . $pkg['days'] . ' dana.';
        if ($useCredits) {
            $msg .= ' Skinuto ' . formatCredits($cost) . '. Saldo: ' . formatCredits(getUserCredits($userId)) . '.';
        }
            $ad = getAdById($adId);
            notifyUser(
                $userId,
                'top_activated',
                'TOP oglas aktiviran',
                $msg,
                $ad ? adUrl($ad) : ('/oglas.php?id=' . $adId)
            );
    }

    return $order;
}

function confirmTopOrder(int $orderId): bool
{
    $orders = readJsonFile('top_orders.json');
    foreach ($orders as &$order) {
        if ((int)($order['id'] ?? 0) !== $orderId) {
            continue;
        }
        if (($order['status'] ?? '') === 'paid') {
            return true;
        }
        activateAdTop((int)$order['ad_id'], (int)$order['days'], (string)$order['package_id']);
        $order['status'] = 'paid';
        $order['paid_at'] = date('Y-m-d H:i:s');
        writeJsonFile('top_orders.json', $orders);
        notifyUser(
            (int)$order['user_id'],
            'top_activated',
            'TOP oglas aktiviran',
            'Plaćanje je potvrđeno. Oglas je istaknut na ' . (int)$order['days'] . ' dana.',
            '/oglas.php?id=' . (int)$order['ad_id']
        );
        return true;
    }
    return false;
}

function rejectTopOrder(int $orderId): bool
{
    $orders = readJsonFile('top_orders.json');
    foreach ($orders as &$order) {
        if ((int)($order['id'] ?? 0) !== $orderId) {
            continue;
        }
        $order['status'] = 'rejected';
        writeJsonFile('top_orders.json', $orders);
        return true;
    }
    return false;
}

function processTopExpirations(bool $force = false): array
{
    $result = ['cleared' => 0, 'skipped' => false];
    $state = readJsonFile('top_state.json');
    $lastRun = strtotime((string)($state['last_run'] ?? '')) ?: 0;
    if (!$force && $lastRun > 0 && (time() - $lastRun) < 1800) {
        $result['skipped'] = true;
        return $result;
    }

    $ads = readJsonFile('ads.json');
    $changed = false;
    $now = time();
    foreach ($ads as &$ad) {
        if (empty($ad['is_promoted']) || empty($ad['promoted_until'])) {
            continue;
        }
        $ts = strtotime((string)$ad['promoted_until']);
        if ($ts === false || $ts > $now) {
            continue;
        }
        $ad['is_promoted'] = 0;
        $ad['updated_at'] = date('Y-m-d H:i:s');
        $changed = true;
        $result['cleared']++;
        $owner = (int)($ad['created_by'] ?? 0);
        if ($owner > 0) {
            notifyUser(
                $owner,
                'top_expired',
                'TOP isticanje je isteklo',
                'Oglas „' . (string)($ad['title'] ?? '') . '” više nije istaknut. Možeš ponovo naručiti TOP.',
                '/nalog.php?tab=oglasi'
            );
        }
    }
    unset($ad);
    if ($changed) {
        writeJsonFile('ads.json', $ads);
    }
    writeJsonFile('top_state.json', ['last_run' => date('Y-m-d H:i:s'), 'last_result' => $result]);
    return $result;
}
