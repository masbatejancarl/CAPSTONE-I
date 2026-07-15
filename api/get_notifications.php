<?php
// Polled by the notification bell (see includes/topnav_user.php) to surface
// new alerts without a full page reload.
header('Content-Type: application/json');
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$uid = (int)currentUser()['id'];

$cstmt = db()->prepare(
    "SELECT COUNT(*) FROM alerts WHERE user_id=? AND status='unread'
     AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date"
);
$cstmt->execute([$uid]);
$unread = (int)$cstmt->fetchColumn();

$nstmt = db()->prepare(
    "SELECT * FROM alerts WHERE user_id=?
     AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date
     ORDER BY detected_at DESC LIMIT 5"
);
$nstmt->execute([$uid]);
$rows = $nstmt->fetchAll();

$notifications = array_map(function ($n) {
    return [
        'id'          => (int)$n['id'],
        'decibel'     => $n['decibel_level'] ? number_format((float)$n['decibel_level'], 1) . ' dB' : '—',
        'direction'   => alertDirection($n['metadata'] ?? null),
        'status'      => $n['status'],
        'detected_at' => toManilaTime($n['detected_at'], 'M d, Y H:i'),
    ];
}, $rows);

echo json_encode([
    'unread_count'  => $unread,
    'notifications' => $notifications,
]);
