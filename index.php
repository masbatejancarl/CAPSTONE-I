<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

header('Location: ' . BASE_URL . '/auth/login.php');
exit;