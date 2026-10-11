<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth     = new Auth();
$auth->requireLogin();
$docClass = new Document();
$stats    = $docClass->getStats();
$db       = Database::getInstance();

$userRole = $_SESSION['user_role'] ?? 'staff';
$userId   = (int)($_SESSION['user_id']  ?? 0);
$userDept = (int)($_SESSION['user_dept'] ?? 0);

// Scope filter
$docWhere  = 'd.deleted_at IS NULL';
$docParams = [];
if ($userRole === 'viewer') {
    $docWhere = "d.deleted_at IS NULL AND d.status = 'approved'";
} elseif ($userRole === 'staff') {
    $docWhere  = 'd.deleted_at IS NULL AND d.department_id = ?';
    $docParams = [$userDept];
} elseif ($userRole === 'department_head') {
    $docWhere  = 'd.deleted_at IS NULL AND d.department_id = ?';
    $docParams = [$userDept];
}

// Recent docs (10 per page; pagination appears once there are more than 10)
$recentPerPage = 10;
$recentCountStmt = $db->prepare("SELECT COUNT(*) FROM documents d WHERE $docWhere");
$recentCountStmt->execute($docParams);
$recentTotal  = (int)$recentCountStmt->fetchColumn();
$recentPages  = max(1, (int)ceil($recentTotal / $recentPerPage));
$recentPage   = min($recentPages, max(1, (int)($_GET['rd_page'] ?? 1)));
$recentOffset = ($recentPage - 1) * $recentPerPage;
$recentStmt = $db->prepare("
    SELECT d.*, c.name AS category_name, c.color AS category_color,
           dep.name AS department_name,
           CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
    FROM documents d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE $docWhere
    ORDER BY d.updated_at DESC LIMIT $recentPerPage OFFSET $recentOffset
");
$recentStmt->execute($docParams);
$recentDocs = $recentStmt->fetchAll();

// Activity feed by role
if ($userRole === 'admin') {
    $activityStmt = $db->prepare("
        SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, d.title AS doc_title
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN documents d ON al.document_id = d.id
        ORDER BY al.created_at DESC LIMIT 8
    ");
    $activityStmt->execute();
} elseif ($userRole === 'department_head') {
    $activityStmt = $db->prepare("
        SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, d.title AS doc_title
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN documents d ON al.document_id = d.id
        WHERE d.department_id = ? OR al.user_id = ?
        ORDER BY al.created_at DESC LIMIT 8
    ");
    $activityStmt->execute([$userDept, $userId]);
} else {
    $activityStmt = $db->prepare("
        SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, d.title AS doc_title
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN documents d ON al.document_id = d.id
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC LIMIT 8
    ");
    $activityStmt->execute([$userId]);
}
$activities = $activityStmt->fetchAll();

// Status colors
$statusColors = [
    'draft'=>'badge-draft','pending_review'=>'badge-pending','under_review'=>'badge-review',
    'approved'=>'badge-approved','rejected'=>'badge-rejected','archived'=>'badge-archived'
];
$actionIcons = [
    'login'          => ['log-in', 'rgba(52, 199, 89, 0.15)', 'var(--apple-green)'],
    'logout'         => ['log-out', 'rgba(142, 142, 147, 0.15)', 'var(--apple-gray)'],
    'upload'         => ['upload', 'rgba(0, 113, 227, 0.15)', 'var(--apple-blue)'],
    'view'           => ['eye', 'rgba(100, 210, 255, 0.18)', '#007aff'],
    'download'       => ['download', 'rgba(0, 113, 227, 0.15)', 'var(--apple-blue)'],
    'restore'        => ['archive-restore', 'rgba(52, 199, 89, 0.15)', 'var(--apple-green)'],
    'permanent_delete'=> ['trash-2', 'rgba(255, 59, 48, 0.15)', 'var(--apple-red)'],
    'status_change'  => ['refresh-cw', 'rgba(255, 149, 0, 0.15)', 'var(--apple-orange)'],
    'delete'         => ['trash-2', 'rgba(255, 59, 48, 0.15)', 'var(--apple-red)'],
    'comment'        => ['message-square', 'rgba(175, 82, 222, 0.15)', 'var(--apple-purple)'],
    'approval'       => ['circle-check', 'rgba(52, 199, 89, 0.15)', 'var(--apple-green)'],
    'password_change'=> ['key-round', 'rgba(88, 86, 214, 0.15)', 'var(--apple-indigo)'],
];

// Additional metrics (scoped with the same role filter as the KPI cards so numbers agree)
$thisMonthStmt = $db->prepare("SELECT COUNT(*) FROM documents d WHERE $docWhere AND d.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$thisMonthStmt->execute($docParams);
$thisMonthUploads = (int)$thisMonthStmt->fetchColumn();
$sizeStmt = $db->prepare("SELECT COALESCE(SUM(d.file_size),0) FROM documents d WHERE $docWhere");
$sizeStmt->execute($docParams);
$totalStorage = (int)$sizeStmt->fetchColumn();

// Documents per department - same visibility scope as everything else on this page.
// The scope sits in the JOIN condition (not WHERE) so departments with 0 visible docs still resolve correctly.
$deptStmt = $db->prepare("
    SELECT dep.name, COUNT(d.id) AS cnt
    FROM departments dep
    LEFT JOIN documents d ON dep.id = d.department_id AND $docWhere
    GROUP BY dep.id, dep.name
");
$deptStmt->execute($docParams);
$deptRows = $deptStmt->fetchAll();

// Documents with no department were silently dropped before - count them as "General"
$generalStmt = $db->prepare("SELECT COUNT(*) FROM documents d WHERE $docWhere AND d.department_id IS NULL");
$generalStmt->execute($docParams);
$generalCnt = (int)$generalStmt->fetchColumn();
if ($generalCnt > 0) $deptRows[] = ['name' => 'General', 'cnt' => $generalCnt];

// Non-admins only see departments that actually have documents visible to them
if ($userRole !== 'admin') {
    $deptRows = array_values(array_filter($deptRows, fn($r) => (int)$r['cnt'] > 0));
}
usort($deptRows, fn($a, $b) => ((int)$b['cnt'] <=> (int)$a['cnt']) ?: strcasecmp($a['name'], $b['name']));
$topDepartments = array_slice($deptRows, 0, 5);

// "Pending Review" metrics:
// - Approvers (admin & department_head): $actionableWaiting counts items requiring THEIR decision/approval
// - Staff: $myPendingWaiting counts their own submissions awaiting approval
// - Department-wide pending count is also preserved for stats
$deptPendingWaiting = (int)$stats['pending_review'] + (int)$stats['under_review'];
$actionableWaiting  = (int)($stats['actionable_pending'] ?? 0);
$myPendingWaiting   = (int)($stats['my_pending'] ?? 0);

$archiveWhere = 'deleted_at IS NOT NULL';
$archiveParams = [];
if ($userRole !== 'admin') { $archiveWhere .= ' AND uploaded_by = ?'; $archiveParams[] = $userId; }
$archiveStmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE $archiveWhere");
$archiveStmt->execute($archiveParams);
$recycleBinCount = (int)$archiveStmt->fetchColumn();

$deadlineStmt = $db->prepare("SELECT d.id,d.title,d.expires_at,c.name AS category_name FROM documents d LEFT JOIN categories c ON d.category_id=c.id WHERE $docWhere AND d.expires_at IS NOT NULL AND d.expires_at >= CURDATE() ORDER BY d.expires_at ASC LIMIT 5");
$deadlineStmt->execute($docParams);
$upcomingDeadlines = $deadlineStmt->fetchAll();

// ── Chart data ───────────────────────────────────────────────────────────
// Status breakdown (donut)
$statusChart = [
    ['label'=>'Pending review', 'value'=>$deptPendingWaiting,        'color'=>'#ff9500'],
    ['label'=>'Approved',       'value'=>(int)$stats['approved'],   'color'=>'#34c759'],
    ['label'=>'Rejected',       'value'=>(int)$stats['rejected'],   'color'=>'#ff3b30'],
    ['label'=>'Draft',          'value'=>(int)$stats['draft'],      'color'=>'#8e8e93'],
];
if ((int)$stats['archived'] > 0) {
    $statusChart[] = ['label'=>'Archived', 'value'=>(int)$stats['archived'], 'color'=>'#636366'];
}
$statusChartTotal = array_sum(array_column($statusChart, 'value'));

// Uploads per month - last 6 months, zero-filled
$monthFirst = new DateTime('first day of this month 00:00:00');
$monthFrom  = (clone $monthFirst)->modify('-5 months');
$monthStmt  = $db->prepare("SELECT DATE_FORMAT(d.created_at,'%Y-%m') AS ym, COUNT(*) AS cnt FROM documents d WHERE $docWhere AND d.created_at >= ? GROUP BY ym");
$monthStmt->execute(array_merge($docParams, [$monthFrom->format('Y-m-d')]));
$monthCounts = $monthStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$monthLabels = []; $monthValues = [];
for ($i = 0; $i < 6; $i++) {
    $m = (clone $monthFrom)->modify("+$i months");
    $monthLabels[] = $m->format('M');
    $monthValues[] = (int)($monthCounts[$m->format('Y-m')] ?? 0);
}

// Documents per department (reuses $deptRows from above, top 6)
$deptChartRows = array_slice($deptRows, 0, 6);

// Breakdown by category (used for single-department views like staff)
$catStmt = $db->prepare("
    SELECT c.name, COUNT(d.id) AS cnt 
    FROM categories c 
    LEFT JOIN documents d ON c.id = d.category_id AND $docWhere 
    GROUP BY c.id, c.name 
    HAVING cnt > 0 
    ORDER BY cnt DESC 
    LIMIT 6
");
$catStmt->execute($docParams);
$catChartRows = $catStmt->fetchAll();

$chartData = [
    'status'   => ['labels'=>array_column($statusChart,'label'), 'values'=>array_column($statusChart,'value'), 'colors'=>array_column($statusChart,'color'), 'total'=>$statusChartTotal],
    'monthly'  => ['labels'=>$monthLabels, 'values'=>$monthValues],
    'dept'     => ['labels'=>array_column($deptChartRows,'name'), 'values'=>array_map('intval', array_column($deptChartRows,'cnt'))],
    'category' => ['labels'=>array_column($catChartRows,'name'), 'values'=>array_map('intval', array_column($catChartRows,'cnt'))],
];

// ── "Needs your approval" panel — same visibility rules as the sidebar Approvals badge ──
$apprJoin = ''; $apprWhere = ''; $apprParams = [];
$apprStatuses = "d.status IN ('pending_review','under_review')";
if ($userRole === 'admin') {
    $apprJoin  = "LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.approver_id = ? JOIN users u ON u.id = d.uploaded_by";
    $apprWhere = "d.deleted_at IS NULL AND u.role <> 'admin' AND $apprStatuses AND (aw.id IS NULL OR aw.status = 'pending')";
    $apprParams = [$userId];
} elseif ($userRole === 'department_head') {
    $apprJoin  = "LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.approver_id = ? JOIN users u ON u.id = d.uploaded_by";
    $apprWhere = "d.deleted_at IS NULL AND d.department_id = ? AND u.role <> 'admin' AND $apprStatuses AND (aw.id IS NULL OR aw.status = 'pending')";
    $apprParams = [$userId, $userDept];
} else {
    $apprWhere = "d.deleted_at IS NULL AND d.uploaded_by = ? AND $apprStatuses";
    $apprParams = [$userId];
}
$apprCountStmt = $db->prepare("SELECT COUNT(DISTINCT d.id) FROM documents d $apprJoin WHERE $apprWhere");
$apprCountStmt->execute($apprParams);
$apprCount = (int)$apprCountStmt->fetchColumn();
$apprListStmt = $db->prepare("SELECT DISTINCT d.id, d.title, d.reference_number, d.status, d.priority, d.updated_at, CONCAT(up.first_name,' ',up.last_name) AS uploader FROM documents d $apprJoin LEFT JOIN users up ON up.id = d.uploaded_by WHERE $apprWhere ORDER BY d.updated_at ASC LIMIT 5");
$apprListStmt->execute($apprParams);
$apprList = $apprListStmt->fetchAll();
$apprTitle = $userRole === 'staff' ? 'My Submissions Awaiting Approval' : 'Needs Your Approval';
$canSeeApprovals = in_array($userRole, ['admin','department_head','staff'], true);

// Greeting summary tailored to user role
$attnParts = [];
if ($userRole === 'staff') {
    if ($myPendingWaiting > 0)             $attnParts[] = $myPendingWaiting . ' of your submission' . ($myPendingWaiting === 1 ? '' : 's') . ' awaiting decision';
    if ((int)$stats['expiring_soon'] > 0)  $attnParts[] = (int)$stats['expiring_soon'] . ' department document' . ((int)$stats['expiring_soon'] === 1 ? '' : 's') . ' expiring soon';
    $attnTotal = $myPendingWaiting + (int)$stats['expiring_soon'];
} elseif ($userRole === 'viewer') {
    if ((int)$stats['expiring_soon'] > 0)  $attnParts[] = (int)$stats['expiring_soon'] . ' document' . ((int)$stats['expiring_soon'] === 1 ? '' : 's') . ' expiring soon';
    $attnTotal = (int)$stats['expiring_soon'];
} else {
    // Admin and Department Head
    if ($actionableWaiting > 0)            $attnParts[] = $actionableWaiting . ' awaiting your approval';
    if ((int)$stats['expiring_soon'] > 0)  $attnParts[] = (int)$stats['expiring_soon'] . ' expiring soon';
    $attnTotal = $actionableWaiting + (int)$stats['expiring_soon'];
}

$canViewReports = in_array($userRole, ['admin', 'department_head', 'staff'], true);

include __DIR__ . '/includes/header.php';
?>

<div class="dash">

<!-- Greeting -->
<div class="dash-hero">
    <div>
        <h1 class="dash-greeting"><span id="greetWord">Welcome back</span>, <?= htmlspecialchars($user['first_name'] ?? 'there') ?></h1>
        <p class="dash-sub">
            <?php if ($attnTotal > 0): ?>
                <?php if ($userRole === 'staff'): ?>
                    Activity overview &mdash; <?= htmlspecialchars(implode(', ', $attnParts)) ?>.
                <?php else: ?>
                    <strong><?= $attnTotal ?></strong> item<?= $attnTotal === 1 ? '' : 's' ?> need<?= $attnTotal === 1 ? 's' : '' ?> your attention &mdash; <?= htmlspecialchars(implode(', ', $attnParts)) ?>.
                <?php endif; ?>
            <?php else: ?>
                You're all caught up &mdash; nothing needs your attention right now.
            <?php endif; ?>
        </p>
    </div>
    <div class="dash-hero-actions">
        <?php if ($canViewReports): ?>
            <a href="<?= APP_URL ?>/reports.php" class="btn btn-outline"><i data-lucide="bar-chart-3"></i> Reports</a>
        <?php else: ?>
            <a href="<?= APP_URL ?>/documents.php" class="btn btn-outline"><i data-lucide="folder-open"></i> Browse Documents</a>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'department_head', 'staff'], true)): ?>
            <a href="<?= APP_URL ?>/upload.php" class="btn btn-primary dash-cta"><i data-lucide="plus"></i> New Document</a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI cards: 4 clean, focused, non-redundant cards tailored per role -->
<?php if ($userRole === 'staff'): ?>
<div class="kpi-grid">
    <a href="<?= APP_URL ?>/approvals.php" class="kpi kpi-primary <?= $myPendingWaiting === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,149,0;">
        <div class="kpi-top"><span class="kpi-label">Awaiting Decision</span><span class="kpi-ico"><i data-lucide="clock"></i></span></div>
        <div class="kpi-num"><?= $myPendingWaiting ?></div>
        <div class="kpi-hint"><?= $myPendingWaiting === 0 ? 'No submissions in review' : 'Your submissions awaiting approval' ?></div>
    </a>
    <a href="<?= APP_URL ?>/deadlines.php" class="kpi kpi-primary <?= (int)$stats['expiring_soon'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,59,48;">
        <div class="kpi-top"><span class="kpi-label">Expiring Soon</span><span class="kpi-ico"><i data-lucide="triangle-alert"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['expiring_soon'] === 0 ? 'None due in 30 days' : 'Due within the next 30 days' ?></div>
    </a>
    <a href="<?= APP_URL ?>/documents.php" class="kpi <?= (int)$stats['total'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:0,113,227;">
        <div class="kpi-top"><span class="kpi-label">Department Files</span><span class="kpi-ico"><i data-lucide="folder-open"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['total'] ?></div>
        <div class="kpi-hint">Accessible department documents</div>
    </a>
    <a href="<?= APP_URL ?>/documents.php?uploader_id=<?= $userId ?>" class="kpi <?= (int)$stats['my_total'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:52,199,89;">
        <div class="kpi-top"><span class="kpi-label">My Uploads</span><span class="kpi-ico"><i data-lucide="cloud-upload"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['my_total'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['my_approved'] ?> approved &middot; <?= (int)$stats['my_draft'] ?> draft</div>
    </a>
</div>

<?php elseif ($userRole === 'department_head'): ?>
<div class="kpi-grid">
    <a href="<?= APP_URL ?>/approvals.php" class="kpi kpi-primary <?= $actionableWaiting === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,149,0;">
        <div class="kpi-top"><span class="kpi-label">Needs Your Approval</span><span class="kpi-ico"><i data-lucide="clock"></i></span></div>
        <div class="kpi-num"><?= $actionableWaiting ?></div>
        <div class="kpi-hint"><?= $actionableWaiting === 0 ? 'Queue is clear' : 'Staff files awaiting your decision' ?></div>
    </a>
    <a href="<?= APP_URL ?>/deadlines.php" class="kpi kpi-primary <?= (int)$stats['expiring_soon'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,59,48;">
        <div class="kpi-top"><span class="kpi-label">Expiring Soon</span><span class="kpi-ico"><i data-lucide="triangle-alert"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['expiring_soon'] === 0 ? 'None due in 30 days' : 'Due within the next 30 days' ?></div>
    </a>
    <a href="<?= APP_URL ?>/documents.php" class="kpi <?= (int)$stats['total'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:0,113,227;">
        <div class="kpi-top"><span class="kpi-label">Department Files</span><span class="kpi-ico"><i data-lucide="folder-open"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['total'] ?></div>
        <div class="kpi-hint">Total in your department</div>
    </a>
    <a href="<?= APP_URL ?>/documents.php?status=approved" class="kpi <?= (int)$stats['approved'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:52,199,89;">
        <div class="kpi-top"><span class="kpi-label">Approved Files</span><span class="kpi-ico"><i data-lucide="circle-check"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['approved'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['draft'] ?> in draft &middot; <?= (int)$stats['rejected'] ?> rejected</div>
    </a>
</div>

<?php elseif ($userRole === 'viewer'): ?>
<div class="kpi-grid">
    <a href="<?= APP_URL ?>/documents.php" class="kpi kpi-primary <?= (int)$stats['total'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:0,113,227;">
        <div class="kpi-top"><span class="kpi-label">Available Documents</span><span class="kpi-ico"><i data-lucide="folder-open"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['total'] ?></div>
        <div class="kpi-hint">Approved published documents</div>
    </a>
    <a href="<?= APP_URL ?>/deadlines.php" class="kpi kpi-primary <?= (int)$stats['expiring_soon'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,59,48;">
        <div class="kpi-top"><span class="kpi-label">Expiring Soon</span><span class="kpi-ico"><i data-lucide="triangle-alert"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['expiring_soon'] === 0 ? 'None due in 30 days' : 'Due within next 30 days' ?></div>
    </a>
    <a href="<?= APP_URL ?>/recent_documents.php" class="kpi <?= empty($recentTotal) ? 'is-zero' : '' ?>" style="--kpi-rgb:52,199,89;">
        <div class="kpi-top"><span class="kpi-label">Recent Releases</span><span class="kpi-ico"><i data-lucide="clock-3"></i></span></div>
        <div class="kpi-num"><?= $recentTotal ?></div>
        <div class="kpi-hint">Recently updated documents</div>
    </a>
    <div class="kpi" style="--kpi-rgb:88,86,214;">
        <div class="kpi-top"><span class="kpi-label">Categories</span><span class="kpi-ico"><i data-lucide="tags"></i></span></div>
        <div class="kpi-num"><?= count($catChartRows) ?></div>
        <div class="kpi-hint">Active document categories</div>
    </div>
</div>

<?php else: // Admin ?>
<div class="kpi-grid">
    <a href="<?= APP_URL ?>/approvals.php" class="kpi kpi-primary <?= $actionableWaiting === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,149,0;">
        <div class="kpi-top"><span class="kpi-label">Pending Approvals</span><span class="kpi-ico"><i data-lucide="clock"></i></span></div>
        <div class="kpi-num"><?= $actionableWaiting ?></div>
        <div class="kpi-hint"><?= $actionableWaiting === 0 ? 'Queue is clear' : 'Awaiting approval decision' ?></div>
    </a>
    <a href="<?= APP_URL ?>/deadlines.php" class="kpi kpi-primary <?= (int)$stats['expiring_soon'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:255,59,48;">
        <div class="kpi-top"><span class="kpi-label">Expiring Soon</span><span class="kpi-ico"><i data-lucide="triangle-alert"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['expiring_soon'] === 0 ? 'None due in 30 days' : 'Due within the next 30 days' ?></div>
    </a>
    <a href="<?= APP_URL ?>/documents.php" class="kpi <?= (int)$stats['total'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:0,113,227;">
        <div class="kpi-top"><span class="kpi-label">Total Documents</span><span class="kpi-ico"><i data-lucide="folder-open"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['total'] ?></div>
        <div class="kpi-hint">Across all departments</div>
    </a>
    <a href="<?= APP_URL ?>/documents.php?status=approved" class="kpi <?= (int)$stats['approved'] === 0 ? 'is-zero' : '' ?>" style="--kpi-rgb:52,199,89;">
        <div class="kpi-top"><span class="kpi-label">Approved Documents</span><span class="kpi-ico"><i data-lucide="circle-check"></i></span></div>
        <div class="kpi-num"><?= (int)$stats['approved'] ?></div>
        <div class="kpi-hint"><?= (int)$stats['draft'] ?> in draft &middot; <?= (int)$stats['rejected'] ?> rejected</div>
    </a>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="chart-grid">
    <div class="card chart-card">
        <div class="card-header"><span class="card-title"><i data-lucide="chart-pie" style="color:var(--apple-blue);margin-right:8px;"></i>Status Breakdown</span></div>
        <div class="card-body chart-body">
            <div class="chart-box chart-box-donut"><canvas id="chStatus" aria-label="Documents by status" role="img"></canvas></div>
            <ul class="chart-legend">
                <?php foreach ($statusChart as $row): ?>
                <li><span class="dot" style="background:<?= $row['color'] ?>;"></span><?= htmlspecialchars($row['label']) ?><b><?= (int)$row['value'] ?></b></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><span class="card-title"><i data-lucide="trending-up" style="color:var(--apple-blue);margin-right:8px;"></i>Uploads &middot; Last 6 Months</span></div>
        <div class="card-body chart-body">
            <div class="chart-box"><canvas id="chMonthly" aria-label="Documents uploaded per month" role="img"></canvas></div>
        </div>
    </div>
    <?php
    $showCategoryChart = ($userRole === 'staff') || (count($chartData['dept']['values']) <= 1 && !empty($chartData['category']['values']));
    $barChartTitle = $showCategoryChart ? 'Documents by Category' : 'Documents by Department';
    $barChartIcon  = $showCategoryChart ? 'tags' : 'building-2';
    $barChartEmpty = $showCategoryChart ? 'No categorized documents yet' : 'No department documents yet';
    $barDataValues = $showCategoryChart ? $chartData['category']['values'] : $chartData['dept']['values'];
    ?>
    <div class="card chart-card">
        <div class="card-header"><span class="card-title"><i data-lucide="<?= $barChartIcon ?>" style="color:var(--apple-blue);margin-right:8px;"></i><?= $barChartTitle ?></span></div>
        <div class="card-body chart-body">
            <?php if (array_sum($barDataValues) === 0): ?>
                <div class="chart-empty"><i data-lucide="<?= $barChartIcon ?>"></i><span><?= $barChartEmpty ?></span></div>
            <?php else: ?>
                <div class="chart-box"><canvas id="chDept" aria-label="<?= $barChartTitle ?>" role="img"></canvas></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Deadlines + approvals -->
<div class="content-split attention-split<?= $canSeeApprovals ? '' : ' single' ?>">
<!-- Upcoming Deadlines -->
<div class="card" id="upcomingDeadlinesCard">
    <div class="card-header">
        <span class="card-title"><i data-lucide="calendar-clock" style="color:var(--apple-orange);margin-right:8px;"></i>Upcoming Document Deadlines</span>
        <div style="display:flex;gap:7px;"><a href="<?= APP_URL ?>/calendar.php" class="btn btn-outline btn-sm"><i data-lucide="calendar-days"></i> Calendar</a><a href="<?= APP_URL ?>/deadlines.php" class="btn btn-outline btn-sm">View All</a></div>
    </div>
    <div class="table-wrap">
        <table><thead><tr><th>Document</th><th>Category</th><th>Deadline</th><th>Time Remaining</th></tr></thead><tbody>
        <?php if (!$upcomingDeadlines): ?><tr><td colspan="4" class="text-center text-muted" style="padding:28px;">No upcoming deadlines.</td></tr>
        <?php else: foreach ($upcomingDeadlines as $deadline): $days=max(0,(int)floor((strtotime($deadline['expires_at'])-strtotime(date('Y-m-d')))/86400)); ?>
        <tr><td class="fw-600"><a href="<?= APP_URL ?>/document_view.php?id=<?= (int)$deadline['id'] ?>" style="color:var(--text-primary);text-decoration:none;"><?= htmlspecialchars(doc_title($deadline['title'], $deadline['reference_number'] ?? null)) ?></a></td><td class="text-sm"><?= htmlspecialchars($deadline['category_name'] ?? '—') ?></td><td class="text-sm"><?= date('M d, Y', strtotime($deadline['expires_at'])) ?></td><td><?php [$dlClass,$dlLabel]=deadline_badge($days); ?><span class="badge <?= $dlClass ?>"><?= $dlLabel ?></span></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
</div>


    <!-- Needs your approval -->
    <?php if ($canSeeApprovals): ?>
    <div class="card approvals-card">
        <div class="card-header">
            <span class="card-title"><i data-lucide="list-todo" style="color:var(--apple-orange);margin-right:8px;"></i><?= $apprTitle ?><?php if ($apprCount > 0): ?> <span class="nav-badge" style="margin-left:8px;"><?= $apprCount ?></span><?php endif; ?></span>
            <a href="<?= APP_URL ?>/approvals.php" class="btn btn-outline btn-sm">Open</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (!$apprList): ?>
            <div class="chart-empty" style="padding:36px 20px;"><i data-lucide="circle-check"></i><span>You're all caught up</span></div>
            <?php else: ?>
            <ul class="appr-list">
                <?php foreach ($apprList as $a): ?>
                <li>
                    <a href="<?= APP_URL ?>/document_view.php?id=<?= (int)$a['id'] ?>">
                        <span class="appr-title"><?= htmlspecialchars(doc_title($a['title'], $a['reference_number'] ?? null)) ?></span>
                        <span class="appr-meta"><?= htmlspecialchars($a['uploader'] ?? 'Unknown') ?> &middot; <?= date('M d', strtotime($a['updated_at'])) ?></span>
                    </a>
                    <span class="badge <?= $statusColors[$a['status']] ?? 'badge-draft' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($apprCount > count($apprList)): ?>
            <div class="appr-more"><a href="<?= APP_URL ?>/approvals.php">+<?= $apprCount - count($apprList) ?> more</a></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Main Split: Recent Documents & Activity -->
<div class="content-split dashboard-split">

    <!-- Recent Documents -->
    <div class="card" id="recentDocs">
        <div class="card-header">
            <span class="card-title"><i data-lucide="history" style="color:var(--apple-blue); margin-right:8px;"></i>Recent Documents</span>
            <div style="display:flex;gap:7px;"><a href="<?= APP_URL ?>/recent_documents.php" class="btn btn-outline btn-sm">Recent</a><a href="<?= APP_URL ?>/documents.php" class="btn btn-outline btn-sm">View All</a></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th style="white-space:nowrap;">Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentDocs)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding:48px 20px;">
                        <i data-lucide="folder-open" style="font-size:32px; color:#d2d2d7; display:block; margin-bottom:10px;"></i>
                        No documents yet. <a href="<?= APP_URL ?>/upload.php" style="color:var(--apple-blue); text-decoration:none; font-weight:600;">Upload a document →</a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($recentDocs as $doc): ?>
                <tr>
                    <td>
                        <div class="fw-600" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; letter-spacing:-0.01em;">
                            <?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>
                        </div>
                        <div class="text-sm text-muted"><?= htmlspecialchars($doc['uploaded_by_name'] ?? 'Unknown') ?></div>
                    </td>
                    <td>
                        <?php if ($doc['category_name']): ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:500;">
                            <span style="width:7px; height:7px; border-radius:50%; background:<?= htmlspecialchars($doc['category_color']) ?>; display:inline-block;"></span>
                            <?= htmlspecialchars($doc['category_name']) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusColors[$doc['status']] ?? 'badge-draft' ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span></td>
                    <td><span class="priority-<?= $doc['priority'] ?>"><?= ucfirst($doc['priority']) ?></span></td>
                    <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y', strtotime($doc['updated_at'])) ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/document_view.php?id=<?= $doc['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View Document">
                            <i data-lucide="arrow-right" style="font-size:11px;"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($recentPage, $recentPages, 'rd_page', 'recentDocs') ?>
    </div>

    <!-- Activity Stream -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i data-lucide="chart-no-axes-column-increasing" style="color:var(--apple-purple); margin-right:8px;"></i>Activity</span>
            <a href="<?= APP_URL ?>/activity_log.php" class="btn btn-outline btn-sm">Log</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($activities)): ?>
            <p class="text-muted text-center" style="padding:32px;">No activity recorded yet.</p>
            <?php else: ?>
            <ul style="list-style:none;">
            <?php foreach ($activities as $act): ?>
            <?php $actData = $actionIcons[$act['action']] ?? ['circle', 'rgba(142, 142, 147, 0.15)', 'var(--apple-gray)']; ?>
            <li style="padding:12px 18px; border-bottom:1px solid rgba(0, 0, 0, 0.04); display:flex; gap:12px; align-items:flex-start;">
                <div style="width:30px; height:30px; border-radius:8px; background:<?= $actData[1] ?>; color:<?= $actData[2] ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px; margin-top:2px;">
                    <i data-lucide="<?= $actData[0] ?>"></i>
                </div>
                <div>
                    <div style="font-size:12.5px; line-height:1.4;">
                        <strong><?= htmlspecialchars($act['user_name'] ?? 'System') ?></strong> <?= htmlspecialchars($act['description']) ?>
                    </div>
                    <div class="text-sm text-muted" style="margin-top:2px; font-size:11px; white-space:nowrap;">
                        <?= date('M d, g:i a', strtotime($act['created_at'])) ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

</div><!-- /.dash -->

<script src="<?= APP_URL ?>/assets/js/chart.umd.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
    // Greeting by the visitor's local time
    var h = new Date().getHours();
    var g = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
    var gw = document.getElementById('greetWord'); if (gw) gw.textContent = g;

    var D = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('.chart-box').forEach(function (b) { b.innerHTML = '<div class="chart-empty"><span>Charts unavailable (Chart.js failed to load)</span></div>'; });
        return;
    }

    function css(n) {
        return (getComputedStyle(document.documentElement).getPropertyValue(n) || '').trim();
    }

    function hexToRgba(color, alpha) {
        if (!color) return 'rgba(0,113,227,' + alpha + ')';
        var c = color.trim();
        if (c.startsWith('#')) {
            var hex = c.substring(1);
            if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            var num = parseInt(hex, 16);
            if (!isNaN(num)) {
                return 'rgba(' + ((num >> 16) & 255) + ',' + ((num >> 8) & 255) + ',' + (num & 255) + ',' + alpha + ')';
            }
        }
        if (c.startsWith('rgb(')) {
            return c.replace('rgb(', 'rgba(').replace(')', ',' + alpha + ')');
        }
        return c;
    }

    var charts = [];

    function build() {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
        charts = [];

        var text = css('--text-secondary') || '#86868b';
        var grid = css('--border-hairline') || 'rgba(0,0,0,0.08)';
        var strong = css('--text-primary') || '#1d1d1f';
        var blue = css('--apple-blue') || '#0071e3';
        var card = css('--bg-card') || '#ffffff';
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        Chart.defaults.color = text;
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
        Chart.defaults.font.size = 11.5;
        Chart.defaults.animation = false;
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        var intTicks = { precision: 0 };

        // 1. Donut — status breakdown (total in the middle)
        var elStatus = document.getElementById('chStatus');
        if (elStatus) {
            var empty = !D.status || D.status.total === 0;
            var statusColors = empty ? [grid] : (D.status.colors || []);
            charts.push(new Chart(elStatus, {
                type: 'doughnut',
                data: {
                    labels: empty ? ['No documents'] : D.status.labels,
                    datasets: [{
                        data: empty ? [1] : D.status.values,
                        backgroundColor: statusColors,
                        borderColor: card,
                        borderWidth: 3,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: !empty }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw: function (c) {
                        var a = c.chartArea;
                        if (!a) return;
                        var x = (a.left + a.right) / 2;
                        var y = (a.top + a.bottom) / 2;
                        var ctx = c.ctx;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = strong;
                        ctx.font = '700 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                        ctx.fillText(String(D.status ? D.status.total : 0), x, y - 6);
                        ctx.fillStyle = text;
                        ctx.font = '500 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                        ctx.fillText('documents', x, y + 14);
                        ctx.restore();
                    }
                }]
            }));
        }

        // 2. Line — uploads per month
        var elMonthly = document.getElementById('chMonthly');
        if (elMonthly && D.monthly) {
            charts.push(new Chart(elMonthly, {
                type: 'line',
                data: {
                    labels: D.monthly.labels,
                    datasets: [{
                        label: 'Documents uploaded',
                        data: D.monthly.values,
                        borderColor: blue,
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: blue,
                        pointBorderColor: card,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        backgroundColor: function (ctx) {
                            var a = ctx.chart.chartArea;
                            if (!a || a.bottom === a.top) return hexToRgba(blue, 0.15);
                            var g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
                            g.addColorStop(0, hexToRgba(blue, 0.28));
                            g.addColorStop(1, hexToRgba(blue, 0.02));
                            return g;
                        }
                    }]
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, ticks: intTicks, grid: { color: grid }, border: { display: false } }
                    }
                }
            }));
        }

        // 3. Horizontal bar — documents per department or per category
        var elDept = document.getElementById('chDept');
        if (elDept) {
            var useCategory = <?= $showCategoryChart ? 'true' : 'false' ?>;
            var barLabels = useCategory ? (D.category ? D.category.labels : []) : (D.dept ? D.dept.labels : []);
            var barValues = useCategory ? (D.category ? D.category.values : []) : (D.dept ? D.dept.values : []);
            charts.push(new Chart(elDept, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        label: 'Documents',
                        data: barValues,
                        backgroundColor: hexToRgba(blue, 0.82),
                        hoverBackgroundColor: blue,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: intTicks, grid: { color: grid }, border: { display: false } },
                        y: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                callback: function (v) {
                                    var l = String(this.getLabelForValue(v) || '');
                                    return l.length > 16 ? l.slice(0, 15) + '\u2026' : l;
                                }
                            }
                        }
                    }
                }
            }));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }

    // Rebuild smoothly when the theme changes (debounced)
    var debounceTimer;
    new MutationObserver(function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(build, 80);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
