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
            --bg: #f3f5f7;
            --card: #fff;
            --text: #1f2933;
            --muted: #6b7280;
            --link: #1760a8;
            --price: #1760a8;
            --border: #e5e7eb;
            --green: #2d7a3e;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.35;
        }
        .wrap { padding: 8px; }
        .head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
            padding: 0 2px;
        }
        .brand {
            font-weight: 800;
            color: var(--green);
            text-decoration: none;
            font-size: 13px;
        }
        .brand:hover { text-decoration: underline; }
        .list { display: flex; flex-direction: column; gap: 8px; }
        .card {
            display: flex;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px;
            text-decoration: none;
            color: inherit;
            transition: border-color .15s ease;
        }
        .card:hover { border-color: #b8c4d4; }
        .thumb {
            width: 72px;
            min-width: 72px;
            height: 72px;
            border-radius: 8px;
            overflow: hidden;
            background: #eef1f4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }
        .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
        .title {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--link);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .meta {
            color: var(--muted);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .price {
            margin-top: auto;
            font-size: 14px;
            font-weight: 800;
            color: var(--price);
        }
        .empty {
            background: var(--card);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 18px 12px;
            text-align: center;
            color: var(--muted);
        }
        .foot {
            margin-top: 8px;
            text-align: center;
            font-size: 11px;
            color: var(--muted);
        }
        .foot a { color: var(--green); font-weight: 700; text-decoration: none; }
        .foot a:hover { text-decoration: underline; }
        .cta {
            display: block;
            margin-top: 8px;
            text-align: center;
            background: var(--green);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12px;
        }
        .cta:hover { filter: brightness(.95); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <a class="brand" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a>
        <span style="color:var(--muted);font-size:11px;">Najnoviji oglasi</span>
    </div>

    <?php if ($ads === []): ?>
        <div class="empty">Trenutno nema oglasa za prikaz.</div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($ads as $ad): ?>
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
                        <?php if ((string)($ad['location'] ?? '') !== ''): ?>
                            <div class="meta"><?= htmlspecialchars((string)$ad['location'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div class="price"><?= htmlspecialchars((string)$ad['price'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="cta" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Pogledaj više na KupiTelefon</a>
    <div class="foot">Powered by <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">KupiTelefon.rs</a></div>
</div>
</body>
</html>
