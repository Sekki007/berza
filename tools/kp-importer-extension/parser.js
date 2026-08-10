/**
 * KP DOM parser — radi u content scriptu i u service workeru (preko DOMParser).
 * Selektori imaju više fallback varijanti jer KP menja markup.
 */
(function (global) {
  'use strict';

  const KP_ORIGIN = 'https://www.kupujemprodajem.com';

  function absUrl(href, base) {
    if (!href) return '';
    try {
      return new URL(href, base || KP_ORIGIN).href;
    } catch {
      return href;
    }
  }

  function extractAdId(url) {
    const m = String(url).match(/\/oglas\/(\d+)/i);
    return m ? m[1] : '';
  }

  function parsePrice(text) {
    const raw = String(text || '').replace(/\s+/g, ' ').trim();
    if (!raw) return { price: null, currency: '', price_text: '' };

    const eur = raw.match(/([\d.,]+)\s*€/i) || raw.match(/([\d.,]+)\s*eur/i);
    if (eur) {
      const num = parseFloat(eur[1].replace(/\./g, '').replace(',', '.'));
      return { price: Number.isFinite(num) ? num : null, currency: 'EUR', price_text: raw };
    }

    const rsd = raw.match(/([\d.,]+)\s*(?:din|rsd)/i);
    if (rsd) {
      const num = parseFloat(rsd[1].replace(/\./g, '').replace(',', '.'));
      return { price: Number.isFinite(num) ? num : null, currency: 'RSD', price_text: raw };
    }

    if (/kupujem|tražim|trazim/i.test(raw)) {
      return { price: null, currency: '', price_text: raw, listing_type: 'buy' };
    }

    return { price: null, currency: '', price_text: raw };
  }

  function mapKpCondition(raw) {
    const c = String(raw || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
    if (!c || c.length > 60 || /[{}\[\]"]/.test(c)) return '';
    if (c === 'new' || c === 'novo' || /^novo\b/.test(c)) return 'Novo';
    if (
      c === 'as-new' ||
      c === 'like-new' ||
      c === 'nekorisceno' ||
      c.includes('nekorisceno') ||
      c.includes('kao novo')
    ) {
      return 'Kao novo';
    }
    if (c === 'damaged' || c === 'broken' || c === 'faulty' || c.includes('ostecen') || c.includes('za delove')) {
      return 'Oštećeno/Za delove';
    }
    if (c === 'used' || c === 'korisceno' || c.includes('polovn') || c.includes('koriscen')) {
      return 'Polovno';
    }
    return '';
  }

  function htmlToPlainText(html) {
    const raw = String(html || '');
    if (!raw) return '';
    return raw
      .replace(/<\s*br\s*\/?>/gi, '\n')
      .replace(/<\/\s*p\s*>/gi, '\n')
      .replace(/<\/\s*div\s*>/gi, '\n')
      .replace(/<\/\s*li\s*>/gi, '\n')
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&lt;/gi, '<')
      .replace(/&gt;/gi, '>')
      .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(Number(n)))
      .replace(/&quot;/gi, '"')
      .replace(/\r/g, '')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .replace(/[ \t]{2,}/g, ' ')
      .trim();
  }

  function cleanAdDescription(text) {
    let t = htmlToPlainText(text);
    if (!t) return '';
    if (/passwordRules|initialReduxState|__NEXT_DATA__|"options"\s*:/.test(t)) return '';
    t = t.replace(/^opis\s+oglasa\s*/i, '').trim();
    if (t.length > 12000) t = t.slice(0, 12000).trim();
    return t;
  }

  function looksLikeGarbageField(value) {
    const t = String(value || '');
    if (!t) return true;
    if (t.length > 80) return true;
    return /[{}\[\]"]|passwordRules|initialReduxState|options"\s*:/.test(t);
  }

  function parseNextDataJson(html) {
    const m = String(html || '').match(/<script id="__NEXT_DATA__"[^>]*>([\s\S]*?)<\/script>/i);
    if (!m) return null;
    try {
      return JSON.parse(m[1]);
    } catch {
      return null;
    }
  }

  /**
   * Detalj oglasa iz __NEXT_DATA__ → initialReduxState.ad.byId[id]
   */
  function parseDetailFromNextData(html, pageUrl) {
    const data = parseNextDataJson(html);
    if (!data) return null;

    const byId =
      data?.props?.initialReduxState?.ad?.byId ||
      data?.props?.pageProps?.initialReduxState?.ad?.byId ||
      null;
    if (!byId || typeof byId !== 'object') return null;

    const wantedId = extractAdId(pageUrl || '');
    let raw = wantedId && byId[wantedId] ? byId[wantedId] : null;
    if (!raw) {
      const keys = Object.keys(byId);
      if (keys.length === 1) raw = byId[keys[0]];
    }
    if (!raw || typeof raw !== 'object') return null;

    const sourceId = String(raw.id || wantedId || '').trim();
    const sourceUrl = absUrl(raw.adUrl || pageUrl || '', KP_ORIGIN);
    const currency = mapKpCurrency(raw.currencyAcronym || raw.currency);
    const priceNum =
      raw.priceNumber != null && Number.isFinite(Number(raw.priceNumber))
        ? Number(raw.priceNumber)
        : raw.price != null && Number.isFinite(Number(raw.price))
          ? Number(raw.price)
          : null;

    const images = [];
    const photoList = Array.isArray(raw.photos) ? raw.photos : [];
    photoList.forEach((p) => {
      if (!p || typeof p !== 'object') return;
      const u = p.original || p.fullscreen || p.thumbnail || '';
      if (u) images.push(absUrl(u, 'https://images.kupujemprodajem.com'));
    });
    if (!images.length && Array.isArray(raw.photosBig)) {
      raw.photosBig.forEach((u) => {
        if (u) images.push(absUrl(u, 'https://images.kupujemprodajem.com'));
      });
    }
    if (!images.length && raw.image) {
      images.push(absUrl(raw.image, 'https://images.kupujemprodajem.com'));
    }

    const listingType =
      raw.type === 'buy' || raw.ad_type === 'buy'
        ? 'buy'
        : raw.isExchange || raw.exchange
          ? 'trade'
          : 'sell';

    const condition =
      mapKpCondition(raw.conditionId) || mapKpCondition(raw.condition) || '';

    return {
      source_id: sourceId,
      source_url: sourceUrl,
      title: String(raw.name || raw.formattedName || '').trim(),
      description: cleanAdDescription(raw.description || raw.descriptionSnippetDecoded || ''),
      price: priceNum,
      currency,
      price_text: String(raw.priceText || raw.priceDisplay || '').trim() || formatPriceText(priceNum, currency),
      location: String(raw.location || '').trim(),
      condition,
      listing_type: listingType,
      images: uniqueImages(images).slice(0, 10),
      category: String(raw.categoryName || '').trim(),
      group_name: String(raw.groupName || '').trim(),
      posted_at: String(raw.posted || raw.postedDesc || '').trim(),
      views: raw.viewCount != null ? Number(raw.viewCount) : null,
      favorites: raw.favoriteCount != null ? Number(raw.favoriteCount) : null,
    };
  }

  function mapKpCurrency(raw) {
    const c = String(raw || '').toLowerCase();
    if (c === 'rsd' || c === 'din') return 'RSD';
    if (c === 'eur' || c === '€' || c === 'euro') return 'EUR';
    return '';
  }

  function formatPriceText(price, currency) {
    if (price == null || price === '' || !Number.isFinite(Number(price))) return '';
    const n = Number(price);
    if (currency === 'RSD') {
      return n.toLocaleString('sr-RS') + ' din';
    }
    return n + ' €';
  }

  /**
   * Pouzdano čitanje liste iz __NEXT_DATA__ (ima price, currency, ad_url…).
   * @returns {{ ads: array, sellerHint: object, pageCount: number } | null}
   */
  function parseAdsFromNextData(html, pageUrl) {
    const data = parseNextDataJson(html);
    if (!data) return null;

    const adsRaw =
      data?.props?.initialReduxState?.search?.lastSearchResult?.ads ||
      data?.props?.pageProps?.initialReduxState?.search?.lastSearchResult?.ads ||
      null;
    if (!Array.isArray(adsRaw) || adsRaw.length === 0) return null;

    const pageCount =
      Number(
        data?.props?.initialReduxState?.search?.lastSearchResult?.pages ||
          data?.props?.initialReduxState?.search?.lastSearchResult?.totalPages ||
          data?.props?.initialReduxState?.search?.lastSearchResult?.pageCount ||
          data?.props?.pageProps?.initialReduxState?.search?.lastSearchResult?.pages ||
          0
      ) || 0;

    const ads = [];
    let sellerUserId = '';
    adsRaw.forEach((raw) => {
      if (!raw || raw.ad_id == null) return;
      const sourceId = String(raw.ad_id);
      const sourceUrl = absUrl(raw.ad_url || '', pageUrl || KP_ORIGIN);
      if (!sourceUrl || !sourceId) return;

      if (!sellerUserId && raw.user_id) sellerUserId = String(raw.user_id);

      const currency = mapKpCurrency(raw.currency);
      const priceNum =
        raw.price != null && raw.price !== '' && Number.isFinite(Number(raw.price))
          ? Number(raw.price)
          : null;
      const priceText =
        String(raw.price_text || '').trim() || formatPriceText(priceNum, currency);

      const images = [];
      const photo = raw.photo_path1 || raw.photo1_tmb_300x300 || '';
      if (photo) {
        const full = absUrl(
          String(photo).replace(/\/tmb-\d+x\d+-/, '/'),
          'https://images.kupujemprodajem.com'
        );
        if (full) images.push(full);
      }

      const desc =
        String(raw.description_snippet_decoded || raw.description_snippet || '').trim() ||
        String(raw.description_decoded || '').trim();

      ads.push({
        source_id: sourceId,
        source_url: sourceUrl,
        title: String(raw.name_decoded || raw.name || '').trim(),
        price: priceNum,
        currency,
        price_text: priceText,
        listing_type: raw.ad_type === 'buy' ? 'buy' : raw.exchange ? 'trade' : 'sell',
        location: String(raw.location_name || '').trim(),
        description_short: desc,
        description: desc,
        condition: mapKpCondition(raw.condition),
        category: String(raw.category_name || '').trim(),
        group_name: String(raw.group_name || '').trim(),
        images,
        posted_at: String(raw.posted || '').trim(),
        views: raw.view_count != null ? Number(raw.view_count) : null,
        favorites: raw.favorite_count != null ? Number(raw.favorite_count) : null,
      });
    });

    return {
      ads,
      sellerHint: { user_id: sellerUserId },
      pageCount,
      page: Number(data?.props?.initialReduxState?.search?.lastSearchResult?.page) || 0,
    };
  }

  function extractPriceFromCard(card, cardText) {
    if (!card) return parsePrice(cardText);
    const selectors = [
      '[class*="inlinePrice"]',
      '[class*="priceText"]',
      '[class*="__price"]',
      '[class*="adPrice"]',
      '[class*="priceHolder"]',
      '[class*="price"]',
      '[class*="Price"]',
    ];
    for (const sel of selectors) {
      const el = card.querySelector(sel);
      const t = textOf(el);
      if (!t) continue;
      const info = parsePrice(t);
      if (info.price != null) return info;
      if (info.price_text && /€|eur|din|rsd/i.test(info.price_text)) return info;
    }
    // Fallback: traži cenu u tekstu kartice
    const eur = String(cardText || '').match(/([\d.,]+)\s*€/);
    if (eur) return parsePrice(eur[0]);
    const din = String(cardText || '').match(/([\d.]+)\s*din/i);
    if (din) return parsePrice(din[0]);
    return parsePrice(cardText);
  }


  function textOf(el) {
    return el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function isKpPhotoUrl(url) {
    return /images\.kupujemprodajem\.com\/photos\/oglasi\//i.test(String(url || ''));
  }

  function isIgnoredImageUrl(url) {
    const u = String(url || '').toLowerCase();
    return (
      !u ||
      u.includes('logo') ||
      u.includes('favicon') ||
      u.includes('/static/images/') ||
      u.includes('original.png') ||
      u.includes('avatar') ||
      u.includes('placeholder')
    );
  }

  function adIdFromPhotoUrl(url) {
    const m = String(url).match(/\/photos\/oglasi\/(?:\d+\/){2}(\d+)\//);
    return m ? m[1] : '';
  }

  /** tmb-300x300 → puna rezolucija kad postoji */
  function normalizeKpPhotoUrl(url) {
    return String(url).replace(/\/tmb-\d+x\d+-/, '/');
  }

  function uniqueImages(urls) {
    const out = [];
    const seen = new Set();
    (urls || []).forEach((raw) => {
      let url = absUrl(String(raw || '').trim());
      if (!url || !isKpPhotoUrl(url) || isIgnoredImageUrl(url)) return;
      url = normalizeKpPhotoUrl(url);
      if (seen.has(url)) return;
      seen.add(url);
      out.push(url);
    });
    out.sort((a, b) => {
      const aThumb = /tmb-/i.test(a) ? 1 : 0;
      const bThumb = /tmb-/i.test(b) ? 1 : 0;
      return aThumb - bThumb;
    });
    return out.slice(0, 10);
  }

  /** Mapira KP ad ID → URL-ovi slika iz HTML-a (lista i detalj). */
  function buildPhotoMapFromHtml(html) {
    const map = new Map();
    const re = /https:\/\/images\.kupujemprodajem\.com\/photos\/oglasi\/[^"'\\s<>)]+/gi;
    const text = String(html || '');
    for (const m of text.matchAll(re)) {
      const url = m[0];
      if (isIgnoredImageUrl(url)) continue;
      const id = adIdFromPhotoUrl(url);
      if (!id) continue;
      if (!map.has(id)) map.set(id, []);
      const list = map.get(id);
      const full = normalizeKpPhotoUrl(url);
      if (!list.includes(url)) list.push(url);
      if (full !== url && !list.includes(full)) list.push(full);
    }
    for (const [id, urls] of map.entries()) {
      map.set(id, uniqueImages(urls));
    }
    return map;
  }

  function collectImageCandidates(el, base) {
    const urls = [];
    if (!el) return urls;

    const push = (v) => {
      if (!v) return;
      String(v)
        .split(',')
        .map((s) => s.trim().split(/\s+/)[0])
        .forEach((part) => {
          if (part) urls.push(absUrl(part, base));
        });
    };

    if (el.tagName === 'IMG') {
      push(el.getAttribute('src'));
      push(el.getAttribute('data-src'));
      push(el.getAttribute('data-lazy-src'));
      push(el.getAttribute('srcset'));
    } else if (el.tagName === 'SOURCE') {
      push(el.getAttribute('srcset'));
      push(el.getAttribute('src'));
    } else {
      push(el.getAttribute('src'));
      push(el.getAttribute('data-src'));
      push(el.getAttribute('srcset'));
      const style = el.getAttribute('style') || '';
      const bg = style.match(/url\(["']?([^"')]+)["']?\)/i);
      if (bg) push(bg[1]);
    }
    return urls;
  }

  function extractImagesFromCard(card, base, sourceId, photoMap) {
    const urls = [];
    if (photoMap && sourceId && photoMap.has(sourceId)) {
      urls.push(...photoMap.get(sourceId));
    }
    if (card) {
      card.querySelectorAll('img, source, [style*="background"]').forEach((el) => {
        urls.push(...collectImageCandidates(el, base));
      });
    }
    return uniqueImages(urls);
  }

  function extractImagesFromDocument(doc, pageUrl, sourceId) {
    const base = pageUrl || doc.location?.href || KP_ORIGIN;
    const html = doc.documentElement?.outerHTML || '';
    const photoMap = buildPhotoMapFromHtml(html);
    let urls = [];

    if (sourceId && photoMap.has(sourceId)) {
      urls = photoMap.get(sourceId);
    }
    if (urls.length === 0) {
      photoMap.forEach((list) => urls.push(...list));
    }

    doc.querySelectorAll('img, source, picture source').forEach((el) => {
      urls.push(...collectImageCandidates(el, base));
    });

    const og = doc.querySelector('meta[property="og:image"], meta[name="og:image"]');
    if (og) urls.push(absUrl(og.getAttribute('content') || '', base));

    if (sourceId) {
      const forAd = uniqueImages(
        urls.filter((u) => {
          const id = adIdFromPhotoUrl(u);
          return !id || id === sourceId;
        })
      );
      if (forAd.length) return forAd;
    }

    return uniqueImages(urls);
  }

  function findCardRoot(link) {
    let node = link;
    for (let i = 0; i < 8 && node; i++) {
      const tag = (node.tagName || '').toLowerCase();
      const cls = (node.className || '').toString().toLowerCase();
      if (tag === 'article' || tag === 'li') return node;
      if (cls.includes('aditem') || cls.includes('ad-item') || cls.includes('listing')) return node;
      if (node.querySelector && node.querySelector('a[href*="/oglas/"]') === link && textOf(node).length > 40) {
        return node;
      }
      node = node.parentElement;
    }
    return link.closest('article, li, div') || link.parentElement;
  }

  function parseSellerFromDocument(doc, pageUrl) {
    const seller = {
      username: '',
      profile_url: '',
      user_id: '',
      display_name: '',
      location: '',
      member_since: '',
      reviews_positive: null,
      reviews_negative: null,
      total_ads: null,
    };

    const url = pageUrl || doc.location?.href || '';
    const sellerMatch = url.match(/kupujemprodajem\.com\/([^/]+)\/svi-oglasi\/(\d+)/i);
    if (sellerMatch) {
      seller.username = decodeURIComponent(sellerMatch[1]);
      seller.user_id = sellerMatch[2];
      seller.profile_url = absUrl('/' + seller.username, KP_ORIGIN);
    }

    const h1 = doc.querySelector('h1');
    const h1Text = textOf(h1);
    const nameMatch = h1Text.match(/Svi oglasi korisnika:\s*(.+)$/i);
    if (nameMatch) seller.display_name = nameMatch[1].trim();

    const bodyText = textOf(doc.body);
    const memberMatch = bodyText.match(/Član od:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i);
    if (memberMatch) seller.member_since = memberMatch[1];

    const totalMatch = bodyText.match(/Ukupno oglasa:\s*([\d.]+)/i);
    if (totalMatch) {
      seller.total_ads = parseInt(String(totalMatch[1]).replace(/\./g, ''), 10);
    }

    const locCandidates = doc.querySelectorAll('h1 + *, .userLocation, [class*="userLocation"], [class*="UserLocation"]');
    for (const el of locCandidates) {
      const t = textOf(el);
      if (
        t &&
        t.length < 60 &&
        !/oglasi|član|ukupno|mesto|okolina|dodajte|adresar/i.test(t)
      ) {
        seller.location = t;
        break;
      }
    }

    // Ocene: "3.656" + "0" pored adresara / thumbs
    const reviewBlock = bodyText.match(/(\d{1,3}(?:\.\d{3})+)\s+(\d{1,4})\s*Dodajte u adresar/i);
    if (reviewBlock) {
      seller.reviews_positive = parseInt(String(reviewBlock[1]).replace(/\./g, ''), 10);
      seller.reviews_negative = parseInt(reviewBlock[2], 10);
    } else {
      const thumbs = bodyText.match(/👍\s*([\d.]+).*?👎\s*(\d+)/);
      if (thumbs) {
        seller.reviews_positive = parseInt(String(thumbs[1]).replace(/\./g, ''), 10);
        seller.reviews_negative = parseInt(thumbs[2], 10);
      }
    }

    return seller;
  }

  function parseListPageFromDocument(doc, pageUrl, photoMap, options) {
    const base = pageUrl || doc.location?.href || KP_ORIGIN;
    const html = doc.documentElement?.outerHTML || '';
    const skipNext = options && options.skipNextData;
    if (!skipNext) {
      const fromNext = parseAdsFromNextData(html, base);
      const listInfo = parseSellerListUrl(base);
      const nextPage =
        Number(
          parseNextDataJson(html)?.props?.initialReduxState?.search?.lastSearchResult?.page
        ) || 0;
      // Posle SPA navigacije __NEXT_DATA__ ostaje na staroj strani — ne koristi ga
      if (fromNext?.ads?.length && (!listInfo || !nextPage || nextPage === listInfo.page)) {
        return fromNext.ads;
      }
    }

    const map = photoMap || buildPhotoMapFromHtml(html);
    const seen = new Set();
    const ads = [];

    doc.querySelectorAll('a[href*="/oglas/"]').forEach((link) => {
      const href = link.getAttribute('href') || '';
      const url = absUrl(href, base);
      const sourceId = extractAdId(url);
      if (!sourceId || seen.has(sourceId)) return;

      const title = textOf(link);
      if (!title || title.length < 3) return;

      seen.add(sourceId);
      const card = findCardRoot(link);
      const cardText = textOf(card);
      const priceInfo = extractPriceFromCard(card, cardText);

      let location = '';
      const locEl = card.querySelector('[class*="location"], [class*="Location"]');
      if (locEl) location = textOf(locEl);

      let descriptionShort = '';
      const paras = card.querySelectorAll('p, [class*="description"], [class*="Description"]');
      for (const p of paras) {
        const t = textOf(p);
        if (!t || t === title || t.length < 20) continue;
        if (/^\d/.test(t)) continue;
        if (t.length < 45 && /\|/.test(t)) continue;
        if (/^(beograd|novi sad|ni[sš]|kragujevac|novi bg)/i.test(t) && t.length < 50) continue;
        if (location && t === location) continue;
        descriptionShort = t;
        break;
      }

      ads.push({
        source_id: sourceId,
        source_url: url,
        title,
        price: priceInfo.price,
        currency: priceInfo.currency,
        price_text: priceInfo.price_text,
        listing_type: priceInfo.listing_type || (priceInfo.price_text && /kupujem|tražim/i.test(priceInfo.price_text) ? 'buy' : 'sell'),
        location: location || '',
        description_short: descriptionShort,
        description: descriptionShort,
        condition: '',
        images: extractImagesFromCard(card, base, sourceId, map),
        posted_at: '',
        views: null,
        favorites: null,
      });
    });

    return ads;
  }

  /**
   * KP lista: /{user}/svi-oglasi/{userId}/{page} — query (?page=1) se ignoriše.
   */
  function parseSellerListUrl(url) {
    const raw = String(url || '').trim();
    if (!raw) return null;

    let pathOnly = raw;
    try {
      const u = new URL(raw, KP_ORIGIN);
      pathOnly = u.origin + u.pathname;
    } catch {
      pathOnly = raw.replace(/[?#].*$/, '');
    }
    pathOnly = pathOnly.replace(/\/+$/, '');

    const loose = pathOnly.match(/^(https?:\/\/[^/]+)\/([^/]+)\/svi-oglasi\/(\d+)(?:\/(\d+))?$/i);
    if (!loose) return null;

    const origin = loose[1];
    const username = decodeURIComponent(loose[2]);
    const userId = loose[3];
    const page = parseInt(loose[4] || '1', 10) || 1;
    const basePath = origin + '/' + username + '/svi-oglasi/' + userId;
    return {
      username,
      user_id: userId,
      page,
      basePath,
      pageUrl: basePath + '/' + page,
      origin,
    };
  }

  function buildSellerPageUrl(basePath, pageNum) {
    const base = String(basePath || '').replace(/\/+$/, '');
    const p = Math.max(1, parseInt(pageNum, 10) || 1);
    return base + '/' + p;
  }

  /** KP često drži totalPages=0 u Reduxu — max stranica iz linkova u HTML-u. */
  function detectMaxPageFromHtml(html, userId) {
    let max = 0;
    const id = userId ? String(userId) : '\\d+';
    const re = new RegExp('\\/svi-oglasi\\/' + id + '\\/(\\d+)', 'gi');
    let m;
    while ((m = re.exec(String(html || '')))) {
      const n = parseInt(m[1], 10);
      if (n > 0 && n < 5000) max = Math.max(max, n);
    }
    if (!max) {
      const re2 = /\/svi-oglasi\/\d+\/(\d+)/gi;
      while ((m = re2.exec(String(html || '')))) {
        const n = parseInt(m[1], 10);
        if (n > 0 && n < 5000) max = Math.max(max, n);
      }
    }
    return max;
  }

  function estimatePagesFromTotalAds(totalAds) {
    const n = parseInt(totalAds, 10);
    if (!n || n < 1) return 0;
    // KP lista ~30 oglasa po strani
    return Math.max(1, Math.ceil(n / 28));
  }

  function parsePaginationFromDocument(doc, pageUrl) {
    const parsedUrl = parseSellerListUrl(pageUrl || doc.location?.href || '');
    if (!parsedUrl) {
      const fallback = String(pageUrl || '').replace(/[?#].*$/, '');
      return { current: 1, max: 1, basePath: '', pageUrls: [fallback || pageUrl] };
    }

    const basePath = parsedUrl.basePath;
    const current = parsedUrl.page;
    let max = current;

    const html =
      (doc.documentElement && (doc.documentElement.outerHTML || doc.documentElement.innerHTML)) ||
      '';
    max = Math.max(max, detectMaxPageFromHtml(html, parsedUrl.user_id));

    doc.querySelectorAll('a[href*="/svi-oglasi/"]').forEach((a) => {
      const href = a.getAttribute('href') || '';
      const m = href.match(/\/svi-oglasi\/\d+\/(\d+)/);
      if (m) max = Math.max(max, parseInt(m[1], 10));
      const info = parseSellerListUrl(absUrl(href, basePath));
      if (info) max = Math.max(max, info.page);
    });

    doc.querySelectorAll(
      'nav a, [class*="pagination"] a, [class*="Pagination"] a, [aria-label*="stran"] a, button, li'
    ).forEach((a) => {
      const t = textOf(a);
      if (!/^\d+$/.test(t)) return;
      const n = parseInt(t, 10);
      if (n > 0 && n < 2000) max = Math.max(max, n);
    });

    const bodyText = textOf(doc.body);
    const dotsMax = bodyText.match(/(?:^|\s)(\d{2,4})\s*(?:Sledeća|Next)/i);
    if (dotsMax) {
      const n = parseInt(dotsMax[1], 10);
      if (n > max && n < 5000) max = n;
    }

    const totalAdsMatch = bodyText.match(/Ukupno oglasa:\s*([\d.]+)/i);
    if (totalAdsMatch) {
      max = Math.max(
        max,
        estimatePagesFromTotalAds(String(totalAdsMatch[1]).replace(/\./g, ''))
      );
    }

    const pageUrls = [];
    for (let p = 1; p <= max; p++) {
      pageUrls.push(buildSellerPageUrl(basePath, p));
    }

    return { current, max, basePath, pageUrls };
  }

  function extractDescriptionFromDocument(doc) {
    const selectors = [
      '[class*="descriptionHolder"]',
      '[class*="DescriptionHolder"]',
      '[class*="adDescription"]',
      '[class*="Description"]',
      '[id*="description"]',
      '#adDescription',
      '.adDescription',
    ];

    let best = '';
    for (const sel of selectors) {
      doc.querySelectorAll(sel).forEach((el) => {
        const t = textOf(el);
        if (t.length > best.length && t.length > 20) best = t;
      });
    }
    if (best) return best;

    const og = doc.querySelector('meta[property="og:description"]');
    if (og) {
      const t = (og.getAttribute('content') || '').trim();
      if (t.length > 20) return t;
    }

    const meta = doc.querySelector('meta[name="description"]');
    if (meta) {
      const t = (meta.getAttribute('content') || '').trim();
      if (t.length > 20 && !/kupujemprodajem/i.test(t)) return t;
    }

    return '';
  }

  function parseDetailFromDocument(doc, pageUrl) {
    const base = pageUrl || doc.location?.href || KP_ORIGIN;
    const ad = {
      source_id: extractAdId(base),
      source_url: base,
      title: '',
      description: '',
      price: null,
      currency: '',
      price_text: '',
      location: '',
      condition: '',
      listing_type: 'sell',
      images: [],
      category: '',
      posted_at: '',
    };

    ad.title = textOf(doc.querySelector('h1')) || textOf(doc.querySelector('[class*="title"]'));

    const priceEl = doc.querySelector('[class*="price"], [class*="Price"], h2');
    const priceInfo = parsePrice(textOf(priceEl));
    if (priceInfo.price != null || priceInfo.price_text) {
      ad.price = priceInfo.price;
      ad.currency = priceInfo.currency || '';
      ad.price_text = priceInfo.price_text || '';
    }
    // listing_type NE čitati iz cele stranice — UI tekst sadrži „kupujem”

    const descEl = doc.querySelector('[class*="description"], [id*="description"], .adDescription, #adDescription');
    if (descEl) ad.description = cleanAdDescription(textOf(descEl));

    if (!ad.description) {
      ad.description = cleanAdDescription(extractDescriptionFromDocument(doc));
    }

    if (!ad.description) {
      const metaDesc = doc.querySelector('meta[name="description"]');
      if (metaDesc) ad.description = cleanAdDescription(metaDesc.getAttribute('content') || '');
    }

    ad.images = extractImagesFromDocument(doc, base, ad.source_id);

    // Samo poznate KP vrednosti — ne greedy regex na body (hvata Next.js JSON)
    const knownCond = doc.body ? (doc.body.textContent || '').match(
      /\bStanje\s*[:\s]*(Novo|Nekorišćeno(?:\s*\([^)]*\))?|Kao novo|Korišćeno|Polovno|Oštećeno(?:\/Za delove)?)/i
    ) : null;
    if (knownCond) {
      ad.condition = mapKpCondition(knownCond[1]) || knownCond[1].trim();
    }

    return ad;
  }

  function parseListHtml(html, pageUrl) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const fromNext = parseAdsFromNextData(html, pageUrl);
    const seller = parseSellerFromDocument(doc, pageUrl);
    if (fromNext?.sellerHint?.user_id && !seller.user_id) {
      seller.user_id = fromNext.sellerHint.user_id;
    }

    let pagination = parsePaginationFromDocument(doc, pageUrl);
    const listInfo = parseSellerListUrl(pageUrl);
    const htmlMax = detectMaxPageFromHtml(html, listInfo?.user_id || seller.user_id);
    const adsEstimate = estimatePagesFromTotalAds(seller.total_ads);
    const pageCount = Math.max(
      pagination.max || 1,
      fromNext?.pageCount || 0,
      htmlMax || 0,
      adsEstimate || 0
    );
    if (listInfo && pageCount > 0) {
      const pageUrls = [];
      for (let p = 1; p <= pageCount; p++) {
        pageUrls.push(buildSellerPageUrl(listInfo.basePath, p));
      }
      pagination = {
        current: listInfo.page,
        max: pageCount,
        basePath: listInfo.basePath,
        pageUrls,
      };
    }

    return {
      seller,
      ads: (() => {
        if (!fromNext?.ads?.length) {
          return parseListPageFromDocument(doc, pageUrl, buildPhotoMapFromHtml(html));
        }
        const nextPage = Number(
          parseNextDataJson(html)?.props?.initialReduxState?.search?.lastSearchResult?.page
        ) || 0;
        if (listInfo && nextPage && nextPage !== listInfo.page) {
          return parseListPageFromDocument(doc, pageUrl, buildPhotoMapFromHtml(html), {
            skipNextData: true,
          });
        }
        return fromNext.ads;
      })(),
      pagination,
    };
  }

  function parseDetailHtml(html, pageUrl) {
    const fromNext = parseDetailFromNextData(html, pageUrl);
    if (fromNext && (fromNext.title || fromNext.description || fromNext.price != null)) {
      return fromNext;
    }
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return parseDetailFromDocument(doc, pageUrl);
  }

  /** Podrazumevane reči za skip (maske, punjači, laptop…). */
  function defaultSkipKeywords() {
    return [
      'torbica', 'maska', 'futrola', 'case', 'folija', 'staklo', 'tempered',
      'punjac', 'punjač', 'punjac', 'charger', 'kabl', 'kabel', 'adapter',
      'laptop', 'powerbank', 'baterija power', 'zvucnik', 'zvučnik', 'speaker',
      'slušalice', 'slusalice', 'airpods', 'buds', 'holder', 'stalak',
      'auto punjac', 'auto punjač', 'dock', 'postolje', 'keyboard', 'tastatura',
    ];
  }

  function normalizeFilterText(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'dj')
      .replace(/č/g, 'c')
      .replace(/ć/g, 'c')
      .replace(/š/g, 's')
      .replace(/ž/g, 'z');
  }

  function parseSkipKeywords(raw) {
    if (Array.isArray(raw)) {
      return raw.map((k) => normalizeFilterText(k)).filter(Boolean);
    }
    return String(raw || '')
      .split(/[\n,;]+/)
      .map((k) => normalizeFilterText(k.trim()))
      .filter(Boolean);
  }

  function adMatchesSkip(ad, skipKeywords) {
    if (!skipKeywords || !skipKeywords.length) return false;
    const blob = normalizeFilterText(
      [ad.title, ad.description_short, ad.description, ad.source_url].filter(Boolean).join(' ')
    );
    return skipKeywords.some((kw) => kw.length >= 3 && blob.includes(kw));
  }

  function isPhoneLikeAd(ad) {
    const url = String(ad.source_url || '').toLowerCase();
    if (url.includes('/mobilni-telefoni/')) return true;
    if (url.includes('/mobilni-tel-oprema') || url.includes('/rucni-i-dzepni-satovi')) return false;

    const title = normalizeFilterText(ad.title || '');
    if (!title) return false;

    // Oprema u naslovu → nije telefon
    if (/\b(torbica|maska|futrola|folija|staklo|punjac|punjac|adapter|kabl|laptop)\b/.test(title)) {
      return false;
    }

    if (/\biphone\b|\bgalaxy\s*[aszf]\d|\bgalaxy\s*note|\bgalaxy\s*a\d|\bredmi\b|\bpoco\b|\bxiaomi\b|\brealme\b|\bhonor\b|\bhuawei\b|\boppo\b|\bvivo\b|\bnothing\b|\bpixel\b|\bmotorola\b|\bnokia\b/.test(title)) {
      return true;
    }
    if (/\b(samsung|apple)\b/.test(title) && /\b(\d+\s*\/\s*\d+|\d+gb|sim\s*free|dual\s*sim|novo|garancija)\b/.test(title)) {
      return true;
    }
    return false;
  }

  function isDeviceLikeAd(ad) {
    if (isPhoneLikeAd(ad)) return true;
    const url = String(ad.source_url || '').toLowerCase();
    if (url.includes('/rucni-i-dzepni-satovi') || url.includes('/smartwatch') || url.includes('/tablet')) {
      return true;
    }
    const title = normalizeFilterText(ad.title || '');
    if (/\b(watch|sat|ipad|tablet|airpods|buds)\b/.test(title) && !/\b(torbica|maska|punjac|kabl)\b/.test(title)) {
      return true;
    }
    return false;
  }

  /**
   * Filtrira oglase pre detalja.
   * mode: all | phones | devices
   * @returns {{ kept: array, skipped: number }}
   */
  function filterAds(ads, options) {
    const mode = (options && options.filterMode) || 'all';
    const skipKeywords = parseSkipKeywords(
      options && options.skipKeywords != null ? options.skipKeywords : defaultSkipKeywords()
    );
    const kept = [];
    let skipped = 0;

    (ads || []).forEach((ad) => {
      if (mode !== 'all' && adMatchesSkip(ad, skipKeywords)) {
        skipped++;
        return;
      }
      if (mode === 'phones' && !isPhoneLikeAd(ad)) {
        skipped++;
        return;
      }
      if (mode === 'devices' && !isDeviceLikeAd(ad)) {
        skipped++;
        return;
      }
      kept.push(ad);
    });

    return { kept, skipped };
  }

  const api = {
    absUrl,
    extractAdId,
    parsePrice,
    buildPhotoMapFromHtml,
    extractImagesFromDocument,
    parseSellerFromDocument,
    parseListPageFromDocument,
    parsePaginationFromDocument,
    parseSellerListUrl,
    buildSellerPageUrl,
    detectMaxPageFromHtml,
    estimatePagesFromTotalAds,
    parseDetailFromDocument,
    parseDetailFromNextData,
    parseListHtml,
    parseDetailHtml,
    parseAdsFromNextData,
    mapKpCondition,
    cleanAdDescription,
    looksLikeGarbageField,
    defaultSkipKeywords,
    parseSkipKeywords,
    filterAds,
    isPhoneLikeAd,
    isDeviceLikeAd,
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  global.KpParser = api;
})(typeof self !== 'undefined' ? self : typeof window !== 'undefined' ? window : globalThis);
