<?php
/**
 * Trackify - Automated Document Expiry Reminders
 *
 * Run this file once per day from Windows Task Scheduler, cron, or another
 * server scheduler. It creates in-app notifications for documents that are
 * approaching or have reached their expiry date.
 *
 * No database schema change is required.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

// Reminder checkpoints. The script runs daily, so each checkpoint is sent
// once per recipient/document combination.
$checkpoints = [30, 14, 7, 3, 1, 0];

// Only active, non-archived documents with an expiry date are relevant.
$stmt = $db->query("
    SELECT d.id, d.title, d.reference_number, d.expires_at, d.department_id,
           d.uploaded_by,
           CONCAT(u.first_name, ' ', u.last_name) AS uploader_name
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE d.expires_at IS NOT NULL
      AND d.deleted_at IS NULL
      AND d.status <> 'archived'
      AND d.expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY d.expires_at ASC
");
$documents = $stmt->fetchAll();

$created = 0;
$skipped = 0;

foreach ($documents as $doc) {
    $expires = new DateTime($doc['expires_at']);
    $today   = new DateTime('today');
    $daysRemaining = (int)$today->diff($expires)->format('%r%a');

    // Choose the nearest reminder checkpoint that has been reached.
    // Example: 10 days remaining -> 7-day reminder.
    $checkpoint = null;
    foreach ($checkpoints as $days) {
        if ($daysRemaining <= $days) {
            $checkpoint = $days;
            break;
        }
    }
    if ($checkpoint === null) {
        continue;
    }

    if ($daysRemaining < 0) {
        $checkpoint = 0;
    }

    $type = 'expiry_reminder_' . $checkpoint;

    if ($daysRemaining > 0) {
        $dayText = $daysRemaining === 1 ? '1 day' : $daysRemaining . ' days';
        $title = 'Document Expiry Reminder';
        $message = "Document '{$doc['title']}' (" . ($doc['reference_number'] ?: 'No reference number') . ") expires in {$dayText} on " . date('M d, Y', strtotime($doc['expires_at'])) . ".";
    } elseif ($daysRemaining === 0) {
        $title = 'Document Expires Today';
        $message = "Document '{$doc['title']}' (" . ($doc['reference_number'] ?: 'No reference number') . ") expires today, " . date('M d, Y', strtotime($doc['expires_at'])) . ".";
    } else {
        $daysExpired = abs($daysRemaining);
        $dayText = $daysExpired === 1 ? '1 day' : $daysExpired . ' days';
        $title = 'Document Expired';
        $message = "Document '{$doc['title']}' (" . ($doc['reference_number'] ?: 'No reference number') . ") expired {$dayText} ago on " . date('M d, Y', strtotime($doc['expires_at'])) . ".";
    }

    // Notify the uploader plus the department head responsible for the
    // document. System Admins also receive reminders for central monitoring.
    $recipientIds = [];
    if (!empty($doc['uploaded_by'])) {
        $recipientIds[] = (int)$doc['uploaded_by'];
    }

    if (!empty($doc['department_id'])) {
        $dh = $db->prepare("SELECT id FROM users WHERE role = 'department_head' AND department_id = ? AND is_active = 1");
        $dh->execute([(int)$doc['department_id']]);
        foreach ($dh->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $recipientIds[] = (int)$id;
        }
    }

    $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $id) {
        $recipientIds[] = (int)$id;
    }

    $recipientIds = array_values(array_unique(array_filter($recipientIds)));

    foreach ($recipientIds as $userId) {
        // Prevent duplicate reminders if the scheduled task runs more than once.
        $dup = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND document_id = ? AND type = ? LIMIT 1");
        $dup->execute([$userId, (int)$doc['id'], $type]);
        if ($dup->fetchColumn()) {
            $skipped++;
            continue;
        }

        $insert = $db->prepare("
            INSERT INTO notifications (user_id, document_id, type, title, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->execute([$userId, (int)$doc['id'], $type, $title, $message]);
        $created++;
    }
}

echo "Trackify expiry reminder job completed. Notifications created: {$created}; duplicates skipped: {$skipped}." . PHP_EOL;
