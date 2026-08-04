<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/widget.php';

$limit = (int)($_GET['limit'] ?? 3);
$type = trim((string)($_GET['type'] ?? ''));
$ref = resolveWidgetRef(trim((string)($_GET['ref'] ?? '')));
$ads = fetchWidgetAds($limit, $type, $ref);
$homeUrl = absoluteUrl('/') . '?' . http_build_query([
    'utm_source' => 'widget',
    'utm_medium' => 'embed',
    'utm_campaign' => $ref !== '' ? $ref : 'partner',
]);
$siteName = 'KupiTelefon.rs';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Oglasi — <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --bg: #eef2f5;
            --card: #ffffff;
            --text: #1f2933;
            --muted: #6b7280;
            --link: #1760a8;
            --price: #1760a8;
            --border: #dde3ea;
            --green: #2d7a3e;
            --yellow: #f5c518;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
            background: linear-gradient(180deg, #f7f9fb 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.35;
        }
        .banner {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
        }
        .topbar {
            background: #fff;
            border-bottom: 2px solid var(--yellow);
            padding: 10px 12px;
            text-align: center;
        }
        .brand {
            display: inline-block;
            font-weight: 800;
            color: var(--green);
            text-decoration: none;
            font-size: 14px;
            letter-spacing: -.01em;
        }
        .brand span { color: var(--text); }
        .tagline {
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted);
        }
        .list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 10px;
            flex: 1;
        }
        .card {
            display: flex;
            flex-direction: column;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 1px 3px rgba(16, 24, 40, .06);
        }
        .card:hover { border-color: #b7c5d6; }
        .thumb {
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #e8edf2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            overflow: hidden;
        }
        .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .body {
            padding: 10px 11px 11px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .title {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--link);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .meta {
            color: var(--muted);
            font-size: 11px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .price {
            flex-shrink: 0;
            background: var(--price);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            border-radius: 6px;
            padding: 5px 8px;
            white-space: nowrap;
        }
        .price.is-open {
            background: #fff8e8;
            color: #8a6400;
            border: 1px solid #f0d78c;
        }
        .empty {
            margin: 10px;
            background: var(--card);
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 24px 12px;
            text-align: center;
            color: var(--muted);
        }
        .bottom {
            margin-top: auto;
            padding: 0 10px 10px;
        }
        .cta {
            display: block;
            text-align: center;
            background: var(--green);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
        }
        .cta:hover { filter: brightness(.96); }
        .foot {
            margin-top: 8px;
            text-align: center;
            font-size: 10px;
            color: var(--muted);
        }
        .foot a { color: var(--green); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<div class="banner">
    <div class="topbar">
        <a class="brand" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Kupi<span>Telefon</span></a>
        <div class="tagline">Telefone · oprema · servis</div>
    </div>

    <?php if ($ads === []): ?>
        <div class="empty">Trenutno nema oglasa za prikaz.</div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($ads as $ad):
                $price = (string)($ad['price'] ?? '');
                $isOpen = str_contains(mb_strtolower($price), 'dogovoru') || str_contains(mb_strtolower($price), 'kontakt');
                ?>
                <a class="card" href="<?= htmlspecialchars((string)$ad['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <div class="thumb">
                        <?php if (!empty($ad['image'])): ?>
                            <img src="<?= htmlspecialchars((string)$ad['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                        <?php else: ?>
                            OGLAS
                        <?php endif; ?>
                    </div>
                    <div class="body">
                        <h2 class="title"><?= htmlspecialchars((string)$ad['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="row">
                            <div class="meta"><?= htmlspecialchars((string)($ad['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="price<?= $isOpen ? ' is-open' : '' ?>"><?= htmlspecialchars($price, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bottom">
        <a class="cta" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Otvori KupiTelefon.rs</a>
        <div class="foot">Powered by <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">KupiTelefon.rs</a></div>
    </div>
</div>
</body>
</html>
