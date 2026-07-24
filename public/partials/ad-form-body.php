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
/** @var int $userId */
?>
<div class="main-wrap">
    <main class="content ad-form-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › <?= $isEdit ? 'Izmena oglasa' : 'Postavi oglas' ?></div>

        <form method="POST" enctype="multipart/form-data" class="form-card ad-form-card" data-ad-form>
            <?= csrfField() ?>
            <div class="ad-form-head">
                <h2><?= $isEdit ? 'Izmeni oglas' : 'Novi oglas' ?></h2>
                <p class="ad-form-sub">Polja se prilagođavaju kategoriji — popuni samo što važi za tvoj oglas.</p>
            </div>

            <?php if ($formError !== ''): ?>
                <p class="form-hint ad-form-error" data-form-error><?= h($formError) ?></p>
            <?php endif; ?>

            <div class="form-group" data-category-wrap hidden>
                <label>Podkategorija</label>
                <select name="category_group" id="ad-category" data-keep-enabled="1" data-group-map="<?= h(json_encode($groupMeta, JSON_UNESCAPED_UNICODE)) ?>">
                    <?php foreach ($cfg['groups'] as $key => $group): ?>
                        <option value="<?= h($key) ?>" data-ad-type="<?= h((string)($group['ad_type'] ?? '')) ?>" <?= ($ad['category_group'] ?? '') === $key ? 'selected' : '' ?>><?= h($group['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">1. Osnovne informacije</h3>

                <div class="form-group">
                    <label>Kategorija *</label>
                    <div class="form-type-select">
                        <label class="form-type-option <?= $currentType === 'telefon' ? 'selected' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="telefon" <?= $currentType === 'telefon' ? 'checked' : '' ?>>
                            <span>
                                <strong>Telefon</strong>
                                <small class="hide-mobile">Ceo uređaj</small>
                                <small class="show-mobile">Uređaj</small>
                            </span>
                        </label>
                        <label class="form-type-option <?= $currentType === 'delovi' ? 'selected-parts' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="delovi" <?= $currentType === 'delovi' ? 'checked' : '' ?>>
                            <span>
                                <strong>Delovi</strong>
                                <small class="hide-mobile">Oprema i delovi</small>
                                <small class="show-mobile">Oprema</small>
                            </span>
                        </label>
                        <label class="form-type-option <?= $currentType === 'servis' ? 'selected-service' : '' ?>">
                            <input data-form-type type="radio" name="ad_type" value="servis" <?= $currentType === 'servis' ? 'checked' : '' ?>>
                            <span>
                                <strong>Servis</strong>
                                <small class="hide-mobile">Popravka / usluga</small>
                                <small class="show-mobile">Usluga</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ad-title">Naslov oglasa *</label>
                    <input name="title" id="ad-title" placeholder="npr. iPhone 13 Pro Max 256GB" value="<?= h((string)$ad['title']) ?>" required maxlength="120" autocomplete="off">
                </div>

                <div class="form-group" data-listing-types>
                    <label>Tip oglasa</label>
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
                    <div class="price-amount-row" data-price-amount-row <?= $currentPriceType !== 'fixed' ? 'hidden' : '' ?>>
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
                    <p class="form-hint" data-price-hint><?= $currentPriceType === 'fixed' ? 'Unesi iznos i valutu.' : 'Polje za cenu je isključeno.' ?></p>
                </div>
            </section>

            <section class="ad-form-section" data-panel="telefon" <?= $currentType !== 'telefon' ? 'hidden' : '' ?>>
                <h3 class="ad-form-section-title">2. Detalji telefona</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Brend</label>
                        <select name="brand" data-phone-brand>
                            <?php foreach ($phoneBrands as $brand): ?>
                                <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input name="model" id="ad-model" list="model-list" value="<?= h((string)$ad['model']) ?>" placeholder="npr. iPhone 13 Pro Max" autocomplete="off">
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
                    <?= $phoneExtraOpen ? 'Manje detalja ▴' : 'Više detalja (RAM, boja, SIM…) ▾' ?>
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
                            <label>SIM status</label>
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
                                <input type="number" name="warranty_months" min="1" max="60" inputmode="numeric" value="<?= !empty($ad['warranty_months']) ? (int)$ad['warranty_months'] : '' ?>" placeholder="Broj meseci">
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
                <h3 class="ad-form-section-title">2. Detalji opreme / delova</h3>
                <div class="form-group">
                    <label>Tip opreme</label>
                    <select name="equipment_type">
                        <option value="">Izaberi</option>
                        <?php foreach ($schema['equipment_types'] as $eq): ?>
                            <option value="<?= h($eq) ?>" <?= ($ad['equipment_type'] ?? '') === $eq ? 'selected' : '' ?>><?= h($eq) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <div class="form-group">
                    <label>Brend (opciono)</label>
                    <select name="brand_parts" data-parts-brand>
                        <option value="">—</option>
                        <?php foreach ($phoneBrands as $brand): ?>
                            <option value="<?= h($brand) ?>" <?= ($ad['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= h($brand) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="ad-form-section" data-panel="servis" <?= $currentType !== 'servis' ? 'hidden' : '' ?>>
                <h3 class="ad-form-section-title">2. Detalji usluge</h3>
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
                            <input type="number" name="work_warranty_months" min="1" max="60" value="<?= !empty($ad['work_warranty_months']) ? (int)$ad['work_warranty_months'] : '' ?>" placeholder="Broj meseci">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Dodatno</label>
                    <?php renderChipGroup('service_extras', $schema['service_extras'], $serviceExtrasSel); ?>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">3. Mediji i opis</h3>
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
                <label class="ad-photo-add" data-photo-drop>
                    <input type="file" name="images[]" accept="image/*" multiple data-photo-input>
                    <span>+ Dodaj fotografije<br><small>ili prevuci ovde</small></span>
                </label>
                <div class="photo-upload" data-photo-preview></div>
                <div class="form-group" style="margin-top:14px;">
                    <label>Detaljan opis</label>
                    <textarea name="description" rows="5" placeholder="Stanje, šta ide uz oglas, napomene..."><?= h((string)$ad['description']) ?></textarea>
                </div>
            </section>

            <section class="ad-form-section">
                <h3 class="ad-form-section-title">4. Lokacija i kontakt</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Grad / Mesto *</label>
                        <select name="location" required>
                            <option value="">Izaberi grad</option>
                            <?php foreach ($cfg['cities'] as $city): ?>
                                <option value="<?= h($city) ?>" <?= ($ad['location'] ?? '') === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Broj telefona *</label>
                        <input name="contact_phone" type="tel" inputmode="tel" placeholder="06x xxx xxxx" value="<?= h((string)$ad['contact_phone']) ?>" required autocomplete="tel">
                    </div>
                </div>
                <div class="form-group">
                    <label>Opcije kontaktiranja</label>
                    <?php renderChipGroup('contact_methods', $schema['contact_methods'], $contactSel); ?>
                </div>
                <div class="form-group">
                    <label>Način preuzimanja</label>
                    <?php renderChipGroup('pickup_methods', $schema['pickup_methods'], $pickupSel); ?>
                </div>

                <?php
                $contactExtraOpen = trim((string)($ad['shop_name'] ?? '')) !== ''
                    || trim((string)($ad['badge'] ?? '')) !== ''
                    || (int)($ad['is_active'] ?? 1) !== 1
                    || !empty($ad['is_sold'])
                    || (isAdmin() && !empty($ad['is_promoted']));
                ?>
                <button type="button" class="ad-form-more-toggle" data-contact-more-toggle aria-expanded="<?= $contactExtraOpen ? 'true' : 'false' ?>">
                    <?= $contactExtraOpen ? 'Manje opcija ▴' : 'Dodatne opcije ▾' ?>
                </button>
                <div class="ad-form-more" data-contact-more <?= $contactExtraOpen ? '' : 'hidden' ?>>
                    <div class="form-group">
                        <label>Naziv prodavnice / izloga</label>
                        <input name="shop_name" value="<?= h((string)($ad['shop_name'] ?? '')) ?>" placeholder="Iz profila ako ostaviš prazno" autocomplete="organization">
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
