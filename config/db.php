<?php
// ============================================================
//  Supabase (PostgreSQL) connection via PDO
//  Credentials live in config/db.local.php (gitignored, not
//  committed). See config/db.example.php for the template.
// ============================================================

$localConfig = __DIR__ . '/db.local.php';
if (!file_exists($localConfig)) {
    http_response_code(500);
    die('Missing config/db.local.php — copy config/db.example.php and fill in your credentials.');
}
require_once $localConfig;

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'pgsql:host=' . DB_HOST
             . ';port='   . DB_PORT
             . ';dbname=' . DB_NAME
             . ';sslmode=require';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    return $pdo;
}
