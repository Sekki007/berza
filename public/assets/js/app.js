(function () {
  function all(sel, root) { return (root || document).querySelectorAll(sel); }
  function one(sel, root) { return (root || document).querySelector(sel); }

  function csrfToken() {
    var meta = one('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  function appendCsrf(fd) {
    if (!fd || typeof fd.append !== 'function') return fd;
    var has = typeof fd.has === 'function' ? fd.has('_csrf') : false;
    if (!has) fd.append('_csrf', csrfToken());
    return fd;
  }

  function csrfHeaders(extra) {
    var h = extra || {};
    var t = csrfToken();
    if (t) h['X-CSRF-Token'] = t;
    return h;
  }

  function ensureCsrfOnForms() {
    var token = csrfToken();
    if (!token) return;
    all('form').forEach(function (form) {
      var method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post') return;
      var existing = form.querySelector('input[name="_csrf"]');
      if (existing) {
        existing.value = token;
        return;
      }
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = token;
      form.appendChild(input);
    });
  }

  ensureCsrfOnForms();
  document.addEventListener('submit', function () { ensureCsrfOnForms(); }, true);

  function syncTypeCheckboxes(source) {
    const value = source.value;
    const checked = source.checked;
    all('[data-type-filter][value="' + value + '"]').forEach(function (cb) { cb.checked = checked; });
  }

  function applyTypeChipVisuals() {
    all('.type-chip').forEach(function (chip) {
      chip.classList.remove('checked-phone', 'checked-parts', 'checked-service');
      const cb = one('[data-type-filter]', chip);
      if (!cb || !cb.checked) return;
      if (cb.value === 'telefon') chip.classList.add('checked-phone');
      if (cb.value === 'delovi') chip.classList.add('checked-parts');
      if (cb.value === 'servis') chip.classList.add('checked-service');
    });
  }

  function selectedTypes() {
    const values = [];
    all('.type-filter [data-type-filter]').forEach(function (cb) {
      if (cb.checked) values.push(cb.value);
    });
    return values;
  }

  function filterAds() {
    const selected = selectedTypes();
    const ads = all('.listing-card[data-category]');
    if (!ads.length) return;
    let visible = 0;
    ads.forEach(function (ad) {
      const show = selected.length === 0 || selected.includes(ad.getAttribute('data-category'));
      ad.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    const count = one('[data-results-count]');
    if (count) {
      const total = count.getAttribute('data-results-total');
      if (selected.length === 0 && total) {
        count.textContent = total;
      } else {
        count.textContent = String(visible);
      }
    }
    const empty = one('.empty-state');
    if (empty) empty.classList.toggle('visible', visible === 0);
  }

  function initTypeFilters() {
    if (!all('[data-type-filter]').length) return;
    all('[data-type-filter]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        syncTypeCheckboxes(cb);
        applyTypeChipVisuals();
        filterAds();
      });
    });
    applyTypeChipVisuals();
    filterAds();
  }

  function initDrawer() {
    const openBtn = one('[data-open-filters]');
    const closeBtn = one('[data-close-filters]');
    const applyBtn = one('[data-apply-filters]');
    const overlay = one('.filter-overlay');
    const drawer = one('.filter-drawer');
    if (!openBtn || !overlay || !drawer) return;

    function open() {
      overlay.classList.add('open');
      drawer.classList.add('open');
      document.body.classList.add('drawer-open');
      document.body.style.overflow = 'hidden';
    }
    function close() {
      overlay.classList.remove('open');
      drawer.classList.remove('open');
      document.body.classList.remove('drawer-open');
      if (!document.body.classList.contains('account-menu-open')) {
        document.body.style.overflow = '';
      }
    }

    openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (applyBtn) applyBtn.addEventListener('click', function () { filterAds(); close(); });
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('open')) close();
    });
  }

  function initListingFilters() {
    all('[data-filter-form]').forEach(function (form) {
      form.querySelectorAll('.filter-chip input[type="radio"]').forEach(function (input) {
        input.addEventListener('change', function () {
          const group = input.closest('.filter-chips');
          if (!group) return;
          group.querySelectorAll('.filter-chip').forEach(function (chip) {
            chip.classList.toggle('is-active', chip.querySelector('input') === input && input.checked);
          });
        });
      });

      const presets = form.querySelector('[data-price-presets]');
      if (!presets) return;
      const minInput = form.querySelector('input[name="min_price"]');
      const maxInput = form.querySelector('input[name="max_price"]');
      presets.querySelectorAll('.filter-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (minInput) minInput.value = btn.getAttribute('data-min') || '';
          if (maxInput) maxInput.value = btn.getAttribute('data-max') || '';
          presets.querySelectorAll('.filter-preset').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
          });
        });
      });
      function syncPresetActive() {
        const min = minInput ? minInput.value : '';
        const max = maxInput ? maxInput.value : '';
        presets.querySelectorAll('.filter-preset').forEach(function (b) {
          b.classList.toggle('is-active', (b.getAttribute('data-min') || '') === min && (b.getAttribute('data-max') || '') === max);
        });
      }
      if (minInput) minInput.addEventListener('input', syncPresetActive);
      if (maxInput) maxInput.addEventListener('input', syncPresetActive);
    });
  }

  function initFocusSearch() {
    function focusSearch() {
      const input = one('[data-search-input]');
      if (!input) return;
      input.focus();
      if (input.scrollIntoView) input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    all('[data-focus-search]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (window.location.pathname === '/index.php' || window.location.pathname === '/') {
          e.preventDefault();
          focusSearch();
          history.replaceState(null, '', '#search');
        }
      });
    });
    if (window.location.hash === '#search') {
      setTimeout(focusSearch, 100);
    }
  }

  function initFormTypeSelect() {
    all('[data-form-type]').forEach(function (input) {
      input.addEventListener('change', function () {
        all('.form-type-option').forEach(function (opt) {
          opt.classList.remove('selected', 'selected-parts', 'selected-service');
        });
        all('.ad-type-tile').forEach(function (tile) {
          tile.classList.remove('is-on', 'is-parts', 'is-service');
        });
        const selected = one('[data-form-type]:checked');
        if (!selected) return;
        const opt = selected.closest('.form-type-option');
        if (opt) {
          if (selected.value === 'telefon') opt.classList.add('selected');
          if (selected.value === 'delovi') opt.classList.add('selected-parts');
          if (selected.value === 'servis') opt.classList.add('selected-service');
        }
        const tile = selected.closest('.ad-type-tile');
        if (tile) {
          tile.classList.add('is-on');
          if (selected.value === 'delovi') tile.classList.add('is-parts');
          if (selected.value === 'servis') tile.classList.add('is-service');
        }
        syncAdFormByType(selected.value);
        const form = one('[data-ad-form]');
        if (form) {
          const title = one('[data-ad-title]', form);
          const suggestBtn = one('[data-suggest-title]', form);
          // tip promenjen → dozvoli auto naslov opet
          if (suggestBtn) suggestBtn.click();
          else if (title && !(title.value || '').trim()) {
            title.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }
        const priceInput = one('[data-price-input]');
        if (priceInput) priceInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    const current = one('[data-form-type]:checked');
    if (current) syncAdFormByType(current.value);
  }

  function syncPhonePhotoRequirement(form, type) {
    const input = one('[data-photo-input]', form);
    if (!input) return;
    const hasExisting = all('[data-photo-existing] [data-photo-item]', form).length > 0;
    const listing = one('[data-listing-type]:checked', form);
    const isBuy = listing && listing.value === 'buy';
    const isPhone = type === 'telefon';
    const required = isPhone && !isBuy && !hasExisting;
    input.required = required;
    const requiredMark = one('[data-photo-required]', form);
    const hint = one('[data-phone-photo-hint]', form);
    const buyHint = one('[data-buy-photo-hint]', form);
    if (requiredMark) requiredMark.hidden = !(isPhone && !isBuy);
    if (hint) hint.hidden = !(isPhone && !isBuy);
    if (buyHint) buyHint.hidden = !isBuy;
    const priceLabel = one('[data-price-label]', form);
    if (priceLabel) {
      if (isBuy) priceLabel.textContent = 'Budžet do';
      else if (type === 'servis') priceLabel.textContent = 'Cena od';
      else priceLabel.textContent = 'Cena';
    }
  }

  function syncAdFormByType(type) {
    const form = one('[data-ad-form]');
    if (!form) return;

    form.querySelectorAll('[data-panel]').forEach(function (panel) {
      const match = panel.getAttribute('data-panel') === type;
      if (match) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
      panel.querySelectorAll('input, select, textarea').forEach(function (el) {
        if (el.getAttribute('data-keep-enabled') === '1') return;
        el.disabled = !match;
      });
    });

    form.querySelectorAll('[data-listing-opt]').forEach(function (lab) {
      const forTypes = (lab.getAttribute('data-for-types') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      const ok = forTypes.indexOf(type) !== -1;
      lab.hidden = !ok;
      const inp = lab.querySelector('input');
      if (inp) {
        inp.disabled = !ok;
        if (ok) inp.removeAttribute('disabled');
      }
      if (!ok && inp && inp.checked) {
        inp.checked = false;
        lab.classList.remove('is-on');
      }
    });

    let listingChecked = one('[data-listing-type]:checked:not(:disabled)', form);
    if (!listingChecked) {
      const fallback = type === 'servis'
        ? one('[data-listing-type][value="service"]', form)
        : one('[data-listing-type][value="sell"]', form);
      if (fallback) {
        fallback.disabled = false;
        fallback.removeAttribute('disabled');
        fallback.checked = true;
        const lab = fallback.closest('.chip-option');
        if (lab) {
          lab.hidden = false;
          lab.classList.add('is-on');
        }
      }
    } else if (type === 'servis') {
      const serviceInp = one('[data-listing-type][value="service"]', form);
      if (serviceInp) {
        serviceInp.disabled = false;
        serviceInp.removeAttribute('disabled');
        serviceInp.checked = true;
        const lab = serviceInp.closest('.chip-option');
        if (lab) {
          lab.hidden = false;
          lab.classList.add('is-on');
        }
      }
    }

    form.querySelectorAll('[data-listing-opt]').forEach(function (lab) {
      const inp = lab.querySelector('[data-listing-type]');
      lab.classList.toggle('is-on', !!(inp && inp.checked && !inp.disabled));
    });

    const cat = one('#ad-category', form);
    const catWrap = one('[data-category-wrap]', form);
    if (cat && cat.tagName === 'SELECT') {
      const options = all('#ad-category option', form);
      let firstVisible = null;
      let visibleCount = 0;
      options.forEach(function (opt) {
        const t = opt.getAttribute('data-ad-type') || '';
        const match = !t || t === type;
        opt.hidden = !match;
        opt.disabled = !match;
        if (match) {
          visibleCount++;
          if (!firstVisible) firstVisible = opt;
        }
      });
      const selected = cat.options[cat.selectedIndex];
      if (selected && (selected.hidden || selected.disabled) && firstVisible) {
        cat.value = firstVisible.value;
      }
      // Podkategorija se prikazuje samo za Opremu (delovi)
      if (catWrap) {
        if (type === 'delovi' && visibleCount > 0) catWrap.removeAttribute('hidden');
        else catWrap.setAttribute('hidden', '');
      }
      if (type === 'delovi') {
        applyCategoryGroupDefaults(form, true);
      }
    } else if (cat) {
      // v2: category_group je hidden input (ne select)
      if (type === 'telefon') cat.value = 'phones';
      else if (type === 'servis') cat.value = 'service';
      else if (type === 'delovi' && !(cat.value || '').trim()) cat.value = 'other_parts';
      if (catWrap) catWrap.setAttribute('hidden', '');
    }

    const batt = one('[data-battery-field]', form);
    if (batt) {
      batt.hidden = type !== 'telefon';
      batt.querySelectorAll('input, select, textarea, button').forEach(function (el) {
        el.disabled = type !== 'telefon';
      });
    }

    form.querySelectorAll('[data-title-preview-block]').forEach(function (block) {
      const forType = block.getAttribute('data-title-for') || '';
      if (forType === 'telefon') {
        block.hidden = type !== 'telefon';
      } else if (forType === 'other') {
        block.hidden = type === 'telefon';
      }
    });

    const phoneCond = one('[data-phone-condition-block]', form);
    if (phoneCond) {
      phoneCond.hidden = type !== 'telefon';
      phoneCond.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.disabled = type !== 'telefon';
      });
    }

    form.querySelectorAll('[data-panel-more]').forEach(function (wrap) {
      const match = wrap.getAttribute('data-panel-more') === type;
      wrap.hidden = !match;
      wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
        if (el.getAttribute('data-keep-enabled') === '1') return;
        el.disabled = !match;
      });
    });

    syncPhonePhotoRequirement(form, type);
  }

  function filterEquipmentTypeOptions(form, bucket) {
    const equipSel = one('[data-equipment-type]', form);
    if (!equipSel) return;
    bucket = bucket || 'both';
    const current = (equipSel.value || '').trim();
    let currentStillVisible = current === '';

    Array.prototype.forEach.call(equipSel.querySelectorAll('optgroup'), function (group) {
      const gBucket = group.getAttribute('data-equip-bucket') || '';
      const show = bucket === 'both' || gBucket === '' || gBucket === bucket;
      group.hidden = !show;
      group.disabled = !show;
      Array.prototype.forEach.call(group.querySelectorAll('option'), function (opt) {
        opt.hidden = !show;
        opt.disabled = !show;
        if (show && opt.value === current) currentStillVisible = true;
      });
    });

    if (!currentStillVisible) {
      equipSel.value = '';
    }
  }

  function applyCategoryGroupDefaults(form, forceEquip) {
    const cat = one('#ad-category', form);
    if (!cat || cat.tagName !== 'SELECT') return;
    const opt = cat.options[cat.selectedIndex];
    if (!opt || opt.disabled || opt.hidden) return;

    const brand = (opt.getAttribute('data-brand') || '').trim();
    const equip = (opt.getAttribute('data-equipment-type') || '').trim();
    const bucket = (opt.getAttribute('data-equipment-bucket') || 'both').trim() || 'both';
    const partsBrand = one('[data-parts-brand]', form);
    const brandHint = one('[data-parts-brand-hint]', form);
    const equipSel = one('[data-equipment-type]', form);

    filterEquipmentTypeOptions(form, bucket);

    if (partsBrand) {
      if (brand) {
        if (forceEquip || !(partsBrand.value || '').trim()) {
          partsBrand.value = brand;
        }
        if ((partsBrand.value || '') === brand) {
          if (brandHint) brandHint.removeAttribute('hidden');
        } else if (brandHint) {
          brandHint.setAttribute('hidden', '');
        }
      } else {
        if (forceEquip) partsBrand.value = '';
        if (brandHint) brandHint.setAttribute('hidden', '');
      }
    }

    if (equipSel) {
      // Za „iPhone delovi“ ne forsira generički „Rezervni delovi“ — korisnik bira LCD/baterija…
      const isGenericParts = equip === 'Rezervni delovi';
      if (equip && !isGenericParts && (forceEquip || !(equipSel.value || '').trim())) {
        // Samo ako je opcija vidljiva u filtriranoj listi
        const matchOpt = Array.prototype.find.call(equipSel.options, function (o) {
          return o.value === equip && !o.disabled && !o.hidden;
        });
        if (matchOpt) equipSel.value = equip;
      } else if (forceEquip && (isGenericParts || !equip)) {
        if (!equipSel.value || equipSel.options[equipSel.selectedIndex].disabled) {
          equipSel.value = '';
        }
      }
    }
  }

  function initCategoryGroupSync() {
    const form = one('[data-ad-form]');
    if (!form) return;
    const cat = one('#ad-category', form);
    if (!cat || cat.tagName !== 'SELECT') return;
    cat.addEventListener('change', function () {
      applyCategoryGroupDefaults(form, true);
    });
    const partsBrand = one('[data-parts-brand]', form);
    if (partsBrand) {
      partsBrand.addEventListener('change', function () {
        const opt = cat.options[cat.selectedIndex];
        const expected = opt ? (opt.getAttribute('data-brand') || '').trim() : '';
        const hint = one('[data-parts-brand-hint]', form);
        if (!hint) return;
        if (expected && (partsBrand.value || '') === expected) hint.removeAttribute('hidden');
        else hint.setAttribute('hidden', '');
      });
    }
    const typeEl = one('[data-form-type]:checked', form);
    if (typeEl && typeEl.value === 'delovi') {
      applyCategoryGroupDefaults(form, false);
    }
  }

  function buildSuggestedAdTitle(form) {
    const typeEl = one('[data-form-type]:checked', form);
    const type = typeEl ? typeEl.value : 'telefon';

    if (type === 'delovi') {
      const eq = one('[data-equipment-type]', form) || one('[name="equipment_type"]', form);
      const compat = one('[data-compat-value]', form) || one('input[name="compatible_models"]', form);
      const orig = one('input[name="originality"]:checked', form) || one('select[name="originality"]', form);
      let eqVal = eq ? (eq.value || '').trim() : '';
      if (!eqVal) {
        const kind = one('[data-parts-kind]:checked', form);
        if (kind) eqVal = (kind.getAttribute('data-equipment-value') || '').trim();
      }
      if (eqVal === 'Rezervni delovi') eqVal = 'Delovi';
      if (!eqVal || eqVal === 'Ostalo') eqVal = 'Oprema';
      const compatVal = compat ? (compat.value || '').trim() : '';
      let title = eqVal;
      if (compatVal) title += ' za ' + compatVal;
      const origVal = orig ? (orig.value || '').trim() : '';
      if (origVal && origVal !== 'Ne znam') title += ' – ' + origVal;
      return title.trim().slice(0, 120);
    }

    if (type === 'servis') {
      return 'Servis';
    }

    const brand = one('[data-phone-brand]', form);
    const model = one('#ad-model', form);
    const storage = one('select[name="storage"]', form) || one('[data-storage-select]', form);
    const condition = one('[data-cond-phone]:checked', form) || one('[data-cond-phone]', form);
    const listing = one('[data-listing-type]:checked', form);
    const tradeFor = one('[data-trade-for]', form);
    const b = brand ? (brand.value || '').trim() : '';
    const m = model ? (model.value || '').trim() : '';
    const parts = [];
    const cue = /^(iphone|galaxy|pixel|redmi|poco|xiaomi|huawei|honor|nothing)\b/i;
    if (b && b !== 'Ostalo' && m && (m.toLowerCase().indexOf(b.toLowerCase()) === 0 || cue.test(m))) {
      parts.push(m);
    } else {
      if (b && b !== 'Ostalo') parts.push(b);
      if (m) parts.push(m);
    }
    const st = storage ? (storage.value || '').trim() : '';
    if (st) parts.push(st);
    let title = parts.join(' ').trim();
    const cond = condition ? (condition.value || '').trim() : '';
    if (cond) title = (title + (title ? ' – ' : '') + cond).trim();
    if (listing && listing.value === 'trade' && tradeFor) {
      const tf = (tradeFor.value || '').trim();
      if (tf) title = (title + (title ? ' ' : '') + 'za ' + tf).trim();
    }
    return title.slice(0, 120);
  }

  function initPhoneCatalogPicker() {
    var form = one('[data-ad-form]');
    if (!form) return;
    var modelSel = one('[data-phone-model]', form);
    var variantSel = one('[data-phone-variant]', form);
    var brand = one('[data-phone-brand]', form);
    var modelInput = one('#ad-model', form);
    var storage = one('select[name="storage"]', form);
    var device = one('select[name="device_type"]', form);
    var wrap = one('[data-phone-catalog-picker]', form);
    if (!modelSel || !variantSel) return;

    function variantsFor(opt) {
      if (!opt) return [];
      try {
        return JSON.parse(opt.getAttribute('data-variants') || '[]') || [];
      } catch (e) {
        return [];
      }
    }

    function refillVariants(keep) {
      var opt = modelSel.options[modelSel.selectedIndex];
      var vars = variantsFor(opt);
      var current = keep ? variantSel.value : '';
      variantSel.innerHTML = '';
      var empty = document.createElement('option');
      empty.value = '';
      empty.textContent = vars.length ? 'Nije izabrano' : 'Nema službene varijante';
      variantSel.appendChild(empty);
      vars.forEach(function (variant) {
        var option = document.createElement('option');
        option.value = variant.id;
        option.textContent = variant.storage;
        option.setAttribute('data-storage', variant.storage);
        if (variant.id === current) option.selected = true;
        variantSel.appendChild(option);
      });
      variantSel.disabled = vars.length === 0;
    }

    function applyModel() {
      var opt = modelSel.options[modelSel.selectedIndex];
      if (!opt || !opt.value) {
        refillVariants(false);
        return;
      }
      var brandName = opt.getAttribute('data-brand') || '';
      var modelName = opt.getAttribute('data-name') || '';
      if (brand && brandName) {
        Array.prototype.forEach.call(brand.options, function (option) {
          if (option.value === brandName) option.selected = true;
        });
        brand.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (modelInput && modelName) {
        modelInput.value = modelName;
        modelInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      refillVariants(true);
    }

    function applyVariant() {
      var opt = variantSel.options[variantSel.selectedIndex];
      if (!opt || !storage) return;
      var value = opt.getAttribute('data-storage') || '';
      if (!value) return;
      var matched = false;
      Array.prototype.forEach.call(storage.options, function (option) {
        if (option.value === value) {
          option.selected = true;
          matched = true;
        }
      });
      if (matched) storage.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncDevice() {
      var phone = !device || device.value === 'phone';
      if (wrap) wrap.hidden = !phone;
      modelSel.disabled = !phone;
      if (!phone) {
        modelSel.value = '';
        refillVariants(false);
        variantSel.disabled = true;
      } else if (variantSel.options.length > 1) {
        variantSel.disabled = false;
      }
    }

    modelSel.addEventListener('change', applyModel);
    variantSel.addEventListener('change', applyVariant);
    if (device) device.addEventListener('change', syncDevice);
    syncDevice();
  }

  function initAdFormExtras() {
    const form = one('[data-ad-form]');
    if (!form) return;

    const title = one('#ad-title', form) || one('[data-ad-title]', form);
    const suggestBtn = one('[data-suggest-title]', form);
    const titleHint = one('[data-title-hint]', form);
    let titleManual = !!(title && (title.value || '').trim() !== '' && form.getAttribute('data-is-edit') === '1');
    // Na novom oglasu: auto; na edit ako već ima naslov — ne diraj dok ne klikne Osveži
    if (title && (title.value || '').trim() !== '') {
      // Ako je edit forma, smatraj ručnim dok ne osveži
      const isEdit = !!one('.ad-form-head h2', form) && /Izmeni/i.test((one('.ad-form-head h2', form).textContent || ''));
      titleManual = isEdit;
    }

    function setTitleHint() {
      if (!titleHint) return;
      titleHint.textContent = titleManual
        ? 'Naslov je zaključan (ručno). Klikni „Osveži“ da se ponovo složi iz polja.'
        : 'Naslov se ažurira dok popunjavaš brend/model… Ručna izmena ga zaključava.';
    }

    function fillSuggestedTitle(force) {
      if (!title) return;
      if (!force && titleManual) return;
      const suggested = buildSuggestedAdTitle(form);
      if (!suggested) return;
      title.value = suggested;
      if (force) titleManual = false;
      setTitleHint();
    }

    if (suggestBtn) {
      suggestBtn.addEventListener('click', function () {
        titleManual = false;
        fillSuggestedTitle(true);
      });
    }

    if (title) {
      title.addEventListener('input', function () {
        titleManual = true;
        setTitleHint();
      });
    }

    form.querySelectorAll('[data-title-source]').forEach(function (el) {
      el.addEventListener('change', function () { fillSuggestedTitle(false); });
      el.addEventListener('input', function () { fillSuggestedTitle(false); });
      el.addEventListener('blur', function () { fillSuggestedTitle(false); });
    });

    setTitleHint();
    if (!titleManual) fillSuggestedTitle(false);

    form.addEventListener('submit', function (e) {
      if (form.getAttribute('data-submitting') === '1') {
        e.preventDefault();
        return;
      }
      form.setAttribute('data-submitting', '1');
      form.classList.add('is-publishing');
      form.querySelectorAll('[type="submit"]').forEach(function (btn) {
        if (!btn.getAttribute('data-label')) {
          btn.setAttribute('data-label', (btn.textContent || '').trim());
        }
        btn.textContent = 'Objavljivanje…';
      });
    });

    // Stepper: skrol do sekcije
    form.querySelectorAll('[data-goto-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const step = btn.getAttribute('data-goto-step');
        form.querySelectorAll('[data-goto-step]').forEach(function (b) {
          b.classList.toggle('is-on', b === btn);
        });
        const target = one('[data-form-step="' + step + '"]', form);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    function syncPriceUi() {
      const typeEl = one('[data-price-type]:checked', form);
      const type = typeEl ? typeEl.value : 'fixed';
      const amountRow = one('[data-price-amount-row]', form);
      const priceInput = one('[data-price-input]', form);
      const hint = one('[data-price-hint]', form);
      const convert = one('[data-price-convert]', form);
      const sanity = one('[data-price-sanity]', form);
      const confirmWrap = one('[data-price-confirm-wrap]', form);
      const confirmCb = one('[data-price-confirm]', form);
      const fixed = type === 'fixed';

      form.querySelectorAll('.price-type-option').forEach(function (lab) {
        const inp = lab.querySelector('[data-price-type]');
        lab.classList.toggle('is-on', !!(inp && inp.checked));
      });
      form.querySelectorAll('.price-cur-option').forEach(function (lab) {
        const inp = lab.querySelector('[data-price-currency]');
        lab.classList.toggle('is-on', !!(inp && inp.checked));
      });

      if (amountRow) {
        if (fixed) amountRow.removeAttribute('hidden');
        else amountRow.setAttribute('hidden', '');
      }
      if (priceInput) {
        priceInput.disabled = !fixed;
        priceInput.required = fixed;
        if (!fixed) priceInput.value = '';
      }
      if (hint) {
        hint.textContent = fixed ? 'Na sajtu se cena prikazuje u eurima.' : 'Polje za cenu je isključeno.';
      }

      function fmt(n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      function currentAdType() {
        const t = one('[data-form-type]:checked', form);
        return t ? t.value : 'telefon';
      }

      function maxEurForType(adType) {
        if (!amountRow) return 5000;
        const key = 'data-price-max-' + (adType || 'telefon');
        const v = parseFloat(amountRow.getAttribute(key) || '');
        return v > 0 ? v : 5000;
      }

      if (convert) {
        if (!fixed) {
          convert.setAttribute('hidden', '');
          convert.textContent = '';
        } else {
          const curEl = one('[data-price-currency]:checked', form);
          const currency = curEl ? curEl.value : 'eur';
          const amount = parseFloat((priceInput && priceInput.value) || '0');
          const rate = parseFloat((amountRow && amountRow.getAttribute('data-eur-rsd-rate')) || '117') || 117;
          if (!amount || amount <= 0) {
            convert.setAttribute('hidden', '');
            convert.textContent = '';
          } else {
            if (currency === 'eur') {
              convert.textContent = '≈ ' + fmt(amount * rate) + ' din (kurs ' + fmt(rate) + ')';
            } else {
              convert.textContent = '≈ ' + fmt(amount / rate) + ' € (kurs ' + fmt(rate) + ')';
            }
            convert.removeAttribute('hidden');
          }
        }
      }

      if (sanity || confirmWrap) {
        if (!fixed) {
          if (sanity) {
            sanity.setAttribute('hidden', '');
            sanity.textContent = '';
            sanity.classList.remove('is-warn', 'is-error');
          }
          if (confirmWrap) confirmWrap.setAttribute('hidden', '');
          if (confirmCb) {
            confirmCb.checked = false;
            confirmCb.required = false;
          }
        } else {
          const curEl = one('[data-price-currency]:checked', form);
          const currency = curEl ? curEl.value : 'eur';
          const amount = parseFloat((priceInput && priceInput.value) || '0');
          const rate = parseFloat((amountRow && amountRow.getAttribute('data-eur-rsd-rate')) || '117') || 117;
          const warnEur = parseFloat((amountRow && amountRow.getAttribute('data-price-warn-eur')) || '2000') || 2000;
          const adType = currentAdType();
          const maxEur = maxEurForType(adType);
          const eur = amount > 0 ? (currency === 'rsd' ? amount / rate : amount) : 0;

          if (sanity) {
            sanity.classList.remove('is-warn', 'is-error');
            if (eur > maxEur) {
              let msg = 'Cena od ~' + fmt(eur) + ' € je iznad limita (' + fmt(maxEur) + ' €). Proveri iznos i valutu.';
              if (currency === 'eur' && amount >= 10000) {
                msg = 'Cena od ' + fmt(amount) + ' € deluje kao greška — možda si uneo dinare u polje za evre? Maksimum je ' + fmt(maxEur) + ' €.';
              }
              sanity.textContent = msg;
              sanity.classList.add('is-error');
              sanity.removeAttribute('hidden');
            } else if (eur > warnEur) {
              sanity.textContent = 'Cena od ~' + fmt(eur) + ' € izgleda visoko. Potvrdi ispod da nije greška u kucanju.';
              sanity.classList.add('is-warn');
              sanity.removeAttribute('hidden');
            } else {
              sanity.setAttribute('hidden', '');
              sanity.textContent = '';
            }
          }

          if (confirmWrap) {
            if (eur > warnEur && eur <= maxEur) {
              confirmWrap.removeAttribute('hidden');
              if (confirmCb) confirmCb.required = true;
            } else {
              confirmWrap.setAttribute('hidden', '');
              if (confirmCb) {
                confirmCb.checked = false;
                confirmCb.required = false;
              }
            }
          }
        }
      }
    }

    all('[data-price-type]', form).forEach(function (el) {
      el.addEventListener('change', syncPriceUi);
    });
    all('[data-price-currency]', form).forEach(function (el) {
      el.addEventListener('change', syncPriceUi);
    });
    const priceInputLive = one('[data-price-input]', form);
    if (priceInputLive) {
      priceInputLive.addEventListener('input', syncPriceUi);
      priceInputLive.addEventListener('change', syncPriceUi);
    }
    syncPriceUi();

    function wireToggle(toggleSel, panelSel) {
      const toggle = one(toggleSel, form);
      const panel = one(panelSel, form);
      if (!toggle || !panel) return;
      function sync() {
        if (toggle.checked) panel.removeAttribute('hidden');
        else panel.setAttribute('hidden', '');
      }
      toggle.addEventListener('change', sync);
      sync();
    }
    wireToggle('[data-warranty-toggle]', '[data-warranty-months]');
    wireToggle('[data-work-warranty-toggle]', '[data-work-warranty-months]');

    function wireMore(btnSel, panelSel, openLabel, closedLabel) {
      const btn = one(btnSel, form);
      const panel = one(panelSel, form);
      if (!btn || !panel) return;
      btn.addEventListener('click', function () {
        const open = panel.hasAttribute('hidden');
        if (open) panel.removeAttribute('hidden');
        else panel.setAttribute('hidden', '');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? openLabel : closedLabel;
        const typeEl = one('[data-form-type]:checked', form);
        if (typeEl) syncAdFormByType(typeEl.value);
      });
    }
    wireMore('[data-phone-more-toggle]', '[data-phone-more]', 'Manje detalja ▴', 'Više detalja (RAM, BH, oprema…) ▾');
    wireMore('[data-contact-more-toggle]', '[data-contact-more]', 'Manje opcija ▴', 'Dodatne opcije (kontakt…) ▾');

    const err = one('[data-form-error]', form);
    if (err) {
      try { err.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    }

    form.addEventListener('change', function (e) {
      const t = e.target;
      if (!t) return;
      if (t.matches('[data-listing-type], .chip-option input')) {
        const lab = t.closest('.chip-option');
        if (!lab) return;
        const name = t.getAttribute('name');
        if (t.type === 'radio' && name) {
          form.querySelectorAll('.chip-option input[name="' + name + '"]').forEach(function (inp) {
            const l = inp.closest('.chip-option');
            if (l) l.classList.toggle('is-on', inp.checked);
          });
        } else if (t.type === 'checkbox') {
          lab.classList.toggle('is-on', t.checked);
        }
      }
    });

    const drop = one('[data-photo-drop]', form);
    const fileInput = one('[data-photo-input]', form);
    if (drop && fileInput) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
          e.preventDefault();
          drop.classList.add('is-drag');
        });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
          e.preventDefault();
          drop.classList.remove('is-drag');
        });
      });
      drop.addEventListener('drop', function (e) {
        const files = e.dataTransfer && e.dataTransfer.files;
        if (!files || !files.length) return;
        try {
          const dt = new DataTransfer();
          Array.prototype.forEach.call(files, function (f) { dt.items.add(f); });
          fileInput.files = dt.files;
          fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err2) {}
      });
    }
  }

  function initActiveNav() {
    const page = document.body.getAttribute('data-page');
    if (!page) return;
    all('.mobile-bar [data-nav]').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-nav') === page);
    });
  }

  function initAccountMenu() {
    const menu = one('[data-account-menu]');
    const overlay = one('[data-account-menu-overlay]');
    if (!menu || !overlay) return;

    const triggers = all('[data-open-account-menu]');
    const closers = all('[data-close-account-menu]');

    function setExpanded(open) {
      triggers.forEach(function (btn) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    function open() {
      menu.classList.add('open');
      overlay.classList.add('open');
      document.body.classList.add('account-menu-open');
      menu.setAttribute('aria-hidden', 'false');
      setExpanded(true);
    }

    function close() {
      menu.classList.remove('open');
      overlay.classList.remove('open');
      document.body.classList.remove('account-menu-open');
      menu.setAttribute('aria-hidden', 'true');
      setExpanded(false);
    }

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (menu.classList.contains('open')) close();
        else open();
      });
    });
    closers.forEach(function (btn) {
      btn.addEventListener('click', close);
    });
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('open')) close();
    });
  }

  function initAccountTabs() {
    const active = one('.account-tabs a.active');
    if (active && active.scrollIntoView) {
      active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }
  }

  function initAccountQuick() {
    const toggle = one('[data-account-quick-toggle]');
    const panel = one('[data-account-quick-panel]');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', function () {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      const next = !open;
      toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
      if (next) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
    });
  }

  function initPromoPanels() {
    all('[data-promo-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const row = btn.closest('.account-ad-main') || btn.closest('.account-ad-row');
        const panel = row ? one('[data-promo-panel]', row) : null;
        if (!panel) return;
        const open = !panel.hasAttribute('hidden');
        if (open) {
          panel.setAttribute('hidden', '');
          btn.setAttribute('aria-expanded', 'false');
        } else {
          panel.removeAttribute('hidden');
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  function initGallery() {
    const track = one('#gallery-track') || one('.kp-gallery-track');
    const counter = one('[data-gallery-counter]');
    if (!track || !counter) return;

    const slides = all('.kp-gallery-slide', track);
    const thumbs = all('[data-gallery-thumb-index]');
    const prevBtn = one('[data-gallery-prev]');
    const nextBtn = one('[data-gallery-next]');
    const total = slides.length;
    if (!total) return;

    let index = 0;
    let touchStartX = null;

    function paint(idx) {
      index = ((idx % total) + total) % total;
      counter.textContent = (index + 1) + ' od ' + total;
      slides.forEach(function (slide, i) {
        slide.style.display = i === index ? 'flex' : 'none';
      });
      thumbs.forEach(function (thumb) {
        const isOn = parseInt(thumb.getAttribute('data-gallery-thumb-index') || '-1', 10) === index;
        thumb.classList.toggle('is-active', isOn);
        thumb.style.borderColor = isOn ? '#3d9a50' : 'transparent';
        thumb.style.boxShadow = isOn ? '0 0 0 2px rgba(61,154,80,.18)' : 'none';
      });
      window.__ktGalleryNavTs = Date.now();
    }

    track.style.display = 'block';
    track.style.overflow = 'hidden';
    paint(0);

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        const idx = parseInt(thumb.getAttribute('data-gallery-thumb-index') || '0', 10);
        paint(isNaN(idx) ? 0 : idx);
      });
    });

    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        paint(index - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        paint(index + 1);
      });
    }

    track.addEventListener('touchstart', function (e) {
      if (!e.touches || !e.touches[0]) return;
      touchStartX = e.touches[0].clientX;
    }, { passive: true });
    track.addEventListener('touchend', function (e) {
      if (touchStartX === null || !e.changedTouches || !e.changedTouches[0]) return;
      const dx = e.changedTouches[0].clientX - touchStartX;
      touchStartX = null;
      if (Math.abs(dx) < 35) return;
      paint(dx > 0 ? index - 1 : index + 1);
    }, { passive: true });
  }

  function initLightbox() {
    const box = one('[data-lightbox]');
    if (!box) return;
    const imgEl = one('[data-lightbox-img]', box);
    const counterEl = one('[data-lightbox-counter]', box);
    const sourcesEl = one('[data-lightbox-sources]', box);
    let sources = [];
    try {
      sources = JSON.parse((sourcesEl && sourcesEl.textContent) || '[]');
    } catch (e) {
      sources = [];
    }
    if (!imgEl || !sources.length) return;

    let index = 0;

    function show(i) {
      index = (i + sources.length) % sources.length;
      imgEl.src = sources[index];
      if (counterEl) counterEl.textContent = (index + 1) + ' od ' + sources.length;
    }

    function open(i) {
      show(typeof i === 'number' ? i : 0);
      box.removeAttribute('hidden');
      document.body.classList.add('kp-lightbox-open');
    }

    function close() {
      box.setAttribute('hidden', '');
      document.body.classList.remove('kp-lightbox-open');
      imgEl.removeAttribute('src');
    }

    all('[data-lightbox-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        const navTs = window.__ktGalleryNavTs || 0;
        if (Date.now() - navTs < 450) {
          if (e && typeof e.preventDefault === 'function') e.preventDefault();
          if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
          return;
        }
        const i = parseInt(btn.getAttribute('data-lightbox-open') || '0', 10);
        open(isNaN(i) ? 0 : i);
      });
    });

    const closeBtn = one('[data-lightbox-close]', box);
    if (closeBtn) closeBtn.addEventListener('click', close);
    box.addEventListener('click', function (e) {
      if (e.target === box || e.target === one('.kp-lightbox-stage', box)) close();
    });

    const prev = one('[data-lightbox-prev]', box);
    const next = one('[data-lightbox-next]', box);
    if (prev) prev.addEventListener('click', function (e) { e.stopPropagation(); show(index - 1); });
    if (next) next.addEventListener('click', function (e) { e.stopPropagation(); show(index + 1); });

    document.addEventListener('keydown', function (e) {
      if (box.hasAttribute('hidden')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(index - 1);
      if (e.key === 'ArrowRight') show(index + 1);
    });

    let touchX = null;
    box.addEventListener('touchstart', function (e) {
      if (!e.touches || !e.touches[0]) return;
      touchX = e.touches[0].clientX;
    }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (touchX === null || !e.changedTouches || !e.changedTouches[0]) return;
      const dx = e.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(dx) < 50) return;
      if (dx > 0) show(index - 1);
      else show(index + 1);
    }, { passive: true });
  }

  function initPhoneReveal() {
    document.addEventListener('click', function (e) {
      var link = e.target && e.target.closest ? e.target.closest('a[data-track-phone]') : null;
      if (!link) return;
      var adId = link.getAttribute('data-ad-id') || '';
      if (!adId) return;
      try {
        var body = new FormData();
        body.append('ad_id', adId);
        appendCsrf(body);
        fetch('/api/track.php', {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: csrfHeaders(),
          keepalive: true
        });
      } catch (err) {}
    });

    all('[data-reveal-phone]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const phone = btn.getAttribute('data-reveal-phone') || '';
        if (!phone) return;
        if (btn.tagName === 'BUTTON' || btn.classList.contains('btn-phone-reveal') || btn.classList.contains('btn-call') || btn.classList.contains('kp-btn-tel')) {
          if (btn.classList.contains('revealed') || btn.getAttribute('data-revealed') === '1') {
            window.location.href = 'tel:' + phone.replace(/\s+/g, '');
            return;
          }
          btn.textContent = phone;
          btn.classList.add('revealed');
          btn.setAttribute('data-revealed', '1');
          const adId = btn.getAttribute('data-ad-id')
            || ((btn.closest('[data-ad-id]') || document.querySelector('[data-ad-id]') || {}).getAttribute
              ? (btn.closest('[data-ad-id]') || document.querySelector('[data-ad-id]')).getAttribute('data-ad-id')
              : '');
          if (adId) {
            try {
              const body = new FormData();
              body.append('ad_id', adId);
              appendCsrf(body);
              fetch('/api/track.php', { method: 'POST', body: body, credentials: 'same-origin', headers: csrfHeaders() });
            } catch (e) {}
          }
        }
      });
    });
  }

  function updateCompareBar(count) {
    const bar = one('[data-compare-bar]');
    const labelCount = one('[data-compare-count]');
    if (labelCount) labelCount.textContent = String(count);
    if (bar) {
      if (count > 0) bar.removeAttribute('hidden');
      else bar.setAttribute('hidden', 'hidden');
    }
    document.body.setAttribute('data-compare-count', String(count));
  }

  function initCompare() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-compare-toggle]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const adId = btn.getAttribute('data-compare-toggle');
      const body = new FormData();
      body.append('ad_id', adId);
      body.append('ajax', '1');
      appendCsrf(body);
      fetch('/uporedi.php', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: csrfHeaders({ 'Accept': 'application/json' })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) return;
        if (data.full) {
          alert('Možeš uporediti najviše 3 oglasa. Otvori poređenje da ukloniš neki.');
          window.location.href = '/uporedi.php';
          return;
        }
        // Dodat u poređenje → odmah na stranicu poređenja
        if (data.added) {
          window.location.href = '/uporedi.php';
          return;
        }
        // Uklonjen sa liste — ostani na stranici
        all('[data-compare-toggle="' + adId + '"]').forEach(function (el) {
          el.classList.remove('active', 'is-in-compare');
          el.setAttribute('aria-pressed', 'false');
          if (el.classList.contains('listing-compare-btn') || el.classList.contains('kp-list-cmp')) {
            el.textContent = '⇄';
          } else if (el.tagName === 'BUTTON') {
            el.textContent = '⇄ Uporedi';
          }
        });
        updateCompareBar(data.count || 0);
      }).catch(function () {
        // Fallback bez JS JSON: klasični POST redirect
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/uporedi.php';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ad_id';
        input.value = adId;
        form.appendChild(input);
        const redir = document.createElement('input');
        redir.type = 'hidden';
        redir.name = 'redirect';
        redir.value = '/uporedi.php';
        form.appendChild(redir);
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  function initShareAd() {
    all('[data-share-ad]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const url = btn.getAttribute('data-share-url') || window.location.href;
        const title = btn.getAttribute('data-share-title') || document.title;
        // Nativni Android share sheet u APK-u
        try {
          if (isNativeApp() && window.KtNative && typeof window.KtNative.share === 'function') {
            window.KtNative.share(title, url);
            return;
          }
        } catch (e) {}
        if (navigator.share) {
          navigator.share({ title: title, url: url }).catch(function () {});
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(function () {
            const prev = btn.textContent;
            btn.textContent = 'Kopirano';
            setTimeout(function () { btn.textContent = prev; }, 1500);
          });
        }
      });
    });
    all('[data-fb-card]').forEach(function (link) {
      link.addEventListener('click', function () {
        const caption = link.getAttribute('data-fb-caption') || '';
        if (!caption) return;
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(caption).then(function () {
              const prev = link.textContent;
              link.textContent = 'Slika + tekst kopiran';
              setTimeout(function () { link.textContent = prev; }, 1800);
            }).catch(function () {});
          }
        } catch (e) {}
      });
    });
  }

  function initPhotoPreview() {
    const input = one('[data-photo-input]');
    const preview = one('[data-photo-preview]');
    const drop = one('[data-photo-drop]');
    if (!input || !preview) return;

    const MAX_TOTAL = 20;
    const MAX_EDGE = 1600;
    const JPEG_QUALITY = 0.82;
    const WARN_RAW_MB = 20;
    const HARD_REJECT_MB = 40;

    /** @type {File[]} */
    let selectedFiles = [];
    /** @type {string[]} */
    let previewUrls = [];
    let syncingInput = false;
    let busy = false;

    function keptExistingCount() {
      return all('[data-photo-existing] [data-photo-item]').length;
    }

    function remainingSlots() {
      return Math.max(0, MAX_TOTAL - keptExistingCount() - selectedFiles.length);
    }

    function updateAddState() {
      if (!drop) return;
      const full = remainingSlots() <= 0;
      drop.classList.toggle('is-full', full);
      if (!busy) input.disabled = full;
      const selectedType = one('[data-form-type]:checked');
      syncPhonePhotoRequirement(input.form, selectedType ? selectedType.value : '');
    }

    function syncInput() {
      const dt = new DataTransfer();
      selectedFiles.forEach(function (f) { dt.items.add(f); });
      syncingInput = true;
      try { input.files = dt.files; } catch (e) {}
      syncingInput = false;
      renderPreview();
      updateAddState();
    }

    function revokePreviews() {
      previewUrls.forEach(function (u) {
        try { URL.revokeObjectURL(u); } catch (e) {}
      });
      previewUrls = [];
    }

    function loadImage(file) {
      return new Promise(function (resolve, reject) {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = function () {
          URL.revokeObjectURL(url);
          resolve(img);
        };
        img.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('unsupported'));
        };
        img.src = url;
      });
    }

    function canvasToBlob(canvas, type, quality) {
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) { resolve(blob); }, type, quality);
      });
    }

    function isHeicFile(file) {
      if (!file) return false;
      const t = (file.type || '').toLowerCase();
      const n = (file.name || '').toLowerCase();
      return t === 'image/heic' || t === 'image/heif' || /\.heic$|\.heif$/.test(n);
    }

    function loadHeic2Any() {
      return new Promise(function (resolve, reject) {
        if (window.heic2any) {
          resolve(window.heic2any);
          return;
        }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js';
        s.onload = function () {
          if (window.heic2any) resolve(window.heic2any);
          else reject(new Error('heic'));
        };
        s.onerror = function () { reject(new Error('heic')); };
        document.head.appendChild(s);
      });
    }

    async function maybeConvertHeic(file) {
      if (!isHeicFile(file)) return file;
      try {
        const heic2any = await loadHeic2Any();
        const blob = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.82 });
        const outBlob = Array.isArray(blob) ? blob[0] : blob;
        const base = (file.name || 'photo').replace(/\.[^.]+$/, '');
        return new File([outBlob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
      } catch (e) {
        return null;
      }
    }

    async function compressFile(file) {
      if (!file) return { file: file, compressed: false, error: null };
      if (isHeicFile(file)) {
        const converted = await maybeConvertHeic(file);
        if (!converted) return { file: file, compressed: false, error: 'unsupported' };
        file = converted;
      }
      if (!file.type || file.type.indexOf('image/') !== 0) {
        return { file: file, compressed: false, error: null };
      }
      if (file.size < 900 * 1024 && file.type === 'image/jpeg') {
        return { file: file, compressed: false, error: null };
      }
      try {
        const img = await loadImage(file);
        let w = img.naturalWidth || img.width;
        let h = img.naturalHeight || img.height;
        if (!w || !h) {
          return { file: file, compressed: false, error: 'read' };
        }
        const scale = Math.min(1, MAX_EDGE / Math.max(w, h));
        const nw = Math.max(1, Math.round(w * scale));
        const nh = Math.max(1, Math.round(h * scale));
        const canvas = document.createElement('canvas');
        canvas.width = nw;
        canvas.height = nh;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, nw, nh);
        ctx.drawImage(img, 0, 0, nw, nh);
        let blob = await canvasToBlob(canvas, 'image/jpeg', JPEG_QUALITY);
        if (!blob) {
          return { file: file, compressed: false, error: 'encode' };
        }
        if (blob.size > 2.5 * 1024 * 1024) {
          blob = await canvasToBlob(canvas, 'image/jpeg', 0.7) || blob;
        }
        if (blob.size > 4 * 1024 * 1024) {
          blob = await canvasToBlob(canvas, 'image/jpeg', 0.55) || blob;
        }
        const base = (file.name || 'photo').replace(/\.[^.]+$/, '');
        const out = new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        return { file: out, compressed: out.size < file.size, error: null, originalMb: file.size / (1024 * 1024) };
      } catch (err) {
        return { file: file, compressed: false, error: 'unsupported' };
      }
    }

    function renderPreview() {
      revokePreviews();
      preview.innerHTML = '';
      const hasExisting = keptExistingCount() > 0;
      selectedFiles.forEach(function (file, index) {
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        const slot = document.createElement('div');
        slot.className = 'photo-slot';
        slot.setAttribute('data-new-photo-item', String(index));

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'photo-slot-remove';
        removeBtn.setAttribute('data-new-photo-remove', '1');
        removeBtn.setAttribute('aria-label', 'Ukloni sliku');
        removeBtn.textContent = '×';

        const img = document.createElement('img');
        img.src = url;
        img.alt = '';

        const meta = document.createElement('div');
        meta.className = 'photo-slot-meta';
        const badge = document.createElement('span');
        badge.className = 'photo-slot-badge';
        badge.textContent = (!hasExisting && index === 0) ? 'Naslovna' : ('#' + (keptExistingCount() + index + 1));

        const reorder = document.createElement('div');
        reorder.className = 'photo-reorder';
        reorder.innerHTML =
          '<button type="button" class="btn-sm" data-new-photo-up title="Gore">↑</button>' +
          '<button type="button" class="btn-sm" data-new-photo-down title="Dole">↓</button>';

        meta.appendChild(badge);
        meta.appendChild(reorder);
        slot.appendChild(removeBtn);
        slot.appendChild(img);
        slot.appendChild(meta);
        preview.appendChild(slot);
      });
    }

    function ensureHint() {
      let hint = one('[data-photo-compress-hint]');
      if (hint) return hint;
      hint = document.createElement('p');
      hint.className = 'form-hint';
      hint.setAttribute('data-photo-compress-hint', '1');
      hint.style.marginTop = '8px';
      const wrap = input.closest('.ad-form-section') || input.parentElement;
      if (wrap) wrap.appendChild(hint);
      return hint;
    }

    function moveSelected(index, dir) {
      const next = index + dir;
      if (next < 0 || next >= selectedFiles.length) return;
      const tmp = selectedFiles[index];
      selectedFiles[index] = selectedFiles[next];
      selectedFiles[next] = tmp;
      syncInput();
    }

    preview.addEventListener('click', function (e) {
      const slot = e.target.closest('[data-new-photo-item]');
      if (!slot) return;
      const index = parseInt(slot.getAttribute('data-new-photo-item') || '-1', 10);
      if (isNaN(index) || index < 0) return;

      if (e.target.closest('[data-new-photo-remove]')) {
        e.preventDefault();
        selectedFiles.splice(index, 1);
        syncInput();
        const hint = one('[data-photo-compress-hint]');
        if (hint && !selectedFiles.length) hint.textContent = '';
        return;
      }
      if (e.target.closest('[data-new-photo-up]')) {
        e.preventDefault();
        moveSelected(index, -1);
        return;
      }
      if (e.target.closest('[data-new-photo-down]')) {
        e.preventDefault();
        moveSelected(index, 1);
      }
    });

    async function addIncomingFiles(fileList) {
      const incoming = Array.from(fileList || []);
      if (!incoming.length || busy) return;

      let room = remainingSlots();
      if (room <= 0) {
        const hint = ensureHint();
        hint.style.color = '#b45309';
        hint.textContent = 'Već imaš ' + MAX_TOTAL + ' slika. Obriši neku da dodaš novu.';
        syncInput();
        return;
      }

      const hint = ensureHint();
      hint.style.color = 'var(--text-muted)';
      hint.textContent = 'Smanjujem fotografije pre slanja…';
      busy = true;
      input.disabled = true;

      const notes = [];
      let rejected = 0;
      let added = 0;

      for (let i = 0; i < incoming.length; i++) {
        if (added >= room) {
          notes.push('Dodato je maksimum od ' + MAX_TOTAL + ' slika.');
          break;
        }
        const raw = incoming[i];
        const rawMb = raw.size / (1024 * 1024);
        if (rawMb > HARD_REJECT_MB) {
          rejected++;
          notes.push((raw.name || 'slika') + ' je prevelika (>' + HARD_REJECT_MB + ' MB) i nije dodata.');
          continue;
        }
        if (rawMb > WARN_RAW_MB) {
          notes.push((raw.name || 'slika') + ' je bila ' + rawMb.toFixed(1) + ' MB — smanjujem automatski.');
        }
        const result = await compressFile(raw);
        if (result.error === 'unsupported') {
          notes.push((raw.name || 'slika') + ' nije podržana (npr. HEIC). Sačuvaj kao JPG/PNG.');
          rejected++;
          continue;
        }
        if (result.file.size > 8 * 1024 * 1024) {
          notes.push((raw.name || 'slika') + ' i posle smanjenja je prevelika. Probaj drugu fotografiju.');
          rejected++;
          continue;
        }
        selectedFiles.push(result.file);
        added++;
      }

      busy = false;
      syncInput();

      if (!added && !selectedFiles.length) {
        hint.style.color = '#b91c1c';
        hint.textContent = notes.join(' ') || 'Nijedna fotografija nije dodata.';
        return;
      }

      const parts = [];
      if (notes.length) parts.push(notes.join(' '));
      parts.push('Ukupno novih: ' + selectedFiles.length + '. Možeš dodati još ili ↑↓ / × za redosled i brisanje.');
      if (rejected > 0) {
        hint.style.color = '#b45309';
      } else {
        hint.style.color = 'var(--kp-green-dark, #15803d)';
      }
      hint.textContent = parts.join(' ');
    }

    input.addEventListener('change', async function () {
      if (syncingInput || busy) return;
      const incoming = Array.from(input.files || []);
      // Picker šalje SAMO novoizabrane — prazan change (cancel) ne briše prethodne
      if (!incoming.length) {
        syncInput();
        return;
      }
      await addIncomingFiles(incoming);
    });

    const cameraInput = one('[data-photo-camera]', input.form || document);
    if (cameraInput) {
      cameraInput.addEventListener('change', async function () {
        if (busy) return;
        const incoming = Array.from(cameraInput.files || []);
        cameraInput.value = '';
        if (!incoming.length) return;
        await addIncomingFiles(incoming);
      });
    }

    // Kad se obriše postojeća slika, otključaj "Dodaj" ako ima mesta
    const existing = one('[data-photo-existing]');
    if (existing) {
      existing.addEventListener('click', function () {
        setTimeout(updateAddState, 0);
      });
    }

    updateAddState();
  }

  function initPhotoReorder() {
    const list = one('[data-photo-existing]');
    if (!list) return;

    function move(item, dir) {
      if (dir < 0 && item.previousElementSibling) {
        list.insertBefore(item, item.previousElementSibling);
      } else if (dir > 0 && item.nextElementSibling) {
        list.insertBefore(item.nextElementSibling, item);
      }
    }

    list.addEventListener('click', function (e) {
      const remove = e.target.closest('[data-photo-remove]');
      if (remove) {
        e.preventDefault();
        const item = remove.closest('[data-photo-item]');
        if (!item) return;
        const wasCover = !!(one('input[name="cover_image"]:checked', item));
        item.remove();
        if (wasCover) {
          const first = one('[data-photo-item] input[name="cover_image"]', list);
          if (first) first.checked = true;
        }
        return;
      }

      const up = e.target.closest('[data-photo-up]');
      const down = e.target.closest('[data-photo-down]');
      if (!up && !down) return;
      e.preventDefault();
      const item = e.target.closest('[data-photo-item]');
      if (!item) return;
      move(item, up ? -1 : 1);
    });
  }

  function initSearchSuggest() {
    const form = one('[data-search-form]');
    const input = one('[data-search-input]');
    const box = one('[data-search-suggest]');
    if (!form || !input || !box) return;

    let timer = null;
    let items = [];
    let active = -1;
    let ctrl = null;
    let navigated = false;

    function hide() {
      box.hidden = true;
      box.innerHTML = '';
      items = [];
      active = -1;
      navigated = false;
      input.setAttribute('aria-expanded', 'false');
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function render() {
      if (!items.length) {
        hide();
        return;
      }
      box.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      box.innerHTML = items.map(function (item, idx) {
        const typeLabel = ({ brand: 'Brend', city: 'Grad', model: 'Model', ad: 'Oglas', search: 'Pretraga' })[item.type] || '';
        return '<button type="button" class="search-suggest-item' + (idx === active ? ' is-active' : '') + '" data-suggest-idx="' + idx + '" role="option">' +
          '<span><strong>' + escapeHtml(item.label || '') + '</strong><small>' + escapeHtml(item.sub || '') + '</small></span>' +
          '<span class="search-suggest-type">' + escapeHtml(typeLabel) + '</span></button>';
      }).join('');
    }

    function go(idx) {
      const item = items[idx];
      if (!item || !item.url) return;
      window.location.href = item.url;
    }

    function fetchSuggest(q) {
      if (ctrl) ctrl.abort();
      ctrl = new AbortController();
      fetch('/api/search.php?q=' + encodeURIComponent(q) + '&limit=8', {
        signal: ctrl.signal,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) {
          if (!r.ok) throw new Error('search failed');
          return r.json();
        })
        .then(function (data) {
          items = Array.isArray(data.suggestions) ? data.suggestions : [];
          active = -1;
          navigated = false;
          render();
        })
        .catch(function () { /* ignore abort/errors */ });
    }

    input.addEventListener('input', function () {
      const q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 1) {
        hide();
        return;
      }
      timer = setTimeout(function () { fetchSuggest(q); }, 160);
    });

    input.addEventListener('keydown', function (e) {
      if (box.hidden || !items.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        navigated = true;
        active = active < 0 ? 0 : (active + 1) % items.length;
        render();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        navigated = true;
        active = active < 0 ? items.length - 1 : (active - 1 + items.length) % items.length;
        render();
      } else if (e.key === 'Enter') {
        // Enter otvara predlog samo ako je korisnik birao strelicama; inače obična pretraga
        if (navigated && active >= 0) {
          e.preventDefault();
          go(active);
        }
      } else if (e.key === 'Escape') {
        hide();
      }
    });

    box.addEventListener('mousedown', function (e) {
      const btn = e.target.closest('[data-suggest-idx]');
      if (!btn) return;
      e.preventDefault();
      go(parseInt(btn.getAttribute('data-suggest-idx') || '-1', 10));
    });

    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) hide();
    });
  }

  function absoluteUrl(path) {
    if (!path) return window.location.href;
    if (/^https?:\/\//i.test(path)) return path;
    return window.location.origin + path;
  }

  function initCopyLink() {
    all('[data-copy-link]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const path = btn.getAttribute('data-copy-url') || '';
        const fullInput = one('[data-copy-full]');
        const text = (fullInput && fullInput.value) ? fullInput.value : absoluteUrl(path);
        const done = function () {
          const prev = btn.textContent;
          btn.textContent = 'Kopirano!';
          btn.classList.add('btn-copy-done');
          setTimeout(function () {
            btn.textContent = prev;
            btn.classList.remove('btn-copy-done');
          }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () {
            if (fullInput) { fullInput.select(); document.execCommand('copy'); done(); }
          });
        } else if (fullInput) {
          fullInput.select();
          document.execCommand('copy');
          done();
        }
      });
    });
  }

  function initShopLinks() {
    all('[data-shop-url]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const url = el.getAttribute('data-shop-url');
        if (url) window.location.href = url;
      });
    });
  }

  function initMsgToast() {
    const toast = one('[data-msg-toast]');
    if (!toast) return;
    const closeBtn = one('[data-msg-toast-close]', toast);
    if (closeBtn) {
      closeBtn.addEventListener('click', function () { toast.remove(); });
    }
    setTimeout(function () {
      if (toast.parentNode) toast.remove();
    }, 8000);
  }

  function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text == null ? '' : String(text);
    return d.innerHTML;
  }

  function nl2brSafe(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
  }

  function syncAppIconBadge(count) {
    if (!isNativeApp()) return;
    var n = Math.max(0, parseInt(count, 10) || 0);
    try {
      var Badge = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Badge;
      if (!Badge) return;
      if (n > 0) {
        Badge.set({ count: n }).catch(function () {});
      } else {
        Badge.clear().catch(function () {});
        var Push = pushPlugin();
        if (Push && typeof Push.removeAllDeliveredNotifications === 'function') {
          Push.removeAllDeliveredNotifications().catch(function () {});
        }
      }
    } catch (e) {}
  }

  function setBadgeOnEl(el, n, labelSuffix) {
    if (!el) return;
    let badge = one('.notif-badge', el);
    if (n <= 0) {
      if (badge) badge.remove();
      return;
    }
    const label = n > 99 ? '99+' : String(n);
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'notif-badge';
      el.appendChild(badge);
    }
    badge.textContent = label;
    badge.setAttribute('aria-label', label + ' ' + (labelSuffix || 'nepročitanih'));
  }

  function refreshMenuCombinedBadge() {
    const msgs = parseInt(document.body.getAttribute('data-unread-messages') || '0', 10) || 0;
    const notifs = parseInt(document.body.getAttribute('data-unread-notifications') || '0', 10) || 0;
    setBadgeOnEl(one('[data-open-account-menu]'), msgs + notifs, 'nepročitanih');
  }

  function updateNotificationBadges(count) {
    const n = Math.max(0, parseInt(count, 10) || 0);
    document.body.setAttribute('data-unread-notifications', String(n));
    all('.nav-with-badge').forEach(function (link) {
      const href = link.getAttribute('href') || '';
      if (link.hasAttribute('data-open-account-menu')) return;
      if (/poruke/i.test(href)) return;
      if (!/nalog\.php/i.test(href) && !/obavestenja/i.test(href)) return;
      setBadgeOnEl(link, n, 'nepročitanih obaveštenja');
    });
    refreshMenuCombinedBadge();
  }

  function updateUnreadBadges(count, notifCount) {
    const n = Math.max(0, parseInt(count, 10) || 0);
    document.body.setAttribute('data-unread-messages', String(n));
    all('.nav-with-badge').forEach(function (link) {
      if (!/poruke/i.test(link.getAttribute('href') || '')) return;
      setBadgeOnEl(link, n, 'nepročitanih poruka');
    });
    const inboxLabel = one('.inbox-unread-label');
    if (inboxLabel) {
      if (n > 0) {
        inboxLabel.textContent = n + ' nepročitanih';
        inboxLabel.style.display = '';
      } else {
        inboxLabel.style.display = 'none';
      }
    }
    if (typeof notifCount === 'number') {
      updateNotificationBadges(notifCount);
    } else {
      refreshMenuCombinedBadge();
    }
    syncAppIconBadge(n);
  }

  function applyUnreadFromApi(data) {
    if (!data || typeof data.unread !== 'number') return;
    var notifs = typeof data.unread_notifications === 'number' ? data.unread_notifications : undefined;
    updateUnreadBadges(data.unread, notifs);
  }

  function appendChatBubble(thread, msg) {
    if (!thread || !msg || !msg.id) return;
    if (one('[data-msg-id="' + msg.id + '"]', thread)) return;
    const empty = one('[data-chat-empty]', thread);
    if (empty) empty.remove();
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + (msg.mine ? 'mine' : 'theirs');
    bubble.setAttribute('data-msg-id', String(msg.id));
    bubble.innerHTML = '<div class="chat-bubble-body">' + nl2brSafe(msg.body) + '</div>'
      + '<div class="chat-bubble-meta">' + escapeHtml(msg.relative || '') + '</div>';
    thread.appendChild(bubble);
    thread.scrollTop = thread.scrollHeight;
    if (!msg.mine) {
      bubble.classList.add('chat-bubble-new');
      setTimeout(function () { bubble.classList.remove('chat-bubble-new'); }, 1200);
    }
  }

  function initLiveChat() {
    const form = one('[data-live-chat]');
    const thread = one('[data-chat-thread]');
    if (!form || !thread) return;

    let lastId = parseInt(form.getAttribute('data-last-id') || '0', 10) || 0;
    const adId = form.getAttribute('data-ad-id');
    const withId = form.getAttribute('data-with-id');
    const input = one('[data-chat-input]', form);
    const sendBtn = one('[data-chat-send]', form);
    const status = one('[data-chat-status]');
    let polling = false;
    let timer = null;

    function setStatus(text, isError) {
      if (!status) return;
      status.textContent = text;
      status.classList.toggle('is-error', !!isError);
    }

    function poll() {
      if (polling || document.hidden) return;
      polling = true;
      fetch('/api/messages.php?action=thread&ad=' + encodeURIComponent(adId) + '&with=' + encodeURIComponent(withId) + '&after_id=' + lastId, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;
          (data.messages || []).forEach(function (msg) {
            appendChatBubble(thread, msg);
            if (msg.id > lastId) lastId = msg.id;
          });
          form.setAttribute('data-last-id', String(lastId));
          applyUnreadFromApi(data);
          setStatus('Uživo · nove poruke stižu automatski', false);
        })
        .catch(function () {
          setStatus('Veza usporena · pokušavam ponovo…', true);
        })
        .finally(function () { polling = false; });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const body = (input && input.value || '').trim();
      if (!body) return;
      if (sendBtn) sendBtn.disabled = true;
      const fd = new FormData();
      fd.append('action', 'send');
      fd.append('ad_id', adId);
      fd.append('to_user_id', withId);
      fd.append('message', body);
      appendCsrf(fd);

      fetch('/api/messages.php?action=send', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
        headers: csrfHeaders({ 'Accept': 'application/json' })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) {
            setStatus('Slanje nije uspelo.', true);
            return;
          }
          if (input) input.value = '';
          appendChatBubble(thread, data.message);
          if (data.message && data.message.id > lastId) lastId = data.message.id;
          form.setAttribute('data-last-id', String(lastId));
          applyUnreadFromApi(data);
          setStatus('Poslato · uživo', false);
        })
        .catch(function () {
          setStatus('Greška pri slanju.', true);
        })
        .finally(function () {
          if (sendBtn) sendBtn.disabled = false;
          if (input) input.focus();
        });
    });

    // Enter = pošalji, Shift+Enter = novi red
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.shiftKey) return;
        if (e.isComposing || e.keyCode === 229) return;
        e.preventDefault();
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
      });
    }

    poll();
    timer = setInterval(poll, 2000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) poll();
    });
    window.addEventListener('beforeunload', function () {
      if (timer) clearInterval(timer);
    });
  }

  function initUnreadPolling() {
    if (!document.body || !document.body.hasAttribute('data-unread-messages')) return;
    let timer = null;
    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }
    function tick() {
      if (document.hidden) return;
      // Skip if live chat already polls unread
      if (one('[data-live-chat]')) return;
      fetch('/api/messages.php?action=unread', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) {
          if (r.status === 401 || r.status === 403) {
            stop();
            return null;
          }
          return r.json();
        })
        .then(function (data) {
          if (!data) return;
          if (data && data.ok && typeof data.unread === 'number') {
            const prev = parseInt(document.body.getAttribute('data-unread-messages') || '0', 10) || 0;
            applyUnreadFromApi(data);
            if (data.unread > prev && !one('[data-msg-toast]') && data.unread > 0) {
              showLiveToast(data.unread);
            }
          }
        })
        .catch(function () {});
    }
    timer = setInterval(tick, 5000);
    window.addEventListener('beforeunload', stop);
  }

  function showLiveToast(count) {
    const existing = one('[data-msg-toast]');
    if (existing) existing.remove();
    const el = document.createElement('div');
    el.className = 'msg-toast';
    el.setAttribute('data-msg-toast', '1');
    el.innerHTML = '<div class="msg-toast-inner">'
      + '<strong>Nova poruka' + (count > 1 ? 'e' : '') + '</strong>'
      + '<span>Imaš ' + count + ' nepročitanih poruka.</span>'
      + '<a href="/poruke.php">Otvori inbox</a>'
      + '<button type="button" class="msg-toast-close" data-msg-toast-close aria-label="Zatvori">×</button>'
      + '</div>';
    document.body.appendChild(el);
    const closeBtn = one('[data-msg-toast-close]', el);
    if (closeBtn) closeBtn.addEventListener('click', function () { el.remove(); });
    setTimeout(function () { if (el.parentNode) el.remove(); }, 8000);
  }

  function initLiveInbox() {
    const list = one('[data-live-inbox]');
    if (!list) return;
    let timer = null;
    function renderThreads(threads) {
      if (!threads || !threads.length) {
        list.innerHTML = '';
        return;
      }
      list.innerHTML = threads.map(function (t) {
        const initial = (t.partner_name || '?').charAt(0).toUpperCase();
        const badge = t.unread > 0
          ? '<span class="notif-badge" aria-label="' + t.unread + ' nepročitanih">' + (t.unread > 99 ? '99+' : t.unread) + '</span>'
          : '';
        return '<a class="msg-item ' + (t.unread > 0 ? 'unread' : '') + '" href="/poruke.php?ad=' + t.ad_id + '&with=' + t.partner_id + '" data-thread-key="' + escapeHtml(t.key) + '">'
          + '<div class="msg-avatar">' + escapeHtml(initial) + '</div>'
          + '<div class="msg-preview"><strong>' + escapeHtml(t.partner_name) + ' <span data-thread-badge>' + badge + '</span></strong>'
          + '<span class="msg-ad">' + escapeHtml(t.ad_title) + '</span>'
          + '<span data-thread-preview>' + escapeHtml(t.last_body) + '</span></div>'
          + '<div class="msg-time" data-thread-time>' + escapeHtml(t.relative) + '</div></a>';
      }).join('');
    }
    function pollInbox() {
      if (document.hidden) return;
      fetch('/api/messages.php?action=threads', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;
          renderThreads(data.threads || []);
          applyUnreadFromApi(data);
        })
        .catch(function () {});
    }
    timer = setInterval(pollInbox, 4000);
    window.addEventListener('beforeunload', function () {
      if (timer) clearInterval(timer);
    });
  }

  function initChatScroll() {
    const thread = one('[data-chat-thread]');
    if (thread) thread.scrollTop = thread.scrollHeight;
  }

  /**
   * Android/iOS WebView: tastatura često overlay-uje sticky chat polje.
   * Merimo visualViewport i dižemo compose iznad tastature.
   */
  function initKeyboardInset() {
    var root = document.documentElement;
    var lastInset = -1;
    var timer = null;

    function keepChatVisible() {
      if (!document.body || !document.body.classList.contains('page-chat')) return;
      var input = one('[data-chat-input]');
      if (!input || document.activeElement !== input) return;
      try {
        input.scrollIntoView({ block: 'nearest', behavior: 'auto' });
      } catch (e) {}
      var thread = one('[data-chat-thread]');
      if (thread) thread.scrollTop = thread.scrollHeight;
    }

    function apply() {
      var inset = 0;
      if (window.visualViewport) {
        inset = Math.max(
          0,
          Math.round(window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop)
        );
      }
      // Male oscilacije (npr. 1–8px) ignoriši
      if (inset < 40) inset = 0;
      if (inset === lastInset) return;
      lastInset = inset;
      root.style.setProperty('--keyboard-inset', inset + 'px');
      root.classList.toggle('kb-open', inset > 0);
      if (inset > 0) keepChatVisible();
    }

    function schedule() {
      if (timer) cancelAnimationFrame(timer);
      timer = requestAnimationFrame(apply);
    }

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', schedule);
      window.visualViewport.addEventListener('scroll', schedule);
    }
    window.addEventListener('resize', schedule);
    document.addEventListener('focusin', function (e) {
      var t = e.target;
      if (!t || !t.matches) return;
      if (t.matches('input, textarea, [contenteditable="true"]')) {
        setTimeout(schedule, 80);
        setTimeout(schedule, 320);
      }
    });
    document.addEventListener('focusout', function () {
      setTimeout(schedule, 80);
    });
    apply();
  }

  function initRatingsTabs() {
    const tabs = all('[data-ratings-tab]');
    const list = one('[data-ratings-list]');
    if (!tabs.length || !list) return;

    const items = all('.rating-item[data-vote]', list);
    const emptyHint = one('[data-ratings-empty]');

    function activate(whichTab) {
      const filter = whichTab === 'positive' || whichTab === 'negative' ? whichTab : 'all';
      tabs.forEach(function (tab) {
        tab.classList.toggle('active', tab.getAttribute('data-ratings-tab') === filter);
      });
      var visible = 0;
      items.forEach(function (item) {
        var vote = item.getAttribute('data-vote') || '';
        var show = filter === 'all' || vote === filter;
        item.hidden = !show;
        if (show) visible += 1;
      });
      if (emptyHint) emptyHint.hidden = visible > 0;
    }

    function fromHash() {
      const hash = (window.location.hash || '').replace('#', '');
      if (hash === 'ocene-positive') activate('positive');
      else if (hash === 'ocene-negative') activate('negative');
      else activate('all');
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var which = tab.getAttribute('data-ratings-tab') || 'all';
        activate(which);
        var targetHash = which === 'positive' ? '#ocene-positive'
          : (which === 'negative' ? '#ocene-negative' : '#ocene');
        if (history.replaceState) {
          history.replaceState(null, '', targetHash);
        } else {
          window.location.hash = targetHash;
        }
      });
    });

    fromHash();
    window.addEventListener('hashchange', fromHash);
  }

  function initAdsView() {
    const list = one('[data-listings]');
    const toggle = one('[data-view-toggle]');
    if (!list || !toggle) return;

    const key = 'tb_ads_view';

    function apply(view) {
      const mode = view === 'grid' ? 'grid' : 'list';
      list.classList.toggle('view-grid', mode === 'grid');
      list.classList.toggle('view-list', mode === 'list');
      all('[data-view]', toggle).forEach(function (btn) {
        const active = btn.getAttribute('data-view') === mode;
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      try { localStorage.setItem(key, mode); } catch (e) {}
    }

    let saved = 'list';
    try { saved = localStorage.getItem(key) || 'list'; } catch (e) {}
    apply(saved);

    toggle.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-view]');
      if (!btn) return;
      apply(btn.getAttribute('data-view'));
    });
  }

  function isNativeApp() {
    try {
      return !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());
    } catch (e) {
      return false;
    }
  }

  function pushPlugin() {
    try {
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.PushNotifications) {
        return window.Capacitor.Plugins.PushNotifications;
      }
    } catch (e) {}
    return null;
  }

  function registerPushTokenOnServer(token) {
    if (!token || !document.body || !document.body.getAttribute('data-logged-in')) return;
    var fd = new FormData();
    fd.append('action', 'register');
    fd.append('token', token);
    fd.append('platform', 'android');
    appendCsrf(fd);
    fetch('/api/push_token.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: csrfHeaders()
    }).catch(function () {});
  }

  function openPushLink(link) {
    if (!link) return;
    try {
      if (/^https?:\/\//i.test(link)) {
        var u = new URL(link, window.location.origin);
        if (u.origin === window.location.origin) {
          window.location.href = u.pathname + u.search + u.hash;
          return;
        }
      }
      if (link.charAt(0) === '/') {
        window.location.href = link;
        return;
      }
      window.location.href = link;
    } catch (e) {
      window.location.href = link;
    }
  }

  function initNativeChrome() {
    if (!isNativeApp()) return;
    try {
      document.documentElement.classList.add('kt-native-app');
      document.body.classList.add('kt-native-app');
    } catch (e) {}

    var StatusBar = null;
    var SplashScreen = null;
    try {
      StatusBar = window.Capacitor.Plugins.StatusBar || null;
      SplashScreen = window.Capacitor.Plugins.SplashScreen || null;
    } catch (e) {}

    if (StatusBar) {
      Promise.resolve()
        .then(function () { return StatusBar.setStyle({ style: 'DARK' }); })
        .then(function () { return StatusBar.setBackgroundColor({ color: '#ffffff' }); })
        .catch(function () {});
    }
    if (SplashScreen && typeof SplashScreen.hide === 'function') {
      setTimeout(function () {
        SplashScreen.hide({ fadeOutDuration: 280 }).catch(function () {});
      }, 350);
    }
  }

  function initPushNotifications() {
    if (!isNativeApp()) return;
    var Push = pushPlugin();
    if (!Push) return;

    var loggedIn = !!(document.body && document.body.getAttribute('data-logged-in'));

    function handlePushOpen(event) {
      var n = (event && event.notification) || {};
      var data = n.data || event.data || {};
      var link = data.link || data.click_action || '';
      if (link === 'FCM_PLUGIN_ACTIVITY') link = '';
      if (link) openPushLink(link);
      else window.location.href = '/poruke.php';
      // NE briši badge/notifikacije ovde — samo kad su poruke pročitane (unread=0)
      try {
        if (window.KtNative && typeof window.KtNative.clearPendingLink === 'function') {
          window.KtNative.clearPendingLink();
        }
      } catch (e) {}
    }

    // Deep link sa nativne strane (klik na push dok je app ugašena)
    try {
      if (window.KtNative && typeof window.KtNative.getPendingLink === 'function') {
        var pending = window.KtNative.getPendingLink();
        if (pending) {
          openPushLink(pending);
          window.KtNative.clearPendingLink();
        }
      }
    } catch (e) {}

    Push.addListener('registration', function (token) {
      var value = (token && (token.value || token.token)) || '';
      if (value) {
        try { localStorage.setItem('kt_push_token', value); } catch (e) {}
        if (loggedIn) registerPushTokenOnServer(value);
      }
    });

    Push.addListener('registrationError', function () {});

    Push.addListener('pushNotificationActionPerformed', handlePushOpen);

    Push.addListener('pushNotificationReceived', function (notification) {
      try {
        var data = (notification && notification.data) || {};
        var unread = parseInt(data.badge || '0', 10);
        if (unread > 0) updateUnreadBadges(unread);
      } catch (e) {}
    });

    // Badge dozvola (Android 13+)
    try {
      var Badge = window.Capacitor.Plugins.Badge;
      if (Badge && typeof Badge.requestPermissions === 'function') {
        Badge.requestPermissions().then(function () {
          var current = parseInt(document.body.getAttribute('data-unread-messages') || '0', 10) || 0;
          syncAppIconBadge(current);
        }).catch(function () {});
      } else if (loggedIn) {
        var current2 = parseInt(document.body.getAttribute('data-unread-messages') || '0', 10) || 0;
        syncAppIconBadge(current2);
      }
    } catch (e) {}

    Push.requestPermissions().then(function (perm) {
      var receive = (perm && (perm.receive || perm.granted)) || '';
      if (receive === 'granted' || receive === true || (perm && perm.receive === 'granted')) {
        if (typeof Push.createChannel === 'function') {
          return Push.createChannel({
            id: 'kupitelefon_messages',
            name: 'Poruke',
            description: 'Nove poruke na KupiTelefon',
            importance: 5,
            visibility: 1,
            sound: 'default',
            vibration: true
          }).catch(function () {}).then(function () {
            return Push.register();
          });
        }
        return Push.register();
      }
    }).catch(function () {});

    if (loggedIn) {
      try {
        var saved = localStorage.getItem('kt_push_token');
        if (saved) registerPushTokenOnServer(saved);
      } catch (e) {}
    }
  }

  function initAdFormV2() {
    const form = one('[data-ad-form]');
    if (!form || !form.classList.contains('ad-form-card')) return;
    if (!one('[data-phone-search]', form) && !one('[data-parts-kind]', form)) return;

    const catalogEl = one('#phone-catalog-data');
    let catalog = [];
    try {
      catalog = catalogEl ? JSON.parse(catalogEl.textContent || '[]') : [];
    } catch (e) {
      catalog = [];
    }

    function refreshTitlePreview() {
      const titleInput = one('[data-ad-title]', form);
      const suggested = buildSuggestedAdTitle(form);
      const manual = titleInput && (titleInput.value || '').trim();
      const core = manual || suggested || 'biće napravljen automatski';
      const text = 'Naslov oglasa: ' + core;
      form.querySelectorAll('[data-title-preview]').forEach(function (preview) {
        preview.textContent = text;
      });
      const mirror = one('[data-ad-title-mirror]', form);
      if (mirror && titleInput && document.activeElement !== mirror) {
        mirror.value = titleInput.value;
      }
    }

    form.querySelectorAll('[data-title-edit-toggle]').forEach(function (titleToggle) {
      titleToggle.addEventListener('click', function () {
        const block = titleToggle.closest('[data-title-preview-block]') || form;
        const titleEdit = one('[data-title-edit]', block);
        if (!titleEdit) return;
        titleEdit.hidden = !titleEdit.hidden;
        if (!titleEdit.hidden) {
          const t = one('[data-ad-title], [data-ad-title-mirror]', titleEdit);
          if (t) t.focus();
        }
      });
    });

    const titleInputMain = one('[data-ad-title]', form);
    const titleMirror = one('[data-ad-title-mirror]', form);
    if (titleMirror && titleInputMain) {
      titleMirror.addEventListener('input', function () {
        titleInputMain.value = titleMirror.value;
        refreshTitlePreview();
      });
      titleInputMain.addEventListener('input', function () {
        if (document.activeElement !== titleMirror) titleMirror.value = titleInputMain.value;
        refreshTitlePreview();
      });
    }

    // Manual phone fields
    const manualToggle = one('[data-manual-phone-toggle]', form);
    const manualBox = one('[data-manual-phone]', form);
    if (manualToggle && manualBox) {
      manualToggle.addEventListener('click', function () {
        manualBox.hidden = !manualBox.hidden;
      });
    }

    // Device type sync
    const deviceSelect = one('[data-device-type-select]', form);
    const deviceHidden = one('[data-device-type-hidden]', form);
    if (deviceSelect && deviceHidden) {
      deviceSelect.addEventListener('change', function () {
        deviceHidden.value = deviceSelect.value;
      });
    }

    // Parts kind → category_group + equipment_type
    form.querySelectorAll('[data-parts-kind]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        const cat = one('#ad-category', form);
        const eq = one('[data-equipment-type]', form);
        if (cat) cat.value = inp.getAttribute('data-category-group') || 'other_parts';
        if (eq) eq.value = inp.getAttribute('data-equipment-value') || '';
        form.querySelectorAll('[data-parts-kind]').forEach(function (x) {
          const lab = x.closest('.chip-option');
          if (lab) lab.classList.toggle('is-on', x.checked);
        });
        refreshTitlePreview();
      });
    });

    // Phone typeahead
    const search = one('[data-phone-search]', form);
    const suggestBox = one('[data-phone-suggestions]', form);
    const modelIdInput = one('[data-phone-model-id]', form);
    const variantIdInput = one('[data-phone-variant-id]', form);
    const brandSel = one('[data-phone-brand]', form);
    const modelInput = one('#ad-model', form);
    const storageSel = one('[data-storage-select]', form);
    const variantChips = one('[data-phone-variant-chips]', form);
    const ramSel = one('[data-ram-select]', form);
    const colorChips = one('[data-color-chips]', form);
    const colorInput = one('[data-color-input]', form);
    let defaultStorageOptions = ['64GB', '128GB', '256GB', '512GB', '1TB'];
    if (variantChips) {
      try {
        const parsedDefaults = JSON.parse(variantChips.getAttribute('data-default-storage') || '[]');
        if (Array.isArray(parsedDefaults) && parsedDefaults.length) {
          defaultStorageOptions = parsedDefaults;
        }
      } catch (e) {}
    }

    function defaultStorageVariants() {
      return defaultStorageOptions.map(function (st) {
        return { id: '', storage: st };
      });
    }

    function renderStorageChips(variants, selectedStorage, selectedVariantId) {
      if (!variantChips) return;
      const vars = (Array.isArray(variants) && variants.length) ? variants : defaultStorageVariants();
      variantChips.innerHTML = '';
      variantChips.hidden = false;
      const wrap = one('[data-storage-chips-wrap]', form);
      if (wrap) wrap.hidden = false;
      vars.forEach(function (v) {
        const btn = document.createElement('button');
        btn.type = 'button';
        const on = (selectedVariantId && v.id && selectedVariantId === v.id)
          || (selectedStorage && selectedStorage === v.storage);
        btn.className = 'chip-option' + (on ? ' is-on' : '');
        btn.setAttribute('data-variant-chip', '1');
        btn.setAttribute('data-variant-id', v.id || '');
        btn.setAttribute('data-storage', v.storage || '');
        btn.textContent = v.storage || '';
        variantChips.appendChild(btn);
      });
    }

    function applyVariantSelection(variantId, storage, silent) {
      if (variantIdInput) variantIdInput.value = variantId || '';
      if (storageSel) {
        Array.prototype.forEach.call(storageSel.options, function (o) {
          o.selected = !!(storage && o.value === storage);
        });
        if (!silent) storageSel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (variantChips) {
        variantChips.querySelectorAll('[data-variant-chip]').forEach(function (b) {
          const btnId = b.getAttribute('data-variant-id') || '';
          const btnSt = b.getAttribute('data-storage') || '';
          let on = false;
          if (variantId) on = btnId === variantId;
          else if (storage) on = btnSt === storage;
          b.classList.toggle('is-on', on);
        });
      }
      refreshTitlePreview();
    }

    function renderColorChips(colors, selected) {
      if (!colorChips) return;
      const list = Array.isArray(colors) ? colors.filter(Boolean) : [];
      colorChips.innerHTML = '';
      colorChips.hidden = list.length === 0;
      if (list.length === 0) {
        if (colorInput) colorInput.hidden = false;
        return;
      }
      let matched = false;
      list.forEach(function (color) {
        const btn = document.createElement('button');
        btn.type = 'button';
        const on = selected && selected === color;
        if (on) matched = true;
        btn.className = 'chip-option' + (on ? ' is-on' : '');
        btn.setAttribute('data-color-chip', color);
        btn.textContent = color;
        colorChips.appendChild(btn);
      });
      const other = document.createElement('button');
      other.type = 'button';
      other.className = 'chip-option' + ((selected && !matched) ? ' is-on' : '');
      other.setAttribute('data-color-chip', '__other__');
      other.textContent = 'Ostalo';
      colorChips.appendChild(other);
      if (colorInput) {
        colorInput.hidden = !(selected && !matched);
        if (matched) colorInput.value = selected;
      }
    }

    function pickModel(item, storage) {
      if (!item) return;
      if (modelIdInput) modelIdInput.value = item.id;
      if (brandSel) {
        Array.prototype.forEach.call(brandSel.options, function (o) {
          o.selected = o.value === item.brand;
        });
      }
      if (modelInput) {
        modelInput.value = item.name;
        modelInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (search) search.value = item.label;
      if (suggestBox) suggestBox.hidden = true;

      if (ramSel) {
        const ram = (item.ram || '').trim();
        if (ram) {
          let found = false;
          Array.prototype.forEach.call(ramSel.options, function (o) {
            if (o.value === ram) {
              o.selected = true;
              found = true;
            }
          });
          if (!found) {
            // keep empty if catalog RAM not in options
            Array.prototype.forEach.call(ramSel.options, function (o) { o.selected = o.value === ''; });
          }
        }
      }

      renderColorChips(item.colors || [], colorInput ? (colorInput.value || '').trim() : '');

      const catalogVars = item.variants || [];
      const currentStorage = storage || (storageSel ? (storageSel.value || '').trim() : '');
      renderStorageChips(catalogVars, currentStorage, '');

      let chosen = null;
      if (currentStorage) {
        for (let i = 0; i < catalogVars.length; i++) {
          if (catalogVars[i].storage === currentStorage) {
            chosen = catalogVars[i];
            break;
          }
        }
      }
      if (!chosen && catalogVars.length === 1) chosen = catalogVars[0];
      if (chosen) {
        applyVariantSelection(chosen.id, chosen.storage, false);
      } else if (currentStorage) {
        // Model bez katalog varijante: zadrži izabranu memoriju bez phone_variant_id.
        applyVariantSelection('', currentStorage, false);
      } else {
        if (variantIdInput) variantIdInput.value = '';
        refreshTitlePreview();
      }
    }

    if (variantChips) {
      variantChips.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-variant-chip]');
        if (!btn) return;
        applyVariantSelection(btn.getAttribute('data-variant-id') || '', btn.getAttribute('data-storage') || '', false);
      });
    }

    if (colorChips) {
      colorChips.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-color-chip]');
        if (!btn || !colorInput) return;
        const val = btn.getAttribute('data-color-chip') || '';
        colorChips.querySelectorAll('[data-color-chip]').forEach(function (b) {
          b.classList.toggle('is-on', b === btn);
        });
        if (val === '__other__') {
          colorInput.hidden = false;
          colorInput.focus();
        } else {
          colorInput.value = val;
          colorInput.hidden = true;
          colorInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        refreshTitlePreview();
      });
    }

    const batteryChips = one('[data-battery-chips]', form);
    const batteryInput = one('[data-battery-input]', form);
    if (batteryChips && batteryInput) {
      batteryChips.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-battery-chip]');
        if (!btn) return;
        const val = btn.getAttribute('data-battery-chip');
        batteryInput.value = val === null ? '' : val;
        batteryChips.querySelectorAll('[data-battery-chip]').forEach(function (b) {
          b.classList.toggle('is-on', b === btn);
        });
        refreshTitlePreview();
      });
    }

    if (search && suggestBox) {
      search.addEventListener('input', function () {
        const q = (search.value || '').trim().toLowerCase();
        if (q.length < 2) {
          suggestBox.hidden = true;
          suggestBox.innerHTML = '';
          return;
        }
        const hits = catalog.filter(function (m) {
          return (m.label || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        suggestBox.innerHTML = '';
        hits.forEach(function (m) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'ad-typeahead-item';
          btn.textContent = m.label;
          btn.addEventListener('click', function () { pickModel(m); });
          suggestBox.appendChild(btn);
        });
        suggestBox.hidden = hits.length === 0;
      });
    }

    function syncPhoneModelLabel() {
      const label = one('[data-phone-model-label]', form);
      const listing = one('[data-listing-type]:checked', form);
      const tradeWrap = one('[data-trade-for-wrap]', form);
      if (!label) return;
      const v = listing ? listing.value : 'sell';
      if (v === 'buy') label.textContent = 'Koji telefon tražiš?';
      else if (v === 'trade') label.textContent = 'Koji telefon menjaš?';
      else label.textContent = 'Koji telefon prodaješ?';
      if (tradeWrap) tradeWrap.hidden = v !== 'trade';
    }
    syncPhoneModelLabel();

    // Compat multi typeahead for parts
    const compatSearch = one('[data-compat-search]', form);
    const compatSuggest = one('[data-compat-suggestions]', form);
    const compatChips = one('[data-compat-chips]', form);
    const compatValue = one('[data-compat-value]', form);
    let compatSelected = [];
    if (compatValue && (compatValue.value || '').trim()) {
      compatSelected = (compatValue.value || '').split(/[,/]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    }
    function renderCompat() {
      if (!compatChips || !compatValue) return;
      compatChips.innerHTML = '';
      compatSelected.forEach(function (label, idx) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'chip-option is-on';
        chip.textContent = label + ' ×';
        chip.addEventListener('click', function () {
          compatSelected.splice(idx, 1);
          renderCompat();
        });
        compatChips.appendChild(chip);
      });
      compatValue.value = compatSelected.join(' / ');
      refreshTitlePreview();
    }
    renderCompat();
    if (compatSearch && compatSuggest) {
      compatSearch.addEventListener('input', function () {
        const q = (compatSearch.value || '').trim().toLowerCase();
        if (q.length < 2) {
          compatSuggest.hidden = true;
          return;
        }
        const hits = catalog.filter(function (m) {
          return (m.label || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        compatSuggest.innerHTML = '';
        hits.forEach(function (m) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'ad-typeahead-item';
          btn.textContent = m.label;
          btn.addEventListener('click', function () {
            if (compatSelected.indexOf(m.label) === -1) compatSelected.push(m.label);
            compatSearch.value = '';
            compatSuggest.hidden = true;
            renderCompat();
          });
          compatSuggest.appendChild(btn);
        });
        compatSuggest.hidden = hits.length === 0;
      });
    }

    // Draft autosave (text fields only)
    const draftKey = form.getAttribute('data-draft-key') || 'kt-ad-draft-v1';
    const draftStatus = one('[data-draft-status]', form);
    const draftFields = 'input[type="text"],input[type="search"],input[type="tel"],input[type="number"],textarea,select';
    function saveDraft() {
      if (form.getAttribute('data-is-edit') === '1') return;
      const data = {};
      form.querySelectorAll(draftFields).forEach(function (el) {
        if (!el.name || el.disabled || el.type === 'file' || el.type === 'password') return;
        if (el.type === 'radio' || el.type === 'checkbox') {
          if (el.checked) data[el.name] = el.value;
          return;
        }
        data[el.name] = el.value;
      });
      try {
        localStorage.setItem(draftKey, JSON.stringify({ at: Date.now(), data: data }));
        if (draftStatus) {
          draftStatus.hidden = false;
          draftStatus.textContent = 'Nacrt sačuvan';
        }
      } catch (e) {}
    }
    function restoreDraft() {
      if (form.getAttribute('data-is-edit') === '1') return;
      if (one('[data-form-error]', form)) return; // keep server redisplay
      try {
        const raw = localStorage.getItem(draftKey);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        const data = parsed && parsed.data ? parsed.data : null;
        if (!data) return;
        Object.keys(data).forEach(function (name) {
          const els = form.querySelectorAll('[name="' + name + '"]');
          els.forEach(function (el) {
            if (el.type === 'radio' || el.type === 'checkbox') {
              el.checked = el.value === data[name];
            } else if (el.type !== 'file') {
              el.value = data[name];
            }
          });
        });
        if (draftStatus) {
          draftStatus.hidden = false;
          draftStatus.textContent = 'Nacrt sačuvan';
        }
        const typeEl = one('[data-form-type]:checked', form);
        if (typeEl) syncAdFormByType(typeEl.value);
        refreshTitlePreview();
      } catch (e) {}
    }
    let draftTimer = null;
    form.addEventListener('input', function () {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 400);
      refreshTitlePreview();
    });
    form.addEventListener('change', function () {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 200);
      refreshTitlePreview();
      const typeEl = one('[data-form-type]:checked', form);
      if (typeEl) syncPhonePhotoRequirement(form, typeEl.value);
    });
    form.addEventListener('submit', function () {
      try { localStorage.removeItem(draftKey); } catch (e) {}
    });
    restoreDraft();

    // Focus first field error
    const firstErr = one('.field-error', form);
    if (firstErr) {
      const wrap = firstErr.closest('.form-group') || firstErr.parentElement;
      const focusEl = wrap ? wrap.querySelector('input, select, textarea') : null;
      if (focusEl) {
        try { focusEl.focus(); } catch (e) {}
      }
    }

    // Listing change → photo requirement / intent chips / model label
    form.querySelectorAll('[data-listing-type]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        form.querySelectorAll('[data-listing-opt]').forEach(function (lab) {
          const i = lab.querySelector('[data-listing-type]');
          lab.classList.toggle('is-on', !!(i && i.checked && !i.disabled));
        });
        const typeEl = one('[data-form-type]:checked', form);
        syncPhonePhotoRequirement(form, typeEl ? typeEl.value : 'telefon');
        syncPhoneModelLabel();
        refreshTitlePreview();
      });
    });

    refreshTitlePreview();
  }

  function isStandalonePwa() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  function isIosDevice() {
    var ua = navigator.userAgent || '';
    if (/iphone|ipad|ipod/i.test(ua)) return true;
    // iPadOS desktop UA
    return navigator.platform === 'MacIntel' && (navigator.maxTouchPoints || 0) > 1;
  }

  function initPwa() {
    if (!('serviceWorker' in navigator)) return;

    // Ne registruj SW unutar Capacitor/Android WebView ako postoji nativni bridge
    if (typeof isNativeApp === 'function' && isNativeApp()) return;

    navigator.serviceWorker.register('/sw.js').catch(function () {});

    var banner = one('[data-pwa-install]');
    var installBtn = one('[data-pwa-install-btn]');
    var iosHelpBtn = one('[data-pwa-ios-help]');
    var dismissBtn = one('[data-pwa-install-dismiss]');
    var hint = one('[data-pwa-install-hint]');
    var steps = one('[data-pwa-install-steps]');
    var deferredPrompt = null;
    var dismissKey = 'kt_pwa_install_dismissed';
    var mode = 'android';
    var pendingPwaMode = null;
    var path = (window.location.pathname || '');
    var isLoginPage = path === '/prijava' || path === '/login.php' || path.indexOf('/prijava') === 0;

    function dismissed() {
      try { return localStorage.getItem(dismissKey) === '1'; } catch (e) { return false; }
    }
    function setDismissed() {
      try { localStorage.setItem(dismissKey, '1'); } catch (e) {}
    }

    function cookieBannerOpen() {
      var cookieBanner = document.getElementById('kp-cookie-banner');
      return !!(cookieBanner && !cookieBanner.hidden);
    }

    function setBtnVisible(btn, visible) {
      if (!btn) return;
      btn.hidden = !visible;
      btn.classList.toggle('is-pwa-hidden', !visible);
    }

    function showBanner(nextMode) {
      if (!banner || isStandalonePwa() || dismissed()) return;
      // Na prijavi: ne prikazuj „Dodaj na telefon“ dok je cookie baner otvoren.
      if (isLoginPage && cookieBannerOpen()) {
        pendingPwaMode = nextMode || mode || 'android';
        banner.hidden = true;
        return;
      }
      mode = nextMode || 'android';
      if (hint) {
        hint.textContent = mode === 'ios'
          ? 'Na iPhone-u Apple ne dozvoljava jedan-klik instalaciju — koristi Share meni.'
          : 'Brži pristup kao aplikacija — početni ekran.';
      }
      if (steps) steps.hidden = true;
      // iOS: sakrij „Dodaj“ (ne radi), prikaži „Kako?“
      setBtnVisible(installBtn, mode !== 'ios');
      setBtnVisible(iosHelpBtn, mode === 'ios');
      banner.hidden = false;
    }

    var cookieBannerEl = document.getElementById('kp-cookie-banner');
    if (cookieBannerEl && typeof MutationObserver === 'function') {
      var cookieObs = new MutationObserver(function () {
        if (!cookieBannerOpen() && pendingPwaMode) {
          var next = pendingPwaMode;
          pendingPwaMode = null;
          showBanner(next);
        } else if (isLoginPage && cookieBannerOpen() && banner && !banner.hidden) {
          pendingPwaMode = mode || 'android';
          banner.hidden = true;
        }
      });
      cookieObs.observe(cookieBannerEl, { attributes: true, attributeFilter: ['hidden'] });
    }

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      showBanner('android');
    });

    // iOS (Safari / Chrome na iOS-u): samo uputstvo
    if (isIosDevice() && !isStandalonePwa()) {
      showBanner('ios');
    }

    if (installBtn) {
      installBtn.addEventListener('click', function () {
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.finally(function () {
            deferredPrompt = null;
            if (banner) banner.hidden = true;
          });
          return;
        }
        // Fallback ako je dugme vidljivo bez prompta
        if (isIosDevice()) {
          showBanner('ios');
          if (steps) steps.hidden = false;
          return;
        }
        if (hint) {
          hint.textContent = 'Otvori meni pregledača → „Instaliraj aplikaciju“ / „Dodaj na početni ekran“.';
        }
      });
    }

    if (iosHelpBtn) {
      iosHelpBtn.addEventListener('click', function () {
        if (steps) {
          steps.hidden = !steps.hidden;
          iosHelpBtn.textContent = steps.hidden ? 'Kako?' : 'Sakrij';
        }
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        setDismissed();
        if (banner) banner.hidden = true;
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTypeFilters();
    initDrawer();
    initListingFilters();
    initFocusSearch();
    initFormTypeSelect();
    initCategoryGroupSync();
    initAdFormExtras();
    initPhoneCatalogPicker();
    initAdFormV2();
    initActiveNav();
    initAccountMenu();
    initAccountTabs();
    initAccountQuick();
    initPromoPanels();
    initGallery();
    initLightbox();
    initPhotoPreview();
    initPhotoReorder();
    initSearchSuggest();
    initCopyLink();
    initShopLinks();
    initPhoneReveal();
    initMsgToast();
    initChatScroll();
    initKeyboardInset();
    initRatingsTabs();
    initLiveChat();
    initLiveInbox();
    initUnreadPolling();
    initAdsView();
    initCompare();
    initShareAd();
    initNativeChrome();
    initPushNotifications();
    initPwa();
  });
})();
