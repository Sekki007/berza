<?php

declare(strict_types=1);

function reportReasons(): array
{
    return [
        'spam' => 'Spam / lažni oglas',
        'fraud' => 'Prevara / sumnjivo',
        'inappropriate' => 'Neprimeren sadržaj',
        'wrong_category' => 'Pogrešna kategorija',
        'stolen' => 'Sumnja na krađu',
        'abuse' => 'Uvrede / zloupotreba',
        'other' => 'Ostalo',
    ];
}

function ensureReportsFile(): void
{
    if (!file_exists(dataPath('reports.json'))) {
        writeJsonFile('reports.json', []);
    }
}

function getAllReports(): array
{
    ensureReportsFile();
    $reports = readJsonFile('reports.json');
    usort($reports, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $reports;
}

function getOpenReportsCount(): int
{
    $count = 0;
    foreach (getAllReports() as $report) {
        if (($report['status'] ?? 'open') === 'open') {
            $count++;
        }
    }
    return $count;
}

function getReportById(int $id): ?array
{
    foreach (getAllReports() as $report) {
        if ((int)($report['id'] ?? 0) === $id) {
            return $report;
        }
    }
    return null;
}

function saveReport(array $payload): ?int
{
    ensureReportsFile();
    $type = (string)($payload['type'] ?? '');
    if (!in_array($type, ['ad', 'user'], true)) {
        return null;
    }

    $reason = (string)($payload['reason'] ?? 'other');
    if (!isset(reportReasons()[$reason])) {
        $reason = 'other';
    }

    $reports = readJsonFile('reports.json');
    $maxId = 0;
    foreach ($reports as $report) {
        $maxId = max($maxId, (int)($report['id'] ?? 0));
    }

    $id = $maxId + 1;
    $reports[] = [
        'id' => $id,
        'type' => $type,
        'target_ad_id' => isset($payload['target_ad_id']) ? (int)$payload['target_ad_id'] : null,
        'target_user_id' => isset($payload['target_user_id']) ? (int)$payload['target_user_id'] : null,
        'from_user_id' => (int)($payload['from_user_id'] ?? 0),
        'from_name' => trim((string)($payload['from_name'] ?? 'Anonimno')),
        'reason' => $reason,
        'details' => mb_substr(trim((string)($payload['details'] ?? '')), 0, 1000),
        'status' => 'open',
        'admin_note' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'resolved_at' => null,
        'resolved_by' => null,
    ];
    writeJsonFile('reports.json', $reports);
    return $id;
}

function updateReportStatus(int $reportId, string $status, string $adminNote = ''): bool
{
    if (!in_array($status, ['open', 'resolved', 'dismissed'], true)) {
        return false;
    }

    $reports = readJsonFile('reports.json');
    $changed = false;
    foreach ($reports as &$report) {
        if ((int)($report['id'] ?? 0) !== $reportId) {
            continue;
        }
        $report['status'] = $status;
        $report['admin_note'] = mb_substr(trim($adminNote), 0, 500);
        if ($status === 'open') {
            $report['resolved_at'] = null;
            $report['resolved_by'] = null;
        } else {
            $report['resolved_at'] = date('Y-m-d H:i:s');
            $report['resolved_by'] = (int)(currentUser()['id'] ?? 0);
        }
        $changed = true;
        break;
    }
    unset($report);

    if ($changed) {
        writeJsonFile('reports.json', $reports);
    }
    return $changed;
}

function isUserBlocked(array $user): bool
{
    return !empty($user['is_blocked']);
}

function setUserBlocked(int $userId, bool $blocked, string $reason = ''): bool
{
    if ($userId <= 0) {
        return false;
    }
    $users = getUsers();
    foreach ($users as &$user) {
        if ((int)($user['id'] ?? 0) !== $userId) {
            continue;
        }
        if (!empty($user['is_admin']) || ($user['username'] ?? '') === 'admin') {
            return false;
        }
        $user['is_blocked'] = $blocked;
        if ($blocked) {
            $user['blocked_at'] = date('Y-m-d H:i:s');
            $user['blocked_reason'] = mb_substr(trim($reason), 0, 300);
        } else {
            $user['blocked_at'] = null;
            $user['blocked_reason'] = '';
        }
        writeJsonFile('users.json', $users);
        return true;
    }
    return false;
}

function deleteUserById(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $users = getUsers();
    $target = null;
    foreach ($users as $user) {
        if ((int)($user['id'] ?? 0) === $userId) {
            $target = $user;
            break;
        }
    }
    if (!$target) {
        return false;
    }
    if (!empty($target['is_admin']) || ($target['username'] ?? '') === 'admin') {
        return false;
    }

    // Deactivate user's ads instead of hard-deleting marketplace history
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$ad) {
        if ((int)($ad['created_by'] ?? 0) === $userId) {
            $ad['is_active'] = 0;
            $ad['updated_at'] = date('Y-m-d H:i:s');
        }
    }
    unset($ad);
    writeJsonFile('ads.json', $ads);

    $users = array_values(array_filter($users, static fn($u) => (int)($u['id'] ?? 0) !== $userId));
    writeJsonFile('users.json', $users);
    return true;
}

function countUserAds(int $userId): int
{
    $count = 0;
    foreach (getAllAds() as $ad) {
        if ((int)($ad['created_by'] ?? 0) === $userId) {
            $count++;
        }
    }
    return $count;
}

function countBlockedUsers(): int
{
    $count = 0;
    foreach (getUsers() as $user) {
        if (!empty($user['is_blocked'])) {
            $count++;
        }
    }
    return $count;
}

function requireNotBlocked(): void
{
    if (!isLoggedIn()) {
        return;
    }
    $user = findUserById((int)currentUser()['id']);
    if ($user && isUserBlocked($user)) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
        setFlash('danger', 'Tvoj nalog je blokiran' . (!empty($user['blocked_reason']) ? ': ' . $user['blocked_reason'] : '.') . ' Kontaktiraj podršku ako misliš da je greška.');
        header('Location: /login.php');
        exit;
    }
}
