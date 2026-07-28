<?php

declare(strict_types=1);

/**
 * Ažurira brending na KupiTelefon u aktivnom storage-u (JSON ili MySQL).
 *
 * Upotreba:
 *   php tools/apply_kupitelefon_branding.php
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$brand = [
    'site_name' => 'KupiTelefon',
    'logo_telefon' => 'Kupi',
    'logo_berza' => 'Telefon',
    'topbar_text' => 'Telefoni · tableti · satovi · servis · Srbija',
    'search_placeholder' => 'Pretraži telefon, tablet, sat ili servis...',
    'footer_copyright' => 'KupiTelefon © 2026',
    'credit_payment_info' => "Uplata kredita:\nPrimalac: KupiTelefon\nBroj računa: 160-0000000000000-00\nSvrha: KR-[BROJ] + tvoje korisničko ime\nPrimer: KR-12 marko",
    'sms_template_phone_verify' => 'KupiTelefon kod: {code}. Vazi 10 min.',
    'sms_template_password_reset' => 'KupiTelefon reset lozinke: {code}. Vazi 10 min.',
];

$current = siteSettings(true);
$merged = array_merge($current, $brand);
writeJsonFile('settings.json', $merged);
clearSiteSettingsCache();

echo 'STORAGE_DRIVER=' . storageDriver() . PHP_EOL;
echo 'Brending ažuriran na KupiTelefon.' . PHP_EOL;
echo 'site_name=' . (string)siteSettings(true)['site_name'] . PHP_EOL;
echo 'logo=' . (string)siteSettings()['logo_telefon'] . (string)siteSettings()['logo_berza'] . PHP_EOL;
exit(0);
