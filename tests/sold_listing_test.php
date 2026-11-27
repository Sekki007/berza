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

$base = [
    'id' => 50,
    'is_active' => 1,
    'is_sold' => 1,
    'brand' => 'Apple',
    'model' => 'iPhone 13',
    'phone_model_id' => 'apple-iphone-15',
    'title' => 'iPhone 13',
    'sold_at' => date('Y-m-d H:i:s', time() - 20 * 86400),
];
$redirect = soldAdOutcome($base);
$check($redirect['action'] === 'redirect' && str_contains($redirect['url'], '/katalog/apple/iphone-15') && str_contains($redirect['url'], 'prodat=50'), 'prodat sa modelom → 301 na model: ' . $redirect['url']);

$grace = $base;
$grace['sold_at'] = date('Y-m-d H:i:s');
$check(soldAdOutcome($grace)['action'] === 'grace', 'prodat u roku od 7 dana ostaje stranica');

$noModel = $base;
$noModel['phone_model_id'] = '';
$check(soldAdOutcome($noModel)['action'] === 'gone', 'prodat bez modela je 410');

$inactive = $base;
$inactive['is_sold'] = 0;
$inactive['is_active'] = 0;
$check(soldAdOutcome($inactive)['action'] === 'gone', 'neaktivan je 410');

$live = $base;
$live['is_sold'] = 0;
$check(soldAdOutcome($live)['action'] === 'public', 'aktivan nije prodat');

$stamped = stampSoldAt(['is_sold' => 1], true);
$check(trim((string)$stamped['sold_at']) !== '', 'prodaja upisuje sold_at');
$cleared = stampSoldAt(['sold_at' => '2026-01-01', 'is_sold' => 0], false);
$check($cleared['sold_at'] === null, 'povratak u prodaju briše sold_at');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);
