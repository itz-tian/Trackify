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
    <div class="card mb-16" style="border-left: 4px solid var(--apple-orange); background: linear-gradient(90deg, rgba(255, 149, 0, 0.08) 0%, var(--bg-card) 100%);">
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
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: linear-gradient(90deg, var(--apple-blue-subtle) 0%, var(--bg-card) 100%);">
        <div class="card-body" style="display:flex; align-items:center; gap:12px;">
            <i data-lucide="clock" style="color:var(--apple-blue); font-size:18px;"></i>
            <div>
                <div style="font-weight:600; font-size:13.5px; color:var(--text-primary);">Pending Approval</div>
                <div class="text-muted text-sm">This document is currently awaiting review by the Department Head or Administration.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php elseif ($doc['status'] === 'draft' && ($isOwner || $isAdmin)): ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: linear-gradient(90deg, var(--apple-blue-subtle) 0%, var(--bg-card) 100%);">
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
        <?php
        $diskFile = realpath(__DIR__ . '/' . ltrim($doc['file_path'], '/\\'));
        $hasDiskFile = ($diskFile && is_file($diskFile));
        ?>
        <div class="card mb-16" style="min-width:0; max-width:100%; overflow:hidden;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <span class="card-title">
                    <i data-lucide="eye" style="color:var(--apple-blue); margin-right:8px;"></i>
                    Document Preview
                </span>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="text-sm text-muted" style="font-size:12px;">
                        <?= strtoupper($ext) ?> · <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                    </span>
                    <?php if ($hasDiskFile): ?>
                        <?php if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt'])): ?>
                        <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Open full screen in a new tab">
                            <i data-lucide="external-link"></i> Open Tab
                        </a>
                        <?php endif; ?>
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm" title="Download original file">
                            <i data-lucide="download"></i> Download
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding:16px; min-width:0; max-width:100%; overflow:hidden;">
                <?php if (!$hasDiskFile): ?>
                <!-- Missing file state: graceful fallback instead of broken nested iframe -->
                <div style="text-align:center; padding:38px 20px; background:var(--bg-card-subtle); border-radius:12px; border:1px dashed var(--border-hairline);">
                    <div style="width:54px; height:54px; border-radius:16px; background:rgba(255, 149, 0, 0.12); color:var(--apple-orange); display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px;">
                        <i data-lucide="file-question"></i>
                    </div>
                    <h3 style="font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
                        File Unavailable in Storage
                    </h3>
                    <p style="font-size:13px; color:var(--text-secondary); max-width:440px; margin:0 auto 16px; line-height:1.5;">
                        The uploaded file for this record is missing from storage (uploaded before persistent volume was attached). Please upload a new version to attach the file and enable preview.
                    </p>
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                        <i data-lucide="upload"></i> Upload New Version
                    </button>
                    <?php endif; ?>
                </div>

                <?php elseif ($ext === 'pdf'): ?>
                <!-- Universal In-Browser PDF Preview: Extracted Pages Reader + Slide View + Native Frame -->
                <div id="pdfViewerContainer" style="border-radius:10px; overflow:hidden; border:1px solid var(--border-hairline); background:#525659; width:100%; max-width:100%;">
                    <!-- PDF Controls Toolbar -->
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#323639; padding:8px 14px; color:#f1f1f1; font-size:12.5px; border-bottom:1px solid rgba(255,255,255,0.1); flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span id="pdfPageBadge" class="badge" style="background:rgba(255,255,255,0.15); color:#fff; font-size:11.5px; padding:3px 8px; border-radius:6px;">
                                <span id="pdfExtractedCount">Loading pages…</span>
                            </span>
                            <div id="pdfSlideNavControls" style="display:none; align-items:center; gap:6px;">
                                <button type="button" id="pdfPrevPage" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">‹ Prev</button>
                                <span style="font-weight:500;">
                                    Page <span id="pdfPageNum">1</span> / <span id="pdfPageCount">—</span>
                                </span>
                                <button type="button" id="pdfNextPage" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">Next ›</button>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <button type="button" id="pdfViewToggleBtn" class="btn btn-sm" style="background:rgba(255,255,255,0.15); color:#fff; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11.5px;">
                                <i data-lucide="layers" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> All Pages View
                            </button>
                            <button type="button" id="pdfZoomOut" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">−</button>
                            <span id="pdfZoomLevel" style="font-size:11.5px; min-width:38px; text-align:center;">100%</span>
                            <button type="button" id="pdfZoomIn" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">+</button>
                            <button type="button" id="pdfNativeToggle" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11.5px;" title="Switch to browser native PDF frame">
                                Native View
                            </button>
                            <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank" class="btn btn-sm" style="background:rgba(0,113,227,0.85); color:#fff; border:none; padding:4px 10px; border-radius:6px; text-decoration:none;">
                                <i data-lucide="external-link" style="width:12px; height:12px; display:inline;"></i> Full Screen
                            </a>
                        </div>
                    </div>
                    <!-- PDF Canvas Viewport -->
                    <div id="pdfCanvasWrapper" style="overflow-x:auto; overflow-y:auto; max-height:720px; min-height:480px; padding:16px; background:#525659; -webkit-overflow-scrolling:touch;">
                        <div id="pdfLoadingSpinner" style="color:#fff; padding:40px; text-align:center;">
                            <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px;"></i>
                            <div>Extracting PDF pages…</div>
                        </div>
                        <!-- Container for all extracted pages stacked vertically -->
                        <div id="pdfPagesContainer" style="display:none; flex-direction:column; align-items:center; gap:20px; width:100%;"></div>
                        <!-- Single slide view container -->
                        <div id="pdfSingleContainer" style="display:none; justify-content:center; align-items:flex-start; width:100%;">
                            <canvas id="pdfSingleCanvas" style="box-shadow:0 4px 20px rgba(0,0,0,0.35); max-width:100%; height:auto; background:#fff; border-radius:4px;"></canvas>
                        </div>
                        <!-- Native iframe container -->
                        <div id="pdfNativeWrapper" style="display:none; width:100%; height:680px;">
                            <iframe id="pdfIframe" src="" style="width:100%; height:100%; border:none; border-radius:6px; background:#fff;"></iframe>
                        </div>
                    </div>
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
                        Tap or click image to view in full resolution
                    </div>
                </div>

                <?php elseif ($ext === 'docx'): ?>
                <!-- DOCX Interactive Document Viewer -->
                <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline);">
                        <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                            <i data-lucide="file-text" style="color:var(--apple-blue); width:18px; height:18px;"></i>
                            Word Document Preview
                        </div>
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                            <i data-lucide="download"></i> Download .docx
                        </a>
                    </div>
                    <div id="docxPreviewContainer" style="padding:24px; max-height:650px; overflow:auto; background:#fff; color:#1d1d1f; line-height:1.6; -webkit-overflow-scrolling:touch;">
                        <div id="docxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                            <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-blue);"></i>
                            <div>Rendering Word document…</div>
                        </div>
                    </div>
                </div>

                <?php elseif (in_array($ext, ['xls', 'xlsx'])): ?>
                <!-- Excel-Styled Interactive Spreadsheet Grid Viewer -->
                <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; background:#107c41; color:#fff; border-radius:4px; font-size:12px; font-weight:700;">X</span>
                            <span>Excel Spreadsheet View</span>
                            <span id="xlsxGridMetaBadge" class="badge" style="background:rgba(16,124,65,0.12); color:#107c41; font-size:11px; padding:2px 8px; border-radius:10px; display:none;"></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <div id="xlsxSheetsContainer" style="display:flex; gap:4px; flex-wrap:wrap;"></div>
                            <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                                <i data-lucide="download"></i> Download .<?= $ext ?>
                            </a>
                        </div>
                    </div>
                    <div id="xlsxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                        <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:#107c41;"></i>
                        <div>Building spreadsheet grid…</div>
                    </div>
                    <div id="xlsxTableWrapper" class="excel-grid-wrapper" style="width:100%; max-width:100%; overflow-x:auto !important; overflow-y:auto !important; max-height:580px; -webkit-overflow-scrolling:touch; background:var(--bg-card);"></div>
                </div>

                <?php elseif (in_array($ext, ['ppt', 'pptx'])): ?>
                <!-- PowerPoint Interactive Presentation & Extracted Slide Pages Viewer -->
                <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                            <i data-lucide="presentation" style="color:var(--apple-orange); width:18px; height:18px;"></i>
                            PowerPoint Slides & Pages
                            <span id="pptxImageCountBadge" class="badge" style="background:rgba(255,149,0,0.15); color:var(--apple-orange); font-size:11px; padding:2px 8px; border-radius:12px; display:none;"></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <div id="pptxViewModes" style="display:none; gap:4px; flex-wrap:wrap;">
                                <button type="button" id="pptxBtnPages" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:4px 10px;">All Slide Pages</button>
                                <button type="button" id="pptxBtnDeck" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Slide Deck</button>
                                <button type="button" id="pptxBtnGrid" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Media Grid</button>
                            </div>
                            <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                                <i data-lucide="download"></i> Download .<?= $ext ?>
                            </a>
                        </div>
                    </div>
                    <div id="pptxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                        <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-orange);"></i>
                        <div>Extracting presentation slide pages…</div>
                    </div>
                    <!-- All Extracted Slide Pages (Scrollable Stack) -->
                    <div id="pptxPagesContainer" style="display:none; padding:16px; max-height:680px; overflow-y:auto; -webkit-overflow-scrolling:touch;">
                        <div id="pptxPagesWrapper" style="display:flex; flex-direction:column; gap:20px; align-items:center; width:100%; max-width:860px; margin:0 auto;"></div>
                    </div>
                    <!-- Slide Deck View (Featured Slide + Controls + Thumbnails) -->
                    <div id="pptxDeckContainer" style="display:none; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; background:var(--bg-card-subtle); padding:8px 14px; border-radius:8px; font-size:12px; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <button type="button" id="pptxPrevBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">‹ Previous</button>
                                <span style="font-weight:600;" id="pptxSlideIndicator">Slide 1 of 1</span>
                                <button type="button" id="pptxNextBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">Next ›</button>
                            </div>
                            <span id="pptxCurrentFileName" class="text-muted" style="font-size:11.5px;"></span>
                        </div>
                        <div style="text-align:center; background:#1c1c1e; border-radius:8px; padding:16px; min-height:360px; display:flex; align-items:center; justify-content:center;">
                            <a id="pptxActiveImageLink" href="#" target="_blank" title="Click to view full slide in new tab">
                                <img id="pptxActiveImage" src="" alt="Presentation Slide" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,0.5); cursor:zoom-in;">
                            </a>
                        </div>
                        <!-- Thumbnail Strip -->
                        <div id="pptxThumbStrip" style="display:flex; gap:8px; overflow-x:auto; padding:12px 0 4px; margin-top:10px;"></div>
                    </div>
                    <!-- Grid View of All Visuals -->
                    <div id="pptxGridContainer" style="display:none; padding:16px; max-height:600px; overflow-y:auto;">
                        <div id="pptxGridWrapper" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:14px;"></div>
                    </div>
                    <!-- Text Outline Fallback -->
                    <div id="pptxTextContainer" style="display:none; padding:20px; max-height:500px; overflow-y:auto;"></div>
                </div>

                <?php elseif ($ext === 'zip'): ?>
                <!-- ZIP Archive & Extracted Presentation Pages Inspector -->
                <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                            <i data-lucide="archive" style="color:var(--apple-orange); width:18px; height:18px;"></i>
                            <span id="zipHeaderTitle">Archive & Slide Pages</span>
                            <span id="zipBadgeCount" class="badge" style="background:rgba(255,149,0,0.15); color:var(--apple-orange); font-size:11px; padding:2px 8px; border-radius:12px; display:none;"></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <div id="zipViewModes" style="display:none; gap:4px; flex-wrap:wrap;">
                                <button type="button" id="zipBtnPages" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:4px 10px;">Extracted Pages</button>
                                <button type="button" id="zipBtnDeck" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Slide Deck</button>
                                <button type="button" id="zipBtnFiles" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">File Listing</button>
                            </div>
                            <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                                <i data-lucide="download"></i> Download ZIP
                            </a>
                        </div>
                    </div>
                    <div id="zipListLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                        <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-orange);"></i>
                        <div>Reading archive contents & slides…</div>
                    </div>
                    <!-- Extracted Pages Scrollable View (Page 1, Page 2... stacked) -->
                    <div id="zipPagesContainer" style="display:none; padding:16px; max-height:680px; overflow-y:auto; -webkit-overflow-scrolling:touch;">
                        <div id="zipPagesWrapper" style="display:flex; flex-direction:column; gap:20px; align-items:center; width:100%; max-width:860px; margin:0 auto;"></div>
                    </div>
                    <!-- Slide Deck View -->
                    <div id="zipDeckContainer" style="display:none; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; background:var(--bg-card-subtle); padding:8px 14px; border-radius:8px; font-size:12px; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <button type="button" id="zipPrevBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">‹ Previous</button>
                                <span style="font-weight:600;" id="zipSlideIndicator">Page 1 of 1</span>
                                <button type="button" id="zipNextBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">Next ›</button>
                            </div>
                            <span id="zipCurrentFileName" class="text-muted" style="font-size:11.5px;"></span>
                        </div>
                        <div style="text-align:center; background:#1c1c1e; border-radius:8px; padding:16px; min-height:360px; display:flex; align-items:center; justify-content:center;">
                            <a id="zipActiveImageLink" href="#" target="_blank" title="Click to view full image in new tab">
                                <img id="zipActiveImage" src="" alt="Extracted Slide Page" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,0.5); cursor:zoom-in;">
                            </a>
                        </div>
                        <!-- Thumbnail Strip -->
                        <div id="zipThumbStrip" style="display:flex; gap:8px; overflow-x:auto; padding:12px 0 4px; margin-top:10px;"></div>
                    </div>
                    <!-- File list wrapper -->
                    <div id="zipFileListWrapper" style="display:none; padding:12px; max-height:500px; overflow:auto;"></div>
                </div>

                <?php elseif ($ext === 'txt'): ?>
                <!-- Text File Preview -->
                <?php
                $fullPath = realpath(__DIR__ . '/' . ltrim($doc['file_path'], '/\\'));
                $textContent = ($fullPath && is_file($fullPath)) ? file_get_contents($fullPath) : '';
                ?>
                <pre style="background:rgba(0,0,0,0.02); border:1px solid var(--border-hairline); border-radius:10px; padding:18px 20px; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12.5px; line-height:1.6; max-height:500px; overflow:auto; color:var(--text-primary); white-space:pre-wrap;"><?= htmlspecialchars($textContent) ?></pre>

                <?php else: ?>
                <!-- Unsupported / Other Format Quick Look Card -->
                <div style="text-align:center; padding:44px 20px; background:linear-gradient(180deg, rgba(0,0,0,0.01) 0%, rgba(0,0,0,0.03) 100%); border-radius:12px; border:1px dashed var(--border-hairline);">
                    <div style="width:68px; height:68px; border-radius:18px; background:<?= $icon[2] ?>; color:<?= $icon[1] ?>; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:14px; box-shadow:0 4px 14px rgba(0,0,0,0.06);">
                        <i data-lucide="<?= $icon[0] ?>"></i>
                    </div>
                    <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
                        <?= htmlspecialchars($doc['file_name']) ?>
                    </h3>
                    <p style="font-size:13px; color:var(--text-secondary); max-width:460px; margin:0 auto 20px; line-height:1.5;">
                        This document is in <strong><?= strtoupper($ext) ?></strong> format (<?= htmlspecialchars($doc['file_type']) ?>). Tap below to download and view using your device's native app.
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
        <form method="POST" enctype="multipart/form-data" id="versionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="upload_version" value="1">
            <div class="form-group">
                <label>Select New File or Folder <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <div class="apple-dropzone" id="verDropZone" style="padding:20px; border:2px dashed var(--border-card); border-radius:12px; text-align:center; background:var(--bg-card-subtle); cursor:pointer;">
                    <div style="font-weight:600; font-size:13.5px; color:var(--text-primary); margin-bottom:4px;">
                        Choose a file or select a folder
                    </div>
                    <div class="text-muted text-sm" style="margin-bottom:12px;">
                        Supported: PDF, Word, Excel, PPT, Text, Images, ZIP. Folders will be automatically packaged into a .ZIP file. Max 20MB. Current version is v<?= $doc['version'] ?>.
                    </div>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-outline btn-sm" id="verChooseFileBtn"><i data-lucide="file-up"></i> Choose File</button>
                        <button type="button" class="btn btn-outline btn-sm" id="verChooseFolderBtn"><i data-lucide="folder-up"></i> Choose Folder</button>
                    </div>
                    <div id="verFolderHint" class="text-muted text-sm" style="display:none; margin-top:9px;"></div>

                    <!-- Progress indicator while zipping folder -->
                    <div id="verZipProgress" style="display:none; margin-top:12px; font-size:12.5px; color:var(--apple-blue); font-weight:500;">
                        <i data-lucide="loader-circle" class="icon-spin"></i> Compressing folder into ZIP archive…
                    </div>

                    <!-- Selected file preview capsule -->
                    <div id="verFilePreview" style="display:none; margin-top:12px; align-items:center; justify-content:center; gap:8px;">
                        <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(0,113,227,0.12); color:var(--apple-blue); font-weight:600; font-size:12px; padding:5px 12px; border-radius:var(--radius-pill);">
                            <i data-lucide="file-check"></i> <span id="verFileName">File</span> (<span id="verFileSize">0 KB</span>)
                        </span>
                        <button type="button" id="verRemoveFileBtn" onclick="event.stopPropagation(); resetVerFile();" style="border:none; background:rgba(0,0,0,0.06); width:24px; height:24px; border-radius:50%; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; justify-content:center;">
                            <i data-lucide="x" style="font-size:11px;"></i>
                        </button>
                    </div>
                </div>

                <input type="file" id="verFileInput" name="new_version_file" style="display:none;"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.zip">
                <input type="file" id="verFolderInput" style="display:none;" webkitdirectory directory multiple>
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
                <button type="submit" id="verSubmitBtn" class="btn btn-primary">Upload v<?= $doc['version'] + 1 ?></button>
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

<script src="<?= APP_URL ?>/assets/js/jszip.min.js"></script>
<script>
(function() {
    const verForm         = document.getElementById('versionForm');
    const verDropZone     = document.getElementById('verDropZone');
    const verFileInput    = document.getElementById('verFileInput');
    const verFolderInput  = document.getElementById('verFolderInput');
    const verChooseFileBtn = document.getElementById('verChooseFileBtn');
    const verChooseFolderBtn = document.getElementById('verChooseFolderBtn');
    const verFilePreview  = document.getElementById('verFilePreview');
    const verFileName     = document.getElementById('verFileName');
    const verFileSize     = document.getElementById('verFileSize');
    const verZipProgress  = document.getElementById('verZipProgress');
    const verFolderHint   = document.getElementById('verFolderHint');
    const verSubmitBtn    = document.getElementById('verSubmitBtn');

    let isZipping = false;

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        const units = ['B','KB','MB','GB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + units[i];
    }

    function setInputFiles(input, fileList) {
        try {
            const dt = new DataTransfer();
            fileList.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (e) {
            console.error('DataTransfer assignment failed:', e);
        }
    }

    window.resetVerFile = function() {
        isZipping = false;
        verFileInput.value = '';
        verFolderInput.value = '';
        if (verFilePreview) verFilePreview.style.display = 'none';
        if (verFolderHint) verFolderHint.style.display = 'none';
        if (verZipProgress) verZipProgress.style.display = 'none';
        if (verSubmitBtn) verSubmitBtn.disabled = false;
        if (verDropZone) verDropZone.style.borderColor = 'var(--border-card)';
    };

    function showSelectedFile(file, note) {
        if (!file) return;
        if (verFileName) verFileName.textContent = file.name;
        if (verFileSize) verFileSize.textContent = formatBytes(file.size);
        if (verFilePreview) verFilePreview.style.display = 'inline-flex';
        if (note && verFolderHint) {
            verFolderHint.innerHTML = note;
            verFolderHint.style.display = 'block';
        } else if (verFolderHint) {
            verFolderHint.style.display = 'none';
        }
        if (window.lucide) lucide.createIcons();
    }

    verDropZone?.addEventListener('click', () => verFileInput.click());
    verChooseFileBtn?.addEventListener('click', (e) => { e.stopPropagation(); verFileInput.click(); });
    verChooseFolderBtn?.addEventListener('click', (e) => { e.stopPropagation(); verFolderInput.click(); });

    verFileInput?.addEventListener('change', () => {
        verFolderInput.value = '';
        if (verFileInput.files && verFileInput.files.length) {
            showSelectedFile(verFileInput.files[0]);
        } else {
            resetVerFile();
        }
    });

    verFolderInput?.addEventListener('change', async () => {
        const rawFiles = Array.from(verFolderInput.files || []);
        if (!rawFiles.length) {
            resetVerFile();
            return;
        }

        let folderName = 'archive';
        const firstPath = rawFiles[0].webkitRelativePath || rawFiles[0].name;
        const parts = firstPath.split(/[\\/]/);
        if (parts.length > 1 && parts[0].trim() !== '') {
            folderName = parts[0].trim();
        }

        if (typeof JSZip === 'undefined') {
            alert('Folder compression is currently unavailable because the ZIP library could not load. Please upload a file directly.');
            return;
        }

        const allowedExts = new Set(['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','png','jpg','jpeg','zip']);
        const supportedFiles = rawFiles.filter(f => {
            const ext = f.name.toLowerCase().split('.').pop();
            return allowedExts.has(ext);
        });
        const skippedFiles = rawFiles.filter(f => !supportedFiles.includes(f));

        if (!supportedFiles.length) {
            alert('The selected folder does not contain any supported documents (PDF, Word, Excel, PPT, Text, Images, ZIP).');
            resetVerFile();
            return;
        }

        try {
            isZipping = true;
            if (verSubmitBtn) verSubmitBtn.disabled = true;
            if (verZipProgress) {
                verZipProgress.style.display = 'block';
                if (window.lucide) lucide.createIcons();
            }
            if (verFolderHint) {
                verFolderHint.style.display = 'block';
                verFolderHint.textContent = `Packaging ${supportedFiles.length} file(s) from "${folderName}" into a ZIP archive…`;
            }

            const zip = new JSZip();
            for (const file of supportedFiles) {
                const relPath = file.webkitRelativePath || file.name;
                zip.file(relPath, file);
            }

            const zipBlob = await zip.generateAsync({
                type: 'blob',
                compression: 'DEFLATE',
                compressionOptions: { level: 6 }
            });

            const zipFile = new File([zipBlob], `${folderName}.zip`, { type: 'application/zip' });
            setInputFiles(verFileInput, [zipFile]);
            verFolderInput.value = '';

            if (verZipProgress) verZipProgress.style.display = 'none';
            if (verSubmitBtn) verSubmitBtn.disabled = false;
            isZipping = false;

            let note = `<i data-lucide="archive" style="display:inline; width:13px; height:13px;"></i> Folder "<strong>${folderName}</strong>" compressed into <code>${folderName}.zip</code> (${supportedFiles.length} files).`;
            if (skippedFiles.length) {
                note += `<br><span style="color:var(--apple-orange);">${skippedFiles.length} unsupported file(s) skipped.</span>`;
            }
            showSelectedFile(zipFile, note);
        } catch (err) {
            console.error('Error zipping folder in version modal:', err);
            if (verZipProgress) verZipProgress.style.display = 'none';
            if (verSubmitBtn) verSubmitBtn.disabled = false;
            isZipping = false;
            alert('Could not compress the selected folder into a ZIP archive.');
            resetVerFile();
        }
    });

    // Drag and drop into version modal dropzone
    ['dragenter', 'dragover'].forEach(name => {
        verDropZone?.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            verDropZone.style.borderColor = 'var(--apple-blue)';
        });
    });

    ['dragleave', 'dragend', 'drop'].forEach(name => {
        verDropZone?.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            verDropZone.style.borderColor = 'var(--border-card)';
        });
    });

    verDropZone?.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            try {
                verFileInput.files = e.dataTransfer.files;
            } catch (err) {}
            showSelectedFile(e.dataTransfer.files[0]);
        }
    });

    verForm?.addEventListener('submit', function(e) {
        if (isZipping) {
            e.preventDefault();
            alert('Please wait until the folder finishes compressing into a ZIP file.');
            return false;
        }
        if (!verFileInput.files || !verFileInput.files.length) {
            e.preventDefault();
            alert('Please select a file or folder for the new document version.');
            return false;
        }
        const maxBytes = 20 * 1024 * 1024;
        if (verFileInput.files[0].size > maxBytes) {
            e.preventDefault();
            alert(`The chosen file is ${formatBytes(verFileInput.files[0].size)}, exceeding the 20MB limit.`);
            return false;
        }
    });
})();
</script>
<!-- ── Multi-Format Document Preview Libraries ── -->
<?php if ($hasDiskFile): ?>
    <?php if ($ext === 'pdf'): ?>
    <script src="<?= APP_URL ?>/assets/vendor/pdfjs/pdf.min.js"></script>
    <script>
    (function() {
        const spinner = document.getElementById('pdfLoadingSpinner');
        const pagesContainer = document.getElementById('pdfPagesContainer');
        const singleContainer = document.getElementById('pdfSingleContainer');
        const singleCanvas = document.getElementById('pdfSingleCanvas');
        const nativeWrapper = document.getElementById('pdfNativeWrapper');
        const iframe = document.getElementById('pdfIframe');
        const pageBadge = document.getElementById('pdfPageBadge');
        const extractedCount = document.getElementById('pdfExtractedCount');
        const slideNavControls = document.getElementById('pdfSlideNavControls');
        const pageNumEl = document.getElementById('pdfPageNum');
        const pageCountEl = document.getElementById('pdfPageCount');
        const zoomLevelEl = document.getElementById('pdfZoomLevel');
        const viewToggleBtn = document.getElementById('pdfViewToggleBtn');
        const nativeToggleBtn = document.getElementById('pdfNativeToggle');
        const prevBtn = document.getElementById('pdfPrevPage');
        const nextBtn = document.getElementById('pdfNextPage');
        const zoomInBtn = document.getElementById('pdfZoomIn');
        const zoomOutBtn = document.getElementById('pdfZoomOut');
        const pdfUrl = '<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>';

        if (typeof pdfjsLib === 'undefined') {
            if (spinner) {
                spinner.innerHTML = `
                    <div style="color:var(--apple-orange); margin-bottom:12px;"><i data-lucide="alert-triangle"></i> PDF reader library could not be loaded.</div>
                    <a href="${pdfUrl}" target="_blank" class="btn btn-primary btn-sm">Open PDF in New Tab</a>
                `;
                if (window.lucide) lucide.createIcons();
            }
            return;
        }

        // Configure worker safely
        pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= APP_URL ?>/assets/vendor/pdfjs/pdf.worker.min.js';

        let pdfDoc = null;
        let scale = 1.0;
        let activePage = 1;
        let viewMode = 'all'; // 'all', 'single', or 'native'
        let isRendering = false;

        function updateZoomText() {
            if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
        }

        function renderAllPages() {
            if (!pdfDoc || !pagesContainer) return;
            pagesContainer.innerHTML = '';
            pagesContainer.style.display = 'flex';
            if (singleContainer) singleContainer.style.display = 'none';
            if (nativeWrapper) nativeWrapper.style.display = 'none';
            if (slideNavControls) slideNavControls.style.display = 'none';
            if (viewToggleBtn) {
                viewToggleBtn.innerHTML = '<i data-lucide="presentation" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> Single Slide';
                if (window.lucide) lucide.createIcons();
            }

            const wrapperWidth = document.getElementById('pdfCanvasWrapper')?.clientWidth || 700;
            const targetWidth = Math.max(Math.min(wrapperWidth - 40, 860) * scale, 300);

            for (let p = 1; p <= pdfDoc.numPages; p++) {
                const card = document.createElement('div');
                card.className = 'extracted-page-card';
                card.style.cssText = 'background:#fff; max-width:860px; width:100%; box-shadow:0 6px 20px rgba(0,0,0,0.3); border-radius:6px; overflow:hidden; margin:0 auto 20px;';
                card.innerHTML = `
                    <div class="extracted-page-header" style="background:#2c2c2e; color:#f1f1f1; padding:6px 14px; font-size:11.5px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:600;">Page ${p} of ${pdfDoc.numPages}</span>
                        <a href="${pdfUrl}" target="_blank" style="color:#64d2ff; text-decoration:none; font-size:11px;">View Full Page</a>
                    </div>
                    <div style="display:flex; justify-content:center; padding:0; background:#fff; overflow:hidden;">
                        <canvas id="pdfCanvasPage_${p}" style="display:block; max-width:100%; height:auto;"></canvas>
                    </div>
                `;
                pagesContainer.appendChild(card);

                pdfDoc.getPage(p).then(page => {
                    const canvas = document.getElementById(`pdfCanvasPage_${p}`);
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    const unscaledViewport = page.getViewport({ scale: 1.0 });
                    const renderScale = (targetWidth / unscaledViewport.width);
                    const viewport = page.getViewport({ scale: renderScale });

                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.floor(viewport.width * dpr);
                    canvas.height = Math.floor(viewport.height * dpr);
                    canvas.style.width = Math.floor(viewport.width) + 'px';
                    canvas.style.height = Math.floor(viewport.height) + 'px';

                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport,
                        transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
                    };
                    page.render(renderContext);
                }).catch(err => console.error('Error rendering page ' + p, err));
            }
        }

        function renderSinglePage(num) {
            if (!pdfDoc || !singleCanvas) return;
            if (num < 1) num = 1;
            if (num > pdfDoc.numPages) num = pdfDoc.numPages;
            activePage = num;

            if (pageNumEl) pageNumEl.textContent = activePage;
            if (pageCountEl) pageCountEl.textContent = pdfDoc.numPages;

            pagesContainer.style.display = 'none';
            if (singleContainer) singleContainer.style.display = 'flex';
            if (nativeWrapper) nativeWrapper.style.display = 'none';
            if (slideNavControls) slideNavControls.style.display = 'flex';
            if (viewToggleBtn) {
                viewToggleBtn.innerHTML = '<i data-lucide="layers" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> All Pages';
                if (window.lucide) lucide.createIcons();
            }

            pdfDoc.getPage(activePage).then(page => {
                const ctx = singleCanvas.getContext('2d');
                const wrapperWidth = document.getElementById('pdfCanvasWrapper')?.clientWidth || 700;
                const unscaledViewport = page.getViewport({ scale: 1.0 });
                const targetWidth = Math.max(Math.min(wrapperWidth - 40, 860) * scale, 300);
                const renderScale = (targetWidth / unscaledViewport.width);
                const viewport = page.getViewport({ scale: renderScale });

                const dpr = window.devicePixelRatio || 1;
                singleCanvas.width = Math.floor(viewport.width * dpr);
                singleCanvas.height = Math.floor(viewport.height * dpr);
                singleCanvas.style.width = Math.floor(viewport.width) + 'px';
                singleCanvas.style.height = Math.floor(viewport.height) + 'px';

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport,
                    transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
                };
                page.render(renderContext);
            });
        }

        // View Mode Toggles
        viewToggleBtn?.addEventListener('click', function() {
            if (viewMode === 'all') {
                viewMode = 'single';
                renderSinglePage(activePage);
            } else {
                viewMode = 'all';
                renderAllPages();
            }
        });

        nativeToggleBtn?.addEventListener('click', function() {
            if (viewMode === 'native') {
                viewMode = 'all';
                nativeToggleBtn.style.background = 'rgba(255,255,255,0.1)';
                renderAllPages();
            } else {
                viewMode = 'native';
                nativeToggleBtn.style.background = 'rgba(0,113,227,0.85)';
                if (pagesContainer) pagesContainer.style.display = 'none';
                if (singleContainer) singleContainer.style.display = 'none';
                if (slideNavControls) slideNavControls.style.display = 'none';
                if (nativeWrapper) {
                    nativeWrapper.style.display = 'block';
                    if (iframe && !iframe.src) iframe.src = pdfUrl;
                }
            }
        });

        prevBtn?.addEventListener('click', () => {
            if (activePage > 1) renderSinglePage(activePage - 1);
        });
        nextBtn?.addEventListener('click', () => {
            if (activePage < pdfDoc.numPages) renderSinglePage(activePage + 1);
        });

        zoomInBtn?.addEventListener('click', () => {
            if (scale < 2.4) {
                scale = +(scale + 0.2).toFixed(1);
                updateZoomText();
                if (viewMode === 'single') renderSinglePage(activePage);
                else renderAllPages();
            }
        });

        zoomOutBtn?.addEventListener('click', () => {
            if (scale > 0.6) {
                scale = +(scale - 0.2).toFixed(1);
                updateZoomText();
                if (viewMode === 'single') renderSinglePage(activePage);
                else renderAllPages();
            }
        });

        // 6-second timeout fallback to native viewer if PDF.js stalls
        const loadTimeout = setTimeout(() => {
            if (!pdfDoc && spinner) {
                spinner.innerHTML = `
                    <div style="color:var(--apple-orange); margin-bottom:12px; font-weight:600;">Canvas rendering is taking longer than expected.</div>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" id="pdfFallbackNativeBtn" class="btn btn-primary btn-sm">Switch to Native PDF Frame</button>
                        <a href="${pdfUrl}" target="_blank" class="btn btn-outline btn-sm" style="color:#fff; border-color:#fff;">Open Full PDF in New Tab</a>
                    </div>
                `;
                document.getElementById('pdfFallbackNativeBtn')?.addEventListener('click', () => {
                    nativeToggleBtn?.click();
                });
            }
        }, 6000);

        fetch(pdfUrl)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.arrayBuffer();
            })
            .then(buf => {
                return pdfjsLib.getDocument({
                    data: new Uint8Array(buf),
                    cMapUrl: '<?= APP_URL ?>/assets/vendor/pdfjs/cmaps/',
                    cMapPacked: true,
                    isEvalSupported: false
                }).promise;
            })
            .then(pdfDoc_ => {
                clearTimeout(loadTimeout);
                pdfDoc = pdfDoc_;
                if (spinner) spinner.style.display = 'none';
                if (extractedCount) extractedCount.textContent = `${pdfDoc.numPages} Pages Extracted`;
                if (pageCountEl) pageCountEl.textContent = pdfDoc.numPages;
                renderAllPages();
            })
            .catch(err => {
                clearTimeout(loadTimeout);
                console.error('PDF.js render error:', err);
                if (spinner) {
                    spinner.innerHTML = `
                        <div style="color:var(--apple-orange); margin-bottom:12px; font-weight:600;"><i data-lucide="alert-triangle"></i> Notice: Device previewing via native viewer.</div>
                        <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
                            <button type="button" id="pdfErrorNativeBtn" class="btn btn-primary btn-sm">Load in Native Frame</button>
                            <a href="${pdfUrl}" target="_blank" class="btn btn-outline btn-sm" style="color:#fff; border-color:#fff;">Open PDF in New Tab</a>
                        </div>
                    `;
                    document.getElementById('pdfErrorNativeBtn')?.addEventListener('click', () => {
                        nativeToggleBtn?.click();
                    });
                    if (window.lucide) lucide.createIcons();
                }
            });
    })();
    </script>
    <?php endif; ?>

    <?php if ($ext === 'docx'): ?>
    <script src="<?= APP_URL ?>/assets/vendor/docx/docx-preview.min.js"></script>
    <script>
    (function() {
        const docxContainer = document.getElementById('docxPreviewContainer');
        const loading = document.getElementById('docxLoading');
        if (!docxContainer) return;
        if (typeof docx === 'undefined') {
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Word viewer library could not be loaded. Please download the file to view.</div>';
            return;
        }

        fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
            .then(res => {
                if (!res.ok) throw new Error('Network error loading DOCX');
                return res.blob();
            })
            .then(blob => {
                if (loading) loading.style.display = 'none';
                docx.renderAsync(blob, docxContainer, null, {
                    className: 'docx-rendered-body',
                    inWrapper: false,
                    ignoreWidth: false,
                    ignoreHeight: false
                }).catch(err => {
                    console.error('docx preview render error:', err);
                    docxContainer.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Unable to parse document layout. Please download the file to view.</div>';
                });
            })
            .catch(err => {
                console.error('docx fetch error:', err);
                if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not load document preview. Please download to view.</div>';
            });
    })();
    </script>
    <?php endif; ?>

    <?php if (in_array($ext, ['xls', 'xlsx'])): ?>
    <script src="<?= APP_URL ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
    <script>
    (function() {
        const tableWrapper = document.getElementById('xlsxTableWrapper');
        const sheetsContainer = document.getElementById('xlsxSheetsContainer');
        const loading = document.getElementById('xlsxLoading');
        const metaBadge = document.getElementById('xlsxGridMetaBadge');
        if (!tableWrapper) return;
        if (typeof XLSX === 'undefined') {
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Spreadsheet viewer library could not be loaded. Please download the file to view.</div>';
            return;
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function getColLetter(idx) {
            let s = '';
            while (idx >= 0) {
                s = String.fromCharCode((idx % 26) + 65) + s;
                idx = Math.floor(idx / 26) - 1;
            }
            return s;
        }

        fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.arrayBuffer();
            })
            .then(buffer => {
                const workbook = XLSX.read(buffer, { type: 'array' });
                if (loading) loading.style.display = 'none';

                if (!workbook.SheetNames || !workbook.SheetNames.length) {
                    tableWrapper.innerHTML = '<div class="text-muted text-center" style="padding:20px;">No sheets found in spreadsheet.</div>';
                    return;
                }

                function renderSheet(name) {
                    const sheet = workbook.Sheets[name];
                    const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false });

                    if (!rows || !rows.length) {
                        tableWrapper.innerHTML = '<div class="text-muted text-center" style="padding:40px;">This sheet is empty.</div>';
                        if (metaBadge) metaBadge.style.display = 'none';
                        return;
                    }

                    let maxCols = 0;
                    rows.forEach(r => { if (r && r.length > maxCols) maxCols = r.length; });
                    if (maxCols === 0) maxCols = 1;

                    if (metaBadge) {
                        metaBadge.textContent = `${rows.length} Rows × ${maxCols} Cols`;
                        metaBadge.style.display = 'inline-block';
                    }

                    // Build authentic Excel grid table directly inside the responsive scroll wrapper
                    let html = '<table class="excel-table">';
                    
                    // Column Letters Header Row (A, B, C...)
                    html += '<thead><tr>';
                    html += '<th></th>';
                    for (let c = 0; c < maxCols; c++) {
                        html += `<th>${getColLetter(c)}</th>`;
                    }
                    html += '</tr></thead><tbody>';

                    // Data Rows with Row Numbers (1, 2, 3...)
                    rows.forEach((row, rIdx) => {
                        html += '<tr>';
                        html += `<th>${rIdx + 1}</th>`;
                        for (let c = 0; c < maxCols; c++) {
                            const rawVal = row ? row[c] : '';
                            const strVal = (rawVal !== undefined && rawVal !== null) ? String(rawVal) : '';
                            const isNum = !isNaN(strVal) && strVal.trim() !== '' && !/^[A-Za-z]/.test(strVal);
                            const alignStyle = isNum ? 'text-align:right; font-variant-numeric:tabular-nums;' : 'text-align:left;';
                            html += `<td style="white-space:nowrap; max-width:320px; overflow:hidden; text-overflow:ellipsis; ${alignStyle}">${escHtml(strVal)}</td>`;
                        }
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    tableWrapper.innerHTML = html;
                }

                // Render sheet tabs styled like Excel
                sheetsContainer.innerHTML = '';
                workbook.SheetNames.forEach((name, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = idx === 0 ? 'btn btn-sm' : 'btn btn-outline btn-sm';
                    if (idx === 0) {
                        btn.style.cssText = 'background:#107c41; color:#fff; border:none; font-size:11.5px; padding:4px 12px; font-weight:600; border-radius:6px;';
                    } else {
                        btn.style.cssText = 'font-size:11.5px; padding:4px 12px; border-radius:6px;';
                    }
                    btn.textContent = name;
                    btn.onclick = () => {
                        sheetsContainer.querySelectorAll('button').forEach(b => {
                            b.className = 'btn btn-outline btn-sm';
                            b.style.cssText = 'font-size:11.5px; padding:4px 12px; border-radius:6px;';
                        });
                        btn.className = 'btn btn-sm';
                        btn.style.cssText = 'background:#107c41; color:#fff; border:none; font-size:11.5px; padding:4px 12px; font-weight:600; border-radius:6px;';
                        renderSheet(name);
                    };
                    sheetsContainer.appendChild(btn);
                });

                renderSheet(workbook.SheetNames[0]);
            })
            .catch(err => {
                console.error('XLSX render error:', err);
                if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not render spreadsheet. Please download to view.</div>';
            });
    })();
    </script>
    <?php endif; ?>

    <?php if (in_array($ext, ['ppt', 'pptx'])): ?>
    <script>
    (function() {
        const loading = document.getElementById('pptxLoading');
        const pagesContainer = document.getElementById('pptxPagesContainer');
        const pagesWrapper = document.getElementById('pptxPagesWrapper');
        const deckContainer = document.getElementById('pptxDeckContainer');
        const gridContainer = document.getElementById('pptxGridContainer');
        const textContainer = document.getElementById('pptxTextContainer');
        const viewModes = document.getElementById('pptxViewModes');
        const countBadge = document.getElementById('pptxImageCountBadge');

        const activeImg = document.getElementById('pptxActiveImage');
        const activeLink = document.getElementById('pptxActiveImageLink');
        const prevBtn = document.getElementById('pptxPrevBtn');
        const nextBtn = document.getElementById('pptxNextBtn');
        const indicator = document.getElementById('pptxSlideIndicator');
        const fileNameEl = document.getElementById('pptxCurrentFileName');
        const thumbStrip = document.getElementById('pptxThumbStrip');
        const gridWrapper = document.getElementById('pptxGridWrapper');
        const btnPages = document.getElementById('pptxBtnPages');
        const btnDeck = document.getElementById('pptxBtnDeck');
        const btnGrid = document.getElementById('pptxBtnGrid');

        if (typeof JSZip === 'undefined') {
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Presentation viewer library could not be loaded. Please download the file to view.</div>';
            return;
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function naturalSort(a, b) {
            const aNum = (a.match(/\d+/) || [])[0];
            const bNum = (b.match(/\d+/) || [])[0];
            if (aNum !== undefined && bNum !== undefined && aNum !== bNum) {
                return parseInt(aNum, 10) - parseInt(bNum, 10);
            }
            return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
        }

        fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.arrayBuffer();
            })
            .then(buffer => JSZip.loadAsync(buffer))
            .then(async zip => {
                // Find all image files in presentation (ppt/media/*, docProps/thumbnail.jpeg)
                const imageFileNames = Object.keys(zip.files).filter(name => {
                    if (zip.files[name].dir) return false;
                    return /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(name);
                });
                imageFileNames.sort(naturalSort);

                const images = [];
                for (const name of imageFileNames) {
                    try {
                        const blob = await zip.files[name].async('blob');
                        const url = URL.createObjectURL(blob);
                        const cleanName = name.replace(/^ppt\/media\//i, '').replace(/^[^\/]+\//, '');
                        images.push({
                            fullName: name,
                            name: cleanName,
                            url: url,
                            size: blob.size
                        });
                    } catch (e) {
                        console.error('Error extracting image', name, e);
                    }
                }

                // Also parse slides XML
                const slideFiles = Object.keys(zip.files).filter(name => /^ppt\/slides\/slide\d+\.xml$/i.test(name));
                slideFiles.sort(naturalSort);

                const parsedSlides = [];
                for (let i = 0; i < slideFiles.length; i++) {
                    try {
                        const xmlStr = await zip.files[slideFiles[i]].async('string');
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(xmlStr, 'text/xml');
                        const textNodes = xmlDoc.getElementsByTagName('a:t');
                        const lines = [];
                        for (let j = 0; j < textNodes.length; j++) {
                            const t = textNodes[j].textContent.trim();
                            if (t) lines.push(t);
                        }
                        parsedSlides.push({
                            slideIndex: i + 1,
                            title: lines[0] || `Slide ${i + 1}`,
                            lines: lines
                        });
                    } catch (e) {}
                }

                if (loading) loading.style.display = 'none';

                const totalItems = images.length || parsedSlides.length;
                if (totalItems > 0) {
                    if (countBadge) {
                        countBadge.textContent = `${totalItems} Slide Pages`;
                        countBadge.style.display = 'inline-block';
                    }
                    if (viewModes) viewModes.style.display = 'flex';

                    // 1. Render All Extracted Slide Pages (Stacked View)
                    if (pagesWrapper) {
                        pagesWrapper.innerHTML = '';
                        if (images.length > 0) {
                            images.forEach((img, idx) => {
                                const card = document.createElement('div');
                                card.className = 'extracted-page-card';
                                card.innerHTML = `
                                    <div class="extracted-page-header">
                                        <span>Slide Page ${idx + 1} of ${images.length}</span>
                                        <a href="${img.url}" target="_blank" class="text-sm" style="color:var(--apple-blue); text-decoration:none;">Open Full Slide</a>
                                    </div>
                                    <div style="background:#1c1c1e; padding:12px; display:flex; justify-content:center; align-items:center;">
                                        <img src="${img.url}" alt="Slide ${idx + 1}" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                                    </div>
                                `;
                                pagesWrapper.appendChild(card);
                            });
                        } else {
                            parsedSlides.forEach((slide, idx) => {
                                const card = document.createElement('div');
                                card.className = 'extracted-page-card';
                                card.innerHTML = `
                                    <div class="extracted-page-header">
                                        <span>Slide Page ${idx + 1} of ${parsedSlides.length}</span>
                                        <span class="text-muted">${escHtml(slide.title)}</span>
                                    </div>
                                    <div style="padding:28px 32px; background:linear-gradient(135deg, #ffffff 0%, #f5f5f7 100%); min-height:260px; display:flex; flex-direction:column; justify-content:center;">
                                        <h3 style="font-size:18px; font-weight:700; color:#1d1d1f; margin-bottom:14px;">${escHtml(slide.title)}</h3>
                                        <div style="font-size:13.5px; line-height:1.7; color:#3a3a3c;">
                                            ${slide.lines.slice(1).map(l => `<div style="margin-bottom:6px;">• ${escHtml(l)}</div>`).join('') || '<span class="text-muted">(Slide graphics or table)</span>'}
                                        </div>
                                    </div>
                                `;
                                pagesWrapper.appendChild(card);
                            });
                        }
                    }

                    // 2. Setup Slide Deck View (Featured Slide)
                    let currentDeckIdx = 0;
                    function showSlide(idx) {
                        if (images.length === 0) return;
                        if (idx < 0) idx = 0;
                        if (idx >= images.length) idx = images.length - 1;
                        currentDeckIdx = idx;
                        const item = images[idx];
                        if (activeImg) activeImg.src = item.url;
                        if (activeLink) activeLink.href = item.url;
                        if (indicator) indicator.textContent = `Slide ${idx + 1} of ${images.length}`;
                        if (fileNameEl) {
                            const kb = Math.round(item.size / 1024);
                            fileNameEl.textContent = `${item.name} (${kb} KB)`;
                        }
                        if (thumbStrip) {
                            thumbStrip.querySelectorAll('.pptx-thumb').forEach((th, i) => {
                                th.style.outline = (i === idx) ? '3px solid var(--apple-orange)' : 'none';
                                th.style.opacity = (i === idx) ? '1' : '0.65';
                            });
                            const activeThumb = thumbStrip.children[idx];
                            if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    }

                    if (thumbStrip && images.length > 0) {
                        thumbStrip.innerHTML = '';
                        images.forEach((item, idx) => {
                            const th = document.createElement('img');
                            th.className = 'pptx-thumb';
                            th.src = item.url;
                            th.alt = item.name;
                            th.style.cssText = 'height:54px; width:80px; object-fit:cover; border-radius:6px; cursor:pointer; flex-shrink:0; background:#000; transition:all 0.2s;';
                            th.onclick = () => showSlide(idx);
                            thumbStrip.appendChild(th);
                        });
                    }

                    if (gridWrapper && images.length > 0) {
                        gridWrapper.innerHTML = '';
                        images.forEach((item, idx) => {
                            const card = document.createElement('div');
                            card.style.cssText = 'border:1px solid var(--border-hairline); border-radius:8px; overflow:hidden; background:var(--bg-card-subtle); display:flex; flex-direction:column;';
                            const kb = Math.round(item.size / 1024);
                            card.innerHTML = `
                                <div style="height:150px; background:#1c1c1e; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer;">
                                    <img src="${item.url}" alt="${item.name}" style="max-height:100%; max-width:100%; object-fit:contain;">
                                </div>
                                <div style="padding:8px 10px; font-size:11.5px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border-hairline);">
                                    <span style="font-weight:600; color:var(--text-primary); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:120px;">Slide ${idx + 1}</span>
                                    <span class="text-muted">${kb} KB</span>
                                </div>
                            `;
                            card.querySelector('div').onclick = () => {
                                if (btnDeck) btnDeck.click();
                                showSlide(idx);
                            };
                            gridWrapper.appendChild(card);
                        });
                    }

                    if (prevBtn) prevBtn.onclick = () => showSlide(currentDeckIdx - 1);
                    if (nextBtn) nextBtn.onclick = () => showSlide(currentDeckIdx + 1);

                    // Mode Switching
                    function setViewMode(mode) {
                        [btnPages, btnDeck, btnGrid].forEach(b => {
                            if (b) {
                                b.className = 'btn btn-outline btn-sm';
                                b.style.fontSize = '11.5px';
                            }
                        });
                        if (pagesContainer) pagesContainer.style.display = 'none';
                        if (deckContainer) deckContainer.style.display = 'none';
                        if (gridContainer) gridContainer.style.display = 'none';

                        if (mode === 'pages' && pagesContainer) {
                            if (btnPages) btnPages.className = 'btn btn-primary btn-sm';
                            pagesContainer.style.display = 'block';
                        } else if (mode === 'deck' && deckContainer) {
                            if (btnDeck) btnDeck.className = 'btn btn-primary btn-sm';
                            deckContainer.style.display = 'block';
                            showSlide(currentDeckIdx);
                        } else if (mode === 'grid' && gridContainer) {
                            if (btnGrid) btnGrid.className = 'btn btn-primary btn-sm';
                            gridContainer.style.display = 'block';
                        }
                    }

                    if (btnPages) btnPages.onclick = () => setViewMode('pages');
                    if (btnDeck) btnDeck.onclick = () => setViewMode('deck');
                    if (btnGrid) btnGrid.onclick = () => setViewMode('grid');

                    // Default to All Slide Pages View
                    setViewMode('pages');
                } else {
                    if (textContainer) {
                        textContainer.innerHTML = '<div class="text-muted text-center" style="padding:30px;">Could not extract slides from this presentation. Please download the file to view.</div>';
                        textContainer.style.display = 'block';
                    }
                }
            })
            .catch(err => {
                console.error('PPTX parse error:', err);
                if (loading) {
                    loading.innerHTML = `
                        <div style="padding:20px; text-align:center;">
                            <div style="color:var(--text-secondary); margin-bottom:12px; font-size:13px;">This presentation file could not be parsed directly.</div>
                            <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary btn-sm">
                                <i data-lucide="download"></i> Download Presentation
                            </a>
                        </div>
                    `;
                    if (window.lucide) lucide.createIcons();
                }
            });
    })();
    </script>
    <?php endif; ?>

    <?php if ($ext === 'zip'): ?>
    <script>
    (function() {
        const loading = document.getElementById('zipListLoading');
        const headerTitle = document.getElementById('zipHeaderTitle');
        const badgeCount = document.getElementById('zipBadgeCount');
        const viewModes = document.getElementById('zipViewModes');
        const pagesContainer = document.getElementById('zipPagesContainer');
        const pagesWrapper = document.getElementById('zipPagesWrapper');
        const deckContainer = document.getElementById('zipDeckContainer');
        const listWrapper = document.getElementById('zipFileListWrapper');
        const btnPages = document.getElementById('zipBtnPages');
        const btnDeck = document.getElementById('zipBtnDeck');
        const btnFiles = document.getElementById('zipBtnFiles');

        const activeImg = document.getElementById('zipActiveImage');
        const activeLink = document.getElementById('zipActiveImageLink');
        const prevBtn = document.getElementById('zipPrevBtn');
        const nextBtn = document.getElementById('zipNextBtn');
        const indicator = document.getElementById('zipSlideIndicator');
        const fileNameEl = document.getElementById('zipCurrentFileName');
        const thumbStrip = document.getElementById('zipThumbStrip');

        if (typeof JSZip === 'undefined') {
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">ZIP library could not be loaded. Please download the file to view.</div>';
            return;
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function naturalSort(a, b) {
            const aNum = (a.match(/\d+/) || [])[0];
            const bNum = (b.match(/\d+/) || [])[0];
            if (aNum !== undefined && bNum !== undefined && aNum !== bNum) {
                return parseInt(aNum, 10) - parseInt(bNum, 10);
            }
            return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
        }

        fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status + ' loading ZIP');
                return res.arrayBuffer();
            })
            .then(buffer => JSZip.loadAsync(buffer))
            .then(async zip => {
                if (loading) loading.style.display = 'none';
                const fileNames = Object.keys(zip.files);
                if (!fileNames.length) {
                    if (listWrapper) {
                        listWrapper.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Archive is empty.</div>';
                        listWrapper.style.display = 'block';
                    }
                    return;
                }

                // Check for extracted slide / page images in archive
                const imgNames = fileNames.filter(n => !zip.files[n].dir && /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(n));
                imgNames.sort(naturalSort);

                // Populate File Listing
                let listHtml = '<div style="font-weight:600; font-size:12px; color:var(--text-secondary); margin-bottom:8px;">All Archive Files (' + fileNames.length + ')</div>';
                listHtml += '<div style="display:flex; flex-direction:column; gap:6px;">';
                fileNames.forEach(name => {
                    const entry = zip.files[name];
                    const isDir = Boolean(entry.dir || name.endsWith('/'));
                    const iconName = isDir ? 'folder' : 'file';
                    let sizeStr = isDir ? 'Folder' : 'File';
                    try {
                        const uSize = entry._data ? entry._data.uncompressedSize : (entry.uncompressedSize || null);
                        if (typeof uSize === 'number' && !isNaN(uSize) && uSize >= 0) {
                            sizeStr = uSize < 1024 ? uSize + ' B' : Math.round(uSize / 1024) + ' KB';
                        }
                    } catch (e) {}

                    listHtml += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--bg-card-subtle); border-radius:8px; font-size:12.5px;">
                            <div style="display:flex; align-items:center; gap:8px; word-break:break-all;">
                                <i data-lucide="${iconName}" style="width:16px; height:16px; color:${isDir ? 'var(--apple-blue)' : 'var(--text-secondary)'}; flex-shrink:0;"></i>
                                <span style="font-weight:${isDir ? '600' : '400'}; color:var(--text-primary);">${escHtml(name)}</span>
                            </div>
                            <span class="text-muted" style="font-size:11.5px; margin-left:12px; flex-shrink:0;">${sizeStr}</span>
                        </div>
                    `;
                });
                listHtml += '</div>';
                if (listWrapper) listWrapper.innerHTML = listHtml;

                // If images exist (e.g. extracted slide pages), build visual presentation viewer
                if (imgNames.length > 0) {
                    if (headerTitle) headerTitle.textContent = 'Extracted Slides & Pages';
                    if (badgeCount) {
                        badgeCount.textContent = `${imgNames.length} Extracted Pages`;
                        badgeCount.style.display = 'inline-block';
                    }
                    if (viewModes) viewModes.style.display = 'flex';

                    const extractedImages = [];
                    for (const n of imgNames) {
                        try {
                            const blob = await zip.files[n].async('blob');
                            const url = URL.createObjectURL(blob);
                            const cleanName = n.split('/').pop();
                            extractedImages.push({ name: cleanName, url: url, size: blob.size });
                        } catch (e) {}
                    }

                    // 1. Build Extracted Pages Stack (Page 1, Page 2... stacked vertically)
                    if (pagesWrapper) {
                        pagesWrapper.innerHTML = '';
                        extractedImages.forEach((img, idx) => {
                            const card = document.createElement('div');
                            card.className = 'extracted-page-card';
                            card.innerHTML = `
                                <div class="extracted-page-header">
                                    <span>Page ${idx + 1} of ${extractedImages.length}</span>
                                    <a href="${img.url}" target="_blank" style="color:var(--apple-blue); text-decoration:none; font-size:11px;">Open Full Page</a>
                                </div>
                                <div style="background:#1c1c1e; padding:12px; display:flex; justify-content:center; align-items:center;">
                                    <img src="${img.url}" alt="Page ${idx + 1}" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                                </div>
                            `;
                            pagesWrapper.appendChild(card);
                        });
                    }

                    // 2. Setup Slide Deck View
                    let currentDeckIdx = 0;
                    function showSlide(idx) {
                        if (idx < 0) idx = 0;
                        if (idx >= extractedImages.length) idx = extractedImages.length - 1;
                        currentDeckIdx = idx;
                        const item = extractedImages[idx];
                        if (activeImg) activeImg.src = item.url;
                        if (activeLink) activeLink.href = item.url;
                        if (indicator) indicator.textContent = `Page ${idx + 1} of ${extractedImages.length}`;
                        if (fileNameEl) {
                            const kb = Math.round(item.size / 1024);
                            fileNameEl.textContent = `${item.name} (${kb} KB)`;
                        }
                        if (thumbStrip) {
                            thumbStrip.querySelectorAll('.zip-thumb').forEach((th, i) => {
                                th.style.outline = (i === idx) ? '3px solid var(--apple-orange)' : 'none';
                                th.style.opacity = (i === idx) ? '1' : '0.65';
                            });
                            const activeThumb = thumbStrip.children[idx];
                            if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    }

                    if (thumbStrip) {
                        thumbStrip.innerHTML = '';
                        extractedImages.forEach((item, idx) => {
                            const th = document.createElement('img');
                            th.className = 'zip-thumb';
                            th.src = item.url;
                            th.alt = item.name;
                            th.style.cssText = 'height:54px; width:80px; object-fit:cover; border-radius:6px; cursor:pointer; flex-shrink:0; background:#000; transition:all 0.2s;';
                            th.onclick = () => showSlide(idx);
                            thumbStrip.appendChild(th);
                        });
                    }

                    if (prevBtn) prevBtn.onclick = () => showSlide(currentDeckIdx - 1);
                    if (nextBtn) nextBtn.onclick = () => showSlide(currentDeckIdx + 1);

                    function setZipMode(mode) {
                        [btnPages, btnDeck, btnFiles].forEach(b => {
                            if (b) b.className = 'btn btn-outline btn-sm';
                        });
                        if (pagesContainer) pagesContainer.style.display = 'none';
                        if (deckContainer) deckContainer.style.display = 'none';
                        if (listWrapper) listWrapper.style.display = 'none';

                        if (mode === 'pages' && pagesContainer) {
                            if (btnPages) btnPages.className = 'btn btn-primary btn-sm';
                            pagesContainer.style.display = 'block';
                        } else if (mode === 'deck' && deckContainer) {
                            if (btnDeck) btnDeck.className = 'btn btn-primary btn-sm';
                            deckContainer.style.display = 'block';
                            showSlide(currentDeckIdx);
                        } else if (mode === 'files' && listWrapper) {
                            if (btnFiles) btnFiles.className = 'btn btn-primary btn-sm';
                            listWrapper.style.display = 'block';
                        }
                    }

                    if (btnPages) btnPages.onclick = () => setZipMode('pages');
                    if (btnDeck) btnDeck.onclick = () => setZipMode('deck');
                    if (btnFiles) btnFiles.onclick = () => setZipMode('files');

                    // Default to Extracted Pages View
                    setZipMode('pages');
                } else {
                    if (listWrapper) listWrapper.style.display = 'block';
                }

                if (window.lucide) lucide.createIcons();
            })
            .catch(err => {
                console.error('ZIP reader error:', err);
                if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not inspect archive files. Please download to view.</div>';
            });
    })();
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
