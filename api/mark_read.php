<?php
header('Content-Type: application/json');
define('BASE_URL', '/..');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);
$uid  = (int)currentUser()['id'];
$role = currentUser()['role'];

if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

// Admin can mark any; user can only mark their own
if ($role === 'admin') {
    db()->exec("UPDATE alerts SET status='read' WHERE id=$id");
} else {
    db()->exec("UPDATE alerts SET status='read' WHERE id=$id AND user_id=$uid");
}

echo json_encode(['success' => true]);
