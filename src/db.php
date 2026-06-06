<?php
declare(strict_types=1);

/**
 * Tiny .env loader + PDO connection to PostgreSQL.
 * Plain and simple — no external dependencies.
 */

function load_env(string $path): array
{
    $vars = [];
    if (!is_file($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $vars[trim($key)] = trim($value);
    }
    return $vars;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $env = load_env(__DIR__ . '/../.env');
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $env['DB_HOST'] ?? 'localhost',
        $env['DB_PORT'] ?? '5432',
        $env['DB_NAME'] ?? 'white_label'
    );

    $pdo = new PDO($dsn, $env['DB_USER'] ?? 'postgres', $env['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
