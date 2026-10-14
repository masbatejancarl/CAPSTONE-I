<?php
$appBaseUrl = getenv('APP_BASE_URL');
$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
define('BASE_URL', rtrim($appBaseUrl !== false ? $appBaseUrl : ($isRailway ? '' : '/CAPSTONE I'), '/'));
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

header('Location: auth/login.php');
exit;
