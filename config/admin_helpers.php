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
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
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

/**
 * Admin izmena korisnika.
 *
 * @return array{ok:bool,error?:string}
 */
function adminUpdateUser(int $userId, array $input): array
{
    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }

    $isTargetAdmin = !empty($user['is_admin']) || ($user['username'] ?? '') === 'admin';
    $fullName = trim((string)($input['full_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $phoneRaw = trim((string)($input['phone'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $shopName = trim((string)($input['shop_name'] ?? ''));
    $location = trim((string)($input['location'] ?? ''));
    $newPassword = (string)($input['new_password'] ?? '');
    $accountType = trim((string)($input['account_type'] ?? 'private')) === 'business' ? 'business' : 'private';
    $businessKind = trim((string)($input['business_kind'] ?? ''));
    $pibRaw = trim((string)($input['pib'] ?? ''));
    $businessStatus = trim((string)($input['business_status'] ?? 'none'));
    $verifiedSeller = !empty($input['verified_seller']);
    $isBlocked = !empty($input['is_blocked']);
    $blockedReason = trim((string)($input['blocked_reason'] ?? ''));
    $phoneVerified = !empty($input['phone_verified']);

    if ($fullName === '') {
        return ['ok' => false, 'error' => 'Ime i prezime su obavezni.'];
    }
    if (mb_strlen($username) < 3) {
        return ['ok' => false, 'error' => 'Korisničko ime mora imati bar 3 karaktera.'];
    }
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        return ['ok' => false, 'error' => 'Korisničko ime sme sadržati samo slova, brojeve, . _ -'];
    }
    $existingUser = findUserByUsername($username);
    if ($existingUser && (int)($existingUser['id'] ?? 0) !== $userId) {
        return ['ok' => false, 'error' => 'Korisničko ime je zauzeto.'];
    }

    $phone = '';
    if ($phoneRaw !== '') {
        $normalized = normalizePhoneRs($phoneRaw);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'Telefon nije validan (koristi srpski broj).'];
        }
        $byPhone = findUserByPhone($normalized);
        if ($byPhone && (int)($byPhone['id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'Telefon je već vezan za drugi nalog.'];
        }
        $phone = $normalized;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email nije validan.'];
    }

    if ($newPassword !== '' && strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'Nova lozinka mora imati bar 6 karaktera.'];
    }

    if ($accountType === 'business') {
        if (!in_array($businessKind, allowedBusinessKinds(), true)) {
            return ['ok' => false, 'error' => 'Izaberi vrstu firme (Servis / Mobile Shop / Servis & Mobile Shop).'];
        }
        $pib = $pibRaw !== '' ? normalizePib($pibRaw) : null;
        if ($pibRaw !== '' && $pib === null) {
            return ['ok' => false, 'error' => 'PIB mora imati tačno 9 cifara.'];
        }
        if (!in_array($businessStatus, ['none', 'pending', 'approved', 'rejected'], true)) {
            $businessStatus = 'none';
        }
    } else {
        $businessKind = '';
        $pib = null;
        $businessStatus = 'none';
    }

    $ok = updateUserProfile($userId, [
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'shop_name' => $shopName,
        'location' => $location,
        'account_type' => $accountType,
        'business_kind' => $businessKind,
        'pib' => $pibRaw,
    ]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Profil nije sačuvan. Proveri telefon / PIB.'];
    }

    $patch = [
        'username' => $username,
        'verified_seller' => $verifiedSeller,
        'verified_seller_at' => $verifiedSeller ? date('Y-m-d H:i:s') : null,
    ];

    if ($accountType === 'business') {
        $patch['business_status'] = $businessStatus;
        if ($businessStatus === 'approved') {
            $patch['business_verified_at'] = date('Y-m-d H:i:s');
            $patch['business_reject_reason'] = null;
        } elseif ($businessStatus === 'rejected') {
            $patch['business_verified_at'] = null;
            $patch['business_reject_reason'] = $blockedReason !== '' ? $blockedReason : 'Odbijeno od admina.';
        } elseif ($businessStatus === 'pending') {
            $patch['business_verified_at'] = null;
            if (empty($user['business_requested_at'])) {
                $patch['business_requested_at'] = date('Y-m-d H:i:s');
            }
        } else {
            $patch['business_verified_at'] = null;
            $patch['business_reject_reason'] = null;
        }
    } else {
        $patch['business_status'] = 'none';
        $patch['business_verified_at'] = null;
        $patch['business_requested_at'] = null;
        $patch['business_reject_reason'] = null;
        $patch['business_kind'] = '';
        $patch['pib'] = '';
    }

    if ($phoneVerified && $phone !== '') {
        $patch['phone_verified_at'] = date('Y-m-d H:i:s');
    } elseif (!$phoneVerified) {
        $patch['phone_verified_at'] = null;
    }

    if (!$isTargetAdmin) {
        $patch['is_blocked'] = $isBlocked ? 1 : 0;
        $patch['blocked_reason'] = $isBlocked ? ($blockedReason !== '' ? $blockedReason : 'Blokiran od administratora') : null;
        if (!$isBlocked) {
            $patch['blocked_at'] = null;
        } elseif (empty($user['blocked_at'])) {
            $patch['blocked_at'] = date('Y-m-d H:i:s');
        }
    }

    if (!patchUser($userId, $patch)) {
        return ['ok' => false, 'error' => 'Dodatna polja nisu sačuvana.'];
    }

    if ($newPassword !== '') {
        if (!updateUserPassword($userId, $newPassword)) {
            return ['ok' => false, 'error' => 'Lozinka nije promenjena.'];
        }
    }

    return ['ok' => true];
}

