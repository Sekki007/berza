<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function loadJsonRaw(string $file): array
{
    $path = dirname(__DIR__) . '/data/' . $file;
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function printLine(string $line): void
{
    echo $line . PHP_EOL;
}

function dbCount(PDO $pdo, string $table): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$checks = [
    ['users.json', 'users'],
    ['ads.json', 'ads'],
    ['messages.json', 'messages'],
    ['ratings.json', 'ratings'],
    ['reports.json', 'reports'],
    ['notifications.json', 'notifications'],
    ['top_orders.json', 'top_orders'],
    ['credit_deposits.json', 'credit_deposits'],
    ['credit_transactions.json', 'credit_transactions'],
    ['saved_searches.json', 'saved_searches'],
];

$failed = false;
printLine('== Count parity checks (JSON vs MySQL tables) ==');
foreach ($checks as [$jsonFile, $table]) {
    $jsonCount = count(loadJsonRaw($jsonFile));
    $sqlCount = dbCount($pdo, $table);
    $ok = $jsonCount === $sqlCount;
    printLine(sprintf(
        '[%s] %-24s JSON=%d MySQL=%d',
        $ok ? 'OK' : 'DIFF',
        $jsonFile,
        $jsonCount,
        $sqlCount
    ));
    if (!$ok) {
        $failed = true;
    }
}

printLine('');
printLine('== JSON storage layer checks (json_documents) ==');
$docCount = dbCount($pdo, 'json_documents');
$files = glob(dirname(__DIR__) . '/data/*.json') ?: [];
printLine(sprintf('json_documents rows: %d', $docCount));
printLine(sprintf('data/*.json files:    %d', count($files)));
if ($docCount < count($files)) {
    $failed = true;
    printLine('[DIFF] json_documents has fewer rows than local JSON files.');
}

$siteJson = loadJsonRaw('settings.json');
$siteDoc = $pdo->prepare('SELECT payload FROM json_documents WHERE filename = :f LIMIT 1');
$siteDoc->execute([':f' => 'settings.json']);
$settingsPayload = (string)($siteDoc->fetchColumn() ?: '');
if ($settingsPayload !== '') {
    $decoded = json_decode($settingsPayload, true);
    if (!is_array($decoded) || $decoded !== $siteJson) {
        $failed = true;
        printLine('[DIFF] settings.json payload mismatch in json_documents.');
    } else {
        printLine('[OK] settings.json payload in json_documents matches file.');
    }
}

printLine('');
if ($failed) {
    printLine('Validation result: FAILED (review DIFF lines).');
    exit(2);
}
printLine('Validation result: OK');
exit(0);

