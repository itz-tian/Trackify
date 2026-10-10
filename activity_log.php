<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
$db   = Database::getInstance();

$page    = max(1,(int)($_GET['page']??1));
$perPage = 25;
$search  = $_GET['search'] ?? '';
$userId  = $_GET['user_id'] ?? '';
$action  = $_GET['action'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) { $where[]='al.description LIKE ?'; $params[]='%'.$search.'%'; }
if ($userId) { $where[]='al.user_id = ?'; $params[]=$userId; }
if ($action) { $where[]='al.action = ?'; $params[]=$action; }
// Who may see whose activity: admin = everyone, department head = their department, staff/viewer = only themselves.
$myRole = $_SESSION['user_role'] ?? 'staff';
$myDept = (int)($_SESSION['user_dept'] ?? 0);
if ($myRole === 'department_head') {
    $where[] = 'al.user_id IN (SELECT id FROM users WHERE department_id = ?)'; $params[] = $myDept;
} elseif ($myRole !== 'admin') {
    $where[] = 'al.user_id = ?'; $params[] = (int)($_SESSION['user_id'] ?? 0);
}

$whereSQL = implode(' AND ',$where);
$offset   = ($page-1)*$perPage;

$total = $db->prepare("SELECT COUNT(*) FROM activity_logs al WHERE $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();

$logs = $db->prepare("
    SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, d.title AS doc_title
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id=u.id
    LEFT JOIN documents d ON al.document_id=d.id
    WHERE $whereSQL ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset
");
$logs->execute($params);
$logs = $logs->fetchAll();

if ($myRole === 'admin') {
    $users = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users ORDER BY first_name")->fetchAll();
} elseif ($myRole === 'department_head') {
    $uStmt = $db->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE department_id = ? ORDER BY first_name");
    $uStmt->execute([$myDept]); $users = $uStmt->fetchAll();
} else {
    $uStmt = $db->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE id = ?");
    $uStmt->execute([(int)($_SESSION['user_id'] ?? 0)]); $users = $uStmt->fetchAll();
}
$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$actionColors = [
    'login'=>['#dcfce7','#166534'],'logout'=>['#f3f4f6','#4b5563'],
    'upload'=>['#dbeafe','#1e40af'],'view'=>['#f0f9ff','#0369a1'],
    'status_change'=>['#fef3c7','#92400e'],'delete'=>['#fee2e2','#991b1b'],
    'comment'=>['#f5f3ff','#6d28d9'],'approval'=>['#ecfdf5','#065f46'],
    'download'=>['#e0f2fe','#075985'],'restore'=>['#dcfce7','#166534'],'permanent_delete'=>['#fee2e2','#991b1b'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Filters -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px;">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search activity…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:160px;">
            <?php if ($_SESSION['user_role']!=='staff'): ?>
            <select name="user_id" style="min-width:150px;">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $userId==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select name="action" style="min-width:130px;">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= $a ?>" <?= $action===$a?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$a)) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search"></i> Filter</button>
                <a href="activity_log.php" class="btn btn-outline btn-sm"><i data-lucide="x"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><i data-lucide="history" style="color:var(--primary);margin-right:8px;"></i>Activity Log</span>
        <span class="text-muted text-sm"><?= $total ?> entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Document</th>
                    <th>IP Address</th>
                    <th style="white-space:nowrap;">Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:40px;">No activity found.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <?php $ac = $actionColors[$log['action']] ?? ['#f8fafc','#64748b']; ?>
            <tr>
                <td class="fw-600 text-sm"><?= htmlspecialchars($log['user_name']) ?></td>
                <td>
                    <span style="background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap;">
                        <?= ucfirst(str_replace('_',' ',$log['action'])) ?>
                    </span>
                </td>
                <td class="text-sm" style="max-width:220px;"><?= htmlspecialchars($log['description']) ?></td>
                <td class="text-sm">
                    <?php if ($log['doc_title']): ?>
                    <a href="document_view.php?id=<?= $log['document_id'] ?>" style="color:var(--primary);"><?= htmlspecialchars($log['doc_title']) ?></a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-sm text-muted" style="white-space:nowrap;"><?= htmlspecialchars($log['ip_address']) ?></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y g:i a', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <?php if (ceil($total/$perPage)>1): ?>
    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">
        <?php for ($p=1;$p<=ceil($total/$perPage);$p++): ?>
        <a href="?<?= http_build_query(['search'=>$search,'user_id'=>$userId,'action'=>$action,'page'=>$p]) ?>"
           class="btn btn-sm <?= $p===$page?'btn-primary':'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
