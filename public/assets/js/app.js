(function () {
  function all(sel, root) { return (root || document).querySelectorAll(sel); }
  function one(sel, root) { return (root || document).querySelector(sel); }

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
    const ads = all('.ad-item[data-category]');
    let visible = 0;
    ads.forEach(function (ad) {
      const show = selected.length === 0 || selected.includes(ad.getAttribute('data-category'));
      ad.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    const count = one('[data-results-count]');
    if (count) count.textContent = String(visible);
    const empty = one('.empty-state');
    if (empty) empty.classList.toggle('visible', visible === 0);
  }

  function initTypeFilters() {
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
      });
    });
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
    const main = one('#gallery-main');
    const counter = one('[data-gallery-counter]');
    const thumbs = all('[data-gallery-thumb]');
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        if (!main) return;
        main.src = thumb.getAttribute('data-gallery-thumb') || '';
        all('.detail-thumb').forEach(function (t) { t.classList.remove('active'); });
        thumb.classList.add('active');
        if (counter) {
          const idx = thumb.getAttribute('data-gallery-index') || '1';
          counter.textContent = idx + ' od ' + thumbs.length;
        }
      });
    });

    const track = one('#gallery-track') || one('.kp-gallery-track');
    if (track && counter) {
      const slides = all('.kp-gallery-slide', track);
      const total = slides.length;
      if (total > 1) {
        track.addEventListener('scroll', function () {
          const w = track.clientWidth || 1;
          const idx = Math.min(total, Math.max(1, Math.round(track.scrollLeft / w) + 1));
          counter.textContent = idx + ' od ' + total;
        }, { passive: true });
      }
    }
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
              fetch('/api/track.php', { method: 'POST', body: body, credentials: 'same-origin' });
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
      fetch('/uporedi.php', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
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
          if (el.classList.contains('ad-compare-btn') || el.classList.contains('kp-list-cmp')) {
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
    if (!input || !preview) return;
    input.addEventListener('change', function () {
      preview.innerHTML = '';
      Array.from(input.files || []).slice(0, 10).forEach(function (file) {
        const url = URL.createObjectURL(file);
        const slot = document.createElement('div');
        slot.className = 'photo-slot';
        slot.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">';
        preview.appendChild(slot);
      });
    });
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

    function hide() {
      box.hidden = true;
      box.innerHTML = '';
      items = [];
      active = -1;
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
      fetch('/api/search.php?q=' + encodeURIComponent(q) + '&limit=8', { signal: ctrl.signal })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          items = Array.isArray(data.suggestions) ? data.suggestions : [];
          active = items.length ? 0 : -1;
          render();
        })
        .catch(function () { /* ignore */ });
    }

    input.addEventListener('input', function () {
      const q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 1) {
        hide();
        return;
      }
      timer = setTimeout(function () { fetchSuggest(q); }, 180);
    });

    input.addEventListener('keydown', function (e) {
      if (box.hidden || !items.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        active = (active + 1) % items.length;
        render();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active - 1 + items.length) % items.length;
        render();
      } else if (e.key === 'Enter' && active >= 0) {
        e.preventDefault();
        go(active);
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

      fetch('/api/messages.php?action=send', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
        headers: { 'Accept': 'application/json' }
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
    // Only when user appears logged in (attribute present from layout)
    let timer = null;
    function tick() {
      if (document.hidden) return;
      // Skip if live chat already polls unread
      if (one('[data-live-chat]')) return;
      fetch('/api/messages.php?action=unread', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
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
    window.addEventListener('beforeunload', function () {
      if (timer) clearInterval(timer);
    });
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
    const list = one('[data-ads-list]');
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

  document.addEventListener('DOMContentLoaded', function () {
    initTypeFilters();
    initDrawer();
    initFocusSearch();
    initFormTypeSelect();
    initActiveNav();
    initAccountMenu();
    initAccountTabs();
    initAccountQuick();
    initPromoPanels();
    initGallery();
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
  });
})();
