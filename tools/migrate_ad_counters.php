<?php

declare(strict_types=1);

/**
 * Jednokratno (idempotentno) prebacivanje pregleda iz json_documents
 * u ad_counter / ad_counter_daily.
 *
 * Posle deploy-a ovo radi i samo, na prvom zahtevu. Pokreni ručno odmah
 * posle git pull da prvi posetilac ne čeka migraciju:
 *
 *   php tools/migrate_ad_counters.php
 *   php tools/migrate_ad_counters.php --check
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

function counterScriptOut(string $line): void
{
    echo $line . PHP_EOL;
}

if (!usesMySqlStorage()) {
    fwrite(STDERR, "STORAGE_DRIVER nije mysql. Brojači ostaju u JSON fajlovima.\n");
    exit(1);
}

$checkOnly = in_array('--check', $argv ?? [], true);

try {
    ensureAdCounterTables();
} catch (Throwable $e) {
    fwrite(STDERR, 'Tabele nisu napravljene: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (!$checkOnly) {
    $already = adCounterMigrationDone();
    if (!ensureAdCountersMigrated()) {
        fwrite(STDERR, "Migracija nije uspela. Pogledaj PHP error log.\n");
        exit(1);
    }
    if ($already) {
        counterScriptOut('Brojači su već migrirani. Postojeći brojevi nisu dirani.');
    } else {
        $totals = (int)db()->query('SELECT COUNT(*) FROM ad_counter')->fetchColumn();
        $daily = (int)db()->query('SELECT COUNT(*) FROM ad_counter_daily')->fetchColumn();
        counterScriptOut("Migracija gotova. ad_counter: {$totals}, ad_counter_daily: {$daily}.");
    }
}

$done = adCounterMigrationDone();
counterScriptOut('migrirano: ' . ($done ? 'da' : 'ne'));
if ($done) {
    $totals = (int)db()->query('SELECT COUNT(*) FROM ad_counter')->fetchColumn();
    $daily = (int)db()->query('SELECT COUNT(*) FROM ad_counter_daily')->fetchColumn();
    $views = (int)db()->query('SELECT COALESCE(SUM(views), 0) FROM ad_counter')->fetchColumn();
    counterScriptOut("ad_counter redova: {$totals}, dnevnih: {$daily}, zbir pregleda: {$views}");
}

$docs = db()->prepare(
    'SELECT filename, CHAR_LENGTH(payload) AS chars, updated_at
     FROM json_documents
     WHERE filename IN (?, ?)
     ORDER BY filename'
);
$docs->execute(['ad_stats.json', 'ads.json']);
foreach ($docs as $row) {
    counterScriptOut(sprintf(
        '%s  chars=%s  updated_at=%s',
        $row['filename'],
        $row['chars'],
        $row['updated_at']
    ));
}

counterScriptOut('Posle otvaranja oglasa updated_at za ads.json i ad_stats.json treba da ostane isti, a zbir pregleda da poraste.');
exit($done || $checkOnly ? 0 : 1);
