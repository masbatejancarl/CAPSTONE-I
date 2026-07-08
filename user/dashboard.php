<?php
define('BASE_URL', '/CAPSTONE I');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];

$lastAlert = db()->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY detected_at DESC LIMIT 1");
$lastAlert->execute([$uid]);
$lastAlert = $lastAlert->fetch();

$unreadToday = (int)db()->query(
    "SELECT COUNT(*) FROM alerts WHERE user_id=$uid AND status='unread' AND detected_at::date = CURRENT_DATE"
)->fetchColumn();

$statusIcons = [
    'Doorbell'        => 'bi-bell-fill',
    'Fire Alarm'      => 'bi-fire',
    'Vehicle Horn'    => 'bi-car-front-fill',
    'Emergency Siren' => 'bi-exclamation-triangle-fill',
    'Loud Knock'      => 'bi-hand-index-thumb-fill',
];

function sbTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' day' . (floor($diff / 86400) == 1 ? '' : 's') . ' ago';
}

$features = [
    [
        'title' => 'Real-Time Sound Detection',
        'desc'  => 'SonoBand continuously listens to your surroundings and recognizes critical sounds — doorbells, fire alarms, vehicle horns, emergency sirens, and loud knocks — the instant they happen.',
        'image' => 'feature-detection.svg',
        'alt'   => 'SonoBand detecting sound in real time',
    ],
    [
        'title' => 'Instant Vibration & Visual Alerts',
        'desc'  => 'The moment a sound is detected, SonoBand turns it into a clear vibration pattern and a visual notification, so you never miss a moment that matters.',
        'image' => 'feature-alerts.svg',
        'alt'   => 'SonoBand alert notification on wrist',
    ],
    [
        'title' => 'Built for Everyday Life',
        'desc'  => 'Lightweight and comfortable for all-day wear, indoors or outdoors. Every alert is saved to your account so you can review your alert history anytime.',
        'image' => 'feature-lifestyle.svg',
        'alt'   => 'SonoBand worn in everyday life',
    ],
];

$developers = [
    ['name' => 'Amira Singin',   'role' => 'Developer', 'email' => 'amirasingin@gmail.com', 'image' => 'developer1.jfif'],
    ['name' => 'John Lloyd Christopher Tadipa',   'role' => 'Developer', 'email' => 'johnlloydtadipa@gmail.com', 'image' => 'developer2.jfif'],
    ['name' => 'Jan Carl Masbate', 'role' => 'Developer', 'email' => 'jancarlmasbate@gmail.com', 'image' => 'developer3.jfif'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<?php require_once __DIR__ . '/../includes/topnav_user.php'; ?>
<div class="fb-content">

    <div class="fb-status-strip">
        <div class="fb-status-live">
            <span class="fb-status-dot"></span> Listening for sounds
        </div>
        <div class="fb-status-divider"></div>
        <?php if ($lastAlert): ?>
        <a href="alerts.php" class="fb-status-recent">
            <i class="bi <?= $statusIcons[$lastAlert['sound_type']] ?? 'bi-volume-up-fill' ?>"></i>
            Last detected: <strong><?= htmlspecialchars($lastAlert['sound_type']) ?></strong>
            · <?= sbTimeAgo($lastAlert['detected_at']) ?>
        </a>
        <?php else: ?>
        <div class="fb-status-recent">
            <i class="bi bi-check-circle"></i> All quiet — no alerts detected yet
        </div>
        <?php endif; ?>
        <?php if ($unreadToday > 0): ?>
        <a href="alerts.php" class="fb-status-badge"><?= $unreadToday ?> unread today</a>
        <?php endif; ?>
    </div>

    <section class="fb-hero">
        <div class="fb-hero-text">
            <div class="fb-hero-eyebrow">Welcome back, <?= htmlspecialchars($user['name']) ?></div>
            <h1 class="fb-hero-title">SonoBand</h1>
            <p class="fb-hero-desc">
                A wearable sound-alert companion designed for the hearing-impaired community.
                SonoBand listens to the world around you and turns critical sounds into
                instant alerts you can feel and see.
            </p>
        </div>
        <div class="fb-hero-media">
            <div class="fb-media">
                <img src="<?= BASE_URL ?>/assets/images/hero-sonoband.svg"
                     alt="SonoBand smartwatch overview"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="fb-media-fallback">
                    <i class="bi bi-image"></i>
                    <span>hero-sonoband.svg</span>
                </div>
            </div>
        </div>
    </section>

    <?php foreach ($features as $i => $f): ?>
    <section class="fb-feature <?= $i % 2 === 1 ? 'reverse' : '' ?>">
        <div class="fb-feature-media">
            <div class="fb-media">
                <img src="<?= BASE_URL ?>/assets/images/<?= $f['image'] ?>"
                     alt="<?= htmlspecialchars($f['alt']) ?>"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="fb-media-fallback">
                    <i class="bi bi-image"></i>
                    <span><?= $f['image'] ?></span>
                </div>
            </div>
        </div>
        <div class="fb-feature-text">
            <h2><?= htmlspecialchars($f['title']) ?></h2>
            <p><?= htmlspecialchars($f['desc']) ?></p>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="fb-contact">
        <h2 class="fb-contact-title">Contact Us</h2>
        <p class="fb-contact-sub">Have questions or feedback? Reach out to the team behind SonoBand.</p>
        <div class="fb-contact-grid">
            <?php foreach ($developers as $dev): ?>
            <div class="fb-dev-card">
                <div class="fb-dev-avatar">
                    <img src="<?= BASE_URL ?>/assets/images/<?= $dev['image'] ?>"
                         alt="<?= htmlspecialchars($dev['name']) ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="fb-dev-avatar-fallback"><i class="bi bi-person-fill"></i></div>
                </div>
                <div class="fb-dev-name"><?= htmlspecialchars($dev['name']) ?></div>
                <div class="fb-dev-role"><?= htmlspecialchars($dev['role']) ?></div>
                <a href="mailto:<?= htmlspecialchars($dev['email']) ?>" class="fb-dev-email">
                    <i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($dev['email']) ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <footer class="fb-footer">
        <div class="fb-footer-brand">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SonoBand" class="fb-footer-brand-logo"> SonoBand
        </div>
        <div class="fb-footer-tagline">IoT Sound Alert System for the Hearing Impaired</div>
        <nav class="fb-footer-links">
            <a href="<?= BASE_URL ?>/user/dashboard.php"><i class="bi bi-house-door-fill"></i> Dashboard</a>
            <a href="<?= BASE_URL ?>/user/alerts.php"><i class="bi bi-bell-fill"></i> Alert History</a>
            <a href="<?= BASE_URL ?>/user/settings.php"><i class="bi bi-gear-fill"></i> Account Settings</a>
        </nav>
        <div class="fb-footer-bottom">
            <div class="fb-footer-copy">&copy; <?= date('Y') ?> SonoBand. All rights reserved.</div>
        </div>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
