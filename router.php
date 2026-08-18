<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$filePath = __DIR__ . '/public' . $uri;

if (preg_match('#^/oglas/(\d+)(?:-.*)?/?$#', $uri, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/public/oglas.php';
    exit;
}

if (preg_match('#^/izlog/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['u'] = rawurldecode($m[1]);
    $_GET['cat'] = rawurldecode($m[2]);
    $_GET['cat_from_path'] = '1';
    require __DIR__ . '/public/izlog.php';
    exit;
}

if (preg_match('#^/izlog/([^/]+)/?$#', $uri, $m)) {
    $_GET['u'] = rawurldecode($m[1]);
    require __DIR__ . '/public/izlog.php';
    exit;
}

if (preg_match('#^/usluge/([^/]+)/?$#', $uri, $m)) {
    $_GET['u'] = rawurldecode($m[1]);
    require __DIR__ . '/public/usluge.php';
    exit;
}

if ($uri === '/servisi' || $uri === '/servisi/') {
    require __DIR__ . '/public/servisi.php';
    exit;
}

if (preg_match('#^/servisi/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['city'] = rawurldecode($m[1]);
    $_GET['slug'] = rawurldecode($m[2]);
    require __DIR__ . '/public/servisi.php';
    exit;
}

if (preg_match('#^/servisi/([^/]+)/?$#', $uri, $m)) {
    $_GET['city'] = rawurldecode($m[1]);
    require __DIR__ . '/public/servisi.php';
    exit;
}

if ($uri === '/vodici' || $uri === '/vodici/' || $uri === '/blog' || $uri === '/blog/') {
    require __DIR__ . '/public/vodici.php';
    exit;
}

if ($uri === '/provera-imei' || $uri === '/provera-imei/') {
    require __DIR__ . '/public/provera-imei.php';
    exit;
}

if (in_array($uri, ['/prijava', '/prijava/', '/login', '/login/'], true)) {
    require __DIR__ . '/public/login.php';
    exit;
}

if (in_array($uri, ['/registracija', '/registracija/', '/registracij', '/registracij/', '/register', '/register/'], true)) {
    require __DIR__ . '/public/register.php';
    exit;
}

if (in_array($uri, ['/postavi-oglas', '/postavi-oglas/', '/dodaj-oglas', '/dodaj-oglas/'], true)) {
    require __DIR__ . '/public/ad_form.php';
    exit;
}

if ($uri === '/kako-radi' || $uri === '/kako-radi/') {
    require __DIR__ . '/public/kako-radi.php';
    exit;
}

if ($uri === '/privatnost' || $uri === '/privatnost/') {
    require __DIR__ . '/public/privatnost.php';
    exit;
}

if ($uri === '/uslovi' || $uri === '/uslovi/') {
    require __DIR__ . '/public/uslovi.php';
    exit;
}

if (preg_match('#^/(vodic|blog)/([^/]+)/?$#', $uri, $m)) {
    $_GET['slug'] = rawurldecode($m[2]);
    require __DIR__ . '/public/vodic.php';
    exit;
}

if ($uri === '/sitemap.xml' || $uri === '/sitemap.php') {
    require __DIR__ . '/public/sitemap.php';
    exit;
}

if ($uri === '/oglasi' || $uri === '/oglasi/' || preg_match('#^/oglasi/.+#', $uri)) {
    require __DIR__ . '/public/index.php';
    exit;
}

if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    if ($ext === 'php') {
        require $filePath;
        exit;
    }

    if (isset($mimeMap[$ext])) {
        header('Content-Type: ' . $mimeMap[$ext]);
    }
    readfile($filePath);
    exit;
}

require_once __DIR__ . '/public/index.php';
