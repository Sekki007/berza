<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/sitemap_seo.php';

$requestPath = rtrim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/') ?: '/';
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) && $requestPath === '/sitemap.php') {
    header('Location: /sitemap.xml', true, 301);
    exit;
}

$part = trim((string)($_GET['part'] ?? 'index'));
if ($part === '') {
    $part = 'index';
}
$xml = seoSitemapDocument($part);
if ($xml === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nepoznat sitemap.';
    exit;
}

header_remove('Expires');
header_remove('Pragma');
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
echo $xml;
