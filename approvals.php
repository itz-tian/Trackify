<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth = new Auth();
$auth->requireRole(['admin', 'department_head', 'staff']);
$db   = Database::getInstance();
$docClass = new Document();

$success  = $error = '';
$userRole = $_SESSION['user_role'] ?? 'staff';
$userId   = (int)$_SESSION['user_id'];
$userDept = $_SESSION['user_dept'] ?? 0;

// Approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $docId   = (int)($_POST['document_id'] ?? 0);
    $action  = $_POST['action']; // 'approve', 'reject' or 'cancel'
    $remarks = trim($_POST['remarks'] ?? '');

    if ($docId > 0 && $action === 'cancel') {
        $res = $docClass->cancelSubmission($docId);
        flash_result($res, 'Submission cancelled. The document was moved back to Draft and removed from the approval queue.');
    } elseif ($docId > 0) {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $res = $docClass->updateStatus($docId, $newStatus, $remarks);
        flash_result($res, "Document has been " . ($action === 'approve' ? 'approved' : 'rejected') . ".");
    }
    // Post/Redirect/Get: Back/Forward must never replay this POST.
    redirect_self();
}

// Message left by the redirect above (shown once)
['success' => $success, 'error' => $error] = flash_pull();

// Fetch pending items based on user role
// Role-based pending docs Staff see assigned workflows
if ($userRole === 'admin') {
    $query = "
        SELECT d.id AS document_id, d.title, d.status AS doc_status, d.priority, d.created_at AS doc_created,
               d.expires_at, c.name AS category_name, c.color AS category_color,
               dep.name AS dept_name, CONCAT(u.first_name,' ',u.last_name) AS submitted_by,
               COALESCE(aw.id, 0) AS workflow_id, COALESCE(aw.status, 'pending') AS wf_status, aw.deadline
        FROM documents d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        LEFT JOIN approval_workflows aw ON d.id = aw.document_id AND aw.approver_id = ?
        WHERE d.deleted_at IS NULL AND d.status IN ('pending_review', 'under_review') AND u.role <> 'admin'
        ORDER BY d.priority = 'urgent' DESC, d.priority = 'high' DESC, d.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
} elseif ($userRole === 'department_head') {
    $query = "
        SELECT d.id AS document_id, d.title, d.status AS doc_status, d.priority, d.created_at AS doc_created,
               d.expires_at, c.name AS category_name, c.color AS category_color,
               dep.name AS dept_name, CONCAT(u.first_name,' ',u.last_name) AS submitted_by,
               d.uploaded_by, aw.id AS workflow_id, aw.status AS wf_status, aw.deadline
        FROM documents d
        LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.approver_id = ?
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.department_id = ? AND d.deleted_at IS NULL
          AND d.status IN ('pending_review', 'under_review')
          AND u.role <> 'admin'
          AND (aw.id IS NULL OR aw.status = 'pending')
        ORDER BY d.priority = 'urgent' DESC, d.priority = 'high' DESC, d.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId, $userDept]);
} else {
    $query = "
        SELECT d.id AS document_id, d.title, d.status AS doc_status, d.priority, d.created_at AS doc_created,
               d.expires_at, c.name AS category_name, c.color AS category_color,
               dep.name AS dept_name, CONCAT(u.first_name,' ',u.last_name) AS submitted_by,
               aw.id AS workflow_id, aw.status AS wf_status, aw.deadline
        FROM approval_workflows aw
        JOIN documents d ON aw.document_id = d.id
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE aw.approver_id = ? AND aw.status = 'pending' AND d.deleted_at IS NULL AND d.status IN ('pending_review', 'under_review') AND u.role <> 'admin'
        ORDER BY d.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
}

$workflows = $stmt->fetchAll();

// ── Waiting for approval, per staff ─────────────────────────────
// Approvers (admin / dept head): one row per staff member, counting THEIR documents still awaiting a decision.
// Staff: their own submissions that are still waiting for someone to approve.
$staffWaiting = [];
$mySubmissions = [];
if (in_array($userRole, ['admin', 'department_head'], true)) {
    $deptFilter = ($userRole === 'department_head') ? ' AND d.department_id = ' . (int)$userDept : '';
    $swStmt = $db->prepare("
        SELECT u.id AS staff_id, CONCAT(u.first_name,' ',u.last_name) AS staff_name,
               dep.name AS dept_name,
               COUNT(d.id) AS waiting_count,
               SUM(d.status = 'pending_review') AS pending_count,
               SUM(d.status = 'under_review')   AS review_count,
               MIN(d.created_at) AS oldest
        FROM documents d
        JOIN users u ON d.uploaded_by = u.id
        LEFT JOIN departments dep ON u.department_id = dep.id
        WHERE d.deleted_at IS NULL AND d.status IN ('pending_review', 'under_review') AND u.role <> 'admin' $deptFilter
        GROUP BY u.id, u.first_name, u.last_name, dep.name
        ORDER BY waiting_count DESC, staff_name ASC
    ");
    $swStmt->execute([]);
    $staffWaiting = $swStmt->fetchAll();
} else {
    $msStmt = $db->prepare("
        SELECT d.id AS document_id, d.title, d.status AS doc_status, d.priority, d.created_at AS doc_created,
               c.name AS category_name, c.color AS category_color, dep.name AS dept_name
        FROM documents d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        WHERE d.uploaded_by = ? AND d.deleted_at IS NULL AND d.status IN ('pending_review', 'under_review')
        ORDER BY d.created_at DESC
    ");
    $msStmt->execute([$userId]);
    $mySubmissions = $msStmt->fetchAll();
}

// Recent history
$historyStmt = $db->prepare("
    SELECT d.id AS document_id, d.title, d.status AS doc_status, d.updated_at,
           c.name AS category_name, c.color AS category_color,
           dep.name AS dept_name, CONCAT(u.first_name,' ',u.last_name) AS submitted_by,
           aw.status AS action_taken, aw.remarks, aw.acted_at
    FROM approval_workflows aw
    JOIN documents d ON aw.document_id = d.id
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE aw.approver_id = ? AND aw.status IN ('approved', 'rejected', 'skipped')
    ORDER BY aw.acted_at DESC
    LIMIT 10
");
$history = [];
if (in_array($userRole, ['admin', 'department_head'], true)) {
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetchAll();
}

$statusColors = ['pending'=>'badge-pending','pending_review'=>'badge-pending','under_review'=>'badge-review','approved'=>'badge-approved','rejected'=>'badge-rejected','skipped'=>'badge-draft','draft'=>'badge-draft'];

include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (in_array($userRole, ['admin','department_head'], true)): ?>
<!-- Pending Approvals -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title">
            <i data-lucide="stamp" style="color:var(--apple-orange); margin-right:8px;"></i>
            Pending Approvals Queue
            <span class="badge badge-pending" style="margin-left:8px;"><?= count($workflows) ?> Pending Action</span>
        </span>
    </div>

    <?php if (empty($workflows)): ?>
    <div class="card-body text-center text-muted" style="padding:56px 20px;">
        <div style="width:54px; height:54px; border-radius:50%; background:rgba(52, 199, 89, 0.12); color:var(--apple-green); display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:12px;">
            <i data-lucide="circle-check"></i>
        </div>
        <div style="font-size:15px; font-weight:600; color:var(--text-primary); margin-bottom:4px;">All Caught Up!</div>
        <div style="font-size:13px;">No documents are currently awaiting your approval.</div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Submitted By</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th style="white-space:nowrap;">Submitted On</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($workflows as $wf): ?>
            <tr>
                <td style="max-width:240px;">
                    <a href="document_view.php?id=<?= $wf['document_id'] ?>" class="fw-600" style="text-decoration:none; color:var(--text-primary); letter-spacing:-0.01em;">
                        <?= htmlspecialchars(doc_title($wf['title'], $wf['reference_number'] ?? null)) ?>
                    </a>
                </td>
                <td>
                    <?php if ($wf['category_name']): ?>
                    <span style="font-size:12px; font-weight:500;">
                        <span style="width:7px; height:7px; border-radius:50%; background:<?= htmlspecialchars($wf['category_color']) ?>; display:inline-block; margin-right:5px;"></span>
                        <?= htmlspecialchars($wf['category_name']) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-sm"><?= htmlspecialchars($wf['submitted_by'] ?? '—') ?></td>
                <td class="text-sm"><?= htmlspecialchars($wf['dept_name'] ?? 'General') ?></td>
                <td><span class="priority-<?= $wf['priority'] ?>"><?= ucfirst($wf['priority']) ?></span></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y', strtotime($wf['doc_created'])) ?></td>
                <td>
                    <span class="badge badge-pending">Needs Approval</span>
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:8px;">
                        <?php if ($userRole !== 'admin' && (int)($wf['uploaded_by'] ?? 0) === $userId): ?>
                        <form method="POST" style="display:inline-flex; margin:0;" data-confirm-title="Cancel Submission" data-confirm-message="Are you sure you want to cancel this submission? It will be removed from the approval queue and returned to Draft." data-confirm-text="Cancel Submission" data-confirm-cancel-text="Keep Submission" data-confirm-icon="undo-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int)$wf['document_id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-outline btn-sm"><i data-lucide="undo-2"></i> Cancel</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline-flex; gap:8px; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int)$wf['document_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i data-lucide="check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="display:inline-flex; gap:8px; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int)$wf['document_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="remarks" value="Rejected by approver.">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i data-lucide="x"></i> Reject
                            </button>
                        </form>
                        <?php endif; ?>
                        <a href="document_view.php?id=<?= $wf['document_id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View details">
                            <i data-lucide="eye" style="font-size:11px;"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array($userRole, ['admin','department_head'], true)): ?>
<!-- Waiting for Approval per Staff -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title">
            <i data-lucide="users" style="color:var(--apple-blue); margin-right:8px;"></i>
            Waiting for Approval per Staff
            <span class="badge badge-pending" style="margin-left:8px;"><?= array_sum(array_column($staffWaiting, 'waiting_count')) ?> Documents</span>
        </span>
    </div>
    <?php if (empty($staffWaiting)): ?>
    <div class="card-body text-center text-muted" style="padding:32px 20px; font-size:13px;">No staff documents are waiting for approval.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Department</th>
                    <th style="text-align:center;">Pending Review</th>
                    <th style="text-align:center;">Under Review</th>
                    <th style="text-align:center;">Total Waiting</th>
                    <th style="white-space:nowrap;">Oldest Submission</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffWaiting as $sw): ?>
            <tr>
                <td class="fw-600"><?= htmlspecialchars($sw['staff_name']) ?></td>
                <td class="text-sm"><?= htmlspecialchars($sw['dept_name'] ?? 'General') ?></td>
                <td style="text-align:center;"><?= (int)$sw['pending_count'] ?></td>
                <td style="text-align:center;"><?= (int)$sw['review_count'] ?></td>
                <td style="text-align:center; font-weight:700; color:var(--apple-orange);"><?= (int)$sw['waiting_count'] ?></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y', strtotime($sw['oldest'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- My Submissions Waiting for Approval (staff) -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title">
            <i data-lucide="hourglass" style="color:var(--apple-blue); margin-right:8px;"></i>
            My Documents Waiting for Approval
            <span class="badge badge-pending" style="margin-left:8px;"><?= count($mySubmissions) ?> Waiting</span>
        </span>
    </div>
    <?php if (empty($mySubmissions)): ?>
    <div class="card-body text-center text-muted" style="padding:32px 20px; font-size:13px;">None of your documents are waiting for approval.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th style="white-space:nowrap;">Submitted On</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mySubmissions as $ms): ?>
            <tr>
                <td style="max-width:240px;">
                    <a href="document_view.php?id=<?= $ms['document_id'] ?>" class="fw-600" style="text-decoration:none; color:var(--text-primary);"><?= htmlspecialchars(doc_title($ms['title'], $ms['reference_number'] ?? null)) ?></a>
                </td>
                <td class="text-sm"><?= htmlspecialchars($ms['category_name'] ?? '—') ?></td>
                <td class="text-sm"><?= htmlspecialchars($ms['dept_name'] ?? 'General') ?></td>
                <td><span class="priority-<?= $ms['priority'] ?>"><?= ucfirst($ms['priority']) ?></span></td>
                <td><span class="badge <?= $statusColors[$ms['doc_status']] ?? 'badge-pending' ?>"><?= ucfirst(str_replace('_',' ',$ms['doc_status'])) ?></span></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y', strtotime($ms['doc_created'])) ?></td>
                <td style="text-align:right;">
                    <form method="POST" style="display:inline-flex; margin:0;" data-confirm-title="Cancel Submission" data-confirm-message="Are you sure you want to cancel this submission? It will be removed from the approval queue and returned to Draft." data-confirm-text="Cancel Submission" data-confirm-cancel-text="Keep Submission" data-confirm-icon="undo-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="document_id" value="<?= (int)$ms['document_id'] ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline btn-sm"><i data-lucide="undo-2"></i> Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent Decisions -->
<?php if (!empty($history)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i data-lucide="history" style="color:var(--apple-blue); margin-right:8px;"></i>
            Recent Approval Decisions
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Submitted By</th>
                    <th>Decision</th>
                    <th>Remarks</th>
                    <th style="white-space:nowrap;">Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
                <td>
                    <a href="document_view.php?id=<?= $h['document_id'] ?>" class="fw-600" style="text-decoration:none; color:var(--text-primary);">
                        <?= htmlspecialchars(doc_title($h['title'], $h['reference_number'] ?? null)) ?>
                    </a>
                </td>
                <td class="text-sm"><?= htmlspecialchars($h['category_name'] ?? '—') ?></td>
                <td class="text-sm"><?= htmlspecialchars($h['submitted_by'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $h['action_taken']==='approved'?'badge-approved':($h['action_taken']==='rejected'?'badge-rejected':'badge-draft') ?>" style="white-space:nowrap;">
                        <?= $h['action_taken']==='skipped' ? 'Not Required' : ucfirst($h['action_taken']) ?>
                    </span>
                </td>
                <td class="text-sm text-muted" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($h['remarks'] ?: 'None') ?>
                </td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y g:i a', strtotime($h['acted_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
