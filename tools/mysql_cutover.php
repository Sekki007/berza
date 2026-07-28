<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function copyDirRecursive(string $src, string $dst): void
{
    if (!is_dir($dst) && !mkdir($dst, 0777, true) && !is_dir($dst)) {
        throw new RuntimeException('Cannot create directory: ' . $dst);
    }
    $items = scandir($src);
    if (!is_array($items)) {
        throw new RuntimeException('Cannot read directory: ' . $src);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($from)) {
            copyDirRecursive($from, $to);
        } else {
            if (!copy($from, $to)) {
                throw new RuntimeException('Failed copying: ' . $from);
            }
        }
    }
}

function replaceEnvStorageDriver(string $targetDriver): void
{
    $envPath = dirname(__DIR__) . '/.env';
    if (!is_file($envPath)) {
        throw new RuntimeException('.env file not found: ' . $envPath);
    }
    $raw = file_get_contents($envPath);
    if (!is_string($raw)) {
        throw new RuntimeException('Cannot read .env');
    }
    if (preg_match('/^STORAGE_DRIVER=.*$/m', $raw)) {
        $raw = preg_replace('/^STORAGE_DRIVER=.*$/m', 'STORAGE_DRIVER=' . $targetDriver, $raw) ?? $raw;
    } else {
        $raw .= PHP_EOL . 'STORAGE_DRIVER=' . $targetDriver . PHP_EOL;
    }
    file_put_contents($envPath, $raw);
}

function importJsonDocuments(PDO $pdo): int
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS json_documents (
            filename VARCHAR(120) NOT NULL PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $stmt = $pdo->prepare(
        'INSERT INTO json_documents (filename, payload, updated_at)
         VALUES (:filename, :payload, NOW())
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = NOW()'
    );
    $files = glob(dirname(__DIR__) . '/data/*.json') ?: [];
    $count = 0;
    foreach ($files as $filePath) {
        $payload = file_get_contents($filePath);
        if (!is_string($payload) || trim($payload) === '') {
            $payload = '[]';
        }
        $stmt->execute([
            ':filename' => basename($filePath),
            ':payload' => $payload,
        ]);
        $count++;
    }
    return $count;
}

$argvList = $argv ?? [];
$apply = in_array('--apply', $argvList, true);

if (!$apply) {
    out('Usage: php tools/mysql_cutover.php --apply');
    out('This script snapshots data/*.json, imports json_documents, and flips STORAGE_DRIVER=mysql.');
    exit(1);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$timestamp = date('Ymd_His');
$backupRoot = dirname(__DIR__) . '/backups/mysql-cutover-' . $timestamp;
$dataDir = dirname(__DIR__) . '/data';

try {
    out('Step 1/3 Snapshot data files...');
    copyDirRecursive($dataDir, $backupRoot . '/data');
    out('Backup created: ' . $backupRoot);

    out('Step 2/3 Import json_documents...');
    $docs = importJsonDocuments($pdo);
    out('Imported json_documents rows: ' . $docs);

    out('Step 3/3 Switch STORAGE_DRIVER=mysql...');
    replaceEnvStorageDriver('mysql');
    out('STORAGE_DRIVER updated to mysql');
} catch (Throwable $e) {
    fwrite(STDERR, 'Cutover failed: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

out('');
out('Cutover completed.');
out('Next: run `php tools/validate_mysql_import.php` and smoke-test core flows.');
exit(0);

