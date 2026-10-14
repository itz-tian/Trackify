<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth     = new Auth();
$auth->requireLogin();
$docClass = new Document();
$db       = Database::getInstance();

$id  = (int)($_GET['id'] ?? 0);
$doc = $docClass->getById($id);
if (!$doc) { header('Location: ' . APP_URL . '/documents.php?error=notfound'); exit; }

$userRole   = $_SESSION['user_role'] ?? 'staff';
$userId     = (int)$_SESSION['user_id'];
$isOwner    = ($doc['uploaded_by'] == $userId);
$isAdmin    = ($userRole === 'admin');
$isDeptHead = ($userRole === 'department_head' && !empty($doc['department_id']) && (int)$doc['department_id'] === (int)($_SESSION['user_dept'] ?? 0));
$isAdminOwned = (($doc['uploader_role'] ?? '') === 'admin');


$checkWf = $db->prepare("SELECT * FROM approval_workflows WHERE document_id = ? AND approver_id = ?");
$checkWf->execute([$id, $userId]);
$myWorkflow = $checkWf->fetch();



$canApprove = ($isAdmin || (!$isAdminOwned && !$isOwner && ($isDeptHead || !empty($myWorkflow))));

$categories  = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$canEdit    = ($isAdmin || (!$isAdminOwned && ($isOwner || ($isDeptHead && (int)$doc['uploaded_by'] !== $userId))));
$canDelete  = ($isAdmin || (!$isAdminOwned && ($isOwner || ($isDeptHead && (int)$doc['uploaded_by'] !== $userId))));
$canComment = ($isAdmin || !$isAdminOwned);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = APP_URL . '/document_view.php?id=' . $id;

    if (isset($_POST['approval_action'])) {
        $action  = $_POST['approval_action'];
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$canApprove) {
            flash_set('error', 'You do not have permission to approve or reject this document.');
        } elseif ($action === 'reject' && $remarks === '') {
            flash_set('error', 'Please provide a reason or remarks when rejecting a document.');
        } else {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $res = $docClass->updateStatus($id, $newStatus, $remarks);
            flash_result($res, $action === 'approve' ? 'Document has been successfully approved.' : 'Document has been rejected.');
        }
        redirect_to($self);
    }

    if (isset($_POST['comment'])) {
        $comment = trim($_POST['comment']);
        if (!$canComment) {
            flash_set('error', 'This administrator-owned document is protected from comments or other changes.');
        } elseif ($comment !== '') {
            $db->prepare("INSERT INTO comments (document_id, user_id, comment) VALUES (?,?,?)")
               ->execute([$id, $userId, $comment]);
            $auth->logActivity($userId, $id, 'comment', 'Added a comment');
            flash_set('success', 'Comment posted successfully.');
        }
        redirect_to($self);
    }

    if (isset($_POST['new_status'])) {
        $remarks = trim($_POST['remarks'] ?? '');
        $res = $docClass->updateStatus($id, $_POST['new_status'], $remarks ?: null);
        flash_result($res, 'Document status updated successfully.');
        redirect_to($self);
    }

    if (isset($_POST['upload_version'])) {
        if (!empty($_FILES['new_version_file']['name'])) {
            $notes        = trim($_POST['version_notes'] ?? '');
            $newVerStatus = !empty($_POST['version_status']) ? $_POST['version_status'] : null;
            $res = $docClass->uploadVersion($id, $_FILES['new_version_file'], $notes, $newVerStatus);
            flash_result($res, 'New document version (v' . ($res['new_version'] ?? '') . ') uploaded successfully.');
        } else {
            flash_set('error', 'Please select a file to upload as the new version.');
        }
        redirect_to($self);
    }

    if (isset($_POST['edit_document'])) {
        $res = $docClass->update($id, $_POST);
        flash_result($res, 'Document details updated successfully.');
        redirect_to($self);
    }

    if (isset($_POST['delete_document'])) {
        $res = $docClass->delete($id);
        if ($res['success']) {
            flash_set('success', 'Document deleted.');
            redirect_to(APP_URL . '/documents.php');
        }
        flash_set('error', $res['message']);
        redirect_to($self);
    }

    redirect_to($self);
}


['success' => $success, 'error' => $error] = flash_pull();

$statusColors = [
    'draft'=>'badge-draft','pending_review'=>'badge-pending','under_review'=>'badge-review',
    'approved'=>'badge-approved','rejected'=>'badge-rejected','archived'=>'badge-archived'
];

$fileIcon = [
    'pdf'  => ['file-text', '#ff3b30', 'rgba(255, 59, 48, 0.12)'],
    'doc'  => ['file-text', '#0071e3', 'rgba(0, 113, 227, 0.12)'],
    'docx' => ['file-text', '#0071e3', 'rgba(0, 113, 227, 0.12)'],
    'xls'  => ['file-spreadsheet', '#34c759', 'rgba(52, 199, 89, 0.12)'],
    'xlsx' => ['file-spreadsheet', '#34c759', 'rgba(52, 199, 89, 0.12)'],
    'ppt'  => ['presentation', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
    'pptx' => ['presentation', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
    'png'  => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'jpg'  => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'jpeg' => ['file-image', '#af52de', 'rgba(175, 82, 222, 0.12)'],
    'txt'  => ['file-text', '#8e8e93', 'rgba(142, 142, 147, 0.12)'],
    'zip'  => ['file-archive', '#ff9500', 'rgba(255, 149, 0, 0.12)'],
];
$ext  = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
$icon = $fileIcon[$ext] ?? ['file', '#8e8e93', 'rgba(142, 142, 147, 0.12)'];

$diskFile = realpath(__DIR__ . '/' . ltrim($doc['file_path'], '/\\'));
$hasDiskFile = ($diskFile && is_file($diskFile));

include __DIR__ . '/includes/header.php';
?>

<?php include __DIR__ . '/includes/document/banners.php'; ?>

<div class="content-split">
    
    <div>
        <?php include __DIR__ . '/includes/document/header_card.php'; ?>
        <?php include __DIR__ . '/includes/document/preview_renderers.php'; ?>
        <?php include __DIR__ . '/includes/document/comments.php'; ?>
    </div>

    
    <?php include __DIR__ . '/includes/document/sidebar.php'; ?>
</div>


<?php include __DIR__ . '/includes/document/modals.php'; ?>
<?php include __DIR__ . '/includes/document/modal_scripts.php'; ?>


<?php include __DIR__ . '/includes/document/preview_scripts.php'; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
