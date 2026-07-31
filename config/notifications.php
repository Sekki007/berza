<?php

declare(strict_types=1);

function ensureNotificationsFile(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('notifications.json'))) {
        writeJsonFile('notifications.json', []);
    }
}

function getAllNotifications(): array
{
    ensureNotificationsFile();
    $items = readJsonFile('notifications.json');
    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $items;
}

function getNotificationsForUser(int $userId, bool $unreadOnly = false): array
{
    return array_values(array_filter(getAllNotifications(), static function ($n) use ($userId, $unreadOnly) {
        if ((int)($n['user_id'] ?? 0) !== $userId) {
            return false;
        }
        if ($unreadOnly && !empty($n['is_read'])) {
            return false;
        }
        return true;
    }));
}

function getUnreadNotificationCount(int $userId): int
{
    return count(getNotificationsForUser($userId, true));
}

function createNotification(int $userId, string $type, string $title, string $body, string $link = ''): int
{
    if ($userId <= 0) {
        return 0;
    }
    ensureNotificationsFile();
    $items = readJsonFile('notifications.json');
    $maxId = 0;
    foreach ($items as $item) {
        $maxId = max($maxId, (int)($item['id'] ?? 0));
    }
    $id = $maxId + 1;
    $items[] = [
        'id' => $id,
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'link' => $link,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    writeJsonFile('notifications.json', $items);
    return $id;
}

function markNotificationRead(int $notificationId, int $userId): bool
{
    $items = readJsonFile('notifications.json');
    $changed = false;
    foreach ($items as &$item) {
        if ((int)($item['id'] ?? 0) === $notificationId && (int)($item['user_id'] ?? 0) === $userId) {
            $item['is_read'] = true;
            $changed = true;
            break;
        }
    }
    unset($item);
    if ($changed) {
        writeJsonFile('notifications.json', $items);
    }
    return $changed;
}

function deleteNotification(int $notificationId, int $userId): bool
{
    $items = readJsonFile('notifications.json');
    $filtered = array_values(array_filter($items, static fn($item) =>
        !((int)($item['id'] ?? 0) === $notificationId && (int)($item['user_id'] ?? 0) === $userId)
    ));
    if (count($filtered) === count($items)) {
        return false;
    }
    writeJsonFile('notifications.json', $filtered);
    return true;
}

function deleteAllNotificationsForUser(int $userId): int
{
    $items = readJsonFile('notifications.json');
    $filtered = array_values(array_filter($items, static fn($item) => (int)($item['user_id'] ?? 0) !== $userId));
    $deleted = count($items) - count($filtered);
    if ($deleted > 0) {
        writeJsonFile('notifications.json', $filtered);
    }
    return $deleted;
}

function markAllNotificationsRead(int $userId): void
{
    $items = readJsonFile('notifications.json');
    $changed = false;
    foreach ($items as &$item) {
        if ((int)($item['user_id'] ?? 0) === $userId && empty($item['is_read'])) {
            $item['is_read'] = true;
            $changed = true;
        }
    }
    unset($item);
    if ($changed) {
        writeJsonFile('notifications.json', $items);
    }
}

function sendRawEmail(string $toEmail, string $subject, string $body, ?string $toName = null): bool
{
    require_once __DIR__ . '/mail.php';

    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Prefer Zoho SMTP; fallback to PHP mail() only if SMTP disabled/missing.
    if (mailIsConfigured()) {
        $result = sendSmtpEmail($toEmail, $subject, $body, $toName);
        return !empty($result['ok']);
    }

    $settings = siteSettings();
    $from = trim((string)($settings['contact_email'] ?? ''));
    $smtpFrom = trim((string)envValue('SMTP_FROM_EMAIL', 'podrska@kupitelefon.rs'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = filter_var($smtpFrom, FILTER_VALIDATE_EMAIL) ? $smtpFrom : 'podrska@kupitelefon.rs';
    }
    $fromName = trim((string)envValue('SMTP_FROM_NAME', 'KupiTelefon.rs'));
    $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n"
        . 'Reply-To: ' . $from . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8';
    $ok = @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (function_exists('mailLog')) {
        mailLog((bool)$ok, $toEmail, $subject, $ok ? null : 'PHP mail() failed or SMTP not configured');
    }
    return (bool)$ok;
}

function userWantsEmailNotifications(?array $user): bool
{
    if (!$user) {
        return false;
    }
    if (array_key_exists('notify_email', $user)) {
        return !empty($user['notify_email']);
    }
    return true;
}

function emailNotificationsEnabled(): bool
{
    $settings = siteSettings();
    if (array_key_exists('enable_email_notifications', $settings)) {
        return !empty($settings['enable_email_notifications']);
    }
    return !empty($settings['enable_expiry_email']);
}

/**
 * In-app always; email when global + user preference + valid email.
 */
function notifyUser(int $userId, string $type, string $title, string $body, string $link = '', bool $emailToo = true): int
{
    $id = createNotification($userId, $type, $title, $body, $link);
    if (function_exists('telegramEnabled') && telegramEnabled()) {
        sendUserTelegramNotification($userId, $type, $title, $body, $link);
    }
    if (function_exists('sendPushToUser')) {
        // Push: nove poruke + ostala obaveštenja ako je uključen kanal
        $pushBody = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $body)) ?? $body);
        if (mb_strlen($pushBody) > 160) {
            $pushBody = mb_substr($pushBody, 0, 157) . '…';
        }
        sendPushToUser($userId, $type, $title, $pushBody, $link);
    }
    if ($emailToo && emailNotificationsEnabled()) {
        $user = findUserById($userId);
        if ($user && userWantsEmailNotifications($user)) {
            $name = trim((string)($user['full_name'] ?? $user['username'] ?? ''));
            $templateKey = in_array($type, ['new_message', 'ad_expiry_warning', 'ad_expired', 'saved_search_match'], true)
                ? $type
                : 'notification';
            $rendered = renderEmailTemplate($templateKey, [
                'name' => $name,
                'title' => $title,
                'body' => $body,
                'link' => $link,
            ]);
            sendUserEmail(
                $userId,
                $rendered['subject'] !== '' ? $rendered['subject'] : ('KupiTelefon: ' . $title),
                $rendered['body'] !== '' ? $rendered['body'] : $body,
                $name !== '' ? $name : null
            );
        }
    }
    return $id;
}

function sendUserEmail(int $userId, string $subject, string $body, ?string $toName = null): bool
{
    if (!emailNotificationsEnabled()) {
        return false;
    }
    $user = findUserById($userId);
    if (!$user || !userWantsEmailNotifications($user)) {
        return false;
    }
    $email = trim((string)($user['email'] ?? ''));
    $name = $toName;
    if ($name === null) {
        $name = trim((string)($user['full_name'] ?? $user['username'] ?? '')) ?: null;
    }
    return sendRawEmail($email, $subject, $body, $name);
}

function adMaxActiveDays(): int
{
    return max(1, (int)(siteSettings()['ad_max_active_days'] ?? 30));
}

function adExpiryWarningDays(): int
{
    return max(1, (int)(siteSettings()['ad_expiry_warning_days'] ?? 3));
}

function adExpiryEnabled(): bool
{
    return !empty(siteSettings()['enable_ad_expiry']);
}

function computeAdExpiresAt(?string $fromDate = null): string
{
    $base = $fromDate ? strtotime($fromDate) : time();
    if ($base === false) {
        $base = time();
    }
    return date('Y-m-d H:i:s', $base + adMaxActiveDays() * 86400);
}

function adDaysRemaining(array $ad): ?int
{
    if (empty($ad['expires_at'])) {
        return null;
    }
    $ts = strtotime((string)$ad['expires_at']);
    if ($ts === false) {
        return null;
    }
    return (int)ceil(($ts - time()) / 86400);
}

function renewAd(int $adId, int $userId): bool
{
    $ad = getAdById($adId);
    if (!$ad || (int)($ad['created_by'] ?? 0) !== $userId) {
        return false;
    }
    $ads = readJsonFile('ads.json');
    foreach ($ads as &$row) {
        if ((int)($row['id'] ?? 0) !== $adId) {
            continue;
        }
        $row['is_active'] = 1;
        $row['is_sold'] = (int)($row['is_sold'] ?? 0);
        $row['expires_at'] = computeAdExpiresAt();
        $row['expiry_warned_at'] = null;
        $row['updated_at'] = date('Y-m-d H:i:s');
        writeJsonFile('ads.json', $ads);
        return true;
    }
    return false;
}

/**
 * Deaktivira istekle oglase i šalje upozorenja N dana ranije.
 * Radi max jednom na 30 min (throttle).
 */
function processAdExpirations(bool $force = false): array
{
    $result = ['warned' => 0, 'expired' => 0, 'skipped' => false];
    if (!adExpiryEnabled()) {
        $result['skipped'] = true;
        return $result;
    }

    $statePath = 'expiry_state.json';
    $state = readJsonFile($statePath);
    $lastRun = strtotime((string)($state['last_run'] ?? '')) ?: 0;
    if (!$force && $lastRun > 0 && (time() - $lastRun) < 1800) {
        $result['skipped'] = true;
        return $result;
    }

    $warningDays = adExpiryWarningDays();
    $ads = readJsonFile('ads.json');
    $changed = false;
    $now = time();

    foreach ($ads as &$ad) {
        if ((int)($ad['is_active'] ?? 0) !== 1) {
            continue;
        }
        if (!empty($ad['is_sold'])) {
            continue;
        }

        if (empty($ad['expires_at'])) {
            $created = (string)($ad['created_at'] ?? date('Y-m-d H:i:s'));
            $ad['expires_at'] = computeAdExpiresAt($created);
            $changed = true;
        }

        $expiresTs = strtotime((string)$ad['expires_at']);
        if ($expiresTs === false) {
            continue;
        }

        $ownerId = (int)($ad['created_by'] ?? 0);
        $title = (string)($ad['title'] ?? 'Oglas');
        $adId = (int)($ad['id'] ?? 0);
        $daysLeft = (int)ceil(($expiresTs - $now) / 86400);

        // Upozorenje pre isteka
        if ($daysLeft > 0 && $daysLeft <= $warningDays && empty($ad['expiry_warned_at']) && $ownerId > 0) {
            $ad['expiry_warned_at'] = date('Y-m-d H:i:s');
            $changed = true;
            $result['warned']++;
            notifyUser(
                $ownerId,
                'ad_expiry_warning',
                'Oglas uskoro ističe',
                "Oglas „{$title}” ističe za {$daysLeft} dana ({$ad['expires_at']}). Produži ga na nalogu da ostane aktivan.",
                '/nalog.php?tab=oglasi'
            );
        }

        // Istekao
        if ($expiresTs <= $now) {
            $ad['is_active'] = 0;
            $ad['updated_at'] = date('Y-m-d H:i:s');
            $changed = true;
            $result['expired']++;
            if ($ownerId > 0) {
                notifyUser(
                    $ownerId,
                    'ad_expired',
                    'Oglas je istekao',
                    "Oglas „{$title}” više nije aktivan. Možeš ga produžiti na nalogu.",
                    '/nalog.php?tab=oglasi'
                );
            }
        }
    }
    unset($ad);

    if ($changed) {
        writeJsonFile('ads.json', $ads);
    }
    writeJsonFile($statePath, ['last_run' => date('Y-m-d H:i:s'), 'last_result' => $result]);

    return $result;
}
