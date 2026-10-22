<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->query(
        "SELECT d.id, d.user_id, d.device_name, d.mac_address, d.ip_address,
                d.is_on, d.last_seen, d.sensitivity, d.vibration_intensity,
                d.sound_threshold, u.name AS user_name, u.email, u.role,
                location.details AS location_details,
                location.created_at AS location_updated_at
         FROM user_devices d
         JOIN users u ON u.id = d.user_id
         LEFT JOIN LATERAL (
             SELECT al.details, al.created_at
             FROM audit_logs al
             WHERE al.user_id = d.user_id
               AND al.action = 'location_update'
             ORDER BY al.created_at DESC
             LIMIT 1
         ) location ON TRUE
         ORDER BY u.name, d.device_name"
    );
    $devices = $stmt->fetchAll();

    $lockStmt = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(?))');
    $stateStmt = $pdo->prepare(
        "SELECT CASE
                    WHEN is_on IS TRUE AND last_seen >= NOW() - INTERVAL '1 minute' THEN 1
                    ELSE 0
                END AS online
         FROM user_devices WHERE id = ?"
    );
    $previousEventStmt = $pdo->prepare(
        "SELECT action
         FROM audit_logs
         WHERE user_id = ?
           AND action IN ('DEVICE_CONNECTED', 'DEVICE_DISCONNECTED')
           AND position(? in details) > 0
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $insertEventStmt = $pdo->prepare(
        "INSERT INTO audit_logs
            (user_id, user_name, user_email, role, action, ip_address, details)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($devices as &$device) {
        $macAddress = (string)($device['mac_address'] ?? '');
        $lockStmt->execute([$macAddress !== '' ? $macAddress : 'device-' . $device['id']]);

        $stateStmt->execute([$device['id']]);
        $device['online'] = (int)$stateStmt->fetchColumn() === 1;

        if ($macAddress !== '') {
            $previousEventStmt->execute([
                $device['user_id'],
                '[MAC: ' . $macAddress . ']',
            ]);
            $previousAction = $previousEventStmt->fetchColumn();
            $nextAction = $device['online'] ? 'DEVICE_CONNECTED' : 'DEVICE_DISCONNECTED';
            $isInitialObservation = $previousAction === false && $device['last_seen'] !== null;

            if ($isInitialObservation || ($previousAction !== false && $previousAction !== $nextAction)) {
                $details = sprintf(
                    '%s device %s [MAC: %s]',
                    $isInitialObservation
                        ? 'Initial status observed as ' . ($device['online'] ? 'online' : 'offline') . ' for'
                        : ($device['online'] ? 'Connected to' : 'Disconnected from'),
                    $device['device_name'] ?: 'SonoBand',
                    $macAddress
                );
                $insertEventStmt->execute([
                    $device['user_id'],
                    $device['user_name'],
                    $device['email'],
                    $device['role'],
                    $nextAction,
                    $device['ip_address'],
                    $details,
                ]);
            }
        }

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
