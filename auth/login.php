<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) { redirectByRole(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!isGmailAddress($email)) {
        $error = emailPolicyMessage();
    } else {
        $stmt = db()->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && passwordMatches($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            logAuditEvent((int)$user['id'], $user['name'], $email, $user['role'], 'login');
            redirectByRole();
        } else {
            logAuditEvent(
                $user ? (int)$user['id']   : null,
                $user ? $user['name']      : '',
                $email,
                $user ? $user['role']      : '',
                'login_failed'
            );
            $error = 'Invalid credentials. Please check your email and password.';
        }
    }
}

$unauthorized = isset($_GET['error']) && $_GET['error'] === 'unauthorized';
$resetSuccess = isset($_GET['reset']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="sb-split">
    <?php require_once __DIR__ . '/../includes/auth_brand_panel.php'; ?>
    <div class="sb-split-form">
    <div class="sb-auth-card">

        <!-- Logo -->
        <div class="sb-auth-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="sb-auth-logo-img">
            <span class="sb-auth-logo-text">SonoBand</span>
        </div>
        <div class="sb-auth-sub">IoT Sound Alert System for the Congenitally Deaf and Non-Speaking Individuals</div>

        <!-- Errors -->
        <?php if ($error): ?>
            <div class="sb-alert sb-alert-danger">
                <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($unauthorized): ?>
            <div class="sb-alert sb-alert-danger">
                <i class="bi bi-shield-x flex-shrink-0"></i>
                <span>Access denied. You are not authorized to view that page.</span>
            </div>
        <?php endif; ?>
        <?php if ($resetSuccess): ?>
            <div class="sb-alert sb-alert-success">
                <i class="bi bi-check-circle flex-shrink-0"></i>
                <span>Password updated. You may now sign in.</span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="sb-label">Email Address</label>
                <input type="email" name="email" class="form-control sb-input"
                       placeholder="user@gmail.com" pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="sb-label">Password</label>
                    <a href="<?= BASE_URL ?>/auth/forgot_password.php" style="font-size:11.5px;color:var(--accent)">Forgot password?</a>
                </div>
                <input type="password" name="password" class="form-control sb-input"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn sb-btn-primary w-100" style="padding:8px">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--muted)">
            No account?
            <a href="register.php" style="color:var(--accent)">Register here</a>
        </div>

    </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
