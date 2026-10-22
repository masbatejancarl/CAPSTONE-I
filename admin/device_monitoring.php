<?php
define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderFavicon(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Device Monitoring — SonoBand</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
    <style>
        #device-map { min-height: 390px; border-radius: 12px; z-index: 1; }
        .device-monitor-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr); gap: 18px; }
        .device-status-pill { display: inline-flex; align-items: center; gap: 7px; border-radius: 30px; padding: 5px 11px; font-size: 12px; font-weight: 700; }
        .device-status-pill::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .device-status-online { background: rgba(61,172,116,.12); color: #3dac74; }
        .device-status-offline { background: rgba(224,82,82,.12); color: #e05252; }
        .device-status-unknown { background: rgba(130,140,155,.14); color: #8993a2; }
        .device-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .device-detail { min-width: 0; padding: 12px; border-radius: 10px; background: var(--surface-2); }
        .device-detail-label { color: var(--muted); font-size: 11px; margin-bottom: 4px; }
        .device-detail-value { color: var(--text); font-size: 13px; font-weight: 600; overflow-wrap: anywhere; }
        .device-monitor-error { color: var(--danger); }
        @media (max-width: 800px) { .device-monitor-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="sb-layout">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="sb-main">
        <div class="sb-content">
            <div class="sb-page-header">
                <div>
                    <div class="sb-page-title"><i class="bi bi-geo-alt"></i> Live Device Monitoring</div>
                    <div class="sb-page-sub">Refreshes every 15 seconds. A device is online when it is on and was seen within the last minute.</div>
                </div>
                <span class="sb-page-sub" id="device-last-refresh">Loading device status…</span>
            </div>
            <p class="sb-page-sub mb-3">Connection-state audit events are detected while this page is open; brief changes between refreshes may not be recorded.</p>

            <div class="sb-card mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="sb-label" for="device-selector">Select device or user</label>
                        <select class="form-select sb-input" id="device-selector" disabled>
                            <option value="">Loading devices…</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="sb-page-sub" id="device-count">Waiting for device data</div>
                    </div>
                </div>
                <div id="device-error" class="device-monitor-error mt-3" role="alert" hidden></div>
            </div>

            <div class="device-monitor-grid">
                <section class="sb-card" aria-live="polite">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="mb-0" style="font-size:16px;color:var(--text)">Device Status</h2>
                        <span id="device-status" class="device-status-pill device-status-unknown">Waiting</span>
                    </div>
                    <div id="device-empty" class="sb-empty">
                        <i class="bi bi-broadcast"></i>
                        <p id="device-empty-text">Loading connected devices…</p>
                    </div>
                    <div id="device-details" class="device-detail-grid" hidden>
                        <div class="device-detail"><div class="device-detail-label">Device Name</div><div class="device-detail-value" data-field="device_name"></div></div>
                        <div class="device-detail"><div class="device-detail-label">MAC Address</div><div class="device-detail-value" data-field="mac_address"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Connected User</div><div class="device-detail-value" data-field="user_name"></div></div>
                        <div class="device-detail"><div class="device-detail-label">User Email</div><div class="device-detail-value" data-field="email"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Role</div><div class="device-detail-value" data-field="role"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Connection IP</div><div class="device-detail-value" data-field="ip_address"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Last Seen</div><div class="device-detail-value" data-field="last_seen"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Latest Location</div><div class="device-detail-value" data-field="location_address"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Location Updated</div><div class="device-detail-value" data-field="location_updated_at"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Vibration Intensity</div><div class="device-detail-value" data-field="vibration_intensity"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Sensitivity</div><div class="device-detail-value" data-field="sensitivity"></div></div>
                        <div class="device-detail"><div class="device-detail-label">Sound Threshold</div><div class="device-detail-value" data-field="sound_threshold"></div></div>
                    </div>
                </section>

                <section class="sb-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="mb-0" style="font-size:16px;color:var(--text)">Device Location</h2>
                        <small class="sb-page-sub">Address is geocoded by OpenStreetMap Nominatim</small>
                    </div>
                    <div id="device-map" role="application" aria-label="Map showing selected device location"></div>
                    <div id="map-message" class="sb-page-sub mt-2" aria-live="polite">Waiting for location data…</div>
                </section>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const selector = document.getElementById('device-selector');
    const errorBox = document.getElementById('device-error');
    const detailsBox = document.getElementById('device-details');
    const emptyBox = document.getElementById('device-empty');
    const statusBadge = document.getElementById('device-status');
    const mapMessage = document.getElementById('map-message');
    const refreshLabel = document.getElementById('device-last-refresh');
    const geocodeCache = new Map();
    const map = typeof L === 'undefined' ? null : L.map('device-map').setView([12.8797, 121.7740], 5);
    const markerLayer = map ? L.layerGroup().addTo(map) : null;
    let lastGeocodeAt = 0;

    if (map) {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    } else {
        mapMessage.textContent = 'Map library did not load. Device status is still available.';
    }

    let devices = [];
    let selectedId = '';
    let refreshInProgress = false;
    let currentLocationKey = '';

    function setText(field, value) {
        const target = detailsBox.querySelector(`[data-field="${field}"]`);
        if (target) target.textContent = value || 'Not available';
    }

    function renderSelectedDevice() {
        const device = devices.find(item => String(item.id) === String(selectedId));
        if (!device) {
            detailsBox.hidden = true;
            emptyBox.hidden = false;
            document.getElementById('device-empty-text').textContent =
                devices.length ? 'Select a device to view its status.' : 'No devices are registered yet.';
            statusBadge.className = 'device-status-pill device-status-unknown';
            statusBadge.textContent = 'No device selected';
            currentLocationKey = '';
            markerLayer?.clearLayers();
            mapMessage.textContent = map ? 'No device selected.' : 'Map library did not load.';
            return;
        }

        emptyBox.hidden = true;
        detailsBox.hidden = false;
        setText('device_name', device.device_name);
        setText('mac_address', device.mac_address);
        setText('user_name', device.user_name);
        setText('email', device.email);
        setText('role', device.role);
        setText('ip_address', device.ip_address);
        setText('last_seen', device.last_seen || 'Never');
        setText('location_address', device.location_address);
        setText('location_updated_at', device.location_updated_at);
        setText('vibration_intensity', device.vibration_intensity);
        setText('sensitivity', device.sensitivity);
        setText('sound_threshold', device.sound_threshold);

        if (device.online) {
            statusBadge.className = 'device-status-pill device-status-online';
            statusBadge.textContent = 'Online';
        } else {
            statusBadge.className = 'device-status-pill device-status-offline';
            statusBadge.textContent = 'Offline';
        }

        showLocation(device);
    }

    async function showLocation(device) {
        const address = device.location_address || '';
        if (!address) {
            currentLocationKey = '';
            markerLayer?.clearLayers();
            mapMessage.textContent = map
                ? 'No location address has been reported for this user.'
                : 'Map library did not load.';
            return;
        }
        const locationKey = `${device.id}|${address}`;
        if (locationKey === currentLocationKey) return;
        currentLocationKey = locationKey;
        markerLayer?.clearLayers();
        if (!map) return;
        mapMessage.textContent = 'Looking up address for map display…';

        try {
            let result = geocodeCache.get(address);
            if (!result) {
                const wait = Math.max(0, 1100 - (Date.now() - lastGeocodeAt));
                if (wait) await new Promise(resolve => window.setTimeout(resolve, wait));
                const params = new URLSearchParams({ format: 'jsonv2', limit: '1', q: address });
                lastGeocodeAt = Date.now();
                const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`);
                if (!response.ok) throw new Error(`Geocoding service returned HTTP ${response.status}.`);
                const matches = await response.json();
                result = matches[0] || null;
                geocodeCache.set(address, result);
            }
            if (currentLocationKey !== locationKey) return;
            if (!result) {
                mapMessage.textContent = 'The address could not be located on the map.';
                return;
            }
            const point = [Number(result.lat), Number(result.lon)];
            map.setView(point, 16);
            L.marker(point).addTo(markerLayer).bindPopup(
                `<strong>${escapeHtml(device.user_name || 'User')}</strong><br>` +
                `${escapeHtml(device.device_name || 'SonoBand device')}<br>` +
                `Location updated: ${escapeHtml(device.location_updated_at || 'Not available')}<br>` +
                `${escapeHtml(address)}`
            ).openPopup();
            mapMessage.textContent = 'Location approximated from the reported address. © OpenStreetMap contributors.';
        } catch (error) {
            mapMessage.textContent = `Map location unavailable: ${error.message}`;
        }
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
    }

    function renderSelector() {
        const previousId = selectedId;
        selector.replaceChildren();
        if (!devices.length) {
            selector.add(new Option('No devices registered', ''));
            selector.disabled = true;
            selectedId = '';
            return;
        }
        devices.forEach(device => {
            const status = device.online ? 'Online' : 'Offline';
            const label = `${device.device_name || 'Unnamed device'} — ${device.user_name || 'Unknown user'} (${status})`;
            selector.add(new Option(label, device.id));
        });
        selector.disabled = false;
        selectedId = devices.some(device => String(device.id) === String(previousId))
            ? previousId
            : String(devices[0].id);
        selector.value = selectedId;
    }

    async function refreshDevices() {
        if (refreshInProgress || document.hidden) return;
        refreshInProgress = true;
        try {
            const response = await fetch(`${window.BASE_URL}/api/get_device_monitoring.php`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || `Request failed (${response.status}).`);
            devices = payload.devices;
            renderSelector();
            renderSelectedDevice();
            document.getElementById('device-count').textContent =
                `${payload.online_count} online · ${devices.length} registered device(s)`;
            refreshLabel.textContent = `Updated ${payload.updated_at}`;
            errorBox.hidden = true;
            errorBox.textContent = '';
        } catch (error) {
            errorBox.textContent = `Could not load device status: ${error.message}`;
            errorBox.hidden = false;
        } finally {
            refreshInProgress = false;
        }
    }

    selector.addEventListener('change', () => {
        selectedId = selector.value;
        renderSelectedDevice();
    });
    refreshDevices();
    window.setInterval(refreshDevices, 15000);
    window.addEventListener('resize', () => map?.invalidateSize());
})();
</script>
</body>
</html>
