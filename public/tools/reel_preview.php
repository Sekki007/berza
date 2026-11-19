<?php

declare(strict_types=1);

/**
 * Lokalni / admin preview: /tools/reel_preview.php?id=6&generate=1
 */
define('KT_SKIP_BACKGROUND', true);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/ad_reel.php';

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost');
if (!$isLocal && !isAdmin()) {
    $key = trim((string)($_GET['key'] ?? ''));
    $expect = trim((string)envValue('REEL_PREVIEW_KEY', ''));
    if ($expect === '' || !hash_equals($expect, $key)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$adId = (int)($_GET['id'] ?? 0);
$ad = $adId > 0 ? getAdById($adId) : null;
if (!$ad) {
    http_response_code(404);
    echo 'Oglas nije pronadjen.';
    exit;
}

$result = null;
if (!empty($_GET['generate'])) {
    $result = generateAdReel($ad, true);
}

$public = adReelPublicPath($adId);
$exists = is_file(adReelFilesystemPath($adId));
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reel preview #<?= (int)$adId ?></title>
  <style>
    body{font-family:system-ui,sans-serif;background:#0f1412;color:#f3f5f2;margin:0;padding:24px}
    .wrap{max-width:420px;margin:0 auto}
    h1{font-size:18px;margin:0 0 8px}
    p{color:#aeb6b0;font-size:14px}
    video{width:100%;border-radius:18px;background:#000;aspect-ratio:9/16}
    a.btn,button{display:inline-block;margin-top:12px;padding:10px 14px;background:#2d7a3e;color:#fff;border-radius:10px;text-decoration:none;border:0;font-weight:700}
    code{color:#f5c518}
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= h(adDisplayTitle($ad)) ?></h1>
    <p>Auto reel prototip (9:16 + srpski TTS)</p>
    <?php if ($exists): ?>
      <video controls playsinline src="<?= h($public) ?>?t=<?= time() ?>"></video>
    <?php else: ?>
      <p>Video jos nije generisan.</p>
    <?php endif; ?>
    <p><a class="btn" href="?id=<?= (int)$adId ?>&generate=1">Generisi ponovo</a></p>
    <?php if (is_array($result)): ?>
      <p><code><?= h(json_encode($result, JSON_UNESCAPED_UNICODE)) ?></code></p>
    <?php endif; ?>
  </div>
</body>
</html>
