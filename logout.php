<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';



prevent_browser_cache();

$auth = new Auth();
$auth->logout();


header('Location: ' . APP_URL . '/login.php', true, 303);
exit;
