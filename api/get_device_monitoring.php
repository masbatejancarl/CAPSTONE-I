<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = db();

try {
    $pdo->beginTransaction();
    $stmt = $pdo->query(
        "SELECT d.id, d.user_id, d.device_name, d.mac_address, d.ip_address,
                d.is_on, d.last_seen, d.sensitivity, d.vibration_intensity,
                u.name AS user_name, u.email, u.role,
                location.details AS location_details,
                location.created_at AS location_updated_at
         FROM user_devices d
         JOIN users u ON u.id::text = d.user_id
         LEFT JOIN LATERAL (
             SELECT al.details, al.created_at
             FROM audit_logs al
             WHERE al.user_id::text = d.user_id
               AND al.action = 'location_update'
             ORDER BY al.created_at DESC
             LIMIT 1
         ) location ON TRUE
         ORDER BY u.name, d.device_name"
    );
    $devices = $stmt->fetchAll();

    $stateStmt = $pdo->prepare(
        "SELECT CASE
                    WHEN is_on IS TRUE AND last_seen >= NOW() - INTERVAL '1 minute' THEN 1
                    ELSE 0
                END AS online
         FROM user_devices WHERE id = ?"
    );

    foreach ($devices as &$device) {
        $stateStmt->execute([$device['id']]);
        $device['online'] = (int)$stateStmt->fetchColumn() === 1;

        $device['location_address'] = '';
        if (preg_match('/Updated location:\s*(.+)$/i', (string)($device['location_details'] ?? ''), $matches)) {
            $device['location_address'] = trim($matches[1]);
        }
        unset($device['location_details']);
        $device['last_seen'] = $device['last_seen']
            ? toManilaTime($device['last_seen'], 'M d, Y h:i:s A') . ' PHT'
            : null;
        $device['location_updated_at'] = $device['location_updated_at']
            ? toManilaTime($device['location_updated_at'], 'M d, Y h:i:s A') . ' PHT'
            : null;
    }
    unset($device);

    $pdo->commit();
    echo json_encode([
        'devices' => $devices,
        'online_count' => count(array_filter($devices, static fn (array $device): bool => $device['online'])),
        'updated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('M d, Y h:i:s A') . ' PHT',
    ], JSON_THROW_ON_ERROR);
} catch (PDOException|JsonException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Device monitoring query failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not retrieve device monitoring data. Check the server logs.']);
}
