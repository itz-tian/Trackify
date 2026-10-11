<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth     = new Auth();
$auth->requireLogin();
$docClass = new Document();
$db       = Database::getInstance();

// Filters
$filters = [
    'status'        => $_GET['status'] ?? '',
    'category_id'   => $_GET['category_id'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
    'priority'      => $_GET['priority'] ?? '',
    'search'        => $_GET['search'] ?? '',
    'date'          => $_GET['date'] ?? '',
    'uploader_id'   => $_GET['uploader_id'] ?? '',
];
$page   = max(1, (int)($_GET['page'] ?? 1));
$requestedPerPage = (int)($_GET['per_page'] ?? 15);
$perPage = in_array($requestedPerPage, [15, 25, 50], true) ? $requestedPerPage : 15;
$result = $docClass->getAll($filters, $page, $perPage);

// Dropdowns
$categories  = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$uploaders = $db->query("SELECT id, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name, last_name")->fetchAll();

$statusColors = [
    'draft'=>'badge-draft','pending_review'=>'badge-pending','under_review'=>'badge-review',
    'approved'=>'badge-approved','rejected'=>'badge-rejected','archived'=>'badge-archived'
];

$batchUploadResult = $_SESSION['upload_batch_result'] ?? null;
unset($_SESSION['upload_batch_result']);

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $res = $docClass->delete((int)$_POST['delete_id']);
        flash_result($res, 'Document deleted.');
    }
    redirect_self(); // Post/Redirect/Get
}
$flash = flash_pull();
$deleteMsg = $flash['success'] ?: $flash['error'];
if ($deleteMsg === '') unset($deleteMsg);
$deleteMsgIsError = ($flash['error'] !== '');

include __DIR__ . '/includes/header.php';
?>

<?php if (($_GET['uploaded'] ?? '') === 'batch' && is_array($batchUploadResult)): ?>
<div class="alert alert-success">
    <i data-lucide="circle-check"></i>
    <strong><?= (int)$batchUploadResult['uploaded'] ?> document(s) uploaded successfully.</strong>
    <?php if (!empty($batchUploadResult['failed'])): ?>
        <div style="margin-top:8px;">
            <strong><?= count($batchUploadResult['failed']) ?> file(s) were not uploaded:</strong>
            <ul style="margin:6px 0 0 20px;">
                <?php foreach ($batchUploadResult['failed'] as $failure): ?>
                    <li><?= htmlspecialchars($failure) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($deleteMsg)): ?>
<div class="alert <?= $deleteMsgIsError ? 'alert-danger' : 'alert-success' ?>">
    <i data-lucide="info"></i> <?= htmlspecialchars($deleteMsg) ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="card mb-16">
    <div class="card-body" style="padding:16px;">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search documents…" value="<?= htmlspecialchars($filters['search']) ?>" style="flex:1;min-width:180px;">
            <select name="status" style="min-width:140px;">
                <option value="">All Statuses</option>
                <?php foreach (['draft','pending_review','under_review','approved','rejected','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category_id" style="min-width:130px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filters['category_id']==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority" style="min-width:110px;">
                <option value="">All Priorities</option>
                <?php foreach (['low','normal','high','urgent'] as $p): ?>
                <option value="<?= $p ?>" <?= $filters['priority']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="uploader_id" style="min-width:150px;">
                <option value="">All Uploaders</option>
                <?php foreach ($uploaders as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filters['uploader_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="filter-date-wrap">
                <label for="filterDate" class="filter-date-label" title="Date Created">
                    <i data-lucide="calendar"></i>
                    <span>Date:</span>
                </label>
                <input type="date" id="filterDate" name="date" value="<?= htmlspecialchars($filters['date']) ?>" title="Filter by date created" aria-label="Date created">
            </div>
            <div class="filter-actions" style="display:flex;gap:8px;align-items:center;">
                <button type="submit" class="btn btn-primary"><i data-lucide="filter"></i> Filter</button>
                <a href="documents.php" class="btn btn-outline"><i data-lucide="rotate-ccw"></i> Reset</a>
                <a href="<?= APP_URL ?>/export.php?<?= http_build_query($filters) ?>" class="btn btn-outline"><i data-lucide="download"></i> Export CSV</a>
                <a href="<?= APP_URL ?>/upload.php" class="btn btn-primary"><i data-lucide="plus"></i> Upload</a>
            </div>
        </form>
    </div>
</div>

<!-- Documents Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i data-lucide="folder" style="color:var(--apple-blue); margin-right:8px;"></i>All Documents
            <span class="text-muted text-sm" style="font-weight:400;margin-left:8px;">(<?= $result['total'] ?> items)</span>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Reference</th>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Version</th>
                    <th>Uploaded By</th>
                    <th style="white-space:nowrap;">Date</th>
                    <th style="width:90px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($result['documents'])): ?>
            <tr><td colspan="11" class="text-center text-muted" style="padding:48px 20px;">No documents found matching your filter criteria.</td></tr>
            <?php else: ?>
            <?php foreach ($result['documents'] as $i => $doc): ?>
            <tr>
                <td class="text-muted text-sm"><?= (($page-1)*$perPage)+$i+1 ?></td>
                <td><span class="text-sm fw-600" style="font-family:monospace;"><?= htmlspecialchars($doc['reference_number'] ?? '—') ?></span></td>
                <td style="max-width:240px;">
                    <a href="document_view.php?id=<?= $doc['id'] ?>" class="fw-600" style="text-decoration:none;color:var(--text-primary);letter-spacing:-0.01em;">
                        <?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>
                    </a>
                    <?php if ($doc['expires_at'] && strtotime($doc['expires_at']) <= strtotime('+30 days')): ?>
                    <span class="badge badge-pending" style="margin-left:6px;font-size:10px;">Expiring</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($doc['category_name']): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:500;">
                        <span style="width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($doc['category_color']) ?>;display:inline-block;"></span>
                        <?= htmlspecialchars($doc['category_name']) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-sm"><?= htmlspecialchars($doc['department_name'] ?? '—') ?></td>
                <td><span class="badge <?= $statusColors[$doc['status']] ?? 'badge-draft' ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span></td>
                <td><span class="priority-<?= $doc['priority'] ?>"><?= ucfirst($doc['priority']) ?></span></td>
                <td class="text-center fw-600" style="font-size:12px;">v<?= $doc['version'] ?></td>
                <td class="text-sm"><?= htmlspecialchars($doc['uploaded_by_name'] ?? '—') ?></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="document_view.php?id=<?= $doc['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View details"><i data-lucide="eye" style="font-size:11px;"></i></a>
                        <?php if (($_SESSION['user_role'] ?? '') === 'admin' || (($doc['uploader_role'] ?? '') !== 'admin' && ($doc['uploaded_by'] == $_SESSION['user_id'] || (($_SESSION['user_role'] ?? '') === 'department_head' && (int)$doc['department_id'] === (int)($_SESSION['user_dept'] ?? 0) && (int)$doc['uploaded_by'] !== (int)$_SESSION['user_id'])))): ?>
                        <button class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="confirmDelete(<?= $doc['id'] ?>, '<?= addslashes($doc['title']) ?>')"><i data-lucide="trash-2" style="font-size:11px;"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($result['pages'] > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
        <?php for ($p=1; $p<=$result['pages']; $p++): ?>
        <a href="?<?= http_build_query(array_merge($filters,['page'=>$p,'per_page'=>$perPage])) ?>"
           class="btn btn-sm <?= $p===$page?'btn-primary':'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--danger);"><i data-lucide="trash-2"></i> Delete Document</div>
            <button class="modal-close" onclick="closeDeleteModal()">×</button>
        </div>
        <p style="margin-bottom:20px;">Are you sure you want to delete <strong id="deleteDocTitle"></strong>? The document will be moved to the Recycle Bin and can be restored.</p>
        <form method="POST" id="deleteForm">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_id" id="deleteDocId">
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger"><i data-lucide="trash-2"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    document.getElementById('deleteDocId').value = id;
    document.getElementById('deleteDocTitle').textContent = '"' + title + '"';
    var confirmArchive = true;
    try {
        var cfg = JSON.parse(localStorage.getItem('trackify_data_settings') || '{}');
        if (cfg.confirmArchive === false) confirmArchive = false;
    } catch (e) {}
    if (!confirmArchive) {
        document.getElementById('deleteForm').submit();
        return;
    }
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
