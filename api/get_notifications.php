<?php
// Polled by the notification bell (see includes/topnav_user.php) to surface
// new alerts without a full page reload.
header('Content-Type: application/json');
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$uid = (int)currentUser()['id'];

$cstmt = db()->prepare("SELECT COUNT(*) FROM alerts WHERE user_id=? AND status='unread' AND detected_at::date = CURRENT_DATE");
$cstmt->execute([$uid]);
$unread = (int)$cstmt->fetchColumn();

$nstmt = db()->prepare("SELECT * FROM alerts WHERE user_id=? AND detected_at::date = CURRENT_DATE ORDER BY detected_at DESC LIMIT 5");
$nstmt->execute([$uid]);
$rows = $nstmt->fetchAll();

$notifIcons = [
    'Doorbell'        => 'bi-bell-fill',
    'Fire Alarm'      => 'bi-fire',
    'Vehicle Horn'    => 'bi-car-front-fill',
    'Emergency Siren' => 'bi-exclamation-triangle-fill',
    'Loud Knock'      => 'bi-hand-index-thumb-fill',
];

$notifications = array_map(function ($n) use ($notifIcons) {
    return [
        'id'          => (int)$n['id'],
        'sound_type'  => $n['sound_type'] ?: 'Sound Detected',
        'icon'        => $notifIcons[$n['sound_type']] ?? 'bi-volume-up-fill',
        'location'    => $n['location'] ?: null,
        'decibel'     => $n['decibel_level'] ? number_format((float)$n['decibel_level'], 1) . ' dB' : '—',
        'direction'   => alertDirection($n['metadata'] ?? null),
        'status'      => $n['status'],
        'detected_at' => date('M d, Y H:i', strtotime($n['detected_at'])),
    ];
}, $rows);

echo json_encode([
    'unread_count'  => $unread,
    'notifications' => $notifications,
]);
