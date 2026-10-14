<?php

defined('APP_NAME') or exit('Direct script access denied.');
?>

<?php if ($_GET['uploaded'] ?? false): ?>
<div class="alert alert-success"><i data-lucide="circle-check"></i> Document uploaded successfully!</div>
<?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i data-lucide="circle-alert"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($isAdminOwned && !$isAdmin): ?><div class="alert alert-info"><i data-lucide="shield-check"></i> Administrator-owned document: view and download only. Editing, archiving, version changes, approvals, and comments are protected.</div><?php endif; ?>


<?php if (in_array($doc['status'], ['pending_review', 'under_review'])): ?>
    <?php if ($canApprove): ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-orange); background: linear-gradient(90deg, rgba(255, 149, 0, 0.08) 0%, var(--bg-card) 100%);">
        <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-weight:700; font-size:15px; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                    <i data-lucide="stamp" style="color:var(--apple-orange);"></i> Approval Required
                </div>
                <div class="text-muted text-sm" style="margin-top:2px;">
                    This document was submitted for review. As an authorized approver, please review and record your decision.
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <form method="POST" style="display:inline-flex; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_action" value="approve">
                    <button type="submit" class="btn btn-success">
                        <i data-lucide="check"></i> Approve Document
                    </button>
                </form>
                <form method="POST" style="display:inline-flex; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_action" value="reject">
                    <input type="hidden" name="remarks" value="Rejected by approver.">
                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="x"></i> Reject Document
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: linear-gradient(90deg, var(--apple-blue-subtle) 0%, var(--bg-card) 100%);">
        <div class="card-body" style="display:flex; align-items:center; gap:12px;">
            <i data-lucide="clock" style="color:var(--apple-blue); font-size:18px;"></i>
            <div>
                <div style="font-weight:600; font-size:13.5px; color:var(--text-primary);">Pending Approval</div>
                <div class="text-muted text-sm">This document is currently awaiting review by the Department Head or Administration.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php elseif ($doc['status'] === 'draft' && ($isOwner || $isAdmin)): ?>
    <div class="card mb-16" style="border-left: 4px solid var(--apple-blue); background: linear-gradient(90deg, var(--apple-blue-subtle) 0%, var(--bg-card) 100%);">
        <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-weight:700; font-size:14px; color:var(--text-primary);">
                    <i data-lucide="file-pen" style="color:var(--apple-blue); margin-right:6px;"></i> Draft Document
                </div>
                <div class="text-muted text-sm" style="margin-top:2px;">
                    This document is saved as a draft. When ready, submit it to trigger the approval workflow.
                </div>
            </div>
            <form method="POST" data-confirm-title="Submit for Approval" data-confirm-message="Submit this draft to the Approval Queue?" data-confirm-text="Submit" data-confirm-icon="send" data-confirm-class="btn-primary" data-confirm-icon-main="send" data-confirm-tone="blue">
                <?= csrf_field() ?>
                <input type="hidden" name="new_status" value="pending_review">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send"></i> Submit for Approval
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
