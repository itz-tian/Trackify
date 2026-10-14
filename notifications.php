<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
$db   = Database::getInstance();


$returnTo = $_GET['return_to'] ?? '';
$safeReturnUrl = APP_URL . '/dashboard.php';
if (!empty($returnTo)) {
    if (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) {
        $safeReturnUrl = $returnTo;
    } elseif (str_starts_with($returnTo, APP_URL)) {
        $safeReturnUrl = $returnTo;
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '' && str_contains($ref, $host) && !str_contains($ref, 'notifications.php')) {
        $safeReturnUrl = $ref;
    }
}


if (isset($_GET['mark_read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
    $redirectUrl = !empty($returnTo) ? ('notifications.php?return_to=' . urlencode($returnTo)) : 'notifications.php';
    header('Location: ' . $redirectUrl); exit;
}


if (isset($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['read'], $_SESSION['user_id']]);
}

$notifs = $db->prepare("
    SELECT n.*, d.title AS doc_title
    FROM notifications n
    LEFT JOIN documents d ON n.document_id = d.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 60
");
$notifs->execute([$_SESSION['user_id']]);
$notifications = $notifs->fetchAll();

$typeIcons = [
    'review_request' => ['list-todo',      '#d97706', 'rgba(217,119,6,0.12)'],
    'status_update'  => ['arrow-left-right','#2563eb', 'rgba(37,99,235,0.12)'],
    'comment'        => ['message-square',  '#7c3aed', 'rgba(124,58,237,0.12)'],
    'expiry_reminder_30' => ['calendar-clock', '#ff9500', 'rgba(255,149,0,0.12)'],
    'expiry_reminder_14' => ['calendar-clock', '#ff9500', 'rgba(255,149,0,0.12)'],
    'expiry_reminder_7'  => ['calendar-clock', '#ff9500', 'rgba(255,149,0,0.12)'],
    'expiry_reminder_3'  => ['calendar-clock', '#ff3b30', 'rgba(255,59,48,0.12)'],
    'expiry_reminder_1'  => ['alarm-clock',   '#ff3b30', 'rgba(255,59,48,0.12)'],
    'expiry_reminder_0'  => ['triangle-alert', '#ff3b30', 'rgba(255,59,48,0.12)'],
];

include __DIR__ . '/includes/header.php';
?>

<div style="max-width:760px;margin:0 auto;">
    <div class="flex justify-between items-center mb-16 flex-wrap gap-10">
        <div class="flex items-center gap-12">
            <a href="<?= htmlspecialchars($safeReturnUrl) ?>" class="btn btn-outline btn-sm" id="notifBackBtn" onclick="return handleNotifBack(event, '<?= htmlspecialchars(addslashes($safeReturnUrl)) ?>');" title="Back to previous page" style="display:inline-flex; align-items:center; gap:6px; font-weight:500;">
                <i data-lucide="arrow-left" style="width:15px; height:15px;"></i>
                <span>Back</span>
            </a>
            <h2 style="font-size:18px;font-weight:700;margin:0;letter-spacing:-0.015em;">Notifications</h2>
        </div>
        <?php if (!empty($notifications)): ?>
        <a href="notifications.php?mark_read=1<?= $returnTo ? '&return_to='.urlencode($returnTo) : '' ?>" class="btn btn-outline btn-sm"><i data-lucide="check-check"></i> Mark All Read</a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="card">
        <div class="card-body text-center text-muted" style="padding:64px;">
            <i data-lucide="bell-off" style="font-size:40px;color:#e2e8f0;display:block;margin-bottom:16px;"></i>
            No notifications yet.
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="overflow:hidden;">
        <?php foreach ($notifications as $notif): ?>
        <?php $ico = $typeIcons[$notif['type']] ?? ['info','#64748b','rgba(100,116,139,0.12)']; ?>
        <?php 
            $itemLink = $notif['document_id'] 
                ? APP_URL . '/document_view.php?id=' . $notif['document_id'] . '&read=' . $notif['id'] 
                : 'notifications.php?read=' . $notif['id'] . ($returnTo ? '&return_to=' . urlencode($returnTo) : '');
        ?>
        <a href="<?= $itemLink ?>"
           class="notif-item<?= $notif['is_read'] ? '' : ' notif-item-unread' ?>"
           style="display:flex;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border-hairline);text-decoration:none;color:inherit;transition:background .14s ease;">
            <div style="width:40px;height:40px;border-radius:10px;background:<?= $ico[2] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="<?= $ico[0] ?>" style="color:<?= $ico[1] ?>;font-size:16px;"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:13.5px;font-weight:<?= $notif['is_read'] ? 400 : 600 ?>;color:var(--text-primary);"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="text-sm text-muted" style="margin-top:3px;"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="text-sm text-muted" style="margin-top:4px;white-space:nowrap;"><i data-lucide="clock"></i> <?= date('M d, Y g:i a', strtotime($notif['created_at'])) ?></div>
            </div>
            <?php if (!$notif['is_read']): ?>
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:8px;"></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function handleNotifBack(e, fallbackUrl) {
    if (window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1 && document.referrer !== window.location.href) {
        e.preventDefault();
        window.history.back();
        return false;
    }
    return true;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

