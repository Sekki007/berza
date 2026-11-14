<?php

declare(strict_types=1);

/**
 * MySQL: migracija brojača ne prepisuje json_documents, a pregled je uzan UPDATE.
 * Pokretanje: php tests/ad_counter_mysql_test.php
 * Preskače se ako test baza nije dostupna.
 */

define('KT_SKIP_BACKGROUND', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/oglas/7';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (ad counter mysql test)';
$_SERVER['HTTPS'] = '';

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=berza_counter_test;charset=utf8mb4';
$user = 'berza_test';
$pass = 'berza_test';

try {
    $probe = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo "SKIP MySQL nije dostupan ({$e->getMessage()})\n";
    exit(0);
}

foreach (['ad_counter_daily', 'ad_counter', 'ad_counter_meta', 'json_documents'] as $table) {
    $probe->exec('DROP TABLE IF EXISTS ' . $table);
}
$probe = null;

putenv('STORAGE_DRIVER=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_NAME=berza_counter_test');
putenv('DB_USER=berza_test');
putenv('DB_PASS=berza_test');
putenv('APP_ENV=local');

$dataDir = dirname(__DIR__) . '/data';
$flag = $dataDir . '/.ad_views_seeded_v1';
$flagExisted = is_file($flag);
$flagBody = $flagExisted ? (string)file_get_contents($flag) : null;
if (!$flagExisted) {
    file_put_contents($flag, 'test');
}
register_shutdown_function(static function () use ($flag, $flagExisted, $flagBody): void {
    if ($flagExisted) {
        if (is_string($flagBody)) {
            file_put_contents($flag, $flagBody);
        }
        return;
    }
    if (is_file($flag)) {
        unlink($flag);
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

function docState(string $filename): array
{
    $stmt = db()->prepare('SELECT payload, updated_at FROM json_documents WHERE filename = ? LIMIT 1');
    $stmt->execute([$filename]);
    $row = $stmt->fetch();
    $payload = is_array($row) ? (string)$row['payload'] : '';
    return [
        'payload' => $payload,
        'updated_at' => is_array($row) ? (string)$row['updated_at'] : '',
        'hash' => md5($payload),
    ];
}

$ads = [
    [
        'id' => 7,
        'title' => 'Telefon',
        'views' => 150,
        'created_at' => '2026-01-01 00:00:00',
        'created_by' => 1,
        'is_active' => 1,
    ],
    [
        'id' => 8,
        'title' => 'Deo',
        'views' => 40,
        'created_at' => '2026-02-01 00:00:00',
        'created_by' => 1,
        'is_active' => 1,
    ],
    [
        'id' => 9,
        'title' => 'Nula',
        'views' => 0,
        'created_at' => '2026-03-01 00:00:00',
        'created_by' => 1,
        'is_active' => 1,
    ],
];
$stats = [
    '7' => [
        'ad_id' => 7,
        'views' => 10,
        'phone_reveals' => 3,
        'messages_started' => 1,
        'daily' => [
            '2026-11-01' => ['views' => 4, 'phone_reveals' => 1, 'messages_started' => 1],
            '2026-11-02' => ['views' => 6, 'phone_reveals' => 2, 'messages_started' => 0],
        ],
    ],
];
writeJsonFile('ads.json', $ads);
writeJsonFile('ad_stats.json', $stats);

$adsBefore = docState('ads.json');
$statsBefore = docState('ad_stats.json');
check($adsBefore['payload'] !== '', 'ads.json je upisan u json_documents');

sleep(2);
ensureJsonDataFiles();
check(docState('ads.json')['updated_at'] === $adsBefore['updated_at'], 'osnovni upis ne dira postojeći ads.json');
check(docState('ads.json')['hash'] === $adsBefore['hash'], 'osnovni upis ne menja payload ads.json');

$reordered = [];
foreach ($ads as $ad) {
    $reordered[] = [
        'views' => $ad['views'],
        'title' => $ad['title'],
        'id' => $ad['id'],
        'is_active' => $ad['is_active'],
        'created_by' => $ad['created_by'],
        'created_at' => $ad['created_at'],
    ];
}
writeJsonFile('ads.json', $reordered);
check(docState('ads.json')['hash'] === $adsBefore['hash'], 'isti oglasi drugim redom ključeva ne prave UPDATE');
check(docState('ads.json')['updated_at'] === $adsBefore['updated_at'], 'updated_at ostaje kad se sadržaj nije promenio');

check(ensureAdCountersMigrated() === true, 'migracija prolazi');
$views7 = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn();
$phone7 = (int)db()->query('SELECT phone_reveals FROM ad_counter WHERE ad_id = 7')->fetchColumn();
$msg7 = (int)db()->query('SELECT messages_started FROM ad_counter WHERE ad_id = 7')->fetchColumn();
$views8 = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 8')->fetchColumn();
check($views7 === 150, 'migracija čuva javni broj pregleda, ne manji ad_stats');
check($phone7 === 3 && $msg7 === 1, 'migracija čuva phone i poruke');
check($views8 === 40, 'oglas bez ad_stats dobija views iz oglasa');
$dailyNov = (int)db()->query("SELECT views FROM ad_counter_daily WHERE ad_id = 7 AND day = '2026-11-01'")->fetchColumn();
check($dailyNov === 4, 'dnevni bucket je prebačen');
check(docState('ads.json')['hash'] === $adsBefore['hash'], 'migracija ne prepisuje ads.json');
check(docState('ad_stats.json')['hash'] === $statsBefore['hash'], 'migracija ne prepisuje ad_stats.json');

$viewsBeforeSecond = $views7;
check(ensureAdCountersMigrated() === true, 'druga migracija je no-op');
$viewsAfterSecond = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn();
check($viewsAfterSecond === $viewsBeforeSecond, 'drugo pokretanje ne duplira preglede');

$shown = null;
foreach (getAllAds() as $ad) {
    if ((int)($ad['id'] ?? 0) === 7) {
        $shown = (int)$ad['views'];
    }
}
check($shown === 150, 'lista oglasa čita preglede iz brojača');

for ($i = 0; $i < 5; $i++) {
    check(bumpAdStat(7, 'views') === true, 'bump views ' . ($i + 1));
}
check(bumpAdStat(7, 'phone_reveals') === true, 'bump phone');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn() === 155, 'pet pregleda diže samo uski red');
check((int)db()->query('SELECT phone_reveals FROM ad_counter WHERE ad_id = 7')->fetchColumn() === 4, 'phone raste za 1');
$today = date('Y-m-d');
$todayViews = (int)db()->query('SELECT views FROM ad_counter_daily WHERE ad_id = 7 AND day = ' . db()->quote($today))->fetchColumn();
$todayPhone = (int)db()->query('SELECT phone_reveals FROM ad_counter_daily WHERE ad_id = 7 AND day = ' . db()->quote($today))->fetchColumn();
check($todayViews === 5 && $todayPhone === 1, 'današnji bucket broji samo nove događaje');
check(docState('ads.json')['payload'] === $adsBefore['payload'], 'pregledi ne menjaju ads.json payload');
check(docState('ad_stats.json')['payload'] === $statsBefore['payload'], 'pregledi ne menjaju ad_stats.json payload');
check(docState('ads.json')['updated_at'] === $adsBefore['updated_at'], 'pregledi ne pomeraju ads.json updated_at');
check(docState('ad_stats.json')['updated_at'] === $statsBefore['updated_at'], 'pregledi ne pomeraju ad_stats.json updated_at');

$shown = null;
foreach (getAllAds() as $ad) {
    if ((int)($ad['id'] ?? 0) === 7) {
        $shown = (int)$ad['views'];
    }
}
check($shown === 155, 'posle bump-a lista vidi novi broj');
$statsNow = getAdStats(7);
check($statsNow['views'] === 155 && $statsNow['phone_reveals'] === 4 && $statsNow['messages_started'] === 1, 'getAdStats čita uske tabele');
check((int)($statsNow['daily']['2026-11-01']['views'] ?? 0) === 4, 'stari dnevni bucket je i dalje tu');
check((int)($statsNow['daily'][$today]['views'] ?? 0) === 5, 'getAdStats vidi današnji bucket');

$summary = sumAdStatsForUser(1, 30);
$ad7Summary = null;
foreach ($summary['ads'] as $row) {
    if ((int)($row['ad']['id'] ?? 0) === 7) {
        $ad7Summary = $row;
    }
}
check(is_array($ad7Summary) && (int)$ad7Summary['views'] === 155, 'nalog i dalje vidi ukupne preglede');
check(is_array($ad7Summary) && (int)$ad7Summary['period_views'] === 15, 'period sabira stare dane i nove preglede');
check(is_array($ad7Summary) && (int)$ad7Summary['phone_reveals'] === 4, 'nalog vidi otvaranja broja');

check(bumpAdStat(8, 'phone_reveals') === true, 'phone na oglasu koji nije bio u ad_stats');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 8')->fetchColumn() === 40, 'phone ne resetuje views');
check((int)db()->query('SELECT phone_reveals FROM ad_counter WHERE ad_id = 8')->fetchColumn() === 1, 'phone kreće od nule pa +1');

check(bumpAdStat(9, 'views') === true, 'prvi pregled oglasa sa nulom');
$seededViews = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 9')->fetchColumn();
check($seededViews >= 29 && $seededViews <= 73, 'nula i dalje dobija početni seed pa +1');
$rawNine = null;
foreach (readJsonFile('ads.json') as $ad) {
    if ((int)($ad['id'] ?? 0) === 9) {
        $rawNine = (int)($ad['views'] ?? -1);
    }
}
check($rawNine === 0, 'seed se ne upisuje nazad u ads.json');
$shownNine = null;
foreach (getAllAds() as $ad) {
    if ((int)($ad['id'] ?? 0) === 9) {
        $shownNine = (int)$ad['views'];
    }
}
check($shownNine === $seededViews, 'prikaz uzima seed iz brojača');

$fresh = readJsonFile('ads.json');
$fresh[] = [
    'id' => 50,
    'title' => 'Nov',
    'views' => 33,
    'created_at' => '2026-04-01 00:00:00',
    'created_by' => 1,
    'is_active' => 1,
];
writeJsonFile('ads.json', $fresh);
$afterInsert = docState('ads.json');
check($afterInsert['hash'] !== $adsBefore['hash'], 'stvarna izmena oglasa i dalje piše dokument');
check(bumpAdStat(50, 'phone_reveals') === true, 'novi oglas dobija red tek na događaju');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 50')->fetchColumn() === 33, 'novi red kreće od views u oglasu');
check((int)db()->query('SELECT phone_reveals FROM ad_counter WHERE ad_id = 50')->fetchColumn() === 1, 'novi red beleži phone');
check(docState('ads.json')['payload'] === $afterInsert['payload'], 'phone na novom oglasu ne prepisuje ads.json');
check(bumpAdStat(50, 'views') === true, 'pregled novog oglasa');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 50')->fetchColumn() === 34, 'pregled novog oglasa je +1 na postojeći views');

db()->prepare('INSERT INTO ad_counter_daily (ad_id, day, views, phone_reveals, messages_started) VALUES (7, ?, 9, 0, 0)')
    ->execute(['2020-01-01']);
check(bumpAdStat(7, 'views') === true, 'bump radi i prune');
$old = db()->query("SELECT COUNT(*) FROM ad_counter_daily WHERE ad_id = 7 AND day = '2020-01-01'")->fetchColumn();
check((int)$old === 0, 'bucket stariji od 60 dana se briše');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn() === 156, 'prune ne dira ukupan broj');

$_SESSION['ad_view_hits'] = [];
$beforeInc = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn();
incrementAdViews(7);
$afterInc = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn();
check($afterInc === $beforeInc + 1, 'otvaranje oglasa diže pregled');
incrementAdViews(7);
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn() === $afterInc, 'isti posetilac u cooldown-u ne diže ponovo');
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
$_SESSION['ad_view_hits'] = [];
incrementAdViews(7);
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn() === $afterInc, 'bot ne diže pregled');
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (ad counter mysql test)';

check(deleteAdById(8) === true, 'brisanje oglasa');
check(db()->query('SELECT COUNT(*) FROM ad_counter WHERE ad_id = 8')->fetchColumn() === 0, 'briše se i uski red');
check((int)db()->query('SELECT COUNT(*) FROM ad_counter_daily WHERE ad_id = 8')->fetchColumn() === 0, 'brišu se i dnevni redovi');

$script = dirname(__DIR__) . '/tools/migrate_ad_counters.php';
$beforeScript = (int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn();
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $scriptCode);
check($scriptCode === 0, 'skripta migracije je idempotentna');
check((int)db()->query('SELECT views FROM ad_counter WHERE ad_id = 7')->fetchColumn() === $beforeScript, 'skripta ne resetuje brojače');

if ($failed > 0) {
    echo "FAILED {$failed}\n";
    exit(1);
}
echo "MYSQL ALL OK\n";
exit(0);
