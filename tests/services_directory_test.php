<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/servisi';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$check(directoryCityUrl('Kragujevac', 'service') === '/servis-telefona/kragujevac', 'servis grad: ' . directoryCityUrl('Kragujevac', 'service'));
$check(directoryCityUrl('Kragujevac', 'shop') === '/prodavnice-telefona/kragujevac', 'prodavnice grad');
$check(directoryHubUrl('service') === '/servis-telefona', 'hub servisa');
$check(directoryHubUrl('shop') === '/prodavnice-telefona', 'hub prodavnica');
$check(directoryCityUrl('Kragujevac') === '/servisi/kragujevac', 'kombinovani grad ostaje /servisi');
$check(!str_contains(directoryCityUrl('Kragujevac', 'service'), '?'), 'nema ?tip=');

$meta = seoDirectoryCityMeta('Kragujevac', 4, 'service');
$check(str_contains($meta['title'], '4'), 'naslov ima broj servisa: ' . $meta['title']);
$check(mb_strlen($meta['description']) <= 160, 'opis servisa ≤ 160');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
