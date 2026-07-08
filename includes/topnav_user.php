<?php
$user = currentUser();
$uid  = (int)$user['id'];

$ustmt = db()->prepare("SELECT email FROM users WHERE id = ?");
$ustmt->execute([$uid]);
$user['email'] = $ustmt->fetchColumn() ?: '';

$unread = (int)db()->query("SELECT COUNT(*) FROM alerts WHERE user_id=$uid AND status='unread' AND detected_at::date = CURRENT_DATE")->fetchColumn();

$nstmt = db()->prepare("SELECT * FROM alerts WHERE user_id=? AND detected_at::date = CURRENT_DATE ORDER BY detected_at DESC LIMIT 5");
$nstmt->execute([$uid]);
$notifications = $nstmt->fetchAll();

$notifIcons = [
    'Doorbell'        => 'bi-bell-fill',
    'Fire Alarm'      => 'bi-fire',
    'Vehicle Horn'    => 'bi-car-front-fill',
    'Emergency Siren' => 'bi-exclamation-triangle-fill',
    'Loud Knock'      => 'bi-hand-index-thumb-fill',
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header class="fb-nav">
    <a href="<?= BASE_URL ?>/user/dashboard.php" class="fb-nav-brand">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="fb-nav-brand-logo">
        <span>SonoBand</span>
    </a>

    <nav class="fb-nav-center">
        <a href="<?= BASE_URL ?>/user/dashboard.php"
           class="fb-nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
           title="Dashboard" id="fbHomeLink">
            <span>Home</span>
        </a>

        <button type="button" class="fb-nav-item" id="fbNotifBtn" title="Notifications">
            <span>Notification</span>
            <?php if ($unread > 0): ?>
                <span class="fb-badge"><?= $unread > 99 ? '99+' : $unread ?></span>
            <?php endif; ?>
            <div class="fb-dropdown fb-notif-panel" id="fbNotifDropdown">
                <div class="fb-dropdown-header"><div class="name">Notifications — Today</div></div>
                <?php if (empty($notifications)): ?>
                    <div class="fb-notif-empty">
                        <i class="bi bi-inbox" style="font-size:20px;display:block;margin-bottom:6px"></i>
                        No alerts today.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="fb-notif-item <?= $n['status'] === 'unread' ? 'unread' : '' ?>">
                            <div class="fb-notif-icon">
                                <i class="bi <?= $notifIcons[$n['sound_type']] ?? 'bi-volume-up-fill' ?>"></i>
                            </div>
                            <div class="fb-notif-text">
                                <div class="fb-notif-title"><?= htmlspecialchars($n['sound_type']) ?></div>
                                <div><?= ucfirst($n['location']) ?> · <?= $n['decibel_level'] ? number_format($n['decibel_level'], 1) . ' dB' : '—' ?></div>
                                <div class="fb-notif-time"><?= date('M d, Y H:i', strtotime($n['detected_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/user/alerts.php" class="fb-dropdown-footer">View All Alerts</a>
            </div>
        </button>
    </nav>

    <div class="fb-nav-right">
        <div class="fb-nav-account" id="fbAccountBtn">
            <div class="sb-avatar sb-avatar-md"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <i class="bi bi-caret-down-fill" style="font-size:9px;color:var(--muted)"></i>
            <div class="fb-dropdown" id="fbAccountDropdown">
                <a href="<?= BASE_URL ?>/user/profile.php" class="fb-dropdown-header fb-dropdown-header-link">
                    <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                </a>
                <a href="<?= BASE_URL ?>/user/settings.php" class="fb-dropdown-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear-fill"></i> Account Settings
                </a>
                <a href="<?= BASE_URL ?>/logout.php" class="fb-dropdown-item danger">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    function toggle(btnId, dropdownId, others) {
        const btn = document.getElementById(btnId);
        const dd  = document.getElementById(dropdownId);
        if (!btn || !dd) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            others.forEach(id => document.getElementById(id)?.classList.remove('show'));
            dd.classList.toggle('show');
        });
    }
    toggle('fbAccountBtn', 'fbAccountDropdown', ['fbNotifDropdown']);
    document.addEventListener('click', () => {
        document.getElementById('fbAccountDropdown')?.classList.remove('show');
    });

    // Home: navigate immediately on hover, no click needed.
    const homeLink = document.getElementById('fbHomeLink');
    if (homeLink) {
        homeLink.addEventListener('mouseenter', () => { window.location.href = homeLink.href; });
    }

    // Notification: open the dropdown on hover, close when the pointer leaves.
    const notifBtn = document.getElementById('fbNotifBtn');
    const notifDropdown = document.getElementById('fbNotifDropdown');
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('mouseenter', () => {
            document.getElementById('fbAccountDropdown')?.classList.remove('show');
            notifDropdown.classList.add('show');
        });
        notifBtn.addEventListener('mouseleave', () => {
            notifDropdown.classList.remove('show');
        });
    }
})();
</script>
