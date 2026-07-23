<?php

declare(strict_types=1);

function envValue(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }

                    [$k, $v] = array_pad(explode('=', $trimmed, 2), 2, '');
                    $env[trim($k)] = trim($v);
                }
            }
        }
    }

    return $env[$key] ?? getenv($key) ?: $default;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envValue('DB_HOST', '127.0.0.1');
    $port = envValue('DB_PORT', '3306');
    $name = envValue('DB_NAME', 'berza');
    $user = envValue('DB_USER', 'root');
    $pass = envValue('DB_PASS', '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
