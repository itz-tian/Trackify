<?php

defined('APP_NAME') or exit('Direct script access denied.');
?>


<div class="card">
    <div class="card-header">
        <span class="card-title"><i data-lucide="message-square" style="color:var(--apple-blue); margin-right:8px;"></i>Discussion (<?= count($doc['comments']) ?>)</span>
    </div>
    <div class="card-body">
        <?php if (empty($doc['comments'])): ?>
        <p class="text-muted text-center" style="padding:24px 0;">No comments yet. Leave a remark below.</p>
        <?php else: ?>
        <?php foreach ($doc['comments'] as $c): ?>
        <div style="display:flex; gap:12px; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid rgba(0,0,0,0.04);">
            <div class="user-avatar" title="<?= htmlspecialchars($c['user_name']) ?>">
                <i data-lucide="user-round"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:12.5px; font-weight:600;">
                    <?= htmlspecialchars($c['user_name']) ?>
                    <span class="text-sm text-muted" style="font-weight:400; margin-left:6px; font-size:11px;">
                        <?= date('M d, Y g:i a', strtotime($c['created_at'])) ?>
                    </span>
                </div>
                <div style="font-size:13px; margin-top:4px; line-height:1.5; color:var(--text-primary);">
                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canComment): ?>
        <form method="POST" style="margin-top:12px;">
            <?= csrf_field() ?>
            <div class="form-group">
                <textarea name="comment" rows="3" placeholder="Leave a comment or review note…" required></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="send"></i> Post Comment</button>
            </div>
        </form>
        <?php else: ?>
        <div class="alert alert-info" style="margin-top:12px;"><i data-lucide="lock"></i> This administrator-owned document is protected. Comments and changes are disabled for non-administrators.</div>
        <?php endif; ?>
    </div>
</div>
