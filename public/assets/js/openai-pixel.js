(function () {
  var cfgEl = document.getElementById('kp-openai-pixel-config');
  if (!cfgEl) return;

  var cfg = {};
  try {
    cfg = JSON.parse(cfgEl.textContent || '{}');
  } catch (e) {
    return;
  }

  var pixelId = String(cfg.pixelId || '').replace(/[^A-Za-z0-9_-]/g, '');
  if (!pixelId) return;

  var requireConsent = !!cfg.requireConsent;
  var debug = !!cfg.debug;
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

  function loadPixel() {
    if (window._kpOpenAiPixelLoaded) return;
    window._kpOpenAiPixelLoaded = true;

    !(function (w, d, s, u) {
      if (w.oaiq) return;
      var q = function () {
        q.q.push(arguments);
      };
      q.q = [];
      w.oaiq = q;
      var j = d.createElement(s);
      j.async = 1;
      j.src = u;
      var f = d.getElementsByTagName(s)[0];
      f.parentNode.insertBefore(j, f);
    })(window, document, 'script', 'https://bzrcdn.openai.com/sdk/oaiq.min.js');

    window.oaiq('init', { pixelId: pixelId, debug: debug });
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
    showBanner();
  }
})();
