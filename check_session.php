<?php
/**
 * Session status endpoint.
 *   (default)       -> { logged_in }                       counts as activity (used on back/forward restore)
 *   ?peek=1         -> { logged_in, seconds_left }         does NOT reset the idle timer
 *   ?keepalive=1    -> { logged_in, seconds_left }         resets the idle timer ("Stay signed in")
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

prevent_browser_cache();
header('Content-Type: application/json');

$auth = new Auth();
$peek = isset($_GET['peek']);
$in   = $auth->isLoggedIn(!$peek);
echo json_encode(['logged_in' => $in, 'seconds_left' => $in ? $auth->secondsLeft() : 0]);
