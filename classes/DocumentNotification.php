<?php
require_once __DIR__ . '/../config/database.php';

class DocumentNotification {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function notifyApprovers(int $docId): void {
        $doc = $this->db->prepare("SELECT title, department_id, uploaded_by FROM documents WHERE id = ?");
        $doc->execute([$docId]);
        $docData = $doc->fetch();
        if (!$docData) return;
        $uploaderId = (int)$docData['uploaded_by'];
        $deptId = !empty($docData['department_id']) ? (int)$docData['department_id'] : null;

        // System Admin has full approval authority. Department heads also receive
        // approval requests for documents belonging to their department.
        $approvers = $this->db->prepare("
            SELECT id FROM users
            WHERE is_active = 1
              AND id <> ?
              AND (role = 'admin' OR (role = 'department_head' AND department_id = ?))
        ");
        $approvers->execute([$uploaderId, $deptId]);
        $approverList = $approvers->fetchAll();

        // Get already assigned workflows for this document
        $existing = $this->db->prepare("SELECT id, approver_id, status FROM approval_workflows WHERE document_id = ?");
        $existing->execute([$docId]);
        $existingWorkflows = [];
        foreach ($existing->fetchAll() as $row) {
            $existingWorkflows[(int)$row['approver_id']] = $row;
        }

        foreach ($approverList as $approver) {
            $apId = (int)$approver['id'];
            if (isset($existingWorkflows[$apId])) {
                // If previous workflow was concluded (e.g. rejected or skipped), reset it to pending for the new review cycle
                if ($existingWorkflows[$apId]['status'] !== 'pending') {
                    $this->db->prepare("
                        UPDATE approval_workflows 
                        SET status = 'pending', remarks = NULL, acted_at = NULL 
                        WHERE id = ?
                    ")->execute([$existingWorkflows[$apId]['id']]);
                }
            } else {
                $this->db->prepare("
                    INSERT INTO approval_workflows (document_id, approver_id, step_order, status)
                    VALUES (?, ?, 1, 'pending')
                ")->execute([$docId, $apId]);
            }

            $this->db->prepare("
                INSERT INTO notifications (user_id, document_id, type, title, message)
                VALUES (?, ?, 'review_request', 'Document Pending Review', ?)
            ")->execute([$apId, $docId, "Document '{$docData['title']}' requires your review."]);
        }
    }

    public function notifyUploader(int $docId, string $status, ?string $remarks): void {
        $doc = $this->db->prepare("SELECT title, uploaded_by FROM documents WHERE id = ?");
        $doc->execute([$docId]);
        $docData = $doc->fetch();
        if (!$docData) return;

        $msg = match($status) {
            'approved' => "Your document '{$docData['title']}' has been approved.",
            'rejected' => "Your document '{$docData['title']}' was rejected." . ($remarks ? " Reason: $remarks" : ''),
            default    => "Your document '{$docData['title']}' status changed to: $status.",
        };

        $this->db->prepare("
            INSERT INTO notifications (user_id, document_id, type, title, message)
            VALUES (?, ?, 'status_update', 'Document Status Update', ?)
        ")->execute([$docData['uploaded_by'], $docId, $msg]);
    }
}
