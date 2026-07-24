<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
requireLogin();

$cfg = categoriesConfig();
$userId = (int)currentUser()['id'];
$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $adId > 0;

$ad = [
    'title' => '',
    'description' => '',
    'ad_type' => 'telefon',
    'category_group' => 'phones',
    'brand' => 'Apple',
    'model' => '',
    'storage' => '',
    'price' => '',
    'condition_state' => 'Polovno',
    'location' => '',
    'contact_phone' => '',
    'shop_name' => '',
    'badge' => '',
    'images' => [],
    'is_active' => 1,
    'is_promoted' => 0,
    'is_sold' => 0,
];

$profile = findUserById($userId) ?? currentUser();

if ($isEdit) {
    $found = getAdById($adId);
    if (!$found || !userOwnsAd($found, $userId)) {
        setFlash('danger', 'Oglas nije pronađen ili nemate pristup.');
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }
    $ad = $found;
} else {
    $ad['contact_phone'] = trim((string)($profile['phone'] ?? ''));
    $ad['shop_name'] = trim((string)(($profile['shop_name'] ?? '') ?: getSellerShopName($profile)));
    $ad['location'] = trim((string)($profile['location'] ?? ''));
    if ($ad['location'] === '') {
        foreach (getAdsByUserId($userId) as $prev) {
            $prevLoc = trim((string)($prev['location'] ?? ''));
            if ($prevLoc !== '') {
                $ad['location'] = $prevLoc;
                break;
            }
        }
    }
}

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/ad_form.php' . ($isEdit ? '?id=' . $adId : ''));
    $adType = trim((string)($_POST['ad_type'] ?? 'telefon'));
    if (!in_array($adType, ['telefon', 'delovi', 'servis'], true)) {
        $adType = 'telefon';
    }

    $payload = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'ad_type' => $adType,
        'category_group' => trim((string)($_POST['category_group'] ?? '')),
        'brand' => trim((string)($_POST['brand'] ?? '')),
        'model' => trim((string)($_POST['model'] ?? '')),
        'storage' => trim((string)($_POST['storage'] ?? '')),
        'price' => (float)($_POST['price'] ?? 0),
        'condition_state' => trim((string)($_POST['condition_state'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'country' => 'Srbija',
        'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
        'shop_name' => trim((string)($_POST['shop_name'] ?? '')),
        'badge' => trim((string)($_POST['badge'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_sold' => isset($_POST['is_sold']) ? 1 : 0,
        'is_promoted' => (int)($ad['is_promoted'] ?? 0),
        'promoted_until' => $ad['promoted_until'] ?? null,
        'images' => $isEdit ? ($ad['images'] ?? []) : [],
    ];

    if (isAdmin() && isset($_POST['is_promoted'])) {
        $payload['is_promoted'] = 1;
    } elseif (isAdmin() && !isset($_POST['is_promoted'])) {
        $payload['is_promoted'] = 0;
        $payload['promoted_until'] = null;
    }

    if (!empty($payload['is_sold'])) {
        $payload['is_promoted'] = 0;
    }

    $ad = array_merge($ad, $payload);

    if ($payload['title'] === '' || $payload['location'] === '') {
        $formError = 'Naslov i grad su obavezni.';
    } elseif ($payload['price'] < 0) {
        $formError = 'Cena nije validna.';
    } else {
        if ($payload['contact_phone'] === '') {
            $payload['contact_phone'] = trim((string)($profile['phone'] ?? ''));
        }
        if ($payload['shop_name'] === '') {
            $payload['shop_name'] = trim((string)(($profile['shop_name'] ?? '') ?: getSellerShopName($profile)));
        }

        if ($isEdit) {
            saveAd($payload, $adId);
            setFlash('success', 'Oglas je uspešno izmenjen.');
            header('Location: /nalog.php?tab=oglasi');
            exit;
        }

        $newId = saveAd($payload);

        $profilePatch = [];
        if (trim((string)($profile['phone'] ?? '')) === '' && $payload['contact_phone'] !== '') {
            $profilePatch['phone'] = $payload['contact_phone'];
        }
        if (trim((string)($profile['shop_name'] ?? '')) === '' && $payload['shop_name'] !== '') {
            $profilePatch['shop_name'] = $payload['shop_name'];
        }
        if (trim((string)($profile['location'] ?? '')) === '' && $payload['location'] !== '') {
            $profilePatch['location'] = $payload['location'];
        }
        if ($profilePatch !== []) {
            updateUserProfile($userId, array_merge([
                'full_name' => (string)($profile['full_name'] ?? ''),
                'phone' => (string)($profile['phone'] ?? ''),
                'email' => (string)($profile['email'] ?? ''),
            ], $profilePatch));
        }

        $promoPackage = trim((string)($_POST['promo_package'] ?? 'standard'));
        $wantHighlight = isset($_POST['promo_highlight']);
        $promoNotes = [];

        if (topPurchaseEnabled() && $promoPackage !== '' && $promoPackage !== 'standard') {
            $order = createTopOrder($userId, $newId, $promoPackage);
            if ($order && ($order['status'] ?? '') === 'paid') {
                $promoNotes[] = 'TOP aktiviran (' . (int)$order['days'] . ' dana).';
            } elseif ($order === null && creditsEnabled()) {
                $promoNotes[] = 'TOP nije aktiviran — proveri saldo kredita.';
            }
        }

        if ($wantHighlight) {
            $hi = activateAdHighlight($newId, $userId, 7);
            if (!empty($hi['ok'])) {
                $promoNotes[] = 'Istaknut plavom bojom.';
            } elseif (($hi['error'] ?? '') === 'credits') {
                $promoNotes[] = 'Plavo isticanje nije aktivirano — nema dovoljno kredita.';
            }
        }

        $msg = 'Oglas je uspešno objavljen.';
        if ($promoNotes) {
            $msg .= ' ' . implode(' ', $promoNotes);
        }
        setFlash('success', $msg);
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }
}

$currentType = getAdType($ad);
$pageTitle = ($isEdit ? 'Izmena oglasa' : 'Postavi oglas') . ' — TelefonBerza';
$activePage = 'dodaj';
$showSearch = false;
$existingImages = is_array($ad['images'] ?? null) ? $ad['images'] : [];
$groupMeta = [];
foreach ($cfg['groups'] as $key => $group) {
    $groupMeta[$key] = (string)($group['ad_type'] ?? '');
}

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content ad-form-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <?= $isEdit ? 'Izmena oglasa' : 'Postavi oglas' ?></div>

        <form method="POST" enctype="multipart/form-data" class="form-card ad-form-card" data-ad-form>
            <?= csrfField() ?>
            <div class="ad-form-head">
                <h2><?= $isEdit ? 'Izmeni oglas' : 'Novi oglas' ?></h2>
                <p class="ad-form-sub">Popuni samo bitno — ostalo je već sa profila gde može.</p>
            </div>

            <?php if ($formError !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($formError) ?></p>
            <?php endif; ?>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">1. Tip oglasa</h3>
                <div class="form-type-select">
                    <label class="form-type-option <?= $currentType === 'telefon' ? 'selected' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="telefon" <?= $currentType === 'telefon' ? 'checked' : '' ?>>
                        <span><strong>Telefon</strong><small>Ceo uređaj</small></span>
                    </label>
                    <label class="form-type-option <?= $currentType === 'delovi' ? 'selected-parts' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="delovi" <?= $currentType === 'delovi' ? 'checked' : '' ?>>
                        <span><strong>Deo / oprema</strong><small>Ekran, baterija…</small></span>
                    </label>
                    <label class="form-type-option <?= $currentType === 'servis' ? 'selected-service' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="servis" <?= $currentType === 'servis' ? 'checked' : '' ?>>
                        <span><strong>Servis</strong><small>Popravka / usluga</small></span>
                    </label>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">2. Osnovni podaci</h3>
                <div class="form-group">
                    <label>Naslov</label>
                    <input name="title" id="ad-title" placeholder="npr. iPhone 13 Pro Max 256GB" value="<?= h((string)$ad['title']) ?>" required maxlength="120">
                    <p class="form-hint">Kratko i jasno — model prvi u naslovu.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cena (€)</label>
                        <input type="number" step="1" min="0" name="price" value="<?= h((string)$ad['price']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Grad</label>
                        <select name="location" required>
                            <option value="">Izaberi grad</option>
                            <?php foreach ($cfg['cities'] as $city): ?>
                                <option value="<?= h($city) ?>" <?= ($ad['location'] ?? '') === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Opis</label>
                    <textarea name="description" rows="4" placeholder="Stanje, šta ide uz oglas, garancija, napomene..."><?= h((string)$ad['description']) ?></textarea>
                </div>
            </section>

            <section class="ad-form-section" data-ad-details>
                <h3 class="ad-form-section-title">3. Detalji</h3>
                <div class="form-group" data-field="category">
                    <label>Kategorija</label>
                    <select name="category_group" id="ad-category" data-group-map="<?= h(json_encode($groupMeta, JSON_UNESCAPED_UNICODE)) ?>">
                        <?php foreach ($cfg['groups'] as $key => $group): ?>
                            <option value="<?= h($key) ?>" data-ad-type="<?= h((string)($group['ad_type'] ?? '')) ?>" <?= ($ad['category_group'] ?? '') === $key ? 'selected' : '' ?>><?= h($group['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group" data-field="brand">
                        <label>Brend</label>
                        <select name="brand">
                            <?php foreach ($cfg['brands'] as $brand): ?>
                                <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" data-field="model">
                        <label>Model</label>
                        <input name="model" id="ad-model" list="model-list" value="<?= h((string)$ad['model']) ?>" placeholder="npr. iPhone 13 Pro Max">
                        <datalist id="model-list">
                            <?php foreach ($cfg['groups'] as $group): ?>
                                <?php foreach ($group['models'] ?? [] as $m): ?>
                                    <option value="<?= h($m) ?>">
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" data-field="storage">
                        <label>Memorija</label>
                        <input name="storage" value="<?= h((string)$ad['storage']) ?>" placeholder="128GB">
                    </div>
                    <div class="form-group" data-field="condition">
                        <label>Stanje</label>
                        <select name="condition_state">
                            <?php foreach ($cfg['conditions'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($ad['condition_state'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">4. Fotografije</h3>
                <p class="form-hint" style="margin-top:0;">Prva slika je naslovna. Do 10 fotografija — kompresuju se automatski.</p>
                <?php if ($existingImages): ?>
                    <div class="photo-existing" data-photo-existing>
                        <?php foreach ($existingImages as $idx => $img): ?>
                            <div class="photo-existing-item" data-photo-item>
                                <img src="<?= h((string)$img) ?>" alt="">
                                <input type="hidden" name="image_order[]" value="<?= h((string)$img) ?>">
                                <label class="photo-keep"><input type="checkbox" name="keep_images[]" value="<?= h((string)$img) ?>" checked> Zadrži</label>
                                <label class="photo-cover"><input type="radio" name="cover_image" value="<?= h((string)$img) ?>" <?= $idx === 0 ? 'checked' : '' ?>> Naslovna</label>
                                <div class="photo-reorder">
                                    <button type="button" class="btn-sm" data-photo-up title="Gore">↑</button>
                                    <button type="button" class="btn-sm" data-photo-down title="Dole">↓</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <label class="ad-photo-add">
                    <input type="file" name="images[]" accept="image/*" multiple data-photo-input>
                    <span>+ Dodaj fotografije</span>
                </label>
                <div class="photo-upload" data-photo-preview></div>
            </section>

            <section class="ad-form-section">
                <button type="button" class="ad-form-more-toggle" data-ad-more-toggle aria-expanded="false">
                    Kontakt i dodatno ▾
                </button>
                <div class="ad-form-more" data-ad-more hidden>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Naziv prodavnice / izloga</label>
                            <input name="shop_name" value="<?= h((string)($ad['shop_name'] ?? '')) ?>" placeholder="Iz profila ako ostaviš prazno">
                        </div>
                        <div class="form-group">
                            <label>Kontakt telefon</label>
                            <input name="contact_phone" placeholder="06x xxx xxxx" value="<?= h((string)$ad['contact_phone']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Oznaka (opciono)</label>
                        <input name="badge" placeholder="npr. Garancija / Original" value="<?= h((string)($ad['badge'] ?? '')) ?>">
                    </div>
                    <div class="form-group form-checks">
                        <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="is_active" <?= (int)($ad['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Aktivan</label>
                        <?php if ($isEdit): ?>
                            <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="is_sold" <?= !empty($ad['is_sold']) ? 'checked' : '' ?>> Prodato</label>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                            <label class="type-chip" style="min-width:auto;flex:none;"><input type="checkbox" name="is_promoted" <?= !empty($ad['is_promoted']) ? 'checked' : '' ?>> TOP (admin)</label>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if (!$isEdit && topPurchaseEnabled()): ?>
                <?php
                $creditsOnForm = creditsEnabled();
                $bal = $creditsOnForm ? getUserCredits($userId) : 0;
                $pkgs = topPackages();
                ?>
                <section class="ad-form-section">
                    <h3 class="ad-form-section-title">5. Vidljivost (opciono)</h3>
                    <p class="form-hint" style="margin-top:0;">Možeš ostaviti besplatno.<?= $creditsOnForm ? ' Saldo: <strong>' . h(formatCredits($bal)) . '</strong>.' : '' ?></p>
                    <div class="promo-pick-list">
                        <label class="promo-pick-option">
                            <input type="radio" name="promo_package" value="standard" checked>
                            <span>
                                <strong>Standardno</strong>
                                <small>Besplatno</small>
                            </span>
                        </label>
                        <?php foreach ($pkgs as $pkg): ?>
                            <?php $cost = (int)$pkg['price']; $ok = !$creditsOnForm || $bal >= $cost; ?>
                            <label class="promo-pick-option <?= $ok ? '' : 'is-disabled' ?>">
                                <input type="radio" name="promo_package" value="<?= h((string)$pkg['id']) ?>" <?= $ok ? '' : 'disabled' ?>>
                                <span>
                                    <strong>TOP — <?= h((string)$pkg['label']) ?></strong>
                                    <small><?= $creditsOnForm ? formatCredits($cost) : formatPrice((float)$cost) ?><?= $ok ? '' : ' · nemaš kredita' ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (highlightCredits() > 0): ?>
                        <?php $hCost = highlightCredits(); $hOk = !$creditsOnForm || $bal >= $hCost; ?>
                        <label class="promo-addon <?= $hOk ? '' : 'is-disabled' ?>">
                            <input type="checkbox" name="promo_highlight" value="1" <?= $hOk ? '' : 'disabled' ?>>
                            <span><strong>Plavo isticanje</strong> (+<?= $creditsOnForm ? formatCredits($hCost) : formatPrice((float)$hCost) ?> / 7 dana)</span>
                        </label>
                    <?php endif; ?>
                </section>
            <?php elseif ($isEdit && topPurchaseEnabled()): ?>
                <p class="form-hint">Promocije: <a href="/nalog.php?tab=oglasi">Moji oglasi</a>.</p>
            <?php endif; ?>

            <div class="ad-form-submit">
                <button class="btn-call" type="submit"><?= $isEdit ? 'Sačuvaj izmene' : 'Objavi oglas' ?></button>
                <a href="/nalog.php?tab=oglasi" class="btn-message">Odustani</a>
            </div>
        </form>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
