<?php
$appBaseUrl = getenv('APP_BASE_URL');
$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
define('BASE_URL', $isRailway ? '' : rtrim($appBaseUrl !== false ? $appBaseUrl : '/CAPSTONE I', '/'));
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';
$role     = $_POST['role']          ?? '';

// Admins can only create other admin or caregiver accounts here — regular users self-register via register.php.
$allowedRoles = ['admin', 'caregiver'];
$patientIds   = [];
if ($role === 'caregiver') {
    $patientIds = array_values(array_unique(array_filter(array_map('intval', $_POST['patient_ids'] ?? []))));
}

if (!$name || !$email || !$password) {
    $_SESSION['flash_error'] = 'All fields are required.';
} elseif (!in_array($role, $allowedRoles, true)) {
    $_SESSION['flash_error'] = 'Invalid role selected.';
} elseif (!isValidName($name)) {
    $_SESSION['flash_error'] = namePolicyMessage();
} elseif (!isGmailAddress($email)) {
    $_SESSION['flash_error'] = emailPolicyMessage();
} elseif (!isStrongPassword($password)) {
    $_SESSION['flash_error'] = passwordPolicyMessage();
} elseif ($role === 'caregiver' && empty($patientIds)) {
    $_SESSION['flash_error'] = 'Select at least one patient for this caregiver to monitor.';
} else {
    $check = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetchColumn() > 0) {
        $_SESSION['flash_error'] = 'Email already registered.';
    } else {
        $validPatientCount = 0;
        if ($role === 'caregiver') {
            $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
            $vstmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND id IN ($placeholders)");
            $vstmt->execute($patientIds);
            $validPatientCount = (int)$vstmt->fetchColumn();
        }

        if ($role === 'caregiver' && $validPatientCount !== count($patientIds)) {
            $_SESSION['flash_error'] = 'One or more selected patients are invalid.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare(
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?) RETURNING id"
            );
            $stmt->execute([$name, $email, $hash, $role]);
            $newUserId = (int)$stmt->fetchColumn();

            if ($role === 'caregiver') {
                $lstmt = db()->prepare(
                    "INSERT INTO caregiver_links (caregiver_id, patient_id) VALUES (?, ?)"
                );
                foreach ($patientIds as $pid) {
                    $lstmt->execute([$newUserId, $pid]);
                }
            }

            $_SESSION['flash_success'] = ucfirst($role) . ' account created successfully.';
        }
    }
}

header('Location: users.php');
exit;
