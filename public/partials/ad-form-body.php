<?php
/** @var array $ad */
/** @var array $schema */
/** @var array $cfg */
/** @var array $groupMeta */
/** @var array $phoneBrands */
/** @var array $existingImages */
/** @var array $accessoriesSel */
/** @var array $serviceTypesSel */
/** @var array $supportedBrandsSel */
/** @var array $serviceExtrasSel */
/** @var array $contactSel */
/** @var array $pickupSel */
/** @var string $currentType */
/** @var string $currentPriceType */
/** @var string $currentCurrency */
/** @var string $currentListing */
/** @var string $formError */
/** @var bool $isEdit */
/** @var bool $canPostService */
/** @var bool $allowServiceType */
/** @var bool $editingOwnService */
/** @var string $bizStatus */

$defaultContact = ['call', 'message'];
$defaultPickup = ['pickup'];
$contactSorted = $contactSel;
$pickupSorted = $pickupSel;
sort($contactSorted);
$dc = $defaultContact;
sort($dc);
sort($pickupSorted);
$dp = $defaultPickup;
sort($dp);

$contactExtraOpen = $contactSorted !== $dc
    || $pickupSorted !== $dp
    || trim((string)($ad['shop_name'] ?? '')) !== ''
    || trim((string)($ad['badge'] ?? '')) !== ''
    || (int)($ad['is_active'] ?? 1) !== 1
    || !empty($ad['is_sold'])
    || (isAdmin() && !empty($ad['is_promoted']));
?>
<div class="main-wrap">
    <main class="content ad-form-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <?= $isEdit ? 'Izmena oglasa' : 'Postavi oglas' ?></div>

        <form method="POST" enctype="multipart/form-data" class="form-card ad-form-card" data-ad-form>
            <?= csrfField() ?>
            <div class="ad-form-head">
                <h2><?= $isEdit ? 'Izmeni oglas' : 'Novi oglas' ?></h2>
                <p class="ad-form-sub">Popuni osnovno — ostalo nije obavezno.</p>
            </div>

            <?php if ($formError !== ''): ?>
                <p class="form-hint ad-form-error" data-form-error><?= h($formError) ?></p>
            <?php endif; ?>

            <section class="ad-form-section ad-form-section--core">
                <div class="form-group ad-form-cat-group">
                    <label>1. Kategorija</label>
                    <div class="form-type-select">
                        <label class="form-type-option <?= $currentType === 'telefon' ? 'selected' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="telefon" <?= $currentType === 'telefon' ? 'checked' : '' ?>>
                            <span>
                                <strong>Telefoni</strong>
                                <small>Telefon, tablet, sat…</small>
                            </span>
                        </label>
                        <label class="form-type-option <?= $currentType === 'delovi' ? 'selected-parts' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="delovi" <?= $currentType === 'delovi' ? 'checked' : '' ?>>
                            <span>
                                <strong>Delovi / oprema</strong>
                                <small>Ekrani, maske, punjači…</small>
                            </span>
                        </label>
                        <label class="form-type-option <?= $currentType === 'servis' ? 'selected-service' : '' ?><?= !$allowServiceType ? ' is-locked' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="servis" <?= $currentType === 'servis' ? 'checked' : '' ?> <?= !$allowServiceType ? 'disabled' : '' ?>>
                            <span>
                                <strong>Servis</strong>
                                <small><?= $allowServiceType ? 'Popravka' : 'Samo firme' ?></small>
                            </span>
                        </label>
                    </div>
                    <?php if (!$canPostService): ?>
                        <p class="form-hint ad-form-service-note">
                            Servis objavljuju firme sa <strong>potvrđenim PIB-om</strong>.
                            <?php if ($editingOwnService): ?>
                                Možeš izmeniti postojeći servis oglas.
                            <?php elseif ($bizStatus === 'pending'): ?>
                                Zahtev čeka potvrdu admina.
                            <?php elseif ($bizStatus === 'rejected'): ?>
                                Zahtev odbijen — <a href="/nalog.php?tab=profil">Nalog</a>.
                            <?php else: ?>
                                <a href="/nalog.php?tab=profil">Registruj firmu</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-group" data-category-wrap <?= $currentType === 'delovi' ? '' : 'hidden' ?>>
                    <label for="ad-category">2. Podkategorija</label>
                    <select name="category_group" id="ad-category" data-keep-enabled="1" data-group-map="<?= h(json_encode($groupMeta, JSON_UNESCAPED_UNICODE)) ?>">
                        <?php foreach ($cfg['groups'] as $key => $group): ?>
                            <?php
                            $gType = (string)($group['ad_type'] ?? '');
                            $gBrand = (string)($group['brand'] ?? '');
                            $gEquip = (string)($group['equipment_type'] ?? '');
                            ?>
                            <option
                                value="<?= h($key) ?>"
                                data-ad-type="<?= h($gType) ?>"
                                data-brand="<?= h($gBrand) ?>"
                                data-equipment-type="<?= h($gEquip) ?>"
                                <?= ($ad['category_group'] ?? '') === $key ? 'selected' : '' ?>
                            ><?= h($group['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Biraš tip opreme / delova — brend se popunjava automatski kad može.</p>
                </div>

                <div class="form-group" data-listing-types>
                    <label>3. Šta želiš?</label>
                    <div class="chip-grid chip-grid-4">
                        <?php foreach ($schema['listing_types'] as $ltKey => $ltLabel): ?>
                            <?php $isServiceOnly = $ltKey === 'service'; ?>
                            <label class="chip-option <?= $currentListing === $ltKey ? 'is-on' : '' ?>"
                                   data-listing-opt="<?= h($ltKey) ?>"
                                   data-for-types="<?= $isServiceOnly ? 'servis' : 'telefon,delovi' ?>">
                                <input type="radio" name="listing_type" value="<?= h($ltKey) ?>" data-listing-type <?= $currentListing === $ltKey ? 'checked' : '' ?>>
                                <span><?= h($ltLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ad-title">Naslov *</label>
                    <input name="title" id="ad-title" placeholder="npr. iPhone 13 Pro Max 256GB" value="<?= h((string)$ad['title']) ?>" required maxlength="120" autocomplete="off">
                </div>

                <?php if (!empty($shopCategoriesForForm)): ?>
                    <div class="form-group">
                        <label for="shop-category-id">Kategorija u izlogu</label>
                        <select name="shop_category_id" id="shop-category-id">
                            <option value="">Bez kategorije</option>
                            <?php foreach ($shopCategoriesForForm as $sc): ?>
                                <option value="<?= h($sc['id']) ?>" <?= ($currentShopCategoryId ?? '') === $sc['id'] ? 'selected' : '' ?>><?= h($sc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Nije obavezno — za katalog na tvom izlogu. Upravljaj kategorijama u <a href="/nalog.php?tab=profil#shop-categories">Nalogu</a>.</p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Cena</label>
                    <div class="price-type-row" data-price-type-row>
                        <label class="price-type-option <?= $currentPriceType === 'fixed' ? 'is-on' : '' ?>">
                            <input type="radio" name="price_type" value="fixed" data-price-type <?= $currentPriceType === 'fixed' ? 'checked' : '' ?>>
                            <span>Fiksno</span>
                        </label>
                        <label class="price-type-option <?= $currentPriceType !== 'fixed' ? 'is-on' : '' ?>">
                            <input type="radio" name="price_type" value="negotiable" data-price-type <?= $currentPriceType !== 'fixed' ? 'checked' : '' ?>>
                            <span>Po dogovoru</span>
                        </label>
                    </div>
                    <?php
                    $priceWarnEur = warnAdPriceEur();
                    $priceMaxEur = maxAdPriceEur($currentType);
                    $priceEurNow = ($currentPriceType === 'fixed' && (float)($ad['price'] ?? 0) > 0)
                        ? amountToEur((float)$ad['price'], $currentCurrency)
                        : 0.0;
                    $showPriceConfirm = $priceEurNow > $priceWarnEur && $priceEurNow <= $priceMaxEur;
                    ?>
                    <div class="price-amount-row" data-price-amount-row <?= $currentPriceType !== 'fixed' ? 'hidden' : '' ?>
                         data-eur-rsd-rate="<?= h((string)eurRsdRate()) ?>"
                         data-price-warn-eur="<?= h((string)$priceWarnEur) ?>"
                         data-price-max-telefon="<?= h((string)maxAdPriceEur('telefon')) ?>"
                         data-price-max-delovi="<?= h((string)maxAdPriceEur('delovi')) ?>"
                         data-price-max-servis="<?= h((string)maxAdPriceEur('servis')) ?>">
                        <input type="number" step="1" min="1" name="price" inputmode="numeric" data-price-input value="<?= $currentPriceType === 'fixed' ? h((string)$ad['price']) : '' ?>" placeholder="Iznos" <?= $currentPriceType === 'fixed' ? 'required' : 'disabled' ?>>
                        <div class="price-currency-toggle" role="group" aria-label="Valuta">
                            <label class="price-cur-option <?= $currentCurrency === 'eur' ? 'is-on' : '' ?>">
                                <input type="radio" name="currency" value="eur" data-price-currency <?= $currentCurrency === 'eur' ? 'checked' : '' ?>>
                                <span>EUR</span>
                            </label>
                            <label class="price-cur-option <?= $currentCurrency === 'rsd' ? 'is-on' : '' ?>">
                                <input type="radio" name="currency" value="rsd" data-price-currency <?= $currentCurrency === 'rsd' ? 'checked' : '' ?>>
                                <span>RSD</span>
                            </label>
                        </div>
                    </div>
                    <p class="form-hint price-convert-hint" data-price-convert hidden></p>
                    <p class="form-hint price-sanity-hint" data-price-sanity hidden></p>
                    <label class="price-confirm-label" data-price-confirm-wrap <?= $showPriceConfirm ? '' : 'hidden' ?>>
                        <input type="checkbox" name="price_confirmed" value="1" data-price-confirm <?= $showPriceConfirm && !empty($_POST['price_confirmed']) ? 'checked' : '' ?>>
                        <span>Potvrđujem da je cena tačna</span>
                    </label>
                    <p class="form-hint" data-price-hint hidden><?= $currentPriceType === 'fixed' ? 'Na sajtu se cena prikazuje u eurima.' : 'Polje za cenu je isključeno.' ?></p>
                </div>
            </section>

            <section class="ad-form-section" data-panel="telefon" <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>
                <h3 class="ad-form-section-title">Uređaj</h3>
                <?php $currentDeviceType = getAdDeviceType($ad) ?: 'phone'; ?>
                <div class="form-group">
                    <label>Tip *</label>
                    <select name="device_type">
                        <?php foreach ($schema['device_types'] as $dtKey => $dtLabel): ?>
                            <option value="<?= h($dtKey) ?>" <?= $currentDeviceType === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Brend <span class="ad-form-optional">(nije obavezno)</span></label>
                        <select name="brand" data-phone-brand>
                            <option value="">—</option>
                            <?php foreach ($phoneBrands as $brand): ?>
                                <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Model <span class="ad-form-optional">(nije obavezno)</span></label>
                        <input name="model" id="ad-model" value="<?= h((string)$ad['model']) ?>" placeholder="npr. iPhone 13 Pro Max" autocomplete="off">
                    </div>
                </div>
                <p class="form-hint">Nije obavezno — dovoljan je naslov. Brend pomaže u filterima.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Stanje</label>
                        <select name="condition_state" data-cond-phone>
                            <?php foreach ($schema['phone_conditions'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($ad['condition_state'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Memorija</label>
                        <select name="storage">
                            <option value="">—</option>
                            <?php foreach ($schema['storage_options'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($ad['storage'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php
                $phoneExtraOpen = trim((string)($ad['ram'] ?? '')) !== ''
                    || trim((string)($ad['color'] ?? '')) !== ''
                    || trim((string)($ad['sim_status'] ?? '')) !== ''
                    || (isset($ad['battery_health']) && $ad['battery_health'] !== null && $ad['battery_health'] !== '')
                    || !empty($ad['has_warranty'])
                    || (is_array($ad['accessories'] ?? null) && $ad['accessories'] !== []);
                ?>
                <button type="button" class="ad-form-more-toggle" data-phone-more-toggle aria-expanded="<?= $phoneExtraOpen ? 'true' : 'false' ?>">
                    <?= $phoneExtraOpen ? 'Manje detalja ▴' : 'Više detalja (RAM, BH, oprema…) ▾' ?>
                </button>
                <div class="ad-form-more" data-phone-more <?= $phoneExtraOpen ? '' : 'hidden' ?>>
                    <div class="form-row">
                        <div class="form-group">
                            <label>RAM</label>
                            <select name="ram">
                                <option value="">—</option>
                                <?php foreach ($schema['ram_options'] as $ram): ?>
                                    <option value="<?= h($ram) ?>" <?= ($ad['ram'] ?? '') === $ram ? 'selected' : '' ?>><?= h($ram) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Boja</label>
                            <input name="color" value="<?= h((string)($ad['color'] ?? '')) ?>" placeholder="npr. Graphite" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>SIM</label>
                            <select name="sim_status">
                                <option value="">—</option>
                                <?php foreach ($schema['sim_statuses'] as $sim): ?>
                                    <option value="<?= h($sim) ?>" <?= ($ad['sim_status'] ?? '') === $sim ? 'selected' : '' ?>><?= h($sim) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" data-battery-field>
                            <label>Battery Health % <span class="form-hint-inline">(iPhone)</span></label>
                            <input type="number" name="battery_health" min="0" max="100" step="1" inputmode="numeric" value="<?= isset($ad['battery_health']) && $ad['battery_health'] !== null && $ad['battery_health'] !== '' ? h((string)$ad['battery_health']) : '' ?>" placeholder="npr. 87">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="warranty-row">
                            <label class="check-inline">
                                <input type="checkbox" name="has_warranty" value="1" data-warranty-toggle <?= !empty($ad['has_warranty']) ? 'checked' : '' ?>>
                                <span>Garancija</span>
                            </label>
                            <div class="warranty-months" data-warranty-months <?= empty($ad['has_warranty']) ? 'hidden' : '' ?>>
                                <input type="number" name="warranty_months" min="1" max="60" inputmode="numeric" value="<?= !empty($ad['warranty_months']) ? (int)$ad['warranty_months'] : '' ?>" placeholder="Meseci">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Prateća oprema</label>
                        <?php renderChipGroup('accessories', $schema['phone_accessories'], $accessoriesSel); ?>
                    </div>
                </div>
            </section>

            <section class="ad-form-section" data-panel="delovi" <?= $currentType !== 'delovi' ? 'hidden' : '' ?>>
                <h3 class="ad-form-section-title">Detalji opreme</h3>
                <div class="form-group">
                    <label>Brend <span class="ad-form-optional">(nije obavezno)</span></label>
                    <select name="brand_parts" data-parts-brand>
                        <option value="">—</option>
                        <?php foreach ($phoneBrands as $brand): ?>
                            <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" data-parts-brand-hint hidden>Popunjeno iz podkategorije.</p>
                </div>
                <div class="form-group" data-equipment-type-wrap>
                    <label>Tip opreme <span class="ad-form-optional">(ako treba preciznije)</span></label>
                    <select name="equipment_type" data-equipment-type>
                        <option value="">Izaberi</option>
                        <?php foreach ($schema['equipment_types'] as $eq): ?>
                            <option value="<?= h($eq) ?>" <?= ($ad['equipment_type'] ?? '') === $eq ? 'selected' : '' ?>><?= h($eq) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Često se popuni iz podkategorije — menjaš samo ako treba drugačije.</p>
                </div>
                <div class="form-group">
                    <label>Kompatibilni modeli</label>
                    <input name="compatible_models" value="<?= h((string)($ad['compatible_models'] ?? '')) ?>" placeholder="npr. iPhone 13 / 13 Pro">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Stanje</label>
                        <select name="condition_state_parts" data-cond-parts>
                            <?php foreach ($schema['parts_conditions'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($ad['condition_state'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Originalnost</label>
                        <select name="originality">
                            <option value="">—</option>
                            <?php foreach ($schema['originality_options'] as $orig): ?>
                                <option value="<?= h($orig) ?>" <?= ($ad['originality'] ?? '') === $orig ? 'selected' : '' ?>><?= h($orig) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="ad-form-section" data-panel="servis" <?= $currentType !== 'servis' ? 'hidden' : '' ?>>
                <h3 class="ad-form-section-title">Usluga</h3>
                <div class="form-group">
                    <label>Vrsta usluge</label>
                    <?php renderChipGroup('service_types', $schema['service_types'], $serviceTypesSel); ?>
                </div>
                <div class="form-group">
                    <label>Podržani brendovi</label>
                    <?php renderChipGroupList('supported_brands', $phoneBrands, $supportedBrandsSel); ?>
                </div>
                <div class="form-group">
                    <div class="warranty-row">
                        <label class="check-inline">
                            <input type="checkbox" name="has_work_warranty" value="1" data-work-warranty-toggle <?= !empty($ad['has_work_warranty']) ? 'checked' : '' ?>>
                            <span>Garancija na rad</span>
                        </label>
                        <div class="warranty-months" data-work-warranty-months <?= empty($ad['has_work_warranty']) ? 'hidden' : '' ?>>
                            <input type="number" name="work_warranty_months" min="1" max="60" value="<?= !empty($ad['work_warranty_months']) ? (int)$ad['work_warranty_months'] : '' ?>" placeholder="Meseci">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Dodatno</label>
                    <?php renderChipGroup('service_extras', $schema['service_extras'], $serviceExtrasSel); ?>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">Fotografije <span data-photo-required <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>*</span></h3>
                <p class="form-hint" style="margin-top:0;">
                    <span data-phone-photo-hint <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>Za telefon je obavezna najmanje jedna fotografija uređaja. </span>
                    Do 10 slika — prva je naslovna. Možeš dodavati jednu po jednu; ↑↓ menja redosled, × briše.
                </p>
                <?php if ($existingImages): ?>
                    <div class="photo-existing" data-photo-existing>
                        <?php foreach ($existingImages as $idx => $img): ?>
                            <div class="photo-existing-item" data-photo-item>
                                <button type="button" class="photo-slot-remove" data-photo-remove aria-label="Ukloni sliku">×</button>
                                <img src="<?= h((string)$img) ?>" alt="">
                                <input type="hidden" name="image_order[]" value="<?= h((string)$img) ?>">
                                <input type="hidden" name="keep_images[]" value="<?= h((string)$img) ?>">
                                <label class="photo-cover"><input type="radio" name="cover_image" value="<?= h((string)$img) ?>" <?= $idx === 0 ? 'checked' : '' ?>> Naslovna</label>
                                <div class="photo-reorder">
                                    <button type="button" class="btn-sm" data-photo-up title="Gore">↑</button>
                                    <button type="button" class="btn-sm" data-photo-down title="Dole">↓</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <label class="ad-photo-add" data-photo-drop>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-photo-input <?= $currentType === 'telefon' && $existingImages === [] ? 'required' : '' ?>>
                    <span>+ Dodaj fotografije<br><small>ili prevuci ovde — možeš više puta</small></span>
                </label>
                <div class="photo-upload" data-photo-preview></div>
                <div class="form-group" style="margin-top:14px;">
                    <label>Opis</label>
                    <textarea name="description" rows="4" placeholder="Stanje, šta ide uz oglas…"><?= h((string)$ad['description']) ?></textarea>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">Kontakt</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Grad *</label>
                        <select name="location" required>
                            <option value="">Izaberi</option>
                            <?php foreach ($cfg['cities'] as $city): ?>
                                <option value="<?= h($city) ?>" <?= ($ad['location'] ?? '') === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Telefon *</label>
                        <input name="contact_phone" type="tel" inputmode="tel" placeholder="06x xxx xxxx" value="<?= h((string)$ad['contact_phone']) ?>" required autocomplete="tel">
                    </div>
                </div>

                <button type="button" class="ad-form-more-toggle" data-contact-more-toggle aria-expanded="<?= $contactExtraOpen ? 'true' : 'false' ?>">
                    <?= $contactExtraOpen ? 'Manje opcija ▴' : 'Dodatne opcije (kontakt…) ▾' ?>
                </button>
                <div class="ad-form-more" data-contact-more <?= $contactExtraOpen ? '' : 'hidden' ?>>
                    <div class="form-group">
                        <label>Kako da te kontaktiraju</label>
                        <?php renderChipGroup('contact_methods', $schema['contact_methods'], $contactSel); ?>
                    </div>
                    <div class="form-group">
                        <label>Preuzimanje</label>
                        <?php renderChipGroup('pickup_methods', $schema['pickup_methods'], $pickupSel); ?>
                    </div>
                    <div class="form-group">
                        <label>Naziv izloga</label>
                        <input name="shop_name" value="<?= h((string)($ad['shop_name'] ?? '')) ?>" placeholder="Iz profila ako ostaviš prazno" autocomplete="organization">
                    </div>
                    <div class="form-group">
                        <label>Oznaka (nije obavezno)</label>
                        <input name="badge" placeholder="npr. Garancija" value="<?= h((string)($ad['badge'] ?? '')) ?>">
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
                <section class="ad-form-section ad-form-section--promo">
                    <h3 class="ad-form-section-title">Istakni oglas <span class="ad-form-optional">(nije obavezno)</span></h3>
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
                <p class="form-hint ad-form-promo-edit">Promocije: <a href="/nalog.php?tab=oglasi">Moji oglasi</a>.</p>
            <?php endif; ?>

            <div class="ad-form-submit">
                <button class="btn-call" type="submit"><?= $isEdit ? 'Sačuvaj izmene' : 'Objavi oglas' ?></button>
                <?php if (!$isEdit): ?>
                    <button class="btn-message" type="submit" name="save_and_add_another" value="1">Objavi i dodaj još</button>
                <?php else: ?>
                    <a href="/nalog.php?tab=oglasi" class="btn-message">Odustani</a>
                <?php endif; ?>
            </div>
            <?php if (!$isEdit): ?>
                <p class="form-hint" style="margin-top:8px;text-align:center;">„Objavi i dodaj još” čuva tip, grad, telefon i kategoriju izloga za sledeći oglas.</p>
            <?php endif; ?>
        </form>
    </main>
</div>
