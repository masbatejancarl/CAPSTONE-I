<?php
require_once __DIR__ . '/config.php';
// Empty on Railway (site served from the web root). For local XAMPP, set the BASE_URL
// environment variable (e.g. "/CAPSTONE%20I"). No trailing slash.
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));
}

session_start();
require_once __DIR__ . '/storage.php';

function assetUrl(string $path): string {
    $normalizedPath = '/' . ltrim((string)$path, '/');
    return BASE_URL . $normalizedPath;
}

function renderFavicon(): void {
    $faviconUrl = htmlspecialchars(BASE_URL . '/favicon.ico?v=2', ENT_QUOTES);
    echo '<link rel="icon" type="image/x-icon" href="' . $faviconUrl . '">' . PHP_EOL;
    echo '<link rel="shortcut icon" type="image/x-icon" href="' . $faviconUrl . '">' . PHP_EOL;
}

// detected_at/created_at are stored in UTC (Postgres NOW()); convert to Philippine Time for display.
function toManilaTime(string $utcDatetime, string $format): string {
    $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}

function manilaToday(): string {
    return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
}

function manilaTodayUtcRange(): array {
    $manilaToday = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $utc = new DateTimeZone('UTC');

    return [
        $manilaToday->setTimezone($utc)->format('Y-m-d H:i:s'),
        $manilaToday->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
    ];
}

function fetchTodayUserAlerts(PDO $pdo, int $userId): array {
    [$startUtc, $endUtc] = manilaTodayUtcRange();
    $stmt = $pdo->prepare(
        "SELECT unread.unread_count, recent.id, recent.sound_type, recent.alert_type,
                recent.status, recent.detected_at, recent.metadata
         FROM (
             SELECT COUNT(*) AS unread_count
             FROM alerts
             WHERE user_id = ? AND status = 'unread'
               AND detected_at >= ? AND detected_at < ?
         ) AS unread
         LEFT JOIN LATERAL (
             SELECT id, sound_type, alert_type, status, detected_at, metadata
             FROM alerts
             WHERE user_id = ? AND detected_at >= ? AND detected_at < ?
             ORDER BY detected_at DESC
             LIMIT 5
         ) AS recent ON TRUE
         ORDER BY recent.detected_at DESC"
    );
    $stmt->execute([$userId, $startUtc, $endUtc, $userId, $startUtc, $endUtc]);
    $rows = $stmt->fetchAll();

    return [
        'unread_count' => (int)$rows[0]['unread_count'],
        'notifications' => array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['id'] !== null
        )),
    ];
}

// SQL fragment: converts a UTC-naive `timestamp` column to Manila wall-clock time,
// so date/"today" comparisons match what Philippine users actually see on screen.
const MANILA_SQL = "AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Manila'";

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/auth/login.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => $_SESSION['name'] ?? '',
        'role'       => $_SESSION['role'] ?? '',
        'avatar_url' => $_SESSION['avatar_url'] ?? null,
    ];
}

// Renders a user's avatar as their uploaded photo, or a letter-initial
// fallback when no photo has been set.
function renderAvatar(string $name, ?string $avatarUrl, string $class = ''): string {
    $classes = trim('sb-avatar ' . $class . ($avatarUrl ? ' sb-avatar-photo' : ''));
    if ($avatarUrl) {
        return sprintf(
            '<span class="%s" style="background-image:url(\'%s\')"></span>',
            htmlspecialchars($classes),
            htmlspecialchars($avatarUrl, ENT_QUOTES)
        );
    }
    $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
    return sprintf('<span class="%s">%s</span>', htmlspecialchars($classes), htmlspecialchars($initial));
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
const EMAIL_PATTERN_ATTR  = '[A-Za-z0-9]([A-Za-z0-9._\\-]*[A-Za-z0-9])?@gmail\.com';
const EMAIL_PATTERN_TITLE = 'Must be a Gmail address, e.g. juan.delacruz@gmail.com';

function namePolicyMessage(): string {
    return 'Full name must contain letters and spaces only.';
}

function emailPolicyMessage(): string {
    return 'Email must be a Gmail address, e.g. juan.delacruz@gmail.com.';
}

const AUDIT_LOG_ACTIONS = [
    'login',
    'logout',
    'login_failed',
    'password_reset',
];

// Records supported authentication events in the audit_logs table.
function logAuditEvent(?int $userId, string $name, string $email, string $role, string $action): void {
    if (!in_array($action, AUDIT_LOG_ACTIONS, true)) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = db()->prepare(
        "INSERT INTO audit_logs (user_id, user_name, user_email, role, action, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $name, $email, $role, $action, $ip]);
}

// ::1 / 127.0.0.1 mean the request came from the same machine the server runs
// on (e.g. testing locally via XAMPP) — show that plainly instead of a raw IP.
function formatIpAddress(?string $ip): string {
    if (!$ip) return 'Unknown IP';
    return in_array($ip, ['::1', '127.0.0.1'], true) ? 'This Device' : $ip;
}

// Reads the left/right mic direction out of an alert row's `metadata` column.
// Different producers store it in different formats, including JSON, raw text,
// or strings like "Direction: left | Amplitude: 3488". We only care about the direction.
function alertDirection(?string $metadataValue): ?string {
    if (!$metadataValue) return null;

    $raw = trim((string)$metadataValue);
    if ($raw === '') return null;

    $meta = json_decode($raw, true);
    if (is_array($meta) && isset($meta['direction'])) {
        $raw = (string)$meta['direction'];
    }

    if (preg_match('/\b(direction|dir)\s*[:=]\s*(left|right|both|l|r)\b/i', $raw, $matches)) {
        $raw = $matches[2];
    } elseif (preg_match('/\b(left|right|both|l|r)\b/i', $raw, $matches)) {
        $raw = $matches[1];
    }

    $dir = strtolower((string)$raw);
    // The ESP32 firmware sometimes sends the shorthand 'l'/'r' instead of the full word.
    $dir = match ($dir) { 'l' => 'left', 'r' => 'right', default => $dir };
    return in_array($dir, ['left', 'right'], true) ? $dir : null;
}

function alertType(?string $type): string {
    $type = strtolower(trim((string)$type));
    return in_array($type, ['critical', 'high', 'medium', 'low'], true) ? $type : 'medium';
}

function alertTypeLabel(?string $type): string {
    return ucfirst(alertType($type));
}

function soundTypeCatalog(): array {
    return [
        'fire_alarm' => ['label' => 'Fire Alarm Bell'],
        'emergency_siren' => ['label' => 'Emergency Siren'],
        'fallen_object' => ['label' => 'Fallen Object'],
        'glass_breaking' => ['label' => 'Glass Breaking'],
        'baby_crying' => ['label' => 'Baby Crying'],
        'vehicle_horn' => ['label' => 'Vehicle Horn'],
        'dog_barking' => ['label' => 'Dog Barking'],
        'doorbell' => ['label' => 'Doorbell'],
        'alarm_clock' => ['label' => 'Alarm Clock'],
        'speech' => ['label' => 'Speech'],
        'phone_notification' => ['label' => 'Phone Notification'],
        'door_knock' => ['label' => 'Door Knock'],
    ];
}

function soundTypeKey(?string $type): ?string {
    if ($type === null || trim($type) === '') {
        return null;
    }

    $value = trim($type);
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $value = (string)($decoded['sound_type'] ?? $decoded['type'] ?? $value);
    }

    $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($value)) ?? $value);
    return trim($key, '_') ?: null;
}

function soundTypeLabel(?string $type): string {
    $key = soundTypeKey($type);
    if ($key === null) {
        return 'Detected Sound';
    }

    $catalog = soundTypeCatalog();
    if (isset($catalog[$key])) {
        return $catalog[$key]['label'];
    }

    $words = explode('_', $key);
    $label = implode(' ', array_map(fn ($word) => ucfirst($word), $words));

    return $label !== '' ? $label : 'Detected Sound';
}

function alertSeverity(?string $storedSeverity): string {
    return alertType($storedSeverity);
}

function alertSeveritySql(string $alias = ''): string {
    $prefix = $alias !== '' ? $alias . '.' : '';
    return "LOWER(COALESCE({$prefix}alert_type, 'medium'))";
}

function alertTypeOptions(): array {
    return ['critical', 'high', 'medium', 'low'];
}

function soundTypeOptionGroups(array $soundTypes): array {
    $catalog = soundTypeCatalog();
    $availableGroups = [];
    foreach ($soundTypes as $soundType) {
        $value = (string)$soundType;
        $key = soundTypeKey($value);
        if ($key === null || !isset($catalog[$key])) {
            continue;
        }
        $availableGroups[$key][] = $value;
    }

    $groups = [];
    foreach ($catalog as $key => $soundType) {
        $groups[$key] = [
            'label' => $soundType['label'],
            'values' => $availableGroups[$key] ?? [$key],
        ];
    }

    return $groups;
}

function directionIcon(?string $direction): string {
    return match ($direction) {
        'left'  => 'bi-arrow-left-circle-fill',
        'right' => 'bi-arrow-right-circle-fill',
        default => 'bi-bell-fill',
    };
}

function directionLabel(?string $direction): string {
    return match ($direction) {
        'left'  => 'Sound Detected Left',
        'right' => 'Sound Detected Right',
        default => 'Sound Detected',
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