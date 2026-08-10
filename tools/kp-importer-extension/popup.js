'use strict';

const $ = (id) => document.getElementById(id);

const DEFAULT_SKIP =
  'torbica, maska, futrola, folija, staklo, punjac, punjač, adapter, kabl, laptop, zvucnik, zvučnik, powerbank, slušalice, airpods';

let pollTimer = null;

function setStatus(text, isError) {
  const el = $('status');
  el.hidden = false;
  el.textContent = text;
  el.classList.toggle('error', !!isError);
}

function isSellerListUrl(url) {
  return /kupujemprodajem\.com\/[^/]+\/svi-oglasi\/\d+/i.test(String(url || '').split(/[?#]/)[0]);
}

async function loadPrefs() {
  const data = await chrome.storage.local.get('kpScrapePrefs');
  const p = data.kpScrapePrefs || {};
  if (p.filterMode) $('filterMode').value = p.filterMode;
  $('skipKeywords').value = p.skipKeywords != null ? p.skipKeywords : DEFAULT_SKIP;
  if (p.maxPages) $('maxPages').value = p.maxPages;
  if (p.delayMs) $('delayMs').value = p.delayMs;
  if (typeof p.allPages === 'boolean') $('allPages').checked = p.allPages;
  if (typeof p.includeDescriptions === 'boolean') $('includeDescriptions').checked = p.includeDescriptions;
  if (typeof p.includeDetails === 'boolean') $('includeDetails').checked = p.includeDetails;
}

async function savePrefs() {
  await chrome.storage.local.set({
    kpScrapePrefs: {
      filterMode: $('filterMode').value,
      skipKeywords: $('skipKeywords').value,
      maxPages: parseInt($('maxPages').value, 10) || 10,
      delayMs: parseInt($('delayMs').value, 10) || 1200,
      allPages: $('allPages').checked,
      includeDescriptions: $('includeDescriptions').checked,
      includeDetails: $('includeDetails').checked,
    },
  });
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function setRunningUi(running) {
  $('btnScrape').disabled = !!running;
  $('btnCancel').hidden = !running;
  $('btnCancel').disabled = false;
}

async function refreshExportPreview() {
  const stored = await chrome.runtime.sendMessage({ action: 'getLastExport' });
  if (stored?.payload?.ads?.length) {
    $('btnDownload').disabled = false;
    $('preview').hidden = false;
    const p = stored.payload;
    $('preview').textContent = JSON.stringify(
      {
        filter: p.meta?.filter_mode,
        kept: p.ads.length,
        skipped: p.meta?.skipped_ads || 0,
        pages_scraped: p.meta?.pages_scraped,
        pages_planned: p.meta?.pages_planned,
        partial: !!p.meta?.partial,
        sample: p.ads[0] ? { title: p.ads[0].title, price: p.ads[0].price_text } : null,
      },
      null,
      2
    );
  }
}

function applyJobToUi(job, running) {
  if (!job) return;
  if (running || job.running) {
    setRunningUi(true);
    const live =
      (job.message || 'Radi u pozadini…') +
      '\n(smeš zatvoriti prozor — badge na ikoni = napredak)';
    setStatus(live, false);
    $('preview').hidden = false;
    $('preview').textContent = JSON.stringify(
      {
        status: 'u toku',
        phase: job.phase,
        pages: (job.pagesDone || 0) + '/' + (job.pagesPlanned || '?'),
        collected_ads: job.adsCount || 0,
        note:
          'Broj oglasa raste tokom učitavanja. Filter (telefoni) se primenjuje na kraju — kept će biti manji od collected.',
      },
      null,
      2
    );
    $('btnDownload').disabled = false;
    return;
  }

  setRunningUi(false);
  stopPolling();

  if (job.finishedOk) {
    setStatus(job.message || 'Gotovo.', false);
    refreshExportPreview();
    return;
  }

  if (job.error || job.phase === 'aborted' || job.phase === 'interrupted') {
    setStatus(job.message || job.error || 'Prekinuto.', true);
    refreshExportPreview();
  }
}

async function pollJobOnce() {
  try {
    const res = await chrome.runtime.sendMessage({ action: 'getJobStatus' });
    applyJobToUi(res?.job, res?.running);
    if (!res?.running && !res?.job?.running) {
      stopPolling();
    }
  } catch (_) {
    /* popup closing */
  }
}

function startPolling() {
  stopPolling();
  pollJobOnce();
  pollTimer = setInterval(pollJobOnce, 1000);
}

async function init() {
  const manifest = chrome.runtime.getManifest();
  const verEl = $('extVersion');
  if (verEl && manifest?.version) {
    verEl.textContent = 'v' + manifest.version + ' · ';
  }

  await loadPrefs();

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  const url = tab?.url || '';

  if (!/kupujemprodajem\.com/i.test(url)) {
    $('btnScrape').disabled = true;
    setStatus('Otvori KupujemProdajem u aktivnom tabu, pa pokreni scrape.', true);
  } else if (!isSellerListUrl(url)) {
    $('pageHint').innerHTML =
      'Preporuka: otvori <strong>Svi oglasi</strong> prodavca<br><code>…/korisnik/svi-oglasi/ID/1</code>';
  } else {
    $('pageHint').textContent =
      'Spremno. Posle „Pokupi“ smeš zatvoriti ovaj prozor — radi u pozadini.';
  }

  await refreshExportPreview();

  const jobRes = await chrome.runtime.sendMessage({ action: 'getJobStatus' });
  if (jobRes?.running || jobRes?.job?.running) {
    setStatus(jobRes.job?.message || 'Već radi u pozadini…', false);
    setRunningUi(true);
    startPolling();
  } else if (jobRes?.job?.finishedOk) {
    applyJobToUi(jobRes.job, false);
  } else if (jobRes?.job?.error) {
    applyJobToUi(jobRes.job, false);
  }
}

$('btnScrape').addEventListener('click', async () => {
  await savePrefs();
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab?.id) {
    setStatus('Nema aktivnog taba.', true);
    return;
  }

  setRunningUi(true);
  setStatus('Pokrećem u pozadini… smeš zatvoriti ovaj prozor.', false);

  try {
    const res = await chrome.runtime.sendMessage({
      action: 'startScrape',
      tabId: tab.id,
      url: tab.url,
      allPages: $('allPages').checked,
      includeDetails: $('includeDetails').checked,
      includeDescriptions: $('includeDescriptions').checked,
      delayMs: parseInt($('delayMs').value, 10) || 1200,
      filterMode: $('filterMode').value,
      skipKeywords: $('skipKeywords').value,
      maxPages: parseInt($('maxPages').value, 10) || 10,
    });

    if (!res?.ok) {
      setRunningUi(false);
      setStatus(res?.error || 'Nije pokrenuto.', true);
      return;
    }

    startPolling();
  } catch (e) {
    setRunningUi(false);
    setStatus(String(e.message || e), true);
  }
});

$('btnCancel').addEventListener('click', async () => {
  $('btnCancel').disabled = true;
  setStatus('Otkazujem…', false);
  try {
    await chrome.runtime.sendMessage({ action: 'cancelScrape' });
    startPolling();
  } catch (e) {
    setStatus(String(e.message || e), true);
  }
});

$('btnDownload').addEventListener('click', async () => {
  const stored = await chrome.runtime.sendMessage({ action: 'getLastExport' });
  const payload = stored?.payload;
  if (!payload) {
    setStatus('Nema sačuvanog izvoza.', true);
    return;
  }

  const slug = (payload.seller?.username || 'kp-export').replace(/[^\w-]+/g, '-');
  const filename = 'kp-' + slug + '-' + new Date().toISOString().slice(0, 10) + '.json';
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);

  await chrome.downloads.download({
    url,
    filename,
    saveAs: true,
  });

  setTimeout(() => URL.revokeObjectURL(url), 5000);
});

init();
