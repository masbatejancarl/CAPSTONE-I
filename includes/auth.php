<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/login.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

function passwordMatches(string $password, string $hash): bool {
    if (password_verify($password, $hash)) {
        return true;
    }

    // Support common mobile hash formats if the app already hashed the password.
    if (preg_match('/^[0-9a-f]{32}$/i', $hash) && hash_equals($hash, md5($password))) {
        return true;
    }

    if (preg_match('/^[0-9a-f]{64}$/i', $hash) && hash_equals($hash, hash('sha256', $password))) {
        return true;
    }

    return false;
}

function isProbablyHashedPassword(string $value): bool {
    return preg_match('/^(\$2[ayb]\$|\$argon2|[0-9a-f]{32}|[0-9a-f]{64})/', $value) === 1;
}

// Password policy: 10+ chars, at least one lowercase, one uppercase, one special character.
function isStrongPassword(string $password): bool {
    return strlen($password) >= 10
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function passwordPolicyMessage(): string {
    return 'Password must be at least 10 characters and include an uppercase letter, a lowercase letter, and a special character.';
}

function renderPwChecklist(string $id): void {
    ?>
    <div class="sb-pw-checklist" id="<?= htmlspecialchars($id) ?>">
        <span class="sb-pw-req" data-req="length"><i class="bi bi-circle"></i> 10+ characters</span>
        <span class="sb-pw-req" data-req="upper"><i class="bi bi-circle"></i> Uppercase letter</span>
        <span class="sb-pw-req" data-req="lower"><i class="bi bi-circle"></i> Lowercase letter</span>
        <span class="sb-pw-req" data-req="special"><i class="bi bi-circle"></i> Special character</span>
    </div>
    <?php
}

// Full name: letters and spaces only.
function isValidName(string $name): bool {
    return preg_match('/^[A-Za-z ]+$/', $name) === 1;
}

// Email: letters, numbers, dots, underscores, and hyphens before the @, and must be a Gmail address.
function isGmailAddress(string $email): bool {
    return preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?@gmail\.com$/i', $email) === 1;
}

const NAME_PATTERN_ATTR  = '[A-Za-z ]+';
const NAME_PATTERN_TITLE = 'Letters and spaces only';
const EMAIL_PATTERN_ATTR  = '[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?@gmail\.com';
const EMAIL_PATTERN_TITLE = 'Must be a Gmail address, e.g. juan.delacruz@gmail.com';

function namePolicyMessage(): string {
    return 'Full name must contain letters and spaces only.';
}

function emailPolicyMessage(): string {
    return 'Email must be a Gmail address, e.g. juan.delacruz@gmail.com.';
}

// Records a login / logout / login_failed event to the audit_logs table.
function logAuditEvent(?int $userId, string $name, string $email, string $role, string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = db()->prepare(
        "INSERT INTO audit_logs (user_id, user_name, user_email, role, action, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $name, $email, $role, $action, $ip]);
}

// Reads the left/right/both mic direction out of an alert row's `metadata` column.
// Accepts either a JSON object like {"direction":"left"} or a bare "left"/"right"/"both" string,
// since different producers (ESP32 firmware, the React app) have written it either way.
function alertDirection(?string $metadataValue): ?string {
    if (!$metadataValue) return null;
    $meta = json_decode($metadataValue, true);
    $dir  = is_array($meta) ? ($meta['direction'] ?? null) : trim($metadataValue);
    return in_array($dir, ['left', 'right', 'both'], true) ? $dir : null;
}

function directionIcon(?string $direction): string {
    return match ($direction) {
        'left'  => 'bi-arrow-left-circle-fill',
        'right' => 'bi-arrow-right-circle-fill',
        'both'  => 'bi-arrows-angle-expand',
        default => '',
    };
}

function redirectByRole() {
    switch ($_SESSION['role']) {
        case 'admin':     header('Location: ' . BASE_URL . '/admin/dashboard.php'); break;
        case 'caregiver': header('Location: ' . BASE_URL . '/caregiver/dashboard.php'); break;
        default:          header('Location: ' . BASE_URL . '/user/dashboard.php'); break;
    }
    exit;
}
