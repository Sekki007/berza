<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = absoluteUrl('/');
$urls = [];
$urls[] = ['loc' => rtrim($base, '/') . '/', 'priority' => '1.0'];

foreach (getPublicAds(['sort' => 'newest']) as $ad) {
    $urls[] = [
        'loc' => absoluteUrl(adUrl($ad)),
        'lastmod' => substr((string)($ad['updated_at'] ?? $ad['created_at'] ?? ''), 0, 10),
        'priority' => '0.8',
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
    if (getPublicAdsByUserId($uid, true) === []) {
        continue;
    }
    $seenUsers[$uid] = true;
    $urls[] = [
        'loc' => absoluteUrl(shopUrl((string)$user['username'])),
        'priority' => '0.6',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . htmlspecialchars((string)$u['lastmod'], ENT_XML1) . "</lastmod>\n";
    }
    if (!empty($u['priority'])) {
        echo '    <priority>' . htmlspecialchars((string)$u['priority'], ENT_XML1) . "</priority>\n";
    }
    echo "  </url>\n";
}
echo '</urlset>';
