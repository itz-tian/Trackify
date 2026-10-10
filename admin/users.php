<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireRole(['admin']);
$db   = Database::getInstance();

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $pass      = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'staff';
        $deptId    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if ($firstName === '' || $lastName === '' || $email === '' || $pass === '') {
            flash_set('error', 'All fields are required.');
        } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $firstName) || !preg_match("/^[a-zA-Z\s'-]+$/", $lastName)) {
            flash_set('error', 'First name and Last name must contain only letters (no numbers or symbols).');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pass)) {
            flash_set('error', 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.');
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                flash_set('error', 'Email address is already in use.');
            } else {
                $stmt = $db->prepare("INSERT INTO users (department_id,first_name,last_name,email,password,role) VALUES (?,?,?,?,?,?)");
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $stmt->execute([$deptId, $firstName, $lastName, $email, $hash, $role]);
                flash_set('success', 'User created successfully.');
            }
        }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([(int)$_POST['user_id']]);
        flash_set('success', 'User status updated.');
    } elseif ($action === 'delete' && (int)$_POST['user_id'] !== $_SESSION['user_id']) {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_POST['user_id']]);
        flash_set('success', 'User deleted.');
    }
    redirect_self(); // Post/Redirect/Get
}

['success' => $success, 'error' => $error] = flash_pull();

$users = $db->query("
    SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id=d.id ORDER BY u.created_at DESC
")->fetchAll();
$depts = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$roleColors=['admin'=>'badge-rejected','department_head'=>'badge-review','staff'=>'badge-approved','viewer'=>'badge-draft'];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="flex justify-between items-center mb-16" style="flex-wrap:wrap; gap:12px;">
    <h2 style="font-size:18px;font-weight:700;"><i data-lucide="users-round" style="color:var(--primary);margin-right:8px;"></i>Manage Users</h2>
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')"><i data-lucide="plus"></i> Add User</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th style="white-space:nowrap;">Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
                <td class="text-muted text-sm"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-avatar" title="<?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?>">
                            <i data-lucide="user-round"></i>
                        </div>
                        <span class="fw-600" style="white-space:nowrap;"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></span>
                    </div>
                </td>
                <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge <?= $roleColors[$u['role']] ?? 'badge-draft' ?>"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
                <td class="text-sm"><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $u['is_active']?'badge-approved':'badge-rejected' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span>
                </td>
                <td class="cell-nowrap text-sm text-muted" style="white-space:nowrap;"><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Toggle Status"><i data-lucide="power"></i></button>
                        </form>
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" data-confirm-title="Delete User" data-confirm-message="Are you sure you want to delete this user?" data-confirm-text="Delete" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i data-lucide="trash-2"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="user-round-plus" style="color:var(--primary);margin-right:8px;"></i>Add New User</div>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required pattern="[A-Za-z\s'-]+" title="Name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)" placeholder="Juan" oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required pattern="[A-Za-z\s'-]+" title="Name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)" placeholder="Dela Cruz" oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')">
                </div>
            </div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="juan@office.ph"></div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number" placeholder="At least 8 chars, 1 uppercase, 1 lowercase, 1 number">
                <span class="form-hint" style="font-size:11.5px;color:var(--text-secondary);margin-top:4px;display:block;">Must be at least 8 characters and include uppercase, lowercase, and a number.</span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="staff">Staff</option>
                        <option value="department_head">Department Head</option>
                        <option value="viewer">Viewer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">None</option>
                        <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
