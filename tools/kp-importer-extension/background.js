'use strict';

importScripts('parser.js');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function ensureContentScripts(tabId) {
  await chrome.scripting.executeScript({
    target: { tabId },
    files: ['parser.js', 'content.js'],
  });
}

async function tabMessage(tabId, msg) {
  try {
    return await chrome.tabs.sendMessage(tabId, msg);
  } catch (e) {
    await ensureContentScripts(tabId);
    return chrome.tabs.sendMessage(tabId, msg);
  }
}

async function scrapeCurrentTab(tabId) {
  const res = await tabMessage(tabId, { action: 'scrapeCurrentPage' });
  return res || { ok: false, error: 'Nema odgovora sa taba.' };
}

function waitForTabComplete(tabId, timeoutMs) {
  const limit = timeoutMs || 45000;
  return new Promise((resolve, reject) => {
    let done = false;
    const timer = setTimeout(() => {
      if (done) return;
      done = true;
      try {
        chrome.tabs.onUpdated.removeListener(onUpdated);
      } catch (_) {}
      reject(new Error('Timeout učitavanja strane (' + limit + 'ms)'));
    }, limit);

    function finish() {
      if (done) return;
      done = true;
      clearTimeout(timer);
      try {
        chrome.tabs.onUpdated.removeListener(onUpdated);
      } catch (_) {}
      resolve();
    }

    function onUpdated(id, info) {
      if (id === tabId && info.status === 'complete') finish();
    }

    chrome.tabs.onUpdated.addListener(onUpdated);
  });
}

function sameSellerPage(urlA, urlB) {
  const a = KpParser.parseSellerListUrl(urlA);
  const b = KpParser.parseSellerListUrl(urlB);
  if (a && b) return a.user_id === b.user_id && a.page === b.page;
  const norm = (u) => String(u || '').replace(/[?#].*$/, '').replace(/\/+$/, '');
  return norm(urlA) === norm(urlB);
}

async function navigateSellerPage(tabId, pageUrl) {
  const pending = waitForTabComplete(tabId);
  await chrome.tabs.update(tabId, { url: pageUrl });
  await pending;
  await sleep(800);
}

/**
 * KP server pri hard navigaciji radi 301 /2 → /1.
 * Zato menjamo stranu klikom / Next routerom u content scriptu.
 */
async function loadAndParseListPage(tabId, pageUrl) {
  const expected = KpParser.parseSellerListUrl(pageUrl);
  if (!expected) throw new Error('Loš URL liste: ' + pageUrl);

  if (expected.page <= 1) {
    const tab = await chrome.tabs.get(tabId);
    if (!sameSellerPage(tab.url, pageUrl)) {
      await navigateSellerPage(tabId, expected.pageUrl);
    }
    const res = await scrapeCurrentTab(tabId);
    if (!res?.ok) throw new Error(res?.error || 'Lista nije parsirana.');
    return res;
  }

  const res = await tabMessage(tabId, { action: 'goToListPage', page: expected.page });
  if (!res?.ok) throw new Error(res?.error || 'Strana ' + expected.page + ' nije učitana.');
  return res;
}

async function fetchAndParseList(tabId, pageUrl) {
  return loadAndParseListPage(tabId, pageUrl);
}

async function fetchAndParseDetail(tabId, adUrl) {
  const res = await tabMessage(tabId, { action: 'fetchAndParseDetail', url: adUrl });
  if (!res?.ok) throw new Error(res?.error || 'Detalj nije parsiran.');
  return res.detail;
}

function isLocationLikeText(text) {
  const t = String(text || '').trim();
  if (!t) return true;
  if (t.length < 40 && /\|/.test(t)) return true;
  if (/^(beograd|novi sad|niš|nis|novi bg)/i.test(t) && t.length < 50) return true;
  return false;
}

function cleanDescription(text, fallback) {
  let t = String(text || '').trim();
  if (typeof KpParser !== 'undefined' && KpParser.cleanAdDescription) {
    t = KpParser.cleanAdDescription(t);
  } else {
    t = t.replace(/^opis\s+oglasa\s*/i, '').trim();
  }
  // Ne koristiti looksLikeGarbage ovde — odbacuje sve duže od 80 znakova
  if (t && !isLocationLikeText(t) && !isJsonJunk(t)) return t;
  let f = String(fallback || '').trim();
  if (typeof KpParser !== 'undefined' && KpParser.cleanAdDescription) {
    f = KpParser.cleanAdDescription(f);
  }
  if (f && !isLocationLikeText(f) && !isJsonJunk(f)) return f;
  return '';
}

function isJsonJunk(value) {
  const t = String(value || '');
  if (!t) return true;
  return /passwordRules|initialReduxState|__NEXT_DATA__|"options"\s*:|scriptLoader/.test(t);
}

function looksLikeGarbage(value) {
  // Samo za kratka polja (stanje), NE za opise
  if (typeof KpParser !== 'undefined' && KpParser.looksLikeGarbageField) {
    return KpParser.looksLikeGarbageField(value);
  }
  const t = String(value || '');
  return !t || t.length > 80 || /[{}\[\]"]|passwordRules|initialReduxState/.test(t);
}

function pickCondition(detailCond, baseCond) {
  const d = String(detailCond || '').trim();
  const b = String(baseCond || '').trim();
  if (d && !looksLikeGarbage(d)) return d;
  if (b && !looksLikeGarbage(b)) return b;
  return '';
}

function pickListingType(detailType, baseType) {
  const d = String(detailType || '').trim();
  const b = String(baseType || '').trim() || 'sell';
  if (d === 'sell' || d === 'buy' || d === 'trade') return d;
  return b;
}

function pickDescription(detailDesc, baseDesc, baseShort) {
  const d = cleanDescription(detailDesc, '');
  const b = cleanDescription(baseDesc, baseShort);
  // Preferiraj duži (detaljni) opis
  if (d && b) return d.length >= b.length ? d : b;
  return d || b || '';
}

function finalizeAd(ad) {
  const desc = pickDescription(ad.description, ad.description_short, '');
  ad.description = desc;
  if (isLocationLikeText(ad.description_short) || !String(ad.description_short || '').trim()) {
    ad.description_short = desc.slice(0, 120) + (desc.length > 120 ? '...' : '');
  }
  ad.condition = pickCondition(ad.condition, '');
  if (!ad.listing_type) ad.listing_type = 'sell';
  return ad;
}

function mergeDescriptionOnly(base, detail) {
  if (!detail) return finalizeAd(base);
  return finalizeAd({
    ...base,
    description: pickDescription(
      detail.description,
      base.description,
      base.description_short
    ),
    condition: pickCondition(detail.condition, base.condition),
    posted_at: detail.posted_at || base.posted_at,
    location: detail.location || base.location,
  });
}

function mergeAd(base, detail) {
  if (!detail) return finalizeAd(base);
  const combinedImages = [...(base.images || []), ...(detail.images || [])];
  const seen = new Set();
  const images = [];
  combinedImages.forEach((url) => {
    const u = String(url || '').replace(/\/tmb-\d+x\d+-/, '/');
    if (!u || seen.has(u)) return;
    seen.add(u);
    images.push(u);
  });
  return finalizeAd({
    ...base,
    title: detail.title || base.title,
    description: pickDescription(
      detail.description,
      base.description,
      base.description_short
    ),
    price: detail.price != null ? detail.price : base.price,
    currency: detail.currency || base.currency,
    price_text: detail.price_text || base.price_text,
    location: detail.location || base.location,
    condition: pickCondition(detail.condition, base.condition),
    listing_type: pickListingType(detail.listing_type, base.listing_type),
    images: images.slice(0, 10),
    category: detail.category || base.category,
    posted_at: detail.posted_at || base.posted_at,
  });
}

function estimatePagesFromSeller(seller) {
  const n = parseInt(seller?.total_ads, 10);
  if (!n || n < 1) return 0;
  if (typeof KpParser !== 'undefined' && KpParser.estimatePagesFromTotalAds) {
    return KpParser.estimatePagesFromTotalAds(n);
  }
  return Math.ceil(n / 28);
}

async function savePartialExport({ seller, ads, url, pageLimit, pagesDone, mode, skipped, beforeFilter, errors, wantDescriptions, includeDetails, partial, pagesPlanned }) {
  const payload = {
    exported_at: new Date().toISOString(),
    source: 'kupujemprodajem',
    seller,
    ads,
    meta: {
      start_url: url,
      pages_scraped: pagesDone,
      pages_limit: pageLimit,
      pages_planned: pagesPlanned != null ? pagesPlanned : pagesDone,
      total_before_filter: beforeFilter != null ? beforeFilter : ads.length,
      total_ads: ads.length,
      skipped_ads: skipped || 0,
      filter_mode: mode,
      include_details: !!includeDetails,
      include_descriptions: !!wantDescriptions,
      partial: !!partial,
      errors: errors || [],
    },
  };
  await chrome.storage.local.set({ kpLastExport: payload });
  return payload;
}

async function runScrape({
  tabId,
  url,
  allPages,
  includeDetails,
  includeDescriptions,
  delayMs,
  filterMode,
  skipKeywords,
  maxPages,
}) {
  const errors = [];
  const delay = Math.max(400, delayMs || 800);
  const wantDescriptions = !!includeDescriptions || !!includeDetails;
  const mode = filterMode || 'all';
  const pageLimit = Math.max(1, Math.min(1000, parseInt(maxPages, 10) || 999));

  let first;
  try {
    first = await scrapeCurrentTab(tabId);
  } catch (e) {
    first = { ok: false, error: String(e.message || e) };
  }

  if (!first.ok) {
    return { ok: false, error: first.error || 'Neuspelo čitanje trenutne strane.' };
  }

  throwIfAborted();
  await setJob({
    phase: 'pages',
    message: 'Čitam stranice…',
    pagesDone: 0,
    pagesPlanned: 0,
  });

  let seller = first.seller;
  let adsMap = new Map();

  const listInfo = KpParser.parseSellerListUrl(url || first.url);
  let pageUrls = [];

  if (allPages && listInfo) {
    let detectedMax = Math.max(
      Number(first.pagination?.max) || 0,
      (first.pagination?.pageUrls && first.pagination.pageUrls.length) || 0,
      estimatePagesFromSeller(first.seller)
    );
    // DOM često pokaže samo 1–2 u paginaciji, a seller ima stotine strana
    if (detectedMax <= 10 && pageLimit > detectedMax) {
      const est = estimatePagesFromSeller(first.seller);
      detectedMax = Math.max(detectedMax, est > 10 ? est : pageLimit);
    }
    if (detectedMax <= 1 && pageLimit > 1) {
      detectedMax = pageLimit;
    }
    const pagesToFetch = Math.min(pageLimit, Math.max(detectedMax, 1));
    for (let p = 1; p <= pagesToFetch; p++) {
      pageUrls.push(KpParser.buildSellerPageUrl(listInfo.basePath, p));
    }
  } else if (allPages && first.pagination?.pageUrls?.length) {
    pageUrls = first.pagination.pageUrls
      .map((p) => (p.startsWith('http') ? p : 'https://www.kupujemprodajem.com' + (p.startsWith('/') ? p : '/' + p)))
      .slice(0, pageLimit);
  } else {
    pageUrls = [listInfo ? listInfo.pageUrl : String(first.url || url).replace(/[?#].*$/, '')];
  }

  await setJob({
    phase: 'pages',
    message: 'Planirano strana: ' + pageUrls.length,
    pagesDone: 0,
    pagesPlanned: pageUrls.length,
    adsCount: first.ads?.length || 0,
  });

  const currentPageUrl = listInfo
    ? listInfo.pageUrl
    : String(first.url || url).replace(/[?#].*$/, '');

  const currentNorm = currentPageUrl.replace(/\/+$/, '');
  let usedCurrent = false;
  for (const pageUrl of pageUrls) {
    if (pageUrl.replace(/\/+$/, '') === currentNorm) {
      first.ads.forEach((ad) => adsMap.set(ad.source_id, ad));
      usedCurrent = true;
      break;
    }
  }
  if (!usedCurrent) {
    first.ads.forEach((ad) => adsMap.set(ad.source_id, ad));
  }

  let pagesDone = usedCurrent ? 1 : 0;
  let emptyStreak = 0;
  for (const pageUrl of pageUrls) {
    throwIfAborted();
    if (pageUrl.replace(/\/+$/, '') === currentNorm && usedCurrent) continue;
    try {
      await sleep(delay);
      throwIfAborted();
      const parsed = await loadAndParseListPage(tabId, pageUrl);
      if (!seller.username && parsed.seller?.username) seller = parsed.seller;
      if (parsed.seller?.total_ads && !seller.total_ads) seller = { ...seller, ...parsed.seller };
      const got = parsed.ads || [];
      let newCount = 0;
      got.forEach((ad) => {
        if (!adsMap.has(ad.source_id)) {
          adsMap.set(ad.source_id, ad);
          newCount++;
        }
      });
      pagesDone++;
      await setJob({
        phase: 'pages',
        message:
          'Strana ' +
          pagesDone +
          '/' +
          pageUrls.length +
          ' · ' +
          adsMap.size +
          ' oglasa (+' +
          newCount +
          ' novih)',
        pagesDone,
        pagesPlanned: pageUrls.length,
        adsCount: adsMap.size,
      });
      // Ako strana nema novih oglasa (ponovo ista lista) — to je signal problema
      if (!got.length) {
        emptyStreak++;
        if (emptyStreak >= 2) {
          errors.push({ page: pageUrl, error: 'Dve prazne stranice zaredom — prekid paginacije.' });
          break;
        }
      } else if (newCount === 0) {
        emptyStreak++;
        errors.push({ page: pageUrl, error: 'Strana bez novih oglasa (mogući duplikat).' });
        if (emptyStreak >= 3) {
          errors.push({ page: pageUrl, error: 'Prekid — KP vraća iste oglase.' });
          break;
        }
      } else {
        emptyStreak = 0;
      }
      if (pagesDone % 5 === 0 || pagesDone === pageUrls.length) {
        await savePartialExport({
          seller,
          ads: Array.from(adsMap.values()).map(finalizeAd),
          url,
          pageLimit,
          pagesDone,
          pagesPlanned: pageUrls.length,
          mode,
          errors,
          wantDescriptions,
          includeDetails,
          partial: true,
        });
      }
    } catch (e) {
      if (e?.code === 'ABORTED') throw e;
      errors.push({ page: pageUrl, error: String(e.message || e) });
      emptyStreak++;
      if (emptyStreak >= 3) break;
    }
  }

  let ads = Array.from(adsMap.values());
  const beforeFilter = ads.length;
  const filtered = KpParser.filterAds(ads, { filterMode: mode, skipKeywords });
  ads = filtered.kept;
  const skipped = filtered.skipped;

  await savePartialExport({
    seller,
    ads: ads.map(finalizeAd),
    url,
    pageLimit,
    pagesDone,
    pagesPlanned: pageUrls.length,
    mode,
    skipped,
    beforeFilter,
    errors,
    wantDescriptions,
    includeDetails,
    partial: wantDescriptions,
  });

  if (includeDetails) {
    await setJob({
      phase: 'details',
      message: 'Detalji 0/' + ads.length,
      adsCount: ads.length,
      pagesDone,
      pagesPlanned: pageUrls.length,
    });
    const detailed = [];
    for (let i = 0; i < ads.length; i++) {
      throwIfAborted();
      const ad = ads[i];
      try {
        await sleep(delay);
        throwIfAborted();
        const detail = await fetchAndParseDetail(tabId, ad.source_url);
        detailed.push(mergeAd(ad, detail));
      } catch (e) {
        if (e?.code === 'ABORTED') throw e;
        errors.push({ ad: ad.source_url, error: String(e.message || e) });
        detailed.push(finalizeAd(ad));
      }
      if ((i + 1) % 5 === 0 || i + 1 === ads.length) {
        await setJob({
          phase: 'details',
          message: 'Detalji ' + (i + 1) + '/' + ads.length,
          adsCount: ads.length,
        });
      }
      if ((i + 1) % 25 === 0) {
        await savePartialExport({
          seller,
          ads: [...detailed, ...ads.slice(i + 1).map(finalizeAd)],
          url,
          pageLimit,
          pagesDone,
          pagesPlanned: pageUrls.length,
          mode,
          skipped,
          beforeFilter,
          errors,
          wantDescriptions,
          includeDetails,
          partial: true,
        });
      }
    }
    ads = detailed;
  } else if (wantDescriptions) {
    await setJob({
      phase: 'descriptions',
      message: 'Opisi 0/' + ads.length,
      adsCount: ads.length,
      pagesDone,
      pagesPlanned: pageUrls.length,
    });
    const withDesc = [];
    for (let i = 0; i < ads.length; i++) {
      throwIfAborted();
      const ad = ads[i];
      try {
        await sleep(delay);
        throwIfAborted();
        const detail = await fetchAndParseDetail(tabId, ad.source_url);
        withDesc.push(mergeDescriptionOnly(ad, detail));
      } catch (e) {
        if (e?.code === 'ABORTED') throw e;
        errors.push({ ad: ad.source_url, error: 'Opis: ' + String(e.message || e) });
        withDesc.push(finalizeAd(ad));
      }
      if ((i + 1) % 5 === 0 || i + 1 === ads.length) {
        await setJob({
          phase: 'descriptions',
          message: 'Opisi ' + (i + 1) + '/' + ads.length,
          adsCount: ads.length,
        });
      }
      if ((i + 1) % 25 === 0) {
        await savePartialExport({
          seller,
          ads: [...withDesc, ...ads.slice(i + 1).map(finalizeAd)],
          url,
          pageLimit,
          pagesDone,
          pagesPlanned: pageUrls.length,
          mode,
          skipped,
          beforeFilter,
          errors,
          wantDescriptions,
          includeDetails,
          partial: true,
        });
      }
    }
    ads = withDesc;
  } else {
    ads = ads.map(finalizeAd);
  }

  ads = ads.map(finalizeAd);

  const payload = await savePartialExport({
    seller,
    ads,
    url,
    pageLimit,
    pagesDone,
    pagesPlanned: pageUrls.length,
    mode,
    skipped,
    beforeFilter,
    errors,
    wantDescriptions,
    includeDetails,
    partial: false,
  });
  return { ok: true, payload };
}

/** Job state — scrape živi u service workeru, popup samo prati. */
let abortScrape = false;
let scrapePromise = null;

async function setJob(partial) {
  const data = await chrome.storage.local.get('kpScrapeJob');
  const job = Object.assign({}, data.kpScrapeJob || {}, partial, { updatedAt: Date.now() });
  await chrome.storage.local.set({ kpScrapeJob: job });
  try {
    if (job.running) {
      const label =
        job.pagesDone && job.pagesPlanned
          ? String(job.pagesDone)
          : job.adsCount
            ? String(Math.min(999, job.adsCount))
            : '…';
      await chrome.action.setBadgeText({ text: label.slice(0, 4) });
      await chrome.action.setBadgeBackgroundColor({ color: '#1a7a3a' });
    } else if (job.error) {
      await chrome.action.setBadgeText({ text: '!' });
      await chrome.action.setBadgeBackgroundColor({ color: '#c0392b' });
    } else if (job.finishedOk) {
      await chrome.action.setBadgeText({ text: 'OK' });
      await chrome.action.setBadgeBackgroundColor({ color: '#1a7a3a' });
    }
  } catch (_) {
    /* ignore badge errors */
  }
  return job;
}

function throwIfAborted() {
  if (abortScrape) {
    const err = new Error('Otkazano od strane korisnika.');
    err.code = 'ABORTED';
    throw err;
  }
}

async function startScrapeJob(msg) {
  if (scrapePromise) {
    return { ok: false, error: 'Već radi u pozadini. Sačekaj ili Otkaži.' };
  }

  abortScrape = false;
  await setJob({
    running: true,
    finishedOk: false,
    error: null,
    message: 'Pokrenuto u pozadini — smeš zatvoriti ovaj prozor.',
    phase: 'start',
    pagesDone: 0,
    pagesPlanned: 0,
    adsCount: 0,
    startedAt: Date.now(),
    finishedAt: null,
  });

  try {
    await chrome.alarms.create('kpScrapeKeepAlive', { periodInMinutes: 1 });
  } catch (_) {
    /* alarms optional */
  }

  scrapePromise = (async () => {
    try {
      const result = await runScrape(msg);
      if (!result?.ok) {
        await setJob({
          running: false,
          finishedOk: false,
          error: result?.error || 'Nepoznata greška',
          message: result?.error || 'Greška',
          finishedAt: Date.now(),
        });
        return result;
      }
      const p = result.payload;
      await setJob({
        running: false,
        finishedOk: true,
        error: null,
        message:
          'Gotovo: ' +
          (p.ads?.length || 0) +
          ' oglasa, strana ' +
          (p.meta?.pages_scraped || '?') +
          '/' +
          (p.meta?.pages_planned || '?') +
          (p.meta?.total_before_filter
            ? ' (pre filtera ' + p.meta.total_before_filter + ')'
            : '') +
          (p.meta?.errors?.length ? ', grešaka ' + p.meta.errors.length : ''),
        phase: 'done',
        pagesDone: p.meta?.pages_scraped || 0,
        pagesPlanned: p.meta?.pages_planned || 0,
        adsCount: p.ads?.length || 0,
        finishedAt: Date.now(),
      });
      return result;
    } catch (e) {
      const aborted = e?.code === 'ABORTED' || /otkazano/i.test(String(e?.message || e));
      await setJob({
        running: false,
        finishedOk: false,
        error: String(e?.message || e),
        message: aborted ? 'Otkazano.' : String(e?.message || e),
        phase: aborted ? 'aborted' : 'error',
        finishedAt: Date.now(),
      });
      return { ok: false, error: String(e?.message || e) };
    } finally {
      scrapePromise = null;
      try {
        await chrome.alarms.clear('kpScrapeKeepAlive');
      } catch (_) {}
    }
  })();

  // Ne čekamo scrapePromise — popup može da se zatvori odmah
  return { ok: true, started: true };
}

// Ako Chrome restartuje SW usred joba
chrome.storage.local.get('kpScrapeJob').then((data) => {
  if (data.kpScrapeJob?.running && !scrapePromise) {
    setJob({
      running: false,
      finishedOk: false,
      error: 'Chrome je ugasio pozadinski proces. Pokreni scrape ponovo.',
      message: 'Prekinuto — pokreni ponovo.',
      phase: 'interrupted',
      finishedAt: Date.now(),
    });
  }
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'kpScrapeKeepAlive' && scrapePromise) {
    // tick — drži SW budnim dok job traje
    chrome.storage.local.get('kpScrapeJob').then(() => {});
  }
});

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg?.action === 'startScrape' || msg?.action === 'scrape') {
    startScrapeJob(msg)
      .then(sendResponse)
      .catch((e) => sendResponse({ ok: false, error: String(e.message || e) }));
    return true;
  }

  if (msg?.action === 'cancelScrape') {
    abortScrape = true;
    setJob({ message: 'Otkazivanje…', phase: 'canceling' }).then(() => {
      sendResponse({ ok: true });
    });
    return true;
  }

  if (msg?.action === 'getJobStatus') {
    chrome.storage.local.get('kpScrapeJob').then((data) => {
      sendResponse({
        ok: true,
        job: data.kpScrapeJob || null,
        running: !!(scrapePromise || data.kpScrapeJob?.running),
      });
    });
    return true;
  }

  if (msg?.action === 'getLastExport') {
    chrome.storage.local.get('kpLastExport').then((data) => {
      sendResponse({ ok: true, payload: data.kpLastExport || null });
    });
    return true;
  }
});
