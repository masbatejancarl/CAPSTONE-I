<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];

$stmt = db()->prepare("SELECT name, email, avatar_url FROM users WHERE id = ?");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

$error = $success = '';
$avatarError = $avatarSuccess = '';
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
$formValues = $_SESSION['settings_form'] ?? null;
unset($_SESSION['settings_form']);
if (is_array($formValues)) {
    $profile['name'] = $formValues['name'] ?? $profile['name'];
    $profile['email'] = $formValues['email'] ?? $profile['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    $result = uploadAvatar($uid, $_FILES['avatar'] ?? [], $profile['avatar_url']);
    if ($result['ok']) {
        db()->prepare("UPDATE users SET avatar_url = ? WHERE id = ?")->execute([$result['url'], $uid]);
        $_SESSION['avatar_url'] = $result['url'];
        $profile['avatar_url']  = $result['url'];
        $avatarSuccess = 'Profile photo updated successfully.';
    } else {
        $avatarError = $result['error'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    removeAvatar($profile['avatar_url']);
    db()->prepare("UPDATE users SET avatar_url = NULL WHERE id = ?")->execute([$uid]);
    $_SESSION['avatar_url'] = null;
    $profile['avatar_url']  = null;
    $avatarSuccess = 'Profile photo removed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            } elseif ($newPass === $currentPass) {
                $error = 'New password must be different from the current password.';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($error || $avatarError) {
        $_SESSION['flash_error'] = $error ?: $avatarError;
        if (isset($_POST['name'], $_POST['email'])) {
            $_SESSION['settings_form'] = ['name' => $_POST['name'], 'email' => $_POST['email']];
        }
    } else {
        $_SESSION['flash_success'] = $success ?: $avatarSuccess;
    }
    header('Location: settings.php');
    exit;
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
    <?php if ($avatarError): ?>
        <div class="sb-alert sb-alert-danger">
            <i class="bi bi-exclamation-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($avatarError) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($avatarSuccess): ?>
        <div class="sb-alert sb-alert-success">
            <i class="bi bi-check-circle flex-shrink-0"></i>
            <span><?= htmlspecialchars($avatarSuccess) ?></span>
        </div>
    <?php endif; ?>

    <div class="sb-card" style="max-width:520px">
        <div class="sb-page-sub" style="margin-bottom:12px">Profile Photo</div>
        <form method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <?= renderAvatar($profile['name'] ?? '', $profile['avatar_url'] ?? null, 'sb-avatar-xl') ?>
            <div style="flex:1;min-width:220px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,.jfif" class="form-control sb-input" style="max-width:260px">
                <button type="submit" name="upload_avatar" value="1" class="btn sb-btn-primary">
                    <i class="bi bi-upload"></i> Upload
                </button>
                <?php if (!empty($profile['avatar_url'])): ?>
                <button type="submit" name="remove_avatar" value="1" class="btn sb-btn-outline"
                        onclick="return confirm('Remove your profile photo?')">
                    <i class="bi bi-trash3"></i> Remove
                </button>
                <?php endif; ?>
            </div>
        </form>
        <div class="sb-modal-sub" style="margin-top:10px">JPG, JFIF, PNG, or WEBP. Max 3MB.</div>
    </div>

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
