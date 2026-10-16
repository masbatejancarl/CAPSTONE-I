<?php
// Polled by the notification bell (see includes/topnav_user.php) to surface
// new alerts without a full page reload.
header('Content-Type: application/json');
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$uid = (int)currentUser()['id'];

$todayAlerts = fetchTodayUserAlerts(db(), $uid);

$notifications = array_map(static function ($n) {
    return [
        'id'          => (int)$n['id'],
        'direction'   => alertDirection($n['metadata'] ?? null),
        'sound_type'  => soundTypeLabel($n['sound_type'] ?? null),
        'alert_type'  => alertSeverity($n['alert_type'] ?? null),
        'status'      => $n['status'],
        'detected_at' => toManilaTime($n['detected_at'], 'M d, Y H:i'),
    ];
}, $todayAlerts['notifications']);

echo json_encode([
    'unread_count'  => $todayAlerts['unread_count'],
    'notifications' => $notifications,
]);
