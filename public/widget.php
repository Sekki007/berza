<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/widget.php';

$size = strtolower(trim((string)($_GET['size'] ?? '300x600')));
$preset = widgetPreset($size);
if ($preset === null) {
    $size = '300x600';
    $preset = widgetPreset($size);
}

$limitParam = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)$preset['limit'];
$limit = max(1, min(6, $limitParam));
$type = trim((string)($_GET['type'] ?? ''));
$ref = resolveWidgetRef(trim((string)($_GET['ref'] ?? '')));
$layout = (string)$preset['layout'];
$ads = fetchWidgetAds($limit, $type, $ref);
$homeUrl = absoluteUrl('/') . '?' . http_build_query([
    'utm_source' => 'widget',
    'utm_medium' => 'embed',
    'utm_campaign' => $ref !== '' ? $ref : 'partner',
]);
$primary = $ads[0] ?? null;

header('Content-Type: text/html; charset=UTF-8');
header('Content-Security-Policy: frame-ancestors *');
header('Cache-Control: public, max-age=120');
header_remove('X-Frame-Options');
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>KupiTelefon widget</title>
    <style>
        :root {
            --bg:#eef2f5; --card:#fff; --text:#1f2933; --muted:#6b7280;
            --link:#1760a8; --price:#1760a8; --border:#dde3ea; --green:#2d7a3e; --yellow:#f5c518;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;width:100%;height:100%;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35}
        a{color:inherit;text-decoration:none}
        .price-pill{background:var(--price);color:#fff;font-weight:800;border-radius:6px;padding:4px 8px;white-space:nowrap;font-size:12px}
        .price-pill.is-open{background:#fff8e8;color:#8a6400;border:1px solid #f0d78c}
        .empty{padding:16px;text-align:center;color:var(--muted);font-size:12px}

        /* --- sky / sky-narrow --- */
        .lay-sky,.lay-sky-narrow{display:flex;flex-direction:column;height:100%;background:linear-gradient(180deg,#f7f9fb,#eef2f5)}
        .topbar{background:#fff;border-bottom:2px solid var(--yellow);padding:8px 10px;text-align:center}
        .brand{font-weight:800;color:var(--green);font-size:13px}
        .brand span{color:var(--text)}
        .tagline{font-size:10px;color:var(--muted);margin-top:1px}
        .list{display:flex;flex-direction:column;gap:8px;padding:8px;flex:1;overflow:auto}
        .vcard{display:flex;flex-direction:column;background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden}
        .vcard .thumb{width:100%;aspect-ratio:4/3;background:#e8edf2;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:10px;font-weight:700}
        .vcard .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .vcard .body{padding:8px 9px 9px;display:flex;flex-direction:column;gap:4px}
        .vcard .title{margin:0;font-size:12px;font-weight:700;color:var(--link);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .vcard .row{display:flex;align-items:center;justify-content:space-between;gap:6px}
        .vcard .meta{color:var(--muted);font-size:10px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .lay-sky-narrow .vcard .title{font-size:11px;-webkit-line-clamp:3}
        .lay-sky-narrow .price-pill{font-size:10px;padding:3px 5px}
        .bottom{margin-top:auto;padding:0 8px 8px}
        .cta{display:block;text-align:center;background:var(--green);color:#fff;font-weight:700;border-radius:8px;padding:8px;font-size:11px}
        .foot{margin-top:6px;text-align:center;font-size:9px;color:var(--muted)}
        .foot a{color:var(--green);font-weight:700}

        /* --- rect 300x250 / 336x280 --- */
        .lay-rect{height:100%;display:flex;flex-direction:column;background:var(--card);border:1px solid var(--border);overflow:hidden}
        .lay-rect .thumb{flex:1;min-height:0;background:#e8edf2;position:relative}
        .lay-rect .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .lay-rect .badge{position:absolute;left:8px;top:8px;background:rgba(255,255,255,.92);border-radius:999px;padding:3px 8px;font-size:10px;font-weight:800;color:var(--green)}
        .lay-rect .body{padding:8px 10px 10px;display:flex;flex-direction:column;gap:4px;flex-shrink:0}
        .lay-rect .title{margin:0;font-size:13px;font-weight:700;color:var(--link);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .lay-rect .row{display:flex;align-items:center;justify-content:space-between;gap:8px}
        .lay-rect .meta{font-size:11px;color:var(--muted)}

        /* --- native in-article --- */
        .lay-native{height:100%;display:flex;align-items:stretch;background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
        .lay-native .thumb{width:132px;min-width:132px;background:#e8edf2}
        .lay-native .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .lay-native .body{flex:1;min-width:0;padding:12px 14px;display:flex;flex-direction:column;gap:6px;justify-content:center}
        .lay-native .kicker{font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--green)}
        .lay-native .title{margin:0;font-size:15px;font-weight:700;color:var(--link);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .lay-native .row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .lay-native .meta{font-size:12px;color:var(--muted)}
        .lay-native .cta-link{font-size:12px;font-weight:700;color:var(--green)}

        /* --- leader 728/970 x 90 --- */
        .lay-leader{height:100%;display:flex;align-items:center;gap:12px;padding:8px 12px;background:var(--card);border:1px solid var(--border)}
        .lay-leader .thumb{width:74px;min-width:74px;height:74px;border-radius:8px;overflow:hidden;background:#e8edf2}
        .lay-leader .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .lay-leader .body{flex:1;min-width:0}
        .lay-leader .kicker{font-size:10px;font-weight:800;color:var(--green);text-transform:uppercase}
        .lay-leader .title{margin:2px 0 0;font-size:15px;font-weight:700;color:var(--link);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lay-leader .meta{font-size:11px;color:var(--muted);margin-top:2px}
        .lay-leader .side{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
        .lay-leader .cta{background:var(--green);color:#fff;font-weight:700;border-radius:8px;padding:8px 12px;font-size:12px;white-space:nowrap}

        /* --- mobile --- */
        .lay-mobile-sm{height:100%;display:flex;align-items:center;gap:8px;padding:4px 8px;background:var(--card);border:1px solid var(--border)}
        .lay-mobile-sm .thumb{width:40px;min-width:40px;height:40px;border-radius:6px;overflow:hidden;background:#e8edf2}
        .lay-mobile-sm .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .lay-mobile-sm .title{margin:0;font-size:11px;font-weight:700;color:var(--link);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lay-mobile-sm .body{flex:1;min-width:0}
        .lay-mobile-lg{height:100%;display:flex;align-items:center;gap:10px;padding:8px;background:var(--card);border:1px solid var(--border)}
        .lay-mobile-lg .thumb{width:84px;min-width:84px;height:84px;border-radius:8px;overflow:hidden;background:#e8edf2}
        .lay-mobile-lg .thumb img{width:100%;height:100%;object-fit:cover;display:block}
        .lay-mobile-lg .body{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
        .lay-mobile-lg .title{margin:0;font-size:12px;font-weight:700;color:var(--link);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .lay-mobile-lg .row{display:flex;align-items:center;justify-content:space-between;gap:6px}
        .lay-mobile-lg .meta{font-size:10px;color:var(--muted)}
    </style>
</head>
<body>
<?php if ($ads === []): ?>
    <div class="empty">Trenutno nema oglasa.</div>
<?php elseif ($layout === 'native' && $primary): ?>
    <a class="lay-native" href="<?= htmlspecialchars((string)$primary['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <div class="thumb">
            <?php if (!empty($primary['image'])): ?><img src="<?= htmlspecialchars((string)$primary['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php else: ?>OGLAS<?php endif; ?>
        </div>
        <div class="body">
            <div class="kicker">KupiTelefon · sponzorisano</div>
            <h2 class="title"><?= htmlspecialchars((string)$primary['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="row">
                <span class="meta"><?= htmlspecialchars((string)$primary['location'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="price-pill<?= widgetPriceIsOpen((string)$primary['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$primary['price'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="cta-link">Pogledaj →</span>
            </div>
        </div>
    </a>
<?php elseif ($layout === 'rect' && $primary): ?>
    <a class="lay-rect" href="<?= htmlspecialchars((string)$primary['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <div class="thumb">
            <span class="badge">KupiTelefon</span>
            <?php if (!empty($primary['image'])): ?><img src="<?= htmlspecialchars((string)$primary['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="body">
            <h2 class="title"><?= htmlspecialchars((string)$primary['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="row">
                <span class="meta"><?= htmlspecialchars((string)$primary['location'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="price-pill<?= widgetPriceIsOpen((string)$primary['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$primary['price'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </a>
<?php elseif ($layout === 'leader' && $primary): ?>
    <a class="lay-leader" href="<?= htmlspecialchars((string)$primary['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <div class="thumb">
            <?php if (!empty($primary['image'])): ?><img src="<?= htmlspecialchars((string)$primary['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php else: ?>KT<?php endif; ?>
        </div>
        <div class="body">
            <div class="kicker">KupiTelefon.rs</div>
            <h2 class="title"><?= htmlspecialchars((string)$primary['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="meta"><?= htmlspecialchars((string)$primary['location'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="side">
            <span class="price-pill<?= widgetPriceIsOpen((string)$primary['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$primary['price'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="cta">Pogledaj</span>
        </div>
    </a>
<?php elseif ($layout === 'mobile-sm' && $primary): ?>
    <a class="lay-mobile-sm" href="<?= htmlspecialchars((string)$primary['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <div class="thumb">
            <?php if (!empty($primary['image'])): ?><img src="<?= htmlspecialchars((string)$primary['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="body"><h2 class="title"><?= htmlspecialchars((string)$primary['title'], ENT_QUOTES, 'UTF-8') ?></h2></div>
        <span class="price-pill<?= widgetPriceIsOpen((string)$primary['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$primary['price'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
<?php elseif ($layout === 'mobile-lg' && $primary): ?>
    <a class="lay-mobile-lg" href="<?= htmlspecialchars((string)$primary['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <div class="thumb">
            <?php if (!empty($primary['image'])): ?><img src="<?= htmlspecialchars((string)$primary['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="body">
            <h2 class="title"><?= htmlspecialchars((string)$primary['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="row">
                <span class="meta"><?= htmlspecialchars((string)$primary['location'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="price-pill<?= widgetPriceIsOpen((string)$primary['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$primary['price'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </a>
<?php else: ?>
    <?php $cls = $layout === 'sky-narrow' ? 'lay-sky-narrow' : 'lay-sky'; ?>
    <div class="<?= $cls ?>">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Kupi<span>Telefon</span></a>
            <div class="tagline">Telefone · oprema · servis</div>
        </div>
        <div class="list">
            <?php foreach ($ads as $ad): ?>
                <a class="vcard" href="<?= htmlspecialchars((string)$ad['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <div class="thumb">
                        <?php if (!empty($ad['image'])): ?><img src="<?= htmlspecialchars((string)$ad['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php else: ?>OGLAS<?php endif; ?>
                    </div>
                    <div class="body">
                        <h2 class="title"><?= htmlspecialchars((string)$ad['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="row">
                            <span class="meta"><?= htmlspecialchars((string)$ad['location'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="price-pill<?= widgetPriceIsOpen((string)$ad['price']) ? ' is-open' : '' ?>"><?= htmlspecialchars((string)$ad['price'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="bottom">
            <a class="cta" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Otvori KupiTelefon.rs</a>
            <div class="foot">Powered by <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">KupiTelefon.rs</a></div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
