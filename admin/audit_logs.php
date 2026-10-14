<?php
$appBaseUrl = getenv('APP_BASE_URL');
$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
define('BASE_URL', rtrim($appBaseUrl !== false ? $appBaseUrl : ($isRailway ? '' : '/CAPSTONE I'), '/'));
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$success = $error = '';

if (!empty($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (!empty($_SESSION['flash_error']))   { $error   = $_SESSION['flash_error'];   unset($_SESSION['flash_error']); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_logs'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    } else {
        db()->exec('TRUNCATE TABLE audit_logs');
        $_SESSION['flash_success'] = 'All audit logs have been permanently deleted.';
    }
    header('Location: audit_logs.php');
    exit;
}

$months = [
    '01' => 'January',   '02' => 'February', '03' => 'March',
    '04' => 'April',     '05' => 'May',      '06' => 'June',
    '07' => 'July',      '08' => 'August',   '09' => 'September',
    '10' => 'October',   '11' => 'November', '12' => 'December',
];
$availableYears = range(2024, (int)date('Y'));

$filterAction = $_GET['action']     ?? '';
$filterRole   = $_GET['role']       ?? '';
$search       = trim($_GET['search'] ?? '');
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
if ($filterAction) { $where[] = 'al.action = ?'; $params[] = $filterAction; }
if ($filterRole)   { $where[] = 'al.role = ?';   $params[] = $filterRole; }

if ($filterYear) {
    $where[] = "to_char(al.created_at, 'YYYY') = ?";
    $params[] = $filterYear;
}
if ($fromMonth && $toMonth) {
    $where[] = "to_char(al.created_at, 'MM') BETWEEN ? AND ?";
    $params[] = $fromMonth;
    $params[] = $toMonth;
} elseif ($fromMonth) {
    $where[] = "to_char(al.created_at, 'MM') >= ?";
    $params[] = $fromMonth;
} elseif ($toMonth) {
    $where[] = "to_char(al.created_at, 'MM') <= ?";
    $params[] = $toMonth;
}
if ($search) {
    $like    = "%$search%";
    $where[] = '(al.user_name ILIKE ? OR al.user_email ILIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cstmt = db()->prepare("SELECT COUNT(*) FROM audit_logs al $whereSQL");
$cstmt->execute($params);
$totalRows  = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$dstmt = db()->prepare(
    "SELECT al.* FROM audit_logs al
     $whereSQL ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset"
);
$dstmt->execute($params);
$logs = $dstmt->fetchAll();

$actionMeta = [
    'login'          => ['bi-box-arrow-in-right', 'sound-doorbell', 'Login'],
    'logout'         => ['bi-box-arrow-right',     'sound-default',  'Logout'],
    'login_failed'   => ['bi-shield-x',            'sound-alarm',    'Failed Login'],
    'password_reset' => ['bi-key-fill',            'sound-vehicle',  'Password Reset'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs — SonoBand</title>
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
            <div class="sb-page-title"><i class="bi bi-clock-history"></i> Audit Logs</div>
            <div class="sb-page-sub"><?= $totalRows ?> event(s) recorded</div>
        </div>
        <?php if ($totalRows > 0): ?>
        <button class="btn sb-btn-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
            <i class="bi bi-trash3"></i> Clear All Logs
        </button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="sb-alert sb-alert-success">
            <i class="bi bi-check-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="sb-alert sb-alert-danger">
            <i class="bi bi-exclamation-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="sb-filter">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="sb-label">Search</label>
                <input type="text" name="search" class="form-control sb-input form-control-sm"
                       placeholder="Name or email…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="sb-label">Action</label>
                <select name="action" class="form-select sb-input form-select-sm">
                    <option value="">All</option>
                    <option value="login"        <?= $filterAction === 'login'        ? 'selected' : '' ?>>Login</option>
                    <option value="logout"       <?= $filterAction === 'logout'       ? 'selected' : '' ?>>Logout</option>
                    <option value="login_failed" <?= $filterAction === 'login_failed' ? 'selected' : '' ?>>Failed Login</option>
                    <option value="password_reset" <?= $filterAction === 'password_reset' ? 'selected' : '' ?>>Password Reset</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="sb-label">Role</label>
                <select name="role" class="form-select sb-input form-select-sm">
                    <option value="">All Roles</option>
                    <option value="user"      <?= $filterRole === 'user'      ? 'selected' : '' ?>>User</option>
                    <option value="caregiver" <?= $filterRole === 'caregiver' ? 'selected' : '' ?>>Caregiver</option>
                    <option value="admin"     <?= $filterRole === 'admin'     ? 'selected' : '' ?>>Admin</option>
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
        <?php if (empty($logs)): ?>
            <div class="sb-empty">
                <i class="bi bi-inbox"></i>
                <p>No audit events match the criteria.</p>
            </div>
        <?php else: ?>
        <?php
            $today     = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $yesterday = (new DateTime('-1 day', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $lastDate  = null;
        ?>
        <?php foreach ($logs as $row):
            $meta    = $actionMeta[$row['action']] ?? ['bi-question-circle', 'sound-default', ucfirst($row['action'])];
            $rowDate = toManilaTime($row['created_at'], 'Y-m-d');

            if ($rowDate !== $lastDate):
                if ($lastDate !== null) echo '</div>'; // close previous .sb-timeline-group
                $lastDate = $rowDate;
                $label = toManilaTime($row['created_at'], 'l, F j, Y');
                if ($rowDate === $today) $label = 'Today — ' . $label;
                elseif ($rowDate === $yesterday) $label = 'Yesterday — ' . $label;
        ?>
            <div class="sb-timeline-day"><?= htmlspecialchars($label) ?></div>
            <div class="sb-timeline-group">
        <?php endif; ?>

            <div class="sb-timeline-item audit-log-item">
                <div class="sb-timeline-node <?= $meta[1] ?>">
                    <i class="bi <?= $meta[0] ?>"></i>
                </div>
                <div class="sb-timeline-content">
                    <div class="sb-timeline-row1">
                        <span class="sb-timeline-title"><?= htmlspecialchars($meta[2]) ?></span>
                        <span class="sb-status sb-role-badge sb-role-<?= htmlspecialchars($row['role'] ?: 'user') ?>">
                            <?= htmlspecialchars(ucfirst($row['role'] ?: 'unknown')) ?>
                        </span>
                    </div>
                    <div class="sb-timeline-meta">
                        <span><i class="bi bi-person"></i> <?= htmlspecialchars($row['user_name'] ?: 'Unknown') ?></span>
                        <span><i class="bi bi-clock"></i> <?= toManilaTime($row['created_at'], 'h:i A') ?></span>
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
                for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++) {
                    $pageNumbers[] = $p;
                }
                $pageNumbers = array_values(array_unique($pageNumbers));
                sort($pageNumbers);
                $previousPage = 0;
            ?>
            <?php foreach ($pageNumbers as $p): ?>
                <?php if ($previousPage && $p > $previousPage + 1): ?>
                    <span class="sb-page-ellipsis" aria-hidden="true">...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="sb-page-link <?= $p === $page ? 'active' : '' ?>"
                   <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php $previousPage = $p; ?>
            <?php endforeach; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="sb-page-link" aria-label="Next page">›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>
</div>

<!-- Clear All Logs Confirmation Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sb-modal-title-group">
                    <i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);background:var(--danger-dim);border-color:rgba(224,82,82,0.25)"></i>
                    <div>
                        <h5 class="modal-title">Clear All Audit Logs</h5>
                        <div class="sb-modal-sub">This action cannot be undone</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="margin:0;font-size:13px;color:var(--text-2)">
                    This will <strong style="color:var(--text)">permanently delete all <?= $totalRows ?> audit log
                    entries</strong> — including login, logout, and failed login records. This cannot be recovered.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn sb-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="clear_all_logs" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn sb-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, Delete All Logs
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
(function () {
    const yearSelect = document.getElementById('sb-year');
    if (!yearSelect) return;

    const CURRENT_YEAR  = '<?= $currentYear ?>';
    const CURRENT_MONTH = '<?= $currentMonth ?>';

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
