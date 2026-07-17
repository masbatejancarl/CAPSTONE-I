<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];

$stmt = db()->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']         ?? '');
    $email       = trim($_POST['email']        ?? '');
    $currentPass =      $_POST['current_password'] ?? '';
    $newPass     =      $_POST['new_password']      ?? '';
    $confirmPass =      $_POST['confirm_password']  ?? '';

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif (!isValidName($name)) {
        $error = namePolicyMessage();
    } elseif (!isGmailAddress($email)) {
        $error = emailPolicyMessage();
    } else {
        $check = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $uid]);
        if ($check->fetchColumn() > 0) {
            $error = 'That email is already used by another account.';
        } elseif ($newPass !== '' || $currentPass !== '' || $confirmPass !== '') {
            $hstmt = db()->prepare("SELECT password FROM users WHERE id = ?");
            $hstmt->execute([$uid]);
            $currentHash = $hstmt->fetchColumn();

            if (!$currentPass || !passwordMatches($currentPass, $currentHash)) {
                $error = 'Current password is incorrect.';
            } elseif (!isStrongPassword($newPass)) {
                $error = passwordPolicyMessage();
            } elseif ($newPass !== $confirmPass) {
                $error = 'New passwords do not match.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $upd = db()->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $upd->execute([$name, $email, $newHash, $uid]);
                $_SESSION['name'] = $name;
                $profile['name'] = $name;
                $profile['email'] = $email;
                $success = 'Profile and password updated successfully.';
            }
        } else {
            $upd = db()->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $upd->execute([$name, $email, $uid]);
            $_SESSION['name'] = $name;
            $profile['name'] = $name;
            $profile['email'] = $email;
            $success = 'Profile updated successfully.';
        }
    }

    if ($error) {
        $profile['name']  = $name;
        $profile['email'] = $email;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Settings — SonoBand</title>
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
            <div class="sb-page-title"><i class="bi bi-gear-fill"></i> Account Settings</div>
            <div class="sb-page-sub">Manage your profile and password</div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="sb-alert sb-alert-danger">
            <i class="bi bi-exclamation-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="sb-alert sb-alert-success">
            <i class="bi bi-check-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <div class="sb-card" style="max-width:520px">
        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="sb-label">Full Name</label>
                <input type="text" name="name" class="form-control sb-input"
                       pattern="<?= NAME_PATTERN_ATTR ?>" title="<?= NAME_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="sb-label">Email Address</label>
                <input type="email" name="email" class="form-control sb-input"
                       pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
            </div>

            <hr style="border-color:var(--border);margin:18px 0">
            <div class="sb-page-sub" style="margin-bottom:12px">Change Password <span style="color:var(--muted)">(optional)</span></div>

            <div class="mb-3">
                <label class="sb-label">Current Password</label>
                <input type="password" name="current_password" class="form-control sb-input"
                       placeholder="••••••••">
            </div>
            <div class="mb-3">
                <label class="sb-label">New Password</label>
                <input type="password" name="new_password" class="form-control sb-input"
                       placeholder="••••••••" data-pw-input="settingsPwChecklist">
                <?php renderPwChecklist('settingsPwChecklist'); ?>
            </div>
            <div class="mb-4">
                <label class="sb-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control sb-input"
                       placeholder="••••••••">
            </div>

            <button type="submit" class="btn sb-btn-primary">
                <i class="bi bi-check-lg"></i> Save Changes
            </button>
        </form>
    </div>

    <footer class="sb-page-footer">&copy; <?= date('Y') ?> SonoBand. All rights reserved.</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
