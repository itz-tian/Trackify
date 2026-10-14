<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

prevent_browser_cache();
header('Content-Type: application/json');

$auth = new Auth();
$peek = isset($_GET['peek']);
$in   = $auth->isLoggedIn(!$peek);
echo json_encode(['logged_in' => $in, 'seconds_left' => $in ? $auth->secondsLeft() : 0]);
