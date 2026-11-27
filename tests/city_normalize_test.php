<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$bg = normalizeCity('Beograd | Novi Bg.');
$check($bg['city'] === 'Beograd' && $bg['district'] === 'Novi Beograd', 'Novi Bg. je Beograd / Novi Beograd');
$check(normalizeCity('Beograd')['city'] === 'Beograd' && normalizeCity('Beograd')['district'] === '', 'Beograd ostaje Beograd');
$check(normalizeCity('Novi Beograd')['city'] === 'Beograd', 'Novi Beograd je grad Beograd');
$check(normalizeCity('Novi Sad')['city'] === 'Novi Sad', 'Novi Sad');

$target = catalogCleanupTarget(
    ['brand_slug' => 'apple', 'slug' => '16-pro'],
    ['apple/iphone-16-pro' => ['id' => 'x'], 'apple/16-pro' => ['id' => 'y']]
);
$check($target === 'apple/iphone-16-pro', '16-pro se spaja na iphone-16-pro');

$map = catalogRedirectFor('nema', 'nema');
$check($map === null, 'prazna mapa ne preusmerava');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
