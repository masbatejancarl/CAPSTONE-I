<?php
$scriptDirectory = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$basePath = $scriptDirectory === '' || $scriptDirectory === '.'
    ? ''
    : '/' . implode('/', array_map('rawurlencode', explode('/', $scriptDirectory)));
define('BASE_URL', $basePath);

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
