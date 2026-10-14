<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
$db   = Database::getInstance();
$user = $auth->currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($firstName && $lastName && $email) {
            if (!preg_match("/^[a-zA-Z\s'-]+$/", $firstName) || !preg_match("/^[a-zA-Z\s'-]+$/", $lastName)) {
                flash_set('error', 'First name and Last name must contain only letters (no numbers or symbols).');
            } else {
                
                $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $user['id']]);
                if ($check->fetch()) {
                    flash_set('error', 'Email is already taken by another account.');
                } else {
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$firstName, $lastName, $email, $user['id']]);
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    flash_set('success', 'Profile updated successfully.');
                }
            }
        } else {
            flash_set('error', 'All profile fields are required.');
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $user['password'])) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPass)) {
            flash_set('error', 'New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.');
        } elseif ($newPass !== $confirmPass) {
            flash_set('error', 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            $auth->logActivity($user['id'], null, 'password_change', 'User changed their password');
            flash_set('success', 'Password changed successfully.');
        }
    }
    redirect_self(); 
}

['success' => $success, 'error' => $error] = flash_pull();

include __DIR__ . '/includes/header.php';
?>

<div style="max-width:720px;margin:0 auto;">
    <?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    
    <div class="card mb-16">
        <div class="card-header">
            <span class="card-title"><i data-lucide="circle-user-round" style="color:var(--primary);margin-right:8px;"></i>My Profile</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required pattern="[A-Za-z\s'-]+" title="Name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)" oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required pattern="[A-Za-z\s'-]+" title="Name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)" oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?= ucfirst(str_replace('_', ' ', $user['role'] ?? '')) ?>" disabled class="input-readonly">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?= htmlspecialchars($user['department_name'] ?? 'None') ?>" disabled class="input-readonly">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i data-lucide="key-round" style="color:var(--accent);margin-right:8px;"></i>Change Password</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required placeholder="Enter your current password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number" placeholder="At least 8 chars, 1 uppercase, 1 lowercase, 1 number">
                        <span class="form-hint" style="font-size:11.5px;color:var(--text-secondary);margin-top:4px;display:block;">Must be at least 8 characters and include uppercase, lowercase, and a number.</span>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number" placeholder="Re-type new password">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-outline"><i data-lucide="lock-keyhole"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
