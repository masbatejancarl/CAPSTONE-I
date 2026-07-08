<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

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

session_destroy();
header('Location: login.php');
exit;
