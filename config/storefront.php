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
        'shop_page_title' => mb_substr(trim((string)($input['shop_page_title'] ?? '')), 0, 100),
        'shop_page_tagline' => mb_substr(trim((string)($input['shop_page_tagline'] ?? '')), 0, 160),
        'shop_page_description' => mb_substr(trim((string)($input['shop_page_description'] ?? '')), 0, 4000),
        'shop_page_address' => mb_substr(trim((string)($input['shop_page_address'] ?? '')), 0, 180),
        'shop_page_work_hours' => mb_substr(trim((string)($input['shop_page_work_hours'] ?? '')), 0, 500),
        'shop_page_contact_email' => mb_substr(trim((string)($input['shop_page_contact_email'] ?? '')), 0, 160),
        'shop_page_payment_methods' => array_values(array_unique($cleanPayments)),
        'shop_page_updated_at' => date('Y-m-d H:i:s'),
    ];
}
