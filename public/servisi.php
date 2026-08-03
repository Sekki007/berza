<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$cityParam = trim((string)($_GET['city'] ?? ''));
$slugParam = trim((string)($_GET['slug'] ?? ''));
$kindFilter = trim((string)($_GET['tip'] ?? ''));
if (!in_array($kindFilter, ['service', 'shop', ''], true)) {
    $kindFilter = '';
}
$activePage = 'servisi';
$showSearch = true;

$dirKindTabs = [
    '' => 'Sve firme',
    'service' => 'Servisi / popravka',
    'shop' => 'Prodavnice',
];

// --- Detalj firme ---
if ($cityParam !== '' && $slugParam !== '') {
    $user = findDirectoryService($cityParam, $slugParam);
    if (!$user) {
        http_response_code(404);
        $pageTitle = 'Firma nije pronađena — KupiTelefon';
        require __DIR__ . '/partials/layout-start.php';
        echo '<div class="main-wrap"><main class="content"><div class="form-card"><h1>Firma nije pronađena</h1><p style="margin-top:10px;color:var(--text-muted);">Proveri link ili pogledaj <a href="/servisi">direktorijum firmi</a>.</p></div></main></div>';
        require __DIR__ . '/partials/layout-end.php';
        exit;
    }

    $cityName = trim((string)($user['location'] ?? ''));
    $canonicalPath = directoryServiceUrl($user, $cityName);
    $requestPath = '/servisi/' . rawurlencode(citySlug($cityParam)) . '/' . rawurlencode(normalizeShopSlug($slugParam));
    if ($canonicalPath !== $requestPath && citySlug($cityName) !== '' && userShopSlug($user) !== '') {
        header('Location: ' . $canonicalPath, true, 301);
        exit;
    }

    $shopName = directoryServiceName($user);
    $seo = seoDirectoryServiceMeta($user, $cityName);
    $pageTitle = $seo['title'];
    $pageDescription = $seo['description'];
    $canonicalUrl = absoluteUrl($canonicalPath);
    $jsonLd = seoDirectoryServiceJsonLd($user, $cityName);
    $logoUrl = userShopLogoUrl($user);
    if ($logoUrl !== '') {
        $pageImage = absoluteUrl($logoUrl);
    }
    $initials = mb_strtoupper(mb_substr($shopName, 0, 1));
    $kind = businessKindLabel(userBusinessKind($user));
    $phone = trim((string)($user['phone'] ?? ''));
    $bio = trim((string)($user['shop_bio'] ?? ''));
    $address = trim((string)($user['shop_page_address'] ?? ''));
    $shopLink = shopUrlForUser($user);
    $storefrontActive = storefrontIsActive($user);
    $storefrontLink = $storefrontActive ? storefrontUrlForUser($user) : '';
    $summary = getSellerRatingSummary((int)$user['id']);
    $ads = array_slice(getPublicAdsByUserId((int)$user['id'], true), 0, 8);

    require __DIR__ . '/partials/layout-start.php';
    ?>
    <div class="main-wrap">
        <main class="content dir-page">
            <div class="breadcrumb">
                <a href="/index.php">Početna</a> ›
                <a href="/servisi">Firme</a> ›
                <a href="<?= h(directoryCityUrl($cityName)) ?>"><?= h($cityName) ?></a> ›
                <?= h($shopName) ?>
            </div>

            <article class="form-card dir-service-hero">
                <div class="dir-service-top">
                    <?= renderShopAvatarHtml($user, $initials, 'shop-avatar dir-service-avatar') ?>
                    <div class="dir-service-info">
                        <h1 class="dir-service-title"><?= h($shopName) ?> <?= renderSellerBadges($user) ?></h1>
                        <p class="dir-service-meta"><?= h($kind) ?> · <?= h($cityName) ?></p>
                        <div class="shop-rating"><?= renderReputation($summary, $shopLink) ?></div>
                        <?php if ($bio !== ''): ?>
                            <p class="shop-bio"><?= nl2br(h($bio)) ?></p>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                            <p class="dir-service-address"><?= h($address) ?></p>
                        <?php endif; ?>
                        <?php if ($phone !== ''): ?>
                            <p class="shop-phone"><a href="tel:<?= h(preg_replace('/\s+/', '', $phone) ?? $phone) ?>"><?= h($phone) ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shop-actions dir-service-actions">
                    <a class="btn-call" href="<?= h($shopLink) ?>">Otvori izlog</a>
                    <?php if ($storefrontLink !== ''): ?>
                        <a class="btn-message" href="<?= h($storefrontLink) ?>">Mini sajt</a>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <a class="btn-message" href="tel:<?= h(preg_replace('/\s+/', '', $phone) ?? $phone) ?>">Pozovi</a>
                    <?php endif; ?>
                    <?php if (isLoggedIn() && (int)currentUser()['id'] !== (int)$user['id']): ?>
                        <a class="btn-message" href="/poruke.php?with=<?= (int)$user['id'] ?>">Pošalji poruku</a>
                    <?php endif; ?>
                </div>
            </article>

            <?php if ($ads !== []): ?>
                <section class="dir-section">
                    <h2 class="dir-section-title">Oglasi firme</h2>
                    <div class="listings">
                        <?php
                        $shopCatalogMode = false;
                        foreach ($ads as $ad) {
                            require __DIR__ . '/partials/ad-card.php';
                        }
                        ?>
                    </div>
                    <p class="dir-more"><a href="<?= h($shopLink) ?>">Svi oglasi na izlogu →</a></p>
                </section>
            <?php endif; ?>

            <section class="dir-section form-card">
                    <h2 class="dir-section-title">Još firmi u <?= h($cityName) ?></h2>
                <?php
                $siblings = array_values(array_filter(
                    listDirectoryServices($cityName),
                    static fn(array $u): bool => (int)($u['id'] ?? 0) !== (int)$user['id']
                ));
                $siblings = array_slice($siblings, 0, 8);
                ?>
                <?php if ($siblings === []): ?>
                    <p class="dir-empty">Trenutno nema drugih verifikovanih firmi u ovom gradu.</p>
                <?php else: ?>
                    <div class="dir-card-grid">
                        <?php foreach ($siblings as $sib): ?>
                            <?php
                            $sibName = directoryServiceName($sib);
                            $sibInit = mb_strtoupper(mb_substr($sibName, 0, 1));
                            ?>
                            <a class="dir-card" href="<?= h(directoryServiceUrl($sib, $cityName)) ?>">
                                <?= renderShopAvatarHtml($sib, $sibInit, 'dir-card-avatar') ?>
                                <div>
                                    <strong><?= h($sibName) ?></strong>
                                    <span><?= h(businessKindLabel(userBusinessKind($sib))) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

// --- Grad ---
if ($cityParam !== '') {
    $cityName = findCityBySlug($cityParam);
    if ($cityName === null) {
        // Dozvoli grad iz podataka firmi i ako nije u settings listi
        foreach (listDirectoryServices(null) as $u) {
            $loc = trim((string)($u['location'] ?? ''));
            if ($loc !== '' && citySlug($loc) === citySlug($cityParam)) {
                $cityName = $loc;
                break;
            }
        }
    }
    if ($cityName === null) {
        http_response_code(404);
        $pageTitle = 'Grad nije pronađen — KupiTelefon';
        require __DIR__ . '/partials/layout-start.php';
        echo '<div class="main-wrap"><main class="content"><div class="form-card"><h1>Grad nije pronađen</h1><p style="margin-top:10px;color:var(--text-muted);"><a href="/servisi">Nazad na servise</a></p></div></main></div>';
        require __DIR__ . '/partials/layout-end.php';
        exit;
    }

    if (citySlug($cityParam) !== citySlug($cityName)) {
        header('Location: ' . directoryCityUrl($cityName, $kindFilter), true, 301);
        exit;
    }

    $services = listDirectoryServices($cityName, $kindFilter);
    $seo = seoDirectoryCityMeta($cityName, count($services), $kindFilter);
    $pageTitle = $seo['title'];
    $pageDescription = $seo['description'];
    $canonicalUrl = absoluteUrl(directoryCityUrl($cityName, $kindFilter));
    $cityHeading = match ($kindFilter) {
        'shop' => 'Prodavnice mobilnih telefona u ' . $cityName,
        'service' => 'Mobilni servisi u ' . $cityName,
        default => 'Servisi i prodavnice u ' . $cityName,
    };
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $cityHeading,
        'url' => $canonicalUrl,
        'description' => $pageDescription,
    ];

    require __DIR__ . '/partials/layout-start.php';
    ?>
    <div class="main-wrap">
        <main class="content dir-page">
            <div class="breadcrumb">
                <a href="/index.php">Početna</a> ›
                <a href="<?= h(directoryHubUrl($kindFilter)) ?>">Firme</a> ›
                <?= h($cityName) ?>
            </div>

            <header class="dir-hub-head form-card">
                <h1><?= h($cityHeading) ?></h1>
                <p>Verifikovane firme za prodaju i/ili popravku mobilnih telefona<?= count($services) > 0 ? ' — ' . count($services) . ' ' . (count($services) === 1 ? 'firma' : 'firmi') : '' ?>.</p>
                <nav class="dir-kind-tabs" aria-label="Tip firme">
                    <?php foreach ($dirKindTabs as $tipKey => $tipLabel): ?>
                        <a class="dir-kind-tab <?= $kindFilter === $tipKey ? 'is-active' : '' ?>" href="<?= h(directoryCityUrl($cityName, $tipKey)) ?>"><?= h($tipLabel) ?></a>
                    <?php endforeach; ?>
                </nav>
                <p style="margin-top:10px;"><a href="<?= h(directoryHubUrl($kindFilter)) ?>">← Svi gradovi / pretraga</a></p>
            </header>

            <?php if ($services === []): ?>
                <div class="form-card">
                    <p class="dir-empty">Još nema verifikovanih firmi za <?= h($cityName) ?> u ovom filteru. Pogledaj <a href="<?= h(directoryHubUrl()) ?>">druge gradove</a> ili <a href="/index.php?type=servis&amp;location=<?= h(rawurlencode($cityName)) ?>">oglase</a>.</p>
                </div>
            <?php else: ?>
                <div class="dir-card-grid dir-card-grid-lg">
                    <?php foreach ($services as $svc): ?>
                        <?php
                        $svcName = directoryServiceName($svc);
                        $svcInit = mb_strtoupper(mb_substr($svcName, 0, 1));
                        $svcPhone = trim((string)($svc['phone'] ?? ''));
                        $svcBio = trim((string)($svc['shop_bio'] ?? ''));
                        ?>
                        <a class="dir-card dir-card-lg" href="<?= h(directoryServiceUrl($svc, $cityName)) ?>">
                            <?= renderShopAvatarHtml($svc, $svcInit, 'dir-card-avatar') ?>
                            <div class="dir-card-body">
                                <strong><?= h($svcName) ?></strong>
                                <span class="dir-card-kind"><?= h(businessKindLabel(userBusinessKind($svc))) ?></span>
                                <?php if ($svcBio !== ''): ?>
                                    <span class="dir-card-bio"><?= h(seoTruncate($svcBio, 110)) ?></span>
                                <?php endif; ?>
                                <?php if ($svcPhone !== ''): ?>
                                    <span class="dir-card-phone"><?= h($svcPhone) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <?php
    require __DIR__ . '/partials/layout-end.php';
    exit;
}

// --- Hub ---
$cityStats = directoryCityStats($kindFilter);
$citySearchIndex = directoryCitySearchIndex();
$allServices = listDirectoryServices(null, $kindFilter);
$seo = seoDirectoryHubMeta($kindFilter);
$pageTitle = $seo['title'];
$pageDescription = $seo['description'];
$canonicalUrl = absoluteUrl(directoryHubUrl($kindFilter));
$hubHeading = match ($kindFilter) {
    'shop' => 'Prodavnice mobilnih telefona u Srbiji',
    'service' => 'Mobilni servisi u Srbiji',
    default => 'Servisi i prodavnice telefona u Srbiji',
};
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $hubHeading,
    'url' => $canonicalUrl,
    'description' => $pageDescription,
];

// Direktna pretraga: ?q=Niš → /servisi/nis
$hubQ = trim((string)($_GET['q'] ?? ''));
if ($hubQ !== '') {
    $qSlug = citySlug($hubQ);
    foreach ($citySearchIndex as $row) {
        if ($row['slug'] === $qSlug || mb_strtolower($row['city']) === mb_strtolower($hubQ)) {
            header('Location: ' . directoryCityUrl($row['city'], $kindFilter), true, 302);
            exit;
        }
    }
}

require __DIR__ . '/partials/layout-start.php';
?>
<div class="main-wrap">
    <main class="content dir-page">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Firme</div>

        <header class="dir-hub-head form-card">
            <h1><?= h($hubHeading) ?></h1>
            <p>Direktorijum verifikovanih firmi za <strong>prodaju</strong> i <strong>popravku</strong> mobilnih telefona. Pretraži grad ili filtriraj tip firme.</p>
            <p class="dir-hub-count"><?= count($allServices) ?> <?= count($allServices) === 1 ? 'firma' : 'firmi' ?> · <?= count($cityStats) ?> <?= count($cityStats) === 1 ? 'grad' : 'gradova' ?></p>

            <nav class="dir-kind-tabs" aria-label="Tip firme">
                <?php foreach ($dirKindTabs as $tipKey => $tipLabel): ?>
                    <a class="dir-kind-tab <?= $kindFilter === $tipKey ? 'is-active' : '' ?>" href="<?= h(directoryHubUrl($tipKey)) ?>"><?= h($tipLabel) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="dir-city-search" method="GET" action="/servisi" data-dir-city-search autocomplete="off">
                <?php if ($kindFilter !== ''): ?>
                    <input type="hidden" name="tip" value="<?= h($kindFilter) ?>">
                <?php endif; ?>
                <label class="dir-city-search-label" for="dir-city-q">Traži grad</label>
                <div class="dir-city-search-wrap">
                    <input
                        id="dir-city-q"
                        class="dir-city-search-input"
                        type="search"
                        name="q"
                        value="<?= h($hubQ) ?>"
                        placeholder="npr. Niš, Novi Sad, Beograd…"
                        autocomplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="dir-city-suggest"
                        data-dir-city-input
                    >
                    <button type="submit" class="btn-sm btn-sm-primary">Traži</button>
                    <div id="dir-city-suggest" class="dir-city-suggest" data-dir-city-suggest hidden role="listbox"></div>
                </div>
                <p class="form-hint dir-city-search-hint">Kucaj naziv grada — predlozi se otvaraju odmah. Enter otvara prvi rezultat.</p>
            </form>
        </header>

        <?php if ($allServices === []): ?>
            <div class="form-card">
                <p class="dir-empty">Još nema javnih verifikovanih firmi u ovom filteru. Probaj drugi tip ili potraži grad iznad.</p>
                <p style="margin-top:10px;"><a href="/index.php?type=servis">Pogledaj oglase usluga →</a> · <a href="/index.php?type=telefon">Oglasi telefona →</a></p>
            </div>
        <?php else: ?>
            <section class="dir-section">
                <h2 class="dir-section-title">Sve firme</h2>
                <div class="dir-card-grid dir-card-grid-lg" data-dir-service-grid>
                    <?php foreach ($allServices as $svc): ?>
                        <?php
                        $svcName = directoryServiceName($svc);
                        $svcInit = mb_strtoupper(mb_substr($svcName, 0, 1));
                        $svcCity = trim((string)($svc['location'] ?? ''));
                        $svcKindCode = userBusinessKind($svc);
                        ?>
                        <a
                            class="dir-card dir-card-lg"
                            href="<?= h(directoryServiceUrl($svc, $svcCity)) ?>"
                            data-dir-service-card
                            data-city="<?= h(mb_strtolower($svcCity)) ?>"
                            data-name="<?= h(mb_strtolower($svcName)) ?>"
                            data-kind="<?= h($svcKindCode) ?>"
                        >
                            <?= renderShopAvatarHtml($svc, $svcInit, 'dir-card-avatar') ?>
                            <div class="dir-card-body">
                                <strong><?= h($svcName) ?></strong>
                                <span class="dir-card-kind"><?= h(businessKindLabel($svcKindCode)) ?> · <?= h($svcCity) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<script>
window.__DIR_CITIES__ = <?= json_encode(array_map(static function (array $row) use ($kindFilter): array {
    $row['url'] = directoryCityUrl($row['city'], $kindFilter);
    return $row;
}, $citySearchIndex), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__DIR_KIND__ = <?= json_encode($kindFilter, JSON_UNESCAPED_UNICODE) ?>;
(function () {
  const cities = Array.isArray(window.__DIR_CITIES__) ? window.__DIR_CITIES__ : [];
  const form = document.querySelector('[data-dir-city-search]');
  const input = document.querySelector('[data-dir-city-input]');
  const box = document.querySelector('[data-dir-city-suggest]');
  if (!form || !input || !box) return;

  const serviceCards = Array.from(document.querySelectorAll('[data-dir-service-card]'));
  let active = -1;
  let items = [];

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'dj')
      .replace(/Đ/g, 'dj');
  }

  function matchCity(row, q) {
    if (!q) return true;
    const nq = norm(q);
    return norm(row.city).includes(nq) || String(row.slug || '').includes(nq.replace(/\s+/g, '-'));
  }

  function filterLists(q) {
    const nq = norm(q);
    serviceCards.forEach(function (card) {
      if (!nq) { card.hidden = false; return; }
      const city = norm(card.getAttribute('data-city'));
      const name = norm(card.getAttribute('data-name'));
      card.hidden = !(city.includes(nq) || name.includes(nq));
    });
  }

  function renderSuggest(q) {
    const nq = norm(q).trim();
    if (nq.length < 1) {
      items = [];
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      box.innerHTML = '';
      active = -1;
      return;
    }
    items = cities.filter(function (row) { return matchCity(row, nq); }).slice(0, 8);
    if (!items.length) {
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      box.innerHTML = '';
      active = -1;
      return;
    }
    box.innerHTML = items.map(function (row, idx) {
      const countLabel = row.count > 0
        ? (row.count + (row.count === 1 ? ' firma' : ' firmi'))
        : 'još nema firmi';
      return '<button type="button" class="dir-city-suggest-item' + (idx === active ? ' is-active' : '') + '" data-idx="' + idx + '" role="option">' +
        '<strong>' + escapeHtml(row.city) + '</strong>' +
        '<span>' + escapeHtml(countLabel) + '</span></button>';
    }).join('');
    box.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function go(idx) {
    if (idx < 0 || idx >= items.length) return;
    window.location.href = items[idx].url;
  }

  input.addEventListener('input', function () {
    active = -1;
    filterLists(input.value);
    renderSuggest(input.value);
  });

  input.addEventListener('keydown', function (e) {
    if (box.hidden || !items.length) {
      if (e.key === 'Enter' && items.length === 1) {
        e.preventDefault();
        go(0);
      }
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      active = (active + 1) % items.length;
      renderSuggest(input.value);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active - 1 + items.length) % items.length;
      renderSuggest(input.value);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      go(active >= 0 ? active : 0);
    } else if (e.key === 'Escape') {
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      active = -1;
    }
  });

  box.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-idx]');
    if (!btn) return;
    go(parseInt(btn.getAttribute('data-idx') || '-1', 10));
  });

  document.addEventListener('click', function (e) {
    if (!form.contains(e.target)) {
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });

  form.addEventListener('submit', function (e) {
    const q = input.value.trim();
    if (!q) return;
    const hit = cities.find(function (row) { return matchCity(row, q); });
    if (hit) {
      e.preventDefault();
      window.location.href = hit.url;
    }
  });

  if (input.value) filterLists(input.value);
})();
</script>
<?php
require __DIR__ . '/partials/layout-end.php';
