<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [];
$urls[] = ['loc' => rtrim(absoluteUrl('/'), '/') . '/', 'priority' => '1.0', 'changefreq' => 'daily'];

$staticPages = [
    ['/kako-radi', '0.5', 'monthly'],
    ['/privatnost', '0.4', 'yearly'],
    ['/uslovi', '0.4', 'yearly'],
    ['/prijava', '0.5', 'monthly'],
    ['/registracija', '0.6', 'monthly'],
    ['/postavi-oglas', '0.6', 'weekly'],
    ['/oglasi', '0.9', 'daily'],
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
    $urls[] = [
        'loc' => absoluteUrl($path),
        'priority' => $priority,
        'changefreq' => $changefreq,
    ];
}

foreach (listingLandingCandidatesForSitemap() as $landingPath) {
    $urls[] = [
        'loc' => absoluteUrl($landingPath),
        'priority' => '0.75',
        'changefreq' => 'daily',
    ];
}

foreach (getPublishedGuides() as $guide) {
    $urls[] = [
        'loc' => absoluteUrl(guideUrl($guide)),
        'priority' => '0.6',
        'changefreq' => 'monthly',
        'lastmod' => !empty($guide['updated_at']) ? date('c', strtotime((string)$guide['updated_at'])) : null,
    ];
}

foreach (directoryCityStats() as $cityRow) {
    $urls[] = [
        'loc' => absoluteUrl($cityRow['url']),
        'priority' => '0.8',
        'changefreq' => 'weekly',
    ];
}
foreach (listDirectoryServices(null) as $svcUser) {
    $urls[] = [
        'loc' => absoluteUrl(directoryServiceUrl($svcUser)),
        'priority' => '0.75',
        'changefreq' => 'weekly',
        'lastmod' => substr((string)($svcUser['verified_seller_at'] ?? $svcUser['business_verified_at'] ?? $svcUser['created_at'] ?? ''), 0, 10),
    ];
}

foreach (getPublicAds(['sort' => 'newest']) as $ad) {
    $urls[] = [
        'loc' => absoluteUrl(adUrl($ad)),
        'lastmod' => substr((string)($ad['updated_at'] ?? $ad['created_at'] ?? ''), 0, 10),
        'priority' => '0.8',
        'changefreq' => 'weekly',
    ];
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

    $hasAds = getPublicAdsByUserId($uid, true) !== [];
    if ($hasAds) {
        $urls[] = [
            'loc' => absoluteUrl(shopUrlForUser($user)),
            'priority' => '0.6',
            'changefreq' => 'weekly',
        ];
        foreach (getShopCategories($user) as $shopCat) {
            $urls[] = [
                'loc' => absoluteUrl(shopCatalogUrl($user, ['cat' => $shopCat['id']])),
                'priority' => '0.55',
                'changefreq' => 'weekly',
            ];
        }
    }

    if (function_exists('storefrontIsActive') && storefrontIsActive($user)) {
        $urls[] = [
            'loc' => absoluteUrl(storefrontUrlForUser($user)),
            'priority' => '0.7',
            'changefreq' => 'weekly',
            'lastmod' => substr((string)($user['shop_page_updated_at'] ?? ''), 0, 10),
        ];
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
