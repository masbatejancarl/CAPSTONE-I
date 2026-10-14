<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$success = $error = '';

if (!empty($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (!empty($_SESSION['flash_error']))   { $error   = $_SESSION['flash_error'];   unset($_SESSION['flash_error']); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delId = (int)$_POST['delete'];
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif ($delId === (int)currentUser()['id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $rstmt = db()->prepare("SELECT role FROM users WHERE id = ?");
        $rstmt->execute([$delId]);
        $targetRole = $rstmt->fetchColumn();

        if ($targetRole === 'admin') {
            $error = 'Admin accounts cannot be deleted here.';
        } else {
            db()->exec("DELETE FROM users WHERE id=$delId");
            $success = 'User deleted successfully.';
        }
    }
}

$search     = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($search) {
    $like    = "%$search%";
    $where[] = '(name ILIKE ? OR email ILIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter) { $where[] = 'role = ?'; $params[] = $roleFilter; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cstmt = db()->prepare("SELECT COUNT(*) FROM users $whereSQL");
$cstmt->execute($params);
$totalRows  = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$dstmt = db()->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM alerts WHERE user_id=u.id) AS alert_count
     FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset"
);
$dstmt->execute($params);
$users = $dstmt->fetchAll();

$patientOptions = db()->query("SELECT id, name FROM users WHERE role='user' ORDER BY name")->fetchAll();

// Linked patients per caregiver (names for display, ids for the edit modal's checklist).
$caregiverIds = array_column(array_filter($users, fn($u) => $u['role'] === 'caregiver'), 'id');
$linkedPatients = [];
$linkedPatientIds = [];
if ($caregiverIds) {
    $placeholders = implode(',', array_fill(0, count($caregiverIds), '?'));
    $lstmt = db()->prepare(
        "SELECT cl.caregiver_id, u.id, u.name FROM caregiver_links cl
         JOIN users u ON u.id = cl.patient_id
         WHERE cl.caregiver_id IN ($placeholders) ORDER BY u.name"
    );
    $lstmt->execute($caregiverIds);
    foreach ($lstmt->fetchAll() as $row) {
        $linkedPatients[$row['caregiver_id']][] = $row['name'];
        $linkedPatientIds[$row['caregiver_id']][] = (int)$row['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderFavicon(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="sb-layout">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="sb-main">
<div class="sb-content">

    <div class="sb-page-header">
        <div>
            <div class="sb-page-title"><i class="bi bi-people"></i> Users</div>
            <div class="sb-page-sub"><?= $totalRows ?> account(s) registered</div>
        </div>
        <button class="btn sb-btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus-fill"></i> Add Account
        </button>
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
            <div class="col-md-5">
                <label class="sb-label">Search</label>
                <input type="text" name="search" class="form-control sb-input form-control-sm"
                       placeholder="Name or email…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="sb-label">Role</label>
                <select name="role" class="form-select sb-input form-select-sm">
                    <option value="">All Roles</option>
                    <option value="user"      <?= $roleFilter === 'user'      ? 'selected' : '' ?>>User</option>
                    <option value="caregiver" <?= $roleFilter === 'caregiver' ? 'selected' : '' ?>>Caregiver</option>
                    <option value="admin"     <?= $roleFilter === 'admin'     ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn sb-btn-primary flex-fill">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- People directory -->
    <?php if (empty($users)): ?>
        <div class="sb-card">
            <div class="sb-empty">
                <i class="bi bi-people"></i>
                <p>No users found.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="sb-user-grid">
        <?php foreach ($users as $row): ?>
        <div class="sb-user-card">
            <div class="sb-user-card-top">
                <?= renderAvatar($row['name'], $row['avatar_url'] ?? null, 'sb-avatar-lg') ?>
                <span class="sb-status sb-role-badge sb-role-<?= $row['role'] ?>"><?= ucfirst($row['role']) ?></span>
            </div>
            <div class="sb-user-card-name"><?= htmlspecialchars($row['name']) ?></div>
            <div class="sb-user-card-email"><?= htmlspecialchars($row['email']) ?></div>

            <?php if ($row['role'] === 'caregiver'): ?>
            <div class="sb-user-card-stat">
                <i class="bi bi-person-heart"></i>
                <?php if (!empty($linkedPatients[$row['id']])): ?>
                    Monitors: <?= htmlspecialchars(implode(', ', $linkedPatients[$row['id']])) ?>
                <?php else: ?>
                    No linked patients
                <?php endif; ?>
            </div>
            <?php elseif ($row['role'] === 'user'): ?>
            <a href="alerts.php?user_id=<?= $row['id'] ?>" class="sb-user-card-stat">
                <i class="bi bi-bell-fill"></i>
                <?= $row['alert_count'] ?> alert<?= (int)$row['alert_count'] === 1 ? '' : 's' ?> recorded
            </a>
            <?php else: ?>
            <div class="sb-user-card-stat">
                <i class="bi bi-shield-check"></i>
                Full system access
            </div>
            <?php endif; ?>

            <div class="sb-user-card-footer">
                <span class="sb-user-card-date">
                    <i class="bi bi-calendar3"></i> Joined <?= date('M d, Y', strtotime($row['created_at'])) ?>
                </span>
                <?php if ($row['id'] === currentUser()['id']): ?>
                <span class="sb-user-card-you sb-user-card-footer-row2">You</span>
                <?php elseif ($row['role'] === 'admin'): ?>
                <span class="sb-user-card-you sb-user-card-footer-row2"><i class="bi bi-shield-lock-fill"></i> Protected</span>
                <?php else: ?>
                <div class="sb-user-card-actions sb-user-card-footer-row2">
                    <?php if ($row['role'] === 'caregiver'): ?>
                    <button type="button" class="btn sb-btn-outline js-edit-patients-trigger"
                            data-caregiver-id="<?= $row['id'] ?>"
                            data-caregiver-name="<?= htmlspecialchars($row['name']) ?>"
                            data-linked-ids="<?= htmlspecialchars(implode(',', $linkedPatientIds[$row['id']] ?? [])) ?>">
                        <i class="bi bi-pencil-fill"></i> Edit
                    </button>
                    <?php endif; ?>
                    <form method="POST" action="users.php?<?= http_build_query($_GET) ?>">
                        <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="button" class="btn sb-btn-danger js-delete-trigger"
                                data-user-name="<?= htmlspecialchars($row['name']) ?>">
                            <i class="bi bi-trash3"></i> Delete
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

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
</div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sb-modal-title-group">
                    <i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);background:var(--danger-dim);border-color:rgba(224,82,82,0.25)"></i>
                    <div>
                        <h5 class="modal-title">Delete Account</h5>
                        <div class="sb-modal-sub">This action cannot be undone</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="margin:0;font-size:13px;color:var(--text-2)">
                    Delete <strong id="deleteConfirmName" style="color:var(--text)"></strong>?
                    This will also remove all of their alerts.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn sb-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn sb-btn-danger" id="deleteConfirmSubmit">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sb-modal-title-group">
                    <i class="bi bi-person-plus-fill"></i>
                    <div>
                        <h5 class="modal-title">Add New Account</h5>
                        <div class="sb-modal-sub">Regular users register themselves via the public sign-up page</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/add_user.php" autocomplete="off" id="addUserForm">
                <div class="modal-body sb-modal-form">
                    <div>
                        <label class="sb-label"><i class="bi bi-person-badge"></i> Full Name</label>
                        <input type="text" name="name" class="form-control sb-input" placeholder="Juan Dela Cruz"
                               pattern="<?= NAME_PATTERN_ATTR ?>" title="<?= NAME_PATTERN_TITLE ?>" required>
                    </div>
                    <div>
                        <label class="sb-label"><i class="bi bi-envelope-fill"></i> Email</label>
                        <input type="email" name="email" class="form-control sb-input" placeholder="user@gmail.com"
                               pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>" required>
                    </div>
                    <div>
                        <label class="sb-label"><i class="bi bi-shield-fill"></i> Role</label>
                        <select name="role" id="addUserRole" class="form-select sb-input" required>
                            <option value="admin">Admin</option>
                            <option value="caregiver">Caregiver</option>
                        </select>
                    </div>
                    <div id="addUserPatientsWrap" style="display:none">
                        <label class="sb-label"><i class="bi bi-person-heart"></i> Patients to Monitor</label>
                        <div class="sb-checklist">
                            <?php if (empty($patientOptions)): ?>
                                <span class="sb-modal-sub">No user accounts available to assign yet.</span>
                            <?php else: ?>
                                <?php foreach ($patientOptions as $p): ?>
                                <label class="sb-checklist-item">
                                    <input type="checkbox" name="patient_ids[]" value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label class="sb-label"><i class="bi bi-lock-fill"></i> Password</label>
                        <input type="password" name="password" class="form-control sb-input"
                               placeholder="••••••••" data-pw-input="addAdminPwChecklist" required>
                        <?php renderPwChecklist('addAdminPwChecklist'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn sb-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn sb-btn-primary">
                        <i class="bi bi-person-plus-fill"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Linked Patients Modal -->
<div class="modal fade" id="editPatientsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sb-modal-title-group">
                    <i class="bi bi-person-heart"></i>
                    <div>
                        <h5 class="modal-title">Edit Linked Patients</h5>
                        <div class="sb-modal-sub" id="editPatientsCaregiverName"></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/edit_caregiver_patients.php" id="editPatientsForm">
                <input type="hidden" name="caregiver_id" id="editPatientsCaregiverId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-body sb-modal-form">
                    <div>
                        <label class="sb-label"><i class="bi bi-person-heart"></i> Patients to Monitor</label>
                        <div class="sb-checklist">
                            <?php if (empty($patientOptions)): ?>
                                <span class="sb-modal-sub">No user accounts available to assign yet.</span>
                            <?php else: ?>
                                <?php foreach ($patientOptions as $p): ?>
                                <label class="sb-checklist-item">
                                    <input type="checkbox" name="patient_ids[]" value="<?= $p['id'] ?>" class="js-edit-patient-checkbox">
                                    <?= htmlspecialchars($p['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn sb-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn sb-btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
<script>
(function () {
    // Always start the Add Admin modal with a blank form, regardless of
    // browser autofill/form-restore behavior.
    const addUserModalEl = document.getElementById('addUserModal');
    const roleSelect = document.getElementById('addUserRole');
    const patientsWrap = document.getElementById('addUserPatientsWrap');

    function togglePatientsWrap() {
        patientsWrap.style.display = roleSelect.value === 'caregiver' ? '' : 'none';
    }

    roleSelect?.addEventListener('change', togglePatientsWrap);

    addUserModalEl?.addEventListener('show.bs.modal', () => {
        document.getElementById('addUserForm')?.reset();
        togglePatientsWrap();
    });

    let pendingDeleteForm = null;
    const modalEl = document.getElementById('deleteConfirmModal');
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.js-delete-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingDeleteForm = btn.closest('form');
            document.getElementById('deleteConfirmName').textContent = btn.dataset.userName;
            modal.show();
        });
    });

    document.getElementById('deleteConfirmSubmit').addEventListener('click', () => {
        if (pendingDeleteForm) pendingDeleteForm.submit();
    });

    // Edit Linked Patients — populate the shared modal with the clicked caregiver's data.
    const editPatientsModalEl = document.getElementById('editPatientsModal');
    const editPatientsModal = new bootstrap.Modal(editPatientsModalEl);
    const editPatientCheckboxes = document.querySelectorAll('.js-edit-patient-checkbox');

    document.querySelectorAll('.js-edit-patients-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
            const linkedIds = (btn.dataset.linkedIds || '')
                .split(',').filter(Boolean).map(Number);

            document.getElementById('editPatientsCaregiverId').value = btn.dataset.caregiverId;
            document.getElementById('editPatientsCaregiverName').textContent =
                'Caregiver: ' + btn.dataset.caregiverName;

            editPatientCheckboxes.forEach(cb => {
                cb.checked = linkedIds.includes(Number(cb.value));
            });

            editPatientsModal.show();
        });
    });
})();
</script>
</body>
</html>
