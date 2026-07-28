<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$filePath = __DIR__ . '/public' . $uri;

if (preg_match('#^/oglas/(\d+)(?:-.*)?/?$#', $uri, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/public/oglas.php';
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

if ($uri === '/sitemap.xml' || $uri === '/sitemap.php') {
    require __DIR__ . '/public/sitemap.php';
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
