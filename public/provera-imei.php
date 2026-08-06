<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$error = '';
$errorDetail = '';
$billingInfo = '';
$extendedError = '';
$checkedImei = '';
$selectedServiceKeys = [];
$result = null;
$extended = null;
$extendedCached = false;
$chargedCredits = 0;
$usedFree = false;
$services = imeiEnabledServices();
$allServices = imeiServiceCatalog();
$enabledServiceMap = [];
foreach ($services as $enabledService) {
    $enabledServiceMap[(string)$enabledService['key']] = true;
}
$serviceLabels = [];
foreach ($services as $service) {
    $serviceLabels[(string)$service['key']] = (string)$service['label'];
}
$extendedRemaining = isLoggedIn() ? chargeableImeiFreeChecksRemaining((int)(currentUser()['id'] ?? 0)) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/provera-imei');
    $checkedImei = normalizeImei((string)($_POST['imei'] ?? ''));
    $selectedServiceKeys = normalizeRequestedImeiServiceKeys((array)($_POST['services'] ?? []));
    if (!isValidImei($checkedImei)) {
        $error = 'Unesi ispravan IMEI od 15 cifara.';
    } else {
        $check = checkImeiModel($checkedImei);
        if (!empty($check['ok']) && is_array($check['result'] ?? null)) {
            $result = $check['result'];
            if ($selectedServiceKeys !== []) {
                $brand = trim((string)($result['brand'] ?? ''));
                $name = trim((string)($result['name'] ?? ''));
                $ext = checkImeiExtended($checkedImei, $selectedServiceKeys, $brand, $name);
                if (!empty($ext['ok']) && is_array($ext['extended'] ?? null)) {
                    $extended = $ext['extended'];
                    $extendedCached = !empty($ext['cached']);
                    $chargedCredits = (int)($ext['charged_credits'] ?? 0);
                    $usedFree = !empty($ext['used_free']);
                    if (!$extendedCached && $usedFree) {
                        $billingInfo = 'Provera je uračunata u besplatni dnevni limit.';
                    } elseif (!$extendedCached && $chargedCredits > 0) {
                        $billingInfo = 'Naplaćeno: ' . $chargedCredits . ' kredita.';
                    }
                } else {
                    $extendedError = (string)($ext['error'] ?? 'Proširena provera trenutno nije uspela.');
                    $errorDetail = (string)($ext['detail'] ?? '');
                }
            }
        } else {
            $error = (string)($check['error'] ?? 'Provera trenutno nije uspela. Pokušaj ponovo.');
            $errorDetail = (string)($check['detail'] ?? '');
        }
    }
    $extendedRemaining = isLoggedIn() ? chargeableImeiFreeChecksRemaining((int)(currentUser()['id'] ?? 0)) : 0;
}

$pageTitle = 'Besplatna IMEI provera telefona — KupiTelefon';
$pageDescription = 'Besplatno proveri brend i model telefona pomoću IMEI broja pre kupovine polovnog uređaja.';
$canonicalUrl = absoluteUrl('/provera-imei');
$activePage = 'imei';
$showSearch = false;
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'KupiTelefon IMEI provera',
    'url' => $canonicalUrl,
    'applicationCategory' => 'UtilitiesApplication',
    'operatingSystem' => 'Web',
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'RSD',
    ],
];

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap imei-page">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Provera IMEI-ja</div>

        <section class="imei-hero">
            <div class="imei-hero-icon" aria-hidden="true">#</div>
            <div>
                <span class="imei-kicker">Besplatna provera</span>
                <h1>Proveri telefon pomoću IMEI broja</h1>
                <p>Pre kupovine proveri da li IMEI odgovara brendu i modelu uređaja koji ti prodavac nudi.</p>
            </div>
        </section>

        <section class="form-card imei-check-card">
            <form method="POST" action="/provera-imei" autocomplete="off">
                <?= csrfField() ?>
                <label class="imei-label" for="imei">IMEI broj telefona</label>
                <div class="imei-input-row">
                    <input
                        id="imei"
                        class="imei-input"
                        type="text"
                        name="imei"
                        inputmode="numeric"
                        pattern="[0-9 ]{15,20}"
                        minlength="15"
                        maxlength="20"
                        placeholder="Unesi 15 cifara"
                        aria-describedby="imei-help"
                        value="<?= h($checkedImei) ?>"
                        required
                    >
                    <button class="btn-call imei-submit" type="submit">Proveri besplatno</button>
                </div>
                <p id="imei-help" class="form-hint">IMEI možeš pronaći pozivom na <strong>*#06#</strong> ili u Podešavanja → O telefonu.</p>
                <div class="imei-services-public">
                    <label class="imei-label" for="imei-service-preview">Lista svih servisa i cena</label>
                    <select id="imei-service-preview" class="imei-service-preview" aria-label="Lista servisa i cena">
                        <?php foreach ($allServices as $service): ?>
                            <?php
                            $serviceKey = (string)$service['key'];
                            $serviceId = (string)$service['service_id'];
                            $serviceName = (string)$service['label'];
                            $price = (int)$service['price'];
                            $appleOnly = !empty($service['apple_only']);
                            $enabled = !empty($enabledServiceMap[$serviceKey]);
                            ?>
                            <option>
                                ID <?= h($serviceId) ?> — <?= h($serviceName) ?> — <?= $price ?> kredita<?= $appleOnly ? ' — samo Apple' : '' ?><?= $enabled ? '' : ' — trenutno isključen' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="imei-credit-note">Napomena: primer obračuna je <strong>1 kredit = 100 din</strong>. Tačna naplata zavisi od cenovnika koji admin podesi.</p>
                </div>

                <?php if (!isLoggedIn()): ?>
                    <p class="imei-extended-login">
                        <a href="/login.php?redirect=<?= rawurlencode('/provera-imei') ?>">Prijavi se</a> da koristiš proširene servise iz liste i naplatu preko kredita.
                    </p>
                <?php else: ?>
                    <p class="imei-extended-login">
                        Za proširenu proveru izaberi servis iz liste iznad i pokreni proveru.
                        Besplatno dnevno: <?= (int)$extendedRemaining ?> / <?= imeiExtendedDailyLimit() ?>.
                    </p>
                <?php endif; ?>
            </form>

            <?php if ($error !== ''): ?>
                <div class="imei-message imei-message--error" role="alert">
                    <strong>Provera nije uspela</strong>
                    <span><?= h($error) ?></span>
                    <?php if ($errorDetail !== '' && isAdmin()): ?>
                        <span class="imei-admin-detail">Detalj (vidi samo admin): <?= h($errorDetail) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($result)): ?>
                <?php
                $brand = trim((string)($result['brand'] ?? ''));
                $model = trim((string)($result['model'] ?? ''));
                $name = trim((string)($result['name'] ?? ''));
                ?>
                <div class="imei-result" aria-live="polite">
                    <div class="imei-result-head">
                        <span class="imei-result-check" aria-hidden="true">✓</span>
                        <div>
                            <strong>Uređaj je pronađen</strong>
                            <span><?= h(maskedImei($checkedImei)) ?></span>
                        </div>
                    </div>
                    <dl class="imei-result-grid">
                        <div>
                            <dt>Brend</dt>
                            <dd><?= h($brand !== '' ? $brand : 'Nije navedeno') ?></dd>
                        </div>
                        <div>
                            <dt>Model</dt>
                            <dd><?= h($model !== '' ? $model : ($name !== '' ? $name : 'Nije navedeno')) ?></dd>
                        </div>
                        <?php if ($name !== '' && $name !== $model): ?>
                            <div>
                                <dt>Naziv uređaja</dt>
                                <dd><?= h($name) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($brand !== ''): ?>
                        <a class="btn-sm btn-sm-primary" href="/index.php?brand=<?= rawurlencode($brand) ?>">Pogledaj <?= h($brand) ?> oglase</a>
                    <?php endif; ?>
                </div>

                <?php if ($extendedError !== ''): ?>
                    <div class="imei-message imei-message--error" role="alert">
                        <strong>Proširena provera nije uspela</strong>
                        <span><?= h($extendedError) ?></span>
                        <?php if ($errorDetail !== '' && isAdmin()): ?>
                            <span class="imei-admin-detail">Detalj (vidi samo admin): <?= h($errorDetail) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (is_array($extended)): ?>
                    <div class="imei-extended-result" aria-live="polite">
                        <div class="imei-extended-head">
                            <strong>Proširena provera</strong>
                            <?php if ($extendedCached): ?>
                                <span class="imei-extended-note">Iz keša (ne troši dnevni limit)</span>
                            <?php elseif ($billingInfo !== ''): ?>
                                <span class="imei-extended-note"><?= h($billingInfo) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="imei-extended-grid">
                            <?php
                            $serviceResults = is_array($extended['services'] ?? null) ? $extended['services'] : [];
                            foreach ($serviceResults as $serviceKey => $serviceData):
                                $level = (string)($serviceData['level'] ?? 'unknown');
                                $label = (string)($serviceData['label'] ?? 'Nepoznato');
                                $detail = (string)($serviceData['detail'] ?? '');
                            ?>
                                <article class="imei-extended-item">
                                    <h3><?= h((string)($serviceLabels[(string)$serviceKey] ?? $serviceKey)) ?></h3>
                                    <span class="imei-badge imei-badge--<?= h($level) ?>"><?= h($label) ?></span>
                                    <?php if ($detail !== ''): ?>
                                        <p><?= h($detail) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="imei-info-grid">
            <article class="form-card">
                <h2>Šta besplatna provera pokazuje?</h2>
                <ul>
                    <li>brend telefona</li>
                    <li>model i naziv uređaja</li>
                    <li>da li format IMEI broja prolazi matematičku proveru</li>
                </ul>
            </article>
            <article class="form-card">
                <h2>Proširena provera (prijavljeni korisnici)</h2>
                <ul>
                    <li>sam biraš servis(e) iz liste</li>
                    <li>cenu i dostupnost servisa podešava admin</li>
                    <li>posle besplatnog limita troši kredite po izabranoj proveri</li>
                </ul>
                <?php if (!isLoggedIn()): ?>
                    <a href="/login.php?redirect=<?= rawurlencode('/provera-imei') ?>">Prijavi se za proširenu proveru →</a>
                <?php endif; ?>
            </article>
        </section>

        <p class="imei-disclaimer">
            Osnovna provera prikazuje brend i model. Proširena provera koristi spoljne servise i ne garantuje 100% tačnost niti pravnu validnost. Uvek uporedi rezultat sa stanjem telefona i traži objašnjenje ako se podaci ne poklapaju.
        </p>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
