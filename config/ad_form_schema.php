<?php

declare(strict_types=1);

/**
 * Opcije i pomoćne funkcije za dinamičku formu oglasa.
 */
function adPhoneBrands(): array
{
    $fromSettings = siteSettings()['brands'] ?? [];
    $fallback = ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Google', 'Motorola', 'Nokia', 'OnePlus', 'Honor', 'Oppo', 'Realme', 'Ostalo'];
    $list = [];
    foreach ((is_array($fromSettings) && $fromSettings !== [] ? $fromSettings : $fallback) as $b) {
        $b = trim((string)$b);
        if ($b !== '' && !in_array($b, $list, true)) {
            $list[] = $b;
        }
    }
    if (!in_array('Ostalo', $list, true)) {
        $list[] = 'Ostalo';
    }
    return $list;
}

function adFormSchema(): array
{
    return [
        'listing_types' => [
            'sell' => 'Prodaja',
            'buy' => 'Tražim / Kupovina',
            'trade' => 'Zamena',
            'service' => 'Nudim uslugu',
        ],
        'phone_brands' => adPhoneBrands(),
        'phone_conditions' => ['Novo', 'Kao novo', 'Polovno', 'Oštećeno/Za delove'],
        'storage_options' => ['64GB', '128GB', '256GB', '512GB', '1TB'],
        'ram_options' => ['4GB', '6GB', '8GB', '12GB', '16GB'],
        'sim_statuses' => ['SIM Free', 'Zaključan na mrežu', 'Dual SIM/eSIM'],
        'phone_accessories' => [
            'box' => 'Originalna kutija',
            'charger' => 'Punjač',
            'cable' => 'Kabl',
            'glass' => 'Zaštitno staklo',
            'case' => 'Maska',
        ],
        'equipment_types' => [
            // Rezervni delovi
            'Displej/LCD',
            'Baterija',
            'Kućište/Staklo',
            'Kamera',
            'Matična ploča',
            'Alati za servis',
            'Rezervni delovi',
            // Oprema
            'Maska/Futrola',
            'Zaštitno staklo',
            'Punjač/Kabl',
            'Slušalice',
            'PowerBank',
            'Ostalo',
        ],
        'parts_equipment_types' => [
            'Displej/LCD',
            'Baterija',
            'Kućište/Staklo',
            'Kamera',
            'Matična ploča',
            'Alati za servis',
            'Rezervni delovi',
        ],
        'oprema_equipment_types' => [
            'Maska/Futrola',
            'Zaštitno staklo',
            'Punjač/Kabl',
            'Slušalice',
            'PowerBank',
            'Ostalo',
        ],
        'device_types' => [
            'phone' => 'Telefon',
            'tablet' => 'Tablet',
            'watch' => 'Pametni sat',
            'earbuds' => 'Slušalice / AirPods',
            'other' => 'Ostalo',
        ],
        'parts_conditions' => ['Novo', 'Polovno'],
        'originality_options' => ['Original', 'Kopija/A klasa', 'Univerzalno'],
        'service_types' => [
            'screen' => 'Zamena ekrana',
            'battery' => 'Zamena baterije',
            'unlock' => 'Dekodiranje',
            'board' => 'Popravka ploče',
            'water' => 'Čišćenje od vode',
            'software' => 'Softver',
            'diag' => 'Dijagnostika',
        ],
        'service_extras' => [
            'onsite' => 'Dolazak na adresu',
            'mail' => 'Slanje poštom',
            'loaner' => 'Zamenski telefon',
            'invoice' => 'Račun/Faktura',
        ],
        'contact_methods' => [
            'call' => 'Poziv',
            'message' => 'Poruke na platformi',
            'viber' => 'Viber',
            'whatsapp' => 'WhatsApp',
        ],
        'pickup_methods' => [
            'pickup' => 'Lično preuzimanje',
            'courier' => 'Slanje kurirskom službom',
        ],
    ];
}

function normalizeListingType(string $type, string $adType = 'telefon'): string
{
    $type = strtolower(trim($type));
    $allowed = ['sell', 'buy', 'trade', 'service'];
    if (!in_array($type, $allowed, true)) {
        $type = $adType === 'servis' ? 'service' : 'sell';
    }
    if ($adType === 'servis') {
        return 'service';
    }
    if ($type === 'service' && $adType !== 'servis') {
        return 'sell';
    }
    return $type;
}

function listingTypeLabel(array $ad): string
{
    $schema = adFormSchema();
    $key = getAdListingType($ad);
    return (string)($schema['listing_types'][$key] ?? 'Prodaja');
}

function getAdListingType(array $ad): string
{
    return normalizeListingType((string)($ad['listing_type'] ?? 'sell'), getAdType($ad));
}

function isBuyListing(array $ad): bool
{
    return getAdListingType($ad) === 'buy';
}

function isTradeListing(array $ad): bool
{
    return getAdListingType($ad) === 'trade';
}

/** Kratki bedž za listu/detalj (prazno za običnu prodaju). */
function adIntentBadgeLabel(array $ad): string
{
    return match (getAdListingType($ad)) {
        'buy' => 'Tražim',
        'trade' => 'Zamena',
        'service' => 'Usluga',
        default => '',
    };
}

/** Naslov sa jasnim prefiksom namere (Tražim / Zamena). */
function adDisplayTitle(array $ad): string
{
    $title = trim((string)($ad['title'] ?? ''));
    if ($title === '') {
        return $title;
    }
    $lower = mb_strtolower($title);
    $type = getAdListingType($ad);
    if ($type === 'buy') {
        if (
            !str_starts_with($lower, 'tražim')
            && !str_starts_with($lower, 'trazim')
            && !str_starts_with($lower, 'kupujem')
        ) {
            return 'Tražim: ' . $title;
        }
    } elseif ($type === 'trade') {
        if (
            !str_starts_with($lower, 'zamena')
            && !str_starts_with($lower, 'menjam')
        ) {
            return 'Zamena: ' . $title;
        }
    }
    return $title;
}

/** Glavni tekst u ceni/status bloku na kartici. */
function adCardPriceMainLabel(array $ad): string
{
    if (isBuyListing($ad)) {
        if (!isAdPriceOpen($ad) && (float)($ad['price'] ?? 0) > 0) {
            return 'Do ' . formatAdPrice($ad);
        }
        return 'Traži se';
    }
    if (isTradeListing($ad) && isAdPriceOpen($ad)) {
        return 'Zamena';
    }
    return isAdPriceOpen($ad) ? 'Po dogovoru' : formatAdPrice($ad);
}

/**
 * @return list<string>
 */
function allowedDeviceTypes(): array
{
    return array_keys(adFormSchema()['device_types']);
}

function normalizeDeviceType(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, allowedDeviceTypes(), true) ? $type : 'phone';
}

function getAdDeviceType(array $ad): string
{
    if (getAdType($ad) !== 'telefon') {
        return '';
    }
    return normalizeDeviceType((string)($ad['device_type'] ?? 'phone'));
}

function deviceTypeLabel(string $type): string
{
    $schema = adFormSchema();
    $key = normalizeDeviceType($type);
    return (string)($schema['device_types'][$key] ?? 'Telefon');
}

/** Javni label kategorije (Tablet, Deo, Servis…). */
function adCategoryLabel(array $ad): string
{
    $type = getAdType($ad);
    if ($type === 'telefon') {
        return deviceTypeLabel(getAdDeviceType($ad));
    }
    return adTypeLabel($type);
}

/** @return list<string> */
function normalizeStringList($value, array $allowed = []): array
{
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $item) {
        $item = trim((string)$item);
        if ($item === '') {
            continue;
        }
        if ($allowed !== [] && !in_array($item, $allowed, true)) {
            continue;
        }
        if (!in_array($item, $out, true)) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Parsira dodatna polja forme u payload oglasa.
 *
 * @return array<string, mixed>
 */
function parseAdFormExtras(array $post, string $adType): array
{
    $schema = adFormSchema();
    $listingType = normalizeListingType((string)($post['listing_type'] ?? 'sell'), $adType);

    $contactMethods = normalizeStringList($post['contact_methods'] ?? [], array_keys($schema['contact_methods']));
    if ($contactMethods === []) {
        $contactMethods = ['call', 'message'];
    }
    $pickupMethods = normalizeStringList($post['pickup_methods'] ?? [], array_keys($schema['pickup_methods']));
    if ($pickupMethods === []) {
        $pickupMethods = ['pickup'];
    }

    $extras = [
        'listing_type' => $listingType,
        'contact_methods' => $contactMethods,
        'pickup_methods' => $pickupMethods,
        'device_type' => '',
        'ram' => '',
        'color' => '',
        'sim_status' => '',
        'battery_health' => null,
        'has_warranty' => 0,
        'warranty_months' => null,
        'accessories' => [],
        'equipment_type' => '',
        'compatible_models' => '',
        'originality' => '',
        'service_types' => [],
        'supported_brands' => [],
        'has_work_warranty' => 0,
        'work_warranty_months' => null,
        'service_extras' => [],
    ];

    if ($adType === 'telefon') {
        $extras['device_type'] = normalizeDeviceType((string)($post['device_type'] ?? 'phone'));
        $extras['ram'] = in_array((string)($post['ram'] ?? ''), $schema['ram_options'], true)
            ? (string)$post['ram'] : trim((string)($post['ram'] ?? ''));
        $extras['color'] = trim((string)($post['color'] ?? ''));
        $sim = trim((string)($post['sim_status'] ?? ''));
        $extras['sim_status'] = in_array($sim, $schema['sim_statuses'], true) ? $sim : '';
        $bh = trim((string)($post['battery_health'] ?? ''));
        if ($bh !== '' && is_numeric($bh)) {
            $extras['battery_health'] = max(0, min(100, (int)$bh));
        }
        $extras['has_warranty'] = isset($post['has_warranty']) ? 1 : 0;
        if ($extras['has_warranty']) {
            $wm = (int)($post['warranty_months'] ?? 0);
            $extras['warranty_months'] = $wm > 0 ? $wm : null;
        }
        $extras['accessories'] = normalizeStringList($post['accessories'] ?? [], array_keys($schema['phone_accessories']));
    }

    if ($adType === 'delovi') {
        $eq = trim((string)($post['equipment_type'] ?? ''));
        $extras['equipment_type'] = in_array($eq, $schema['equipment_types'], true) ? $eq : $eq;
        $extras['compatible_models'] = trim((string)($post['compatible_models'] ?? ''));
        $orig = trim((string)($post['originality'] ?? ''));
        $extras['originality'] = in_array($orig, $schema['originality_options'], true) ? $orig : '';
    }

    if ($adType === 'servis') {
        $extras['service_types'] = normalizeStringList($post['service_types'] ?? [], array_keys($schema['service_types']));
        $extras['supported_brands'] = normalizeStringList($post['supported_brands'] ?? [], $schema['phone_brands']);
        $extras['has_work_warranty'] = isset($post['has_work_warranty']) ? 1 : 0;
        if ($extras['has_work_warranty']) {
            $wm = (int)($post['work_warranty_months'] ?? 0);
            $extras['work_warranty_months'] = $wm > 0 ? $wm : null;
        }
        $extras['service_extras'] = normalizeStringList($post['service_extras'] ?? [], array_keys($schema['service_extras']));
    }

    return $extras;
}

/**
 * Lista atributa za prikaz na detalju oglasa.
 *
 * @return list<array{label:string,value:string}>
 */
function adAttributeRows(array $ad): array
{
    $schema = adFormSchema();
    $type = getAdType($ad);
    $rows = [];

    $add = static function (string $label, $value) use (&$rows): void {
        $value = trim((string)$value);
        if ($value === '') {
            return;
        }
        $rows[] = ['label' => $label, 'value' => $value];
    };

    $add('Tip oglasa', listingTypeLabel($ad));

    if ($type === 'telefon') {
        $add('Tip uređaja', deviceTypeLabel(getAdDeviceType($ad)));
        $add('Brend', $ad['brand'] ?? '');
        $add('Model', $ad['model'] ?? '');
        $add('Stanje', $ad['condition_state'] ?? '');
        $add('Memorija', $ad['storage'] ?? '');
        $add('RAM', $ad['ram'] ?? '');
        $add('Boja', $ad['color'] ?? '');
        $add('SIM', $ad['sim_status'] ?? '');
        if (isset($ad['battery_health']) && $ad['battery_health'] !== null && $ad['battery_health'] !== '') {
            $add('Battery Health', (int)$ad['battery_health'] . '%');
        }
        if (!empty($ad['has_warranty'])) {
            $months = (int)($ad['warranty_months'] ?? 0);
            $add('Garancija', $months > 0 ? $months . ' mes.' : 'Da');
        }
        $acc = is_array($ad['accessories'] ?? null) ? $ad['accessories'] : [];
        if ($acc !== []) {
            $labels = [];
            foreach ($acc as $key) {
                $labels[] = $schema['phone_accessories'][$key] ?? $key;
            }
            $add('Prateća oprema', implode(', ', $labels));
        }
    } elseif ($type === 'delovi') {
        $add('Tip opreme', $ad['equipment_type'] ?? '');
        $add('Kompatibilno', $ad['compatible_models'] ?? '');
        $add('Stanje', $ad['condition_state'] ?? '');
        $add('Originalnost', $ad['originality'] ?? '');
        $add('Brend', $ad['brand'] ?? '');
    } else {
        $svc = is_array($ad['service_types'] ?? null) ? $ad['service_types'] : [];
        if ($svc !== []) {
            $labels = [];
            foreach ($svc as $key) {
                $labels[] = $schema['service_types'][$key] ?? $key;
            }
            $add('Usluge', implode(', ', $labels));
        }
        $brands = is_array($ad['supported_brands'] ?? null) ? $ad['supported_brands'] : [];
        if ($brands !== []) {
            $add('Brendovi', implode(', ', $brands));
        }
        if (!empty($ad['has_work_warranty'])) {
            $months = (int)($ad['work_warranty_months'] ?? 0);
            $add('Garancija na rad', $months > 0 ? $months . ' mes.' : 'Da');
        }
        $extra = is_array($ad['service_extras'] ?? null) ? $ad['service_extras'] : [];
        if ($extra !== []) {
            $labels = [];
            foreach ($extra as $key) {
                $labels[] = $schema['service_extras'][$key] ?? $key;
            }
            $add('Dodatno', implode(', ', $labels));
        }
    }

    $contact = is_array($ad['contact_methods'] ?? null) ? $ad['contact_methods'] : [];
    if ($contact !== []) {
        $labels = [];
        foreach ($contact as $key) {
            $labels[] = $schema['contact_methods'][$key] ?? $key;
        }
        $add('Kontakt', implode(', ', $labels));
    }

    $pickup = is_array($ad['pickup_methods'] ?? null) ? $ad['pickup_methods'] : [];
    if ($pickup !== []) {
        $labels = [];
        foreach ($pickup as $key) {
            $labels[] = $schema['pickup_methods'][$key] ?? $key;
        }
        $add('Preuzimanje', implode(', ', $labels));
    }

    return $rows;
}

/**
 * Predloži SEO naslov iz polja forme (bez AI).
 */
function suggestAdTitle(array $ad): string
{
    $type = getAdType($ad);
    $parts = [];

    if ($type === 'delovi') {
        $eq = trim((string)($ad['equipment_type'] ?? ''));
        $brand = trim((string)($ad['brand'] ?? ''));
        $compat = trim((string)($ad['compatible_models'] ?? ''));
        $model = trim((string)($ad['model'] ?? ''));
        if ($eq !== '' && $eq !== 'Ostalo' && $eq !== 'Rezervni delovi') {
            $parts[] = $eq;
        } elseif ($eq === 'Rezervni delovi') {
            $parts[] = 'Delovi';
        }
        if ($brand !== '' && $brand !== 'Ostalo') {
            $parts[] = $brand;
        }
        if ($compat !== '') {
            $parts[] = $compat;
        } elseif ($model !== '') {
            $parts[] = $model;
        }
        $orig = trim((string)($ad['originality'] ?? ''));
        if ($orig !== '') {
            $parts[] = $orig;
        }
    } elseif ($type === 'servis') {
        $parts[] = 'Servis';
        $brand = trim((string)($ad['brand'] ?? ''));
        if ($brand !== '') {
            $parts[] = $brand;
        }
        $title = trim((string)($ad['title'] ?? ''));
        if ($title !== '' && $parts === ['Servis']) {
            return mb_strimwidth($title, 0, 120, '');
        }
    } else {
        $brand = trim((string)($ad['brand'] ?? ''));
        $model = trim((string)($ad['model'] ?? ''));
        $storage = trim((string)($ad['storage'] ?? ''));
        $color = trim((string)($ad['color'] ?? ''));
        $condition = trim((string)($ad['condition_state'] ?? ''));
        if ($brand !== '' && $brand !== 'Ostalo') {
            $parts[] = $brand;
        }
        if ($model !== '') {
            // Avoid "Apple iPhone" duplication if model already has brand
            if ($brand !== '' && $brand !== 'Ostalo' && str_starts_with(mb_strtolower($model), mb_strtolower($brand))) {
                $parts = [$model];
            } else {
                $parts[] = $model;
            }
        }
        if ($storage !== '') {
            $parts[] = $storage;
        }
        if ($color !== '') {
            $parts[] = $color;
        }
        $bits = [];
        if ($condition !== '' && $condition !== 'Polovno') {
            $bits[] = $condition;
        }
        if (!empty($ad['has_warranty'])) {
            $bits[] = 'Garancija';
        }
        $bh = $ad['battery_health'] ?? null;
        if ($bh !== null && $bh !== '' && (int)$bh > 0) {
            $bits[] = 'BH ' . (int)$bh . '%';
        }
        $title = trim(implode(' ', array_filter($parts, static fn($p) => trim((string)$p) !== '')));
        if ($bits !== []) {
            $title = trim($title . ' - ' . implode(' / ', $bits));
        }
        return mb_strimwidth($title, 0, 120, '');
    }

    $title = trim(implode(' ', array_filter($parts, static fn($p) => trim((string)$p) !== '')));
    return mb_strimwidth($title, 0, 120, '');
}
