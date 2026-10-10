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
    render_error_page(404);
}

$ext = strtolower(pathinfo($targetFileName, PATHINFO_EXTENSION));

// Map correct MIME types for inline preview
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'txt'  => 'text/plain; charset=utf-8',
];

$mimeType = $mimeTypes[$ext] ?? ($doc['file_type'] ?: 'application/octet-stream');

// Clean output buffer before streaming
if (ob_get_level()) {
    ob_end_clean();
}

$cleanFileName = str_replace(['"', "'", "\r", "\n"], '', $targetFileName);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $cleanFileName . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');

readfile($fullPath);
exit;
