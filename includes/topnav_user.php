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
                    ?>
                        <div class="fb-notif-item <?= $n['status'] === 'unread' ? 'unread' : '' ?>" data-alert-id="<?= $n['id'] ?>">
                            <div class="fb-notif-icon">
                                <i class="bi <?= $notifIcons[$n['sound_type']] ?? 'bi-volume-up-fill' ?>"></i>
                            </div>
                            <div class="fb-notif-text">
                                <div class="fb-notif-title">
                                    <?= htmlspecialchars($n['sound_type'] ?: 'Sound Detected') ?>
                                    <?php if ($dir): ?>
                                        <span class="fb-notif-dir">
                                            <i class="bi <?= directionIcon($dir) ?>"></i>
                                            <?= $dir === 'both' ? 'Both sides' : ucfirst($dir) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div><?= $n['location'] ? ucfirst($n['location']) . ' · ' : '' ?><?= $n['decibel_level'] ? number_format($n['decibel_level'], 1) . ' dB' : '—' ?></div>
                                <div class="fb-notif-time"><?= date('M d, Y H:i', strtotime($n['detected_at'])) ?></div>
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
    const dirIcons = { left: 'bi-arrow-left-circle-fill', right: 'bi-arrow-right-circle-fill', both: 'bi-arrows-angle-expand' };
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
        list.innerHTML = data.notifications.map(n => {
            const dirLabel = n.direction === 'both' ? 'Both sides' : (n.direction ? n.direction.charAt(0).toUpperCase() + n.direction.slice(1) : '');
            const dirTag = n.direction
                ? ` <span class="fb-notif-dir"><i class="bi ${dirIcons[n.direction] || ''}"></i> ${dirLabel}</span>`
                : '';
            return `<div class="fb-notif-item ${n.status === 'unread' ? 'unread' : ''}" data-alert-id="${n.id}">
                <div class="fb-notif-icon"><i class="bi ${n.icon}"></i></div>
                <div class="fb-notif-text">
                    <div class="fb-notif-title">${n.sound_type || 'Sound Detected'}${dirTag}</div>
                    <div>${n.location ? n.location.charAt(0).toUpperCase() + n.location.slice(1) + ' · ' : ''}${n.decibel}</div>
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
                    new Notification('SonoBand Alert', { body: data.notifications[0].sound_type });
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
