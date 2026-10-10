<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
prevent_browser_cache(); // Prevent browser back/forward to cached protected pages
$user = $auth->currentUser();

// Unread notifications
$db = Database::getInstance();
$notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifStmt->execute([$_SESSION['user_id']]);
$unreadCount = (int)$notifStmt->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$pageTitles = [
    'dashboard'         => 'Dashboard',
    'documents'         => 'Documents',
    'document_view'     => 'Document View',
    'upload'            => 'Upload Document',
    'approvals'         => 'Approvals',
    'notifications'     => 'Notifications',
    'recent_documents'  => 'Recent Documents',
    'deadlines'         => 'Deadlines',
    'calendar'          => 'Calendar',
    'activity_log'      => 'Activity Log',
    'archive'           => 'Archive',
    'recycle_bin'       => 'Recycle Bin',
    'reports'           => 'Reports',
    'profile'           => 'Profile',
    'users'             => 'Manage Users',
    'departments'       => 'Departments',
    'categories'        => 'Categories',
];

$topbarTitle = $pageTitle ?? ($pageTitles[$currentPage] ?? ucwords(str_replace('_', ' ', $currentPage)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — <?= htmlspecialchars($topbarTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/favicon.png?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= APP_VERSION ?>">
    <script>
        // Theme init — runs synchronously before render to avoid flash
        (function() {
            var saved = localStorage.getItem('trackify_theme') || 'system';
            var apply = saved;
            if (saved === 'system') {
                apply = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', apply);
            document.documentElement.setAttribute('data-theme-setting', saved);
            // Sidebar collapsed state (desktop) — applied before render to avoid flash
            try {
                if (localStorage.getItem('trackify_sidebar') === 'collapsed') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-logo-link" title="Go to Dashboard" aria-label="Go to Dashboard">
            <img src="<?= APP_URL ?>/assets/logo-light.png?v=<?= APP_VERSION ?>" alt="Trackify" class="logo-img">
            <div>
                <div class="logo-text"><?= APP_NAME ?></div>
                <div class="logo-sub">Document Tracking</div>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/dashboard.php" class="<?= $currentPage==='dashboard'?'active':'' ?>">
                <span class="nav-icon" style="background:#0071e3;"><i data-lucide="layout-dashboard"></i></span>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/documents.php" class="<?= $currentPage==='documents'?'active':'' ?>">
                <span class="nav-icon" style="background:#5856d6;"><i data-lucide="folder-open"></i></span>
                <span class="nav-label">Documents</span>
            </a>
        </div>
        <div class="nav-item"><a href="<?= APP_URL ?>/recent_documents.php" class="<?= $currentPage==='recent_documents'?'active':'' ?>"><span class="nav-icon" style="background:#34c759;"><i data-lucide="clock-3"></i></span><span class="nav-label">Recent Documents</span></a></div>
        <div class="nav-item"><a href="<?= APP_URL ?>/deadlines.php" class="<?= $currentPage==='deadlines'?'active':'' ?>"><span class="nav-icon" style="background:#ff9500;"><i data-lucide="calendar-clock"></i></span><span class="nav-label">Deadlines</span></a></div>
        <div class="nav-item"><a href="<?= APP_URL ?>/calendar.php" class="<?= $currentPage==='calendar'?'active':'' ?>"><span class="nav-icon" style="background:#ff9500;"><i data-lucide="calendar-days"></i></span><span class="nav-label">Calendar</span></a></div>
        <?php if (in_array($_SESSION['user_role'] ?? '', ['admin','department_head','staff'])): ?>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/upload.php" class="<?= $currentPage==='upload'?'active':'' ?>">
                <span class="nav-icon" style="background:#34c759;"><i data-lucide="cloud-upload"></i></span>
                <span class="nav-label">Upload Document</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array($_SESSION['user_role'] ?? '', ['admin','department_head','staff'])): ?>
        <div class="nav-section">Workflow</div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/approvals.php" class="<?= $currentPage==='approvals'?'active':'' ?>">
                <span class="nav-icon" style="background:#ff9500;"><i data-lucide="list-todo"></i></span>
                <span class="nav-label">Approvals</span>
                <?php
                $uRole = $_SESSION['user_role'] ?? 'staff';
                $uDept = $_SESSION['user_dept'] ?? 0;
                $uId   = (int)$_SESSION['user_id'];

                if ($uRole === 'admin') {
                    $pendingStmt = $db->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL AND status IN ('pending_review', 'under_review')");
                    $pendingCount = (int)$pendingStmt->fetchColumn();
                } elseif ($uRole === 'department_head') {
                    $pendingStmt = $db->prepare("SELECT COUNT(DISTINCT d.id) FROM documents d LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.approver_id = ? LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.deleted_at IS NULL AND d.department_id = ? AND u.role <> 'admin' AND d.status IN ('pending_review', 'under_review') AND (aw.id IS NULL OR aw.status = 'pending')");
                    $pendingStmt->execute([$uId, $uDept]);
                    $pendingCount = (int)$pendingStmt->fetchColumn();
                } else {
                    $pendingStmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL AND uploaded_by = ? AND status IN ('pending_review', 'under_review')");
                    $pendingStmt->execute([$uId]);
                    $pendingCount = (int)$pendingStmt->fetchColumn();
                }
                if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
        </div>
        <?php endif; // end workflow role guard ?>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/notifications.php" class="<?= $currentPage==='notifications'?'active':'' ?>">
                <span class="nav-icon" style="background:#ff3b30;"><i data-lucide="bell"></i></span>
                <span class="nav-label">Notifications</span>
                <?php if ($unreadCount > 0): ?><span class="nav-badge"><?= $unreadCount ?></span><?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/activity_log.php" class="<?= $currentPage==='activity_log'?'active':'' ?>">
                <span class="nav-icon" style="background:#8e8e93;"><i data-lucide="history"></i></span>
                <span class="nav-label">Activity Log</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/archive.php" class="<?= in_array($currentPage,['archive','recycle_bin'])?'active':'' ?>">
                <span class="nav-icon" style="background:#ff9500;"><i data-lucide="archive"></i></span>
                <span class="nav-label">Archive</span>
            </a>
        </div>

        <?php if (in_array($_SESSION['user_role'] ?? '', ['admin','department_head','staff'])): ?>
        <div class="nav-section"><?= ($_SESSION['user_role'] ?? '') === 'staff' ? 'Analytics' : 'Management' ?></div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/reports.php" class="<?= $currentPage==='reports'?'active':'' ?>">
                <span class="nav-icon" style="background:#af52de;"><i data-lucide="chart-column"></i></span>
                <span class="nav-label">Reports</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <div class="nav-section">Admin</div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/admin/users.php" class="<?= $currentPage==='users'?'active':'' ?>">
                <span class="nav-icon" style="background:#00c7be;"><i data-lucide="users-round"></i></span>
                <span class="nav-label">Manage Users</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/admin/categories.php" class="<?= $currentPage==='categories'?'active':'' ?>">
                <span class="nav-icon" style="background:#ff2d55;"><i data-lucide="tags"></i></span>
                <span class="nav-label">Categories</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/admin/departments.php" class="<?= $currentPage==='departments'?'active':'' ?>">
                <span class="nav-icon" style="background:#64d2ff;"><i data-lucide="building-2"></i></span>
                <span class="nav-label">Departments</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <!-- User Popover Menu -->
        <div class="user-popover" id="userPopover">
            <div class="user-popover-header">
                <div class="pop-name"><?= htmlspecialchars(($user['first_name']??'User').' '.($user['last_name']??'')) ?></div>
                <div class="pop-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
            <a href="<?= APP_URL ?>/profile.php" class="user-popover-item">
                <i data-lucide="user-round"></i>
                My Profile
            </a>
            <div class="user-popover-divider"></div>
            <a href="<?= APP_URL ?>/logout.php" class="user-popover-item danger">
                <i data-lucide="log-out"></i>
                Sign Out
            </a>
        </div>

        <!-- Clickable User Card -->
        <div class="sidebar-user" id="userMenuTrigger" onclick="toggleUserPopover()" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
            <div class="avatar"><i data-lucide="user-round"></i></div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars(($user['first_name']??'User').' '.($user['last_name']??'')) ?></div>
                <div class="role"><?= str_replace('_',' ', $user['role'] ?? 'Staff') ?></div>
            </div>
            <span class="chevron-icon"><i data-lucide="chevron-up" style="width:14px;height:14px;"></i></span>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- Topbar -->
<header class="topbar">
    <div class="flex items-center gap-12">
        <button onclick="toggleSidebar()" class="menu-btn" id="menuBtn" title="Collapse / expand sidebar (Ctrl+B)" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="true">
            <i data-lucide="menu"></i>
        </button>
        <div class="topbar-title" id="pageTitle"><?= htmlspecialchars($topbarTitle) ?></div>
    </div>
    <div class="topbar-actions">
        <a href="<?= APP_URL ?>/notifications.php" class="topbar-btn" title="Notifications">
            <i data-lucide="bell"></i>
            <?php if ($unreadCount > 0): ?><span class="badge"><?= $unreadCount ?></span><?php endif; ?>
        </a>
        <button type="button" class="topbar-btn" id="themeQuickBtn" onclick="quickToggleTheme()" title="Switch light / dark" aria-label="Switch between light and dark theme">
            <i data-lucide="moon" class="ico-moon"></i><i data-lucide="sun" class="ico-sun"></i>
        </button>
        <button type="button" class="topbar-btn" id="settingsBtn" onclick="openSettingsModal()" title="Settings" aria-label="Open settings" aria-haspopup="dialog">
            <i data-lucide="settings"></i>
        </button>
    </div>
</header>

<!-- Settings Modal -->
<div class="modal-overlay" id="settingsModal" role="dialog" aria-modal="true" aria-labelledby="settingsModalTitle" onclick="if(event.target===this)closeSettingsModal()">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <div class="modal-title" id="settingsModalTitle"><i data-lucide="settings" style="color:var(--apple-blue); margin-right:6px;"></i> Settings</div>
            <button type="button" class="modal-close" onclick="closeSettingsModal()" aria-label="Close settings">×</button>
        </div>

        <div class="settings-section-label">Appearance</div>
        <div class="settings-theme-grid">
            <button type="button" class="settings-theme-opt" id="themeOptLight" onclick="setTheme('light')"><i data-lucide="sun"></i><span>Light</span></button>
            <button type="button" class="settings-theme-opt" id="themeOptDark" onclick="setTheme('dark')"><i data-lucide="moon"></i><span>Dark</span></button>
            <button type="button" class="settings-theme-opt" id="themeOptSystem" onclick="setTheme('system')"><i data-lucide="monitor"></i><span>System</span></button>
        </div>

        <div class="settings-section-label">Data Management</div>
        <div class="settings-list settings-data-list">
            <label class="settings-control-row"><span><i data-lucide="bell-ring"></i><span><strong>Expiry reminders</strong><small>Show expiry warnings in the dashboard and deadlines page.</small></span></span><input type="checkbox" id="settingExpiryReminders" checked><span class="settings-switch" aria-hidden="true"></span></label>
            <label class="settings-control-row"><span><i data-lucide="shield-alert"></i><span><strong>Confirm archive actions</strong><small>Ask for confirmation before moving documents to Archive.</small></span></span><input type="checkbox" id="settingConfirmArchive" checked><span class="settings-switch" aria-hidden="true"></span></label>
            <div class="settings-value-row"><span><i data-lucide="list"></i><span><strong>Documents per page</strong><small>Choose how many documents appear in a list.</small></span></span><select id="settingPageSize"><option value="15">15</option><option value="25">25</option><option value="50">50</option></select></div>
            <button type="button" class="settings-row" onclick="resetDataSettings()"><i data-lucide="rotate-ccw"></i><span>Reset Data Management Preferences</span></button>
        </div>

        <div class="settings-section-label" style="margin-top:20px;">More</div>
        <div class="settings-list">
            <a href="<?= APP_URL ?>/profile.php" class="settings-row"><i data-lucide="user-round"></i><span>My Profile</span><i data-lucide="chevron-right" class="settings-chevron"></i></a>
            <a href="<?= APP_URL ?>/logout.php" class="settings-row danger"><i data-lucide="log-out"></i><span>Sign Out</span></a>
        </div>
    </div>
</div>

<style>
    .settings-section-label { font-size:11.5px; font-weight:600; text-transform:uppercase; letter-spacing:0.03em; color:var(--text-secondary); margin:6px 0 10px; }
    .settings-theme-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:22px; }
    .settings-theme-opt { display:flex; flex-direction:column; align-items:center; gap:6px; padding:14px 8px; border-radius:12px; border:1.5px solid var(--border-hairline); background:var(--bg-card-subtle); color:var(--text-primary); font-size:12.5px; font-weight:500; cursor:pointer; font-family:inherit; transition:border-color .15s, background .15s; }
    .settings-theme-opt i, .settings-theme-opt svg { width:20px; height:20px; opacity:.8; }
    .settings-theme-opt:hover { border-color:var(--apple-blue); }
    .settings-theme-opt.active { border-color:var(--apple-blue); color:var(--apple-blue); font-weight:600; background:rgba(0,113,227,0.08); }
    .settings-theme-opt.active i, .settings-theme-opt.active svg { opacity:1; }
    .settings-list { border:1px solid var(--border-hairline); border-radius:12px; overflow:hidden; }
    .settings-row { display:flex; align-items:center; gap:10px; padding:13px 14px; font-size:13.5px; font-weight:500; color:var(--text-primary); text-decoration:none; background:var(--bg-card-subtle); width:100%; box-sizing:border-box; border:none; border-radius:0; outline:none; font-family:inherit; cursor:pointer; text-align:left; appearance:none; -webkit-appearance:none; }
    .settings-row + .settings-row { border-top:1px solid var(--border-hairline); }
    .settings-row:hover { background:rgba(0,0,0,0.04); }
    [data-theme="dark"] .settings-row:hover { background:rgba(255,255,255,0.07); }
    .settings-row i, .settings-row svg { width:16px; height:16px; flex-shrink:0; opacity:.8; }
    .settings-row span { flex:1; }
    .settings-row .settings-chevron { opacity:.4; }
    .settings-row.danger { color:var(--apple-red); }
    .settings-control-row, .settings-value-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; color:var(--text-primary); background:var(--bg-card-subtle); }
    .settings-control-row + .settings-control-row, .settings-value-row + .settings-control-row, .settings-control-row + .settings-value-row, .settings-value-row + .settings-row { border-top:1px solid var(--border-hairline); }
    .settings-control-row > span:first-child, .settings-value-row > span:first-child { display:flex; align-items:flex-start; gap:10px; flex:1; min-width:0; }
    .settings-control-row > span:first-child > i, .settings-value-row > span:first-child > i { width:17px; height:17px; flex-shrink:0; margin-top:2px; opacity:.75; }
    .settings-control-row strong, .settings-value-row strong { display:block; font-size:12.5px; font-weight:600; }
    .settings-control-row small, .settings-value-row small { display:block; margin-top:2px; color:var(--text-secondary); font-size:10.5px; line-height:1.35; }
    .settings-control-row input[type="checkbox"] { position:absolute; opacity:0; pointer-events:none; }
    .settings-switch { width:38px; height:22px; border-radius:20px; background:#8e8e93; position:relative; flex-shrink:0; transition:.15s; }
    .settings-switch::after { content:""; position:absolute; width:18px; height:18px; top:2px; left:2px; border-radius:50%; background:white; box-shadow:0 1px 3px rgba(0,0,0,.25); transition:.15s; }
    .settings-control-row input:checked + .settings-switch { background:var(--apple-green); }
    .settings-control-row input:checked + .settings-switch::after { transform:translateX(16px); }
    .settings-value-row select { width:82px; padding:7px 9px; font-size:12px; }
</style>

<script>
function isDesktop() { return window.innerWidth > 768; }

function toggleSidebar() {
    // Desktop: collapse to icon rail / expand. Mobile: slide-in drawer.
    if (isDesktop()) {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('trackify_sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        syncSidebarUI();
        return;
    }
    const sb = document.getElementById('sidebar');
    const bd = document.getElementById('sidebarBackdrop');
    const isOpen = sb.classList.contains('open');
    if (isOpen) {
        closeSidebar();
    } else {
        sb.classList.add('open');
        if (bd) bd.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Tooltips (native title) on nav links only while collapsed + aria state on the toggle button
function syncSidebarUI() {
    const collapsed = document.documentElement.classList.contains('sidebar-collapsed') && isDesktop();
    document.querySelectorAll('#sidebar .nav-item a').forEach(function(a) {
        const label = a.querySelector('.nav-label');
        if (collapsed && label) a.setAttribute('title', label.textContent.trim());
        else a.removeAttribute('title');
    });
    const btn = document.getElementById('menuBtn');
    if (btn && isDesktop()) btn.setAttribute('aria-expanded', String(!collapsed));
    // close the user popover so it doesn't float oddly mid-transition
    document.getElementById('userPopover')?.classList.remove('open');
    document.getElementById('userMenuTrigger')?.classList.remove('active');
}
window.addEventListener('resize', syncSidebarUI);

// Ctrl/Cmd + B toggles the sidebar
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b' && !e.shiftKey && !e.altKey) {
        const t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault();
        toggleSidebar();
    }
});

function closeSidebar() {
    const sb = document.getElementById('sidebar');
    const bd = document.getElementById('sidebarBackdrop');
    if (sb) sb.classList.remove('open');
    if (bd) bd.classList.remove('active');
    document.body.style.overflow = '';
}

function toggleUserPopover() {
    const popover = document.getElementById('userPopover');
    const trigger = document.getElementById('userMenuTrigger');
    const isOpen  = popover.classList.contains('open');
    popover.classList.toggle('open', !isOpen);
    trigger.classList.toggle('active', !isOpen);
    trigger.setAttribute('aria-expanded', String(!isOpen));
}

// Close on outside click
document.addEventListener('click', function(e) {
    const popover = document.getElementById('userPopover');
    const trigger = document.getElementById('userMenuTrigger');
    if (!popover || !trigger) return;
    if (!trigger.contains(e.target) && !popover.contains(e.target)) {
        popover.classList.remove('open');
        trigger.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
    }
});

// Keyboard controls
document.getElementById('userMenuTrigger')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleUserPopover(); }
    if (e.key === 'Escape') {
        document.getElementById('userPopover')?.classList.remove('open');
        this.classList.remove('active');
        this.setAttribute('aria-expanded', 'false');
    }
});

/* ── Theme Switcher ───────────────────────────────────── */
function applyTheme(setting) {
    var resolved = setting;
    if (setting === 'system') {
        resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-setting', setting);
}

function setTheme(setting) {
    localStorage.setItem('trackify_theme', setting);
    applyTheme(setting);
    syncThemeUI(setting);
}

function quickToggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme');
    setTheme(cur === 'dark' ? 'light' : 'dark');
}

function syncThemeUI(setting) {
    ['light','dark','system'].forEach(function(opt) {
        var el = document.getElementById('themeOpt' + opt.charAt(0).toUpperCase() + opt.slice(1));
        if (el) el.classList.toggle('active', opt === setting);
    });
}

/* ── Settings Modal ───────────────────────────────────── */
function openSettingsModal() {
    var m = document.getElementById('settingsModal');
    if (m) m.classList.add('open');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeSettingsModal() {
    var m = document.getElementById('settingsModal');
    if (m) m.classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSettingsModal();
});

// Listen for OS dark-mode changes (System theme)
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
    var saved = localStorage.getItem('trackify_theme') || 'system';
    if (saved === 'system') applyTheme('system');
});

function loadDataSettings() {
    var defaults = { expiryReminders:true, confirmArchive:true, pageSize:'15' };
    try {
        var saved = JSON.parse(localStorage.getItem('trackify_data_settings') || '{}');
        var cfg = Object.assign({}, defaults, saved);
        document.getElementById('settingExpiryReminders').checked = !!cfg.expiryReminders;
        var deadlineCard = document.getElementById('upcomingDeadlinesCard');
        if (deadlineCard) deadlineCard.style.display = cfg.expiryReminders ? '' : 'none';
        document.getElementById('settingConfirmArchive').checked = !!cfg.confirmArchive;
        document.getElementById('settingPageSize').value = String(cfg.pageSize);
    } catch (e) {}
}

function saveDataSettings() {
    var cfg = {
        expiryReminders: document.getElementById('settingExpiryReminders').checked,
        confirmArchive: document.getElementById('settingConfirmArchive').checked,
        pageSize: document.getElementById('settingPageSize').value
    };
    localStorage.setItem('trackify_data_settings', JSON.stringify(cfg));
}

function resetDataSettings() {
    localStorage.removeItem('trackify_data_settings');
    loadDataSettings();
}

document.addEventListener('change', function(e) {
    if (!e.target || !['settingExpiryReminders','settingConfirmArchive','settingPageSize'].includes(e.target.id)) return;
    saveDataSettings();
    if (e.target.id === 'settingExpiryReminders') {
        var deadlineCard = document.getElementById('upcomingDeadlinesCard');
        if (deadlineCard) deadlineCard.style.display = e.target.checked ? '' : 'none';
    }
    if (e.target.id === 'settingPageSize' && window.location.pathname.endsWith('/documents.php')) {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', e.target.value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
});

// Init on load
document.addEventListener('DOMContentLoaded', function() {
    loadDataSettings();
    syncSidebarUI();
    var saved = localStorage.getItem('trackify_theme') || 'system';
    syncThemeUI(saved);
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<div class="main-content">
