<?php
/*
 * SonoBand ESP32 Alert Receiver
 * ESP32 sends a POST request to this endpoint when a sound is detected.
 *
 * Example POST body (JSON):
 * {
 *   "user_id": 2,
 *   "sound_type": "Fire Alarm",
 *   "decibel_level": 95.3,
 *   "location": "indoor"
 * }
 *
 * Arduino/ESP32 example:
 *   HTTPClient http;
 *   http.begin("http://yourserver/api/receive_alert.php");
 *   http.addHeader("Content-Type", "application/json");
 *   http.addHeader("X-API-Key", "sonoband_esp32_key");
 *   http.POST("{\"user_id\":2,\"sound_type\":\"Fire Alarm\",\"decibel_level\":95.3,\"location\":\"indoor\"}");
 */

header('Content-Type: application/json');

define('BASE_URL', '/..');
require_once __DIR__ . '/../config/db.php';

// Simple API key check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== ESP32_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$userId       = (int)($data['user_id']       ?? 0);
$soundType    = trim($data['sound_type']     ?? '');
$decibelLevel = (float)($data['decibel_level'] ?? 0);
$location     = in_array($data['location'] ?? '', ['indoor','outdoor']) ? $data['location'] : 'indoor';

$validSounds = ['Doorbell','Fire Alarm','Vehicle Horn','Emergency Siren','Loud Knock'];
if (!$userId || !in_array($soundType, $validSounds)) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid fields']);
    exit;
}

// Verify user exists
$check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'user'");
$check->execute([$userId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$stmt = db()->prepare(
    "INSERT INTO alerts (user_id, sound_type, decibel_level, location, status) VALUES (?, ?, ?, ?, 'unread')"
);

if ($stmt->execute([$userId, $soundType, $decibelLevel, $location])) {
    echo json_encode([
        'success'  => true,
        'alert_id' => (int)db()->lastInsertId(),
        'message'  => "Alert '$soundType' recorded for user $userId"
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save alert']);
}
