<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth     = new Auth();
$auth->requireRole(['admin', 'department_head', 'staff']); // viewers cannot upload
$docClass = new Document();
$db       = Database::getInstance();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    // When post_max_size is exceeded PHP may discard the entire request body.
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMax = ini_get('post_max_size');
    $postMaxBytes = 0;
    if ($postMax) {
        $unit = strtolower(substr(trim($postMax), -1));
        $num = (float)$postMax;
        if ($unit === 'g') $num *= 1024 * 1024 * 1024;
        elseif ($unit === 'm') $num *= 1024 * 1024;
        elseif ($unit === 'k') $num *= 1024;
        $postMaxBytes = (int)$num;
    }
    if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes && empty($_POST) && empty($_FILES)) {
        $error = 'The upload is too large for the server. Your selected file/folder exceeds PHP post_max_size (' . htmlspecialchars($postMax) . '). Please choose fewer files or increase post_max_size in php.ini.';
    }
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId  = trim($_POST['category_id'] ?? '');
    $deptId      = trim($_POST['department_id'] ?? '');
    $priority    = trim($_POST['priority'] ?? '');
    $status      = trim($_POST['status'] ?? '');
    // Administrator uploads do not enter an approval queue. They are immediately
    // approved and become protected administrator-owned documents.
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        $_POST['status'] = 'approved';
        $status = 'approved';
    } else {
        $deptId = (string)($_SESSION['user_dept'] ?? '');
        $_POST['department_id'] = $deptId;
    }
    $tags        = trim($_POST['tags'] ?? '');
    $expiresAt   = trim($_POST['expires_at'] ?? '');
    $referenceNo = trim($_POST['reference_number'] ?? '');

    // Validation
    if ($error !== '') {
        // Keep the error and show it in the modal below.
    }
    $hasUpload = isset($_FILES['document']['name']) && (
        is_array($_FILES['document']['name'])
            ? count(array_filter($_FILES['document']['name'], static fn($name) => trim((string)$name) !== '')) > 0
            : $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE
    );
    if ($error !== '') {
        // Server-level upload error already identified.
    } elseif (!$hasUpload) {
        $error = 'Document file or folder is required. Please attach a file or select a folder.';
    } elseif ($title === '') {
        $error = 'Document Title is required.';
    } elseif (mb_strlen($title) < 3 || title_looks_like_hash($title)) {
        $error = 'Please enter a descriptive title (at least 3 characters, not just a code like "9f4cf0f2…").';
    } elseif ($categoryId === '') {
        $error = 'Category is required. Please choose a category.';
    } elseif ($deptId === '') {
        $error = 'Department is required. Please select a department.';
    } elseif ($priority === '') {
        $error = 'Priority is required. Please select a priority level.';
    } elseif ($status === '') {
        $error = 'Initial Status is required. Please select a status.';
    } elseif ($tags === '') {
        $error = 'Tags are required. Please enter at least one tag (comma-separated).';
    } elseif ($expiresAt === '') {
        $error = 'Expiry Date is required. Please choose an expiration date.';
    } elseif ($referenceNo !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{2,49}$/', $referenceNo)) {
        $error = 'Reference Number may contain letters, numbers, dots, hyphens, and slashes only.';
    } else {
        $isBatch = is_array($_FILES['document']['name'] ?? null);
        if ($isBatch) {
            $fileCount = count($_FILES['document']['name'] ?? []);
            // Preserve the normal single-document workflow when only one file was chosen.
            if ($fileCount === 1) {
                $singleFile = [
                    'name' => $_FILES['document']['name'][0],
                    'type' => $_FILES['document']['type'][0],
                    'tmp_name' => $_FILES['document']['tmp_name'][0],
                    'error' => $_FILES['document']['error'][0],
                    'size' => $_FILES['document']['size'][0],
                ];
                $result = $docClass->upload($_POST, $singleFile);
                if ($result['success']) {
                    header('Location: ' . APP_URL . '/document_view.php?id=' . $result['document_id'] . '&uploaded=1');
                    exit;
                }
                $error = $result['message'];
            } else {
                $result = $docClass->uploadBatch($_POST, $_FILES['document']);
                if ($result['success']) {
                    $_SESSION['upload_batch_result'] = [
                        'uploaded' => (int)($result['uploaded'] ?? 0),
                        'failed' => array_values($result['failed'] ?? [])
                    ];
                    header('Location: ' . APP_URL . '/documents.php?uploaded=batch');
                    exit;
                }
                $error = $result['message'];
                if (!empty($result['failed'])) {
                    $error .= "
Details:
- " . implode("
- ", $result['failed']);
                }
            }
        } else {
            $result = $docClass->upload($_POST, $_FILES['document']);
            if ($result['success']) {
                header('Location: ' . APP_URL . '/document_view.php?id=' . $result['document_id'] . '&uploaded=1');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$categories  = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 700px; margin: 0 auto;">
    <div class="modal-overlay <?= $error ? 'open' : '' ?>" id="uploadErrorModal" role="dialog" aria-modal="true" aria-labelledby="uploadErrorTitle">
        <div class="modal" style="max-width:560px;">
            <div class="modal-header">
                <div class="modal-title" id="uploadErrorTitle" style="display:flex; align-items:center; gap:10px; color:var(--text-primary);">
                    <span style="width:34px; height:34px; border-radius:50%; background:rgba(255, 149, 0, 0.14); color:var(--apple-orange); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i data-lucide="triangle-alert" style="width:19px; height:19px;"></i>
                    </span>
                    Warning!
                </div>
                <button type="button" class="modal-close" onclick="closeUploadErrorModal()">×</button>
            </div>
            <div data-upload-error-message style="white-space:pre-line; max-height:360px; overflow:auto; font-size:13px; line-height:1.6; color:var(--text-secondary);"><?= htmlspecialchars($error ?: 'Please review the highlighted information and try again.') ?></div>
            <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-primary" onclick="closeUploadErrorModal()">Understood</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">
                    <i data-lucide="upload" style="color:var(--apple-blue); margin-right: 8px;"></i>
                    Upload New Document
                </span>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                    Fields marked with <span style="color:var(--apple-red); font-weight:700;">*</span> are mandatory.
                </div>
            </div>
            <span class="badge badge-draft">Required Fields</span>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <?= csrf_field() ?>
                <input type="hidden" name="expected_file_count" id="expectedFileCount" value="0">

                <!-- Apple Style Drop Zone -->
                <div class="form-group">
                    <label>Document File Attachment <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                    <div class="apple-dropzone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                        <div class="apple-dropzone-icon">
                            <i data-lucide="cloud-upload"></i>
                        </div>
                        <div style="font-weight: 600; font-size: 14.5px; color: var(--text-primary); margin-bottom: 4px;">
                            Choose a file or select a folder <span style="color:var(--apple-red);">*</span>
                        </div>
                        <div class="text-muted text-sm" style="margin-bottom:10px;">
                            Supported formats: PDF, Word, Excel, PowerPoint, Text, Images, ZIP. Folders are compressed into one ZIP. Unsupported files are skipped automatically. Max 20MB.
                        </div>
                        <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-outline" id="chooseFileBtn"><i data-lucide="file-up"></i> Choose File</button>
                            <button type="button" class="btn btn-outline" id="chooseFolderBtn"><i data-lucide="folder-up"></i> Choose Folder</button>
                        </div>
                        <div id="folderHint" class="text-muted text-sm" style="display:none; margin-top:9px;"></div>

                        <!-- Progress indicator while zipping folder -->
                        <div id="zipProgress" style="display:none; margin-top:14px; font-size:12.5px; color:var(--apple-blue); font-weight:500;">
                            <i data-lucide="loader-circle" class="icon-spin"></i> Compressing folder into ZIP archive…
                        </div>

                        <!-- File Selected Preview Capsule -->
                        <div id="filePreview" style="display: none; margin-top: 14px; align-items: center; justify-content: center; gap: 8px;">
                            <span id="fileBadge" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(0, 113, 227, 0.12); color: var(--apple-blue); font-weight: 600; font-size: 12.5px; padding: 6px 14px; border-radius: var(--radius-pill);">
                                <i data-lucide="file-check"></i> <span id="fileName">File</span> (<span id="fileSize">0 KB</span>)
                            </span>
                            <button type="button" id="removeFileBtn" onclick="event.stopPropagation(); resetFile();" style="border: none; background: rgba(0,0,0,0.06); width: 26px; height: 26px; border-radius: 50%; cursor: pointer; color: var(--text-secondary); display: inline-flex; align-items: center; justify-content: center;">
                                <i data-lucide="x" style="font-size: 11px;"></i>
                            </button>
                        </div>
                    </div>
                    <input type="file" id="fileInput" name="document[]" style="display:none;"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.zip">
                    <input type="file" id="folderInput" style="display:none;" webkitdirectory directory multiple>
                </div>

                <!-- Reference Number -->
                <div class="form-group">
                    <label>Reference Number <span class="text-muted text-sm">(Optional — auto-generated if blank)</span></label>
                    <input type="text" name="reference_number" maxlength="50" placeholder="e.g. FIN-2026-001" value="<?= htmlspecialchars($_POST['reference_number'] ?? '') ?>">
                    <div class="text-muted text-sm" style="margin-top:4px;">The system automatically creates a unique reference such as DOC-2026-000123.</div>
                </div>

                <!-- Document Title (Required) -->
                <div class="form-group">
                    <label>Document Title <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                    <input type="text" name="title" id="docTitle" required placeholder="e.g. Office Financial Review Q3" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>

                <!-- Description (Optional) -->
                <div class="form-group">
                    <label>Description <span class="text-muted text-sm">(Optional)</span></label>
                    <textarea name="description" placeholder="Provide a summary of the document, purpose, and relevant details…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <!-- Category & Department (Both Required) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                        <select name="category_id" required>
                            <option value="" disabled <?= empty($_POST['category_id']) ? 'selected' : '' ?>>Select category…</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id']??'')==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                        <select name="department_id" required>
                            <option value="" disabled <?= empty($_POST['department_id']) ? 'selected' : '' ?>>Select department…</option>
                            <?php foreach ($departments as $dep): ?>
                            <option value="<?= $dep['id'] ?>" <?= ($_POST['department_id']??'')==$dep['id']?'selected':'' ?>><?= htmlspecialchars($dep['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <select name="department_id" required disabled>
                            <?php foreach ($departments as $dep): if ((int)$dep['id'] === (int)$_SESSION['user_dept']): ?>
                            <option value="<?= $dep['id'] ?>" selected><?= htmlspecialchars($dep['name']) ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <input type="hidden" name="department_id" value="<?= (int)$_SESSION['user_dept'] ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Priority & Initial Status (Both Required) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Priority <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                        <select name="priority" required>
                            <option value="" disabled <?= empty($_POST['priority']) ? 'selected' : '' ?>>Select priority…</option>
                            <option value="low" <?= ($_POST['priority']??'')==='low'?'selected':'' ?>>Low (Standard Routine)</option>
                            <option value="normal" <?= ($_POST['priority']??'normal')==='normal'?'selected':'' ?>>Normal (Regular Priority)</option>
                            <option value="high" <?= ($_POST['priority']??'')==='high'?'selected':'' ?>>High (Action Needed)</option>
                            <option value="urgent" <?= ($_POST['priority']??'')==='urgent'?'selected':'' ?>>Urgent (Immediate Review)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Initial Workflow Status <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                        <select name="status" required disabled>
                            <option value="approved" selected>Approved (Administrator Upload)</option>
                        </select>
                        <input type="hidden" name="status" value="approved">
                        <?php else: ?>
                        <select name="status" required>
                            <option value="draft" <?= ($_POST['status']??'')==='draft'?'selected':'' ?>>Draft (Save as draft without sending for approval)</option>
                            <option value="pending_review" <?= ($_POST['status']??'pending_review')==='pending_review'?'selected':'' ?>>Submit for Review (Send directly to Approvers)</option>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tags & Expiry Date (Both Required) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Tags <span style="color:var(--apple-red); font-weight:700;">*</span> <span class="text-muted text-sm">(Comma-separated)</span></label>
                        <input type="text" name="tags" required placeholder="e.g. memo, legal, hr, 2026" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                        <input type="date" name="expires_at" required value="<?= htmlspecialchars($_POST['expires_at'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-hairline);">
                    <button type="button" class="btn btn-outline" id="cancelBtn" onclick="openCancelModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i data-lucide="arrow-up"></i> Upload & Submit Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal-overlay" id="discardModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header" style="margin-bottom: 14px;">
            <div class="modal-title" style="display:flex; align-items:center; gap:10px; color:var(--text-primary);">
                <span style="width:34px; height:34px; border-radius:50%; background:rgba(255, 149, 0, 0.14); color:var(--apple-orange); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="triangle-alert" style="width:19px; height:19px;"></i>
                </span>
                Cancel Upload
            </div>
            <button class="modal-close" type="button" onclick="closeDiscardModal()">×</button>
        </div>
        <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.55; margin-bottom: 22px;">
            Are you sure you want to cancel? All attached files and entered details will be cleared. You will stay on this page.
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeDiscardModal()">
                Keep Editing
            </button>
            <button type="button" class="btn btn-danger" id="confirmDiscardBtn" onclick="proceedClearForm()">
                <i data-lucide="trash-2"></i> Clear &amp; Cancel
            </button>
        </div>
    </div>
</div>

<!-- Nav Leave Modal -->
<div class="modal-overlay" id="navLeaveModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header" style="margin-bottom: 14px;">
            <div class="modal-title" style="display:flex; align-items:center; gap:10px; color:var(--text-primary);">
                <span style="width:34px; height:34px; border-radius:50%; background:rgba(255, 149, 0, 0.14); color:var(--apple-orange); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="triangle-alert" style="width:19px; height:19px;"></i>
                </span>
                Cancel Filing Confirmation
            </div>
            <button class="modal-close" type="button" onclick="closeNavModal()">×</button>
        </div>
        <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.55; margin-bottom: 22px;">
            You have unsaved changes in this document filing. If you switch tabs or leave this page now, your attached file and entered details will be discarded.
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeNavModal()">
                Keep Editing
            </button>
            <button type="button" class="btn btn-danger" onclick="proceedLeave()">
                <i data-lucide="trash-2"></i> Discard &amp; Leave
            </button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/jszip.min.js"></script>
<script>
const uploadForm      = document.getElementById('uploadForm');
const fileInput       = document.getElementById('fileInput');
const folderInput     = document.getElementById('folderInput');
const dropZone        = document.getElementById('dropZone');
const filePreview     = document.getElementById('filePreview');
const fileName        = document.getElementById('fileName');
const fileSize        = document.getElementById('fileSize');
const docTitle        = document.getElementById('docTitle');
const submitBtn       = document.getElementById('submitBtn');
const cancelBtn       = document.getElementById('cancelBtn');
const chooseFileBtn   = document.getElementById('chooseFileBtn');
const chooseFolderBtn = document.getElementById('chooseFolderBtn');
const folderHint      = document.getElementById('folderHint');
const zipProgress     = document.getElementById('zipProgress');

let fileIsAttached   = false;
let isFormSubmitting = false;
let isZippingFolder  = false;
let pendingNavUrl    = null;
let allowHistoryLeave = false;

// ── Browser Back button guard ──
// Chrome ignores history entries pushed before the user interacts with the page
// ("history manipulation intervention"), so the Back button would skip our guard and
// trigger the native "Leave site?" dialog. We therefore push the guard entry on the
// first user interaction instead of at load.
let guardPushed = false;
function ensureHistoryGuard() {
    if (guardPushed) return;
    try {
        history.pushState({ uploadGuard: true }, '', window.location.href);
        guardPushed = true;
        window.__historyGuardActive = true;
    } catch (e) {}
}
['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
    document.addEventListener(evt, ensureHistoryGuard, { capture: true, once: true, passive: true });
});

window.addEventListener('popstate', function () {
    if (allowHistoryLeave || isFormSubmitting) return;
    if (!guardPushed) return;
    if (!isFormDirty()) {
        // Nothing to lose: step past the guard entry so Back keeps working normally.
        guardPushed = false;
        window.__historyGuardActive = false;
        history.back();
        return;
    }
    try { history.pushState({ uploadGuard: true }, '', window.location.href); } catch (e) {}
    openNavModal(document.referrer || 'documents.php');
});

// Dirty check: true only when files are attached or details are typed
function isFormDirty() {
    if (fileIsAttached) return true;
    if (fileInput && fileInput.files && fileInput.files.length > 0) return true;
    if (docTitle && docTitle.value.trim() !== '') return true;
    const desc = document.querySelector('textarea[name="description"]');
    if (desc && desc.value.trim() !== '') return true;
    const tags = document.querySelector('input[name="tags"]');
    if (tags && tags.value.trim() !== '') return true;
    const ref = document.querySelector('input[name="reference_number"]');
    if (ref && ref.value.trim() !== '') return true;
    return false;
}

// ── Modals ──
function openCancelModal() {
    const modal = document.getElementById('discardModal');
    if (modal) { modal.classList.add('open'); if (window.lucide) lucide.createIcons(); }
}
function closeDiscardModal() {
    const modal = document.getElementById('discardModal');
    if (modal) modal.classList.remove('open');
}
function proceedClearForm() {
    closeDiscardModal();
    uploadForm.reset();
    resetFile();
    fileIsAttached = false;
    uploadForm.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    uploadForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openNavModal(url) {
    pendingNavUrl = url;
    const modal = document.getElementById('navLeaveModal');
    if (modal) { modal.classList.add('open'); if (window.lucide) lucide.createIcons(); }
}
function closeNavModal() {
    pendingNavUrl = null;
    const modal = document.getElementById('navLeaveModal');
    if (modal) modal.classList.remove('open');
}
function proceedLeave() {
    isFormSubmitting = true;
    if (pendingNavUrl && pendingNavUrl !== document.referrer) { window.location.href = pendingNavUrl; return; }
    allowHistoryLeave = true;
    // Skip over both the guard entry and the original upload page entry.
    history.go(guardPushed ? -2 : -1);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeDiscardModal(); closeNavModal(); }
});
document.getElementById('discardModal').addEventListener('click', function(e) {
    if (e.target === this) closeDiscardModal();
});
document.getElementById('navLeaveModal').addEventListener('click', function(e) {
    if (e.target === this) closeNavModal();
});

document.addEventListener('click', function(e) {
    let el = e.target;
    let link = null;
    while (el && el !== document.body) {
        if (el.tagName && el.tagName.toLowerCase() === 'a') { link = el; break; }
        el = el.parentElement;
    }
    if (!link) return;
    if (link.closest('#discardModal') || link.closest('#navLeaveModal')) return;
    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript:')) return;
    if (link.target === '_blank') return;
    if (link.pathname === window.location.pathname && link.search === window.location.search) return;
    if (isFormDirty() && !isFormSubmitting) {
        e.preventDefault();
        e.stopPropagation();
        openNavModal(link.href);
    }
}, true);

window.addEventListener('beforeunload', function(e) {
    if (isFormDirty() && !isFormSubmitting) { e.preventDefault(); e.returnValue = ''; }
});

function setInputFiles(input, files) {
    try {
        const dt = new DataTransfer();
        files.forEach(file => dt.items.add(file));
        input.files = dt.files;
        return true;
    } catch (err) {
        return false;
    }
}

function showSelectedFiles(fileList, note = '') {
    if (!fileList || !fileList.length) {
        fileIsAttached = false;
        if (folderHint) folderHint.style.display = 'none';
        return;
    }

    fileIsAttached = true;
    const files = Array.from(fileList);
    const first = files[0];
    const count = files.length;
    const totalBytes = files.reduce((sum, f) => sum + f.size, 0);
    const expectedCount = document.getElementById('expectedFileCount');
    if (expectedCount) expectedCount.value = String(count);

    fileName.textContent = count === 1 ? first.name : `${count} files attached`;
    fileSize.textContent = `${(totalBytes / (1024 * 1024)).toFixed(2)} MB total`;
    filePreview.style.display = 'inline-flex';
    dropZone.classList.add('dragover');

    if (folderHint) {
        if (note) {
            folderHint.style.display = 'block';
            folderHint.innerHTML = note;
        } else {
            folderHint.style.display = 'none';
        }
    }

    if (count === 1 && !docTitle.value.trim()) {
        const rawName = first.name.replace(/\.[^/.]+$/, '').replace(/[-_]+/g, ' ');
        const hashLike = /^[0-9a-f]{16,}$/i.test(rawName.replace(/[\s-]+/g, ''));
        if (hashLike) {
            // A file named like a hash makes a useless title - ask for a real one instead.
            docTitle.placeholder = 'Give this document a descriptive title';
            docTitle.focus();
        } else {
            docTitle.value = rawName.charAt(0).toUpperCase() + rawName.slice(1);
        }
    }
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B','KB','MB','GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + units[i];
}

function showUploadValidationModal(message) {
    const m = document.getElementById('uploadErrorModal');
    const body = m?.querySelector('[data-upload-error-message]');
    if (body) body.textContent = message;
    if (m) { m.classList.add('open'); if (window.lucide) lucide.createIcons(); }
}

function closeUploadErrorModal() {
    const m = document.getElementById('uploadErrorModal');
    if (m) m.classList.remove('open');
}

function resetFile() {
    fileIsAttached = false;
    isZippingFolder = false;
    fileInput.value = '';
    folderInput.value = '';
    const expectedCount = document.getElementById('expectedFileCount');
    if (expectedCount) expectedCount.value = '0';
    filePreview.style.display = 'none';
    if (folderHint) folderHint.style.display = 'none';
    if (zipProgress) zipProgress.style.display = 'none';
    dropZone.classList.remove('dragover');
}

chooseFileBtn?.addEventListener('click', () => fileInput.click());
chooseFolderBtn?.addEventListener('click', () => folderInput.click());

fileInput.addEventListener('change', () => {
    folderInput.value = '';
    showSelectedFiles(fileInput.files);
});

// ── Folder Selection: Compress into ZIP automatically ──
folderInput.addEventListener('change', async () => {
    const rawFiles = Array.from(folderInput.files || []);
    if (!rawFiles.length) {
        resetFile();
        return;
    }

    // Determine folder root name
    let folderName = 'archive';
    const firstPath = rawFiles[0].webkitRelativePath || rawFiles[0].name;
    const parts = firstPath.split(/[\\/]/);
    if (parts.length > 1 && parts[0].trim() !== '') {
        folderName = parts[0].trim();
    }

    if (typeof JSZip === 'undefined') {
        showUploadValidationModal('Folder upload is unavailable because the ZIP component did not load. Please refresh the page and try again.');
        return;
    }

    const allowedExts = new Set(['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','png','jpg','jpeg','zip']);
    const supportedFiles = rawFiles.filter(file => {
        const ext = file.name.toLowerCase().split('.').pop();
        return allowedExts.has(ext);
    });
    const skippedFiles = rawFiles.filter(file => !supportedFiles.includes(file));
    if (!supportedFiles.length) {
        showUploadValidationModal('The selected folder contains no supported document files. Supported formats are PDF, Word, Excel, PowerPoint, Text, Images, and ZIP.');
        resetFile();
        return;
    }

    try {
        isZippingFolder = true;
        if (zipProgress) {
            zipProgress.style.display = 'block';
            if (window.lucide) lucide.createIcons();
        }
        if (folderHint) {
            folderHint.style.display = 'block';
            folderHint.textContent = `Compressing ${supportedFiles.length} supported files from folder "${folderName}" into a ZIP archive…`;
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
        setInputFiles(fileInput, [zipFile]);
        folderInput.value = '';

        if (zipProgress) zipProgress.style.display = 'none';
        isZippingFolder = false;

        let folderNote = `<i data-lucide="archive" style="display:inline; width:14px; height:14px;"></i> Folder "<strong>${folderName}</strong>" compressed into <code>${folderName}.zip</code> (${supportedFiles.length} supported files included).`;
        if (skippedFiles.length) folderNote += `<br><span style="color:var(--apple-orange);">${skippedFiles.length} unsupported file(s) skipped because they cannot be uploaded as documents.</span>`;
        showSelectedFiles(fileInput.files, folderNote);
        if (window.lucide) lucide.createIcons();
    } catch (err) {
        console.error('Error zipping folder:', err);
        if (zipProgress) zipProgress.style.display = 'none';
        isZippingFolder = false;
        showUploadValidationModal('The folder could not be compressed. Please try a smaller folder or upload the documents individually.');
        resetFile();
    }
});

// Drag and drop
['dragenter', 'dragover'].forEach(name => {
    dropZone.addEventListener(name, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('dragover');
    });
});

['dragleave', 'dragend', 'drop'].forEach(name => {
    dropZone.addEventListener(name, (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (name !== 'drop' && !fileInput.files.length) {
            dropZone.classList.remove('dragover');
        }
    });
});

dropZone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files.length) {
        try {
            fileInput.files = e.dataTransfer.files;
        } catch (err) {}
        showSelectedFiles(e.dataTransfer.files);
    }
});

// ── Validation and Confirmation when Uploading ──
uploadForm.addEventListener('submit', function(e) {
    if (isZippingFolder) {
        e.preventDefault();
        showUploadValidationModal('Please wait while the folder is being compressed into a ZIP file.');
        return false;
    }

    // 1. Validate File
    const selectedFiles = fileInput.files;
    if (!selectedFiles || !selectedFiles.length) {
        e.preventDefault();
        showUploadValidationModal('Please attach a document file or select a folder before submitting.');
        dropZone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        dropZone.style.borderColor = 'var(--apple-red)';
        setTimeout(() => dropZone.style.borderColor = '', 3000);
        return false;
    }

    const maxBytes = 20 * 1024 * 1024;
    if (selectedFiles[0].size > maxBytes) {
        e.preventDefault();
        showUploadValidationModal(`The selected file is ${formatBytes(selectedFiles[0].size)}, which exceeds the 20 MB maximum. Please choose a smaller file.`);
        return false;
    }

    // 2. Validate Title
    if (!docTitle.value.trim()) {
        e.preventDefault();
        showUploadValidationModal('Please provide a Document Title.');
        docTitle.focus();
        return false;
    }

    // 3. Validate Category
    const catField = document.querySelector('select[name="category_id"]');
    if (!catField.value) {
        e.preventDefault();
        showUploadValidationModal('Please select a Category.');
        catField.focus();
        return false;
    }

    // 4. Validate Department
    const deptField = document.querySelector('select[name="department_id"]');
    if (!deptField.value) {
        e.preventDefault();
        showUploadValidationModal('Please select a Department.');
        deptField.focus();
        return false;
    }

    // 5. Validate Priority
    const priorityField = document.querySelector('select[name="priority"]');
    if (!priorityField.value) {
        e.preventDefault();
        showUploadValidationModal('Please select a Priority level.');
        priorityField.focus();
        return false;
    }

    // 6. Validate Status
    const statusField = document.querySelector('select[name="status"]');
    if (!statusField.value) {
        e.preventDefault();
        showUploadValidationModal('Please select an Initial Status.');
        statusField.focus();
        return false;
    }

    // 7. Validate Tags
    const tagsField = document.querySelector('input[name="tags"]');
    if (!tagsField.value.trim()) {
        e.preventDefault();
        showUploadValidationModal('Please enter at least one tag (comma-separated).');
        tagsField.focus();
        return false;
    }

    // 8. Validate Expiry Date
    const expiryField = document.querySelector('input[name="expires_at"]');
    if (!expiryField.value) {
        e.preventDefault();
        showUploadValidationModal('Please select an Expiration Date.');
        expiryField.focus();
        return false;
    }

    // ── All Validations Passed: Prompt Confirmation ──
    const title = docTitle.value.trim();
    const statusLabel = statusField.options[statusField.selectedIndex].text.split('(')[0].trim();
    const fileNameText = selectedFiles.length === 1 ? selectedFiles[0].name : `${selectedFiles.length} files`;

    if (uploadForm.dataset.confirmed !== '1') {
        e.preventDefault();
        appConfirm({
            title: 'Submit Document',
            message: 'Are you sure you want to upload and submit this document?',
            details: [['Title', title], ['File', fileNameText], ['Status', statusLabel]],
            confirmText: 'Upload & Submit',
            cancelText: 'Review Details',
            confirmClass: 'btn-primary',
            confirmIcon: 'arrow-up'
        }).then(function (yes) {
            if (!yes) return;
            uploadForm.dataset.confirmed = '1';
            uploadForm.requestSubmit();
        });
        return false;
    }

    // Confirmed: proceeding with upload
    isFormSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-lucide="loader-circle" class="icon-spin"></i> Uploading & Submitting…';
    lucide.createIcons();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
