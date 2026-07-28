<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [];
$urls[] = ['loc' => rtrim(absoluteUrl('/'), '/') . '/', 'priority' => '1.0', 'changefreq' => 'daily'];

$staticPages = [
    ['/kako-radi.php', '0.5', 'monthly'],
    ['/index.php?type=telefon', '0.8', 'daily'],
    ['/index.php?type=delovi', '0.7', 'daily'],
    ['/index.php?type=servis', '0.8', 'daily'],
    ['/index.php?device_type=tablet', '0.7', 'daily'],
    ['/index.php?device_type=watch', '0.7', 'daily'],
];
foreach ($staticPages as [$path, $priority, $changefreq]) {
    $urls[] = [
        'loc' => absoluteUrl($path),
        'priority' => $priority,
        'changefreq' => $changefreq,
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
