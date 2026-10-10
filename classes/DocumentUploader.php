<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentNotification.php';

class DocumentUploader {
    private PDO $db;
    private Auth $auth;
    private DocumentNotification $notifier;

    public function __construct(PDO $db, Auth $auth, DocumentNotification $notifier) {
        $this->db       = $db;
        $this->auth     = $auth;
        $this->notifier = $notifier;
    }

    private function referenceExists(string $reference): bool {
        $stmt = $this->db->prepare('SELECT id FROM documents WHERE reference_number = ? LIMIT 1');
        $stmt->execute([$reference]);
        return (bool)$stmt->fetchColumn();
    }

    private function buildUniqueReference(?string $requested, int $batchIndex = 0, bool $batch = false): ?string {
        $requested = trim((string)$requested);
        if ($requested === '') {
            return null;
        }

        if (!$batch) {
            if ($this->referenceExists($requested)) {
                return null;
            }
            return $requested;
        }

        $base = $requested;
        $candidate = $batchIndex > 0 ? $base . '-' . str_pad((string)$batchIndex, 3, '0', STR_PAD_LEFT) : $base;
        $suffix = max(1, $batchIndex);
        while ($this->referenceExists($candidate)) {
            $suffix++;
            $candidate = $base . '-' . str_pad((string)$suffix, 3, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    public function upload(array $data, array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit. Check upload_max_filesize in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was received.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            return ['success' => false, 'message' => $messages[$file['error']] ?? 'File upload error.'];
        }

        if (!isset($file['tmp_name']) || !is_file($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid upload source. Please select the file again.'];
        }

        if ((int)$file['size'] <= 0) {
            return ['success' => false, 'message' => 'The selected file is empty.'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File exceeds maximum allowed size of 20MB.'];
        }

        $originalName = (string)$file['name'];
        if (strpos($originalName, "\0") !== false) {
            return ['success' => false, 'message' => 'Malicious file name detected.'];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $dangerousExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'cgi', 'pl', 'exe', 'sh', 'py', 'bat', 'cmd', 'js', 'html', 'htm', 'svg'];
        if (in_array($ext, $dangerousExts, true)) {
            return ['success' => false, 'message' => 'Executable and script files are strictly prohibited.'];
        }

        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => 'File extension "' . ($ext ? '.' . $ext : '(none)') . '" is not permitted. Supported formats: ' . implode(', ', array_map('strtoupper', ALLOWED_EXTENSIONS)) . '.'];
        }

        $requestedReference = trim((string)($data['_resolved_reference'] ?? $data['reference_number'] ?? ''));
        if ($requestedReference !== '' && !isset($data['_resolved_reference']) && $this->referenceExists($requestedReference)) {
            return ['success' => false, 'message' => 'Reference Number "' . $requestedReference . '" is already in use. Please choose a different reference number.'];
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                return ['success' => false, 'message' => 'Server file validation is unavailable; contact the administrator.'];
            }
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'pdf'  => ['application/pdf'],
                'doc'  => ['application/msword', 'application/vnd.ms-office'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office'],
                'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
                'txt'  => ['text/plain'],
                'csv'  => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
                'png'  => ['image/png'],
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'zip'  => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream']
            ];

            if ($detectedMime === false || !isset($allowedMimes[$ext]) || !in_array($detectedMime, $allowedMimes[$ext], true)) {
                return ['success' => false, 'message' => 'Security alert: File content does not match its extension.'];
            }
        } else {
            return ['success' => false, 'message' => 'Server file validation is unavailable; contact the administrator.'];
        }

        $safeName   = bin2hex(random_bytes(16)) . '.' . $ext;
        $baseUploadDir = rtrim(UPLOAD_PATH, '/\\') . '/';
        if (!is_dir($baseUploadDir) && !@mkdir($baseUploadDir, 0777, true) && !is_dir($baseUploadDir)) {
            return ['success' => false, 'message' => 'Upload directory could not be created.'];
        }
        @chmod($baseUploadDir, 0777);

        $subDir = date('Y/m/');
        $folderPart = '';
        if (!empty($file['relative_path']) && str_contains(str_replace('\\', '/', $file['relative_path']), '/')) {
            $parts = explode('/', str_replace('\\', '/', $file['relative_path']));
            array_pop($parts);
            $safeParts = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || $part === '.' || $part === '..') continue;
                $part = preg_replace('/[^A-Za-z0-9 ._-]/', '_', $part);
                $part = trim($part, '. ');
                if ($part !== '') $safeParts[] = substr($part, 0, 80);
            }
            if ($safeParts) {
                $folderPart = implode('/', $safeParts) . '/';
            }
        }

        $subDir .= $folderPart;
        $targetDir = $baseUploadDir . $subDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Upload directory could not be created.'];
        }
        @chmod($targetDir, 0777);
        $relativePath = 'uploads/' . $subDir . $safeName;
        $destFile = $targetDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destFile)) {
            return ['success' => false, 'message' => 'Failed to save file. Please verify write permissions for the uploads directory.'];
        }
        $userId        = $_SESSION['user_id'];
        $isAdminUpload = (($_SESSION['user_role'] ?? '') === 'admin');

        if ($isAdminUpload) {
            $data['status'] = 'approved';
        } else {
            $data['department_id'] = (int)($_SESSION['user_dept'] ?? 0);
            if (!in_array($data['status'] ?? 'draft', ['draft', 'pending_review'], true)) {
                $data['status'] = 'pending_review';
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO documents (title, description, category_id, department_id, uploaded_by,
                file_name, file_path, file_size, file_type, tags, status, priority, expires_at, reference_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $extension = strtoupper($ext);
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            (($_SESSION['user_role'] ?? '') !== 'admin' ? $_SESSION['user_dept'] : ($data['department_id'] ?? $_SESSION['user_dept'])),
            $userId,
            $file['name'],
            $relativePath,
            $file['size'],
            $extension,
            $data['tags'] ?? null,
            $data['status'] ?? 'draft',
            $data['priority'] ?? 'normal',
            $data['expires_at'] ?? null,
            $data['_resolved_reference'] ?? (!empty($data['reference_number']) ? trim($data['reference_number']) : null),
        ]);

        $docId = (int)$this->db->lastInsertId();

        if (empty($data['reference_number'])) {
            $reference = 'DOC-' . date('Y') . '-' . str_pad((string)$docId, 6, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE documents SET reference_number = ? WHERE id = ?')->execute([$reference, $docId]);
        }

        $this->db->prepare("
            INSERT INTO document_versions (document_id, version, file_name, file_path, file_size, notes, uploaded_by)
            VALUES (?, 1, ?, ?, ?, ?, ?)
        ")->execute([$docId, $file['name'], $relativePath, $file['size'], 'Initial upload', $userId]);

        $this->auth->logActivity($userId, $docId, 'upload', "Uploaded document: {$data['title']}");

        if (($data['status'] ?? 'draft') === 'pending_review') {
            $this->notifier->notifyApprovers($docId);
        }

        return ['success' => true, 'document_id' => $docId];
    }

    public function uploadBatch(array $data, array $files): array {
        $rawCount = count($files['name'] ?? []);
        if ($rawCount === 0) {
            return ['success' => false, 'uploaded' => 0, 'failed' => [], 'message' => 'No files were selected.'];
        }

        if ($rawCount > 100) {
            return ['success' => false, 'uploaded' => 0, 'failed' => [], 'message' => 'A maximum of 100 files can be uploaded at once.'];
        }

        $uploaded = 0;
        $failed = [];
        $originalTitle = trim((string)($data['title'] ?? ''));
        $requestedReference = trim((string)($data['reference_number'] ?? ''));

        $indexes = [];
        for ($i = 0; $i < $rawCount; $i++) {
            $name = (string)($files['name'][$i] ?? '');
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($name !== '' && $error !== UPLOAD_ERR_NO_FILE) {
                $indexes[] = $i;
            }
        }

        $isMultiple = count($indexes) > 1;

        foreach ($indexes as $position => $i) {
            $baseName = (string)$files['name'][$i];
            $relativePath = (string)($files['full_path'][$i] ?? $baseName);

            $singleFile = [
                'name' => $baseName,
                'type' => (string)($files['type'][$i] ?? ''),
                'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_OK),
                'size' => (int)($files['size'][$i] ?? 0),
                'relative_path' => $relativePath,
            ];

            $docData = $data;
            if ($isMultiple) {
                $title = pathinfo($baseName, PATHINFO_FILENAME);
                $title = preg_replace('/[_-]+/', ' ', $title);
                $title = trim(preg_replace('/\s+/', ' ', $title));
                if (mb_strlen($title) < 3) {
                    $title = $originalTitle !== '' ? $originalTitle . ' - ' . $baseName : 'Document - ' . $baseName;
                }
                $docData['title'] = $title;
                $docData['description'] = ($originalTitle !== '' ? $originalTitle . ' — ' : '') .
                    ($docData['description'] ?? '') .
                    ' (Batch upload: ' . $relativePath . ')';
            }

            if ($requestedReference !== '') {
                $resolvedRef = $this->buildUniqueReference($requestedReference, $position + 1, $isMultiple);
                if ($resolvedRef === null) {
                    $failed[] = $relativePath . ': Reference number "' . $requestedReference . '" is already in use.';
                    continue;
                }
                $docData['_resolved_reference'] = $resolvedRef;
            }

            $result = $this->upload($docData, $singleFile);
            if ($result['success']) {
                $uploaded++;
            } else {
                $failed[] = $relativePath . ': ' . $result['message'];
            }
        }

        $expected = (int)($data['expected_file_count'] ?? 0);
        if ($expected > count($indexes)) {
            $failed[] = 'Server received only ' . count($indexes) . ' of ' . $expected . ' selected files. PHP may be limiting max_file_uploads or post_max_size.';
        }

        if ($uploaded === 0) {
            return ['success' => false, 'uploaded' => 0, 'failed' => $failed, 'message' => 'No files were uploaded successfully.'];
        }

        return ['success' => true, 'uploaded' => $uploaded, 'failed' => $failed];
    }
}
