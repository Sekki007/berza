<?php

declare(strict_types=1);

/**
 * Brojači pregleda: plan migracije, JSON režim i (ako ima MySQL) uske tabele.
 * Pokretanje: php tests/ad_counter_test.php
 */

define('KT_SKIP_BACKGROUND', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (ad counter test)';
$_SERVER['HTTPS'] = '';

$dataDir = dirname(__DIR__) . '/data';
$before = [];
foreach (glob($dataDir . '/*') ?: [] as $path) {
    if (is_file($path)) {
        $before[$path] = (string)file_get_contents($path);
    }
}
register_shutdown_function(static function () use ($dataDir, $before): void {
    $now = glob($dataDir . '/*') ?: [];
    foreach ($now as $path) {
        if (!is_file($path)) {
            continue;
        }
        if (!array_key_exists($path, $before)) {
            unlink($path);
        }
    }
    foreach ($before as $path => $contents) {
        file_put_contents($path, $contents);
    }
});

require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "OK  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$initial = adCounterInitialTotals(40, 'views', 1, 55);
check($initial === ['views' => 41, 'phone_reveals' => 0, 'messages_started' => 0], 'postojeći pregledi se uvećavaju bez novog seed-a');

$seeded = adCounterInitialTotals(0, 'views', 1, 30);
check($seeded['views'] === 31 && $seeded['phone_reveals'] === 0, 'nula pregleda na prvom view dobija seed + 1');

$phone = adCounterInitialTotals(40, 'phone_reveals', 1, 30);
check($phone === ['views' => 40, 'phone_reveals' => 1, 'messages_started' => 0], 'otvaranje broja ne dira seed pregleda');

$daily = [];
$start = new DateTimeImmutable('2024-01-01');
for ($i = 0; $i < 61; $i++) {
    $day = $start->modify('+' . $i . ' days')->format('Y-m-d');
    $daily[$day] = ['views' => $i + 1, 'phone_reveals' => 0, 'messages_started' => 0];
}
$stats = [
    '4' => [
        'views' => 12,
        'phone_reveals' => 2,
        'messages_started' => '3',
        'daily' => $daily + ['2024-02-31' => ['views' => 9, 'phone_reveals' => 0, 'messages_started' => 0], 'nope' => []],
    ],
    'bad' => 'x',
    '0' => ['ad_id' => 0, 'views' => 5],
];
$ads = [
    ['id' => 4, 'views' => '80'],
    ['id' => 5, 'views' => 7],
    ['id' => '0', 'views' => 9],
    'trash',
];
$plan = buildAdCounterMigrationPlan($ads, $stats);
check(($plan['totals'][4]['views'] ?? 0) === 80, 'javni broj pregleda sa oglasa pobeđuje manji ad_stats');
check(($plan['totals'][4]['phone_reveals'] ?? 0) === 2, 'phone_reveals ostaje iz ad_stats');
check(($plan['totals'][4]['messages_started'] ?? 0) === 3, 'messages_started se čita i kad je string');
check(($plan['totals'][5]['views'] ?? -1) === 7, 'oglas bez statistike dobija svoj views');
check(!isset($plan['totals'][0]), 'id 0 se preskače');
check(count($plan['daily']) === 60, 'dnevni bucketi se skraćuju na 60 i loši datumi otpadaju');
check($plan['daily'][0]['day'] === '2024-01-02', 'najstariji dan ispada');
check($plan['daily'][59]['day'] === '2024-03-01', 'najnoviji dan ostaje');

check(jsonPayloadsEqual(['b' => 1, 'a' => 'ć'], "{\n  \"a\": \"ć\",\n  \"b\": 1\n}"), 'isti sadržaj sa drugim razmacima i redom ključeva');
check(!jsonPayloadsEqual(['a' => 2], '{"a":1}'), 'drugačija vrednost nije ista');
check(!jsonPayloadsEqual([['id' => 2], ['id' => 1]], '[{"id":1},{"id":2}]'), 'redosled liste oglasa jeste razlika');

$statsPath = $dataDir . '/ad_stats.json';
$originalStats = $before[$statsPath] ?? null;
writeJsonFile('ad_stats.json', []);
check(bumpAdStat(15, 'views', 2) === true, 'JSON bump uspeva');
$stored = readJsonFile('ad_stats.json');
$today = date('Y-m-d');
check((int)($stored['15']['views'] ?? 0) === 2, 'JSON bump podiže ukupan broj');
check((int)($stored['15']['daily'][$today]['views'] ?? 0) === 2, 'JSON bump piše današnji bucket');
check(bumpAdStat(15, 'phone_reveals') === true, 'JSON bump phone');
$stored = readJsonFile('ad_stats.json');
check((int)($stored['15']['phone_reveals'] ?? 0) === 1, 'JSON phone raste za 1');
check((int)($stored['15']['views'] ?? 0) === 2, 'JSON phone ne dira views');
if ($originalStats === null) {
    unlink($statsPath);
} else {
    file_put_contents($statsPath, $originalStats);
}

$mysqlTest = __DIR__ . '/ad_counter_mysql_test.php';
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($mysqlTest), $mysqlCode);
check($mysqlCode === 0, 'MySQL test brojača');

if ($failed > 0) {
    echo "FAILED {$failed}\n";
    exit(1);
}
echo "ALL OK\n";
exit(0);
