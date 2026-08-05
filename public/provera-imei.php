<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$error = '';
$errorDetail = '';
$checkedImei = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/provera-imei');
    $checkedImei = normalizeImei((string)($_POST['imei'] ?? ''));
    if (!isValidImei($checkedImei)) {
        $error = 'Unesi ispravan IMEI od 15 cifara.';
    } else {
        $check = checkImeiModel($checkedImei);
        if (!empty($check['ok']) && is_array($check['result'] ?? null)) {
            $result = $check['result'];
        } else {
            $error = (string)($check['error'] ?? 'Provera trenutno nije uspela. Pokušaj ponovo.');
            $errorDetail = (string)($check['detail'] ?? '');
        }
    }
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
                        required
                    >
                    <button class="btn-call imei-submit" type="submit">Proveri besplatno</button>
                </div>
                <p id="imei-help" class="form-hint">IMEI možeš pronaći pozivom na <strong>*#06#</strong> ili u Podešavanja → O telefonu.</p>
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
                $displayName = $name !== '' ? $name : trim($brand . ' ' . $model);
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
            <?php endif; ?>
        </section>

        <section class="imei-info-grid">
            <article class="form-card">
                <h2>Šta ova besplatna provera pokazuje?</h2>
                <ul>
                    <li>brend telefona</li>
                    <li>model i naziv uređaja</li>
                    <li>da li format IMEI broja prolazi matematičku proveru</li>
                </ul>
            </article>
            <article class="form-card">
                <h2>Važno pre kupovine</h2>
                <p>Uporedi rezultat sa modelom u oglasu, kutijom i podacima u telefonu. Ako se podaci ne poklapaju, traži objašnjenje pre plaćanja.</p>
                <a href="/vodic/provera-polovnog-iphone-a">Pročitaj vodič za proveru telefona →</a>
            </article>
        </section>

        <p class="imei-disclaimer">
            Besplatna provera prikazuje osnovne podatke o modelu. Ne proverava blacklist status, vlasništvo, iCloud/Activation Lock, SIM zaključavanje niti garantuje da telefon nije prijavljen kao izgubljen ili ukraden. Podatke obezbeđuje spoljni servis IMEICheck.com.
        </p>
    </main>
</div>
<?php require __DIR__ . '/partials/layout-end.php'; ?>
