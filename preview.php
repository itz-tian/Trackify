<?php
/** Document preview gateway. */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth = new Auth();
$auth->requireLogin();

$db       = Database::getInstance();
$docClass = new Document();

$docId     = (int)($_GET['id'] ?? 0);
$versionId = isset($_GET['v']) ? (int)$_GET['v'] : null;

if ($docId <= 0) {
    http_response_code(400);
    die('Invalid document ID specified.');
}

$doc = $docClass->getById($docId);
if (!$doc) {
    render_error_page(404);
}

// All authenticated users can view/download every active document.
// Modification permissions are enforced inside Document.php.

// Determine target file path
$targetFileName = $doc['file_name'];
$targetFilePath = $doc['file_path'];

if ($versionId) {
    $verStmt = $db->prepare("SELECT * FROM document_versions WHERE document_id = ? AND id = ? LIMIT 1");
    $verStmt->execute([$docId, $versionId]);
    $ver = $verStmt->fetch();
    if ($ver) {
        $targetFileName = $ver['file_name'];
        $targetFilePath = $ver['file_path'];
    }
}

// Directory Traversal Prevention
$baseUploadDir = realpath(UPLOAD_PATH);
$fullPath = realpath(__DIR__ . '/' . ltrim($targetFilePath, '/\\'));

if (!$fullPath || !$baseUploadDir || strpos($fullPath, $baseUploadDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { margin:0; display:flex; align-items:center; justify-content:center; height:100vh; background:#1c1c1e; color:#8e8e93; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; text-align:center; padding:20px; box-sizing:border-box; }
            .box { max-width:320px; }
            h2 { font-size:15px; color:#f5f5f7; margin-bottom:6px; font-weight:600; }
            p { font-size:12.5px; line-height:1.5; margin:0; color:#8e8e93; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>File Not Available</h2>
            <p>The document file could not be located in storage.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$ext = strtolower(pathinfo($targetFileName, PATHINFO_EXTENSION));

// Serve all supported formats with correct MIME types for both inline preview and JS-based viewers
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'txt'  => 'text/plain; charset=utf-8',
    'csv'  => 'text/csv; charset=utf-8',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
];

// Fallback: if file type is a valid MIME string use it, otherwise serve as binary stream
$mimeType = $mimeTypes[$ext] ?? (str_contains((string)$doc['file_type'], '/') ? $doc['file_type'] : 'application/octet-stream');

// Clean output buffer before streaming
if (ob_get_level()) {
    ob_end_clean();
}

$cleanFileName = str_replace(['"', "'", "\r", "\n"], '', $targetFileName);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $cleanFileName . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: frame-ancestors 'self';");

readfile($fullPath);
exit;
