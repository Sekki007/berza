<?php

declare(strict_types=1);

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
        'items_per_page' => 20,
        'max_promoted_ads' => 3,
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
        } elseif (is_int($defaultValue)) {
            $settings[$key] = max(1, (int)$value);
        } elseif (is_float($defaultValue)) {
            $settings[$key] = (float)$value;
        } elseif ($key === 'top_packages' && is_array($defaultValue)) {
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
