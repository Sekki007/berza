<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [];
$seenLocs = [];
$addUrl = static function (array $entry) use (&$urls, &$seenLocs): void {
    $loc = (string)($entry['loc'] ?? '');
    if ($loc === '' || isset($seenLocs[$loc])) {
        return;
    }
    $seenLocs[$loc] = true;
    if (array_key_exists('lastmod', $entry)) {
        $lastmod = seoLastmodDate((string)$entry['lastmod']);
        if ($lastmod === '') {
            unset($entry['lastmod']);
        } else {
            $entry['lastmod'] = $lastmod;
        }
    }
    $urls[] = $entry;
};

$addUrl(['loc' => rtrim(absoluteUrl('/'), '/') . '/', 'priority' => '1.0', 'changefreq' => 'daily']);

$staticPages = [
    ['/kako-radi', '0.5', 'monthly'],
    ['/privatnost', '0.4', 'yearly'],
    ['/uslovi', '0.4', 'yearly'],
    ['/prijava', '0.5', 'monthly'],
    ['/registracija', '0.6', 'monthly'],
    ['/postavi-oglas', '0.6', 'weekly'],
    ['/oglasi', '0.9', 'daily'],
    ['/katalog', '0.8', 'daily'],
    ['/vodici', '0.7', 'weekly'],
    ['/servisi', '0.9', 'daily'],
    ['/provera-imei', '0.8', 'monthly'],
    ['/oglasi/telefoni', '0.8', 'daily'],
    ['/oglasi/delovi', '0.7', 'daily'],
    ['/oglasi/oprema', '0.7', 'daily'],
    ['/oglasi/servis', '0.8', 'daily'],
    ['/oglasi?device_type=tablet', '0.7', 'daily'],
    ['/oglasi?device_type=watch', '0.7', 'daily'],
];
foreach ($staticPages as [$path, $priority, $changefreq]) {
    $addUrl([
        'loc' => absoluteUrl($path),
        'priority' => $priority,
        'changefreq' => $changefreq,
    ]);
}

$publicAds = getPublicAds(['sort' => 'newest']);
foreach (listingLandingCandidatesForSitemap($publicAds) as $landingPath) {
    $addUrl([
        'loc' => absoluteUrl($landingPath),
        'priority' => '0.75',
        'changefreq' => 'daily',
    ]);
}

if (function_exists('phoneCatalogSitemapPaths')) {
    foreach (phoneCatalogSitemapPaths() as $catalogPath) {
        if ($catalogPath === '/katalog') {
            continue;
        }
        $addUrl([
            'loc' => absoluteUrl($catalogPath),
            'priority' => '0.6',
            'changefreq' => 'weekly',
        ]);
    }
}

foreach (getPublishedGuides() as $guide) {
    $guideUpdated = trim((string)($guide['updated_at'] ?? ''));
    if ($guideUpdated === '') {
        $guideUpdated = trim((string)($guide['published_at'] ?? $guide['created_at'] ?? ''));
    }
    $addUrl([
        'loc' => absoluteUrl(guideUrl($guide)),
        'priority' => '0.6',
        'changefreq' => 'monthly',
        'lastmod' => $guideUpdated,
    ]);
}

foreach (directoryCityStats() as $cityRow) {
    $addUrl([
        'loc' => absoluteUrl($cityRow['url']),
        'priority' => '0.8',
        'changefreq' => 'weekly',
    ]);
}
foreach (listDirectoryServices(null) as $svcUser) {
    $svcUpdated = trim((string)($svcUser['verified_seller_at'] ?? ''));
    if ($svcUpdated === '') {
        $svcUpdated = trim((string)($svcUser['business_verified_at'] ?? ''));
    }
    if ($svcUpdated === '') {
        $svcUpdated = trim((string)($svcUser['created_at'] ?? ''));
    }
    $addUrl([
        'loc' => absoluteUrl(directoryServiceUrl($svcUser)),
        'priority' => '0.75',
        'changefreq' => 'weekly',
        'lastmod' => $svcUpdated,
    ]);
}

foreach ($publicAds as $ad) {
    $adUpdated = trim((string)($ad['updated_at'] ?? ''));
    if ($adUpdated === '') {
        $adUpdated = trim((string)($ad['created_at'] ?? ''));
    }
    $addUrl([
        'loc' => absoluteUrl(adUrl($ad)),
        'lastmod' => $adUpdated,
        'priority' => '0.8',
        'changefreq' => 'weekly',
    ]);
}

$sellersWithPublicAds = [];
foreach (getAllAds() as $anyAd) {
    if (!is_array($anyAd) || (int)($anyAd['is_active'] ?? 0) !== 1) {
        continue;
    }
    $sellerId = (int)($anyAd['created_by'] ?? 0);
    if ($sellerId > 0) {
        $sellersWithPublicAds[$sellerId] = true;
    }
}

$seenUsers = [];
foreach (getUsers() as $user) {
    if (!empty($user['is_blocked'])) {
        continue;
    }
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0 || isset($seenUsers[$uid])) {
        continue;
    }
    $seenUsers[$uid] = true;

    $hasAds = isset($sellersWithPublicAds[$uid]);
    if ($hasAds) {
        $addUrl([
            'loc' => absoluteUrl(shopUrlForUser($user)),
            'priority' => '0.6',
            'changefreq' => 'weekly',
        ]);
        foreach (getShopCategories($user) as $shopCat) {
            $addUrl([
                'loc' => absoluteUrl(shopCatalogUrl($user, ['cat' => $shopCat['id']])),
                'priority' => '0.55',
                'changefreq' => 'weekly',
            ]);
        }
    }

    if (function_exists('storefrontIsActive') && storefrontIsActive($user)) {
        $addUrl([
            'loc' => absoluteUrl(storefrontUrlForUser($user)),
            'priority' => '0.7',
            'changefreq' => 'weekly',
            'lastmod' => (string)($user['shop_page_updated_at'] ?? ''),
        ]);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars((string)$u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . htmlspecialchars((string)$u['lastmod'], ENT_XML1) . "</lastmod>\n";
    }
    if (!empty($u['changefreq'])) {
        echo '    <changefreq>' . htmlspecialchars((string)$u['changefreq'], ENT_XML1) . "</changefreq>\n";
    }
    if (!empty($u['priority'])) {
        echo '    <priority>' . htmlspecialchars((string)$u['priority'], ENT_XML1) . "</priority>\n";
    }
    echo "  </url>\n";
}
echo '</urlset>';
