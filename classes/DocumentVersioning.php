<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentNotification.php';

class DocumentVersioning {
    private PDO $db;
    private Auth $auth;
    private DocumentNotification $notifier;

    public function __construct(PDO $db, Auth $auth, DocumentNotification $notifier) {
        $this->db       = $db;
        $this->auth     = $auth;
        $this->notifier = $notifier;
    }

    public function uploadVersion(int $id, array $file, ?string $notes = null, ?string $newStatus = null, ?array $doc = null): array {
        if (!$doc) {
            $stmt = $this->db->prepare("
                SELECT d.*, u.role AS uploader_role 
                FROM documents d 
                LEFT JOIN users u ON u.id = d.uploaded_by 
                WHERE d.id = ? AND d.deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
        }

        if (!$doc) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $userId   = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['user_role'] ?? 'staff';
        $userDept = (int)($_SESSION['user_dept'] ?? 0);

        $isAdminOwned = (($doc['uploader_role'] ?? '') === 'admin');
        if ($isAdminOwned && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'This document was uploaded by an administrator and is protected from changes.'];
        }

        $isDeptHead = ($userRole === 'department_head'
                       && (!empty($doc['department_id']) && (int)$doc['department_id'] === $userDept)
                       && (int)$doc['uploaded_by'] !== $userId);
        if ($userRole !== 'admin' && $doc['uploaded_by'] != $userId && !$isDeptHead) {
            return ['success' => false, 'message' => 'Permission denied. Department heads may manage staff documents in their department, but administrator-owned documents are protected.'];
        }

        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Please select a valid file to upload as the new version.'];
        }

        if (!isset($file['tmp_name']) || !is_file($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid upload source. Please select the file again.'];
        }
        if ((int)$file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File is empty or exceeds maximum allowed size of 20MB.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => 'File type ".' . $ext . '" is not permitted.'];
        }
        $allowedMimes = [
            'pdf'=>['application/pdf'], 'doc'=>['application/msword','application/vnd.ms-office'],
            'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
            'xls'=>['application/vnd.ms-excel','application/vnd.ms-office'],
            'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
            'ppt'=>['application/vnd.ms-powerpoint','application/vnd.ms-office'],
            'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'],
            'txt'=>['text/plain','application/x-empty'],
            'csv'=>['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
            'png'=>['image/png'], 'jpg'=>['image/jpeg'],
            'jpeg'=>['image/jpeg'], 'zip'=>['application/zip','application/x-zip','application/x-zip-compressed','multipart/x-zip','application/octet-stream']
        ];
        if (!function_exists('finfo_open')) return ['success' => false, 'message' => 'Server file validation is unavailable.'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if ($detectedMime === false || !in_array($detectedMime, $allowedMimes[$ext] ?? [], true)) {
            return ['success' => false, 'message' => 'Security alert: File content does not match its extension.'];
        }

        $subDir    = date('Y/m/');
        $targetDir = rtrim(UPLOAD_PATH, '/\\') . '/' . $subDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Upload directory could not be created.'];
        }
        @chmod($targetDir, 0777);

        $safeName     = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destFile     = $targetDir . $safeName;
        $relativePath = 'uploads/' . $subDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destFile)) {
            return ['success' => false, 'message' => 'Failed to save file. Please verify write permissions for the uploads directory.'];
        }

        $currentVersion = (int)($doc['version'] ?? 1);
        $nextVersion    = $currentVersion + 1;
        $extension      = strtoupper($ext);
        $versionNotes   = !empty($notes) ? trim($notes) : 'Version ' . $nextVersion . ' update';

        $this->db->prepare("
            INSERT INTO document_versions (document_id, version, file_name, file_path, file_size, notes, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$id, $nextVersion, $file['name'], $relativePath, $file['size'], $versionNotes, $userId]);

        $updateStatusSql = '';
        $updateParams = [$file['name'], $relativePath, $file['size'], $extension, $nextVersion];
        
        $allowedStatuses = ['draft','pending_review','under_review','approved','rejected','archived'];
        if ($newStatus && in_array($newStatus, $allowedStatuses, true)) {
            $isDecision = in_array($newStatus, ['approved', 'rejected', 'under_review'], true);
            if ($isDecision && $userRole !== 'admin' && (int)$doc['uploaded_by'] === $userId) {
                $newStatus = 'pending_review';
            }
            $updateStatusSql = ', status = ?';
            $updateParams[] = $newStatus;
        }
        $updateParams[] = $id;

        $this->db->prepare("
            UPDATE documents 
            SET file_name = ?, file_path = ?, file_size = ?, file_type = ?, version = ?, updated_at = NOW() $updateStatusSql
            WHERE id = ?
        ")->execute($updateParams);

        $this->auth->logActivity($userId, $id, 'upload', "Uploaded new version v{$nextVersion} ({$file['name']})");

        if ($newStatus && $newStatus === 'pending_review') {
            $this->notifier->notifyApprovers($id);
        }

        return ['success' => true, 'new_version' => $nextVersion];
    }
}
