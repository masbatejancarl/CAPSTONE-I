<?php
header('Content-Type: application/json');
$appBaseUrl = getenv('APP_BASE_URL');
$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
define('BASE_URL', rtrim($appBaseUrl !== false ? $appBaseUrl : ($isRailway ? '' : '/CAPSTONE I'), '/'));
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);
$uid  = (int)currentUser()['id'];

if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

// Only the alert's own user may mark it read — no role gets to mark others' alerts.
$stmt = db()->prepare("UPDATE alerts SET status='read' WHERE id=? AND user_id=?");
$stmt->execute([$id, $uid]);

echo json_encode(['success' => true]);
