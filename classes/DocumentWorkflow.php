<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentNotification.php';

class DocumentWorkflow {
    private PDO $db;
    private Auth $auth;
    private DocumentNotification $notifier;

    public function __construct(PDO $db, Auth $auth, DocumentNotification $notifier) {
        $this->db       = $db;
        $this->auth     = $auth;
        $this->notifier = $notifier;
    }

    public function updateStatus(int $id, string $status, ?string $remarks = null): array {
        $allowed = ['draft','pending_review','under_review','approved','rejected','archived'];
        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid status provided.'];
        }

        $docStmt = $this->db->prepare("SELECT d.id, d.status, d.title, d.uploaded_by, d.department_id, u.role AS uploader_role FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ? AND d.deleted_at IS NULL");
        $docStmt->execute([$id]);
        $currentDoc = $docStmt->fetch();
        if (!$currentDoc) {
            return ['success' => false, 'message' => 'Document not found or has been deleted.'];
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $userRole      = $_SESSION['user_role'] ?? 'staff';
        $userDept      = (int)($_SESSION['user_dept'] ?? 0);
        $approverName  = $_SESSION['user_name'] ?? 'an authorized reviewer';

        $isAdmin    = ($userRole === 'admin');
        $isDeptHead = ($userRole === 'department_head'
                       && (!empty($currentDoc['department_id']) && (int)$currentDoc['department_id'] === $userDept));
        $isOwner    = ((int)$currentDoc['uploaded_by'] === $currentUserId);
        $isAdminOwned = (($currentDoc['uploader_role'] ?? '') === 'admin');

        if ($isAdminOwned && !$isAdmin) {
            return ['success' => false, 'message' => 'This document was uploaded by an administrator and is protected from changes.'];
        }

        $isAssignedApprover = false;
        if (!$isAdmin && !$isDeptHead && $currentUserId > 0) {
            $apChk = $this->db->prepare("SELECT 1 FROM approval_workflows WHERE document_id = ? AND approver_id = ? LIMIT 1");
            $apChk->execute([$id, $currentUserId]);
            $isAssignedApprover = (bool)$apChk->fetchColumn();
        }

        if (in_array($status, ['approved', 'rejected', 'under_review'], true)) {
            if (!$isAdmin && $isOwner) {
                return ['success' => false, 'message' => 'You cannot approve or review your own uploaded document. Only a System Admin can approve it.'];
            }
            $permitted = $isAdmin || $isDeptHead || $isAssignedApprover;
        } else {
            $permitted = $isAdmin || $isDeptHead || $isOwner;
        }
        if ($userRole === 'viewer') $permitted = false;
        if ($status === 'approved' && !$isAdmin && $isOwner) $permitted = false;

        if (!$permitted) {
            return ['success' => false, 'message' => 'You do not have permission to change this document to that status.'];
        }

        $this->db->prepare("UPDATE documents SET status = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$status, $id]);

        $statusName = ucfirst(str_replace('_', ' ', $status));
        $this->auth->logActivity($currentUserId, $id, 'status_change', "Status changed to: {$statusName}" . ($remarks ? " (Remarks: {$remarks})" : ""));

        if (!empty($remarks)) {
            $this->db->prepare("INSERT INTO comments (document_id, user_id, comment) VALUES (?, ?, ?)")
                     ->execute([$id, $currentUserId, "[Status Changed to {$statusName}] " . trim($remarks)]);
        }

        if ($status === 'pending_review') {
            $this->notifier->notifyApprovers($id);
        }

        if (in_array($status, ['approved', 'rejected'], true)) {
            $checkWf = $this->db->prepare("SELECT id FROM approval_workflows WHERE document_id = ? AND approver_id = ?");
            $checkWf->execute([$id, $currentUserId]);
            $wfId = $checkWf->fetchColumn();

            if ($wfId) {
                $this->db->prepare("
                    UPDATE approval_workflows SET status = ?, remarks = ?, acted_at = NOW()
                    WHERE id = ?
                ")->execute([$status, $remarks, $wfId]);
            } else {
                $this->db->prepare("
                    INSERT INTO approval_workflows (document_id, approver_id, step_order, status, remarks, acted_at)
                    VALUES (?, ?, 1, ?, ?, NOW())
                ")->execute([$id, $currentUserId, $status, $remarks]);
            }

            $skipNote = ($status === 'approved')
                ? "Approval concluded (Approved by {$approverName})"
                : "Workflow concluded (Rejected by {$approverName})";

            $this->db->prepare("
                UPDATE approval_workflows 
                SET status = 'skipped', remarks = ?, acted_at = NOW()
                WHERE document_id = ? AND status = 'pending' AND approver_id != ?
            ")->execute([$skipNote, $id, $currentUserId]);

            $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE document_id = ? AND type = 'review_request'
            ")->execute([$id]);
        }

        $this->notifier->notifyUploader($id, $status, $remarks);
        return ['success' => true];
    }

    public function cancelSubmission(int $id): array {
        $stmt = $this->db->prepare("SELECT id, title, status, uploaded_by FROM documents WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $userId   = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['user_role'] ?? 'staff';

        if ((int)$doc['uploaded_by'] !== $userId && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'Only the uploader (or an administrator) can cancel this submission.'];
        }

        if (!in_array($doc['status'], ['pending_review', 'under_review'], true)) {
            return ['success' => false, 'message' => 'Only documents currently pending review can be cancelled.'];
        }

        $this->db->prepare("UPDATE documents SET status = 'draft', updated_at = NOW() WHERE id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM approval_workflows WHERE document_id = ?")->execute([$id]);
        $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE document_id = ? AND type = 'review_request'")->execute([$id]);
        $this->auth->logActivity($userId, $id, 'status_change', "Cancelled submission for review: {$doc['title']} (returned to Draft)");

        return ['success' => true];
    }
}
