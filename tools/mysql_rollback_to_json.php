<?php

declare(strict_types=1);

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function copyDirRecursive(string $src, string $dst): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('Source directory not found: ' . $src);
    }
    if (!is_dir($dst) && !mkdir($dst, 0777, true) && !is_dir($dst)) {
        throw new RuntimeException('Cannot create destination directory: ' . $dst);
    }
    $items = scandir($src);
    if (!is_array($items)) {
        throw new RuntimeException('Cannot read source directory: ' . $src);
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

$argvList = $argv ?? [];
$backup = null;
for ($i = 0; $i < count($argvList); $i++) {
    if ($argvList[$i] === '--backup' && isset($argvList[$i + 1])) {
        $backup = (string)$argvList[$i + 1];
        break;
    }
}

if ($backup === null || trim($backup) === '') {
    out('Usage: php tools/mysql_rollback_to_json.php --backup backups/mysql-cutover-YYYYmmdd_HHMMSS');
    exit(1);
}

$backupData = dirname(__DIR__) . '/' . trim($backup, '/\\') . '/data';
$targetData = dirname(__DIR__) . '/data';

try {
    out('Restoring data files from backup: ' . $backupData);
    copyDirRecursive($backupData, $targetData);
    replaceEnvStorageDriver('json');
    out('Rollback completed. STORAGE_DRIVER=json');
} catch (Throwable $e) {
    fwrite(STDERR, 'Rollback failed: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

exit(0);

