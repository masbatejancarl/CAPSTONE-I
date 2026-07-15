<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) { redirectByRole(); }

$error   = '';
$success = isset($_GET['registered']) ? 'Account created. You may now sign in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';
    $confirm  =      $_POST['confirm']  ?? '';
    $role     = 'user';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!isValidName($name)) {
        $error = namePolicyMessage();
    } elseif (!isGmailAddress($email)) {
        $error = emailPolicyMessage();
    } elseif (!isStrongPassword($password)) {
        $error = passwordPolicyMessage();
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $error = 'Email already registered.';
        } else {
            $hash = isProbablyHashedPassword($password)
                ? $password
                : password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hash, $role])) {
                header('Location: register.php?registered=1');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="sb-split">
    <?php require_once __DIR__ . '/../includes/auth_brand_panel.php'; ?>
    <div class="sb-split-form">
    <div class="sb-auth-card" style="max-width:430px">

        <div class="sb-auth-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="sb-auth-logo-img">
            <span class="sb-auth-logo-text">SonoBand</span>
        </div>
        <div class="sb-auth-sub">Create a new account</div>

        <?php if ($error): ?>
            <div class="sb-alert sb-alert-danger">
                <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="sb-alert sb-alert-success sb-alert-countdown-3s" id="regSuccessAlert">
                <i class="bi bi-check-circle flex-shrink-0"></i>
                <span><?= htmlspecialchars($success) ?> Redirecting to sign in in <span id="regCountdown">3</span>…</span>
            </div>
            <script>
            (function () {
                let n = 3;
                const numEl = document.getElementById('regCountdown');
                const timer = setInterval(() => {
                    n--;
                    if (numEl) numEl.textContent = Math.max(n, 0);
                    if (n <= 0) {
                        clearInterval(timer);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            })();
            </script>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="sb-label">Full Name</label>
                <input type="text" name="name" class="form-control sb-input"
                       placeholder="Juan Dela Cruz" pattern="<?= NAME_PATTERN_ATTR ?>" title="<?= NAME_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="sb-label">Email Address</label>
                <input type="email" name="email" class="form-control sb-input"
                       placeholder="user@gmail.com" pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="sb-label">Password</label>
                <input type="password" name="password" id="regPassword" class="form-control sb-input"
                       placeholder="••••••••" data-pw-input="regPwChecklist" required>
                <?php renderPwChecklist('regPwChecklist'); ?>
            </div>
            <div class="mb-4">
                <label class="sb-label">Confirm Password</label>
                <input type="password" name="confirm" class="form-control sb-input"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn sb-btn-primary w-100" style="padding:8px">
                <i class="bi bi-person-plus"></i> Create Account
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--muted)">
            Already have an account?
            <a href="login.php" style="color:var(--accent)">Sign in</a>
        </div>
    </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
