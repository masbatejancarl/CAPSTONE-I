<?php
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
        $stmt = db()->prepare("SELECT id, name, password, role, avatar_url FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && passwordMatches($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
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
    <?php renderFavicon(); ?>
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
    <div class="sb-auth-card bootstrap">

        <!-- Logo -->
        <div class="sb-auth-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo-optimized.png" alt="SonoBand" class="sb-auth-logo-img">
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
                <label class="sb-label" for="loginEmail">Email Address</label>
                <input type="email" name="email" id="loginEmail" autocomplete="username" class="form-control sb-input"
                       placeholder="user@gmail.com" pattern="<?= EMAIL_PATTERN_ATTR ?>" title="<?= EMAIL_PATTERN_TITLE ?>"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="sb-label" for="loginPassword">Password</label>
                    <a href="<?= BASE_URL ?>/auth/forgot_password.php" style="font-size:11.5px;color:var(--accent)">Forgot password?</a>
                </div>
                <input type="password" name="password" id="loginPassword" autocomplete="current-password" class="form-control sb-input"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary sb-btn-primary w-100" style="padding:8px">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <!-- Onboarding inline (inside auth card) -->
        <div class="sb-onboard sb-onboard-in-card">
            <div class="d-flex justify-content-between align-items-center">
                <div style="display:flex;gap:10px;align-items:center">
                    <i class="bi bi-compass-fill" style="font-size:18px;color:var(--accent)"></i>
                    <div>
                        <strong style="margin:0;font-size:14px;color:var(--text)">New here?</strong>
                        <div class="muted" style="margin-top:2px;font-size:13px">Quick tour: create account → view dashboard → see alerts</div>
                    </div>
                </div>
                <div>
                    <button id="startOnboard" class="btn btn-outline-primary btn-sm">Start Tour</button>
                </div>
            </div>
        </div>

        <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--muted)">
            No account?
            <a href="register.php" style="color:var(--accent)">Register here</a>
        </div>

    </div>
    </div>
</div>

<!-- Bootstrap JS for modal (used for quick onboarding modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<!-- standalone onboarding box removed (kept modal and script) -->

<!-- Modal (two-step, with "Don't show again") -->
<div class="modal fade" id="onboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Welcome to SonoBand - Quick Tour</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="onboard-step" data-step="1">
                    <p><strong>Onboarding content is displayed</strong></p>
                    <p style="margin-bottom:8px;color:var(--text)">Launch the web application as a new user and observe the onboarding flow which highlights core SonoBand features.</p>
                    <div style="font-size:13px;color:var(--muted)">Expected: The application displays guidance about core SonoBand features.</div>
                </div>

                <div class="onboard-step d-none" data-step="2">
                    <p><strong>Onboarding directs to Authentication</strong></p>
                    <p style="margin-bottom:8px;color:var(--text)">Complete or skip the onboarding flow to be directed to the Authentication page.</p>
                    <div style="font-size:13px;color:var(--muted)">Expected: The system directs the user to the Authentication page.</div>
                </div>

                <div class="onboard-step d-none" data-step="3">
                    <p><strong>Onboarding can be completed</strong></p>
                    <p style="margin-bottom:8px;color:var(--text)">Move through the onboarding content until the final step to complete the flow.</p>
                    <div style="font-size:13px;color:var(--muted)">Expected: The user can complete the onboarding flow without errors.</div>
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-between">
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" id="dontShowOnboard" />
                    <label for="dontShowOnboard" style="margin:0;font-size:13px;color:var(--muted)">Don't show again</label>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary me-2" id="onboardPrev" style="display:none">Prev</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="register.php" class="btn btn-primary ms-2">Create Account</a>
                    <button type="button" class="btn btn-primary ms-2" id="onboardNext">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){
        const OPT_KEY = 'onboardDontShow';
        const ONCE_KEY = 'onboardShownOnce';

        const btn = document.getElementById('startOnboard');
        const modalEl = document.getElementById('onboardModal');
        const nextBtn = document.getElementById('onboardNext');
        const prevBtn = document.getElementById('onboardPrev');
        const dontShow = document.getElementById('dontShowOnboard');
        let currentStep = 1;

        function isOptOutActive() {
            return localStorage.getItem(OPT_KEY) === '1';
        }

        function setOptOut() {
            localStorage.setItem(OPT_KEY, '1');
        }

        function clearOptOut() {
            localStorage.removeItem(OPT_KEY);
        }

        function syncTourButton() {
            if (!btn) return;
            const disabled = isOptOutActive();
            btn.disabled = disabled;
            btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            btn.title = disabled ? 'You chose not to show the tour again' : 'Start the SonoBand tour';
        }

        function showStep(step) {
            modalEl.querySelectorAll('.onboard-step').forEach(el => {
                el.classList.toggle('d-none', parseInt(el.dataset.step,10) !== step);
            });
            prevBtn.style.display = step === 1 ? 'none' : 'inline-block';
            nextBtn.textContent = step === 3 ? 'Done' : 'Next';
        }

        if (modalEl) {
            const bsModal = new bootstrap.Modal(modalEl);

            syncTourButton();

            // Manual open is blocked after the user chooses not to show the tour again.
            if (btn) btn.addEventListener('click', () => {
                if (isOptOutActive()) return;
                if (dontShow) dontShow.checked = isOptOutActive();
                currentStep = 1; showStep(currentStep); bsModal.show();
            });

            // Auto-show logic: ?tour=1 bypasses opt-out; otherwise auto-show once unless opted out
            if (location.search.indexOf('tour=1') !== -1) {
                if (!isOptOutActive()) { currentStep = 1; showStep(currentStep); bsModal.show(); }
            } else if (!isOptOutActive() && localStorage.getItem(ONCE_KEY) !== '1') {
                currentStep = 1; showStep(currentStep); bsModal.show();
                localStorage.setItem(ONCE_KEY, '1');
            }

            nextBtn.addEventListener('click', () => {
                if (currentStep < 3) { currentStep++; showStep(currentStep); }
                else { bsModal.hide(); }
            });
            prevBtn.addEventListener('click', () => { if (currentStep > 1) { currentStep--; showStep(currentStep); } });

            // Persist opt-out when the tour is skipped.
            modalEl.addEventListener('hidden.bs.modal', () => {
                if (!dontShow) return;
                if (dontShow.checked) {
                    setOptOut();
                    syncTourButton();
                } else {
                    // user unchecked - clear opt-out
                    clearOptOut();
                    syncTourButton();
                }
            });
        }
    })();
</script>
</body>
</html>