<?php
// TEMPORARY DIAGNOSTIC — REMOVE AFTER DEBUGGING
header('Content-Type: text/plain');
echo "=== Railway Env Diagnostic (raw getenv values) ===\n\n";

$keys = ['MYSQLHOST','MYSQLPORT','MYSQLUSER','MYSQLPASSWORD','MYSQLDATABASE','DATABASE_URL'];

foreach ($keys as $k) {
    $raw = getenv($k);
    $inEnv  = isset($_ENV[$k])    ? var_export($_ENV[$k], true)    : 'NOT SET';
    $srvStr = (isset($_SERVER[$k]) && is_string($_SERVER[$k])) ? var_export($_SERVER[$k], true) : 'NOT SET / non-string';
    echo "$k\n";
    echo "  getenv()  => " . var_export($raw, true) . "  (length=" . (is_string($raw) ? strlen($raw) : 'N/A') . ")\n";
    echo "  \$_ENV     => $inEnv\n";
    echo "  \$_SERVER  => $srvStr\n\n";
}

echo "\n--- Full getenv() dump for MYSQL/DB keys ---\n";
$all = getenv();
if (is_array($all)) {
    foreach ($all as $k => $v) {
        if (stripos($k, 'mysql') !== false || stripos($k, 'database') !== false) {
            echo var_export($k, true) . " => " . var_export($v, true) . "\n";
        }
    }
}

