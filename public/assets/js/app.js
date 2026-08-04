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
        const selected = one('[data-form-type]:checked');
        if (!selected) return;
        const opt = selected.closest('.form-type-option');
        if (!opt) return;
        if (selected.value === 'telefon') opt.classList.add('selected');
        if (selected.value === 'delovi') opt.classList.add('selected-parts');
        if (selected.value === 'servis') opt.classList.add('selected-service');
        syncAdFormByType(selected.value);
        const priceInput = one('[data-price-input]');
        if (priceInput) priceInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    const current = one('[data-form-type]:checked');
    if (current) syncAdFormByType(current.value);
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
      const forTypes = (lab.getAttribute('data-for-types') || '').split(',');
      const ok = forTypes.indexOf(type) !== -1;
      lab.hidden = !ok;
      const inp = lab.querySelector('input');
      if (inp) inp.disabled = !ok;
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
      if (fallback && !fallback.disabled) {
        fallback.checked = true;
        const lab = fallback.closest('.chip-option');
        if (lab) lab.classList.add('is-on');
      }
    }

    form.querySelectorAll('[data-listing-opt]').forEach(function (lab) {
      const inp = lab.querySelector('[data-listing-type]');
      lab.classList.toggle('is-on', !!(inp && inp.checked && !inp.disabled));
    });

    const cat = one('#ad-category', form);
    const catWrap = one('[data-category-wrap]', form);
    if (cat) {
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
      if (catWrap) {
        if (visibleCount > 1) catWrap.removeAttribute('hidden');
        else catWrap.setAttribute('hidden', '');
      }
    }

    const brandSel = one('[data-phone-brand]', form);
    const batt = one('[data-battery-field]', form);
    if (batt && brandSel) {
      const isApple = (brandSel.value || '') === 'Apple';
      batt.hidden = !isApple;
      const bh = batt.querySelector('input');
      if (bh) {
        var panel = batt.closest('[data-panel]');
        var panelHidden = panel && panel.hasAttribute('hidden');
        bh.disabled = !isApple || !!panelHidden;
        if (!isApple) bh.value = '';
      }
    }
  }

  function initAdFormExtras() {
    const form = one('[data-ad-form]');
    if (!form) return;

    const title = one('#listing-title', form);
    const model = one('#ad-model', form);
    const brand = one('[data-phone-brand]', form);
    if (title && model && brand) {
      function suggestTitle() {
        if ((title.value || '').trim() !== '') return;
        const b = (brand.value || '').trim();
        const m = (model.value || '').trim();
        if (!m) return;
        title.placeholder = (b && b !== 'Ostalo' ? b + ' ' : '') + m;
      }
      model.addEventListener('change', suggestTitle);
      model.addEventListener('blur', function () {
        if ((title.value || '').trim() !== '') return;
        const b = (brand.value || '').trim();
        const m = (model.value || '').trim();
        if (!m) return;
        title.value = (b && b !== 'Ostalo' ? b + ' ' : '') + m;
      });
      brand.addEventListener('change', function () {
        const typeEl = one('[data-form-type]:checked', form);
        syncAdFormByType(typeEl ? typeEl.value : 'telefon');
        suggestTitle();
      });
    }

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

    let rafId = null;
    const consume = function (e) {
      if (!e) return;
      window.__ktGalleryNavTs = Date.now();
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    };

    function currentIndex() {
      const w = track.clientWidth || 1;
      return Math.min(total - 1, Math.max(0, Math.round(track.scrollLeft / w)));
    }

    function paint(idx) {
      counter.textContent = (idx + 1) + ' od ' + total;
      thumbs.forEach(function (thumb) {
        const isOn = parseInt(thumb.getAttribute('data-gallery-thumb-index') || '-1', 10) === idx;
        thumb.classList.toggle('is-active', isOn);
        thumb.style.borderColor = isOn ? '#1a73e8' : 'transparent';
        thumb.style.boxShadow = isOn ? '0 0 0 2px rgba(26,115,232,.16)' : 'none';
      });
    }

    function scrollToIndex(idx, smooth) {
      const w = track.clientWidth || 1;
      const wrapped = ((idx % total) + total) % total;
      const left = w * wrapped;
      try {
        if (smooth) {
          track.scrollTo({ left: left, behavior: 'smooth' });
        } else {
          track.scrollLeft = left;
        }
      } catch (e) {
        track.scrollLeft = left;
      }
      paint(wrapped);
    }

    track.style.scrollBehavior = 'smooth';
    paint(currentIndex());

    track.addEventListener('scroll', function () {
      if (rafId !== null) return;
      rafId = window.requestAnimationFrame(function () {
        rafId = null;
        paint(currentIndex());
      });
    }, { passive: true });

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('pointerdown', consume);
      thumb.addEventListener('click', function (e) {
        consume(e);
        const idx = parseInt(thumb.getAttribute('data-gallery-thumb-index') || '0', 10);
        scrollToIndex(isNaN(idx) ? 0 : idx, true);
      });
    });

    if (prevBtn) {
      prevBtn.addEventListener('pointerdown', consume);
      prevBtn.addEventListener('click', function (e) {
        consume(e);
        scrollToIndex(currentIndex() - 1, true);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('pointerdown', consume);
      nextBtn.addEventListener('click', function (e) {
        consume(e);
        scrollToIndex(currentIndex() + 1, true);
      });
    }
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
  }

  function initPhotoPreview() {
    const input = one('[data-photo-input]');
    const preview = one('[data-photo-preview]');
    const drop = one('[data-photo-drop]');
    if (!input || !preview) return;

    const MAX_TOTAL = 10;
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

    async function compressFile(file) {
      if (!file || !file.type || file.type.indexOf('image/') !== 0) {
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

  function updateUnreadBadges(count) {
    const n = Math.max(0, parseInt(count, 10) || 0);
    document.body.setAttribute('data-unread-messages', String(n));
    all('.nav-with-badge').forEach(function (link) {
      if (!/poruke/i.test(link.getAttribute('href') || '')) return;
      let badge = one('.notif-badge', link);
      if (n <= 0) {
        if (badge) badge.remove();
        return;
      }
      const label = n > 99 ? '99+' : String(n);
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notif-badge';
        link.appendChild(badge);
      }
      badge.textContent = label;
      badge.setAttribute('aria-label', label + ' nepročitanih');
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
    syncAppIconBadge(n);
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
          if (typeof data.unread === 'number') updateUnreadBadges(data.unread);
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
          if (typeof data.unread === 'number') updateUnreadBadges(data.unread);
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
            updateUnreadBadges(data.unread);
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
          if (typeof data.unread === 'number') updateUnreadBadges(data.unread);
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

  function initRatingsTabs() {
    const tabs = all('[data-ratings-tab]');
    if (!tabs.length) return;

    function activate(whichTab) {
      tabs.forEach(function (tab) {
        tab.classList.toggle('active', tab.getAttribute('data-ratings-tab') === whichTab);
      });
    }

    function fromHash() {
      const hash = (window.location.hash || '').replace('#', '');
      if (hash === 'ocene-positive') activate('positive');
      else if (hash === 'ocene-negative') activate('negative');
      else if (hash === 'ocene' || hash === 'ocene-all') activate('all');
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(tab.getAttribute('data-ratings-tab') || 'all');
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

  document.addEventListener('DOMContentLoaded', function () {
    initTypeFilters();
    initDrawer();
    initListingFilters();
    initFocusSearch();
    initFormTypeSelect();
    initAdFormExtras();
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
    initRatingsTabs();
    initLiveChat();
    initLiveInbox();
    initUnreadPolling();
    initAdsView();
    initCompare();
    initShareAd();
    initNativeChrome();
    initPushNotifications();
  });
})();
