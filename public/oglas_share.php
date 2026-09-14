<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$ad = $id > 0 ? getAdById($id) : null;
if (!$ad || (int)($ad['is_active'] ?? 0) !== 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Oglas nije pronađen.';
    exit;
}

$pathRel = ensureAdShareCard($ad);
$fs = adShareCardFilesystemPath($id);
if ($pathRel === '' || !is_file($fs)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Slika oglasa nije dostupna.';
    exit;
}

$download = isset($_GET['download']) && $_GET['download'] !== '0';
$filename = 'kupitelefon-' . $id . '.jpg';

header('Content-Type: image/jpeg');
header('Content-Length: ' . (string)filesize($fs));
header('Cache-Control: public, max-age=86400');
header(($download ? 'Content-Disposition: attachment; filename="' : 'Content-Disposition: inline; filename="') . $filename . '"');
readfile($fs);
exit;
