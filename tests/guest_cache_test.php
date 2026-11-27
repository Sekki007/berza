<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/oglas/6-test';
$_COOKIE = [];
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$check(session_status() !== PHP_SESSION_ACTIVE, 'gost na oglasu nema aktivnu sesiju');
$check(requestNeedsSession() === false, 'javni GET oglasa ne traži sesiju');

$_SERVER['REQUEST_METHOD'] = 'POST';
$check(requestNeedsSession() === true, 'POST traži sesiju');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/prijava';
$check(requestNeedsSession() === true, 'prijava traži sesiju');
$_SERVER['REQUEST_URI'] = '/';
$_COOKIE[session_name()] = 'abc';
$check(requestNeedsSession() === true, 'postojeći kolačić sesije je zadržava');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
