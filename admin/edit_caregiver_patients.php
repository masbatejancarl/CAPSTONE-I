<?php
$appBaseUrl = getenv('APP_BASE_URL');
$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
define('BASE_URL', rtrim($appBaseUrl !== false ? $appBaseUrl : ($isRailway ? '' : '/CAPSTONE I'), '/'));
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: users.php');
    exit;
}

$caregiverId = (int)($_POST['caregiver_id'] ?? 0);
$patientIds  = array_values(array_unique(array_filter(array_map('intval', $_POST['patient_ids'] ?? []))));

$rstmt = db()->prepare("SELECT role FROM users WHERE id = ?");
$rstmt->execute([$caregiverId]);
$targetRole = $rstmt->fetchColumn();

if ($targetRole !== 'caregiver') {
    $_SESSION['flash_error'] = 'Invalid caregiver account.';
    header('Location: users.php');
    exit;
}

$validPatientCount = 0;
if ($patientIds) {
    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
    $vstmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND id IN ($placeholders)");
    $vstmt->execute($patientIds);
    $validPatientCount = (int)$vstmt->fetchColumn();
}

if ($validPatientCount !== count($patientIds)) {
    $_SESSION['flash_error'] = 'One or more selected patients are invalid.';
    header('Location: users.php');
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $dstmt = $pdo->prepare("DELETE FROM caregiver_links WHERE caregiver_id = ?");
    $dstmt->execute([$caregiverId]);

    if ($patientIds) {
        $istmt = $pdo->prepare("INSERT INTO caregiver_links (caregiver_id, patient_id) VALUES (?, ?)");
        foreach ($patientIds as $pid) {
            $istmt->execute([$caregiverId, $pid]);
        }
    }

    $pdo->commit();
    $_SESSION['flash_success'] = 'Linked patients updated successfully.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Failed to update linked patients.';
}

header('Location: users.php');
exit;
