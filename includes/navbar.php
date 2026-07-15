<?php
$user = currentUser();
$role = $user['role'];

$navLinks = [
    'user' => [
        ['href' => BASE_URL . '/user/dashboard.php', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
        ['href' => BASE_URL . '/user/alerts.php',    'icon' => 'bi-bell',           'label' => 'Alert History'],
    ],
    'admin' => [
        ['href' => BASE_URL . '/admin/dashboard.php', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
        ['href' => BASE_URL . '/admin/users.php',     'icon' => 'bi-people',         'label' => 'Users'],
        ['href' => BASE_URL . '/admin/alerts.php',     'icon' => 'bi-bell',           'label' => 'Alerts'],
        ['href' => BASE_URL . '/admin/audit_logs.php', 'icon' => 'bi-clock-history',  'label' => 'Audit Logs'],
        ['href' => BASE_URL . '/admin/settings.php',   'icon' => 'bi-gear-fill',      'label' => 'Account Settings'],
    ],
    'caregiver' => [
        ['href' => BASE_URL . '/caregiver/dashboard.php', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
        ['href' => BASE_URL . '/caregiver/alerts.php',    'icon' => 'bi-bell',          'label' => 'Alert History'],
        ['href' => BASE_URL . '/caregiver/settings.php',  'icon' => 'bi-gear-fill',     'label' => 'Account Settings'],
    ],
];

$links       = $navLinks[$role] ?? [];
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile topbar -->
<div class="sb-topbar">
    <button class="sb-topbar-toggle" onclick="sbToggle()" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="sb-topbar-brand">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="sb-brand-logo sb-brand-logo-sm">
        SonoBand
    </span>
    <div class="ms-auto">
        <div class="sb-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    </div>
</div>

<!-- Overlay -->
<div class="sb-overlay" id="sbOverlay" onclick="sbClose()"></div>

<!-- Sidebar -->
<aside class="sb-sidebar" id="sbSidebar">

    <!-- Brand -->
    <div class="sb-sidebar-brand">
        <div class="sb-brand-text">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="sb-brand-logo">
            SonoBand
        </div>
        <button class="sb-sidebar-close-btn" onclick="sbClose()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- User -->
    <div class="sb-sidebar-user">
        <div class="sb-avatar sb-avatar-md"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div style="min-width:0">
            <div class="sb-sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="sb-sidebar-user-role"><?= ucfirst($role) ?></div>
        </div>
    </div>

    <!-- Nav links -->
    <nav class="sb-sidebar-nav">
        <div class="sb-nav-label">Menu</div>
        <?php foreach ($links as $link): ?>
        <a href="<?= $link['href'] ?>"
           class="sb-nav-link <?= basename($link['href']) === $currentPage ? 'active' : '' ?>">
            <i class="bi <?= $link['icon'] ?>"></i>
            <span><?= $link['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="sb-sidebar-footer">
        <a href="<?= BASE_URL ?>/auth/logout.php" class="sb-logout-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
        </a>
    </div>

</aside>

<script>
function sbToggle() {
    document.getElementById('sbSidebar').classList.toggle('open');
    document.getElementById('sbOverlay').classList.toggle('show');
}
function sbClose() {
    document.getElementById('sbSidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
}
</script>
