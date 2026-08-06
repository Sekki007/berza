<?php

declare(strict_types=1);

function creditsEnabled(): bool
{
    return !empty(siteSettings()['enable_credits']);
}

function creditCurrencyLabel(): string
{
    $label = trim((string)(siteSettings()['credit_currency_label'] ?? 'din'));
    return $label !== '' ? $label : 'din';
}

function formatCredits(int|float $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' ' . creditCurrencyLabel();
}

function defaultCreditTopupAmounts(): array
{
    return [500, 1000, 2000, 5000];
}

function creditTopupAmounts(): array
{
    $stored = siteSettings()['credit_topup_amounts'] ?? null;
    if (!is_array($stored) || $stored === []) {
        return defaultCreditTopupAmounts();
    }
    $out = [];
    foreach ($stored as $v) {
        $n = (int)$v;
        if ($n > 0) {
            $out[] = $n;
        }
    }
    return $out !== [] ? array_values(array_unique($out)) : defaultCreditTopupAmounts();
}

function creditPaymentInfo(): string
{
    return trim((string)(siteSettings()['credit_payment_info'] ?? ''));
}

function getUserCredits(int $userId): int
{
    $user = findUserById($userId);
    return max(0, (int)($user['credits'] ?? 0));
}

function ensureCreditFiles(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('credit_deposits.json'))) {
        writeJsonFile('credit_deposits.json', []);
    }
    if (!file_exists(dataPath('credit_transactions.json'))) {
        writeJsonFile('credit_transactions.json', []);
    }
}

function getCreditDeposits(): array
{
    ensureCreditFiles();
    $items = readJsonFile('credit_deposits.json');
    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $items;
}

function getPendingCreditDepositsCount(): int
{
    return count(array_filter(getCreditDeposits(), static fn($d) => ($d['status'] ?? '') === 'pending'));
}

function getCreditDepositsForUser(int $userId): array
{
    return array_values(array_filter(getCreditDeposits(), static fn($d) => (int)($d['user_id'] ?? 0) === $userId));
}

function getCreditTransactionsForUser(int $userId, int $limit = 20): array
{
    ensureCreditFiles();
    $items = array_values(array_filter(
        readJsonFile('credit_transactions.json'),
        static fn($t) => (int)($t['user_id'] ?? 0) === $userId
    ));
    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function addCreditTransaction(int $userId, int $amount, string $type, string $note = '', ?int $refId = null): void
{
    ensureCreditFiles();
    $items = readJsonFile('credit_transactions.json');
    $maxId = 0;
    foreach ($items as $item) {
        $maxId = max($maxId, (int)($item['id'] ?? 0));
    }
    $items[] = [
        'id' => $maxId + 1,
        'user_id' => $userId,
        'amount' => $amount,
        'balance_after' => getUserCredits($userId),
        'type' => $type,
        'note' => $note,
        'ref_id' => $refId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    writeJsonFile('credit_transactions.json', $items);
}

/**
 * Menja saldo. $amount može biti + ili −.
 */
function adjustUserCredits(int $userId, int $amount, string $type, string $note = '', ?int $refId = null): bool
{
    if ($userId <= 0 || $amount === 0) {
        return false;
    }
    $users = getUsers();
    foreach ($users as &$user) {
        if ((int)($user['id'] ?? 0) !== $userId) {
            continue;
        }
        $current = max(0, (int)($user['credits'] ?? 0));
        $next = $current + $amount;
        if ($next < 0) {
            return false;
        }
        $user['credits'] = $next;
        writeJsonFile('users.json', $users);
        addCreditTransaction($userId, $amount, $type, $note, $refId);
        return true;
    }
    return false;
}

function requestCreditDeposit(int $userId, int $amount): ?array
{
    if (!creditsEnabled() || $userId <= 0 || $amount < 1) {
        return null;
    }
    $allowed = creditTopupAmounts();
    if (!in_array($amount, $allowed, true)) {
        // dozvoli i proizvoljan iznos ≥ najmanjeg paketa
        $min = min($allowed);
        if ($amount < $min) {
            return null;
        }
    }

    ensureCreditFiles();
    $items = readJsonFile('credit_deposits.json');
    $maxId = 0;
    foreach ($items as $item) {
        $maxId = max($maxId, (int)($item['id'] ?? 0));
    }

    $deposit = [
        'id' => $maxId + 1,
        'user_id' => $userId,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'confirmed_at' => null,
        'confirmed_by' => null,
    ];
    $items[] = $deposit;
    writeJsonFile('credit_deposits.json', $items);

    notifyUser(
        $userId,
        'credit_deposit_pending',
        'Zahtev za uplatu kredita',
        'Poslao si zahtev za ' . formatCredits($amount) . '. Uplati na račun i sačekaj potvrdu admina. Poziv na broj: KR-' . $deposit['id'],
        '/nalog.php?tab=krediti'
    );

    return $deposit;
}

function confirmCreditDeposit(int $depositId, int $adminId = 0): bool
{
    ensureCreditFiles();
    $items = readJsonFile('credit_deposits.json');
    foreach ($items as &$deposit) {
        if ((int)($deposit['id'] ?? 0) !== $depositId) {
            continue;
        }
        if (($deposit['status'] ?? '') === 'confirmed') {
            return true;
        }
        if (($deposit['status'] ?? '') !== 'pending') {
            return false;
        }

        $userId = (int)$deposit['user_id'];
        $amount = (int)$deposit['amount'];
        if (!adjustUserCredits($userId, $amount, 'deposit', 'Uplata potvrđena #' . $depositId, $depositId)) {
            return false;
        }

        $deposit['status'] = 'confirmed';
        $deposit['confirmed_at'] = date('Y-m-d H:i:s');
        $deposit['confirmed_by'] = $adminId;
        writeJsonFile('credit_deposits.json', $items);

        notifyUser(
            $userId,
            'credit_deposit_confirmed',
            'Krediti su dopunjeni',
            'Uplata od ' . formatCredits($amount) . ' je potvrđena. Trenutni saldo: ' . formatCredits(getUserCredits($userId)) . '.',
            '/nalog.php?tab=krediti'
        );
        return true;
    }
    return false;
}

function rejectCreditDeposit(int $depositId): bool
{
    ensureCreditFiles();
    $items = readJsonFile('credit_deposits.json');
    foreach ($items as &$deposit) {
        if ((int)($deposit['id'] ?? 0) !== $depositId) {
            continue;
        }
        if (($deposit['status'] ?? '') !== 'pending') {
            return false;
        }
        $deposit['status'] = 'rejected';
        writeJsonFile('credit_deposits.json', $items);
        notifyUser(
            (int)$deposit['user_id'],
            'credit_deposit_rejected',
            'Zahtev za uplatu odbijen',
            'Zahtev #' . $depositId . ' (' . formatCredits((int)$deposit['amount']) . ') nije potvrđen. Kontaktiraj podršku ako si uplatio.',
            '/nalog.php?tab=krediti'
        );
        return true;
    }
    return false;
}

/**
 * Admin ručno dodaje kredite (npr. video uplatu van sistema).
 */
function adminGrantCredits(int $userId, int $amount, string $note = ''): bool
{
    if ($amount < 1) {
        return false;
    }
    $ok = adjustUserCredits($userId, $amount, 'admin_grant', $note !== '' ? $note : 'Admin dopuna');
    if ($ok) {
        notifyUser(
            $userId,
            'credit_deposit_confirmed',
            'Krediti su dopunjeni',
            'Na nalog je dodato ' . formatCredits($amount) . '. Saldo: ' . formatCredits(getUserCredits($userId)) . '.',
            '/nalog.php?tab=krediti'
        );
    }
    return $ok;
}

/**
 * @return list<array{user_id:int,username:string,full_name:string,checks:int,spent:int,refunded:int,net:int,last_at:string}>
 */
function imeiCreditUsageByUser(int $limit = 100): array
{
    ensureCreditFiles();
    $tx = readJsonFile('credit_transactions.json');
    $grouped = [];
    foreach ($tx as $row) {
        $type = (string)($row['type'] ?? '');
        if (!in_array($type, ['imei_check', 'imei_refund'], true)) {
            continue;
        }
        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        if (!isset($grouped[$userId])) {
            $u = findUserById($userId);
            $grouped[$userId] = [
                'user_id' => $userId,
                'username' => (string)($u['username'] ?? ('#' . $userId)),
                'full_name' => (string)($u['full_name'] ?? ''),
                'checks' => 0,
                'spent' => 0,
                'refunded' => 0,
                'net' => 0,
                'last_at' => '',
            ];
        }
        $amount = (int)($row['amount'] ?? 0);
        if ($type === 'imei_check') {
            $grouped[$userId]['checks']++;
            $grouped[$userId]['spent'] += abs($amount);
            $grouped[$userId]['net'] += abs($amount);
        } else {
            $grouped[$userId]['refunded'] += abs($amount);
            $grouped[$userId]['net'] -= abs($amount);
        }
        $at = (string)($row['created_at'] ?? '');
        if ($at !== '' && strcmp($at, (string)$grouped[$userId]['last_at']) > 0) {
            $grouped[$userId]['last_at'] = $at;
        }
    }
    $list = array_values($grouped);
    usort($list, static function (array $a, array $b): int {
        if ($a['net'] !== $b['net']) {
            return $b['net'] <=> $a['net'];
        }
        return strcmp((string)$b['last_at'], (string)$a['last_at']);
    });
    return array_slice($list, 0, max(1, $limit));
}
