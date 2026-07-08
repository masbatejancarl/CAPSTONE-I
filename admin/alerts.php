<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$months = [
    '01' => 'January',   '02' => 'February', '03' => 'March',
    '04' => 'April',     '05' => 'May',      '06' => 'June',
    '07' => 'July',      '08' => 'August',   '09' => 'September',
    '10' => 'October',   '11' => 'November', '12' => 'December',
];
$availableYears = range(2024, (int)date('Y'));

$filterType   = $_GET['type']       ?? '';
$filterStatus = $_GET['status']     ?? '';
$filterUser   = (int)($_GET['user_id'] ?? 0);
$fromMonth    = $_GET['from_month'] ?? '';
$toMonth      = $_GET['to_month']   ?? '';
$filterYear   = $_GET['year']       ?? '';

$currentYear  = date('Y');
$currentMonth = date('m');

// Realtime year: future months don't exist yet, so clamp them server-side too.
if ($filterYear === $currentYear) {
    if ($fromMonth && $fromMonth > $currentMonth) { $fromMonth = $currentMonth; }
    if ($toMonth && $toMonth > $currentMonth)     { $toMonth = $currentMonth; }
}

if ($fromMonth && $toMonth && $fromMonth > $toMonth) {
    [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($filterType)   { $where[] = 'a.sound_type = ?';        $params[] = $filterType; }
if ($filterStatus) { $where[] = 'a.status = ?';            $params[] = $filterStatus; }
if ($filterUser)   { $where[] = 'a.user_id = ?';           $params[] = $filterUser; }

if ($filterYear) {
    $where[] = "to_char(a.detected_at, 'YYYY') = ?";
    $params[] = $filterYear;
}
if ($fromMonth && $toMonth) {
    $where[] = "to_char(a.detected_at, 'MM') BETWEEN ? AND ?";
    $params[] = $fromMonth;
    $params[] = $toMonth;
} elseif ($fromMonth) {
    $where[] = "to_char(a.detected_at, 'MM') >= ?";
    $params[] = $fromMonth;
} elseif ($toMonth) {
    $where[] = "to_char(a.detected_at, 'MM') <= ?";
    $params[] = $toMonth;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cstmt = db()->prepare("SELECT COUNT(*) FROM alerts a $whereSQL");
$cstmt->execute($params);
$totalRows  = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$dstmt = db()->prepare(
    "SELECT a.*, u.name AS user_name FROM alerts a
     JOIN users u ON a.user_id = u.id
     $whereSQL ORDER BY a.detected_at DESC LIMIT $limit OFFSET $offset"
);
$dstmt->execute($params);
$alerts = $dstmt->fetchAll();

$hiUsers = db()->query("SELECT id, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

$soundIcons = [
    'Doorbell'        => ['bi-bell-fill',                'sound-doorbell'],
    'Fire Alarm'      => ['bi-fire',                     'sound-alarm'],
    'Vehicle Horn'    => ['bi-car-front-fill',            'sound-vehicle'],
    'Emergency Siren' => ['bi-exclamation-triangle-fill', 'sound-siren'],
    'Loud Knock'      => ['bi-hand-index-thumb-fill',     'sound-knock'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Alerts — SonoBand</title>
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
            <div class="sb-page-title"><i class="bi bi-bell"></i> All Alerts</div>
            <div class="sb-page-sub"><?= $totalRows ?> record(s) found</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sb-filter">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="sb-label">Type</label>
                <select name="type" class="form-select sb-input form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (['Doorbell','Fire Alarm','Vehicle Horn','Emergency Siren','Loud Knock'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="sb-label">Status</label>
                <select name="status" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <option value="unread" <?= $filterStatus === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read"   <?= $filterStatus === 'read'   ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="sb-label">User</label>
                <select name="user_id" class="form-select sb-input form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach ($hiUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="sb-label">Year</label>
                <select name="year" id="sb-year" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($availableYears as $yr): ?>
                    <option value="<?= $yr ?>" <?= $filterYear === (string)$yr ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="sb-label">From Month</label>
                <select name="from_month" id="sb-from-month" class="form-select sb-input form-select-sm">
                    <option value="">Any</option>
                    <?php foreach ($months as $mm => $mname): ?>
                    <option value="<?= $mm ?>" <?= $fromMonth === $mm ? 'selected' : '' ?>><?= $mname ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="sb-label">To Month</label>
                <select name="to_month" id="sb-to-month" class="form-select sb-input form-select-sm">
                    <option value="">Any</option>
                    <?php foreach ($months as $mm => $mname): ?>
                    <option value="<?= $mm ?>" <?= $toMonth === $mm ? 'selected' : '' ?>><?= $mname ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-1 d-flex">
                <button type="submit" class="btn sb-btn-primary flex-fill px-2" title="Filter">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Timeline -->
    <div class="sb-card">
        <?php if (empty($alerts)): ?>
            <div class="sb-empty">
                <i class="bi bi-inbox"></i>
                <p>No alerts match the criteria.</p>
            </div>
        <?php else: ?>
        <?php
            $today     = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $lastDate  = null;
        ?>
        <?php foreach ($alerts as $row):
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

        <?php if ($totalPages > 1): ?>
        <div class="sb-pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="sb-page-link">‹</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="sb-page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="sb-page-link">›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
(function () {
    const CURRENT_YEAR  = '<?= $currentYear ?>';
    const CURRENT_MONTH = '<?= $currentMonth ?>';

    const yearSelect   = document.getElementById('sb-year');
    const monthSelects = [document.getElementById('sb-from-month'), document.getElementById('sb-to-month')];

    function applyMonthLimit() {
        const isCurrentYear = yearSelect.value === CURRENT_YEAR;

        monthSelects.forEach(function (select) {
            let selectedIsNowInvalid = false;

            Array.from(select.options).forEach(function (opt) {
                if (opt.value === '') return; // keep "Any" always available

                const isFuture = isCurrentYear && opt.value > CURRENT_MONTH;
                opt.hidden = isFuture;
                opt.disabled = isFuture;

                if (isFuture && opt.selected) selectedIsNowInvalid = true;
            });

            if (selectedIsNowInvalid) select.value = '';
        });
    }

    yearSelect.addEventListener('change', applyMonthLimit);
    applyMonthLimit(); // enforce on initial load too (e.g. after form submit)
})();
</script>
</body>
</html>
