<?php
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $hosts = [DB_HOST];
            if (DB_HOST === 'localhost') {
                $hosts[] = '127.0.0.1';
            }

            $lastException = null;

            foreach ($hosts as $host) {
                try {
                    $portPart = defined('DB_PORT') && DB_PORT ? ";port=" . DB_PORT : "";
                    $dsn = "mysql:host={$host}{$portPart};dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                    // Disable ONLY_FULL_GROUP_BY for session to ensure cross-version MySQL 8 compatibility
                    try {
                        $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
                        $pdo->exec("SET time_zone = '+08:00'");
                    } catch (Throwable $ignored) {}

                    // Check if schema is already applied (users table must exist).
                    // On Railway, the 'railway' DB already exists so error 1049 never fires —
                    // we must detect an empty DB here and seed it ourselves.
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
                    if ($tableCheck === false) {
                        $schemaPath = __DIR__ . '/../database/schema.sql';
                        if (file_exists($schemaPath)) {
                            $sql = file_get_contents($schemaPath);
                            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                            // Strip CREATE DATABASE and USE statements — the DB already exists on Railway
                            $sql = preg_replace('/^\s*(CREATE\s+DATABASE|USE)\s+[^\n;]+;?\s*$/im', '', $sql);
                            $statements = array_filter(array_map('trim', explode(';', $sql)));
                            foreach ($statements as $stmt) {
                                if ($stmt !== '') $pdo->exec($stmt);
                            }
                        }
                    }

                    self::$instance = $pdo;
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;

                    // DB missing; auto-init from schema.sql
                    if ($e->getCode() == 1049) {
                        try {
                            $serverPdo = new PDO("mysql:host={$host};charset=" . DB_CHARSET, DB_USER, DB_PASS, $options);
                            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $serverPdo->exec("USE `" . DB_NAME . "`");

                            $schemaPath = __DIR__ . '/../database/schema.sql';
                            if (file_exists($schemaPath)) {
                                $sql = file_get_contents($schemaPath);
                                // Remove full-line SQL comments before splitting statements. Otherwise a
                                // comment preceding CREATE TABLE can cause the entire statement to be skipped.
                                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                                $statements = array_filter(array_map('trim', explode(';', $sql)));
                                foreach ($statements as $stmt) {
                                    if ($stmt !== '') {
                                        // Do not silently ignore schema errors; a partial schema is worse
                                        // than a visible setup failure.
                                        $serverPdo->exec($stmt);
                                    }
                                }
                            }

                            // Reconnect to new DB
                            $portPart2 = defined('DB_PORT') && DB_PORT ? ";port=" . DB_PORT : "";
                            $dsn = "mysql:host={$host}{$portPart2};dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                            break;
                        } catch (Throwable $initException) {
                            $lastException = $initException;
                        }
                    }
                }
            }

            if (self::$instance === null) {
                $err = $lastException ? $lastException->getMessage() : 'Unknown database error';
                $availableKeys = array_keys(array_merge($_SERVER, $_ENV, getenv()));
                $dbKeys = array_filter($availableKeys, fn($k) => stripos($k, 'mysql') !== false || stripos($k, 'db') !== false || stripos($k, 'database') !== false || stripos($k, 'host') !== false);
                $foundStr = !empty($dbKeys) ? implode(', ', $dbKeys) : 'NONE';
                $rawHost = var_export(getenv('MYSQLHOST'), true);
                $rawUrl = var_export(getenv('DATABASE_URL'), true);
                $details = "Host: " . DB_HOST . ":" . (defined('DB_PORT') ? DB_PORT : '3306') . " | User: " . DB_USER . " | Database: " . DB_NAME . "\nDetected DB Env Keys: [" . $foundStr . "]\nRaw MYSQLHOST: {$rawHost} | Raw DATABASE_URL: {$rawUrl}\nError: " . $err;
                $msg = htmlspecialchars($details);
                http_response_code(500);
                die('<!DOCTYPE html><html><head><meta charset="UTF-8">
                <title>DocuTrack — Database Setup Required</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                    .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; }
                    h2 { margin: 0 0 12px; font-size: 20px; color: #dc2626; display: flex; align-items: center; gap: 8px; }
                    p { font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 16px; }
                    .code { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-family: monospace; font-size: 13px; word-break: break-all; margin-bottom: 20px; }
                    .guide { background: #f1f5f9; border-radius: 8px; padding: 16px; font-size: 13px; margin-bottom: 20px; }
                    .guide ol { margin: 0; padding-left: 20px; }
                    .guide li { margin-bottom: 8px; }
                    .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; }
                    .btn:hover { background: #1d4ed8; }
                </style></head><body>
                <div class="card">
                    <h2>Database Connection Error</h2>
                    <p>DocuTrack could not connect to MySQL server:</p>
                    <div class="code">' . $msg . '</div>
                    <div class="guide">
                        <strong>Please check:</strong>
                        <ol>
                            <li>Open <strong>XAMPP Control Panel</strong> and verify <strong>MySQL</strong> shows green (Running).</li>
                            <li>Open <a href="http://localhost/phpmyadmin" target="_blank" style="color:#2563eb;font-weight:600;">phpMyAdmin</a> and create database <code>dms_db</code>.</li>
                            <li>Import <code>database/schema.sql</code> into <code>dms_db</code>.</li>
                        </ol>
                    </div>
                    <a class="btn" href="javascript:location.reload()">&#8635; Refresh Page</a>
                </div></body></html>');
            }
        }
        return self::$instance;
    }
}
