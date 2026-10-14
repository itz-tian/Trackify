</div>

<div class="modal-overlay" id="appConfirmModal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
    <div class="modal confirm-modal">
        <div class="modal-header">
            <div class="modal-title confirm-modal-title">
                <span class="confirm-modal-icon" id="appConfirmIcon"><i data-lucide="triangle-alert"></i></span>
                <span id="appConfirmTitle">Confirm</span>
            </div>
            <button type="button" class="modal-close" id="appConfirmClose" aria-label="Close">×</button>
        </div>
        <p class="confirm-modal-message" id="appConfirmMessage"></p>
        <div class="confirm-modal-details" id="appConfirmDetails" hidden></div>
        <div class="confirm-modal-actions">
            <button type="button" class="btn btn-outline" id="appConfirmCancel">Cancel</button>
            <button type="button" class="btn btn-danger" id="appConfirmOk">Confirm</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="sessionModal" role="alertdialog" aria-modal="true" aria-labelledby="sessionModalTitle" aria-describedby="sessionModalMsg">
    <div class="modal confirm-modal">
        <div class="modal-header">
            <div class="modal-title confirm-modal-title">
                <span class="confirm-modal-icon" style="background:rgba(255,149,0,.14);color:var(--apple-orange);"><i data-lucide="timer"></i></span>
                <span id="sessionModalTitle">Still there?</span>
            </div>
        </div>
        <p class="confirm-modal-message" id="sessionModalMsg">For your security you'll be signed out in <strong id="sessionCountdown">60</strong> seconds because of inactivity.</p>
        <div class="confirm-modal-actions">
            <a class="btn btn-outline" href="<?= APP_URL ?>/logout.php">Log out</a>
            <button type="button" class="btn btn-primary" id="sessionStay">Stay signed in</button>
        </div>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/lucide.min.js?v=<?= APP_VERSION ?>"></script>
<script>
// ── Idle-timeout warning ──────────────────────────────────────────
(function () {
    var WARN = <?= (int)SESSION_WARNING_SECONDS ?>;
    var PING_URL = <?= json_encode(APP_URL . '/check_session.php') ?>;
    var LOGIN_URL = <?= json_encode(APP_URL . '/login.php?timeout=1') ?>;
    var expiresAt = Date.now() + <?= (int)(isset($auth) ? $auth->secondsLeft() : SESSION_TIMEOUT) ?> * 1000;
    var lastPing = Date.now(), timer = null, tick = null, modalOpen = false;
    var modal = document.getElementById('sessionModal'), cd = document.getElementById('sessionCountdown');

    function call(mode) {
        return fetch(PING_URL + '?' + mode + '=1', { cache: 'no-store', credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function schedule() {
        clearTimeout(timer);
        var wait = Math.max(0, expiresAt - Date.now() - WARN * 1000);
        timer = setTimeout(check, wait);
    }
    // Ask the server (another tab may have been active) before warning.
    function check() {
        call('peek').then(function (d) {
            if (!d.logged_in) { location.href = LOGIN_URL; return; }
            expiresAt = Date.now() + d.seconds_left * 1000;
            if (d.seconds_left > WARN) { schedule(); } else { openModal(); }
        }).catch(function () { openModal(); });
    }
    function openModal() {
        modalOpen = true; modal.classList.add('open');
        clearInterval(tick);
        tick = setInterval(function () {
            var s = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            cd.textContent = s;
            if (s <= 0) { clearInterval(tick); location.href = LOGIN_URL; }
        }, 500);
        document.getElementById('sessionStay').focus();
    }
    function stay() {
        call('keepalive').then(function (d) {
            if (!d.logged_in) { location.href = LOGIN_URL; return; }
            expiresAt = Date.now() + d.seconds_left * 1000; lastPing = Date.now();
            modalOpen = false; modal.classList.remove('open'); clearInterval(tick); schedule();
        }).catch(function () { location.reload(); });
    }
    document.getElementById('sessionStay').addEventListener('click', stay);

    // Real activity keeps the session alive (throttled to one ping every 2 minutes).
    function activity() {
        if (modalOpen || Date.now() - lastPing < 120000) return;
        lastPing = Date.now();
        call('keepalive').then(function (d) {
            if (d.logged_in) { expiresAt = Date.now() + d.seconds_left * 1000; schedule(); }
        }).catch(function () {});
    }
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (ev) { window.addEventListener(ev, activity, { passive: true }); });
    schedule();
})();
</script>
<script>
lucide.createIcons();

// Sync title if dynamically set by client page
const ptEl = document.getElementById('pageTitle');
if (ptEl && document.title && document.title.includes('—')) {
    const t = document.title.split('—')[1]?.trim();
    if (t) ptEl.textContent = t;
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => el.style.display = 'none', 5000);
});

// Close on outside click or escape
document.addEventListener('click', e => {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.getElementById('menuBtn');
    if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuBtn?.contains(e.target)) {
        if (typeof closeSidebar === 'function') closeSidebar();
        else sidebar.classList.remove('open');
    }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && typeof closeSidebar === 'function') closeSidebar();
});


// ── Shared confirm modal ─────────────────────────────────────────────
// appConfirm({title, message, details:[[label,value]], confirmText, cancelText,
//             icon, tone:'orange|blue|red', confirmClass:'btn-danger|btn-primary'}) → Promise<boolean>
(function () {
    const overlay = document.getElementById('appConfirmModal');
    if (!overlay) return;
    const el = id => document.getElementById(id);
    let resolver = null, lastFocus = null;

    function finish(result) {
        overlay.classList.remove('open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        const r = resolver; resolver = null;
        if (r) r(result);
    }

    window.appConfirm = function (o) {
        o = o || {};
        if (resolver) finish(false);
        lastFocus = document.activeElement;
        el('appConfirmTitle').textContent   = o.title || 'Are you sure?';
        el('appConfirmMessage').textContent = o.message || '';
        el('appConfirmCancel').textContent  = o.cancelText || 'Cancel';
        const ok = el('appConfirmOk');
        ok.className = 'btn ' + (o.confirmClass || 'btn-danger');
        ok.innerHTML = '';
        if (o.confirmIcon) { const i = document.createElement('i'); i.setAttribute('data-lucide', o.confirmIcon); ok.appendChild(i); }
        ok.appendChild(document.createTextNode(' ' + (o.confirmText || 'Confirm')));
        const iconWrap = el('appConfirmIcon');
        iconWrap.className = 'confirm-modal-icon' + (o.tone && o.tone !== 'orange' ? ' tone-' + o.tone : '');
        iconWrap.innerHTML = '<i data-lucide="' + (o.icon || 'triangle-alert') + '"></i>';
        const list = el('appConfirmDetails');
        list.innerHTML = '';
        (o.details || []).forEach(function (d) {
            const row = document.createElement('div');
            const a = document.createElement('strong'); a.textContent = d[0] + ': ';
            row.appendChild(a); row.appendChild(document.createTextNode(d[1]));
            list.appendChild(row);
        });
        list.hidden = !(o.details && o.details.length);
        overlay.classList.add('open');
        if (window.lucide) lucide.createIcons();
        el('appConfirmCancel').focus();
        return new Promise(function (resolve) { resolver = resolve; });
    };

    el('appConfirmOk').addEventListener('click', function () { finish(true); });
    el('appConfirmCancel').addEventListener('click', function () { finish(false); });
    el('appConfirmClose').addEventListener('click', function () { finish(false); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) finish(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) finish(false);
    });

    // Declarative use: <form data-confirm-title="…" data-confirm-message="…" …>
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.matches || !form.matches('form[data-confirm-message]') || form.dataset.confirmed === '1') return;
        e.preventDefault();
        appConfirm({
            title: form.dataset.confirmTitle,
            message: form.dataset.confirmMessage,
            confirmText: form.dataset.confirmText,
            cancelText: form.dataset.confirmCancelText,
            confirmIcon: form.dataset.confirmIcon,
            confirmClass: form.dataset.confirmClass,
            icon: form.dataset.confirmIconMain,
            tone: form.dataset.confirmTone
        }).then(function (yes) {
            if (yes) { form.dataset.confirmed = '1'; form.submit(); }
        });
    }, true);
})();

// Always reset transient UI when a page is restored from browser history/bfcache.
function resetTransientModals() {
    document.querySelectorAll('.modal-overlay.open').forEach(function(modal) {
        modal.classList.remove('open');
    });
    document.querySelectorAll('form').forEach(function(form) {
        form.querySelectorAll('button[type="submit"]').forEach(function(btn){ btn.disabled = false; });
    });
}
window.addEventListener('pageshow', function (e) { if (e.persisted) resetTransientModals(); });
window.addEventListener('popstate', function () { if (!window.__historyGuardActive) resetTransientModals(); });
window.addEventListener('pagehide', function () { resetTransientModals(); });

// bfcache session check
window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
        fetch('<?= APP_URL ?>/check_session.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.logged_in) {
                    // Session gone — send to login, replace history so
                    // pressing Forward doesn't bring them back here either.
                    window.location.replace('<?= APP_URL ?>/login.php');
                }
            })
            .catch(() => {
                
                window.location.replace('<?= APP_URL ?>/login.php');
            });
    }
});
</script>
</body>
</html>
