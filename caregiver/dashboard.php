<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('caregiver');

$user = currentUser();
$cid  = (int)$user['id'];

$pstmt = db()->prepare(
    "SELECT u.id, u.name FROM caregiver_links cl
     JOIN users u ON u.id = cl.patient_id
     WHERE cl.caregiver_id = ? ORDER BY u.name"
);
$pstmt->execute([$cid]);
$patients = $pstmt->fetchAll();
$patientIds = array_column($patients, 'id');

$totalAlerts  = 0;
$unreadAlerts = 0;
$todayAlerts  = 0;

$directionInfo = [
    'left'    => ['Sound Detected Left',              'var(--info)'],
    'right'   => ['Sound Detected Right',             'var(--warning)'],
    'unknown' => ['Sound Detected — Unknown Direction', 'var(--muted)'],
];
$directionCounts = ['left' => 0, 'right' => 0, 'unknown' => 0];

if ($patientIds) {
    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));

    $stmt = db()->prepare("SELECT COUNT(*) FROM alerts WHERE user_id IN ($placeholders)");
    $stmt->execute($patientIds);
    $totalAlerts = (int)$stmt->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) FROM alerts WHERE user_id IN ($placeholders) AND status='unread'");
    $stmt->execute($patientIds);
    $unreadAlerts = (int)$stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM alerts WHERE user_id IN ($placeholders)
         AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date"
    );
    $stmt->execute($patientIds);
    $todayAlerts = (int)$stmt->fetchColumn();

    $stmt = db()->prepare("SELECT metadata FROM alerts WHERE user_id IN ($placeholders)");
    $stmt->execute($patientIds);
    foreach ($stmt->fetchAll() as $m) {
        $directionCounts[alertDirection($m['metadata'] ?? null) ?? 'unknown']++;
    }
}
$directionCounts = array_filter($directionCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Caregiver Dashboard — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="sb-layout">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="sb-main">
<div class="sb-content">

    <div class="sb-page-header">
        <div>
            <div class="sb-page-title"><i class="bi bi-grid-1x2-fill"></i> Caregiver Dashboard</div>
            <div class="sb-page-sub">Welcome back, <?= htmlspecialchars($user['name']) ?></div>
        </div>
        <span class="sb-clock" id="sb-clock"></span>
    </div>

    <?php if (empty($patients)): ?>
    <div class="sb-card">
        <div class="sb-empty">
            <i class="bi bi-person-heart"></i>
            <p>No patients have been linked to your caregiver account yet. Please contact an administrator.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- System Pulse -->
    <div class="sb-pulse mb-4">
        <div class="sb-pulse-main">
            <div class="sb-pulse-total">
                <div class="sb-pulse-number"><?= $totalAlerts ?></div>
                <div class="sb-pulse-label">Total Alerts Detected</div>
            </div>

            <?php if (!empty($directionCounts)): ?>
            <div class="sb-pulse-bar">
                <?php foreach ($directionCounts as $dir => $n):
                    [$label, $color] = $directionInfo[$dir];
                    $pct = $totalAlerts > 0 ? round(($n / $totalAlerts) * 100, 1) : 0;
                ?>
                <div class="sb-pulse-seg" style="width:<?= $pct ?>%;background:<?= $color ?>" title="<?= htmlspecialchars($label) ?>: <?= $n ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="sb-pulse-legend">
                <?php foreach ($directionCounts as $dir => $n):
                    [$label, $color] = $directionInfo[$dir];
                ?>
                <span class="sb-pulse-legend-item">
                    <span class="sb-pulse-dot" style="background:<?= $color ?>"></span>
                    <?= htmlspecialchars($label) ?> · <?= $n ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="sb-pulse-side">
            <div class="sb-pulse-stat">
                <i class="bi bi-person-heart"></i>
                <div>
                    <div class="sb-pulse-stat-num"><?= count($patients) ?></div>
                    <div class="sb-pulse-stat-label">Patients</div>
                </div>
            </div>
            <div class="sb-pulse-stat">
                <i class="bi bi-exclamation-circle-fill" style="color:var(--danger)"></i>
                <div>
                    <div class="sb-pulse-stat-num"><?= $unreadAlerts ?></div>
                    <div class="sb-pulse-stat-label">Unread</div>
                </div>
            </div>
            <div class="sb-pulse-stat">
                <i class="bi bi-calendar-day-fill" style="color:var(--warning)"></i>
                <div>
                    <div class="sb-pulse-stat-num"><?= $todayAlerts ?></div>
                    <div class="sb-pulse-stat-label">Today</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Patients -->
    <div class="sb-card mb-4">
        <div class="sb-section-header">
            <div class="sb-section-title">
                <i class="bi bi-person-heart"></i> Patients You Monitor
            </div>
        </div>
        <div class="sb-user-grid">
            <?php foreach ($patients as $p): ?>
            <div class="sb-user-card">
                <div class="sb-user-card-top">
                    <div class="sb-avatar sb-avatar-md"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                </div>
                <div class="sb-user-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <a href="alerts.php?patient_id=<?= $p['id'] ?>" class="sb-user-card-stat">
                    <i class="bi bi-bell-fill"></i> View alert history
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
