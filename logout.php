<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

// Prevent bfcache

prevent_browser_cache();

$auth = new Auth();
$auth->logout();

// 303 avoids cache
header('Location: ' . APP_URL . '/login.php', true, 303);
exit;
