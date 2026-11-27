<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/cene-polovnih-telefona';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$check(priceStatsFromValues([100, 110]) === null, 'uzorak 2 je null');
$stats = priceStatsFromValues([100, 200, 300, 10000]);
$check($stats !== null && $stats['max'] < 10000, 'outlier 10000 je izbačen');
$check($stats !== null && $stats['count'] === 3, 'ostaju 3 cene');
$check(priceStatsFromValues([0, 0, 10, 20]) === null, 'nule se ne računaju kao uzorak');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
