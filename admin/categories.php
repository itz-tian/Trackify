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
        $db->prepare("INSERT INTO categories (name,description,color) VALUES (?,?,?)")
           ->execute([$_POST['name'],$_POST['description']??null,$_POST['color']??'#3b82f6']);
        flash_set('success', 'Category created.');
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_POST['cat_id']]);
        flash_set('success', 'Category deleted.');
    } elseif ($action === 'edit') {
        $db->prepare("UPDATE categories SET name=?,description=?,color=? WHERE id=?")
           ->execute([$_POST['name'],$_POST['description']??null,$_POST['color']??'#3b82f6',(int)$_POST['cat_id']]);
        flash_set('success', 'Category updated.');
    }
    redirect_self(); 
}

['success' => $success] = flash_pull();

$categories = $db->query("SELECT c.*, COUNT(d.id) AS doc_count FROM categories c LEFT JOIN documents d ON c.id=d.category_id GROUP BY c.id ORDER BY c.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="flex justify-between items-center mb-16" style="flex-wrap:wrap; gap:12px;">
    <h2 style="font-size:18px;font-weight:700;"><i data-lucide="tags" style="color:var(--primary);margin-right:8px;"></i>Document Categories</h2>
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')"><i data-lucide="plus"></i> Add Category</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Color</th><th>Name</th><th>Description</th><th>Documents</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= htmlspecialchars($cat['color']) ?>;"></span></td>
                <td class="fw-600"><?= htmlspecialchars($cat['name']) ?></td>
                <td class="text-sm text-muted"><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                <td><span class="badge badge-draft"><?= $cat['doc_count'] ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='editCategory(<?= json_encode($cat) ?>)'><i data-lucide="pencil"></i></button>
                        <form method="POST" data-confirm-title="Delete Category" data-confirm-message="Are you sure you want to delete this category?" data-confirm-text="Delete" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
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


<div class="modal-overlay" id="createModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title">Add Category</div>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Name</label><input type="text" name="name" required placeholder="Category name"></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="2" placeholder="Optional description"></textarea></div>
            <div class="form-group"><label>Color</label><input type="color" name="color" value="#3b82f6" style="width:60px;height:36px;padding:2px;cursor:pointer;"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title">Edit Category</div>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="cat_id" id="editCatId">
            <div class="form-group"><label>Name</label><input type="text" name="name" id="editCatName" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="editCatDesc" rows="2"></textarea></div>
            <div class="form-group"><label>Color</label><input type="color" name="color" id="editCatColor" style="width:60px;height:36px;padding:2px;cursor:pointer;"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(cat) {
    document.getElementById('editCatId').value    = cat.id;
    document.getElementById('editCatName').value  = cat.name;
    document.getElementById('editCatDesc').value  = cat.description || '';
    document.getElementById('editCatColor').value = cat.color || '#3b82f6';
    document.getElementById('editModal').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
