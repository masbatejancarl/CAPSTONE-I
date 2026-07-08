<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];

$stmt = db()->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

$totalAlerts = (int)db()->query("SELECT COUNT(*) FROM alerts WHERE user_id=$uid")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<?php require_once __DIR__ . '/../includes/topnav_user.php'; ?>
<div class="fb-content">

    <div class="sb-page-header">
        <div>
            <div class="sb-page-title"><i class="bi bi-person-fill"></i> My Profile</div>
            <div class="sb-page-sub">Your basic account information</div>
        </div>
    </div>

    <div class="sb-card" style="max-width:520px">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
            <div class="sb-avatar" style="width:52px;height:52px;font-size:20px;border-radius:8px">
                <?= strtoupper(substr($profile['name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:16px;font-weight:700;color:var(--text)"><?= htmlspecialchars($profile['name']) ?></div>
                <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($profile['email']) ?></div>
            </div>
        </div>

        <div class="sb-info-row">
            <div class="sb-info-label"><i class="bi bi-person-badge"></i> Full Name</div>
            <div class="sb-info-value"><?= htmlspecialchars($profile['name']) ?></div>
        </div>
        <div class="sb-info-row">
            <div class="sb-info-label"><i class="bi bi-envelope-fill"></i> Email Address</div>
            <div class="sb-info-value"><?= htmlspecialchars($profile['email']) ?></div>
        </div>
        <div class="sb-info-row">
            <div class="sb-info-label"><i class="bi bi-shield-fill"></i> Account Role</div>
            <div class="sb-info-value"><?= ucfirst($profile['role']) ?></div>
        </div>
        <div class="sb-info-row">
            <div class="sb-info-label"><i class="bi bi-calendar-check"></i> Member Since</div>
            <div class="sb-info-value"><?= date('F d, Y', strtotime($profile['created_at'])) ?></div>
        </div>
        <div class="sb-info-row">
            <div class="sb-info-label"><i class="bi bi-bell-fill"></i> Total Alerts Recorded</div>
            <div class="sb-info-value"><?= $totalAlerts ?></div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
