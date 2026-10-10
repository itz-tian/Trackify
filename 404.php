<?php
// 404 — friendly error page. Works when included by render_error_page() and when served by Apache (ErrorDocument).
if (!defined('APP_URL')) { require_once __DIR__ . '/config/config.php'; }
if (!headers_sent()) { http_response_code(404); }
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trackify — Page not found</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/favicon.png">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= defined('APP_VERSION') ? APP_VERSION : '1' ?>">
    <script>
        (function () {
            var s = 'system'; try { s = localStorage.getItem('trackify_theme') || 'system'; } catch (e) {}
            var dark = s === 'dark' || (s === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        })();
    </script>
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .err-card { max-width: 460px; width: 100%; text-align: center; padding: 44px 34px 38px; border-radius: 20px; background: var(--bg-card); box-shadow: var(--shadow-float); }
        .err-code { font-size: 72px; font-weight: 800; letter-spacing: -0.05em; line-height: 1; color: var(--apple-blue); margin: 14px 0 6px; }
        .err-card h1 { font-size: 21px; margin: 0 0 8px; color: var(--text-primary); letter-spacing: -0.02em; }
        .err-card p { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin: 0 0 26px; }
        .err-logo { width: 54px; height: 54px; object-fit: contain; }
        .err-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="err-card">
        <img class="err-logo" src="<?= APP_URL ?>/assets/logo.png" alt="Trackify" onerror="this.style.display='none'">
        <div class="err-code">404</div>
        <h1>We can’t find that page</h1>
        <p>The page or document you’re looking for doesn’t exist, was moved, or has been archived.</p>
        <div class="err-actions">
            <a class="btn btn-primary" href="<?= APP_URL ?>/dashboard.php">Go to Dashboard</a>
            <a class="btn btn-outline" href="javascript:history.back()">Go back</a>
        </div>
    </div>
</body>
</html>
