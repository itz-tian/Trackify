<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentNotification.php';
require_once __DIR__ . '/DocumentUploader.php';
require_once __DIR__ . '/DocumentWorkflow.php';
require_once __DIR__ . '/DocumentVersioning.php';

class Document {
    private PDO $db;
    private Auth $auth;
    private DocumentNotification $notifier;
    private DocumentUploader $uploader;
    private DocumentWorkflow $workflow;
    private DocumentVersioning $versioning;

    public function __construct() {
        $this->db         = Database::getInstance();
        $this->auth       = new Auth();
        $this->notifier   = new DocumentNotification($this->db);
        $this->uploader   = new DocumentUploader($this->db, $this->auth, $this->notifier);
        $this->workflow   = new DocumentWorkflow($this->db, $this->auth, $this->notifier);
        $this->versioning = new DocumentVersioning($this->db, $this->auth, $this->notifier);
    }

    // ── Upload Delegations ─────────────────────────────────
    public function upload(array $data, array $file): array {
        return $this->uploader->upload($data, $file);
    }

    public function uploadBatch(array $data, array $files): array {
        return $this->uploader->uploadBatch($data, $files);
    }

    // ── Workflow Delegations ───────────────────────────────
    public function updateStatus(int $id, string $status, ?string $remarks = null): array {
        return $this->workflow->updateStatus($id, $status, $remarks);
    }

    public function cancelSubmission(int $id): array {
        return $this->workflow->cancelSubmission($id);
    }

    // ── Versioning Delegations ─────────────────────────────
    public function uploadVersion(int $id, array $file, ?string $notes = null, ?string $newStatus = null): array {
        $doc = $this->getById($id);
        return $this->versioning->uploadVersion($id, $file, $notes, $newStatus, $doc);
    }

    // ── Get All (with filters & search) ────────────────────
    public function getAll(array $filters = [], int $page = 1, int $perPage = 15): array {
        $where  = ['1=1'];
        $params = [];

        $filteringArchived = ($filters['status'] ?? '') === 'archived';

        if (!empty($filters['status'])) {
            if ($filteringArchived) {
                // Show archived-status docs OR soft-deleted docs (they're all "archived")
                $where[] = "(d.status = 'archived' OR d.deleted_at IS NOT NULL)";
            } else {
                $where[] = 'd.status = ?'; $params[] = $filters['status'];
            }
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'd.category_id = ?'; $params[] = $filters['category_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'd.department_id = ?'; $params[] = $filters['department_id'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'd.priority = ?'; $params[] = $filters['priority'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ? OR d.reference_number LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(d.created_at) >= ?'; $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(d.created_at) <= ?'; $params[] = $filters['date_to'];
        }
        if (!empty($filters['uploader_id'])) {
            $where[] = 'd.uploaded_by = ?'; $params[] = (int)$filters['uploader_id'];
        }

        $userRole = $_SESSION['user_role'] ?? 'staff';
        $userDept = (int)($_SESSION['user_dept'] ?? 0);
        $userId   = (int)($_SESSION['user_id'] ?? 0);

        if ($userRole === 'viewer') {
            $where[] = "d.status = 'approved'";
        } elseif ($userRole === 'staff') {
            $where[] = 'd.department_id = ?';
            $params[] = $userDept;
        } elseif ($userRole === 'department_head') {
            $where[] = 'd.department_id = ?';
            $params[] = $userDept;
        }

        if ($filteringArchived) {
            // No deleted_at filter — we want both soft-deleted and status=archived rows
        } else {
            // Normal listing: hide soft-deleted AND hide archived-status docs
            $where[] = 'd.deleted_at IS NULL';
            $where[] = "d.status != 'archived'";
        }
        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM documents d WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $allowedSort = ['created_at', 'title', 'status', 'priority', 'file_size', 'expires_at'];
        $sortCol = in_array($filters['sort'] ?? '', $allowedSort, true) ? $filters['sort'] : 'created_at';
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT d.*, c.name AS category_name, c.color AS category_color,
                   dep.name AS department_name,
                   CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                   u.role AS uploader_role
            FROM documents d
            LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN departments dep ON d.department_id = dep.id
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE $whereClause
            ORDER BY d.{$sortCol} {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $docs = $stmt->fetchAll();

        return [
            'documents'    => $docs,
            'total'        => $total,
            'pages'        => ceil($total / $perPage),
            'current_page' => $page,
            'per_page'     => $perPage
        ];
    }

    // ── Get Single ─────────────────────────────────────────
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT d.*, c.name AS category_name, c.color AS category_color,
                   dep.name AS department_name,
                   CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                   u.role AS uploader_role
            FROM documents d
            LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN departments dep ON d.department_id = dep.id
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = ? AND d.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) return null;

        $role = $_SESSION['user_role'] ?? 'staff';
        $deptId = (int)($_SESSION['user_dept'] ?? 0);
        if ($role === 'viewer') {
            if ($doc['status'] !== 'approved') return null;
        } elseif ($role !== 'admin') {
            if ((int)($doc['department_id'] ?? 0) !== $deptId) return null;
        }

        $this->auth->logActivity($_SESSION['user_id'], $id, 'view', "Viewed document: {$doc['title']}");

        $doc['versions']  = $this->getVersions($id);
        $doc['comments']  = $this->getComments($id);
        $doc['approvals'] = $this->getApprovals($id);
        return $doc;
    }

    // ── Update Document Metadata ────────────────────────────
    public function update(int $id, array $data): array {
        $doc = $this->getById($id);
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
        if ($userRole !== 'admin' && (int)$doc['uploaded_by'] !== $userId && !$isDeptHead) {
            return ['success' => false, 'message' => 'Permission denied. Department heads may edit staff documents in their department, but administrator-owned documents are protected.'];
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return ['success' => false, 'message' => 'Title is required.'];
        }

        $updates = [
            'title'       => $title,
            'description' => $data['description'] ?? $doc['description'],
            'category_id' => $data['category_id'] ?? $doc['category_id'],
            'priority'    => $data['priority'] ?? $doc['priority'],
            'tags'        => $data['tags'] ?? $doc['tags'],
            'expires_at'  => $data['expires_at'] ?? $doc['expires_at'],
        ];

        if ($userRole === 'admin' && isset($data['department_id'])) {
            $updates['department_id'] = $data['department_id'];
        }

        $setParts = [];
        $params   = [];
        foreach ($updates as $col => $val) {
            $setParts[] = "$col = ?";
            $params[]   = $val;
        }
        $params[] = $id;

        $sql = "UPDATE documents SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
        $this->db->prepare($sql)->execute($params);

        $this->auth->logActivity($userId, $id, 'edit', "Updated metadata for: {$title}");
        return ['success' => true];
    }

    // ── Delete / Trash / Lifecycle ─────────────────────────
    public function delete(int $id): array {
        $doc = $this->getById($id);
        if (!$doc) return ['success' => false, 'message' => 'Document not found.'];
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['user_role'] ?? 'staff';
        $userDept = (int)($_SESSION['user_dept'] ?? 0);
        $isAdminOwned = (($doc['uploader_role'] ?? '') === 'admin');
        if ($isAdminOwned && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'This document was uploaded by an administrator and is protected from changes.'];
        }
        $isDeptHead = ($userRole === 'department_head'
                       && (!empty($doc['department_id']) && (int)$doc['department_id'] === $userDept)
                       && (int)$doc['uploaded_by'] !== $userId);
        if ((int)$doc['uploaded_by'] !== $userId && $userRole !== 'admin' && !$isDeptHead) {
            return ['success' => false, 'message' => 'Permission denied. Department heads may archive staff documents in their department, but administrator-owned documents are protected.'];
        }

        $this->db->prepare("UPDATE documents SET deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([(int)$_SESSION['user_id'], $id]);
        $this->auth->logActivity($_SESSION['user_id'], $id, 'delete', "Archived document: {$doc['title']}");
        return ['success' => true];
    }

    public function restore(int $id): array {
        $stmt = $this->db->prepare("SELECT d.*, u.role AS uploader_role FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ? AND (d.deleted_at IS NOT NULL OR d.status = 'archived')");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) return ['success' => false, 'message' => 'Archived document not found.'];
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['user_role'] ?? 'staff';
        $userDept = (int)($_SESSION['user_dept'] ?? 0);
        $isAdminOwned = (($doc['uploader_role'] ?? '') === 'admin');
        if ($isAdminOwned && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'This document was uploaded by an administrator and is protected from changes.'];
        }
        $isDeptHead = ($userRole === 'department_head'
                       && (!empty($doc['department_id']) && (int)$doc['department_id'] === $userDept)
                       && (int)$doc['uploaded_by'] !== $userId);
        if ((int)$doc['uploaded_by'] !== $userId && $userRole !== 'admin' && !$isDeptHead) {
            return ['success' => false, 'message' => 'Permission denied.'];
        }
        // Restore: clear soft-delete fields and reset status to draft
        $this->db->prepare("UPDATE documents SET deleted_at = NULL, deleted_by = NULL, status = 'draft', updated_at = NOW() WHERE id = ?")->execute([$id]);
        $this->auth->logActivity($_SESSION['user_id'], $id, 'restore', "Restored document: {$doc['title']}");
        return ['success' => true];
    }

    public function permanentDelete(int $id): array {
        if (($_SESSION['user_role'] ?? '') !== 'admin') return ['success' => false, 'message' => 'Only administrators can permanently delete documents.'];
        $stmt = $this->db->prepare("SELECT * FROM documents WHERE id = ? AND (deleted_at IS NOT NULL OR status = 'archived')");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) return ['success' => false, 'message' => 'Archived document not found.'];

        $paths = [$doc['file_path']];
        $v = $this->db->prepare("SELECT file_path FROM document_versions WHERE document_id = ?");
        $v->execute([$id]);
        foreach ($v->fetchAll(PDO::FETCH_COLUMN) as $path) $paths[] = $path;
        foreach (array_unique($paths) as $path) {
            $fullPath = __DIR__ . '/../' . ltrim($path, '/');
            if (is_file($fullPath)) @unlink($fullPath);
        }
        $this->db->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
        $this->auth->logActivity($_SESSION['user_id'], null, 'permanent_delete', "Permanently deleted document: {$doc['title']}");
        return ['success' => true];
    }

    public function getDeleted(int $page = 1, int $perPage = 15): array {
        $role = $_SESSION['user_role'] ?? 'staff';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $dept = (int)($_SESSION['user_dept'] ?? 0);
        // Include both soft-deleted docs AND docs with status='archived'
        $where = "(d.deleted_at IS NOT NULL OR d.status = 'archived')"; $params = [];
        if ($role === 'staff' || $role === 'viewer') {
            $where .= ' AND d.uploaded_by = ?'; $params[] = $userId;
        } elseif ($role === 'department_head') {
            $where .= ' AND d.department_id = ?'; $params[] = $dept;
        }
        $offset = ($page - 1) * $perPage;
        $count = $this->db->prepare("SELECT COUNT(*) FROM documents d WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $stmt = $this->db->prepare("
            SELECT d.*, dep.name AS department_name, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name,
                   u.role AS uploader_role, CONCAT(du.first_name,' ',du.last_name) AS deleted_by_name
            FROM documents d
            LEFT JOIN departments dep ON d.department_id = dep.id
            LEFT JOIN users u ON d.uploaded_by = u.id
            LEFT JOIN users du ON d.deleted_by = du.id
            WHERE $where ORDER BY COALESCE(d.deleted_at, d.updated_at) DESC LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        return ['documents'=>$stmt->fetchAll(), 'total'=>$total, 'pages'=>ceil($total/$perPage), 'current_page'=>$page];
    }

    // ── Export CSV ─────────────────────────────────────────
    public function exportCsv(array $filters = []): void {
        $result = $this->getAll($filters, 1, 100000);
        $filename = 'documents-export-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Reference No.','Title','Category','Department','Status','Priority','Version','Uploaded By','Created Date','Expiry Date','Tags']);
        foreach ($result['documents'] as $d) {
            $createdDate = !empty($d['created_at']) ? date('m/d/Y', strtotime($d['created_at'])) : '';
            $expiryDate  = !empty($d['expires_at'])  ? date('m/d/Y', strtotime($d['expires_at']))  : '';
            fputcsv($out, [
                $d['reference_number'],
                $d['title'],
                $d['category_name'],
                $d['department_name'],
                $d['status'],
                $d['priority'],
                'v' . $d['version'],
                $d['uploaded_by_name'],
                $createdDate,
                $expiryDate,
                $d['tags'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ── Sub-resource Queries ───────────────────────────────
    private function getVersions(int $docId): array {
        $stmt = $this->db->prepare("
            SELECT dv.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
            FROM document_versions dv LEFT JOIN users u ON dv.uploaded_by = u.id
            WHERE dv.document_id = ? ORDER BY dv.version DESC
        ");
        $stmt->execute([$docId]);
        return $stmt->fetchAll();
    }

    private function getComments(int $docId): array {
        $stmt = $this->db->prepare("
            SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.role AS user_role
            FROM comments c LEFT JOIN users u ON c.user_id = u.id
            WHERE c.document_id = ? ORDER BY c.created_at ASC
        ");
        $stmt->execute([$docId]);
        return $stmt->fetchAll();
    }

    private function getApprovals(int $docId): array {
        $stmt = $this->db->prepare("
            SELECT aw.*, CONCAT(u.first_name,' ',u.last_name) AS approver_name, u.role AS approver_role
            FROM approval_workflows aw LEFT JOIN users u ON aw.approver_id = u.id
            WHERE aw.document_id = ? ORDER BY aw.step_order ASC, aw.created_at DESC
        ");
        $stmt->execute([$docId]);
        return $stmt->fetchAll();
    }

    // ── Dashboard Statistics ───────────────────────────────
    public function getStats(): array {
        $role   = $_SESSION['user_role']  ?? 'staff';
        $userId = (int)($_SESSION['user_id']   ?? 0);
        $deptId = (int)($_SESSION['user_dept'] ?? 0);

        $where  = 'deleted_at IS NULL';
        $params = [];
        if ($role === 'viewer') {
            $where .= " AND status = 'approved'";
        } elseif ($role !== 'admin') {
            $where .= ' AND department_id = ?';
            $params[] = $deptId;
        }

        $stmt = $this->db->prepare("SELECT status, COUNT(*) AS cnt FROM documents WHERE $where GROUP BY status");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $stats = [
            'total'             => 0,
            'draft'             => 0,
            'pending_review'    => 0,
            'under_review'      => 0,
            'approved'          => 0,
            'rejected'          => 0,
            'archived'          => 0,
            'my_total'          => 0,
            'my_pending'        => 0,
            'my_approved'       => 0,
            'my_draft'          => 0,
            'my_rejected'       => 0,
            'actionable_pending'=> 0,
        ];

        foreach ($rows as $r) {
            $key = $r['status'];
            if (isset($stats[$key])) $stats[$key] = (int)$r['cnt'];
            $stats['total'] += (int)$r['cnt'];
        }

        $expWhere = $where === 'deleted_at IS NULL'
            ? "expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            : "($where) AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        $expStmt = $this->db->prepare("SELECT COUNT(*) FROM documents WHERE $expWhere");
        $expStmt->execute($params);
        $stats['expiring_soon'] = (int)$expStmt->fetchColumn();

        if ($userId > 0) {
            $myStmt = $this->db->prepare("
                SELECT status, COUNT(*) AS cnt 
                FROM documents 
                WHERE deleted_at IS NULL AND uploaded_by = ? 
                GROUP BY status
            ");
            $myStmt->execute([$userId]);
            $myRows = $myStmt->fetchAll();
            foreach ($myRows as $mr) {
                $cnt = (int)$mr['cnt'];
                $stats['my_total'] += $cnt;
                if ($mr['status'] === 'approved') {
                    $stats['my_approved'] += $cnt;
                } elseif ($mr['status'] === 'draft') {
                    $stats['my_draft'] += $cnt;
                } elseif ($mr['status'] === 'rejected') {
                    $stats['my_rejected'] += $cnt;
                } elseif (in_array($mr['status'], ['pending_review', 'under_review'], true)) {
                    $stats['my_pending'] += $cnt;
                }
            }
        }

        if ($role === 'admin') {
            $actStmt = $this->db->query("
                SELECT COUNT(*) FROM documents d 
                LEFT JOIN users u ON d.uploaded_by = u.id 
                WHERE d.deleted_at IS NULL 
                  AND d.status IN ('pending_review', 'under_review') 
                  AND u.role <> 'admin'
            ");
            $stats['actionable_pending'] = (int)$actStmt->fetchColumn();
        } elseif ($role === 'department_head') {
            $actStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT d.id) FROM documents d 
                LEFT JOIN approval_workflows aw ON aw.document_id = d.id AND aw.approver_id = ? 
                LEFT JOIN users u ON u.id = d.uploaded_by 
                WHERE d.deleted_at IS NULL 
                  AND d.department_id = ? 
                  AND u.role <> 'admin' 
                  AND d.status IN ('pending_review', 'under_review') 
                  AND (aw.id IS NULL OR aw.status = 'pending')
            ");
            $actStmt->execute([$userId, $deptId]);
            $stats['actionable_pending'] = (int)$actStmt->fetchColumn();
        } else {
            $stats['actionable_pending'] = 0;
        }

        return $stats;
    }
}
