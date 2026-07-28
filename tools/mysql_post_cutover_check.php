<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function say(string $line): void
{
    echo $line . PHP_EOL;
}

$driver = strtolower((string)envValue('STORAGE_DRIVER', 'json'));
say('STORAGE_DRIVER=' . $driver);
if ($driver !== 'mysql') {
    fwrite(STDERR, "Storage driver is not mysql.\n");
    exit(2);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tables = ['json_documents', 'users', 'ads', 'messages', 'credit_transactions', 'notifications'];
foreach ($tables as $table) {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    say(sprintf('%-20s %d', $table, $count));
}

say('Post-cutover check OK.');
exit(0);

