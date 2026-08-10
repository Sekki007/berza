<?php

declare(strict_types=1);

/**
 * @return list<array{key:string,service_id:string,label:string,price:int,enabled:bool,apple_only:bool,free_daily:bool}>
 */
function defaultImeiServices(): array
{
    $services = [
        ['key' => 'svc_1', 'service_id' => '1', 'label' => 'Find My iPhone [ FMI ] (ON/OFF)', 'price' => 1, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_2', 'service_id' => '2', 'label' => 'Warranty + Activation - Apple [IMEI/SN]', 'price' => 2, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_3', 'service_id' => '3', 'label' => 'Apple FULL INFO [No Carrier]', 'price' => 7, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_4', 'service_id' => '4', 'label' => 'iCloud Clean/Lost Check', 'price' => 2, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_5', 'service_id' => '5', 'label' => 'Blacklist Status (GSMA) -updated', 'price' => 2, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_6', 'service_id' => '6', 'label' => 'Blacklist Pro Check (GSMA) -with history', 'price' => 9, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_8', 'service_id' => '8', 'label' => 'Samsung Info (S1) (IMEI)', 'price' => 4, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_9', 'service_id' => '9', 'label' => 'SOLD BY + GSX', 'price' => 100, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_10', 'service_id' => '10', 'label' => 'IMEI to Model [all brands][IMEI/SN]', 'price' => 1, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_11', 'service_id' => '11', 'label' => 'IMEI to Brand/Model/Name', 'price' => 1, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_13', 'service_id' => '13', 'label' => 'Model + Color + Storage + FMI (+config code)', 'price' => 2, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_14', 'service_id' => '14', 'label' => 'IMEI to SN (Full Convertor)', 'price' => 2, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_15', 'service_id' => '15', 'label' => 'T-mobile (ESN) PRO Check', 'price' => 4, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_16', 'service_id' => '16', 'label' => 'Verizon (ESN) Clean/Lost Status', 'price' => 3, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_17', 'service_id' => '17', 'label' => 'Huawei IMEI Info', 'price' => 7, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_18', 'service_id' => '18', 'label' => 'iMac FMI Status On/Off', 'price' => 30, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_19', 'service_id' => '19', 'label' => 'Apple FULL INFO [+Carrier] B (+MDM ) -updated', 'price' => 15, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_21', 'service_id' => '21', 'label' => 'SAMSUNG INFO & KNOX GUARD (S2)', 'price' => 14, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_22', 'service_id' => '22', 'label' => 'Apple BASIC INFO (PRO) - new', 'price' => 5, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_23', 'service_id' => '23', 'label' => 'Apple Carrier Check (S2) -updated', 'price' => 4, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_25', 'service_id' => '25', 'label' => 'XIAOMI MI LOCK & INFO', 'price' => 5, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_27', 'service_id' => '27', 'label' => 'ONEPLUS IMEI INFO', 'price' => 4, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_33', 'service_id' => '33', 'label' => 'Replacement Status (Active Device)', 'price' => 1, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_34', 'service_id' => '34', 'label' => 'Replaced Status (Original Device)', 'price' => 1, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_36', 'service_id' => '36', 'label' => 'Samsung Info (S1) + Blacklist', 'price' => 6, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_37', 'service_id' => '37', 'label' => 'Samsung Info & KNOX GUARD (S1)', 'price' => 8, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_39', 'service_id' => '39', 'label' => 'APPLE FULL INFO [+Carrier] A-updated', 'price' => 10, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_41', 'service_id' => '41', 'label' => 'MDM Status ON/OFF + Fmi + ModelDesc', 'price' => 23, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_47', 'service_id' => '47', 'label' => 'CHIMAERA Check – Apple Block', 'price' => 45, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_50', 'service_id' => '50', 'label' => 'Apple SERIAL Info(model,size,color)', 'price' => 1, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_51', 'service_id' => '51', 'label' => 'Warranty + Activation - Apple [SN]', 'price' => 1, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_52', 'service_id' => '52', 'label' => 'Apple Model Description (Model, Color, Size)', 'price' => 12, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_58', 'service_id' => '58', 'label' => 'Honor Info', 'price' => 5, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_61', 'service_id' => '61', 'label' => 'Apple Demo Unit Device Info', 'price' => 14, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_62', 'service_id' => '62', 'label' => 'EID INFO (IMEI TO EID)', 'price' => 2, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_63', 'service_id' => '63', 'label' => 'Motorola Info', 'price' => 5, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_64', 'service_id' => '64', 'label' => 'Model Description + MPN+ FMI', 'price' => 25, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_67', 'service_id' => '67', 'label' => 'Warranty Pro +Activation -Apple [IMEI/SN]', 'price' => 3, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_69', 'service_id' => '69', 'label' => 'Apple Info Custom + Carrier V2 (IMEI/SN)', 'price' => 7, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_71', 'service_id' => '71', 'label' => 'Apple IMEI Pair Lookup (IMEI1+IMEI2)', 'price' => 1, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_72', 'service_id' => '72', 'label' => 'Google Pixel Info + Warranty (S2)', 'price' => 10, 'enabled' => true, 'apple_only' => false],
        ['key' => 'svc_73', 'service_id' => '73', 'label' => 'Apple Simlock - cheap - BETA', 'price' => 1, 'enabled' => true, 'apple_only' => true],
        ['key' => 'svc_74', 'service_id' => '74', 'label' => 'SOLD BY - simple- NEW', 'price' => 65, 'enabled' => true, 'apple_only' => false],
    ];
    return array_map(static function (array $service): array {
        if (!array_key_exists('free_daily', $service)) {
            $service['free_daily'] = false;
        }
        return $service;
    }, $services);
}

function defaultSiteSettings(): array
{
    return [
        'site_name' => 'KupiTelefon',
        'logo_telefon' => 'Kupi',
        'logo_berza' => 'Telefon',
        'topbar_text' => 'Telefoni · tableti · satovi · servis · Srbija',
        'search_placeholder' => 'Pretraži telefon, tablet, sat ili servis...',
        'footer_copyright' => 'KupiTelefon © 2026',
        'contact_email' => 'podrska@kupitelefon.rs',
        'contact_phone' => '',
        'telegram_channel_url' => 'https://t.me/kupitelefon',
        'viber_community_url' => 'https://invite.viber.com/?g2=AQBMjKShYTQtKFbrUb2YOEJazXmjRUYLEX3UIgJuHs6Ba3KiLYm4yCSCkJgkI1P2',
        'items_per_page' => 20,
        'max_promoted_ads' => 3,
        'max_ads_per_user_homepage' => 2,
        'show_promoted_section' => true,
        'show_ticker' => true,
        'ticker_label' => 'Traže se:',
        'ticker_items' => [
            'iPhone 13 Pro Max kamera',
            'Samsung S23 Ultra ekran',
            'pllak 15 Pro Face ID',
        ],
        'enable_registration' => true,
        'enable_messages' => true,
        'enable_whatsapp' => true,
        'enable_favorites' => true,
        'enable_ad_expiry' => true,
        'ad_max_active_days' => 30,
        'ad_expiry_warning_days' => 3,
        'enable_expiry_email' => true,
        'enable_email_notifications' => true,
        'enable_top_purchase' => true,
        'top_auto_activate' => false,
        'top_payment_info' => "Za TOP koristi kredite. Uplati na račun pa sačekaj potvrdu admina.",
        'top_packages' => [
            ['id' => 'd3', 'days' => 3, 'price' => 300, 'label' => '3 dana'],
            ['id' => 'd7', 'days' => 7, 'price' => 600, 'label' => '7 dana'],
            ['id' => 'd14', 'days' => 14, 'price' => 1000, 'label' => '14 dana'],
        ],
        'enable_credits' => true,
        'credit_currency_label' => 'din',
        'eur_rsd_rate' => 117.0,
        'eur_rsd_auto_nbs' => true,
        'credit_topup_amounts' => [500, 1000, 2000, 5000],
        'credit_payment_info' => "Uplata kredita:\nPrimalac: KupiTelefon\nBroj računa: 160-0000000000000-00\nSvrha: KR-[BROJ] + tvoje korisničko ime\nPrimer: KR-12 marko",
        'ad_renewal_credits' => 200,
        'highlight_credits' => 150,
        'enable_shop_page_paid' => true,
        'shop_page_price_credits' => 1200,
        'shop_page_duration_days' => 30,
        'maintenance_mode' => false,
        'maintenance_message' => 'Sajt je trenutno u održavanju. Pokušajte ponovo uskoro.',
        'cities' => [
            'Beograd', 'Novi Sad', 'Niš', 'Kragujevac', 'Novi Pazar', 'Subotica',
            'Čačak', 'Kraljevo', 'Pančevo', 'Zrenjanin', 'Šabac', 'Leskovac',
            'Užice', 'Valjevo', 'Vranje', 'Smederevo', 'Sombor', 'Pirot',
        ],
        'brands' => ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Google', 'Motorola', 'Nokia', 'OnePlus', 'Honor', 'Oppo', 'Realme', 'Ostalo'],
        'conditions' => ['Novo', 'Kao novo', 'Polovno', 'Oštećeno/Za delove', 'Odlično', 'Servisirano'],
        'sms_template_phone_verify' => 'KupiTelefon kod: {code}. Vazi 10 min.',
        'sms_template_password_reset' => 'KupiTelefon reset lozinke: {code}. Vazi 10 min.',
        'email_templates' => [],
        'facebook_pixel_enabled' => false,
        'facebook_pixel_id' => '',
        'facebook_pixel_require_consent' => true,
        'google_tag_enabled' => false,
        'google_tag_ga4_id' => '',
        'google_tag_ads_id' => '',
        'google_tag_require_consent' => true,
        'ga4_property_id' => '',
        'imei_free_checks_per_day' => 5,
        'imei_services' => defaultImeiServices(),
        'telegram_welcome_text' => '',
        'telegram_welcome_delete_sec' => 0,
        'ad_image_watermark' => true,
        'ad_image_watermark_position' => 'bottom-right',
        'ad_image_watermark_label' => 'KupiTelefon.rs',
    ];
}

/** Jedan slogan sajta (top bar / SEO / footer). */
function siteTagline(): string
{
    $t = trim((string)(siteSettings()['topbar_text'] ?? ''));
    return $t !== '' ? $t : 'Telefoni · tableti · satovi · servis · Srbija';
}

function siteSettings(bool $reload = false): array
{
    static $settings = null;
    if ($reload) {
        $settings = null;
    }
    if ($settings !== null) {
        return $settings;
    }

    $defaults = defaultSiteSettings();
    $stored = readJsonFile('settings.json');
    if ($stored === []) {
        writeJsonFile('settings.json', $defaults);
        $stored = $defaults;
    }

    $settings = array_merge($defaults, $stored);
    return $settings;
}

function clearSiteSettingsCache(): void
{
    siteSettings(true);
}

function saveSiteSettings(array $input): bool
{
    $defaults = defaultSiteSettings();
    $settings = [];

    foreach ($defaults as $key => $defaultValue) {
        if (!array_key_exists($key, $input)) {
            $settings[$key] = $defaultValue;
            continue;
        }

        $value = $input[$key];
        if (is_bool($defaultValue)) {
            $settings[$key] = !empty($value) && (string)$value !== '0';
        } elseif ($key === 'eur_rsd_rate') {
            $settings[$key] = max(1.0, (float)$value);
        } elseif ($key === 'imei_free_checks_per_day') {
            $settings[$key] = max(0, (int)$value);
        } elseif ($key === 'telegram_welcome_delete_sec') {
            $settings[$key] = max(0, (int)$value);
        } elseif ($key === 'max_ads_per_user_homepage') {
            $settings[$key] = max(0, (int)$value);
        } elseif (is_int($defaultValue)) {
            $settings[$key] = max(1, (int)$value);
        } elseif (is_float($defaultValue)) {
            $settings[$key] = (float)$value;
        } elseif ($key === 'top_packages' && is_array($defaultValue)) {
            $settings[$key] = is_array($value) ? array_values($value) : $defaultValue;
        } elseif ($key === 'imei_services' && is_array($defaultValue)) {
            $settings[$key] = is_array($value) ? array_values($value) : $defaultValue;
        } elseif ($key === 'email_templates') {
            $settings[$key] = is_array($value) ? parseEmailTemplatesPost($value) : [];
        } elseif ($key === 'credit_topup_amounts' && is_array($defaultValue)) {
            if (is_array($value)) {
                $settings[$key] = array_values(array_unique(array_filter(array_map('intval', $value), static fn($n) => $n > 0)));
            } else {
                $settings[$key] = $defaultValue;
            }
        } elseif (is_array($defaultValue)) {
            $settings[$key] = is_array($value) ? array_values(array_filter(array_map('trim', $value), static fn($v) => $v !== '')) : $defaultValue;
        } else {
            $settings[$key] = trim((string)$value);
        }
    }

    writeJsonFile('settings.json', $settings);
    clearSiteSettingsCache();
    return true;
}

function parseLines(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}

function isAdmin(): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    if (!empty($user['is_admin'])) {
        return true;
    }

    $profile = findUserById((int)$user['id']);
    return !empty($profile['is_admin']) || ($user['username'] ?? '') === 'admin';
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Nemate pristup admin panelu.');
        header('Location: /nalog.php');
        exit;
    }
}

function checkMaintenanceMode(): void
{
    $settings = siteSettings();
    if (empty($settings['maintenance_mode'])) {
        return;
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $allowed = ['login.php', 'logout.php', 'admin_settings.php', 'dashboard.php', 'ads.php'];
    if (in_array($script, $allowed, true)) {
        return;
    }

    if (isLoggedIn() && isAdmin()) {
        return;
    }

    http_response_code(503);
    $message = h((string)$settings['maintenance_message']);
    echo '<!DOCTYPE html><html lang="sr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Održavanje</title><link rel="stylesheet" href="/assets/css/style.css"></head><body><div class="main-wrap"><main class="content"><div class="form-card" style="margin-top:40px;text-align:center;"><h2>Sajt u održavanju</h2><p style="margin-top:12px;color:var(--text-muted);">' . $message . '</p><p style="margin-top:16px;"><a href="/login.php">Prijava za administratore</a></p></div></main></div></body></html>';
    exit;
}
