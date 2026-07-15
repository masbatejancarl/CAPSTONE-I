<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

header('Location: auth/login.php');
exit;
