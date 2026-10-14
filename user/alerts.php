<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];
$soundTypeStmt = db()->prepare("SELECT DISTINCT sound_type FROM alerts WHERE user_id = ? AND sound_type IS NOT NULL AND sound_type <> '' ORDER BY sound_type");
$soundTypeStmt->execute([$uid]);
$soundTypeOptions = soundTypeOptionGroups($soundTypeStmt->fetchAll(PDO::FETCH_COLUMN));

$availableYears = range(2024, (int)manilaToday());

$months = [
    '01' => 'January',   '02' => 'February', '03' => 'March',
    '04' => 'April',     '05' => 'May',      '06' => 'June',
    '07' => 'July',      '08' => 'August',   '09' => 'September',
    '10' => 'October',   '11' => 'November', '12' => 'December',
];

$filterDirection = $_GET['direction']  ?? '';
$filterStatus    = $_GET['status']     ?? '';
$filterAlertType = $_GET['alert_type'] ?? '';
$filterSoundType = $_GET['sound_type'] ?? '';
$fromMonth  = $_GET['from_month'] ?? '';
$toMonth    = $_GET['to_month']   ?? '';
$filterYear = $_GET['year']       ?? '';

$currentYear  = substr(manilaToday(), 0, 4);
$currentMonth = substr(manilaToday(), 5, 2);

// Realtime year: future months don't exist yet, so clamp them server-side too.
if ($filterYear === $currentYear) {
    if ($fromMonth && $fromMonth > $currentMonth) { $fromMonth = $currentMonth; }
    if ($toMonth && $toMonth > $currentMonth)     { $toMonth = $currentMonth; }
}

if ($fromMonth && $toMonth && $fromMonth > $toMonth) {
    [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = ["user_id = $uid"];
$params = [];
if ($filterStatus) { $where[] = 'status = ?'; $params[] = $filterStatus; }
if ($filterAlertType && in_array($filterAlertType, alertTypeOptions(), true)) {
    $where[] = alertSeveritySql() . ' = ?'; $params[] = $filterAlertType;
}
if (isset($soundTypeOptions[$filterSoundType])) {
    $soundTypeValues = $soundTypeOptions[$filterSoundType]['values'];
    $where[] = 'sound_type IN (' . implode(',', array_fill(0, count($soundTypeValues), '?')) . ')';
    array_push($params, ...$soundTypeValues);
} else {
    $filterSoundType = '';
}
if ($filterDirection) {
    // metadata holds either a bare "left"/"right"/"both" string or JSON like
    // {"direction":"left"} depending on the producer, so a substring match
    // catches both formats without requiring metadata to be valid JSON.
    $where[] = 'metadata ILIKE ?';
    $params[] = '%' . $filterDirection . '%';
}

if ($filterYear) {
    $where[] = "to_char(detected_at " . MANILA_SQL . ", 'YYYY') = ?";
    $params[] = $filterYear;
}
if ($fromMonth && $toMonth) {
    $where[] = "to_char(detected_at " . MANILA_SQL . ", 'MM') BETWEEN ? AND ?";
    $params[] = $fromMonth;
    $params[] = $toMonth;
} elseif ($fromMonth) {
    $where[] = "to_char(detected_at " . MANILA_SQL . ", 'MM') >= ?";
    $params[] = $fromMonth;
} elseif ($toMonth) {
    $where[] = "to_char(detected_at " . MANILA_SQL . ", 'MM') <= ?";
    $params[] = $toMonth;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cstmt = db()->prepare("SELECT COUNT(*) FROM alerts $whereSQL");
$cstmt->execute($params);
$totalRows  = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$dstmt = db()->prepare("SELECT * FROM alerts $whereSQL ORDER BY detected_at DESC LIMIT $limit OFFSET $offset");
$dstmt->execute($params);
$alerts = $dstmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alert History — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<?php require_once __DIR__ . '/../includes/topnav_user.php'; ?>
<div class="fb-content">

    <div class="sb-page-header">
        <div>
            <div class="sb-page-title"><i class="bi bi-bell"></i> Alert History</div>
            <div class="sb-page-sub"><?= $totalRows ?> record(s) found</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sb-filter">
        <form method="GET" class="row g-2 align-items-end alert-filter-form">
            <div class="col-6 col-md-3">
                <label class="sb-label">Direction</label>
                <select name="direction" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <option value="left"  <?= $filterDirection === 'left'  ? 'selected' : '' ?>>Left</option>
                    <option value="right" <?= $filterDirection === 'right' ? 'selected' : '' ?>>Right</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="sb-label">Status</label>
                <select name="status" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <option value="unread" <?= $filterStatus === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read"   <?= $filterStatus === 'read'   ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="sb-label">Severity</label>
                <select name="alert_type" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <?php foreach (alertTypeOptions() as $type): ?>
                    <option value="<?= $type ?>" <?= $filterAlertType === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="sb-label">Sound Type</label>
                <select name="sound_type" class="form-select sb-input form-select-sm">
                    <option value="">All Sounds</option>
                    <?php foreach ($soundTypeOptions as $soundTypeKey => $soundTypeOption): ?>
                    <option value="<?= htmlspecialchars($soundTypeKey) ?>" <?= $filterSoundType === $soundTypeKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars($soundTypeOption['label']) ?>
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
                <p>No alerts match your filter criteria.</p>
            </div>
        <?php else: ?>
        <?php
            $today     = manilaToday();
            $yesterday = (new DateTime('now', new DateTimeZone('Asia/Manila')))->modify('-1 day')->format('Y-m-d');
            $lastDate  = null;
        ?>
        <?php foreach ($alerts as $row):
            $direction = alertDirection($row['metadata'] ?? null);
            $rowDate   = toManilaTime($row['detected_at'], 'Y-m-d');

            if ($rowDate !== $lastDate):
                if ($lastDate !== null) echo '</div>'; // close previous .sb-timeline-group
                $lastDate = $rowDate;
                $label = toManilaTime($row['detected_at'], 'l, F j, Y');
                if ($rowDate === $today) $label = 'Today — ' . $label;
                elseif ($rowDate === $yesterday) $label = 'Yesterday — ' . $label;
        ?>
            <div class="sb-timeline-day"><?= htmlspecialchars($label) ?></div>
            <div class="sb-timeline-group">
        <?php endif; ?>

            <div class="sb-timeline-item js-mark-readable" data-alert-id="<?= $row['id'] ?>">
                <div class="sb-timeline-node signal-<?= htmlspecialchars($direction ?: 'neutral') ?>">
                    <i class="bi <?= directionIcon($direction) ?>"></i>
                </div>
                <div class="sb-timeline-content">
                    <div class="sb-timeline-row1">
                        <span class="sb-timeline-title"><?= htmlspecialchars(directionLabel($direction)) ?></span>
                        <span class="sb-sound-type"><?= htmlspecialchars(soundTypeLabel($row['sound_type'] ?? null)) ?></span>
                        <?php $severity = alertSeverity($row['alert_type'] ?? null); ?>
                        <span class="sb-alert-type <?= $severity ?>"><?= alertTypeLabel($severity) ?></span>
                        <span class="sb-status <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                    </div>
                    <div class="sb-timeline-meta">
                        <span><i class="bi bi-clock"></i> <?= toManilaTime($row['detected_at'], 'h:i A') ?></span>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>
        </div><!-- close last .sb-timeline-group -->

        <?php if ($totalPages > 1): ?>
        <div class="sb-pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="sb-page-link" aria-label="Previous page">‹</a>
            <?php endif; ?>
            <?php
                $pageNumbers = [1, $totalPages];
                for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++) $pageNumbers[] = $p;
                $pageNumbers = array_values(array_unique($pageNumbers));
                sort($pageNumbers);
                $previousPage = 0;
            ?>
            <?php foreach ($pageNumbers as $p): ?>
                <?php if ($previousPage && $p > $previousPage + 1): ?><span class="sb-page-ellipsis" aria-hidden="true">...</span><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="sb-page-link <?= $p === $page ? 'active' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php $previousPage = $p; ?>
            <?php endforeach; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="sb-page-link" aria-label="Next page">›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="sb-page-footer">&copy; <?= date('Y') ?> SonoBand. All rights reserved.</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
<script>
(function () {
    const CURRENT_YEAR  = '<?= $currentYear ?>';
    const CURRENT_MONTH = '<?= $currentMonth ?>';

    const yearSelect  = document.getElementById('sb-year');
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
