<?php

defined('APP_NAME') or exit('Direct script access denied.');
?>


<div>
    
    <div class="card mb-16">
        <div class="card-header">
            <span class="card-title"><i data-lucide="stamp" style="color:var(--apple-orange); margin-right:6px;"></i>Approval Status</span>
            <span class="badge <?= $statusColors[$doc['status']] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span>
        </div>
        <div class="card-body" style="padding:16px 20px;">
            <?php if (empty($doc['approvals'])): ?>
                <?php if (in_array($doc['status'], ['pending_review', 'under_review'])): ?>
                <p class="text-sm" style="color:var(--apple-orange); margin-bottom:12px;">
                    <i data-lucide="circle-alert"></i> Awaiting review from department head or admin.
                </p>
                <?php if ($canApprove): ?>
                <div style="display:flex; gap:8px;">
                    <form method="POST" style="flex:1; margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="approval_action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                            <i data-lucide="check"></i> Approve
                        </button>
                    </form>
                    <form method="POST" style="flex:1; margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="approval_action" value="reject">
                        <input type="hidden" name="remarks" value="Rejected by approver.">
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                            <i data-lucide="x"></i> Reject
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-muted text-sm">No workflow records logged.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($doc['approvals'] as $ap): ?>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid rgba(0,0,0,0.04);">
                    <div style="width:28px; height:28px; border-radius:50%; background:rgba(0,0,0,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">
                        <?php $apIcon = match($ap['status']) {
                            'approved' => ['check', 'var(--apple-green)'],
                            'rejected' => ['x', 'var(--apple-red)'],
                            'skipped'  => ['check-check', '#8e8e93'],
                            default    => ['clock', 'var(--apple-orange)'],
                        }; ?>
                        <i data-lucide="<?= $apIcon[0] ?>" style="color:<?= $apIcon[1] ?>; font-size:12px;"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:12.5px; font-weight:600;"><?= htmlspecialchars($ap['approver_name'] ?? 'Approver') ?></div>
                        <div class="text-sm" style="color:<?= $ap['status']==='approved'?'var(--apple-green)':($ap['status']==='rejected'?'var(--apple-red)':($ap['status']==='skipped'?'#8e8e93':'var(--apple-orange)')) ?>; font-weight:600; font-size:11.5px;">
                            <?= $ap['status'] === 'skipped' ? 'Not Required' : ucfirst($ap['status']) ?> <?= $ap['acted_at'] ? '· '.date('M d, Y',strtotime($ap['acted_at'])) : '' ?>
                        </div>
                        <?php if ($ap['remarks']): ?>
                        <div class="text-sm" style="margin-top:4px; font-size:12px; color:var(--text-secondary); background:rgba(0,0,0,0.03); padding:6px 10px; border-radius:6px;">
                            "<?= htmlspecialchars($ap['remarks']) ?>"
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (in_array($doc['status'], ['pending_review', 'under_review']) && $canApprove): ?>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <form method="POST" style="flex:1; margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="approval_action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                            <i data-lucide="check"></i> Approve
                        </button>
                    </form>
                    <form method="POST" style="flex:1; margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="approval_action" value="reject">
                        <input type="hidden" name="remarks" value="Rejected by approver.">
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                            <i data-lucide="x"></i> Reject
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="card mb-16">
        <div class="card-header"><span class="card-title">Document Details</span></div>
        <div class="card-body" style="font-size:12.5px; padding:16px 20px;">
            <?php $meta = [
                ['Owner', $doc['uploaded_by_name'] ?? '—'],
                ['Department', $doc['department_name'] ?? '—'],
                ['File Name', $doc['file_name']],
                ['File Size', number_format($doc['file_size']/1024,1).' KB'],
                ['Format', strtoupper($ext)],
                ['Created', date('M d, Y', strtotime($doc['created_at']))],
                ['Modified', date('M d, Y', strtotime($doc['updated_at']))],
                ['Expires', $doc['expires_at'] ? date('M d, Y', strtotime($doc['expires_at'])) : '—'],
            ]; ?>
            <?php foreach ($meta as [$label,$val]): ?>
            <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid rgba(0,0,0,0.04);">
                <span class="text-muted"><?= $label ?></span>
                <span class="fw-600" style="text-align:right; max-width:170px; word-break:break-all;"><?= htmlspecialchars($val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span class="card-title"><i data-lucide="history" style="color:var(--apple-blue); margin-right:6px;"></i>Version History</span>
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                <i data-lucide="plus"></i> New Version
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body" style="font-size:12.5px; padding:16px 20px;">
            <?php foreach ($doc['versions'] as $ver): ?>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.04);">
                <div>
                    <div class="fw-600">v<?= $ver['version'] ?> <?= ($ver['version'] == $doc['version']) ? '<span style="font-size:10.5px; color:var(--apple-blue); font-weight:700; background:rgba(0,113,227,0.1); padding:1px 6px; border-radius:4px; margin-left:4px;">Current</span>' : '' ?></div>
                    <div class="text-sm text-muted"><?= htmlspecialchars($ver['uploaded_by_name'] ?? 'Owner') ?> · <?= date('M d, Y', strtotime($ver['created_at'])) ?> · <?= number_format($ver['file_size']/1024, 1) ?> KB</div>
                    <?php if ($ver['notes']): ?><div class="text-sm text-muted" style="margin-top:2px;">"<?= htmlspecialchars($ver['notes']) ?>"</div><?php endif; ?>
                </div>
                <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] . '&v=' . (int)$ver['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Download version v<?= $ver['version'] ?>"><i data-lucide="download" style="font-size:11px;"></i></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
