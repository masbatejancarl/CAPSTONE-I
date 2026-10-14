<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('user');

$user = currentUser();
$uid  = (int)$user['id'];

$lastAlert = db()->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY detected_at DESC LIMIT 1");
$lastAlert->execute([$uid]);
$lastAlert = $lastAlert->fetch();

$utstmt = db()->prepare(
    "SELECT COUNT(*) FROM alerts WHERE user_id=? AND status='unread'
     AND (detected_at " . MANILA_SQL . ")::date = (NOW() AT TIME ZONE 'Asia/Manila')::date"
);
$utstmt->execute([$uid]);
$unreadToday = (int)$utstmt->fetchColumn();

$lastAlertDirection = $lastAlert ? alertDirection($lastAlert['metadata'] ?? null) : null;

function sbTimeAgo(string $datetime): string {
    // detected_at is stored as UTC-naive; parse it as UTC explicitly instead of
    // relying on the server's default timezone (strtotime() would otherwise
    // misinterpret it, skewing the computed diff by hours).
    $eventTime = (new DateTime($datetime, new DateTimeZone('UTC')))->getTimestamp();
    $diff = time() - $eventTime;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins == 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hrs = floor($diff / 3600);
        return $hrs . ' hr' . ($hrs == 1 ? '' : 's') . ' ago';
    }
    $days = floor($diff / 86400);
    return $days . ' day' . ($days == 1 ? '' : 's') . ' ago';
}

$features = [
    [
        'title' => 'Real-Time Sound Detection',
        'desc'  =>'SonoBand continuously monitors your surroundings and detects important sounds such as doorbells, fire alarms, vehicle horns, emergency sirens, and loud knocks, then alerts you as soon as they occur.',
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
        'desc'  => 'Designed for everyday use indoors or outdoors. Every alert is saved to your account so you can review your alert history anytime.',
        'image' => 'feature-lifestyle.svg',
        'alt'   => 'SonoBand worn in everyday life',
    ],
];

$developers = [
    ['name' => 'Amira Singin',   'role' => 'Developer', 'email' => 'amirasingin@gmail.com', 'image' => 'developer1.jfif'],
    ['name' => 'John Lloyd Christopher Tadipa',   'role' => 'Developer', 'email' => 'johnlloydtadipa@gmail.com', 'image' => 'developer2.jfif'],
    ['name' => 'Jan Carl Masbate', 'role' => 'Developer', 'email' => 'jancarlmasbate@gmail.com', 'image' => 'developer3.jfif'],
];

$howItWorks = [
    ['title' => 'Listen',        'desc' => "SonoBand continuously monitors ambient sound through its built-in microphone."],
    ['title' => 'Detect & Locate', 'desc' => "When a critical sound is picked up, SonoBand identifies which side it came from left or right."],
    ['title' => 'Alert',         'desc' => "You feel it instantly through vibration and see it through a visual cue on the band."],
    ['title' => 'Review',        'desc' => "Every alert is saved to your account, so you can check your Alert History anytime."],
];

$specs = [
    ['icon' => 'bi-battery-charging', 'label' => 'Rechargeable Battery'],
    ['icon' => 'bi-bell-fill',        'label' => 'Instant Vibration + Visual Alerts'],
    ['icon' => 'bi-compass-fill',     'label' => 'Left / Right Sound Direction'],
    ['icon' => 'bi-cloud-check-fill', 'label' => 'Synced Alert History'],
    ['icon' => 'bi-house-check-fill', 'label' => 'Indoor & Outdoor Use'],
];

$faqs = [
    [
        'q' => 'What kinds of sounds does SonoBand detect?',
        'a' => 'SonoBand listens for critical everyday sounds like doorbells, fire alarms, vehicle horns, emergency sirens, and loud knocks, then alerts you as soon as a sound is detected.',
    ],
    [
        'q' => 'How will I know when SonoBand detects something?',
        'a' => "You'll feel a vibration and see a visual alert on the band immediately, and it's also saved to your Alert History so you can review it anytime.",
    ],
    [
        'q' => 'Can I tell which direction a sound came from?',
        'a' => 'Yes. SonoBand tells you whether the sound was detected from your left or right side.',
    ],
    [
        'q' => 'Do I need an account to use SonoBand?',
        'a' => 'Yes, signing in lets SonoBand save your alert history securely so you can look back at past detections anytime.',
    ],
    [
        'q' => 'Who is SonoBand designed for?',
        'a' => 'SonoBand was designed for congenitally deaf and non-speaking individuals who want to stay aware of important sounds in their environment.',
    ],
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
            <i class="bi <?= directionIcon($lastAlertDirection) ?>"></i>
            Last detected: <strong><?= htmlspecialchars(directionLabel($lastAlertDirection)) ?></strong>
            · <?= sbTimeAgo($lastAlert['detected_at']) ?>
        </a>
        <?php else: ?>
        <div class="fb-status-recent">
            <i class="bi bi-check-circle"></i> All Quiet — No Alerts Detected Yet
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
                A wearable sound-alert companion designed for congenitally deaf and non-speaking
                individuals. SonoBand listens to the world around you and turns critical sounds into
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

    <div id="sonobandFeatures" class="carousel slide fb-feature-carousel" data-bs-ride="carousel" data-bs-pause="hover">
        <div class="carousel-indicators">
            <?php foreach ($features as $i => $f): ?>
            <button type="button" data-bs-target="#sonobandFeatures" data-bs-slide-to="<?= $i ?>"
                    class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-label="<?= htmlspecialchars($f['title']) ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($features as $i => $f): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <section class="fb-feature">
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
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#sonobandFeatures" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#sonobandFeatures" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <section class="fb-section">
        <h2 class="fb-section-title">How SonoBand Works</h2>
        <p class="fb-section-sub">From the moment a sound happens to the moment you know about it.</p>
        <div class="fb-steps">
            <?php foreach ($howItWorks as $i => $step): ?>
            <div class="fb-step">
                <div class="fb-step-num"><?= $i + 1 ?></div>
                <div class="fb-step-title"><?= htmlspecialchars($step['title']) ?></div>
                <div class="fb-step-desc"><?= htmlspecialchars($step['desc']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fb-section">
        <h2 class="fb-section-title">Who It's For</h2>
        <div class="fb-mission-text">
            <p>SonoBand was built for individuals who are congenitally deaf or non-speaking people who can't rely on hearing sudden, important sounds like a knock at the door, a car horn, or an emergency alarm.</p>
            <p>Instead of missing these moments, SonoBand turns sound into something you can feel and see so you stay aware, safe, and connected to the world around you, wherever you are.</p>
        </div>
    </section>

    <section class="fb-section">
        <h2 class="fb-section-title">Features at a Glance</h2>
        <div class="fb-specs">
            <?php foreach ($specs as $spec): ?>
            <div class="fb-spec-item">
                <i class="bi <?= $spec['icon'] ?>"></i>
                <span class="fb-spec-label"><?= htmlspecialchars($spec['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fb-section">
        <h2 class="fb-section-title">Frequently Asked Questions</h2>
        <div class="accordion fb-faq" id="sonobandFaq">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                            data-bs-toggle="collapse" data-bs-target="#faqItem<?= $i ?>"
                            aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faqItem<?= $i ?>">
                        <?= htmlspecialchars($faq['q']) ?>
                    </button>
                </h3>
                <div id="faqItem<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                     data-bs-parent="#sonobandFaq">
                    <div class="accordion-body"><?= htmlspecialchars($faq['a']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

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
        <div class="fb-footer-copy">&copy; <?= date('Y') ?> SonoBand. All rights reserved.</div>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
