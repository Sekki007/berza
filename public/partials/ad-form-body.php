<?php
/** @var array $ad */
/** @var array $schema */
/** @var array $cfg */
/** @var array $phoneBrands */
/** @var array $existingImages */
/** @var array $accessoriesSel */
/** @var array $serviceTypesSel */
/** @var array $supportedBrandsSel */
/** @var array $serviceExtrasSel */
/** @var string $currentType */
/** @var string $currentPriceType */
/** @var string $currentCurrency */
/** @var string $currentListing */
/** @var string $formError */
/** @var array $formErrors */
/** @var bool $isEdit */
/** @var bool $canPostService */
/** @var bool $allowServiceType */
/** @var bool $editingOwnService */
/** @var string $bizStatus */
/** @var string $titlePreview */

$formErrors = is_array($formErrors ?? null) ? $formErrors : [];
$titlePreview = trim((string)($titlePreview ?? $ad['title'] ?? ''));
$currentDeviceType = getAdDeviceType($ad) ?: 'phone';
$catalogModels = function_exists('phoneCatalogModels') ? phoneCatalogModels() : [];
$selectedCatalogId = trim((string)($ad['phone_model_id'] ?? ''));
$selectedCatalog = ($selectedCatalogId !== '' && function_exists('phoneCatalogModel')) ? phoneCatalogModel($selectedCatalogId) : null;
$catalogJson = [];
foreach ($catalogModels as $catalogModel) {
    $variants = [];
    foreach ($catalogModel['variants'] as $variant) {
        $variants[] = ['id' => $variant['id'], 'storage' => $variant['storage']];
    }
    $catalogJson[] = [
        'id' => $catalogModel['id'],
        'brand' => $catalogModel['brand'],
        'name' => $catalogModel['name'],
        'label' => trim($catalogModel['brand'] . ' ' . $catalogModel['name']),
        'variants' => $variants,
    ];
}
$partsKindChips = $schema['parts_kind_chips'] ?? [];
$currentPartsKind = '';
$eqNow = trim((string)($ad['equipment_type'] ?? ''));
$cgNow = trim((string)($ad['category_group'] ?? ''));
foreach ($partsKindChips as $kindKey => $kindMeta) {
    if ($cgNow === ($kindMeta['category_group'] ?? '') || $eqNow === ($kindMeta['equipment_type'] ?? '')) {
        $currentPartsKind = $kindKey;
        break;
    }
}
$manualPhoneOpen = $selectedCatalogId === '' && (trim((string)($ad['brand'] ?? '')) !== '' || trim((string)($ad['model'] ?? '')) !== '');
$phoneExtraOpen = trim((string)($ad['ram'] ?? '')) !== ''
    || trim((string)($ad['color'] ?? '')) !== ''
    || trim((string)($ad['sim_status'] ?? '')) !== ''
    || (isset($ad['battery_health']) && $ad['battery_health'] !== null && $ad['battery_health'] !== '')
    || !empty($ad['has_warranty'])
    || (is_array($ad['accessories'] ?? null) && $ad['accessories'] !== [])
    || $currentDeviceType !== 'phone'
    || trim((string)($ad['description'] ?? '')) !== '';
$isBuy = $currentListing === 'buy';
$photosRequired = $currentType === 'telefon' && !$isBuy;
$priceWarnEur = warnAdPriceEur();
$priceEurNow = ($currentPriceType === 'fixed' && (float)($ad['price'] ?? 0) > 0)
    ? amountToEur((float)$ad['price'], $currentCurrency)
    : 0.0;
$showPriceConfirm = $priceEurNow > $priceWarnEur && $priceEurNow <= maxAdPriceEur($currentType);
?>
<div class="main-wrap">
    <main class="content ad-form-page ad-form-page--v2">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <?= $isEdit ? 'Izmena oglasa' : 'Postavi oglas' ?></div>

        <form method="POST" enctype="multipart/form-data" class="form-card ad-form-card" data-ad-form data-is-edit="<?= $isEdit ? '1' : '0' ?>" data-draft-key="kt-ad-draft-v1">
            <?= csrfField() ?>
            <?php if (!$isEdit && $adSubmitToken !== ''): ?>
                <input type="hidden" name="ad_submit_token" value="<?= h($adSubmitToken) ?>">
            <?php endif; ?>
            <input type="hidden" name="device_type" value="<?= h($currentDeviceType) ?>" data-device-type-hidden>
            <input type="hidden" name="phone_model_id" value="<?= h($selectedCatalogId) ?>" data-phone-model-id>
            <input type="hidden" name="phone_variant_id" value="<?= h((string)($ad['phone_variant_id'] ?? '')) ?>" data-phone-variant-id>
            <input type="hidden" name="category_group" id="ad-category" value="<?= h((string)($ad['category_group'] ?? ($currentType === 'delovi' ? 'other_parts' : ($currentType === 'servis' ? 'service' : 'phones')))) ?>">
            <input type="hidden" name="equipment_type" value="<?= h($eqNow) ?>" data-equipment-type>
            <script type="application/json" id="phone-catalog-data"><?= json_encode($catalogJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

            <div class="ad-form-head">
                <h2><?= $isEdit ? 'Izmeni oglas' : 'Novi oglas' ?></h2>
                <p class="ad-form-sub"><?= $isEdit ? 'Kratka forma — samo bitno.' : 'Prvo izaberi kategoriju. Mobilno-prvo, bez suvišnog.' ?></p>
                <p class="ad-form-draft-status" data-draft-status hidden>Nacrt sačuvan</p>
            </div>

            <?php if ($formError !== ''): ?>
                <p class="form-hint ad-form-error" data-form-error><?= h($formError) ?></p>
            <?php endif; ?>

            <section class="ad-form-section ad-form-section--core">
                <h3 class="ad-form-section-title">Šta postavljaš?</h3>
                <div class="ad-type-tiles" role="radiogroup" aria-label="Kategorija">
                    <label class="ad-type-tile <?= $currentType === 'telefon' ? 'is-on' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="telefon" <?= $currentType === 'telefon' ? 'checked' : '' ?>>
                        <strong>Telefon</strong>
                        <small>Telefoni, tableti, satovi</small>
                    </label>
                    <label class="ad-type-tile <?= $currentType === 'delovi' ? 'is-on is-parts' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="delovi" <?= $currentType === 'delovi' ? 'checked' : '' ?>>
                        <strong>Oprema i delovi</strong>
                        <small>Maske, punjači, ekrani…</small>
                    </label>
                    <label class="ad-type-tile <?= $currentType === 'servis' ? 'is-on is-service' : '' ?><?= !$allowServiceType ? ' is-locked' : '' ?>">
                        <input data-form-type type="radio" name="ad_type" value="servis" <?= $currentType === 'servis' ? 'checked' : '' ?> <?= !$allowServiceType ? 'disabled' : '' ?>>
                        <strong>Servis</strong>
                        <small><?= $allowServiceType ? 'Popravke i usluge' : 'Samo verifikovani servisi' ?></small>
                    </label>
                </div>
                <?php if (!$canPostService): ?>
                    <p class="form-hint ad-form-service-note">
                        Servis objavljuju firme sa <strong>potvrđenim PIB-om</strong>.
                        <?php if ($editingOwnService): ?> Možeš izmeniti postojeći servis oglas.
                        <?php elseif ($bizStatus === 'pending'): ?> Zahtev čeka potvrdu admina.
                        <?php elseif ($bizStatus === 'rejected'): ?> Zahtev odbijen — <a href="/nalog.php?tab=profil">Nalog</a>.
                        <?php else: ?> <a href="/nalog.php?tab=profil">Registruj firmu</a>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>

            <section class="ad-form-section" data-listing-types>
                <h3 class="ad-form-section-title">Šta želiš?</h3>
                <div class="chip-grid chip-grid-intent">
                    <label class="chip-option <?= $currentListing === 'sell' ? 'is-on' : '' ?>" data-listing-opt="sell" data-for-types="telefon,delovi">
                        <input type="radio" name="listing_type" value="sell" data-listing-type <?= $currentListing === 'sell' ? 'checked' : '' ?>><span>Prodajem</span>
                    </label>
                    <label class="chip-option <?= $currentListing === 'buy' ? 'is-on' : '' ?>" data-listing-opt="buy" data-for-types="telefon,delovi">
                        <input type="radio" name="listing_type" value="buy" data-listing-type <?= $currentListing === 'buy' ? 'checked' : '' ?>><span>Tražim</span>
                    </label>
                    <label class="chip-option <?= $currentListing === 'trade' ? 'is-on' : '' ?>" data-listing-opt="trade" data-for-types="telefon">
                        <input type="radio" name="listing_type" value="trade" data-listing-type <?= $currentListing === 'trade' ? 'checked' : '' ?>><span>Menjam</span>
                    </label>
                    <label class="chip-option <?= $currentListing === 'service' ? 'is-on' : '' ?>" data-listing-opt="service" data-for-types="servis">
                        <input type="radio" name="listing_type" value="service" data-listing-type <?= $currentListing === 'service' ? 'checked' : '' ?>><span>Nudim uslugu</span>
                    </label>
                </div>
            </section>

            <?php if (!empty($shopCategoriesForForm)): ?>
                <section class="ad-form-section">
                    <div class="form-group">
                        <label for="shop-category-id">Kategorija u izlogu</label>
                        <select name="shop_category_id" id="shop-category-id">
                            <option value="">Bez kategorije</option>
                            <?php foreach ($shopCategoriesForForm as $sc): ?>
                                <option value="<?= h($sc['id']) ?>" <?= ($currentShopCategoryId ?? '') === $sc['id'] ? 'selected' : '' ?>><?= h($sc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </section>
            <?php endif; ?>

            <section class="ad-form-section" data-panel="telefon" <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>
                <div class="form-group">
                    <label for="phone-search">Koji telefon prodaješ?</label>
                    <input type="search" id="phone-search" data-phone-search autocomplete="off" placeholder="npr. iPhone 15, Galaxy S24…" value="<?= h($selectedCatalog ? ($selectedCatalog['brand'] . ' ' . $selectedCatalog['name']) : trim(($ad['brand'] ?? '') . ' ' . ($ad['model'] ?? ''))) ?>">
                    <div class="ad-typeahead-list" data-phone-suggestions hidden role="listbox"></div>
                    <div class="chip-grid" data-phone-variant-chips <?= $selectedCatalog && $selectedCatalog['variants'] !== [] ? '' : 'hidden' ?>>
                        <?php if ($selectedCatalog): foreach ($selectedCatalog['variants'] as $variant): ?>
                            <button type="button" class="chip-option <?= (string)($ad['phone_variant_id'] ?? '') === $variant['id'] || (string)($ad['storage'] ?? '') === $variant['storage'] ? 'is-on' : '' ?>" data-variant-chip data-variant-id="<?= h($variant['id']) ?>" data-storage="<?= h($variant['storage']) ?>"><?= h($variant['storage']) ?></button>
                        <?php endforeach; endif; ?>
                    </div>
                    <p class="form-hint"><button type="button" class="linkish" data-manual-phone-toggle>Ne vidim model – ručni unos</button></p>
                </div>
                <div class="ad-form-more" data-manual-phone <?= $manualPhoneOpen ? '' : 'hidden' ?>>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Brend</label>
                            <select name="brand" data-phone-brand data-title-source>
                                <option value="">—</option>
                                <?php foreach ($phoneBrands as $brand): ?>
                                    <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input name="model" id="ad-model" data-title-source value="<?= h((string)$ad['model']) ?>" placeholder="npr. iPhone 13 Pro Max" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Memorija</label>
                        <select name="storage" data-title-source data-storage-select>
                            <option value="">—</option>
                            <?php foreach ($schema['storage_options'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($ad['storage'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="ad-form-section" data-panel="delovi" <?= $currentType !== 'delovi' ? 'hidden' : '' ?>>
                <div class="form-group">
                    <label>Vrsta</label>
                    <div class="chip-grid">
                        <?php foreach ($partsKindChips as $kindKey => $kindMeta): ?>
                            <label class="chip-option <?= $currentPartsKind === $kindKey ? 'is-on' : '' ?>">
                                <input type="radio" name="parts_kind" value="<?= h($kindKey) ?>" data-parts-kind data-category-group="<?= h((string)$kindMeta['category_group']) ?>" data-equipment-value="<?= h((string)$kindMeta['equipment_type']) ?>" <?= $currentPartsKind === $kindKey ? 'checked' : '' ?>>
                                <span><?= h((string)$kindMeta['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="compat-search">Za koji model?</label>
                    <input type="search" id="compat-search" data-compat-search autocomplete="off" placeholder="Dodaj jedan ili više modela…">
                    <div class="ad-typeahead-list" data-compat-suggestions hidden></div>
                    <div class="chip-grid" data-compat-chips></div>
                    <input type="hidden" name="compatible_models" data-compat-value data-title-source value="<?= h((string)($ad['compatible_models'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Brend</label>
                    <select name="brand_parts" data-parts-brand data-title-source>
                        <option value="">—</option>
                        <?php foreach ($phoneBrands as $brand): ?>
                            <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Originalnost</label>
                    <div class="chip-grid">
                        <?php foreach (['Original' => 'Original', 'Kopija/A klasa' => 'Kopija', 'Ne znam' => 'Ne znam'] as $val => $label): ?>
                            <label class="chip-option <?= (string)($ad['originality'] ?? 'Ne znam') === $val ? 'is-on' : '' ?>">
                                <input type="radio" name="originality" value="<?= h($val) ?>" data-title-source <?= (string)($ad['originality'] ?? 'Ne znam') === $val ? 'checked' : '' ?>><span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Stanje</label>
                    <div class="chip-grid">
                        <?php foreach ($schema['parts_conditions'] as $st): ?>
                            <label class="chip-option <?= ($ad['condition_state'] ?? 'Novo') === $st ? 'is-on' : '' ?>">
                                <input type="radio" name="condition_state_parts" value="<?= h($st) ?>" data-cond-parts <?= ($ad['condition_state'] ?? 'Novo') === $st ? 'checked' : '' ?>><span><?= h($st) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="ad-form-section" data-panel="servis" <?= $currentType !== 'servis' ? 'hidden' : '' ?>>
                <div class="form-group"><label>Vrste usluga</label><?php renderChipGroup('service_types', $schema['service_types'], $serviceTypesSel); ?></div>
                <div class="form-group"><label>Brendovi</label><?php renderChipGroupList('supported_brands', $phoneBrands, $supportedBrandsSel); ?></div>
                <div class="form-group">
                    <div class="warranty-row">
                        <label class="check-inline"><input type="checkbox" name="has_work_warranty" value="1" data-work-warranty-toggle <?= !empty($ad['has_work_warranty']) ? 'checked' : '' ?>><span>Garancija na rad</span></label>
                        <div class="warranty-months" data-work-warranty-months <?= empty($ad['has_work_warranty']) ? 'hidden' : '' ?>>
                            <input type="number" name="work_warranty_months" min="1" max="60" value="<?= !empty($ad['work_warranty_months']) ? (int)$ad['work_warranty_months'] : '' ?>" placeholder="Meseci">
                        </div>
                    </div>
                </div>
                <div class="form-group"><label>Dodatno</label><?php renderChipGroup('service_extras', $schema['service_extras'], $serviceExtrasSel); ?></div>
            </section>

            <section class="ad-form-section" data-shared-media>
                <div class="form-group">
                    <label>Fotografije <span data-photo-required <?= !$photosRequired ? 'hidden' : '' ?>>*</span></label>
                    <?php if (!empty($formErrors['images'])): ?><p class="field-error"><?= h($formErrors['images']) ?></p><?php endif; ?>
                    <p class="form-hint" style="margin-top:0;">
                        <span data-phone-photo-hint <?= !$photosRequired ? 'hidden' : '' ?>>Za prodaju telefona obavezna je bar jedna. </span>
                        <span data-buy-photo-hint <?= $photosRequired ? 'hidden' : '' ?>>Za „Tražim“ nisu obavezne. </span>
                        Do 20 — prva je naslovna.
                    </p>
                    <div class="photo-existing" data-photo-existing>
                        <?php foreach ($existingImages as $idx => $img): ?>
                            <div class="photo-existing-item" data-photo-item>
                                <button type="button" class="photo-slot-remove" data-photo-remove aria-label="Ukloni">×</button>
                                <img src="<?= h((string)$img) ?>" alt="">
                                <input type="hidden" name="image_order[]" value="<?= h((string)$img) ?>">
                                <input type="hidden" name="keep_images[]" value="<?= h((string)$img) ?>">
                                <label class="photo-cover"><input type="radio" name="cover_image" value="<?= h((string)$img) ?>" <?= $idx === 0 ? 'checked' : '' ?>> Naslovna</label>
                                <div class="photo-reorder">
                                    <button type="button" class="btn-sm" data-photo-up>↑</button>
                                    <button type="button" class="btn-sm" data-photo-down>↓</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <label class="ad-photo-add" data-photo-drop>
                        <input type="file" name="images[]" accept="image/*,.heic,.heif" capture="environment" multiple data-photo-input <?= $photosRequired && $existingImages === [] ? 'required' : '' ?>>
                        <span>+ Dodaj fotografije<br><small>Kamera ili galerija</small></span>
                    </label>
                    <div class="photo-upload" data-photo-preview></div>
                </div>

                <div class="form-group" data-phone-condition-block <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>
                    <label>Stanje</label>
                    <div class="chip-grid">
                        <?php foreach ($schema['phone_conditions'] as $st): ?>
                            <label class="chip-option <?= ($ad['condition_state'] ?? 'Polovno') === $st ? 'is-on' : '' ?>">
                                <input type="radio" name="condition_state" value="<?= h($st) ?>" data-cond-phone data-title-source <?= ($ad['condition_state'] ?? 'Polovno') === $st ? 'checked' : '' ?>><span><?= h($st) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" data-title-price-block>
                    <label data-price-label><?= $isBuy ? 'Budžet do' : ($currentType === 'servis' ? 'Cena od' : 'Cena') ?></label>
                    <?php if (!empty($formErrors['price'])): ?><p class="field-error"><?= h($formErrors['price']) ?></p><?php endif; ?>
                    <div class="price-type-row" data-price-type-row>
                        <label class="price-type-option <?= $currentPriceType === 'fixed' ? 'is-on' : '' ?>">
                            <input type="radio" name="price_type" value="fixed" data-price-type <?= $currentPriceType === 'fixed' ? 'checked' : '' ?>><span data-price-fixed-label><?= $isBuy ? 'Budžet' : ($currentType === 'servis' ? 'Od' : 'Iznos') ?></span>
                        </label>
                        <label class="price-type-option <?= $currentPriceType !== 'fixed' ? 'is-on' : '' ?>">
                            <input type="radio" name="price_type" value="negotiable" data-price-type <?= $currentPriceType !== 'fixed' ? 'checked' : '' ?>><span>Po dogovoru</span>
                        </label>
                    </div>
                    <div class="price-amount-row" data-price-amount-row <?= $currentPriceType !== 'fixed' ? 'hidden' : '' ?>
                         data-eur-rsd-rate="<?= h((string)eurRsdRate()) ?>"
                         data-price-warn-eur="<?= h((string)$priceWarnEur) ?>"
                         data-price-max-telefon="<?= h((string)maxAdPriceEur('telefon')) ?>"
                         data-price-max-delovi="<?= h((string)maxAdPriceEur('delovi')) ?>"
                         data-price-max-servis="<?= h((string)maxAdPriceEur('servis')) ?>">
                        <input type="number" step="1" min="1" name="price" inputmode="numeric" data-price-input value="<?= $currentPriceType === 'fixed' ? h((string)$ad['price']) : '' ?>" placeholder="Iznos" <?= $currentPriceType === 'fixed' ? ($currentType === 'servis' ? '' : 'required') : 'disabled' ?>>
                        <div class="price-currency-toggle">
                            <label class="price-cur-option <?= $currentCurrency === 'eur' ? 'is-on' : '' ?>"><input type="radio" name="currency" value="eur" data-price-currency <?= $currentCurrency === 'eur' ? 'checked' : '' ?>><span>EUR</span></label>
                            <label class="price-cur-option <?= $currentCurrency === 'rsd' ? 'is-on' : '' ?>"><input type="radio" name="currency" value="rsd" data-price-currency <?= $currentCurrency === 'rsd' ? 'checked' : '' ?>><span>RSD</span></label>
                        </div>
                    </div>
                    <p class="form-hint price-convert-hint" data-price-convert hidden></p>
                    <p class="form-hint price-sanity-hint" data-price-sanity hidden></p>
                    <label class="price-confirm-label" data-price-confirm-wrap <?= $showPriceConfirm ? '' : 'hidden' ?>>
                        <input type="checkbox" name="price_confirmed" value="1" data-price-confirm <?= $showPriceConfirm && !empty($_POST['price_confirmed']) ? 'checked' : '' ?>>
                        <span>Potvrđujem da je cena tačna</span>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ad-location"><?= $currentType === 'servis' ? 'Adresa / grad *' : 'Grad *' ?></label>
                        <?php if (!empty($formErrors['location'])): ?><p class="field-error"><?= h($formErrors['location']) ?></p><?php endif; ?>
                        <input name="location" id="ad-location" list="city-suggestions" value="<?= h((string)$ad['location']) ?>" placeholder="npr. Beograd" required autocomplete="address-level2">
                    </div>
                    <div class="form-group">
                        <label>Telefon *</label>
                        <?php if (!empty($formErrors['contact_phone'])): ?><p class="field-error"><?= h($formErrors['contact_phone']) ?></p><?php endif; ?>
                        <input name="contact_phone" type="tel" inputmode="tel" placeholder="06x xxx xxxx" value="<?= h((string)$ad['contact_phone']) ?>" required autocomplete="tel">
                    </div>
                </div>

                <div data-panel-more="telefon" <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>
                    <button type="button" class="ad-form-more-toggle" data-phone-more-toggle aria-expanded="<?= $phoneExtraOpen ? 'true' : 'false' ?>"><?= $phoneExtraOpen ? 'Manje detalja ▴' : 'Više detalja ▾' ?></button>
                    <div class="ad-form-more" data-phone-more <?= $phoneExtraOpen ? '' : 'hidden' ?>>
                        <div class="form-group">
                            <label>Tip uređaja</label>
                            <select data-device-type-select>
                                <?php foreach ($schema['device_types'] as $dtKey => $dtLabel): ?>
                                    <option value="<?= h($dtKey) ?>" <?= $currentDeviceType === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>RAM</label>
                                <select name="ram"><option value="">—</option>
                                    <?php foreach ($schema['ram_options'] as $ram): ?><option value="<?= h($ram) ?>" <?= ($ad['ram'] ?? '') === $ram ? 'selected' : '' ?>><?= h($ram) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Boja</label>
                                <input name="color" data-title-source value="<?= h((string)($ad['color'] ?? '')) ?>" placeholder="npr. Graphite" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>SIM</label>
                                <select name="sim_status"><option value="">—</option>
                                    <?php foreach ($schema['sim_statuses'] as $sim): ?><option value="<?= h($sim) ?>" <?= ($ad['sim_status'] ?? '') === $sim ? 'selected' : '' ?>><?= h($sim) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" data-battery-field><label>Baterija %</label>
                                <input type="number" name="battery_health" min="0" max="100" step="1" inputmode="numeric" data-title-source value="<?= isset($ad['battery_health']) && $ad['battery_health'] !== null && $ad['battery_health'] !== '' ? h((string)$ad['battery_health']) : '' ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="warranty-row">
                                <label class="check-inline"><input type="checkbox" name="has_warranty" value="1" data-warranty-toggle data-title-source <?= !empty($ad['has_warranty']) ? 'checked' : '' ?>><span>Garancija</span></label>
                                <div class="warranty-months" data-warranty-months <?= empty($ad['has_warranty']) ? 'hidden' : '' ?>>
                                    <input type="number" name="warranty_months" min="1" max="60" inputmode="numeric" value="<?= !empty($ad['warranty_months']) ? (int)$ad['warranty_months'] : '' ?>" placeholder="Meseci">
                                </div>
                            </div>
                        </div>
                        <div class="form-group"><label>Oprema</label><?php renderChipGroup('accessories', $schema['phone_accessories'], $accessoriesSel); ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Opis</label>
                    <textarea name="description" rows="3" placeholder="Stanje, adresa, šta ide uz oglas…"><?= h((string)$ad['description']) ?></textarea>
                </div>
            </section>

            <datalist id="city-suggestions">
                <?php foreach ($cfg['cities'] as $city): ?><option value="<?= h($city) ?>"></option><?php endforeach; ?>
            </datalist>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">Naslov</h3>
                <p class="ad-title-preview" data-title-preview><?= h($titlePreview !== '' ? $titlePreview : 'Naslov će se napraviti automatski') ?></p>
                <p class="form-hint">Nije obavezan. Ako ostane prazan, server ga složi (npr. iPhone 15 128GB – Polovno).</p>
                <button type="button" class="btn-sm" data-title-edit-toggle>izmeni</button>
                <div class="form-group" data-title-edit <?= trim((string)($ad['title'] ?? '')) !== '' ? '' : 'hidden' ?> style="margin-top:8px;">
                    <input name="title" id="ad-title" data-ad-title maxlength="120" autocomplete="off" value="<?= h((string)$ad['title']) ?>" placeholder="Ručni naslov">
                    <button type="button" class="btn-sm" data-suggest-title>Osveži predlog</button>
                </div>
            </section>

            <div class="ad-form-submit ad-form-submit--desktop">
                <button class="btn-call" type="submit"><?= $isEdit ? 'Sačuvaj izmene' : 'Objavi oglas' ?></button>
                <?php if (!$isEdit): ?>
                    <button class="btn-message" type="submit" name="save_and_add_another" value="1">Objavi i dodaj još</button>
                <?php else: ?>
                    <a href="/nalog.php?tab=oglasi" class="btn-message">Odustani</a>
                <?php endif; ?>
            </div>
            <?php if (!$isEdit): ?>
                <p class="form-hint" style="margin-top:8px;text-align:center;">TOP isticanje nudi se posle objave.</p>
            <?php endif; ?>

            <div class="ad-form-sticky" data-ad-sticky>
                <button class="btn-call" type="submit"><?= $isEdit ? 'Sačuvaj' : 'Objavi oglas' ?></button>
                <?php if (!$isEdit): ?>
                    <button class="btn-message" type="submit" name="save_and_add_another" value="1">+ još</button>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>
