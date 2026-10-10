<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireRole(['admin']);
$db   = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $db->prepare("INSERT INTO departments (name,description) VALUES (?,?)")
           ->execute([$_POST['name'],$_POST['description']??null]);
        flash_set('success', 'Department created.');
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM departments WHERE id=?")->execute([(int)$_POST['dept_id']]);
        flash_set('success', 'Department deleted.');
    } elseif ($action === 'edit') {
        $db->prepare("UPDATE departments SET name=?,description=? WHERE id=?")
           ->execute([$_POST['name'],$_POST['description']??null,(int)$_POST['dept_id']]);
        flash_set('success', 'Department updated.');
    }
    redirect_self(); // Post/Redirect/Get
}

['success' => $success] = flash_pull();

$depts = $db->query("
    SELECT d.*, COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT doc.id) AS doc_count
    FROM departments d
    LEFT JOIN users u ON u.department_id=d.id
    LEFT JOIN documents doc ON doc.department_id=d.id
    GROUP BY d.id ORDER BY d.name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="flex justify-between items-center mb-16" style="flex-wrap:wrap; gap:12px;">
    <h2 style="font-size:18px;font-weight:700;"><i data-lucide="building-2" style="color:var(--primary);margin-right:8px;"></i>Departments</h2>
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')"><i data-lucide="plus"></i> Add Department</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Users</th><th>Documents</th><th style="white-space:nowrap;">Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($depts as $i => $d): ?>
            <tr>
                <td class="text-muted text-sm"><?= $i+1 ?></td>
                <td class="fw-600"><?= htmlspecialchars($d['name']) ?></td>
                <td class="text-sm text-muted"><?= htmlspecialchars($d['description'] ?? '—') ?></td>
                <td><span class="badge badge-review"><?= $d['user_count'] ?> users</span></td>
                <td><span class="badge badge-draft"><?= $d['doc_count'] ?> docs</span></td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y',strtotime($d['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='editDept(<?= json_encode($d) ?>)'><i data-lucide="pencil"></i></button>
                        <form method="POST" data-confirm-title="Delete Department" data-confirm-message="Are you sure you want to delete this department?" data-confirm-text="Delete" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i data-lucide="trash-2"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title">Add Department</div>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Department Name</label><input type="text" name="name" required placeholder="e.g. Marketing"></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Optional description"></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title">Edit Department</div>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="dept_id" id="editDeptId">
            <div class="form-group"><label>Department Name</label><input type="text" name="name" id="editDeptName" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="editDeptDesc" rows="3"></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDept(d) {
    document.getElementById('editDeptId').value   = d.id;
    document.getElementById('editDeptName').value = d.name;
    document.getElementById('editDeptDesc').value = d.description || '';
    document.getElementById('editModal').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
