<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

function parseTopPackagesPost(): array
{
    $ids = $_POST['top_pkg_id'] ?? [];
    $labels = $_POST['top_pkg_label'] ?? [];
    $days = $_POST['top_pkg_days'] ?? [];
    $prices = $_POST['top_pkg_price'] ?? [];
    if (!is_array($ids)) {
        return defaultTopPackages();
    }
    $out = [];
    foreach ($ids as $i => $id) {
        $id = trim((string)$id);
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'label' => trim((string)($labels[$i] ?? $id)),
            'days' => max(1, (int)($days[$i] ?? 1)),
            'price' => max(0, (float)($prices[$i] ?? 0)),
        ];
    }
    return $out !== [] ? $out : defaultTopPackages();
}

$settings = siteSettings();
$tab = trim((string)($_GET['tab'] ?? 'general'));
$validTabs = ['general', 'homepage', 'lists', 'features', 'email', 'sms', 'maintenance'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'general';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_settings.php?tab=' . urlencode($tab));

    if (isset($_POST['refresh_nbs_rate'])) {
        $result = fetchNbsEurRsdRate();
        if (!empty($result['ok'])) {
            $rate = (float)$result['rate'];
            $current = siteSettings();
            $current['eur_rsd_rate'] = round($rate, 4);
            $current['eur_rsd_auto_nbs'] = true;
            saveSiteSettings($current);
            setFlash('success', 'NBS kurs ažuriran: 1 € = ' . number_format($rate, 4, ',', '.') . ' din (lista ' . (string)$result['date'] . ').');
        } else {
            setFlash('danger', 'NBS kurs nije ažuriran: ' . (string)($result['error'] ?? 'greška'));
        }
        header('Location: /admin_settings.php?tab=' . urlencode($tab));
        exit;
    }

    if (isset($_POST['test_smtp_email'])) {
        require_once dirname(__DIR__) . '/config/mail.php';
        $to = trim((string)($_POST['test_smtp_to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $admin = currentUser();
            $to = trim((string)($admin['email'] ?? ''));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Unesi validan email za test.');
            header('Location: /admin_settings.php?tab=features');
            exit;
        }

        // Spreči spam: 1 test na 60s (dupli klik / browser retry dok SMTP „visi“)
        $lastTest = (int)($_SESSION['_smtp_test_at'] ?? 0);
        if ($lastTest > 0 && (time() - $lastTest) < 60) {
            $wait = 60 - (time() - $lastTest);
            setFlash('danger', "Sačekaj {$wait}s pre sledećeg test emaila (zaštita od duplog slanja).");
            header('Location: /admin_settings.php?tab=features');
            exit;
        }
        $_SESSION['_smtp_test_at'] = time();

        @set_time_limit(25);
        $result = sendSmtpEmail(
            $to,
            'KupiTelefon — test email',
            "Zdravo,\n\nOvo je test poruka sa KupiTelefon.rs SMTP (Zoho).\n\nAko vidiš ovaj mail, notifikacije su spremne.\n\n—\nKupiTelefon.rs\n" . appBaseUrl()
        );
        if (!empty($result['ok'])) {
            setFlash('success', 'Test email poslat na ' . $to . '.');
        } else {
            // Dozvoli retry odmah ako nije uspelo
            unset($_SESSION['_smtp_test_at']);
            setFlash('danger', 'Test email nije poslat: ' . (string)($result['error'] ?? 'nepoznata greška'));
        }
        header('Location: /admin_settings.php?tab=features');
        exit;
    }

    $current = siteSettings();
    $payload = array_merge($current, [
        'site_name' => trim((string)($_POST['site_name'] ?? $current['site_name'])),
        'logo_telefon' => trim((string)($_POST['logo_telefon'] ?? $current['logo_telefon'])),
        'logo_berza' => trim((string)($_POST['logo_berza'] ?? $current['logo_berza'])),
        'topbar_text' => trim((string)($_POST['topbar_text'] ?? $current['topbar_text'])),
        'search_placeholder' => trim((string)($_POST['search_placeholder'] ?? $current['search_placeholder'])),
        'footer_copyright' => trim((string)($_POST['footer_copyright'] ?? $current['footer_copyright'])),
        'contact_email' => trim((string)($_POST['contact_email'] ?? $current['contact_email'])),
        'contact_phone' => trim((string)($_POST['contact_phone'] ?? $current['contact_phone'])),
        'items_per_page' => (int)($_POST['items_per_page'] ?? $current['items_per_page']),
        'max_promoted_ads' => (int)($_POST['max_promoted_ads'] ?? $current['max_promoted_ads']),
        'show_promoted_section' => (string)($_POST['show_promoted_section'] ?? '0') === '1',
        'show_ticker' => (string)($_POST['show_ticker'] ?? '0') === '1',
        'ticker_label' => trim((string)($_POST['ticker_label'] ?? $current['ticker_label'])),
        'ticker_items' => parseLines((string)($_POST['ticker_items'] ?? implode("\n", $current['ticker_items']))),
        'enable_registration' => (string)($_POST['enable_registration'] ?? '0') === '1',
        'enable_messages' => (string)($_POST['enable_messages'] ?? '0') === '1',
        'enable_whatsapp' => (string)($_POST['enable_whatsapp'] ?? '0') === '1',
        'enable_favorites' => (string)($_POST['enable_favorites'] ?? '0') === '1',
        'enable_ad_expiry' => (string)($_POST['enable_ad_expiry'] ?? '0') === '1',
        'ad_max_active_days' => (int)($_POST['ad_max_active_days'] ?? $current['ad_max_active_days'] ?? 30),
        'ad_expiry_warning_days' => (int)($_POST['ad_expiry_warning_days'] ?? $current['ad_expiry_warning_days'] ?? 3),
        'enable_expiry_email' => (string)($_POST['enable_expiry_email'] ?? '0') === '1',
        'enable_email_notifications' => (string)($_POST['enable_email_notifications'] ?? '0') === '1',
        'enable_top_purchase' => (string)($_POST['enable_top_purchase'] ?? '0') === '1',
        'top_auto_activate' => (string)($_POST['top_auto_activate'] ?? '0') === '1',
        'top_payment_info' => trim((string)($_POST['top_payment_info'] ?? $current['top_payment_info'] ?? '')),
        'top_packages' => isset($_POST['top_pkg_id']) ? parseTopPackagesPost() : ($current['top_packages'] ?? defaultTopPackages()),
        'enable_credits' => (string)($_POST['enable_credits'] ?? '0') === '1',
        'credit_currency_label' => trim((string)($_POST['credit_currency_label'] ?? $current['credit_currency_label'] ?? 'din')),
        'eur_rsd_rate' => max(1, (float)($_POST['eur_rsd_rate'] ?? $current['eur_rsd_rate'] ?? 117)),
        'eur_rsd_auto_nbs' => (string)($_POST['eur_rsd_auto_nbs'] ?? '0') === '1',
        'credit_payment_info' => trim((string)($_POST['credit_payment_info'] ?? $current['credit_payment_info'] ?? '')),
        'credit_topup_amounts' => isset($_POST['credit_topup_amounts_text'])
            ? array_values(array_filter(array_map('intval', parseLines((string)$_POST['credit_topup_amounts_text'])), static fn($n) => $n > 0))
            : ($current['credit_topup_amounts'] ?? defaultCreditTopupAmounts()),
        'ad_renewal_credits' => (int)($_POST['ad_renewal_credits'] ?? $current['ad_renewal_credits'] ?? 200),
        'highlight_credits' => (int)($_POST['highlight_credits'] ?? $current['highlight_credits'] ?? 150),
        'enable_shop_page_paid' => (string)($_POST['enable_shop_page_paid'] ?? '0') === '1',
        'shop_page_price_credits' => (int)($_POST['shop_page_price_credits'] ?? $current['shop_page_price_credits'] ?? 1200),
        'shop_page_duration_days' => (int)($_POST['shop_page_duration_days'] ?? $current['shop_page_duration_days'] ?? 30),
        'email_templates' => isset($_POST['email_templates']) && is_array($_POST['email_templates'])
            ? parseEmailTemplatesPost($_POST['email_templates'])
            : ($current['email_templates'] ?? []),
        'maintenance_mode' => (string)($_POST['maintenance_mode'] ?? '0') === '1',
        'maintenance_message' => trim((string)($_POST['maintenance_message'] ?? $current['maintenance_message'])),
        'cities' => parseLines((string)($_POST['cities'] ?? implode("\n", $current['cities']))),
        'brands' => parseLines((string)($_POST['brands'] ?? implode("\n", $current['brands']))),
        'conditions' => parseLines((string)($_POST['conditions'] ?? implode("\n", $current['conditions']))),
        'sms_template_phone_verify' => trim((string)($_POST['sms_template_phone_verify'] ?? $current['sms_template_phone_verify'] ?? '')),
        'sms_template_password_reset' => trim((string)($_POST['sms_template_password_reset'] ?? $current['sms_template_password_reset'] ?? '')),
    ]);

    $defaultsSms = defaultSiteSettings();
    foreach (['sms_template_phone_verify', 'sms_template_password_reset'] as $smsKey) {
        $tpl = (string)($payload[$smsKey] ?? '');
        if ($tpl === '' || !str_contains($tpl, '{code}')) {
            $payload[$smsKey] = (string)$defaultsSms[$smsKey];
        } elseif (mb_strlen($tpl) > 160) {
            $payload[$smsKey] = mb_substr($tpl, 0, 160);
        }
    }

    saveSiteSettings($payload);
    setFlash('success', 'Podešavanja su sačuvana.');
    header('Location: /admin_settings.php?tab=' . urlencode($tab));
    exit;
}

$pageTitle = 'Podešavanja sajta — KupiTelefon';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'settings';

require_once dirname(__DIR__) . '/config/mail.php';
$mailStatus = mailStatusSummary();
$adminEmail = trim((string)(currentUser()['email'] ?? ''));

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Podešavanja sajta</div>
        <h2 style="font-size:18px;margin-bottom:12px;">Podešavanja sajta</h2>

        <div class="admin-tabs">
            <a href="?tab=general" class="<?= $tab === 'general' ? 'active' : '' ?>">Opšte</a>
            <a href="?tab=homepage" class="<?= $tab === 'homepage' ? 'active' : '' ?>">Početna</a>
            <a href="?tab=lists" class="<?= $tab === 'lists' ? 'active' : '' ?>">Liste</a>
            <a href="?tab=features" class="<?= $tab === 'features' ? 'active' : '' ?>">Funkcije</a>
            <a href="?tab=email" class="<?= $tab === 'email' ? 'active' : '' ?>">Email šabloni</a>
            <a href="?tab=sms" class="<?= $tab === 'sms' ? 'active' : '' ?>">SMS</a>
            <a href="?tab=maintenance" class="<?= $tab === 'maintenance' ? 'active' : '' ?>">Održavanje</a>
        </div>

        <form method="POST" class="form-card admin-settings-form">
            <?= csrfField() ?>
            <div class="admin-tab-panel <?= $tab === 'general' ? 'active' : '' ?>">
                <h3>Opšte informacije</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Naziv sajta</label>
                        <input name="site_name" value="<?= h((string)$settings['site_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Slogan sajta</label>
                        <input name="topbar_text" value="<?= h((string)$settings['topbar_text']) ?>">
                        <p class="form-hint">Jedan tekst — top bar, footer i SEO. Npr. „Telefoni · tableti · satovi · servis · Srbija”.</p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Logo — prvi deo</label>
                        <input name="logo_telefon" value="<?= h((string)$settings['logo_telefon']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Logo — drugi deo</label>
                        <input name="logo_berza" value="<?= h((string)$settings['logo_berza']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Placeholder pretrage</label>
                    <input name="search_placeholder" value="<?= h((string)$settings['search_placeholder']) ?>">
                </div>
                <div class="form-group">
                    <label>Footer copyright</label>
                    <input name="footer_copyright" value="<?= h((string)$settings['footer_copyright']) ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kontakt email</label>
                        <input type="email" name="contact_email" value="<?= h((string)$settings['contact_email']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kontakt telefon</label>
                        <input name="contact_phone" value="<?= h((string)$settings['contact_phone']) ?>">
                    </div>
                </div>
            </div>

            <div class="admin-tab-panel <?= $tab === 'homepage' ? 'active' : '' ?>">
                <h3>Početna strana</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Oglasa po strani</label>
                        <input type="number" min="5" max="100" name="items_per_page" value="<?= (int)$settings['items_per_page'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Broj TOP oglasa</label>
                        <input type="number" min="1" max="10" name="max_promoted_ads" value="<?= (int)$settings['max_promoted_ads'] ?>">
                    </div>
                </div>
                <div class="form-group form-checks">
                    <input type="hidden" name="show_promoted_section" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="show_promoted_section" value="1" <?= !empty($settings['show_promoted_section']) ? 'checked' : '' ?>> Prikaži sekciju istaknutih oglasa</label>
                    <input type="hidden" name="show_ticker" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="show_ticker" value="1" <?= !empty($settings['show_ticker']) ? 'checked' : '' ?>> Prikaži traku „Traže se"</label>
                </div>
                <div class="form-group">
                    <label>Naslov trake</label>
                    <input name="ticker_label" value="<?= h((string)$settings['ticker_label']) ?>">
                </div>
                <div class="form-group">
                    <label>Stavke trake (jedna po liniji)</label>
                    <textarea name="ticker_items" rows="6"><?= h(implode("\n", $settings['ticker_items'])) ?></textarea>
                </div>
            </div>

            <div class="admin-tab-panel <?= $tab === 'lists' ? 'active' : '' ?>">
                <h3>Gradovi, brendovi i stanja</h3>
                <p class="form-hint">Sajt je namenjen isključivo Srbiji. Gradovi se koriste u filterima i formi za oglas.</p>
                <div class="form-group">
                    <label>Gradovi u Srbiji (jedan po liniji)</label>
                    <textarea name="cities" rows="12"><?= h(implode("\n", $settings['cities'])) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Brendovi (jedan po liniji)</label>
                    <textarea name="brands" rows="8"><?= h(implode("\n", $settings['brands'])) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Stanja proizvoda (jedan po liniji)</label>
                    <textarea name="conditions" rows="6"><?= h(implode("\n", $settings['conditions'])) ?></textarea>
                </div>
            </div>

            <div class="admin-tab-panel <?= $tab === 'features' ? 'active' : '' ?>">
                <h3>Funkcionalnosti</h3>
                <div class="form-group form-checks">
                    <input type="hidden" name="enable_registration" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_registration" value="1" <?= !empty($settings['enable_registration']) ? 'checked' : '' ?>> Dozvoli registraciju korisnika</label>
                    <input type="hidden" name="enable_messages" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_messages" value="1" <?= !empty($settings['enable_messages']) ? 'checked' : '' ?>> Poruke između korisnika</label>
                    <input type="hidden" name="enable_whatsapp" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_whatsapp" value="1" <?= !empty($settings['enable_whatsapp']) ? 'checked' : '' ?>> WhatsApp dugme na oglasu</label>
                    <input type="hidden" name="enable_favorites" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_favorites" value="1" <?= !empty($settings['enable_favorites']) ? 'checked' : '' ?>> Omiljeni oglasi</label>
                </div>

                <h3 style="margin-top:22px;">Rok trajanja oglasa</h3>
                <p class="form-hint">Oglas se automatski deaktivira posle isteka. Prodavač dobija upozorenje na profilu (i opciono email) nekoliko dana ranije.</p>
                <div class="form-group form-checks">
                    <input type="hidden" name="enable_ad_expiry" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_ad_expiry" value="1" <?= !empty($settings['enable_ad_expiry']) ? 'checked' : '' ?>> Uključi rok trajanja oglasa</label>
                    <input type="hidden" name="enable_expiry_email" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_expiry_email" value="1" <?= !empty($settings['enable_expiry_email']) ? 'checked' : '' ?>> Email za istek oglasa (legacy flag)</label>
                    <input type="hidden" name="enable_email_notifications" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_email_notifications" value="1" <?= !empty($settings['enable_email_notifications']) || (!isset($settings['enable_email_notifications']) && !empty($settings['enable_expiry_email'])) ? 'checked' : '' ?>> Email obaveštenja (poruke, alerti, istek…)</label>
                </div>

                <?php /* $mailStatus već učitan gore */ ?>
                <h3 style="margin-top:22px;">Email SMTP (Zoho)</h3>
                <p class="form-hint">
                    Lozinka ide samo u <code>.env</code> kao <code>ZOHO_SMTP_PASSWORD</code> (nikad u admin formu).
                    From: <strong><?= h($mailStatus['from_name']) ?></strong> &lt;<?= h($mailStatus['from_email']) ?>&gt;
                </p>
                <p class="form-hint" style="margin-top:6px;">
                    Status:
                    <?php if (!empty($mailStatus['ok'])): ?>
                        <strong style="color:var(--kp-green-dark,#2d7a3e);"><?= h($mailStatus['message']) ?></strong>
                    <?php else: ?>
                        <strong style="color:#b91c1c;"><?= h($mailStatus['message']) ?></strong>
                    <?php endif; ?>
                </p>
                <p class="form-hint">Test dugme je ispod (odvojen form) — klikni <strong>jednom</strong> i sačekaj poruku na vrhu stranice.</p>

                <div class="form-row">
                    <div class="form-group">
                        <label>Maksimalno dana aktivan</label>
                        <input type="number" min="1" max="365" name="ad_max_active_days" value="<?= (int)($settings['ad_max_active_days'] ?? 30) ?>">
                    </div>
                    <div class="form-group">
                        <label>Upozorenje N dana pre isteka</label>
                        <input type="number" min="1" max="30" name="ad_expiry_warning_days" value="<?= (int)($settings['ad_expiry_warning_days'] ?? 3) ?>">
                    </div>
                </div>

                <h3 style="margin-top:22px;">TOP isticanje (plaćeno kreditima)</h3>
                <div class="form-group form-checks">
                    <input type="hidden" name="enable_top_purchase" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_top_purchase" value="1" <?= !empty($settings['enable_top_purchase']) ? 'checked' : '' ?>> Dozvoli kupovinu TOP paketa sa naloga</label>
                    <input type="hidden" name="top_auto_activate" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="top_auto_activate" value="1" <?= !empty($settings['top_auto_activate']) ? 'checked' : '' ?>> Auto-aktivacija bez kredita (stari demo režim)</label>
                </div>
                <div class="form-group">
                    <label>Uputstvo (ako nema kredita)</label>
                    <textarea name="top_payment_info" rows="3"><?= h((string)($settings['top_payment_info'] ?? '')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>TOP paketi (ID / naziv / dani / cena u din = krediti)</label>
                    <?php foreach (topPackages() as $i => $pkg): ?>
                        <div class="form-row" style="margin-bottom:8px;">
                            <input name="top_pkg_id[]" value="<?= h((string)$pkg['id']) ?>" placeholder="id">
                            <input name="top_pkg_label[]" value="<?= h((string)$pkg['label']) ?>" placeholder="naziv">
                            <input type="number" min="1" name="top_pkg_days[]" value="<?= (int)$pkg['days'] ?>" placeholder="dani">
                            <input type="number" min="0" step="1" name="top_pkg_price[]" value="<?= h((string)$pkg['price']) ?>" placeholder="din">
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin-top:22px;">Krediti (novčanik)</h3>
                <p class="form-hint">Korisnik uplati npr. 1.000 din → admin potvrdi → dobija 1.000 kredita → njima plaća TOP.</p>
                <div class="form-group form-checks">
                    <input type="hidden" name="enable_credits" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="enable_credits" value="1" <?= !empty($settings['enable_credits']) ? 'checked' : '' ?>> Uključi sistem kredita</label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Oznaka valute</label>
                        <input name="credit_currency_label" value="<?= h((string)($settings['credit_currency_label'] ?? 'din')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kurs EUR → RSD (rezerva)</label>
                        <input type="number" step="0.0001" min="1" name="eur_rsd_rate" value="<?= h((string)($settings['eur_rsd_rate'] ?? 117)) ?>">
                    </div>
                </div>
                <div class="form-group form-checks">
                    <input type="hidden" name="eur_rsd_auto_nbs" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;">
                        <input type="checkbox" name="eur_rsd_auto_nbs" value="1" <?= !empty($settings['eur_rsd_auto_nbs']) ? 'checked' : '' ?>>
                        Automatski vuči srednji kurs NBS (EUR)
                    </label>
                </div>
                <?php
                $nbsCache = readNbsRateCache();
                $liveRate = eurRsdRate();
                ?>
                <p class="form-hint">
                    Trenutni kurs u upotrebi: <strong>1 € = <?= h(number_format($liveRate, 4, ',', '.')) ?> din</strong>
                    <?php if ($nbsCache): ?>
                        · NBS lista <?= h((string)$nbsCache['date']) ?>
                        · keš <?= h((string)$nbsCache['fetched_at']) ?>
                    <?php endif; ?>
                </p>
                <div class="form-group">
                    <button type="submit" name="refresh_nbs_rate" value="1" class="btn-sm">Osveži kurs sa NBS sada</button>
                </div>
                <div class="form-group">
                    <label>Iznosi dopune (jedan po liniji)</label>
                    <textarea name="credit_topup_amounts_text" rows="4"><?= h(implode("\n", creditTopupAmounts())) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Uputstvo za uplatu kredita</label>
                    <textarea name="credit_payment_info" rows="5"><?= h((string)($settings['credit_payment_info'] ?? '')) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cena Obnove (din)</label>
                        <input type="number" min="0" name="ad_renewal_credits" value="<?= (int)($settings['ad_renewal_credits'] ?? 200) ?>">
                    </div>
                    <div class="form-group">
                        <label>Cena plavog isticanja / 7 dana (din)</label>
                        <input type="number" min="0" name="highlight_credits" value="<?= (int)($settings['highlight_credits'] ?? 150) ?>">
                    </div>
                </div>

                <h3 style="margin-top:22px;">Mini web stranica radnje (plaćeno)</h3>
                <p class="form-hint">Dostupno samo verifikovanim firmama (PIB). Plaća se kreditima, na period koji odrediš.</p>
                <div class="form-group form-checks">
                    <input type="hidden" name="enable_shop_page_paid" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;">
                        <input type="checkbox" name="enable_shop_page_paid" value="1" <?= !empty($settings['enable_shop_page_paid']) ? 'checked' : '' ?>>
                        Uključi mini stranicu radnje
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cena paketa (krediti / din)</label>
                        <input type="number" min="1" name="shop_page_price_credits" value="<?= (int)($settings['shop_page_price_credits'] ?? 1200) ?>">
                    </div>
                    <div class="form-group">
                        <label>Trajanje paketa (dana)</label>
                        <input type="number" min="1" max="365" name="shop_page_duration_days" value="<?= (int)($settings['shop_page_duration_days'] ?? 30) ?>">
                    </div>
                </div>
            </div>

            <div class="admin-tab-panel <?= $tab === 'email' ? 'active' : '' ?>">
                <h3>Email šabloni</h3>
                <p class="form-hint" style="margin-bottom:16px;">
                    Tekstovi koje sistem šalje preko Zoho SMTP. Placeholders se zamenjuju automatski
                    (npr. <code>{code}</code>, <code>{name}</code>, <code>{title}</code>, <code>{body}</code>, <code>{link}</code>, <code>{site}</code>, <code>{url}</code>).
                    <code>{name_hello}</code> = „ Ime” ili prazno.
                </p>
                <?php foreach (emailTemplatesMerged() as $tplKey => $tpl): ?>
                    <div class="form-card" style="margin-bottom:14px;padding:14px;border:1px solid var(--border);border-radius:10px;">
                        <h4 style="font-size:15px;margin:0 0 10px;"><?= h((string)$tpl['label']) ?></h4>
                        <p class="form-hint" style="margin-bottom:10px;">Ključ: <code><?= h($tplKey) ?></code> · <?= h((string)$tpl['vars']) ?></p>
                        <div class="form-group">
                            <label>Naslov (subject)</label>
                            <input name="email_templates[<?= h($tplKey) ?>][subject]" value="<?= h((string)$tpl['subject']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Tekst poruke</label>
                            <textarea name="email_templates[<?= h($tplKey) ?>][body]" rows="7"><?= h((string)$tpl['body']) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="admin-tab-panel <?= $tab === 'sms' ? 'active' : '' ?>">
                <h3>SMS poruke (OTP)</h3>
                <?php
                $smsOn = smsEnabled();
                $smsUrl = trim((string)envValue('SMS_GATEWAY_URL', ''));
                $smsUser = trim((string)envValue('SMS_GATEWAY_USER', ''));
                $smsConfigured = $smsOn && $smsUrl !== '' && $smsUser !== '' && trim((string)envValue('SMS_GATEWAY_PASS', '')) !== '';
                ?>
                <p class="form-hint" style="margin-bottom:14px;">
                    Status gateway-a:
                    <?php if ($smsConfigured): ?>
                        <strong style="color:var(--kp-green-dark);">uključen</strong>
                        (<?= h($smsUser) ?> @ <?= h(parse_url($smsUrl, PHP_URL_HOST) ?: $smsUrl) ?>)
                    <?php elseif ($smsOn): ?>
                        <strong style="color:#b45309;">uključen, ali .env nije kompletan</strong>
                    <?php else: ?>
                        <strong style="color:#b91c1c;">isključen</strong> (`SMS_ENABLED` u `.env`)
                    <?php endif; ?>
                </p>
                <p class="form-hint" style="margin-bottom:14px;">
                    Kredencijali (URL, user, pass) menjaju se samo u <code>.env</code> na serveru — ne ovde.
                    SMS ide isključivo na srpske mobilne brojeve <code>+3816…</code>.
                    U tekstu obavezno koristi placeholder <code>{code}</code> (max 160 karaktera).
                </p>
                <div class="form-group">
                    <label>Šablon — verifikacija telefona</label>
                    <textarea name="sms_template_phone_verify" rows="2" maxlength="160"><?= h((string)($settings['sms_template_phone_verify'] ?? 'KupiTelefon kod: {code}. Vazi 10 min.')) ?></textarea>
                    <p class="form-hint" style="margin-top:6px;">Primer: <code>KupiTelefon kod: {code}. Vazi 10 min.</code></p>
                </div>
                <div class="form-group">
                    <label>Šablon — reset lozinke</label>
                    <textarea name="sms_template_password_reset" rows="2" maxlength="160"><?= h((string)($settings['sms_template_password_reset'] ?? 'KupiTelefon reset lozinke: {code}. Vazi 10 min.')) ?></textarea>
                    <p class="form-hint" style="margin-top:6px;">Primer: <code>KupiTelefon reset lozinke: {code}. Vazi 10 min.</code></p>
                </div>
            </div>

            <div class="admin-tab-panel <?= $tab === 'maintenance' ? 'active' : '' ?>">
                <h3>Režim održavanja</h3>
                <div class="form-group form-checks">
                    <input type="hidden" name="maintenance_mode" value="0">
                    <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="maintenance_mode" value="1" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>> Uključi režim održavanja</label>
                </div>
                <div class="form-group">
                    <label>Poruka posetiocima</label>
                    <textarea name="maintenance_message" rows="4"><?= h((string)$settings['maintenance_message']) ?></textarea>
                </div>
                <p class="form-hint">U režimu održavanja sajt je vidljiv samo administratorima i na stranicama za prijavu.</p>
            </div>

            <button class="btn-call" type="submit">Sačuvaj podešavanja</button>
        </form>

        <?php if ($tab === 'features'): ?>
        <form method="POST" action="/admin_settings.php?tab=features" class="form-card" style="margin-top:12px;" id="smtp-test-form" data-smtp-test>
            <?= csrfField() ?>
            <h3 style="font-size:16px;margin-bottom:10px;">Pošalji test email</h3>
            <div class="form-row" style="align-items:end;">
                <div class="form-group" style="flex:1;">
                    <label>Na adresu</label>
                    <input type="email" name="test_smtp_to" value="<?= h($adminEmail) ?>" placeholder="tvoj@email.com" required>
                </div>
                <div class="form-group" style="flex:0;">
                    <button type="submit" name="test_smtp_email" value="1" class="btn-message" id="smtp-test-btn">Pošalji test</button>
                </div>
            </div>
            <p class="form-hint">Maksimalno 1 test na 60 sekundi. Ne klikći više puta dok se stranica ne vrati.</p>
        </form>
        <script>
        (function () {
          var form = document.getElementById('smtp-test-form');
          var btn = document.getElementById('smtp-test-btn');
          if (!form || !btn) return;
          form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Šaljem…';
          });
        })();
        </script>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
