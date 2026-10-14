<?php
$user = currentUser();
$uid  = (int)$user['id'];

$ustmt = db()->prepare("SELECT email FROM users WHERE id = ?");
$ustmt->execute([$uid]);
$user['email'] = $ustmt->fetchColumn() ?: '';

$ucstmt = db()->prepare(
    "SELECT COUNT(*) FROM alerts WHERE user_id=? AND status='unread'
     AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date"
);
$ucstmt->execute([$uid]);
$unread = (int)$ucstmt->fetchColumn();

$nstmt = db()->prepare(
    "SELECT * FROM alerts WHERE user_id=?
     AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date
     ORDER BY detected_at DESC LIMIT 5"
);
$nstmt->execute([$uid]);
$notifications = $nstmt->fetchAll();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header class="fb-nav role-user">
    <a href="<?= BASE_URL ?>/user/dashboard.php" class="fb-nav-brand">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="fb-nav-brand-logo">
        <span>SonoBand</span>
    </a>

    <nav class="fb-nav-center">
        <a href="<?= BASE_URL ?>/user/dashboard.php"
           class="fb-nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
           title="Dashboard" id="fbHomeLink">
            <span>Overview</span>
        </a>

        <button type="button" class="fb-nav-item" id="fbNotifBtn" title="Notifications">
            <span>Alerts</span>
            <span class="fb-badge" id="fbNotifBadge" <?= $unread > 0 ? '' : 'style="display:none"' ?>><?= $unread > 99 ? '99+' : $unread ?></span>
            <div class="fb-dropdown fb-notif-panel" id="fbNotifDropdown">
                <div class="fb-dropdown-header"><div class="name">Notifications — Today</div></div>
                <div id="fbNotifList">
                <?php if (empty($notifications)): ?>
                    <div class="fb-notif-empty">
                        <i class="bi bi-inbox" style="font-size:20px;display:block;margin-bottom:6px"></i>
                        No alerts today.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n):
                        $dir = alertDirection($n['metadata'] ?? null);
                        $soundType = soundTypeLabel($n['sound_type'] ?? null);
                    ?>
                        <div class="fb-notif-item <?= $n['status'] === 'unread' ? 'unread' : '' ?>" data-alert-id="<?= $n['id'] ?>">
                            <div class="fb-notif-icon">
                                <i class="bi <?= directionIcon($dir) ?>"></i>
                            </div>
                            <div class="fb-notif-text">
                                <div class="fb-notif-title"><?= htmlspecialchars(directionLabel($dir)) ?></div>
                                <div><?= htmlspecialchars($soundType) ?></div>
                                <div><?= htmlspecialchars(alertTypeLabel(alertSeverity($n['alert_type'] ?? null))) ?></div>
                                <div class="fb-notif-time"><?= toManilaTime($n['detected_at'], 'M d, Y H:i') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>/user/alerts.php" class="fb-dropdown-footer">View All Alerts</a>
            </div>
        </button>
    </nav>

    <div class="fb-nav-right">
        <div class="fb-nav-account" id="fbAccountBtn">
            <?= renderAvatar($user['name'], $user['avatar_url'] ?? null, 'sb-avatar-44') ?>
            <i class="bi bi-caret-down-fill" style="font-size:9px;color:var(--muted)"></i>
            <div class="fb-dropdown" id="fbAccountDropdown">
                <a href="<?= BASE_URL ?>/user/profile.php" class="fb-dropdown-header fb-dropdown-header-link">
                    <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                </a>
                <a href="<?= BASE_URL ?>/user/settings.php" class="fb-dropdown-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear-fill"></i> Account Settings
                </a>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="fb-dropdown-item danger">
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
    toggle('fbNotifBtn', 'fbNotifDropdown', ['fbAccountDropdown']);
    document.addEventListener('click', () => {
        document.getElementById('fbAccountDropdown')?.classList.remove('show');
        document.getElementById('fbNotifDropdown')?.classList.remove('show');
    });

    // Home: navigate immediately on hover, no click needed.
    const homeLink = document.getElementById('fbHomeLink');
    if (homeLink) {
        homeLink.addEventListener('mouseenter', () => { window.location.href = homeLink.href; });
    }

    const notifDropdown = document.getElementById('fbNotifDropdown');
    // The dropdown is nested inside the notification button, so clicks inside it
    // (e.g. marking a notification as read) must not bubble up and re-toggle/close it.
    notifDropdown?.addEventListener('click', (e) => e.stopPropagation());

    // Poll for new alerts so a sound detected on the device shows up here
    // without the user having to reload the page.
    const dirIcons = { left: 'bi-arrow-left-circle-fill', right: 'bi-arrow-right-circle-fill' };
    const badge = document.getElementById('fbNotifBadge');
    const list  = document.getElementById('fbNotifList');
    let lastSeenId = null;

    function renderNotifications(data) {
        if (badge) {
            if (data.unread_count > 0) {
                badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
        if (!list) return;
        if (!data.notifications.length) {
            list.innerHTML = '<div class="fb-notif-empty"><i class="bi bi-inbox" style="font-size:20px;display:block;margin-bottom:6px"></i>No alerts today.</div>';
            return;
        }
        const dirLabels = {
            left: 'Sound Detected Left',
            right: 'Sound Detected Right',
        };
        list.innerHTML = data.notifications.map(n => {
            const icon  = dirIcons[n.direction] || 'bi-bell-fill';
            const label = dirLabels[n.direction] || 'Sound Detected';
            const soundType = n.sound_type || 'Detected Sound';
            const alertType = n.alert_type ? n.alert_type.charAt(0).toUpperCase() + n.alert_type.slice(1) : 'Medium';
            return `<div class="fb-notif-item ${n.status === 'unread' ? 'unread' : ''}" data-alert-id="${n.id}">
                <div class="fb-notif-icon"><i class="bi ${icon}"></i></div>
                <div class="fb-notif-text">
                    <div class="fb-notif-title">${label}</div>
                    <div>${soundType}</div>
                    <div>${alertType}</div>
                    <div class="fb-notif-time">${n.detected_at}</div>
                </div>
            </div>`;
        }).join('');
    }

    // Mark a notification as read on click, without closing the dropdown.
    if (list) {
        list.addEventListener('click', (e) => {
            const item = e.target.closest('[data-alert-id]');
            if (!item || !item.classList.contains('unread')) return;

            const id = item.dataset.alertId;
            fetch(`${window.BASE_URL}/api/mark_read.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            }).then(res => {
                if (!res.ok) return;
                item.classList.remove('unread');
                if (badge) {
                    const remaining = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
                    if (remaining > 0) {
                        badge.textContent = remaining > 99 ? '99+' : remaining;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        });
    }

    function pollNotifications() {
        fetch(`${window.BASE_URL}/api/get_notifications.php`)
            .then(res => res.ok ? res.json() : null)
            .then(data => {
                if (!data) return;
                const newestId = data.notifications[0]?.id ?? null;
                if (lastSeenId !== null && newestId !== null && newestId !== lastSeenId && Notification?.permission === 'granted') {
                    const dirLabels = {
                        left: 'Sound Detected Left',
                        right: 'Sound Detected Right',
                        both: 'Sound Detected Both Sides',
                    };
                    new Notification('SonoBand Alert', { body: dirLabels[data.notifications[0].direction] || 'Sound Detected' });
                }
                lastSeenId = newestId;
                renderNotifications(data);
            })
            .catch(() => {});
    }

    if (badge || list) {
        pollNotifications();
        setInterval(pollNotifications, 5000);
    }
})();
</script>
