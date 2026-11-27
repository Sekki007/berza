<?php

declare(strict_types=1);

require_once __DIR__ . '/city_normalize.php';

/**
 * Predlog tipa oglasa. Ne upisuje ništa.
 * Očigledan slučaj (trotinet, futrola, „Tražim”) sme da se ispravi pri čuvanju.
 * „iPhone 13 sa novom baterijom” ostaje telefon ako postoji model i cena telefona.
 *
 * @return array{
 *   ad_type:string,
 *   category_group:string,
 *   listing_type:string,
 *   brand:string,
 *   reason:string,
 *   confident:bool,
 *   changed:bool
 * }
 */
function classifyAd(array $ad): array
{
    $title = trim((string)($ad['title'] ?? ''));
    $model = trim((string)($ad['model'] ?? ''));
    $text = mb_strtolower($title . ' ' . $model . ' ' . (string)($ad['description'] ?? ''));
    $currentType = function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? 'telefon');
    $currentListing = function_exists('getAdListingType') ? getAdListingType($ad) : (string)($ad['listing_type'] ?? 'sell');
    $currentBrand = trim((string)($ad['brand'] ?? ''));

    $listing = $currentListing;
    $reason = '';
    if (preg_match('/^\s*(tražim|trazim|otkup|kupujem|kupujem:|traži se)\b/u', $text) === 1
        || preg_match('/\b(tražim|trazim)\s*:/u', $text) === 1) {
        $listing = 'buy';
        $reason = 'naslov je potražnja (tražim/otkup/kupujem)';
    }

    $phoneLike = adLooksLikePhone($ad, $text);
    $bucket = 'telefon';
    $group = 'phones';
    $confident = false;

    $rules = [
        ['need' => ['trotinet', 'm365', 'elektricni trotinet', 'električni trotinet'], 'type' => 'delovi', 'group' => 'trotinet', 'why' => 'trotinet ili deo trotineta'],
        ['need' => ['guma za', 'sajla', 'prednje svetlo', 'prednje svjetlo'], 'type' => 'delovi', 'group' => 'trotinet', 'why' => 'deo trotineta'],
        ['need' => ['narukvica', 'narukvice', 'kais za sat', 'kaiš za sat', 'watch band'], 'type' => 'delovi', 'group' => 'sat', 'why' => 'narukvica ili kaiš'],
        ['need' => ['futrola', 'maska za', 'maskica', 'zastitno staklo', 'zaštitno staklo'], 'type' => 'delovi', 'group' => 'oprema', 'why' => 'futrola, maska ili staklo'],
        ['need' => ['punjac', 'punjač', 'kabl ', 'kabel '], 'type' => 'delovi', 'group' => 'oprema', 'why' => 'punjač ili kabl'],
        ['need' => ['ekran za', 'displej za', 'maticna ploca', 'matična ploča', 'lcd za'], 'type' => 'delovi', 'group' => 'delovi', 'why' => 'rezervni deo telefona'],
    ];

    if (!$phoneLike) {
        foreach ($rules as $rule) {
            foreach ($rule['need'] as $needle) {
                if (str_contains($text, $needle)) {
                    $bucket = $rule['type'];
                    $group = $rule['group'];
                    $reason = trim($reason . ' ' . $rule['why']);
                    $confident = true;
                    break 2;
                }
            }
        }
        if (!$confident && preg_match('/\bbaterij[ae]\b/u', $text) === 1 && !str_contains($text, 'battery health') && !str_contains($text, 'zdravlje baterije')) {
            $bucket = 'delovi';
            $group = 'delovi';
            $reason = trim($reason . ' baterija kao deo, bez modela telefona');
            $confident = true;
        }
    }

    $brand = suggestAdBrand($text, $currentBrand);
    $brandChanged = $brand !== '' && $brand !== $currentBrand;
    if ($brandChanged && $reason === '') {
        $reason = 'naslov pripada brendu ' . $brand;
    }

    $changed = $bucket !== $currentType || $listing !== $currentListing || $brandChanged;
    if ($reason === '' && !$changed) {
        $reason = 'nema predloga';
    }

    return [
        'ad_type' => $bucket,
        'category_group' => $group,
        'listing_type' => $listing,
        'brand' => $brand !== '' ? $brand : $currentBrand,
        'reason' => trim($reason),
        'confident' => $confident || $listing !== $currentListing,
        'changed' => $changed,
    ];
}

function adLooksLikePhone(array $ad, string $text): bool
{
    $hasModel = trim((string)($ad['phone_model_id'] ?? '')) !== '';
    if (!$hasModel && function_exists('phoneCatalogMatchAd')) {
        $match = phoneCatalogMatchAd($ad);
        $hasModel = ($match['status'] ?? '') === 'linked';
    }
    $price = function_exists('adPriceEur') ? adPriceEur($ad) : (float)($ad['price'] ?? 0);
    $phonePrice = $price >= 40;
    $batteryPhrase = str_contains($text, 'sa novom baterijom')
        || str_contains($text, 'nova baterija')
        || str_contains($text, 'battery health')
        || str_contains($text, 'zdravlje baterije');
    if ($hasModel && $phonePrice && ($batteryPhrase || !preg_match('/\b(trotinet|futrola|narukvica|punjac|punjač|displej za|ekran za)\b/u', $text))) {
        return true;
    }
    if ($hasModel && $phonePrice && $batteryPhrase) {
        return true;
    }
    return false;
}

function suggestAdBrand(string $text, string $current): string
{
    $map = [
        'Samsung' => ['galaxy', 'samsung'],
        'Apple' => ['iphone', 'ipad'],
        'Xiaomi' => ['redmi', 'poco', 'xiaomi'],
        'Huawei' => ['huawei'],
        'Honor' => ['honor'],
        'Google' => ['pixel'],
    ];
    $found = '';
    foreach ($map as $brand => $needles) {
        foreach ($needles as $needle) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $text) === 1) {
                $found = $brand;
                break 2;
            }
        }
    }
    if ($found === '' || mb_strtolower($found) === mb_strtolower($current)) {
        return $current;
    }
    if ($current === '' || $current === 'Ostalo') {
        return $found;
    }
    return $found;
}

/**
 * Ispravlja samo očigledne slučajeve (siguran klasifikator). Ne dira ostalo.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function applyObviousAdClassification(array $payload): array
{
    $suggestion = classifyAd($payload);
    if (empty($suggestion['confident']) || empty($suggestion['changed'])) {
        return $payload;
    }
    if ($suggestion['ad_type'] !== '' && $suggestion['ad_type'] !== (string)($payload['ad_type'] ?? '')) {
        $payload['ad_type'] = $suggestion['ad_type'];
        if ($suggestion['category_group'] !== '') {
            $payload['category_group'] = $suggestion['category_group'];
        }
    }
    if ($suggestion['listing_type'] !== '' && $suggestion['listing_type'] !== (string)($payload['listing_type'] ?? 'sell')) {
        $payload['listing_type'] = $suggestion['listing_type'];
    }
    if ($suggestion['brand'] !== '' && $suggestion['brand'] !== (string)($payload['brand'] ?? '') && ($payload['brand'] ?? '') !== '') {
        $payload['brand_suggestion'] = $suggestion['brand'];
    } elseif ($suggestion['brand'] !== '' && trim((string)($payload['brand'] ?? '')) === '') {
        $payload['brand'] = $suggestion['brand'];
    }
    return $payload;
}

/**
 * Uvezeni oglasi bez izmene duže od N dana. Podrazumevano isključeno:
 * ne šalje poštu i ne gasi oglase. Samo broji koliko bi pravilo pogodilo.
 *
 * @return array{enabled:bool,days:int,affected:int}
 */
function staleImportedAdsReport(int $days = 90): array
{
    $settings = function_exists('siteSettings') ? siteSettings() : [];
    $enabled = !empty($settings['stale_import_reminder_enabled']);
    $days = max(30, (int)($settings['stale_import_reminder_days'] ?? $days));
    $cutoff = time() - ($days * 86400);
    $affected = 0;
    if (function_exists('getAllAds')) {
        foreach (getAllAds() as $ad) {
            if (!is_array($ad) || (int)($ad['is_active'] ?? 0) !== 1 || !empty($ad['is_sold'])) {
                continue;
            }
            $created = strtotime((string)($ad['created_at'] ?? ''));
            $updated = strtotime((string)($ad['updated_at'] ?? ''));
            if ($created === false || $created > $cutoff) {
                continue;
            }
            if ($updated !== false && $updated > $created + 86400 && $updated > $cutoff) {
                continue;
            }
            $affected++;
        }
    }
    return ['enabled' => $enabled, 'days' => $days, 'affected' => $affected];
}
