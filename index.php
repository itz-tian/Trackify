<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth();
prevent_browser_cache();

if ($auth->isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php', true, 303);
} else {
    header('Location: ' . APP_URL . '/login.php', true, 303);
}
exit;
