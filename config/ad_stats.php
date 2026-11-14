<?php

declare(strict_types=1);

/**
 * Pregledi / otvaranja broja / poruke.
 *
 * JSON režim i dalje drži sve u ad_stats.json (lokalni razvoj i testovi).
 * MySQL režim drži brojače u uskim tabelama ad_counter i ad_counter_daily
 * i radi atomski UPDATE ... SET views = views + 1. Tako jedan pregled ne
 * prepisuje cele dokumente ads.json (~2 MB) i ad_stats.json (~3.5 MB), što
 * je punilo binlog (ROW, binlog_row_image=FULL).
 */

function ensureAdStatsFile(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('ad_stats.json'))) {
        writeJsonFile('ad_stats.json', []);
    }
}

function &adCounterRuntime(): array
{
    static $state = [
        'migration' => 'new',
        'views_loaded' => false,
        'views' => [],
        'stats_loaded' => false,
        'stats' => [],
    ];
    return $state;
}

function forgetAdCounterCaches(): void
{
    $state = &adCounterRuntime();
    $state['views_loaded'] = false;
    $state['views'] = [];
    $state['stats_loaded'] = false;
    $state['stats'] = [];
}

function adCounterKeepDays(): int
{
    return 60;
}

function adCounterNonNeg(mixed $value): int
{
    $n = (int)$value;
    return $n > 0 ? $n : 0;
}

function adCounterValidDay(string $day): bool
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m) !== 1) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/**
 * @param array<string,mixed> $daily
 * @return array<string,mixed>
 */
function adCounterTrimDailyBuckets(array $daily): array
{
    if (count($daily) <= adCounterKeepDays()) {
        return $daily;
    }
    ksort($daily);
    return array_slice($daily, -adCounterKeepDays(), null, true);
}

/**
 * Početno stanje reda kad oglas još nema brojač.
 * Javni broj pregleda živi na oglasu (uključuje početni seed 28–72).
 * Tek ako je taj broj 0, pregled dobija novi seed — isto kao stari incrementAdViews.
 *
 * @return array{views:int,phone_reveals:int,messages_started:int}
 */
function adCounterInitialTotals(int $jsonViews, string $field, int $by, int $seed): array
{
    $views = max(0, $jsonViews);
    if ($field === 'views' && $views < 1) {
        $views = max(0, $seed);
    }
    $row = [
        'views' => $views,
        'phone_reveals' => 0,
        'messages_started' => 0,
    ];
    $row[$field] = (int)$row[$field] + $by;
    return $row;
}

/**
 * @param list<mixed>|array<string,mixed> $ads
 * @param array<string,mixed> $stats
 * @return array{
 *   totals: array<int, array{views:int,phone_reveals:int,messages_started:int}>,
 *   daily: list<array{ad_id:int,day:string,views:int,phone_reveals:int,messages_started:int}>
 * }
 */
function buildAdCounterMigrationPlan(array $ads, array $stats): array
{
    $totals = [];
    $daily = [];

    foreach ($stats as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $adId = (int)($row['ad_id'] ?? $key);
        if ($adId <= 0) {
            continue;
        }
        $totals[$adId] = [
            'views' => adCounterNonNeg($row['views'] ?? 0),
            'phone_reveals' => adCounterNonNeg($row['phone_reveals'] ?? 0),
            'messages_started' => adCounterNonNeg($row['messages_started'] ?? 0),
        ];
        $cleanDaily = [];
        $rawDaily = is_array($row['daily'] ?? null) ? $row['daily'] : [];
        foreach ($rawDaily as $day => $bucket) {
            $day = (string)$day;
            if (!is_array($bucket) || !adCounterValidDay($day)) {
                continue;
            }
            $cleanDaily[$day] = $bucket;
        }
        foreach (adCounterTrimDailyBuckets($cleanDaily) as $day => $bucket) {
            $daily[] = [
                'ad_id' => $adId,
                'day' => $day,
                'views' => adCounterNonNeg($bucket['views'] ?? 0),
                'phone_reveals' => adCounterNonNeg($bucket['phone_reveals'] ?? 0),
                'messages_started' => adCounterNonNeg($bucket['messages_started'] ?? 0),
            ];
        }
    }

    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $adId = (int)($ad['id'] ?? 0);
        if ($adId <= 0) {
            continue;
        }
        $jsonViews = adCounterNonNeg($ad['views'] ?? 0);
        if (!isset($totals[$adId])) {
            $totals[$adId] = [
                'views' => $jsonViews,
                'phone_reveals' => 0,
                'messages_started' => 0,
            ];
            continue;
        }
        // Kartice i detalj oglasa prikazuju ads.json views (seed + pregledi).
        // ad_stats views broji samo stvarne preglede, pa je često manji.
        if ($jsonViews > $totals[$adId]['views']) {
            $totals[$adId]['views'] = $jsonViews;
        }
    }

    return ['totals' => $totals, 'daily' => $daily];
}

function ensureAdCounterTables(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo = db();
    try {
        // Posle prvog deploy-a ovo je samo čitanje. CREATE na svakom zahtevu
        // bi išao u binlog i uzimao metadata lock.
        $pdo->query('SELECT 1 FROM ad_counter_meta LIMIT 0');
        $pdo->query('SELECT 1 FROM ad_counter LIMIT 0');
        $pdo->query('SELECT 1 FROM ad_counter_daily LIMIT 0');
        $ready = true;
        return;
    } catch (Throwable $e) {
        // Tabele još ne postoje.
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ad_counter (
            ad_id INT UNSIGNED NOT NULL PRIMARY KEY,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
            messages_started INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ad_counter_daily (
            ad_id INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
            messages_started INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (ad_id, day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ad_counter_meta (
            meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $ready = true;
}

function adCounterMigrationDone(): bool
{
    if (!function_exists('usesMySqlStorage') || !usesMySqlStorage()) {
        return false;
    }
    ensureAdCounterTables();
    $stmt = db()->query("SELECT meta_value FROM ad_counter_meta WHERE meta_key = 'migrated_at' LIMIT 1");
    $value = $stmt->fetchColumn();
    return is_string($value) && $value !== '';
}

function adCounterLock(int $seconds): bool
{
    $stmt = db()->prepare('SELECT GET_LOCK(?, ?)');
    $stmt->execute(['berza_ad_counter_migrate', $seconds]);
    return (int)$stmt->fetchColumn() === 1;
}

function adCounterUnlock(): void
{
    $stmt = db()->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->execute(['berza_ad_counter_migrate']);
}

function adCounterInsertChunks(PDO $pdo, string $sqlPrefix, string $updateSql, array $rows, int $width): void
{
    foreach (array_chunk($rows, 200) as $chunk) {
        if ($chunk === []) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, $width, '?')) . ')'));
        $params = [];
        foreach ($chunk as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }
        $pdo->prepare($sqlPrefix . $placeholders . $updateSql)->execute($params);
    }
}

function migrateAdCountersFromJsonDocuments(): void
{
    $plan = buildAdCounterMigrationPlan(readJsonFile('ads.json'), readJsonFile('ad_stats.json'));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $totalRows = [];
        foreach ($plan['totals'] as $adId => $row) {
            $totalRows[] = [$adId, $row['views'], $row['phone_reveals'], $row['messages_started']];
        }
        adCounterInsertChunks(
            $pdo,
            'INSERT INTO ad_counter (ad_id, views, phone_reveals, messages_started) VALUES ',
            ' ON DUPLICATE KEY UPDATE views = VALUES(views), phone_reveals = VALUES(phone_reveals), messages_started = VALUES(messages_started)',
            $totalRows,
            4
        );

        $dailyRows = [];
        foreach ($plan['daily'] as $row) {
            $dailyRows[] = [$row['ad_id'], $row['day'], $row['views'], $row['phone_reveals'], $row['messages_started']];
        }
        adCounterInsertChunks(
            $pdo,
            'INSERT INTO ad_counter_daily (ad_id, day, views, phone_reveals, messages_started) VALUES ',
            ' ON DUPLICATE KEY UPDATE views = VALUES(views), phone_reveals = VALUES(phone_reveals), messages_started = VALUES(messages_started)',
            $dailyRows,
            5
        );

        $marker = date('c') . ' totals=' . count($totalRows) . ' daily=' . count($dailyRows);
        if (strlen($marker) > 255) {
            $marker = substr($marker, 0, 255);
        }
        $meta = $pdo->prepare(
            'INSERT INTO ad_counter_meta (meta_key, meta_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)'
        );
        $meta->execute(['migrated_at', $marker]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ensureAdCountersMigrated(): bool
{
    $state = &adCounterRuntime();
    if ($state['migration'] === 'ok') {
        return true;
    }
    if ($state['migration'] === 'failed') {
        return false;
    }
    if (!function_exists('usesMySqlStorage') || !usesMySqlStorage()) {
        $state['migration'] = 'failed';
        return false;
    }

    try {
        ensureAdCounterTables();
        if (adCounterMigrationDone()) {
            $state['migration'] = 'ok';
            return true;
        }
        if (!adCounterLock(60)) {
            error_log('ad counter migration lock timeout');
            $state['migration'] = 'failed';
            return false;
        }
        try {
            if (adCounterMigrationDone()) {
                $state['migration'] = 'ok';
                return true;
            }
            if (function_exists('maybeBackfillAdStartingViews')) {
                maybeBackfillAdStartingViews();
            }
            migrateAdCountersFromJsonDocuments();
            $state['migration'] = 'ok';
            return true;
        } finally {
            adCounterUnlock();
        }
    } catch (Throwable $e) {
        error_log('ad counter migration failed: ' . $e->getMessage());
        $state['migration'] = 'failed';
        return false;
    }
}

/**
 * @return array<int,int>
 */
function adCounterViewMap(): array
{
    $state = &adCounterRuntime();
    if ($state['views_loaded']) {
        return $state['views'];
    }
    if (!function_exists('usesMySqlStorage') || !usesMySqlStorage() || !ensureAdCountersMigrated()) {
        $state['views'] = [];
        $state['views_loaded'] = true;
        return $state['views'];
    }
    $map = [];
    foreach (db()->query('SELECT ad_id, views FROM ad_counter') as $row) {
        $map[(int)$row['ad_id']] = (int)$row['views'];
    }
    $state['views'] = $map;
    $state['views_loaded'] = true;
    return $map;
}

function overlayAdCounterViews(array $ads): array
{
    if (!function_exists('usesMySqlStorage') || !usesMySqlStorage()) {
        return $ads;
    }
    $map = adCounterViewMap();
    if ($map === []) {
        return $ads;
    }
    foreach ($ads as &$ad) {
        if (!is_array($ad)) {
            continue;
        }
        $adId = (int)($ad['id'] ?? 0);
        if ($adId > 0 && array_key_exists($adId, $map)) {
            $ad['views'] = $map[$adId];
        }
    }
    unset($ad);
    return $ads;
}

function getAllAdStatsFromCounters(): array
{
    $state = &adCounterRuntime();
    if ($state['stats_loaded']) {
        return $state['stats'];
    }
    $all = [];
    foreach (db()->query('SELECT ad_id, views, phone_reveals, messages_started FROM ad_counter') as $row) {
        $id = (int)$row['ad_id'];
        $all[(string)$id] = [
            'ad_id' => $id,
            'views' => (int)$row['views'],
            'phone_reveals' => (int)$row['phone_reveals'],
            'messages_started' => (int)$row['messages_started'],
            'daily' => [],
        ];
    }
    foreach (db()->query('SELECT ad_id, day, views, phone_reveals, messages_started FROM ad_counter_daily') as $row) {
        $id = (int)$row['ad_id'];
        $key = (string)$id;
        if (!isset($all[$key])) {
            $all[$key] = [
                'ad_id' => $id,
                'views' => 0,
                'phone_reveals' => 0,
                'messages_started' => 0,
                'daily' => [],
            ];
        }
        $day = substr((string)$row['day'], 0, 10);
        $all[$key]['daily'][$day] = [
            'views' => (int)$row['views'],
            'phone_reveals' => (int)$row['phone_reveals'],
            'messages_started' => (int)$row['messages_started'],
        ];
    }
    $state['stats'] = $all;
    $state['stats_loaded'] = true;
    return $all;
}

function getAllAdStats(): array
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage() && ensureAdCountersMigrated()) {
        return getAllAdStatsFromCounters();
    }
    ensureAdStatsFile();
    return readJsonFile('ad_stats.json');
}

function getAdStats(int $adId): array
{
    $all = getAllAdStats();
    $key = (string)$adId;
    $row = $all[$key] ?? [];
    return [
        'ad_id' => $adId,
        'views' => (int)($row['views'] ?? 0),
        'phone_reveals' => (int)($row['phone_reveals'] ?? 0),
        'messages_started' => (int)($row['messages_started'] ?? 0),
        'daily' => is_array($row['daily'] ?? null) ? $row['daily'] : [],
    ];
}

function adJsonViewsForCounter(int $adId): int
{
    foreach (readJsonFile('ads.json') as $ad) {
        if (is_array($ad) && (int)($ad['id'] ?? 0) === $adId) {
            return adCounterNonNeg($ad['views'] ?? 0);
        }
    }
    return 0;
}

function bumpAdStatMysql(int $adId, string $field, int $by): bool
{
    if (!ensureAdCountersMigrated()) {
        error_log('bumpAdStat: ad counters are not available');
        return false;
    }

    $adds = ['views' => 0, 'phone_reveals' => 0, 'messages_started' => 0];
    $adds[$field] = $by;
    $seedFlag = $field === 'views' ? 1 : 0;
    $seed = $seedFlag === 1
        ? (function_exists('randomAdStartingViews') ? randomAdStartingViews() : random_int(28, 72))
        : 0;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE ad_counter
             SET views = IF(views < 1 AND ? = 1, ?, views) + ?,
                 phone_reveals = phone_reveals + ?,
                 messages_started = messages_started + ?
             WHERE ad_id = ?'
        );
        $update->execute([
            $seedFlag,
            $seed,
            $adds['views'],
            $adds['phone_reveals'],
            $adds['messages_started'],
            $adId,
        ]);

        // rowCount 0 znači da reda nema (PDO bez MYSQL_ATTR_FOUND_ROWS).
        // UPDATE uvek menja bar jedno polje kad red postoji, pa ne može da
        // se pomeša sa „nema izmene”.
        if ($update->rowCount() === 0) {
            $init = adCounterInitialTotals(adJsonViewsForCounter($adId), $field, $by, $seed);
            $insert = $pdo->prepare(
                'INSERT INTO ad_counter (ad_id, views, phone_reveals, messages_started)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   views = IF(views < 1 AND ? = 1, ?, views) + ?,
                   phone_reveals = phone_reveals + ?,
                   messages_started = messages_started + ?'
            );
            $insert->execute([
                $adId,
                $init['views'],
                $init['phone_reveals'],
                $init['messages_started'],
                $seedFlag,
                $seed,
                $adds['views'],
                $adds['phone_reveals'],
                $adds['messages_started'],
            ]);
        }

        $day = date('Y-m-d');
        $daily = $pdo->prepare(
            'INSERT INTO ad_counter_daily (ad_id, day, views, phone_reveals, messages_started)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               views = views + ?,
               phone_reveals = phone_reveals + ?,
               messages_started = messages_started + ?'
        );
        $daily->execute([
            $adId,
            $day,
            $adds['views'],
            $adds['phone_reveals'],
            $adds['messages_started'],
            $adds['views'],
            $adds['phone_reveals'],
            $adds['messages_started'],
        ]);

        $cutoff = date('Y-m-d', strtotime('-' . (adCounterKeepDays() - 1) . ' days'));
        $prune = $pdo->prepare('DELETE FROM ad_counter_daily WHERE ad_id = ? AND day < ?');
        $prune->execute([$adId, $cutoff]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('bumpAdStat failed: ' . $e->getMessage());
        return false;
    }

    forgetAdCounterCaches();
    return true;
}

function bumpAdStat(int $adId, string $field, int $by = 1): bool
{
    if ($adId <= 0 || $by <= 0) {
        return false;
    }
    if (!in_array($field, ['views', 'phone_reveals', 'messages_started'], true)) {
        return false;
    }
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return bumpAdStatMysql($adId, $field, $by);
    }

    ensureAdStatsFile();
    $all = getAllAdStats();
    $key = (string)$adId;
    if (!isset($all[$key]) || !is_array($all[$key])) {
        $all[$key] = [
            'ad_id' => $adId,
            'views' => 0,
            'phone_reveals' => 0,
            'messages_started' => 0,
            'daily' => [],
        ];
    }
    $all[$key][$field] = (int)($all[$key][$field] ?? 0) + $by;
    $day = date('Y-m-d');
    if (!isset($all[$key]['daily'][$day]) || !is_array($all[$key]['daily'][$day])) {
        $all[$key]['daily'][$day] = ['views' => 0, 'phone_reveals' => 0, 'messages_started' => 0];
    }
    $all[$key]['daily'][$day][$field] = (int)($all[$key]['daily'][$day][$field] ?? 0) + $by;

    $daily = $all[$key]['daily'];
    if (count($daily) > adCounterKeepDays()) {
        $all[$key]['daily'] = adCounterTrimDailyBuckets($daily);
    }

    writeJsonFile('ad_stats.json', $all);
    return true;
}

function deleteAdCounters(int $adId): void
{
    if ($adId <= 0 || !function_exists('usesMySqlStorage') || !usesMySqlStorage()) {
        return;
    }
    try {
        if (!ensureAdCountersMigrated()) {
            return;
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM ad_counter_daily WHERE ad_id = ?')->execute([$adId]);
        $pdo->prepare('DELETE FROM ad_counter WHERE ad_id = ?')->execute([$adId]);
        $pdo->commit();
        forgetAdCounterCaches();
    } catch (Throwable $e) {
        if (function_exists('db') && db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('deleteAdCounters failed: ' . $e->getMessage());
    }
}

function sumAdStatsForUser(int $userId, int $days = 30): array
{
    $ads = getAdsByUserId($userId);
    $totals = ['views' => 0, 'phone_reveals' => 0, 'messages_started' => 0];
    $perAd = [];
    $cutoff = date('Y-m-d', time() - max(1, $days) * 86400);

    foreach ($ads as $ad) {
        $adId = (int)($ad['id'] ?? 0);
        $stats = getAdStats($adId);
        $row = [
            'ad' => $ad,
            'views' => (int)($ad['views'] ?? $stats['views']),
            'phone_reveals' => $stats['phone_reveals'],
            'messages_started' => $stats['messages_started'],
            'period_views' => 0,
            'period_phone_reveals' => 0,
            'period_messages' => 0,
        ];
        foreach ($stats['daily'] as $day => $bucket) {
            if ($day < $cutoff) {
                continue;
            }
            $row['period_views'] += (int)($bucket['views'] ?? 0);
            $row['period_phone_reveals'] += (int)($bucket['phone_reveals'] ?? 0);
            $row['period_messages'] += (int)($bucket['messages_started'] ?? 0);
        }
        $totals['views'] += $row['views'];
        $totals['phone_reveals'] += $row['phone_reveals'];
        $totals['messages_started'] += $row['messages_started'];
        $perAd[] = $row;
    }

    usort($perAd, static fn($a, $b) => ($b['views'] <=> $a['views']));
    return ['totals' => $totals, 'ads' => $perAd, 'days' => $days];
}
