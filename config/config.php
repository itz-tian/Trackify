<?php
// ── Database ─────────────────────────────────────────────────────────────────
// Reads Railway / Cloud environment variables first; falls back to local XAMPP defaults.
$env = function(string $key): ?string {
    // Railway injects variables into $_SERVER (not process env), so check $_SERVER first.
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return (string)$_SERVER[$key];
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string)$_ENV[$key];
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    return null;
};

$dbHost = $env('MYSQLHOST') ?? $env('DB_HOST') ?? 'localhost';
$dbUser = $env('MYSQLUSER') ?? $env('DB_USER') ?? 'root';
$dbPass = $env('MYSQLPASSWORD') ?? $env('MYSQL_PASSWORD') ?? $env('DB_PASS') ?? '';
$dbName = $env('MYSQLDATABASE') ?? $env('DB_NAME') ?? 'dms_db';
$dbPort = $env('MYSQLPORT') ?? $env('DB_PORT') ?? '3306';

// If Railway provides a full MYSQL_URL or DATABASE_URL
$mysqlUrl = $env('MYSQL_URL') ?? $env('DATABASE_URL');
if ($mysqlUrl) {
    $parsedUrl = parse_url($mysqlUrl);
    if ($parsedUrl) {
        $dbHost = $parsedUrl['host'] ?? $dbHost;
        $dbUser = $parsedUrl['user'] ?? $dbUser;
        $dbPass = $parsedUrl['pass'] ?? $dbPass;
        $dbPort = (string)($parsedUrl['port'] ?? $dbPort);
        $dbName = ltrim($parsedUrl['path'] ?? '', '/') ?: $dbName;
    }
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Trackify');
define('APP_VERSION', '1.0.4');

// Bcrypt cost factor. 12 is the 2024+ recommended minimum.
define('BCRYPT_COST', 12);

// Default days allowed for a document approval decision (used by the calendar).
if (!defined('APPROVAL_SLA_DAYS')) define('APPROVAL_SLA_DAYS', 7);

// ── HTTPS & Reverse Proxy Detection ──────────────────────────────────────────
if (!function_exists('is_https_request')) {
    function is_https_request(): bool {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
        return false;
    }
}

// ── APP_URL — fully automatic, no hardcoded folder name ──────────────────────
// Works whether the app lives at the root of a domain or inside a subfolder,
// and correctly detects HTTPS when running behind reverse proxies (Railway, Cloudflare, etc.).
if (!defined('APP_URL')) {
    $protocol = is_https_request() ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Derive the subfolder path by comparing the real app root to the document root.
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(__DIR__ . '/..') ?: '';
    $basePath = '';
    if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $basePath = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }

    define('APP_URL', $protocol . '://' . $host . $basePath);
}

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20 MB
// Permitted file types for upload and versioning. ZIP is used by folder compress-and-upload.
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'zip', 'csv']);

define('SESSION_TIMEOUT', 3600);            // idle timeout (seconds without activity) — 1 hour
define('SESSION_ABSOLUTE_TIMEOUT', 28800);  // hard limit per login — 8 hours
define('SESSION_WARNING_SECONDS', 60);      // show the "still there?" modal this long before the idle timeout

// DEPLOYMENT: false — hides the admin credentials hint from the login page.
if (!defined('SHOW_DEMO_CREDENTIALS')) define('SHOW_DEMO_CREDENTIALS', false);

if (session_status() === PHP_SESSION_NONE) {
    session_name('DOCUTRACK_SESSID');
    // Harden the session cookie: not readable from JavaScript, not sent on cross-site requests, HTTPS-only when on HTTPS.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** No-cache headers; call on every protected page. */
function prevent_browser_cache(): void {
    if (headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

/** Baseline security headers. */
function send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');

    // HSTS: tell browsers to always use HTTPS for the next year (HTTPS-only servers).
    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content Security Policy — 'unsafe-inline' required because the app uses inline <script>/<style>.
    // Once scripts are moved to external files, 'unsafe-inline' can be removed for stronger protection.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");
}

send_security_headers();

/** Returns session CSRF token. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input with CSRF token. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Constant-time CSRF token validation. */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Validates CSRF on POST or dies with 403. */
function require_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{font-family:-apple-system,sans-serif;padding:50px;background:#f5f5f7;color:#1d1d1f;text-align:center;} .box{background:#fff;padding:30px;border-radius:12px;display:inline-block;box-shadow:0 4px 12px rgba(0,0,0,0.08);max-width:400px;}</style></head><body><div class="box"><h2>Security Alert (CSRF)</h2><p style="color:#86868b;font-size:14px;margin:15px 0;">Your session verification token is missing or invalid. Please refresh the page and try again.</p><a href="javascript:history.back()" style="display:inline-block;padding:8px 18px;background:#0071e3;color:#fff;border-radius:980px;text-decoration:none;font-size:13px;font-weight:600;">Go Back</a></div></body></html>');
        }
    }
}

/**
 * ── Flash messages + Post/Redirect/Get ──────────────────────────────
 * Every form handler should finish with flash_set() + redirect_self() (or redirect_to())
 * so the browser's history entry is a plain GET page. Pressing Back/Forward then never
 * replays a POST (no "Confirm Form Resubmission", no stale action, no repeat modal).
 * The next page load calls flash_pull() and shows the message once.
 */
function flash_set(string $type, string $message): void {
    if (!in_array($type, ['success', 'error', 'info'], true)) $type = 'info';
    $_SESSION['flash'][$type] = $message;
}

/** Returns ['success'=>..., 'error'=>..., 'info'=>...] and clears them (empty string when unset). */
function flash_pull(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return [
        'success' => (string)($f['success'] ?? ''),
        'error'   => (string)($f['error'] ?? ''),
        'info'    => (string)($f['info'] ?? ''),
    ];
}

/** Sets a success or error flash depending on a Document-class style result array. */
function flash_result(array $res, string $successMsg): void {
    if (!empty($res['success'])) flash_set('success', $successMsg);
    else flash_set('error', (string)($res['message'] ?? 'Something went wrong.'));
}

/** Redirects (303 See Other) and stops. Use after every successful or failed POST. */
function redirect_to(string $url): void {
    header('Location: ' . $url, true, 303);
    exit;
}

/** Redirects back to the current page (same path + query string) with a GET. */
function redirect_self(): void {
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = '/' . ltrim((string)parse_url($uri, PHP_URL_PATH), '/');
    $qs   = parse_url($uri, PHP_URL_QUERY);
    redirect_to($path . ($qs ? '?' . $qs : ''));
}

/**
 * Renders a compact pagination bar (Prev / numbers / Next). Returns '' when there is only one page.
 * $pageKey is the query-string key (e.g. 'page'); $anchor optionally scrolls back to a card (e.g. 'recentDocs').
 */
function render_pagination(int $page, int $pages, string $pageKey = 'page', string $anchor = ''): string {
    if ($pages <= 1) return '';
    $base = $_GET;
    $url = function (int $p) use ($base, $pageKey, $anchor): string {
        $base[$pageKey] = $p;
        return '?' . http_build_query($base) . ($anchor !== '' ? '#' . $anchor : '');
    };
    $start = max(1, min($page - 2, $pages - 4));
    $end   = min($pages, $start + 4);
    $html  = '<div style="padding:14px 20px;border-top:1px solid var(--border-hairline);display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:center;">';
    if ($page > 1) $html .= '<a href="' . htmlspecialchars($url($page - 1)) . '" class="btn btn-sm btn-outline">&laquo; Prev</a>';
    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($url(1)) . '" class="btn btn-sm btn-outline">1</a>';
        if ($start > 2) $html .= '<span class="text-muted text-sm">&hellip;</span>';
    }
    for ($p = $start; $p <= $end; $p++) {
        $html .= '<a href="' . htmlspecialchars($url($p)) . '" class="btn btn-sm ' . ($p === $page ? 'btn-primary' : 'btn-outline') . '"' . ($p === $page ? ' aria-current="page"' : '') . '>' . $p . '</a>';
    }
    if ($end < $pages) {
        if ($end < $pages - 1) $html .= '<span class="text-muted text-sm">&hellip;</span>';
        $html .= '<a href="' . htmlspecialchars($url($pages)) . '" class="btn btn-sm btn-outline">' . $pages . '</a>';
    }
    if ($page < $pages) $html .= '<a href="' . htmlspecialchars($url($page + 1)) . '" class="btn btn-sm btn-outline">Next &raquo;</a>';
    return $html . '</div>';
}

/** True when a title is just a hash / UUID / long hex string (e.g. "9f4cf0f24b376077a2fcdab2e85c3584"). */
function title_looks_like_hash(string $title): bool {
    $t = trim($title);
    if ($t === '') return false;
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $t)) return true;
    return (bool)preg_match('/^[0-9a-f]{16,}$/i', preg_replace('/[\s_-]+/', '', $t));
}

/** Title to DISPLAY: hash-like titles (old data) fall back to "Untitled document · REF". */
function doc_title($title, $reference = null): string {
    $title = (string)$title;
    if (title_looks_like_hash($title)) {
        return 'Untitled document' . ($reference ? ' · ' . $reference : '');
    }
    return $title;
}

/** Deadline badge: [css class, label] — red (<=3 days / overdue), orange (<=7), blue (later). */
function deadline_badge(int $days): array {
    if ($days < 0)   return ['badge-dl-overdue', 'Overdue by ' . (-$days) . ' day' . ($days === -1 ? '' : 's')];
    if ($days === 0) return ['badge-dl-urgent',  'Due today'];
    $label = $days . ' day' . ($days === 1 ? '' : 's') . ' left';
    if ($days <= 3)  return ['badge-dl-urgent', $label];
    if ($days <= 7)  return ['badge-dl-soon',   $label];
    return ['badge-dl-later', $label];
}

/** Renders a friendly error page (403.php / 404.php) and stops. */
function render_error_page(int $code): void {
    $code = in_array($code, [403, 404], true) ? $code : 404;
    if (!headers_sent()) http_response_code($code);
    require __DIR__ . '/../' . $code . '.php';
    exit;
}
