(function () {
  var cfgEl = document.getElementById('kp-google-tag-config');
  if (!cfgEl) return;

  var cfg = {};
  try {
    cfg = JSON.parse(cfgEl.textContent || '{}');
  } catch (e) {
    return;
  }

  var ga4Id = String(cfg.ga4 || '').trim();
  var adsId = String(cfg.ads || '').trim();
  if (!ga4Id && !adsId) return;

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

  function canLoadTag() {
    if (!requireConsent) return true;
    return getConsent() === 'all';
  }

  function fireEvent(row) {
    if (!window.gtag || !row || !row.event) return;
    var params = row.params && typeof row.params === 'object' ? row.params : {};
    window.gtag('event', row.event, params);
  }

  function loadTag() {
    if (window._kpGoogleTagLoaded) {
      pendingEvents.forEach(fireEvent);
      pendingEvents = [];
      return;
    }
    window._kpGoogleTagLoaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4Id || adsId);
    document.head.appendChild(script);

    if (ga4Id) {
      window.gtag('config', ga4Id);
    }
    if (adsId) {
      window.gtag('config', adsId);
    }
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
      if (choice === 'all') loadTag();
    });
  }

  if (canLoadTag()) {
    loadTag();
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

