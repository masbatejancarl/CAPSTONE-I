// ===== SonoBand Main JS =====

// Auto-dismiss flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sb-alert').forEach(el => {
        setTimeout(() => el.classList.add('fade'), 3500);
        setTimeout(() => el.remove(), 4000);
    });

    // Live clock in navbar if element exists
    const clock = document.getElementById('sb-clock');
    if (clock) {
        const tick = () => {
            clock.textContent = new Date().toLocaleString('en-PH', {
                dateStyle: 'medium', timeStyle: 'short'
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    // Mark alert as read on row click (user alert history only — admin/caregiver views are read-only)
    document.querySelectorAll('.js-mark-readable[data-alert-id]').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', () => {
            const id = row.dataset.alertId;
            fetch(`${window.BASE_URL}/api/mark_read.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            }).then(() => {
                const badge = row.querySelector('.sb-status.unread');
                if (badge) { badge.textContent = 'Read'; badge.classList.replace('unread', 'read'); }
            });
        });
    });

    // Auto-add a show/hide eye toggle to every password field on the page
    document.querySelectorAll('input[type="password"]').forEach(input => {
        if (input.closest('.sb-pw-wrap')) return; // already wrapped

        const wrap = document.createElement('div');
        wrap.className = 'sb-pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sb-pw-toggle';
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML = '<i class="bi bi-eye"></i>';
        btn.addEventListener('click', () => {
            const icon = btn.querySelector('i');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
        wrap.appendChild(btn);
    });

    // Live password requirements checklist (10+ chars, upper, lower, special)
    document.querySelectorAll('[data-pw-input]').forEach(input => {
        const checklist = document.getElementById(input.dataset.pwInput);
        if (!checklist) return;
        const update = () => {
            const val = input.value;
            const checks = {
                length:  val.length >= 10,
                upper:   /[A-Z]/.test(val),
                lower:   /[a-z]/.test(val),
                special: /[^A-Za-z0-9]/.test(val),
            };
            Object.entries(checks).forEach(([key, ok]) => {
                const el = checklist.querySelector(`[data-req="${key}"]`);
                if (!el) return;
                el.classList.toggle('ok', ok);
                el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            });
        };
        input.addEventListener('input', update);
        update();
    });
});

// Sound type → icon + CSS class helper
function getSoundMeta(type) {
    const map = {
        'Doorbell':        { icon: '🔔', cls: 'sound-doorbell' },
        'Fire Alarm':      { icon: '🚨', cls: 'sound-alarm'    },
        'Vehicle Horn':    { icon: '📯', cls: 'sound-vehicle'  },
        'Emergency Siren': { icon: '🚑', cls: 'sound-siren'    },
        'Loud Knock':      { icon: '👊', cls: 'sound-knock'    },
    };
    return map[type] || { icon: '🔊', cls: 'sound-default' };
}
