<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) { redirectByRole(); }

// Two-step, email-only reset (no verification code): step 1 looks up the email,
// step 2 lets that email set a new password. The confirmed email is kept in the
// session (not a hidden form field) so step 2 can't be reached without this page
// having actually verified the email in step 1 first.
$error = '';
$step  = 'find_email';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'find_email') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !isGmailAddress($email)) {
        $error = emailPolicyMessage();
    } else {
        $stmt = db()->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['fp_email'] = $email;
            $step = 'set_password';
        } else {
            $error = 'No account found with that email.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'set_password') {
    $email    = $_SESSION['fp_email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!$email) {
        $error = 'Session expired. Please enter your email again.';
    } elseif (!$password || !$confirm) {
        $error = 'All fields are required.';
        $step  = 'set_password';
    } elseif (!isStrongPassword($password)) {
        $error = passwordPolicyMessage();
        $step  = 'set_password';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $step  = 'set_password';
    } else {
            $stmt = db()->prepare("SELECT id, name, role, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

            if (!$user) {
            $error = 'Session expired. Please enter your email again.';
        } else {
                // Prevent resetting to the same password value as currently stored
                $currentHash = $user['password'] ?? '';
                if ($currentHash && passwordMatches($password, $currentHash)) {
                    $error = 'New password must be different from the current password.';
                    $step  = 'set_password';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd  = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$hash, $user['id']]);

                    logAuditEvent((int)$user['id'], $user['name'], $email, $user['role'], 'password_reset');
                    unset($_SESSION['fp_email']);

                    header('Location: ' . BASE_URL . '/auth/login.php?reset=1');
                    exit;
                }
        }
    }
} elseif (($_SESSION['fp_email'] ?? '') !== '') {
    // Re-showing the page after a failed step-2 submit — stay on step 2.
    $step = 'set_password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sb-split">
    <?php require_once __DIR__ . '/../includes/auth_brand_panel.php'; ?>
    <div class="sb-split-form">
    <div class="sb-auth-card">

        <div class="sb-auth-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="sb-auth-logo-img">
            <span class="sb-auth-logo-text">SonoBand</span>
        </div>
        <div class="sb-auth-sub">
            <?= $step === 'set_password' ? 'Set a new password' : 'Find your account' ?>
        </div>

        <?php if ($error): ?>
            <div class="sb-alert sb-alert-danger">
                <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'set_password'): ?>
            <form method="POST" novalidate>
                <input type="hidden" name="step" value="set_password">
                <div class="mb-3">
                    <label class="sb-label">New Password</label>
                    <input type="password" name="password" id="fpPassword" class="form-control sb-input"
                           placeholder="••••••••" data-pw-input="fpPwChecklist" required>
                    <?php renderPwChecklist('fpPwChecklist'); ?>
                </div>
                <div class="mb-4">
                    <label class="sb-label">Confirm New Password</label>
                    <input type="password" name="confirm" class="form-control sb-input"
                           placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn sb-btn-primary w-100" style="padding:8px">
                    <i class="bi bi-key"></i> Update Password
                </button>
            </form>
        <?php else: ?>
            <form method="POST" novalidate>
                <input type="hidden" name="step" value="find_email">
                <div class="mb-4">
                    <label class="sb-label">Email Address</label>
                    <input type="email" name="email" class="form-control sb-input"
                           placeholder="user@gmail.com" pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <button type="submit" class="btn sb-btn-primary w-100" style="padding:8px">
                    <i class="bi bi-search"></i> Continue
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--muted)">
            <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--accent)">Back to sign in</a>
        </div>

    </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
