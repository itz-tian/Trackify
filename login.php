<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth  = new Auth();
$error = '';

// Redirect if logged in
if ($auth->isLoggedIn()) {
    prevent_browser_cache();
    header('Location: ' . APP_URL . '/dashboard.php', true, 303);
    exit;
}

// Prevent cache
prevent_browser_cache();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result   = $auth->login($email, $password);
    if ($result['success']) {
        header('Location: ' . APP_URL . '/dashboard.php', true, 303);
        exit;
    } else {
        $error = $result['message'];
    }
} elseif (isset($_GET['timeout'])) {
    $error = 'You were signed out because of inactivity. Please sign in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Log In</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/favicon.png?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login.css?v=<?= APP_VERSION ?>.<?= time() ?>">
</head>
<body>

<div class="login-canvas">
    <!-- Left Hero Half (Apple Dark Slate Panel) -->
    <div class="login-left">
        <div class="left-top">
            <img src="<?= APP_URL ?>/assets/logo-light.png?v=<?= APP_VERSION ?>" alt="<?= APP_NAME ?>" class="brand-logo-img">
            <span class="brand-text"><?= APP_NAME ?></span>
        </div>

        <div class="left-center">
            <div class="accent-dash"></div>
            <h1 class="left-heading">Smart, secure document tracking for modern teams.</h1>
            <ul class="left-perks">
                <li class="left-perk-item">
                    <span class="perk-check"><i data-lucide="check" style="width:12px;height:12px;stroke-width:2.5;"></i></span>
                    Fast uploads with automated format indexing
                </li>
                <li class="left-perk-item">
                    <span class="perk-check"><i data-lucide="check" style="width:12px;height:12px;stroke-width:2.5;"></i></span>
                    Multi-tier approval workflows & status tracking
                </li>
                <li class="left-perk-item">
                    <span class="perk-check"><i data-lucide="check" style="width:12px;height:12px;stroke-width:2.5;"></i></span>
                    Full audit history & departmental compliance
                </li>
            </ul>
        </div>
    </div>

    <!-- Right Form Half (Apple Clean White Panel) -->
    <div class="login-right">
        <div class="login-form-box">
            <div class="mobile-brand-header">
                <img src="<?= APP_URL ?>/assets/logo-light.png?v=<?= APP_VERSION ?>" alt="<?= APP_NAME ?>" class="mobile-logo-img">
                <span class="mobile-brand-title"><?= APP_NAME ?></span>
            </div>
            <h1 class="login-form-title">Log In</h1>
            <p class="login-form-desc">Enter your credentials to access your document workspace.</p>

            <?php if ($error): ?>
            <div class="alert-error"><i data-lucide="circle-alert" style="width:16px;height:16px;flex-shrink:0;"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="field-label" for="emailField">Email</label>
                    <input type="text" id="emailField" name="email" class="field-input" placeholder="admin@dms.local" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="field-label" for="passwordField">Password</label>
                    <div class="pass-wrapper">
                        <input type="password" name="password" id="passwordField" class="field-input" placeholder="••••••••" required>
                        <button type="button" class="btn-toggle-show" id="togglePassBtn" onclick="togglePass()">Show</button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <?php if (SHOW_DEMO_CREDENTIALS): ?>
            <div class="demo-credentials">
                Default credentials: <strong>admin@dms.local</strong> / <strong>password</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/lucide.min.js?v=<?= APP_VERSION ?>"></script>
<script>
lucide.createIcons();

function togglePass() {
    const f = document.getElementById('passwordField');
    const btn = document.getElementById('togglePassBtn');
    const isPass = f.type === 'password';
    f.type = isPass ? 'text' : 'password';
    btn.textContent = isPass ? 'Hide' : 'Show';
}

window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
        fetch('<?= APP_URL ?>/check_session.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (data.logged_in) {
                    window.location.replace('<?= APP_URL ?>/dashboard.php');
                }
            });
    }
});
</script>
</body>
</html>
