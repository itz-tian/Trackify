<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth     = new Auth();
$auth->requireLogin();
$docClass = new Document();
$db       = Database::getInstance();

$id  = (int)($_GET['id'] ?? 0);
$doc = $docClass->getById($id);
if (!$doc) { header('Location: ' . APP_URL . '/documents.php?error=notfound'); exit; }

$userRole   = $_SESSION['user_role'] ?? 'staff';
$userId     = (int)$_SESSION['user_id'];
$isOwner    = ($doc['uploaded_by'] == $userId);
$isAdmin    = ($userRole === 'admin');
$isDeptHead = ($userRole === 'department_head' && !empty($doc['department_id']) && (int)$doc['department_id'] === (int)($_SESSION['user_dept'] ?? 0));
$isAdminOwned = (($doc['uploader_role'] ?? '') === 'admin');

// Approver check
$checkWf = $db->prepare("SELECT * FROM approval_workflows WHERE document_id = ? AND approver_id = ?");
$checkWf->execute([$id, $userId]);
$myWorkflow = $checkWf->fetch();

// Only a System Admin can approve a document the approver uploaded themselves.
// Department heads/assigned approvers can never approve their own uploads.
$canApprove = ($isAdmin || (!$isAdminOwned && !$isOwner && ($isDeptHead || !empty($myWorkflow))));

$categories  = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$canEdit   = ($isAdmin || (!$isAdminOwned && ($isOwner || ($isDeptHead && (int)$doc['uploaded_by'] !== $userId))));
$canDelete = ($isAdmin || (!$isAdminOwned && ($isOwner || ($isDeptHead && (int)$doc['uploaded_by'] !== $userId))));
$canComment = ($isAdmin || !$isAdminOwned);

// CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

// ── POST actions ─────────────────────────────────────────────────────
// Every action sets a flash message and redirects (Post/Redirect/Get), so Back/Forward in the
// browser never replays a stale POST against this document.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = APP_URL . '/document_view.php?id=' . $id;

    if (isset($_POST['approval_action'])) {                       // Approve / reject
        $action  = $_POST['approval_action']; // 'approve' or 'reject'
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$canApprove) {
            flash_set('error', 'You do not have permission to approve or reject this document.');
        } elseif ($action === 'reject' && $remarks === '') {
            flash_set('error', 'Please provide a reason or remarks when rejecting a document.');
        } else {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $res = $docClass->updateStatus($id, $newStatus, $remarks);
            flash_result($res, $action === 'approve' ? 'Document has been successfully approved.' : 'Document has been rejected.');
        }
        redirect_to($self);
    }

    if (isset($_POST['comment'])) {                               // Add comment
        $comment = trim($_POST['comment']);
        if (!$canComment) {
            flash_set('error', 'This administrator-owned document is protected from comments or other changes.');
        } elseif ($comment !== '') {
            $db->prepare("INSERT INTO comments (document_id, user_id, comment) VALUES (?,?,?)")
               ->execute([$id, $userId, $comment]);
            $auth->logActivity($userId, $id, 'comment', 'Added a comment');
            flash_set('success', 'Comment posted successfully.');
        }
        redirect_to($self);
    }

    if (isset($_POST['new_status'])) {                            // Status update
        $remarks = trim($_POST['remarks'] ?? '');
        $res = $docClass->updateStatus($id, $_POST['new_status'], $remarks ?: null);
        flash_result($res, 'Document status updated successfully.');
        redirect_to($self);
    }

    if (isset($_POST['upload_version'])) {                        // Upload new version
        if (!empty($_FILES['new_version_file']['name'])) {
            $notes        = trim($_POST['version_notes'] ?? '');
            $newVerStatus = !empty($_POST['version_status']) ? $_POST['version_status'] : null;
            $res = $docClass->uploadVersion($id, $_FILES['new_version_file'], $notes, $newVerStatus);
            flash_result($res, 'New document version (v' . ($res['new_version'] ?? '') . ') uploaded successfully.');
        } else {
            flash_set('error', 'Please select a file to upload as the new version.');
        }
        redirect_to($self);
    }

    if (isset($_POST['edit_document'])) {                         // Edit metadata
        $res = $docClass->update($id, $_POST);
        flash_result($res, 'Document details updated successfully.');
        redirect_to($self);
    }

    if (isset($_POST['delete_document'])) {                       // Delete
        $res = $docClass->delete($id);
        if ($res['success']) {
            flash_set('success', 'Document deleted.');
            redirect_to(APP_URL . '/documents.php');
        }
        flash_set('error', $res['message']);
        redirect_to($self);
    }

    redirect_to($self); // Unknown POST: still never leave a POST page in history
}

// Message left by the redirect above (shown once)
['success' => $success, 'error' => $error] = flash_pull();

$statusColors = [
    'draft'=>'badge-draft','pending_review'=>'badge-pending','under_review'=>'badge-review',
    'approved'=>'badge-approved','rejected'=>'badge-rejected','archived'=>'badge-archived'
];

$fileIcon = [
    'pdf'  => ['file-text', '#ff3b30', 'rgba(255, 59, 48, 0.12)'],
    'doc'  => ['file-text', '#0071e3', 'rgba(0, 113, 227, 0.12)'],
    'docx' => ['file-text', '#0071e3', 'rgba(0, 113, 227, 0.12)'],
    'xls'  => ['file-spreadsheet', '#34c759', 'rgba(52, 199, 89, 0.12)'],
    'xlsx' => ['file-spreadsheet', '#34c759', 'rgba(52, 199, 89, 0.12)'],
    'ppt'  => ['presentation', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
    'pptx' => ['presentation', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
    'png'  => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'jpg'  => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'jpeg' => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'txt'  => ['file-text', '#8e8e93', 'rgba(142, 142, 147, 0.12)'],
    'zip'  => ['file-archive', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
];
$ext  = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
$icon = $fileIcon[$ext] ?? ['file', '#8e8e93', 'rgba(142, 142, 147, 0.12)'];

include __DIR__ . '/includes/header.php';
?>

<?php if ($_GET['uploaded'] ?? false): ?>
<div class="alert alert-success"><i data-lucide="circle-check"></i> Document uploaded successfully!</div>
<?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($isAdminOwned && !$isAdmin): ?><div class="alert alert-info"><i data-lucide="shield-check"></i> Administrator-owned document: view and download only. Editing, archiving, version changes, approvals, and comments are protected.</div><?php endif; ?>

<!-- Approval Banner -->
<?php if (in_array($doc['status'], ['pending_review', 'under_review'])): ?>
    <?php if ($canApprove): ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-orange); background: linear-gradient(90deg, rgba(255, 149, 0, 0.08) 0%, #ffffff 100%);">
        <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-weight:700; font-size:15px; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                    <i data-lucide="stamp" style="color:var(--apple-orange);"></i> Approval Required
                </div>
                <div class="text-muted text-sm" style="margin-top:2px;">
                    This document was submitted for review. As an authorized approver, please review and record your decision.
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <form method="POST" style="display:inline-flex; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_action" value="approve">
                    <button type="submit" class="btn btn-success">
                        <i data-lucide="check"></i> Approve Document
                    </button>
                </form>
                <form method="POST" style="display:inline-flex; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_action" value="reject">
                    <input type="hidden" name="remarks" value="Rejected by approver.">
                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="x"></i> Reject Document
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: rgba(0, 113, 227, 0.04);">
        <div class="card-body" style="display:flex; align-items:center; gap:12px;">
            <i data-lucide="clock" style="color:var(--apple-blue); font-size:18px;"></i>
            <div>
                <div style="font-weight:600; font-size:13.5px;">Pending Approval</div>
                <div class="text-muted text-sm">This document is currently awaiting review by the Department Head or Administration.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php elseif ($doc['status'] === 'draft' && ($isOwner || $isAdmin)): ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: #ffffff;">
        <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-weight:700; font-size:14px; color:var(--text-primary);">
                    <i data-lucide="file-pen" style="color:var(--apple-blue); margin-right:6px;"></i> Draft Document
                </div>
                <div class="text-muted text-sm" style="margin-top:2px;">
                    This document is saved as a draft. When ready, submit it to trigger the approval workflow.
                </div>
            </div>
            <form method="POST" data-confirm-title="Submit for Approval" data-confirm-message="Submit this draft to the Approval Queue?" data-confirm-text="Submit" data-confirm-icon="send" data-confirm-class="btn-primary" data-confirm-icon-main="send" data-confirm-tone="blue">
                <?= csrf_field() ?>
                <input type="hidden" name="new_status" value="pending_review">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send"></i> Submit for Approval
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="content-split">

    <!-- Left: Main Info & Comments -->
    <div>
        <!-- Document Header Card -->
        <div class="card mb-16">
            <div class="card-body">
                <div class="doc-header-layout">
                    <div style="width:54px; height:54px; background:<?= $icon[2] ?>; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i data-lucide="<?= $icon[0] ?>" style="font-size:24px; color:<?= $icon[1] ?>;"></i>
                    </div>
                    <div class="doc-header-info">
                        <h1 style="font-size:19px; font-weight:700; letter-spacing:-0.02em; margin-bottom:6px; color:var(--text-primary);">
                            <?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>
                        </h1>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                            <span class="badge <?= $statusColors[$doc['status']] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span>
                            <span class="badge badge-draft"><i data-lucide="flag"></i> <?= ucfirst($doc['priority']) ?></span>
                            <?php if ($doc['category_name']): ?>
                            <span style="font-size:12px; font-weight:500; color:<?= htmlspecialchars($doc['category_color']) ?>; display:inline-flex; align-items:center; gap:4px;">
                                <i data-lucide="tag"></i> <?= htmlspecialchars($doc['category_name']) ?>
                            </span>
                            <?php endif; ?>
                            <span class="text-sm text-muted">Version <?= $doc['version'] ?></span>
                            <span class="text-sm text-muted" style="font-family:monospace;">Ref: <?= htmlspecialchars($doc['reference_number'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="doc-header-actions">
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary btn-sm">
                            <i data-lucide="download"></i> Download
                        </a>
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('statusModal').classList.add('open')">
                            <i data-lucide="refresh-cw"></i> Status
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                            <i data-lucide="upload-cloud"></i> New Version
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('editModal').classList.add('open')">
                            <i data-lucide="pencil"></i> Edit
                        </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <form method="POST" style="display:inline;" data-confirm-title="Move to Recycle Bin" data-confirm-message="Move this document to the Recycle Bin?" data-confirm-text="Move" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_document" value="1">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--apple-red); border-color:rgba(255,59,48,0.25);" title="Delete Document">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($doc['description']): ?>
                <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border-hairline);">
                    <p style="font-size:13.5px; color:var(--text-secondary); line-height:1.6;"><?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($doc['tags']): ?>
                <div style="margin-top:14px; display:flex; gap:6px; flex-wrap:wrap;">
                    <?php foreach (explode(',', $doc['tags']) as $tag): ?>
                    <span style="background:rgba(0,0,0,0.04); color:var(--text-secondary); font-size:11.5px; font-weight:500; padding:3px 10px; border-radius:var(--radius-pill);">
                        #<?= htmlspecialchars(trim($tag)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i data-lucide="clipboard-list" style="color:var(--apple-blue);margin-right:8px;"></i>Record Metadata</span></div>
            <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;">
                <div><div class="text-sm text-muted">Reference Number</div><div class="fw-600" style="font-family:monospace;margin-top:3px;"><?= htmlspecialchars($doc['reference_number'] ?? '—') ?></div></div>
                <div><div class="text-sm text-muted">File Type</div><div class="fw-600" style="margin-top:3px;"><?= htmlspecialchars(strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION))) ?></div></div>
                <div><div class="text-sm text-muted">Created</div><div class="fw-600" style="margin-top:3px;"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div></div>
                <div><div class="text-sm text-muted">Expires</div><div class="fw-600" style="margin-top:3px;"><?= $doc['expires_at'] ? date('M d, Y', strtotime($doc['expires_at'])) : 'No expiry' ?></div></div>
                <div><div class="text-sm text-muted">File Size</div><div class="fw-600" style="margin-top:3px;"><?= number_format($doc['file_size']/1024,1) ?> KB</div></div>
            </div>
        </div>

        <!-- Document Preview -->
        <div class="card mb-16">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title">
                    <i data-lucide="eye" style="color:var(--apple-blue); margin-right:8px;"></i>
                    Document Preview
                </span>
                <div style="display:flex; gap:8px; align-items:center;">
                    <span class="text-sm text-muted" style="font-size:12px;">
                        <?= strtoupper($ext) ?> · <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                    </span>
                    <?php if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt'])): ?>
                    <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Open full screen in a new tab">
                        <i data-lucide="external-link"></i> Open Tab
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm" title="Download original file">
                        <i data-lucide="download"></i> Download
                    </a>
                </div>
            </div>
            <div class="card-body" style="padding:16px;">
                <?php if ($ext === 'pdf'): ?>
                <!-- PDF Interactive Inline Preview -->
                <div style="position:relative; width:100%; border-radius:10px; overflow:hidden; border:1px solid var(--border-hairline); background:#525659;">
                    <iframe src="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>#toolbar=1&navpanes=0"
                            class="doc-preview-iframe"
                            title="PDF Preview: <?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>">
                        <p style="padding:20px; text-align:center; color:#fff;">
                            Your browser does not support inline PDF viewing. 
                            <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" style="color:#64d2ff;">Click here to download the file.</a>
                        </p>
                    </iframe>
                </div>

                <?php elseif (in_array($ext, ['png', 'jpg', 'jpeg'])): ?>
                <!-- Image Preview -->
                <div style="text-align:center; padding:20px; background:rgba(0,0,0,0.02); border-radius:10px; border:1px solid var(--border-hairline);">
                    <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank">
                        <img src="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" 
                             alt="<?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>"
                             style="max-width:100%; max-height:560px; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,0.08); object-fit:contain; cursor:zoom-in;">
                    </a>
                    <div style="font-size:12px; color:var(--text-secondary); margin-top:10px;">
                        Click image to view in full resolution
                    </div>
                </div>

                <?php elseif ($ext === 'txt'): ?>
                <!-- Text File Preview -->
                <?php
                $fullPath = realpath(__DIR__ . '/' . ltrim($doc['file_path'], '/\\'));
                $textContent = ($fullPath && is_file($fullPath)) ? file_get_contents($fullPath) : '';
                ?>
                <pre style="background:rgba(0,0,0,0.02); border:1px solid var(--border-hairline); border-radius:10px; padding:18px 20px; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12.5px; line-height:1.6; max-height:500px; overflow:auto; color:var(--text-primary); white-space:pre-wrap;"><?= htmlspecialchars($textContent) ?></pre>

                <?php else: ?>
                <!-- Microsoft Office / Binary Format Quick Look Card -->
                <div style="text-align:center; padding:44px 20px; background:linear-gradient(180deg, rgba(0,0,0,0.01) 0%, rgba(0,0,0,0.03) 100%); border-radius:12px; border:1px dashed var(--border-hairline);">
                    <div style="width:68px; height:68px; border-radius:18px; background:<?= $icon[2] ?>; color:<?= $icon[1] ?>; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:14px; box-shadow:0 4px 14px rgba(0,0,0,0.06);">
                        <i data-lucide="<?= $icon[0] ?>"></i>
                    </div>
                    <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
                        <?= htmlspecialchars($doc['file_name']) ?>
                    </h3>
                    <p style="font-size:13px; color:var(--text-secondary); max-width:460px; margin:0 auto 20px; line-height:1.5;">
                        This document is in <strong><?= strtoupper($ext) ?></strong> format (<?= htmlspecialchars($doc['file_type']) ?>). Web browsers cannot natively render editable Microsoft Office files inside a page without downloading.
                    </p>
                    <div style="display:inline-flex; gap:10px; flex-wrap:wrap; justify-content:center;">
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary">
                            <i data-lucide="download"></i> Download & Open File
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Discussion Thread -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-lucide="message-square" style="color:var(--apple-blue); margin-right:8px;"></i>Discussion (<?= count($doc['comments']) ?>)</span>
            </div>
            <div class="card-body">
                <?php if (empty($doc['comments'])): ?>
                <p class="text-muted text-center" style="padding:24px 0;">No comments yet. Leave a remark below.</p>
                <?php else: ?>
                <?php foreach ($doc['comments'] as $c): ?>
                <div style="display:flex; gap:12px; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid rgba(0,0,0,0.04);">
                    <div class="user-avatar" title="<?= htmlspecialchars($c['user_name']) ?>">
                        <i data-lucide="user-round"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:12.5px; font-weight:600;">
                            <?= htmlspecialchars($c['user_name']) ?>
                            <span class="text-sm text-muted" style="font-weight:400; margin-left:6px; font-size:11px;">
                                <?= date('M d, Y g:i a', strtotime($c['created_at'])) ?>
                            </span>
                        </div>
                        <div style="font-size:13px; margin-top:4px; line-height:1.5; color:var(--text-primary);">
                            <?= nl2br(htmlspecialchars($c['comment'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($canComment): ?>
                <form method="POST" style="margin-top:12px;">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <textarea name="comment" rows="3" placeholder="Leave a comment or review note…" required></textarea>
                    </div>
                    <div style="display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="send"></i> Post Comment</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-info" style="margin-top:12px;"><i data-lucide="lock"></i> This administrator-owned document is protected. Comments and changes are disabled for non-administrators.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Apple Inspector Panel -->
    <div>
        <!-- Approval Status Card -->
        <div class="card mb-16">
            <div class="card-header">
                <span class="card-title"><i data-lucide="stamp" style="color:var(--apple-orange); margin-right:6px;"></i>Approval Status</span>
                <span class="badge <?= $statusColors[$doc['status']] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <?php if (empty($doc['approvals'])): ?>
                    <?php if (in_array($doc['status'], ['pending_review', 'under_review'])): ?>
                    <p class="text-sm" style="color:var(--apple-orange); margin-bottom:12px;">
                        <i data-lucide="circle-alert"></i> Awaiting review from department head or admin.
                    </p>
                    <?php if ($canApprove): ?>
                    <div style="display:flex; gap:8px;">
                        <form method="POST" style="flex:1; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                                <i data-lucide="check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="flex:1; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_action" value="reject">
                            <input type="hidden" name="remarks" value="Rejected by approver.">
                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                                <i data-lucide="x"></i> Reject
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted text-sm">No workflow records logged.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($doc['approvals'] as $ap): ?>
                    <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid rgba(0,0,0,0.04);">
                        <div style="width:28px; height:28px; border-radius:50%; background:rgba(0,0,0,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">
                            <?php $apIcon = match($ap['status']) {
                                'approved' => ['check', 'var(--apple-green)'],
                                'rejected' => ['x', 'var(--apple-red)'],
                                'skipped'  => ['check-check', '#8e8e93'],
                                default    => ['clock', 'var(--apple-orange)'],
                            }; ?>
                            <i data-lucide="<?= $apIcon[0] ?>" style="color:<?= $apIcon[1] ?>; font-size:12px;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:12.5px; font-weight:600;"><?= htmlspecialchars($ap['approver_name'] ?? 'Approver') ?></div>
                            <div class="text-sm" style="color:<?= $ap['status']==='approved'?'var(--apple-green)':($ap['status']==='rejected'?'var(--apple-red)':($ap['status']==='skipped'?'#8e8e93':'var(--apple-orange)')) ?>; font-weight:600; font-size:11.5px;">
                                <?= $ap['status'] === 'skipped' ? 'Not Required' : ucfirst($ap['status']) ?> <?= $ap['acted_at'] ? '· '.date('M d, Y',strtotime($ap['acted_at'])) : '' ?>
                            </div>
                            <?php if ($ap['remarks']): ?>
                            <div class="text-sm" style="margin-top:4px; font-size:12px; color:var(--text-secondary); background:rgba(0,0,0,0.03); padding:6px 10px; border-radius:6px;">
                                "<?= htmlspecialchars($ap['remarks']) ?>"
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (in_array($doc['status'], ['pending_review', 'under_review']) && $canApprove): ?>
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <form method="POST" style="flex:1; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                                <i data-lucide="check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="flex:1; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="approval_action" value="reject">
                            <input type="hidden" name="remarks" value="Rejected by approver.">
                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                                <i data-lucide="x"></i> Reject
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Document Details -->
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">Document Details</span></div>
            <div class="card-body" style="font-size:12.5px; padding:16px 20px;">
                <?php $meta = [
                    ['Owner', $doc['uploaded_by_name'] ?? '—'],
                    ['Department', $doc['department_name'] ?? '—'],
                    ['File Name', $doc['file_name']],
                    ['File Size', number_format($doc['file_size']/1024,1).' KB'],
                    ['Format', strtoupper($ext)],
                    ['Created', date('M d, Y', strtotime($doc['created_at']))],
                    ['Modified', date('M d, Y', strtotime($doc['updated_at']))],
                    ['Expires', $doc['expires_at'] ? date('M d, Y', strtotime($doc['expires_at'])) : '—'],
                ]; ?>
                <?php foreach ($meta as [$label,$val]): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid rgba(0,0,0,0.04);">
                    <span class="text-muted"><?= $label ?></span>
                    <span class="fw-600" style="text-align:right; max-width:170px; word-break:break-all;"><?= htmlspecialchars($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Version History -->
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span class="card-title"><i data-lucide="history" style="color:var(--apple-blue); margin-right:6px;"></i>Version History</span>
                <?php if ($canEdit): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                    <i data-lucide="plus"></i> New Version
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body" style="font-size:12.5px; padding:16px 20px;">
                <?php foreach ($doc['versions'] as $ver): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.04);">
                    <div>
                        <div class="fw-600">v<?= $ver['version'] ?> <?= ($ver['version'] == $doc['version']) ? '<span style="font-size:10.5px; color:var(--apple-blue); font-weight:700; background:rgba(0,113,227,0.1); padding:1px 6px; border-radius:4px; margin-left:4px;">Current</span>' : '' ?></div>
                        <div class="text-sm text-muted"><?= htmlspecialchars($ver['uploaded_by_name'] ?? 'Owner') ?> · <?= date('M d, Y', strtotime($ver['created_at'])) ?> · <?= number_format($ver['file_size']/1024, 1) ?> KB</div>
                        <?php if ($ver['notes']): ?><div class="text-sm text-muted" style="margin-top:2px;">"<?= htmlspecialchars($ver['notes']) ?>"</div><?php endif; ?>
                    </div>
                    <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] . '&v=' . (int)$ver['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Download version v<?= $ver['version'] ?>"><i data-lucide="download" style="font-size:11px;"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Apple Sheet Modal for Status Override ── -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="refresh-cw" style="color:var(--apple-blue); margin-right:6px;"></i> Change Document Status</div>
            <button type="button" class="modal-close" onclick="document.getElementById('statusModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Document Status <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <select name="new_status" required>
                    <?php foreach (array_filter(['draft'=>'Draft','pending_review'=>'Submit for Review','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','archived'=>'Archived'], fn($k) => $canApprove || !in_array($k, ['under_review','approved','rejected'], true) || $doc['status'] === $k, ARRAY_FILTER_USE_KEY) as $s => $label): ?>
                    <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks / Notes <span class="text-muted text-sm">(Optional)</span></label>
                <textarea name="remarks" placeholder="Notes on this review or status change…" rows="3"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('statusModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Apple Sheet Modal for Uploading New Version ── -->
<div class="modal-overlay" id="versionModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="upload-cloud" style="color:var(--apple-blue); margin-right:6px;"></i> Upload New Document Version</div>
            <button type="button" class="modal-close" onclick="document.getElementById('versionModal').classList.remove('open')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="upload_version" value="1">
            <div class="form-group">
                <label>Select New File <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <input type="file" name="new_version_file" required style="padding:10px; background:var(--bg-card-subtle); border-radius:8px; width:100%; border:1px solid var(--border-card);">
                <div class="text-muted text-sm" style="margin-top:4px;">Supported: PDF, Word, Excel, PPT, Text, Images, ZIP. Max 20MB. Current version is v<?= $doc['version'] ?>.</div>
            </div>
            <div class="form-group">
                <label>Update Status When Uploading</label>
                <select name="version_status">
                    <option value="">Keep current status (<?= ucfirst(str_replace('_',' ',$doc['status'])) ?>)</option>
                    <option value="draft">Save as Draft</option>
                    <option value="pending_review">Submit for Review</option>
                    <option value="under_review">Under Review</option>
                    <?php if ($canApprove): ?><option value="approved">Approved</option><?php endif; ?>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="form-group">
                <label>Revision Notes <span class="text-muted text-sm">(Optional)</span></label>
                <textarea name="version_notes" placeholder="e.g. Updated financial figures, corrected section 2.1…" rows="3"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('versionModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload v<?= $doc['version'] + 1 ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ── Apple Sheet Modal for Editing Metadata ── -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:540px;">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="pencil" style="color:var(--apple-blue); margin-right:6px;"></i> Edit Document Details</div>
            <button type="button" class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_document" value="1">
            <div class="form-group">
                <label>Document Title <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <input type="text" name="title" value="<?= htmlspecialchars($doc['title']) ?>" required>
            </div>
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" value="<?= htmlspecialchars($doc['reference_number'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($doc['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <?php foreach ($departments as $dep): ?>
                        <option value="<?= $dep['id'] ?>" <?= ($doc['department_id'] == $dep['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dep['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <?php foreach (['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $p => $lbl): ?>
                        <option value="<?= $p ?>" <?= $doc['priority']===$p?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expiration Date</label>
                    <input type="date" name="expires_at" value="<?= $doc['expires_at'] ? date('Y-m-d', strtotime($doc['expires_at'])) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Document Status</label>
                <select name="status">
                    <?php foreach (array_filter(['draft'=>'Draft','pending_review'=>'Submit for Review','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','archived'=>'Archived'], fn($k) => $canApprove || !in_array($k, ['under_review','approved','rejected'], true) || $doc['status'] === $k, ARRAY_FILTER_USE_KEY) as $s => $label): ?>
                    <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tags <span class="text-muted text-sm">(Comma-separated)</span></label>
                <input type="text" name="tags" value="<?= htmlspecialchars($doc['tags'] ?? '') ?>" placeholder="e.g. invoice, q3, budget">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($doc['description'] ?? '') ?></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
