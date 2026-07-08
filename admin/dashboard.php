<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$user = currentUser();

$totalUsers   = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAlerts  = (int)db()->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
$unreadAlerts = (int)db()->query("SELECT COUNT(*) FROM alerts WHERE status='unread'")->fetchColumn();
$todayAlerts  = (int)db()->query("SELECT COUNT(*) FROM alerts WHERE detected_at::date=CURRENT_DATE")->fetchColumn();

$recent = db()->query(
    "SELECT a.*, u.name AS user_name FROM alerts a
     JOIN users u ON a.user_id = u.id
     ORDER BY a.detected_at DESC LIMIT 8"
)->fetchAll();

$soundIcons = [
    'Doorbell'        => ['bi-bell-fill',                'sound-doorbell'],
    'Fire Alarm'      => ['bi-fire',                     'sound-alarm'],
    'Vehicle Horn'    => ['bi-car-front-fill',            'sound-vehicle'],
    'Emergency Siren' => ['bi-exclamation-triangle-fill', 'sound-siren'],
    'Loud Knock'      => ['bi-hand-index-thumb-fill',     'sound-knock'],
];

$typeCounts = db()->query(
    "SELECT sound_type, COUNT(*) AS n FROM alerts GROUP BY sound_type ORDER BY n DESC"
)->fetchAll();
$typeColors = [
    'Doorbell'        => 'var(--info)',
    'Fire Alarm'      => 'var(--danger)',
    'Vehicle Horn'    => 'var(--warning)',
    'Emergency Siren' => 'var(--danger)',
    'Loud Knock'      => 'var(--info)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard — SonoBand</title>
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
            <div class="sb-page-title"><i class="bi bi-grid-1x2-fill"></i> System Overview</div>
            <div class="sb-page-sub">SonoBand Administration</div>
        </div>
        <span class="sb-clock" id="sb-clock"></span>
    </div>

    <!-- System Pulse -->
    <div class="sb-pulse mb-4">
        <div class="sb-pulse-main">
            <div class="sb-pulse-total">
                <div class="sb-pulse-number"><?= $totalAlerts ?></div>
                <div class="sb-pulse-label">Total Alerts Detected</div>
            </div>

            <?php if (!empty($typeCounts)): ?>
            <div class="sb-pulse-bar">
                <?php foreach ($typeCounts as $tc):
                    $pct = $totalAlerts > 0 ? round(($tc['n'] / $totalAlerts) * 100, 1) : 0;
                    $color = $typeColors[$tc['sound_type']] ?? 'var(--muted)';
                ?>
                <div class="sb-pulse-seg" style="width:<?= $pct ?>%;background:<?= $color ?>" title="<?= htmlspecialchars($tc['sound_type']) ?>: <?= $tc['n'] ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="sb-pulse-legend">
                <?php foreach ($typeCounts as $tc):
                    $color = $typeColors[$tc['sound_type']] ?? 'var(--muted)';
                ?>
                <span class="sb-pulse-legend-item">
                    <span class="sb-pulse-dot" style="background:<?= $color ?>"></span>
                    <?= htmlspecialchars($tc['sound_type']) ?> · <?= $tc['n'] ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="sb-pulse-side">
            <div class="sb-pulse-stat">
                <i class="bi bi-person-fill"></i>
                <div>
                    <div class="sb-pulse-stat-num"><?= $totalUsers ?></div>
                    <div class="sb-pulse-stat-label">Users</div>
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

    <!-- Quick links -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <a href="users.php" class="sb-link-card">
                <div class="sb-stat-icon c-blue"><i class="bi bi-people-fill"></i></div>
                <div class="sb-link-card-info">
                    <div class="title">Manage Users</div>
                    <div class="sub">Add, search, and remove registered accounts</div>
                </div>
                <i class="bi bi-chevron-right sb-link-card-arrow"></i>
            </a>
        </div>
        <div class="col-md-6">
            <a href="alerts.php" class="sb-link-card">
                <div class="sb-stat-icon c-red"><i class="bi bi-bell-fill"></i></div>
                <div class="sb-link-card-info">
                    <div class="title">All Alerts</div>
                    <div class="sub">View and filter system-wide alert events</div>
                </div>
                <i class="bi bi-chevron-right sb-link-card-arrow"></i>
            </a>
        </div>
    </div>

    <!-- Recent alerts -->
    <div class="sb-card">
        <div class="sb-section-header">
            <div class="sb-section-title">
                <i class="bi bi-activity"></i> Recent System Alerts
            </div>
            <a href="alerts.php" class="btn sb-btn-outline">View All</a>
        </div>

        <?php if (empty($recent)): ?>
            <div class="sb-empty">
                <i class="bi bi-inbox"></i>
                <p>No alerts in the system yet.</p>
            </div>
        <?php else: ?>
        <?php
            $today     = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $lastDate  = null;
        ?>
        <?php foreach ($recent as $row):
            $meta    = $soundIcons[$row['sound_type']] ?? ['bi-volume-up-fill', 'sound-default'];
            $rowDate = date('Y-m-d', strtotime($row['detected_at']));

            if ($rowDate !== $lastDate):
                if ($lastDate !== null) echo '</div>'; // close previous .sb-timeline-group
                $lastDate = $rowDate;
                $label = date('l, F j, Y', strtotime($row['detected_at']));
                if ($rowDate === $today) $label = 'Today — ' . $label;
                elseif ($rowDate === $yesterday) $label = 'Yesterday — ' . $label;
        ?>
            <div class="sb-timeline-day"><?= htmlspecialchars($label) ?></div>
            <div class="sb-timeline-group">
        <?php endif; ?>

            <div class="sb-timeline-item" data-alert-id="<?= $row['id'] ?>">
                <div class="sb-timeline-node <?= $meta[1] ?>">
                    <i class="bi <?= $meta[0] ?>"></i>
                </div>
                <div class="sb-timeline-content">
                    <div class="sb-timeline-row1">
                        <span class="sb-timeline-title"><?= htmlspecialchars($row['sound_type']) ?></span>
                        <span class="sb-status <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                    </div>
                    <div class="sb-timeline-meta">
                        <span>
                            <span class="sb-avatar sb-avatar-sm"><?= strtoupper(substr($row['user_name'], 0, 1)) ?></span>
                            <?= htmlspecialchars($row['user_name']) ?>
                        </span>
                        <span><i class="bi bi-clock"></i> <?= date('h:i A', strtotime($row['detected_at'])) ?></span>
                        <span><i class="bi bi-<?= $row['location'] === 'indoor' ? 'house' : 'tree' ?>"></i> <?= ucfirst($row['location']) ?></span>
                        <span><i class="bi bi-soundwave"></i> <?= $row['decibel_level'] ? number_format($row['decibel_level'], 1) . ' dB' : '—' ?></span>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>
        </div><!-- close last .sb-timeline-group -->
        <?php endif; ?>
    </div>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
