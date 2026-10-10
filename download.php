<?php
/** Secure download gateway. */
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

// Target file

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

// Directory Traversal Prevention & Realpath Check

$baseUploadDir = realpath(UPLOAD_PATH);
$fullPath = realpath(__DIR__ . '/' . ltrim($targetFilePath, '/\\'));

if (!$fullPath || !$baseUploadDir || strpos($fullPath, $baseUploadDir) !== 0 || !is_file($fullPath)) {
    render_error_page(404);
}

// Log download activity
$auth->logActivity((int)($_SESSION['user_id'] ?? 0), $docId, 'download', "Downloaded file: {$targetFileName}");

// Clean output buffer before streaming
if (ob_get_level()) {
    ob_end_clean();
}

// Safe headers for downloading
$cleanFileName = str_replace(['"', "'", "\r", "\n"], '', $targetFileName);
$downloadMimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg'
];
$downloadExt = strtolower(pathinfo($targetFileName, PATHINFO_EXTENSION));
$mimeType = $downloadMimeTypes[$downloadExt] ?? (str_contains((string)$doc['file_type'], '/') ? $doc['file_type'] : 'application/octet-stream');

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $cleanFileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
