'use strict';

function ensureParser() {
  if (typeof KpParser === 'undefined') {
    throw new Error('KpParser nije učitan na stranici.');
  }
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function textOf(el) {
  return el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '';
}

function currentListInfo() {
  ensureParser();
  return KpParser.parseSellerListUrl(location.href);
}

function firstAdIdFromDom() {
  const a = document.querySelector('a[href*="/oglas/"]');
  if (!a) return '';
  const m = (a.getAttribute('href') || '').match(/\/oglas\/(\d+)/);
  return m ? m[1] : '';
}

function scrapeListNow() {
  ensureParser();
  const info = currentListInfo();
  const html = document.documentElement.outerHTML;
  const fromNext = KpParser.parseAdsFromNextData(html, location.href);
  const nextPage = fromNext?.page || 0;

  // Ako URL kaže stranu N a NEXT_DATA još uvek drugu — čitaj DOM (SPA)
  if (info && fromNext?.ads?.length && (!nextPage || nextPage === info.page)) {
    const parsed = KpParser.parseListHtml(html, location.href);
    return {
      ok: true,
      seller: parsed.seller,
      ads: parsed.ads,
      pagination: parsed.pagination,
      url: location.href,
      source: 'next',
    };
  }

  const ads = KpParser.parseListPageFromDocument(document, location.href, null, {
    skipNextData: true,
  });
  const seller = KpParser.parseSellerFromDocument(document, location.href);
  let pagination = KpParser.parsePaginationFromDocument(document, location.href);
  if (fromNext?.pageCount > (pagination.max || 0)) {
    pagination.max = fromNext.pageCount;
  }
  return {
    ok: true,
    seller,
    ads,
    pagination,
    url: location.href,
    source: 'dom',
  };
}

async function waitForPage(pageNum, previousFirstId, timeoutMs) {
  const limit = timeoutMs || 20000;
  const start = Date.now();
  while (Date.now() - start < limit) {
    const info = currentListInfo();
    const firstId = firstAdIdFromDom();
    if (info && info.page === pageNum) {
      // sačekaj da se lista zameni
      if (!previousFirstId || firstId !== previousFirstId || pageNum === 1) {
        await sleep(400);
        return true;
      }
    }
    await sleep(250);
  }
  const info = currentListInfo();
  return !!(info && info.page === pageNum);
}

async function goToListPage(pageNum) {
  ensureParser();
  const info = currentListInfo();
  if (!info) throw new Error('Nisi na listi svi-oglasi prodavca.');
  if (info.page === pageNum) {
    return scrapeListNow();
  }

  const prevFirst = firstAdIdFromDom();
  const targetPath = '/' + info.username + '/svi-oglasi/' + info.user_id + '/' + pageNum;
  let clicked = false;

  // 1) Link sa tačnim href-om
  const anchors = Array.from(document.querySelectorAll('a[href*="/svi-oglasi/"]'));
  for (const a of anchors) {
    const u = KpParser.parseSellerListUrl(a.href);
    if (u && String(u.user_id) === String(info.user_id) && u.page === pageNum) {
      a.click();
      clicked = true;
      break;
    }
  }

  // 2) Dugme/li sa brojem strane
  if (!clicked) {
    const nodes = Array.from(
      document.querySelectorAll('a, button, [role="button"], li, span')
    );
    const exact = nodes.find((el) => textOf(el) === String(pageNum));
    if (exact) {
      (exact.closest('a, button, [role="button"]') || exact).click();
      clicked = true;
    }
  }

  // 3) Sledeća
  if (!clicked && pageNum === info.page + 1) {
    const next = Array.from(document.querySelectorAll('a, button, [role="button"], span')).find(
      (el) => /^sledeća|^next$/i.test(textOf(el))
    );
    if (next) {
      (next.closest('a, button, [role="button"]') || next).click();
      clicked = true;
    }
  }

  // 4) Next router
  if (!clicked && window.next && window.next.router && typeof window.next.router.push === 'function') {
    try {
      await window.next.router.push(targetPath);
      clicked = true;
    } catch (_) {}
  }

  // 5) history + popstate (ponekad okini CSR)
  if (!clicked) {
    try {
      history.pushState({}, '', targetPath);
      window.dispatchEvent(new PopStateEvent('popstate'));
      clicked = true;
    } catch (_) {}
  }

  if (!clicked) {
    throw new Error('Ne mogu da otvorim stranu ' + pageNum + ' (nema paginacija kontrole).');
  }

  const ok = await waitForPage(pageNum, prevFirst, 20000);
  if (!ok) {
    // još jednom proba preko routera
    if (window.next && window.next.router && typeof window.next.router.push === 'function') {
      await window.next.router.push(targetPath);
      await waitForPage(pageNum, prevFirst, 10000);
    }
  }

  const after = currentListInfo();
  if (!after || after.page !== pageNum) {
    throw new Error(
      'KP nije prešao na stranu ' +
        pageNum +
        ' (ostao na ' +
        (after ? after.page : '?') +
        ').'
    );
  }

  await sleep(500);
  return scrapeListNow();
}

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg?.action === 'fetchPageHtml') {
    fetch(msg.url, {
      credentials: 'include',
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Accept-Language': 'sr,en;q=0.9',
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('HTTP ' + res.status + ' za ' + msg.url);
        return res.text();
      })
      .then((html) => sendResponse({ ok: true, html }))
      .catch((err) => sendResponse({ ok: false, error: String(err?.message || err) }));
    return true;
  }

  if (msg?.action === 'parseListHtml') {
    try {
      ensureParser();
      const parsed = KpParser.parseListHtml(msg.html || '', msg.url || location.href);
      sendResponse({ ok: true, seller: parsed.seller, ads: parsed.ads, pagination: parsed.pagination });
    } catch (err) {
      sendResponse({ ok: false, error: String(err?.message || err) });
    }
    return true;
  }

  if (msg?.action === 'parseDetailHtml') {
    try {
      ensureParser();
      const detail = KpParser.parseDetailHtml(msg.html || '', msg.url || '');
      sendResponse({ ok: true, detail });
    } catch (err) {
      sendResponse({ ok: false, error: String(err?.message || err) });
    }
    return true;
  }

  if (msg?.action === 'fetchAndParseList') {
    // Zastarelo za paginaciju (server radi 301→1). Koristi goToListPage.
    const info = KpParser.parseSellerListUrl(msg.url || '');
    if (info && info.page > 1) {
      goToListPage(info.page)
        .then((parsed) => sendResponse(parsed))
        .catch((err) => sendResponse({ ok: false, error: String(err?.message || err) }));
      return true;
    }
    fetch(msg.url, {
      credentials: 'include',
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Accept-Language': 'sr,en;q=0.9',
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('HTTP ' + res.status + ' za ' + msg.url);
        return res.text();
      })
      .then((html) => {
        ensureParser();
        const parsed = KpParser.parseListHtml(html, msg.url);
        sendResponse({
          ok: true,
          seller: parsed.seller,
          ads: parsed.ads,
          pagination: parsed.pagination,
          url: msg.url,
        });
      })
      .catch((err) => sendResponse({ ok: false, error: String(err?.message || err) }));
    return true;
  }

  if (msg?.action === 'goToListPage') {
    goToListPage(Number(msg.page) || 1)
      .then((parsed) => sendResponse(parsed))
      .catch((err) => sendResponse({ ok: false, error: String(err?.message || err) }));
    return true;
  }

  if (msg?.action === 'fetchAndParseDetail') {
    fetch(msg.url, {
      credentials: 'include',
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Accept-Language': 'sr,en;q=0.9',
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('HTTP ' + res.status + ' za ' + msg.url);
        return res.text();
      })
      .then((html) => {
        ensureParser();
        const detail = KpParser.parseDetailHtml(html, msg.url);
        sendResponse({ ok: true, detail });
      })
      .catch((err) => sendResponse({ ok: false, error: String(err?.message || err) }));
    return true;
  }

  if (msg?.action === 'scrapeCurrentPage') {
    try {
      sendResponse(scrapeListNow());
    } catch (err) {
      sendResponse({ ok: false, error: String(err?.message || err) });
    }
    return true;
  }
});
