<?php



require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
$auth = new Auth(); $auth->requireLogin();
$db   = Database::getInstance();

$role   = $_SESSION['user_role'] ?? 'staff';
$userId = (int)($_SESSION['user_id'] ?? 0);
$dept   = (int)($_SESSION['user_dept'] ?? 0);
$isAdmin = $role === 'admin';
$canShare = $role !== 'viewer' && $dept > 0;   


if (empty($_SESSION['cal_tasks_table_ok'])) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS calendar_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            due_date DATE NOT NULL,
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            is_done TINYINT(1) NOT NULL DEFAULT 0,
            visibility ENUM('private','department') NOT NULL DEFAULT 'private',
            department_id INT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ct_due (due_date),
            INDEX idx_ct_user (created_by),
            INDEX idx_ct_dept (department_id),
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['cal_tasks_table_ok'] = true;
    } catch (Throwable $e) {  }
}


function cal_load_task(PDO $db, int $id, int $userId, int $dept, bool $isAdmin): ?array {
    $st = $db->prepare("SELECT * FROM calendar_tasks WHERE id = ?");
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) return null;
    $mine    = (int)$t['created_by'] === $userId;
    $visible = $mine || ($t['visibility'] === 'department' && ($isAdmin || (int)$t['department_id'] === $dept));
    if (!$visible) return null;
    $t['can_edit'] = $mine || $isAdmin;
    return $t;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $action  = (string)$_POST['action'];
    $backTo  = APP_URL . '/calendar.php';
    $redirDate = (string)($_POST['due_date'] ?? '');

    try {
        if ($action === 'task_save') {
            $tid   = (int)($_POST['task_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc  = trim((string)($_POST['description'] ?? ''));
            $due   = (string)($_POST['due_date'] ?? '');
            $prio  = (string)($_POST['priority'] ?? 'normal');
            $vis   = (string)($_POST['visibility'] ?? 'private');
            $dt    = DateTime::createFromFormat('Y-m-d', $due);
            $okDate = $dt && $dt->format('Y-m-d') === $due;

            if ($title === '' || mb_strlen($title) > 200)            flash_set('error', 'Please enter a task title (up to 200 characters).');
            elseif (!$okDate)                                        flash_set('error', 'Please choose a valid due date.');
            elseif (!in_array($prio, ['low','normal','high','urgent'], true)) flash_set('error', 'Invalid priority.');
            else {
                if (mb_strlen($desc) > 1000) $desc = mb_substr($desc, 0, 1000);
                if ($vis !== 'department' || !$canShare) $vis = 'private';
                $deptVal = $vis === 'department' ? $dept : null;
                if ($tid > 0) {
                    $t = cal_load_task($db, $tid, $userId, $dept, $isAdmin);
                    if (!$t || !$t['can_edit']) { flash_set('error', 'You cannot edit that task.'); }
                    else {
                        
                        if ((int)$t['created_by'] !== $userId) { $vis = $t['visibility']; $deptVal = $t['department_id']; }
                        $db->prepare("UPDATE calendar_tasks SET title=?, description=?, due_date=?, priority=?, visibility=?, department_id=? WHERE id=?")
                           ->execute([$title, $desc !== '' ? $desc : null, $due, $prio, $vis, $deptVal, $tid]);
                        flash_set('success', 'Task updated.');
                    }
                } else {
                    $db->prepare("INSERT INTO calendar_tasks (title, description, due_date, priority, visibility, department_id, created_by) VALUES (?,?,?,?,?,?,?)")
                       ->execute([$title, $desc !== '' ? $desc : null, $due, $prio, $vis, $deptVal, $userId]);
                    flash_set('success', 'Task added to your calendar.');
                }
                $backTo .= '?d=' . urlencode($due);
            }
        } elseif ($action === 'task_toggle' || $action === 'task_delete') {
            $t = cal_load_task($db, (int)($_POST['task_id'] ?? 0), $userId, $dept, $isAdmin);
            if (!$t || !$t['can_edit']) {
                flash_set('error', 'You cannot change that task.');
            } elseif ($action === 'task_toggle') {
                $db->prepare("UPDATE calendar_tasks SET is_done = 1 - is_done WHERE id = ?")->execute([(int)$t['id']]);
                flash_set('success', ((int)$t['is_done'] === 0) ? 'Task marked as done.' : 'Task reopened.');
                $backTo .= '?d=' . urlencode((string)$t['due_date']);
            } else {
                $db->prepare("DELETE FROM calendar_tasks WHERE id = ?")->execute([(int)$t['id']]);
                flash_set('success', 'Task deleted.');
                $backTo .= '?d=' . urlencode((string)$t['due_date']);
            }
        }
    } catch (Throwable $e) {
        flash_set('error', 'Could not save the task. Please try again.');
    }
    redirect_to($backTo);
}
['success' => $success, 'error' => $error] = flash_pull();


$scope  = 'd.deleted_at IS NULL';
$params = [];
if ($role === 'viewer') {
    $scope .= " AND d.status = 'approved'";
} elseif (!$isAdmin) {
    $scope .= ' AND d.department_id = ?';
    $params[] = $dept;
}

$events = [];


try {
    $stmt = $db->prepare("
        SELECT t.id AS tid, t.title, t.description AS descr, t.due_date AS due, t.priority, t.is_done, t.visibility, t.created_by,
               CONCAT(u.first_name, ' ', u.last_name) AS owner, dep.name AS department
        FROM calendar_tasks t
        LEFT JOIN users u ON u.id = t.created_by
        LEFT JOIN departments dep ON dep.id = t.department_id
        WHERE t.created_by = ? OR (t.visibility = 'department' AND (t.department_id = ? OR ? = 1))
        ORDER BY t.due_date ASC, t.id ASC LIMIT 2000
    ");
    $stmt->execute([$userId, $dept, $isAdmin ? 1 : 0]);
    foreach ($stmt->fetchAll() as $r) {
        $events[] = [
            'kind' => 'task', 'tid' => (int)$r['tid'], 'title' => $r['title'], 'descr' => $r['descr'], 'due' => $r['due'],
            'priority' => $r['priority'], 'done' => (int)$r['is_done'] === 1, 'visibility' => $r['visibility'],
            'owner' => $r['owner'], 'department' => $r['department'], 'mine' => (int)$r['created_by'] === $userId,
            'can_edit' => ((int)$r['created_by'] === $userId) || $isAdmin,
        ];
    }
} catch (Throwable $e) {  }


$stmt = $db->prepare("
    SELECT d.id, d.title, d.reference_number AS ref, d.expires_at AS due, d.status, d.priority,
           c.name AS category, dep.name AS department
    FROM documents d
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    WHERE $scope AND d.expires_at IS NOT NULL
    ORDER BY d.expires_at ASC LIMIT 2000
");
$stmt->execute($params);
foreach ($stmt->fetchAll() as $r) { $r['kind'] = 'expiry'; $r['title'] = doc_title($r['title'], $r['ref'] ?? null); $events[] = $r; }



if ($role !== 'viewer') {
    $apprWhere  = $scope . " AND d.status IN ('pending_review','under_review')";
    $apprParams = array_merge([(int)APPROVAL_SLA_DAYS], $params);
    if ($role === 'staff') {
        $apprWhere .= ' AND d.uploaded_by = ?';
        $apprParams[] = $userId;
    } elseif ($role === 'department_head') {
        $apprWhere .= " AND NOT EXISTS (SELECT 1 FROM approval_workflows x WHERE x.document_id = d.id AND x.approver_id = ? AND x.status <> 'pending')";
        $apprParams[] = $userId;
    }
    $stmt = $db->prepare("
        SELECT d.id, d.title, d.reference_number AS ref,
               MIN(COALESCE(aw.deadline, DATE_ADD(DATE(d.created_at), INTERVAL ? DAY))) AS due,
               d.status, d.priority, c.name AS category, dep.name AS department
        FROM documents d
        LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.status = 'pending'
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        WHERE $apprWhere
        GROUP BY d.id, d.title, d.reference_number, d.status, d.priority, c.name, dep.name
        ORDER BY due ASC LIMIT 2000
    ");
    $stmt->execute($apprParams);
    foreach ($stmt->fetchAll() as $r) { $r['kind'] = 'approval'; $r['title'] = doc_title($r['title'], $r['ref'] ?? null); $events[] = $r; }
}

$eventsJson = json_encode(array_values($events), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
$hasApprovalKind = $role !== 'viewer';

include __DIR__ . '/includes/header.php';
?>
<style>
    .cal-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    .cal-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 16px 20px; border-bottom: 1px solid var(--border-hairline); }
    .cal-nav { display: flex; align-items: center; gap: 8px; }
    .cal-month { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); min-width: 170px; }
    .cal-iconbtn { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid var(--border-hairline); background: var(--bg-card-subtle); color: var(--text-primary); cursor: pointer; transition: background .15s ease, transform .15s ease; }
    .cal-iconbtn:hover { background: var(--apple-blue-subtle); transform: translateY(-1px); }
    .cal-iconbtn svg { width: 16px; height: 16px; }
    .cal-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .cal-pill { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 980px; border: 1px solid var(--border-hairline); background: var(--bg-card-subtle); color: var(--text-secondary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .15s ease; }
    .cal-pill .sw { width: 9px; height: 9px; border-radius: 3px; background: var(--apple-blue); }
    .cal-pill.k-task .sw { background: var(--apple-indigo); }
    .cal-pill.k-approval .sw { background: transparent; border: 1.5px dashed var(--apple-orange); }
    .cal-pill.off { opacity: .45; }
    .cal-pill:hover { border-color: var(--apple-blue); }

    .cal-dow { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); padding: 10px 0 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-tertiary); text-align: center; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 1px; background: var(--border-hairline); border-top: 1px solid var(--border-hairline); }
    .cal-day { position: relative; min-height: 108px; padding: 8px 8px 6px; background: var(--bg-card); border: 0; text-align: left; font-family: inherit; color: var(--text-primary); cursor: pointer; display: flex; flex-direction: column; gap: 4px; min-width: 0; transition: background .15s ease; }
    .cal-day:hover { background: var(--bg-card-subtle); }
    .cal-day:focus-visible { outline: 2px solid var(--apple-blue); outline-offset: -2px; z-index: 1; }
    .cal-day.out { color: var(--text-tertiary); background: var(--bg-card-subtle); }
    .cal-day.out .cal-num { opacity: .55; }
    .cal-day.sel { box-shadow: inset 0 0 0 2px var(--apple-blue); z-index: 1; }
    .cal-num { font-size: 12.5px; font-weight: 600; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
    .cal-day.today .cal-num { background: var(--apple-blue); color: #fff; }
    .cal-chip { display: block; font-size: 11px; font-weight: 600; line-height: 1.35; padding: 2px 6px; border-radius: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid transparent; }
    .cal-chip.k-approval { border: 1px dashed currentColor; background-clip: padding-box; }
    .cal-chip.k-task { border-left: 3px solid var(--apple-indigo); border-radius: 4px 6px 6px 4px; }
    .cal-chip.done { background: var(--bg-card-subtle); color: var(--text-tertiary); text-decoration: line-through; border-left-color: var(--text-tertiary); }
    .cal-more { font-size: 11px; color: var(--text-secondary); font-weight: 600; padding-left: 4px; }
    .cal-dots { display: none; gap: 3px; margin-top: 2px; }
    .cal-dot { width: 6px; height: 6px; border-radius: 50%; }
    .cal-dot.done { background: var(--text-tertiary); }

    
    .u-overdue { background: var(--apple-red); color: #fff; }
    .u-urgent  { background: rgba(255, 59, 48, .14);  color: #d70015; }
    .u-soon    { background: rgba(255, 149, 0, .17);  color: #b25e00; }
    .u-later   { background: rgba(0, 113, 227, .12);  color: #0062c4; }
    [data-theme="dark"] .u-urgent { color: #ff6961; }
    [data-theme="dark"] .u-soon   { color: #ffb340; }
    [data-theme="dark"] .u-later  { color: #64a8ff; }
    .cal-dot.u-overdue, .cal-dot.u-urgent { background: var(--apple-red); }
    .cal-dot.u-soon { background: var(--apple-orange); }
    .cal-dot.u-later { background: var(--apple-blue); }

    .cal-legend { display: flex; gap: 14px; flex-wrap: wrap; padding: 12px 20px 16px; font-size: 11.5px; color: var(--text-secondary); }
    .cal-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .cal-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

    
    .cal-side { display: flex; flex-direction: column; gap: 20px; }
    .cal-side-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; width: 100%; }
    .cal-side-title { font-size: 15px; font-weight: 700; letter-spacing: -0.01em; color: var(--text-primary); }
    .cal-side-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
    .cal-item { display: flex; flex-direction: column; gap: 6px; padding: 12px 16px; border-bottom: 1px solid var(--border-hairline); transition: background .15s ease; }
    .cal-item:last-child { border-bottom: 0; }
    .cal-item:hover { background: var(--bg-card-subtle); }
    .cal-item a, .cal-item .cal-ttl { color: var(--text-primary); text-decoration: none; font-weight: 600; font-size: 13.5px; }
    .cal-item a:hover { color: var(--apple-blue); }
    .cal-item.done .cal-ttl { text-decoration: line-through; color: var(--text-tertiary); }
    .cal-desc { font-size: 12.5px; color: var(--text-secondary); white-space: pre-wrap; word-break: break-word; }
    .cal-meta { font-size: 11.5px; color: var(--text-secondary); }
    .cal-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .cal-tag { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 980px; }
    .cal-tag.plain { background: var(--bg-card-subtle); color: var(--text-secondary); border: 1px solid var(--border-hairline); }
    .cal-tag.task { background: rgba(88, 86, 214, .14); color: var(--apple-indigo); }
    .cal-tag.ok { background: rgba(52, 199, 89, .16); color: #1f9d46; }
    .cal-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
    .cal-actions form { margin: 0; }
    .cal-empty { padding: 30px 18px; text-align: center; color: var(--text-tertiary); font-size: 13px; }
    .cal-next { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 16px; background: none; border: 0; border-bottom: 1px solid var(--border-hairline); text-align: left; font-family: inherit; cursor: pointer; color: inherit; transition: background .15s ease; }
    .cal-next:last-child { border-bottom: 0; }
    .cal-next:hover { background: var(--bg-card-subtle); }
    .cal-next .d { width: 38px; text-align: center; flex-shrink: 0; }
    .cal-next .d b { display: block; font-size: 16px; line-height: 1.1; color: var(--text-primary); }
    .cal-next .d small { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-secondary); }
    .cal-next .t { min-width: 0; flex: 1; }
    .cal-next .t span { display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cal-next .t small { font-size: 11px; color: var(--text-secondary); }
    .cal-hint { font-size: 11.5px; color: var(--text-tertiary); margin-top: -4px; }

    @media (max-width: 1100px) { .cal-wrap { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 640px) {
        .cal-day { min-height: 56px; padding: 6px 4px; align-items: center; }
        .cal-chip, .cal-more { display: none; }
        .cal-dots { display: flex; }
        .cal-toolbar { padding: 12px; }
        .cal-month { font-size: 16px; min-width: 0; }
    }
    @media (prefers-reduced-motion: reduce) { .cal-day, .cal-iconbtn, .cal-item, .cal-next, .cal-pill { transition: none; } }
</style>

<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="cal-wrap">
    
    <div class="card">
        <div class="cal-toolbar">
            <div class="cal-nav">
                <button type="button" class="cal-iconbtn" id="calPrev" aria-label="Previous month"><i data-lucide="chevron-left"></i></button>
                <div class="cal-month" id="calMonth" aria-live="polite"></div>
                <button type="button" class="cal-iconbtn" id="calNext" aria-label="Next month"><i data-lucide="chevron-right"></i></button>
                <button type="button" class="btn btn-outline btn-sm" id="calToday">Today</button>
            </div>
            <div class="cal-filters">
                <button type="button" class="cal-pill k-task" data-kind="task" aria-pressed="true"><span class="sw"></span>Tasks</button>
                <button type="button" class="cal-pill k-expiry" data-kind="expiry" aria-pressed="true"><span class="sw"></span>Document deadlines</button>
                <?php if ($hasApprovalKind): ?>
                <button type="button" class="cal-pill k-approval" data-kind="approval" aria-pressed="true"><span class="sw"></span>Approval due</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" id="calNewTask"><i data-lucide="plus"></i> New Task</button>
            </div>
        </div>
        <div class="cal-dow"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>
        <div class="cal-grid" id="calGrid" role="grid" aria-label="Deadline calendar"></div>
        <div class="cal-legend">
            <span><i class="u-overdue"></i>Overdue</span>
            <span><i class="u-urgent"></i>Within 3 days</span>
            <span><i class="u-soon"></i>Within 7 days</span>
            <span><i class="u-later"></i>Later</span>
            <span><i style="background:var(--apple-indigo);"></i>Task (left bar)</span>
            <?php if ($hasApprovalKind): ?><span>Dashed outline = approval due date</span><?php endif; ?>
        </div>
    </div>

    
    <div class="cal-side">
        <div class="card">
            <div class="card-header">
                <div class="cal-side-head">
                    <div>
                        <div class="cal-side-title" id="calSelTitle">Selected day</div>
                        <div class="cal-side-sub" id="calSelSub"></div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" id="calAddOnDay" title="Add a task on this day"><i data-lucide="plus"></i> Task</button>
                </div>
            </div>
            <div id="calSelList"></div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title"><i data-lucide="list-checks" style="color:var(--apple-blue);margin-right:8px;"></i>Next Up</span></div>
            <div id="calNextList"></div>
        </div>
    </div>
</div>


<div class="modal-overlay" id="taskModal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title" id="taskModalTitle">New Task</div>
            <button type="button" class="modal-close" id="taskModalClose" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/calendar.php" id="taskForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="task_save">
            <input type="hidden" name="task_id" id="tfId" value="">
            <div class="form-group"><label for="tfTitle">Task</label><input type="text" name="title" id="tfTitle" maxlength="200" required placeholder="e.g. Submit quarterly report"></div>
            <div class="form-row">
                <div class="form-group"><label for="tfDate">Due date</label><input type="date" name="due_date" id="tfDate" required></div>
                <div class="form-group"><label for="tfPrio">Priority</label>
                    <select name="priority" id="tfPrio"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                </div>
            </div>
            <?php if ($canShare): ?>
            <div class="form-group"><label for="tfVis">Who can see it</label>
                <select name="visibility" id="tfVis"><option value="private">Only me</option><option value="department">My department</option></select>
            </div>
            <?php endif; ?>
            <div class="form-group"><label for="tfDesc">Notes (optional)</label><textarea name="description" id="tfDesc" rows="3" maxlength="1000" placeholder="Anything worth remembering"></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
                <button type="button" class="btn btn-outline" id="taskCancel">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Save Task</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var APP_URL = <?= json_encode(APP_URL) ?>;
    var CSRF    = <?= json_encode(csrf_token()) ?>;
    var EVENTS  = <?= $eventsJson ?: '[]' ?>;
    var START_DATE = <?= json_encode(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['d'] ?? '')) ? $_GET['d'] : null) ?>;
    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var DAYS   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var STATUS_CLS = { draft:'badge-draft', pending_review:'badge-pending', under_review:'badge-review', approved:'badge-approved', rejected:'badge-rejected', archived:'badge-archived' };
    var KIND_LABEL = { task: 'Task', expiry: 'Document deadline', approval: 'Approval due' };

    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    var key = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var parse = function (k) { var p = k.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
    var now = new Date(); var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var daysFrom = function (k) { return Math.round((parse(k) - today) / 86400000); };
    var urgency = function (e) {
        if (e.kind === 'task' && e.done) return 'done';
        var d = daysFrom(e.due); return d < 0 ? 'overdue' : d <= 3 ? 'urgent' : d <= 7 ? 'soon' : 'later';
    };
    var remaining = function (k) {
        var d = daysFrom(k);
        if (d < 0) return 'Overdue by ' + (-d) + ' day' + (d === -1 ? '' : 's');
        if (d === 0) return 'Due today'; if (d === 1) return 'Due tomorrow';
        return d + ' days left';
    };
    var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
    var cap = function (s) { s = String(s || '').replace(/_/g, ' '); return s.charAt(0).toUpperCase() + s.slice(1); };

    var byDate = {};
    EVENTS.forEach(function (e) { (byDate[e.due] = byDate[e.due] || []).push(e); });
    
    var order = { task: 0, approval: 1, expiry: 2 };
    Object.keys(byDate).forEach(function (k) { byDate[k].sort(function (a, b) { return (a.done === b.done ? 0 : a.done ? 1 : -1) || order[a.kind] - order[b.kind]; }); });
    var show = { task: true, expiry: true, approval: true };
    var visible = function (list) { return (list || []).filter(function (e) { return show[e.kind]; }); };

    var selected = (START_DATE && !isNaN(parse(START_DATE))) ? START_DATE : key(today);
    var sd = parse(selected);
    var view = new Date(sd.getFullYear(), sd.getMonth(), 1);
    var grid = document.getElementById('calGrid');

    function hiddenInput(form, name, value) { var i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value; form.appendChild(i); }
    function postForm(action, task, btnText, btnClass, confirmCfg) {
        var f = document.createElement('form'); f.method = 'POST'; f.action = APP_URL + '/calendar.php';
        hiddenInput(f, 'csrf_token', CSRF); hiddenInput(f, 'action', action); hiddenInput(f, 'task_id', task.tid);
        var b = el('button', btnClass, btnText); b.type = 'submit'; f.appendChild(b);
        if (confirmCfg) { f.dataset.confirmTitle = confirmCfg.title; f.dataset.confirmMessage = confirmCfg.message; f.dataset.confirmText = confirmCfg.ok; }
        return f;
    }

    function renderGrid() {
        grid.innerHTML = '';
        document.getElementById('calMonth').textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
        var first = new Date(view.getFullYear(), view.getMonth(), 1);
        var start = new Date(first); start.setDate(1 - first.getDay());
        for (var i = 0; i < 42; i++) {
            var d = new Date(start); d.setDate(start.getDate() + i);
            var k = key(d), evs = visible(byDate[k]);
            var cell = el('button', 'cal-day' + (d.getMonth() !== view.getMonth() ? ' out' : '') + (k === key(today) ? ' today' : '') + (k === selected ? ' sel' : ''));
            cell.type = 'button'; cell.setAttribute('role', 'gridcell');
            cell.setAttribute('aria-label', DAYS[d.getDay()] + ', ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + ': ' + evs.length + ' item' + (evs.length === 1 ? '' : 's'));
            if (k === selected) cell.setAttribute('aria-selected', 'true');
            cell.appendChild(el('span', 'cal-num', d.getDate()));
            evs.slice(0, 2).forEach(function (e) {
                var u = urgency(e);
                var c = el('span', 'cal-chip ' + (u === 'done' ? 'done' : 'u-' + u) + (e.kind === 'approval' ? ' k-approval' : '') + (e.kind === 'task' ? ' k-task' : ''), e.title);
                c.title = KIND_LABEL[e.kind] + ': ' + e.title; cell.appendChild(c);
            });
            if (evs.length > 2) cell.appendChild(el('span', 'cal-more', '+' + (evs.length - 2) + ' more'));
            if (evs.length) {
                var dots = el('span', 'cal-dots');
                evs.slice(0, 3).forEach(function (e) { var u = urgency(e); dots.appendChild(el('span', 'cal-dot ' + (u === 'done' ? 'done' : 'u-' + u))); });
                cell.appendChild(dots);
            }
            cell.addEventListener('click', (function (date, kk) { return function () {
                selected = kk;
                if (date.getMonth() !== view.getMonth() || date.getFullYear() !== view.getFullYear()) view = new Date(date.getFullYear(), date.getMonth(), 1);
                renderAll();
            }; })(d, k));
            grid.appendChild(cell);
        }
    }

    function renderSelected() {
        var d = parse(selected), evs = visible(byDate[selected]);
        document.getElementById('calSelTitle').textContent = DAYS[d.getDay()] + ', ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        var monthCount = 0;
        Object.keys(byDate).forEach(function (k) { var p = parse(k); if (p.getMonth() === view.getMonth() && p.getFullYear() === view.getFullYear()) monthCount += visible(byDate[k]).length; });
        document.getElementById('calSelSub').textContent = evs.length + ' item' + (evs.length === 1 ? '' : 's') + ' on this day \u00b7 ' + monthCount + ' in ' + MONTHS[view.getMonth()];
        var box = document.getElementById('calSelList'); box.innerHTML = '';
        if (!evs.length) { box.appendChild(el('div', 'cal-empty', 'Nothing due on this day.')); return; }
        evs.forEach(function (e) {
            var row = el('div', 'cal-item' + (e.done ? ' done' : ''));
            var tags = el('div', 'cal-tags');
            if (e.kind === 'task') {
                row.appendChild(el('span', 'cal-ttl', e.title));
                if (e.descr) row.appendChild(el('div', 'cal-desc', e.descr));
                var meta = [e.visibility === 'department' ? 'Shared with ' + (e.department || 'department') : 'Private', e.mine ? null : 'by ' + e.owner].filter(Boolean).join(' \u00b7 ');
                row.appendChild(el('div', 'cal-meta', meta));
                tags.appendChild(el('span', 'cal-tag task', 'Task'));
                tags.appendChild(e.done ? el('span', 'cal-tag ok', 'Done') : el('span', 'cal-tag u-' + urgency(e), remaining(e.due)));
                if (e.priority && e.priority !== 'normal') tags.appendChild(el('span', 'cal-tag plain', cap(e.priority) + ' priority'));
                row.appendChild(tags);
                if (e.can_edit) {
                    var act = el('div', 'cal-actions');
                    act.appendChild(postForm('task_toggle', e, e.done ? 'Reopen' : 'Mark done', 'btn btn-outline btn-sm', null));
                    var ed = el('button', 'btn btn-outline btn-sm', 'Edit'); ed.type = 'button'; ed.addEventListener('click', function () { openTask(e); }); act.appendChild(ed);
                    act.appendChild(postForm('task_delete', e, 'Delete', 'btn btn-outline btn-sm', { title: 'Delete task?', message: '\u201c' + e.title + '\u201d will be removed from the calendar.', ok: 'Delete' }));
                    row.appendChild(act);
                }
            } else {
                var a = el('a', null, e.title); a.href = APP_URL + '/document_view.php?id=' + encodeURIComponent(e.id);
                row.appendChild(a);
                var m = [e.ref, e.category, e.department].filter(Boolean).join(' \u00b7 ');
                if (m) row.appendChild(el('div', 'cal-meta', m));
                tags.appendChild(el('span', 'cal-tag u-' + urgency(e), remaining(e.due)));
                tags.appendChild(el('span', 'cal-tag plain', KIND_LABEL[e.kind]));
                tags.appendChild(el('span', 'badge ' + (STATUS_CLS[e.status] || 'badge-draft'), cap(e.status)));
                row.appendChild(tags);
            }
            box.appendChild(row);
        });
    }

    function renderNext() {
        var box = document.getElementById('calNextList'); box.innerHTML = '';
        var todayKey = key(today);
        var list = EVENTS.filter(function (e) { return show[e.kind] && !e.done && e.due >= todayKey; })
            .sort(function (a, b) { return a.due < b.due ? -1 : a.due > b.due ? 1 : order[a.kind] - order[b.kind]; }).slice(0, 6);
        if (!list.length) { box.appendChild(el('div', 'cal-empty', 'Nothing coming up.')); return; }
        list.forEach(function (e) {
            var p = parse(e.due), b = el('button', 'cal-next'); b.type = 'button';
            var d = el('div', 'd'); d.appendChild(el('b', null, p.getDate())); d.appendChild(el('small', null, MONTHS[p.getMonth()].slice(0, 3)));
            var t = el('div', 't'); t.appendChild(el('span', null, e.title)); t.appendChild(el('small', null, KIND_LABEL[e.kind] + ' \u00b7 ' + remaining(e.due)));
            b.appendChild(d); b.appendChild(t);
            b.addEventListener('click', function () { selected = e.due; view = new Date(p.getFullYear(), p.getMonth(), 1); renderAll(); });
            box.appendChild(b);
        });
    }

    function renderAll() { renderGrid(); renderSelected(); renderNext(); }

    
    var modal = document.getElementById('taskModal');
    function openTask(task, dateKey) {
        document.getElementById('taskModalTitle').textContent = task ? 'Edit Task' : 'New Task';
        document.getElementById('tfId').value    = task ? task.tid : '';
        document.getElementById('tfTitle').value = task ? task.title : '';
        document.getElementById('tfDate').value  = task ? task.due : (dateKey || selected);
        document.getElementById('tfPrio').value  = task ? task.priority : 'normal';
        document.getElementById('tfDesc').value  = task ? (task.descr || '') : '';
        var vis = document.getElementById('tfVis'); if (vis) vis.value = task ? task.visibility : 'private';
        modal.classList.add('open');
        setTimeout(function () { document.getElementById('tfTitle').focus(); }, 30);
    }
    function closeTask() { modal.classList.remove('open'); }
    document.getElementById('calNewTask').addEventListener('click', function () { openTask(null, selected); });
    document.getElementById('calAddOnDay').addEventListener('click', function () { openTask(null, selected); });
    document.getElementById('taskModalClose').addEventListener('click', closeTask);
    document.getElementById('taskCancel').addEventListener('click', closeTask);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeTask(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeTask(); });

    document.getElementById('calPrev').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); renderAll(); });
    document.getElementById('calNext').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); renderAll(); });
    document.getElementById('calToday').addEventListener('click', function () { view = new Date(today.getFullYear(), today.getMonth(), 1); selected = key(today); renderAll(); });
    document.querySelectorAll('.cal-pill').forEach(function (p) {
        p.addEventListener('click', function () {
            var k = p.getAttribute('data-kind'); show[k] = !show[k];
            p.classList.toggle('off', !show[k]); p.setAttribute('aria-pressed', String(show[k]));
            renderAll();
        });
    });
    renderAll();
    if (window.lucide) lucide.createIcons();
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
