<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth = new Auth();
$auth->requireLogin();
$docClass = new Document();

if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore_id'])) {
        $result = $docClass->restore((int)$_POST['restore_id']);
        flash_result($result, 'Document restored successfully.');
    }
    if (isset($_POST['permanent_delete_id'])) {
        $result = $docClass->permanentDelete((int)$_POST['permanent_delete_id']);
        flash_result($result, 'Document permanently deleted.');
    }
    redirect_self(); // Post/Redirect/Get
}

['success' => $message, 'error' => $error] = flash_pull();

$page = max(1, (int)($_GET['page'] ?? 1));
$result = $docClass->getDeleted($page);

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card mb-16" style="border-left:4px solid var(--apple-orange);">
    <div class="card-body" style="display:flex; gap:14px; align-items:center; padding:16px 20px;">
        <div style="width:36px; height:36px; border-radius:10px; background:rgba(255,149,0,0.12); color:var(--apple-orange); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i data-lucide="archive" style="width:18px; height:18px;"></i>
        </div>
        <div>
            <div style="font-weight:700; font-size:14.5px; color:var(--text-primary);">Archive</div>
            <div class="text-muted text-sm" style="margin-top:2px;">
                Archived documents are kept here instead of being immediately destroyed. <?= ($_SESSION['user_role'] ?? '') === 'admin' ? 'Administrators can permanently delete records.' : 'You can restore documents that you deleted.' ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i data-lucide="archive-restore" style="color:var(--apple-orange); margin-right:8px;"></i>
            Archived Documents
            <span class="text-muted text-sm" style="font-weight:400; margin-left:8px;">(<?= $result['total'] ?> items)</span>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="white-space:nowrap; width:150px;">Reference</th>
                    <th>Document</th>
                    <th style="white-space:nowrap; width:140px;">Department</th>
                    <th style="white-space:nowrap; width:130px;">Uploaded By</th>
                    <th style="white-space:nowrap; width:130px;">Deleted By</th>
                    <th style="white-space:nowrap; width:160px;">Archived Date</th>
                    <th style="white-space:nowrap; text-align:right; width:210px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$result['documents']): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:48px 20px;">Archive is empty.</td></tr>
            <?php else: foreach ($result['documents'] as $doc): ?>
                <tr>
                    <td style="white-space:nowrap;">
                        <span class="text-sm fw-600" style="font-family:monospace; font-size:12.5px; letter-spacing:0.02em;">
                            <?= htmlspecialchars($doc['reference_number'] ?? '—') ?>
                        </span>
                    </td>
                    <td style="min-width:180px;">
                        <div class="fw-600" style="letter-spacing:-0.01em;"><?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?></div>
                        <div class="text-sm text-muted" style="font-size:11.5px;"><?= htmlspecialchars($doc['file_name']) ?></div>
                    </td>
                    <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($doc['department_name'] ?? '—') ?></td>
                    <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($doc['uploaded_by_name'] ?? '—') ?></td>
                    <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($doc['deleted_by_name'] ?? '—') ?></td>
                    <td class="text-sm text-muted" style="white-space:nowrap;"><?= $doc['deleted_at'] ? date('M d, Y · g:i a', strtotime($doc['deleted_at'])) : (($doc['updated_at'] ?? '') ? date('M d, Y · g:i a', strtotime($doc['updated_at'])) : '—') ?></td>
                    <td style="white-space:nowrap; text-align:right;">
                        <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
                            <?php if (($doc['uploader_role'] ?? '') !== 'admin' || ($_SESSION['user_role'] ?? '') === 'admin'): ?>
                            <form method="POST" data-confirm-title="Restore Document" data-confirm-message="Restore this document?" data-confirm-text="Restore" data-confirm-icon="archive-restore" data-confirm-class="btn-success" data-confirm-icon-main="archive-restore" data-confirm-tone="blue" style="margin:0;">
                                <?= csrf_field() ?><input type="hidden" name="restore_id" value="<?= $doc['id'] ?>">
                                <button class="btn btn-success btn-sm" type="submit" style="white-space:nowrap; padding:5px 10px;">
                                    <i data-lucide="archive-restore"></i> Restore
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                            <form method="POST" data-confirm-title="Delete Forever" data-confirm-message="Permanently delete this document and all stored versions? This cannot be undone." data-confirm-text="Delete Forever" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red" style="margin:0;">
                                <?= csrf_field() ?><input type="hidden" name="permanent_delete_id" value="<?= $doc['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit" style="white-space:nowrap; padding:5px 10px;">
                                    <i data-lucide="archive"></i> Delete Forever
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <div style="padding:16px 20px; border-top:1px solid var(--border-hairline); display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
        <?php for($p=1; $p<=$result['pages']; $p++): ?>
        <a class="btn btn-sm <?= $p===$page?'btn-primary':'btn-outline' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
