(function () {
  var cfgEl = document.getElementById('kp-fb-pixel-config');
  if (!cfgEl) return;

  var cfg = {};
  try {
    cfg = JSON.parse(cfgEl.textContent || '{}');
  } catch (e) {
    return;
  }

  var pixelId = String(cfg.id || '').replace(/\D+/g, '');
  if (!pixelId) return;

  var requireConsent = !!cfg.requireConsent;
  var pendingEvents = Array.isArray(cfg.events) ? cfg.events : [];
  var CONSENT_KEY = 'kp_cookie_consent';

  function getConsent() {
    try {
      return localStorage.getItem(CONSENT_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  function setConsent(value) {
    try {
      localStorage.setItem(CONSENT_KEY, value);
    } catch (e) {}
  }

  function canLoadPixel() {
    if (!requireConsent) return true;
    return getConsent() === 'all';
  }

  function fireEvent(row) {
    if (!window.fbq || !row || !row.event) return;
    var params = row.params && typeof row.params === 'object' ? row.params : {};
    if (row.custom) {
      window.fbq('trackCustom', row.event, params);
    } else {
      window.fbq('track', row.event, params);
    }
  }

  function loadPixel() {
    if (window._kpFbPixelLoaded) {
      pendingEvents.forEach(fireEvent);
      pendingEvents = [];
      return;
    }
    window._kpFbPixelLoaded = true;

    !(function (f, b, e, v, n, t, s) {
      if (f.fbq) return;
      n = f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
      };
      if (!f._fbq) f._fbq = n;
      n.push = n;
      n.loaded = !0;
      n.version = '2.0';
      n.queue = [];
      t = b.createElement(e);
      t.async = !0;
      t.src = v;
      s = b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t, s);
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

    window.fbq('init', pixelId);
    window.fbq('track', 'PageView');
    pendingEvents.forEach(fireEvent);
    pendingEvents = [];
  }

  function showBanner() {
    var banner = document.getElementById('kp-cookie-banner');
    if (!banner) return;
    banner.hidden = false;
    banner.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-kp-cookie]') : null;
      if (!btn) return;
      var choice = btn.getAttribute('data-kp-cookie') || 'necessary';
      setConsent(choice);
      banner.hidden = true;
      if (choice === 'all') {
        loadPixel();
      }
    });
  }

  if (canLoadPixel()) {
    loadPixel();
    return;
  }

  if (requireConsent && !getConsent()) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', showBanner);
    } else {
      showBanner();
    }
  }
})();
