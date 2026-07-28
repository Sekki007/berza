<?php

declare(strict_types=1);

function storefrontEnabled(): bool
{
    return !empty(siteSettings()['enable_shop_page_paid']);
}

function storefrontPriceCredits(): int
{
    return max(1, (int)(siteSettings()['shop_page_price_credits'] ?? 1200));
}

function storefrontDurationDays(): int
{
    return max(1, (int)(siteSettings()['shop_page_duration_days'] ?? 30));
}

function storefrontPaymentMethodsOptions(): array
{
    return [
        'cash' => 'Gotovina',
        'card' => 'Kartice',
        'installments' => 'Rate',
        'bank' => 'Prenos na račun',
    ];
}

function storefrontIsActive(?array $user): bool
{
    if (!$user || !isBusinessVerified($user)) {
        return false;
    }
    $until = strtotime((string)($user['shop_page_until'] ?? ''));
    if ($until === false) {
        return false;
    }
    return $until >= time();
}

function storefrontUrlForUser(array $user): string
{
    $username = trim((string)($user['username'] ?? ''));
    if ($username === '') {
        return '/usluge.php';
    }
    return '/usluge/' . rawurlencode($username);
}

function storefrontPurchase(int $userId): array
{
    if (!storefrontEnabled()) {
        return ['ok' => false, 'error' => 'Mini stranica trenutno nije dostupna.'];
    }
    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }
    if (!isBusinessVerified($user)) {
        return ['ok' => false, 'error' => 'Mini stranica je dostupna samo za verifikovane firme (PIB).'];
    }
    $cost = storefrontPriceCredits();
    $balance = getUserCredits($userId);
    if ($balance < $cost) {
        return ['ok' => false, 'error' => 'Nemaš dovoljno kredita.'];
    }

    $durationDays = storefrontDurationDays();
    $now = time();
    $baseTs = $now;
    $existing = strtotime((string)($user['shop_page_until'] ?? ''));
    if ($existing !== false && $existing > $now) {
        $baseTs = $existing;
    }
    $newUntil = date('Y-m-d H:i:s', $baseTs + ($durationDays * 86400));

    if (!adjustUserCredits($userId, -$cost, 'shop_page_purchase', 'Mini stranica (' . $durationDays . ' dana)')) {
        return ['ok' => false, 'error' => 'Kupovina nije uspela.'];
    }

    $ok = patchUser($userId, [
        'shop_page_enabled' => true,
        'shop_page_until' => $newUntil,
        'shop_page_updated_at' => date('Y-m-d H:i:s'),
    ]);
    if (!$ok) {
        // rollback credits
        adjustUserCredits($userId, $cost, 'shop_page_refund', 'Povraćaj: greška aktivacije mini stranice');
        return ['ok' => false, 'error' => 'Aktivacija nije uspela.'];
    }

    notifyUser(
        $userId,
        'shop_page_activated',
        'Mini stranica je aktivirana',
        'Mini stranica je aktivna do ' . date('d.m.Y.', strtotime($newUntil) ?: time()) . '.',
        '/nalog.php?tab=mini_sajt'
    );

    return [
        'ok' => true,
        'cost' => $cost,
        'until' => $newUntil,
        'days' => $durationDays,
    ];
}

/**
 * Admin: aktivira ili produžava mini sajt bez naplate kreditima.
 */
function storefrontAdminGrant(int $userId, ?int $days = null): array
{
    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }
    if (!isBusinessVerified($user)) {
        return ['ok' => false, 'error' => 'Prvo potvrdi firmu (PIB), pa onda aktiviraj mini sajt.'];
    }

    $durationDays = $days !== null && $days > 0 ? $days : storefrontDurationDays();
    $now = time();
    $baseTs = $now;
    $existing = strtotime((string)($user['shop_page_until'] ?? ''));
    if ($existing !== false && $existing > $now) {
        $baseTs = $existing;
    }
    $newUntil = date('Y-m-d H:i:s', $baseTs + ($durationDays * 86400));

    $ok = patchUser($userId, [
        'shop_page_enabled' => true,
        'shop_page_until' => $newUntil,
        'shop_page_updated_at' => date('Y-m-d H:i:s'),
        'shop_page_admin_granted' => true,
        'shop_page_admin_granted_at' => date('Y-m-d H:i:s'),
    ]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Aktivacija nije uspela.'];
    }

    notifyUser(
        $userId,
        'shop_page_activated',
        'Mini stranica je aktivirana',
        'Admin ti je omogućio mini sajt do ' . date('d.m.Y.', strtotime($newUntil) ?: time()) . '.',
        '/nalog.php?tab=mini_sajt'
    );

    return [
        'ok' => true,
        'until' => $newUntil,
        'days' => $durationDays,
    ];
}

/**
 * Admin: isključuje mini sajt odmah.
 */
function storefrontAdminRevoke(int $userId): array
{
    $user = findUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Korisnik nije pronađen.'];
    }

    $ok = patchUser($userId, [
        'shop_page_enabled' => false,
        'shop_page_until' => null,
        'shop_page_updated_at' => date('Y-m-d H:i:s'),
    ]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Isključivanje nije uspelo.'];
    }

    notifyUser(
        $userId,
        'shop_page_revoked',
        'Mini stranica je isključena',
        'Admin je isključio tvoj mini sajt.',
        '/nalog.php?tab=mini_sajt'
    );

    return ['ok' => true];
}

function storefrontPayloadFromInput(array $input): array
{
    $payments = $input['shop_page_payment_methods'] ?? [];
    if (!is_array($payments)) {
        $payments = [];
    }
    $allowed = storefrontPaymentMethodsOptions();
    $cleanPayments = [];
    foreach ($payments as $k) {
        $key = trim((string)$k);
        if ($key !== '' && isset($allowed[$key])) {
            $cleanPayments[] = $key;
        }
    }

    return [
        'shop_page_legal_name' => mb_substr(trim((string)($input['shop_page_legal_name'] ?? '')), 0, 160),
        'shop_page_registration_no' => mb_substr(trim((string)($input['shop_page_registration_no'] ?? '')), 0, 32),
        'shop_page_title' => mb_substr(trim((string)($input['shop_page_title'] ?? '')), 0, 100),
        'shop_page_tagline' => mb_substr(trim((string)($input['shop_page_tagline'] ?? '')), 0, 160),
        'shop_page_description' => mb_substr(trim((string)($input['shop_page_description'] ?? '')), 0, 4000),
        'shop_page_address' => mb_substr(trim((string)($input['shop_page_address'] ?? '')), 0, 180),
        'shop_page_work_hours' => mb_substr(trim((string)($input['shop_page_work_hours'] ?? '')), 0, 700),
        'shop_page_contact_email' => mb_substr(trim((string)($input['shop_page_contact_email'] ?? '')), 0, 160),
        'shop_page_contact_whatsapp' => mb_substr(trim((string)($input['shop_page_contact_whatsapp'] ?? '')), 0, 40),
        'shop_page_website' => mb_substr(trim((string)($input['shop_page_website'] ?? '')), 0, 180),
        'shop_page_instagram' => mb_substr(trim((string)($input['shop_page_instagram'] ?? '')), 0, 180),
        'shop_page_facebook' => mb_substr(trim((string)($input['shop_page_facebook'] ?? '')), 0, 180),
        'shop_page_tiktok' => mb_substr(trim((string)($input['shop_page_tiktok'] ?? '')), 0, 180),
        'shop_page_payment_methods' => array_values(array_unique($cleanPayments)),
        'shop_page_hours_weekly' => storefrontWeeklyHoursFromInput($input),
        'shop_page_services' => storefrontParseNameValueLines((string)($input['shop_page_services_text'] ?? ''), 40, 120),
        'shop_page_faq' => storefrontParseNameValueLines((string)($input['shop_page_faq_text'] ?? ''), 30, 260),
        'shop_page_updated_at' => date('Y-m-d H:i:s'),
    ];
}

function storefrontWeeklyDayLabels(): array
{
    return [
        'mon' => 'Ponedeljak',
        'tue' => 'Utorak',
        'wed' => 'Sreda',
        'thu' => 'Četvrtak',
        'fri' => 'Petak',
        'sat' => 'Subota',
        'sun' => 'Nedelja',
    ];
}

function storefrontWeeklyHoursDefault(): array
{
    return [
        'mon' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
        'tue' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
        'wed' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
        'thu' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
        'fri' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
        'sat' => ['closed' => false, 'open' => '09:00', 'close' => '14:00'],
        'sun' => ['closed' => true, 'open' => '00:00', 'close' => '00:00'],
    ];
}

function storefrontNormalizeTime(string $value, string $fallback): string
{
    $value = trim($value);
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $fallback;
    }
    [$h, $m] = array_map('intval', explode(':', $value));
    $h = max(0, min(23, $h));
    $m = max(0, min(59, $m));
    return sprintf('%02d:%02d', $h, $m);
}

function storefrontWeeklyHoursFromInput(array $input): array
{
    $defaults = storefrontWeeklyHoursDefault();
    $days = storefrontWeeklyDayLabels();
    $out = [];
    foreach (array_keys($days) as $day) {
        $closed = !empty($input['shop_page_day_closed_' . $day]);
        $open = storefrontNormalizeTime((string)($input['shop_page_day_open_' . $day] ?? $defaults[$day]['open']), $defaults[$day]['open']);
        $close = storefrontNormalizeTime((string)($input['shop_page_day_close_' . $day] ?? $defaults[$day]['close']), $defaults[$day]['close']);
        $out[$day] = ['closed' => $closed, 'open' => $open, 'close' => $close];
    }
    return $out;
}

function storefrontWeeklyHoursForUser(array $user): array
{
    $defaults = storefrontWeeklyHoursDefault();
    $raw = $user['shop_page_hours_weekly'] ?? null;
    if (!is_array($raw)) {
        return $defaults;
    }
    $days = storefrontWeeklyDayLabels();
    foreach (array_keys($days) as $day) {
        $item = is_array($raw[$day] ?? null) ? $raw[$day] : [];
        $defaults[$day] = [
            'closed' => !empty($item['closed']),
            'open' => storefrontNormalizeTime((string)($item['open'] ?? $defaults[$day]['open']), $defaults[$day]['open']),
            'close' => storefrontNormalizeTime((string)($item['close'] ?? $defaults[$day]['close']), $defaults[$day]['close']),
        ];
    }
    return $defaults;
}

function storefrontParseNameValueLines(string $raw, int $maxLines, int $maxLine): array
{
    $lines = preg_split('/\R/u', $raw) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!str_contains($line, '|')) {
            $name = mb_substr($line, 0, $maxLine);
            $value = '';
        } else {
            [$name, $value] = array_map('trim', explode('|', $line, 2));
            $name = mb_substr($name, 0, $maxLine);
            $value = mb_substr($value, 0, $maxLine);
        }
        if ($name === '') {
            continue;
        }
        $out[] = ['name' => $name, 'value' => $value];
        if (count($out) >= $maxLines) {
            break;
        }
    }
    return $out;
}

function storefrontLinesFromPairs(array $items): string
{
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['name'] ?? ''));
        $value = trim((string)($item['value'] ?? ''));
        if ($name === '') {
            continue;
        }
        $lines[] = $name . ($value !== '' ? ' | ' . $value : '');
    }
    return implode("\n", $lines);
}

function storefrontOpenStatus(array $user): array
{
    $hours = storefrontWeeklyHoursForUser($user);
    $days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $now = new DateTime('now');
    $dayKey = $days[(int)$now->format('w')] ?? 'mon';
    $day = $hours[$dayKey] ?? ['closed' => true, 'open' => '00:00', 'close' => '00:00'];
    if (!empty($day['closed'])) {
        return ['open' => false, 'label' => 'Zatvoreno danas'];
    }
    $cur = (int)$now->format('H') * 60 + (int)$now->format('i');
    [$oh, $om] = array_map('intval', explode(':', (string)$day['open']));
    [$ch, $cm] = array_map('intval', explode(':', (string)$day['close']));
    $openAt = $oh * 60 + $om;
    $closeAt = $ch * 60 + $cm;
    $isOpen = $cur >= $openAt && $cur < $closeAt;
    return ['open' => $isOpen, 'label' => $isOpen ? 'Otvoreno sada' : 'Trenutno zatvoreno'];
}

function storefrontUploadsDir(): string
{
    return dirname(__DIR__) . '/public/uploads/storefront';
}

function ensureStorefrontUploadsDir(): void
{
    $dir = storefrontUploadsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function handleStorefrontCoverUpload(int $userId, ?string $existingCover = null): ?string
{
    ensureStorefrontUploadsDir();

    if (!empty($_POST['shop_page_cover_remove'])) {
        if ($existingCover && str_starts_with($existingCover, '/uploads/storefront/')) {
            $old = dirname(__DIR__) . '/public' . $existingCover;
            if (is_file($old)) {
                @unlink($old);
            }
        }
        return null;
    }

    if (!isset($_FILES['shop_page_cover']) || !is_array($_FILES['shop_page_cover'])) {
        return $existingCover;
    }
    if ((int)($_FILES['shop_page_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $existingCover;
    }

    $tmp = (string)($_FILES['shop_page_cover']['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return $existingCover;
    }
    $type = mime_content_type($tmp) ?: '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($type, $allowed, true)) {
        return $existingCover;
    }

    $targetDir = storefrontUploadsDir() . '/' . $userId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $name = 'cover_' . time() . '.jpg';
    $dest = $targetDir . '/' . $name;
    if (!compressAndSaveImage($tmp, $dest, $type)) {
        return $existingCover;
    }

    if ($existingCover && str_starts_with($existingCover, '/uploads/storefront/')) {
        $old = dirname(__DIR__) . '/public' . $existingCover;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    return '/uploads/storefront/' . $userId . '/' . $name;
}

function handleStorefrontGalleryUploads(int $userId, array $existing = []): array
{
    ensureStorefrontUploadsDir();
    $gallery = array_values(array_filter($existing, static fn($v) => is_string($v) && $v !== ''));
    $keep = $_POST['shop_page_gallery_keep'] ?? [];
    if (is_array($keep) && $keep !== []) {
        $gallery = array_values(array_filter($gallery, static fn($img) => in_array($img, $keep, true)));
    } elseif (!empty($_POST['shop_page_gallery_clear'])) {
        $gallery = [];
    }

    $targetDir = storefrontUploadsDir() . '/' . $userId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    if (isset($_FILES['shop_page_gallery']) && is_array($_FILES['shop_page_gallery']['name'] ?? null)) {
        $count = count($_FILES['shop_page_gallery']['name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        for ($i = 0; $i < $count && count($gallery) < 8; $i++) {
            if ((int)($_FILES['shop_page_gallery']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string)($_FILES['shop_page_gallery']['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_file($tmp)) {
                continue;
            }
            $type = mime_content_type($tmp) ?: '';
            if (!in_array($type, $allowed, true)) {
                continue;
            }
            $name = 'gallery_' . time() . '_' . $i . '.jpg';
            $dest = $targetDir . '/' . $name;
            if (compressAndSaveImage($tmp, $dest, $type)) {
                $gallery[] = '/uploads/storefront/' . $userId . '/' . $name;
            }
        }
    }
    return array_values(array_slice($gallery, 0, 8));
}
