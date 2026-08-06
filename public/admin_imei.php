<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$settings = siteSettings();
$services = imeiServiceCatalog();
$providerStatus = imeiProviderStatus();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin_imei.php');
    $current = siteSettings();
    $action = trim((string)($_POST['action'] ?? 'save'));

    if ($action === 'load_all') {
        $current['imei_services'] = defaultImeiServices();
        saveSiteSettings($current);
        setFlash('success', 'Učitana je puna lista servisa i svi su uključeni.');
        header('Location: /admin_imei.php');
        exit;
    }

    $keys = $_POST['service_key'] ?? [];
    $ids = $_POST['service_id'] ?? [];
    $labels = $_POST['service_label'] ?? [];
    $prices = $_POST['service_price'] ?? [];
    $enabledKeys = is_array($_POST['service_enabled'] ?? null) ? $_POST['service_enabled'] : [];
    $appleOnlyKeys = is_array($_POST['service_apple_only'] ?? null) ? $_POST['service_apple_only'] : [];

    $newServices = [];
    if (is_array($keys)) {
        foreach ($keys as $i => $keyRaw) {
            $key = trim((string)$keyRaw);
            $serviceId = preg_replace('/\D+/', '', (string)($ids[$i] ?? '')) ?? '';
            if ($key === '' || $serviceId === '') {
                continue;
            }
            $newServices[] = [
                'key' => $key,
                'service_id' => $serviceId,
                'label' => trim((string)($labels[$i] ?? $key)),
                'price' => max(0, (int)($prices[$i] ?? 0)),
                'enabled' => in_array($key, $enabledKeys, true),
                'apple_only' => in_array($key, $appleOnlyKeys, true),
            ];
        }
    }

    $extraIds = is_array($_POST['new_service_id'] ?? null) ? $_POST['new_service_id'] : [];
    $extraLabels = is_array($_POST['new_service_label'] ?? null) ? $_POST['new_service_label'] : [];
    $extraPrices = is_array($_POST['new_service_price'] ?? null) ? $_POST['new_service_price'] : [];
    $extraEnabled = is_array($_POST['new_service_enabled'] ?? null) ? $_POST['new_service_enabled'] : [];
    $extraApple = is_array($_POST['new_service_apple_only'] ?? null) ? $_POST['new_service_apple_only'] : [];
    foreach ($extraIds as $i => $rawId) {
        $serviceId = preg_replace('/\D+/', '', (string)$rawId) ?? '';
        $label = trim((string)($extraLabels[$i] ?? ''));
        if ($serviceId === '' || $label === '') {
            continue;
        }
        $key = 'svc_' . $serviceId;
        $suffix = 2;
        $existingKeys = array_column($newServices, 'key');
        while (in_array($key, $existingKeys, true)) {
            $key = 'svc_' . $serviceId . '_' . $suffix;
            $suffix++;
        }
        $newServices[] = [
            'key' => $key,
            'service_id' => $serviceId,
            'label' => $label,
            'price' => max(0, (int)($extraPrices[$i] ?? 0)),
            'enabled' => in_array((string)$i, $extraEnabled, true),
            'apple_only' => in_array((string)$i, $extraApple, true),
        ];
    }

    if ($newServices === []) {
        setFlash('danger', 'Mora ostati bar jedan IMEI servis.');
        header('Location: /admin_imei.php');
        exit;
    }

    $current['imei_free_checks_per_day'] = max(0, (int)($_POST['imei_free_checks_per_day'] ?? 0));
    $current['imei_services'] = $newServices;
    saveSiteSettings($current);
    setFlash('success', 'IMEI podešavanja su sačuvana.');
    header('Location: /admin_imei.php');
    exit;
}

$pageTitle = 'IMEI podešavanja — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'imei';

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › IMEI</div>
        <h2 style="font-size:18px;margin-bottom:12px;">IMEI servisi i cene</h2>
        <p class="form-hint">Ovde uključuješ/isključuješ servise i cenu po proveri. Korisnik na stranici bira koje servise želi da plati.</p>
        <p class="form-hint" style="margin-top:6px;">
            Status providera:
            <strong style="color:<?= $providerStatus['online'] ? 'var(--kp-green, #1a7f4b)' : '#c0392b' ?>">
                <?= $providerStatus['online'] ? 'ONLINE' : 'OFFLINE' ?>
            </strong>
            — <?= h($providerStatus['detail']) ?>
        </p>

        <form method="POST" style="margin:12px 0;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="load_all">
            <button class="btn-sm btn-sm-primary" type="submit">Učitaj i uključi sve servise</button>
        </form>

        <form method="POST" class="form-card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">

            <div class="form-group">
                <label>Besplatnih proširenih provera dnevno (po korisniku)</label>
                <input type="number" min="0" name="imei_free_checks_per_day" value="<?= (int)($settings['imei_free_checks_per_day'] ?? 0) ?>">
                <p class="form-hint">Stavi <strong>0</strong> kada budeš prebacio sistem skroz na kredite.</p>
            </div>

            <h3 style="margin-top:18px;">Dostupni servisi</h3>
            <p class="form-hint">Online/Offline je status API providera. Uključen/Isključen je tvoja kontrola po servisu.</p>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naziv</th>
                            <th>Cena</th>
                            <th>Apple</th>
                            <th>Uključen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php $key = (string)$service['key']; ?>
                            <tr>
                                <td style="max-width:90px;">
                                    <input type="hidden" name="service_key[]" value="<?= h($key) ?>">
                                    <input name="service_id[]" value="<?= h((string)$service['service_id']) ?>" required>
                                </td>
                                <td>
                                    <input name="service_label[]" value="<?= h((string)$service['label']) ?>" required>
                                </td>
                                <td style="max-width:110px;">
                                    <input type="number" min="0" name="service_price[]" value="<?= (int)$service['price'] ?>" required>
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="service_apple_only[]" value="<?= h($key) ?>" <?= !empty($service['apple_only']) ? 'checked' : '' ?>>
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="service_enabled[]" value="<?= h($key) ?>" <?= !empty($service['enabled']) ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <span class="imei-badge imei-badge--<?= $providerStatus['online'] ? 'good' : 'bad' ?>">
                                        <?= $providerStatus['online'] ? 'online' : 'offline' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top:16px;">Dodaj novi servis</h3>
            <p class="form-hint">Upiši nove servise iz PHP LIST tabele (ID, naziv, cena) i sačuvaj.</p>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="form-row" style="align-items:end; margin-bottom:8px;">
                    <div class="form-group" style="max-width:110px;">
                        <label>ID</label>
                        <input name="new_service_id[]" inputmode="numeric" pattern="[0-9]*">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Naziv</label>
                        <input name="new_service_label[]">
                    </div>
                    <div class="form-group" style="max-width:120px;">
                        <label>Cena (krediti)</label>
                        <input type="number" min="0" name="new_service_price[]" value="1">
                    </div>
                    <label class="type-chip" style="min-width:auto;flex:none;">
                        <input type="checkbox" name="new_service_apple_only[]" value="<?= (string)$i ?>"> Samo Apple
                    </label>
                    <label class="type-chip" style="min-width:auto;flex:none;">
                        <input type="checkbox" name="new_service_enabled[]" value="<?= (string)$i ?>" checked> Uključen
                    </label>
                </div>
            <?php endfor; ?>

            <button class="btn-call" type="submit">Sačuvaj IMEI podešavanja</button>
        </form>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>

