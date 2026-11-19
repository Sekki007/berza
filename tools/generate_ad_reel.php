<?php

declare(strict_types=1);

/**
 * CLI: php tools/generate_ad_reel.php [adId]
 * Primer: php tools/generate_ad_reel.php 6
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/ad_reel.php';

$adId = (int)($argv[1] ?? 6);
$ad = getAdById($adId);
if (!$ad) {
    fwrite(STDERR, "Oglas #{$adId} nije pronadjen.\n");
    exit(1);
}

echo "Generisem reel za #{$adId}: " . adDisplayTitle($ad) . "\n";
$result = generateAdReel($ad, true);
if (empty($result['ok'])) {
    fwrite(STDERR, "Greska: " . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

echo "OK\n";
echo "script: " . ($result['script'] ?? '') . "\n";
echo "duration: " . ($result['duration'] ?? '') . "s\n";
echo "file: " . ($result['path'] ?? '') . "\n";
echo "url: " . ($result['public'] ?? '') . "\n";
