<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    $user = currentUser();
    $stmt = db()->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    // Account may have been deleted by an admin while this session was still active.
    $userId = $row ? (int)$row['id'] : null;
    $email  = $row['email'] ?? '';
    logAuditEvent($userId, $user['name'], $email, $user['role'], 'logout');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
