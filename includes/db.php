<?php
declare(strict_types=1);

/** Read DB settings from .env (never committed to Git). */
function env_config(): array
{
    $file = __DIR__ . '/../.env';
    if (!is_readable($file)) {
        return [];
    }
    return parse_ini_file($file, false, INI_SCANNER_RAW) ?: [];
}

/** Task 2(a): one PDO connection, with connection errors handled. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = env_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $c['DB_HOST'] ?? '127.0.0.1',
        $c['DB_PORT'] ?? '3306',
        $c['DB_NAME'] ?? 'nrb_db'
    );
    try {
        $pdo = new PDO($dsn, $c['DB_USER'] ?? 'root', $c['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('<h2>Database connection failed</h2>'
           . '<p>Check that MySQL/MariaDB is running, the database is imported and '
           . 'the credentials in <code>.env</code> are correct.</p>');
    }
    return $pdo;
}
