<?php
// ============================================================
//  Supabase (PostgreSQL) connection via PDO.
//  Local development can use config/db.local.php; hosted deployments can use
//  environment variables instead.
// ============================================================

$localConfig = __DIR__ . '/db.local.php';
$readEnv = static function (string $name): ?string {
    $value = getenv($name);
    if ($value === false) {
        // Some SAPIs expose environment variables only through $_SERVER / $_ENV.
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? false;
    }
    return $value === false ? null : (string)$value;
};

if (file_exists($localConfig)) {
    require_once $localConfig;
}

$configValues = [
    'DB_HOST' => $readEnv('DB_HOST'),
    'DB_PORT' => $readEnv('DB_PORT'),
    'DB_NAME' => $readEnv('DB_NAME'),
    'DB_USER' => $readEnv('DB_USER'),
    'DB_PASS' => $readEnv('DB_PASS') ?? $readEnv('DB_PASSWORD'),
    'ESP32_API_KEY' => $readEnv('ESP32_API_KEY') ?? '',
    'SUPABASE_URL' => $readEnv('SUPABASE_URL') ?? '',
    'SUPABASE_SERVICE_ROLE_KEY' => $readEnv('SUPABASE_SERVICE_ROLE_KEY') ?? '',
    'SUPABASE_AVATAR_BUCKET' => $readEnv('SUPABASE_AVATAR_BUCKET') ?? 'avatars',
];

foreach ($configValues as $name => $value) {
    if (!defined($name) && $value !== null) {
        define($name, $value);
    }
}

$requiredConstants = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$missingConstants = array_filter(
    $requiredConstants,
    static fn (string $name): bool => !defined($name) || constant($name) === ''
);

if ($missingConstants) {
    http_response_code(500);
    error_log('Database configuration is missing: ' . implode(', ', $missingConstants));
    die('Database configuration is missing: ' . implode(', ', $missingConstants)
        . '. Set DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS (or DB_PASSWORD) in the environment or configure config/db.local.php.');
}

foreach (['ESP32_API_KEY', 'SUPABASE_URL', 'SUPABASE_SERVICE_ROLE_KEY', 'SUPABASE_AVATAR_BUCKET'] as $name) {
    if (!defined($name)) {
        define($name, $configValues[$name]);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        if (!extension_loaded('pdo_pgsql')) {
            http_response_code(500);
            error_log('PHP extension pdo_pgsql is not installed.');
            die(json_encode(['error' => 'Database driver (pdo_pgsql) is not installed on the server.']));
        }

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
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed. Check the server logs.']));
        }
    }

    return $pdo;
}