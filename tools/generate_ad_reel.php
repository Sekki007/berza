<?php

declare(strict_types=1);

/**
 * CLI: php tools/generate_ad_reel.php [adId]
 * Pro voiceover: ELEVENLABS_API_KEY u .env
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/ad_reel.php';
require_once dirname(__DIR__) . '/config/ad_reel_tts.php';

$adId = (int)($argv[1] ?? 6);
$ad = getAdById($adId);
if (!$ad) {
    fwrite(STDERR, "Oglas #{$adId} nije pronadjen.\n");
    exit(1);
}

echo "Generisem reel za #{$adId}: " . adDisplayTitle($ad) . "\n";
echo "TTS provider: " . adReelTtsProvider() . "\n";
if (adReelTtsProvider() === 'edge') {
    fwrite(STDERR, "\nPro voiceover nije konfigurisan.\n");
    fwrite(STDERR, "1) Napravi nalog na https://elevenlabs.io (Starter je dovoljan)\n");
    fwrite(STDERR, "2) API Key stavi u .env:\n");
    fwrite(STDERR, "   ELEVENLABS_API_KEY=sk_...\n");
    fwrite(STDERR, "   ELEVENLABS_VOICE_ID=j96cp162VcYdsYfSp1nc\n");
    fwrite(STDERR, "3) php tools/generate_ad_reel.php {$adId}\n\n");
    fwrite(STDERR, "Glas: Ivan — Deep Serbian & Croatian (Balkan) — isti tip kao marketplace reels.\n");
    exit(2);
}

$result = generateAdReel($ad, true);
if (empty($result['ok'])) {
    fwrite(STDERR, "Greska: " . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

echo "OK\n";
echo "provider: " . ($result['provider'] ?? '') . "\n";
echo "script: " . ($result['script'] ?? '') . "\n";
echo "duration: " . ($result['duration'] ?? '') . "s\n";
echo "file: " . ($result['path'] ?? '') . "\n";
echo "url: " . ($result['public'] ?? '') . "\n";
