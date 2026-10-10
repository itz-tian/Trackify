<?php
// TEMPORARY DIAGNOSTIC — REMOVE AFTER DEBUGGING
$keys = ['MYSQLHOST','MYSQLPORT','MYSQLUSER','MYSQLPASSWORD','MYSQLDATABASE','DATABASE_URL'];
$rows = [];
foreach ($keys as $k) {
    $rows[] = [
        'key'       => $k,
        '$_SERVER'  => isset($_SERVER[$k]) ? substr($_SERVER[$k], 0, 30) . (strlen($_SERVER[$k]) > 30 ? '…' : '') : 'NOT SET',
        '$_ENV'     => isset($_ENV[$k])    ? substr($_ENV[$k], 0, 30)    . (strlen($_ENV[$k])    > 30 ? '…' : '') : 'NOT SET',
        'getenv()'  => (getenv($k) !== false) ? substr(getenv($k), 0, 30) . (strlen(getenv($k)) > 30 ? '…' : '') : 'false',
    ];
}

header('Content-Type: text/plain');
echo "=== Railway Env Diagnostic ===\n\n";
printf("%-20s %-32s %-32s %-32s\n", 'KEY', '$_SERVER', '$_ENV', 'getenv()');
echo str_repeat('-', 120) . "\n";
foreach ($rows as $r) {
    printf("%-20s %-32s %-32s %-32s\n", $r['key'], $r['$_SERVER'], $r['$_ENV'], $r['getenv()']);
}

echo "\n--- All $_SERVER keys containing 'MYSQL' or 'DB' ---\n";
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'mysql') !== false || stripos($k, 'database') !== false) {
        echo "$k = " . substr($v, 0, 60) . "\n";
    }
}

echo "\n--- getenv() array keys containing 'MYSQL' or 'DB' ---\n";
$env = getenv();
if (is_array($env)) {
    foreach ($env as $k => $v) {
        if (stripos($k, 'mysql') !== false || stripos($k, 'database') !== false) {
            echo "$k = " . substr($v, 0, 60) . "\n";
        }
    }
} else {
    echo "getenv() did not return array\n";
}
