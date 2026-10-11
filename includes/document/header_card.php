<?php
/**
 * Trackify — Document View Header Card & Metadata Details
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');
?>

<!-- Document Header Card -->
<div class="card mb-16">
    <div class="card-body">
        <div class="doc-header-layout">
            <div style="width:54px; height:54px; background:<?= $icon[2] ?>; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="<?= $icon[0] ?>" style="font-size:24px; color:<?= $icon[1] ?>;"></i>
            </div>
            <div class="doc-header-info">
                <h1 style="font-size:19px; font-weight:700; letter-spacing:-0.02em; margin-bottom:6px; color:var(--text-primary);">
                    <?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>
                </h1>
                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <span class="badge <?= $statusColors[$doc['status']] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span>
                    <span class="badge badge-draft"><i data-lucide="flag"></i> <?= ucfirst($doc['priority']) ?></span>
                    <?php if ($doc['category_name']): ?>
                    <span style="font-size:12px; font-weight:500; color:<?= htmlspecialchars($doc['category_color']) ?>; display:inline-flex; align-items:center; gap:4px;">
                        <i data-lucide="tag"></i> <?= htmlspecialchars($doc['category_name']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="text-sm text-muted">Version <?= $doc['version'] ?></span>
                    <span class="text-sm text-muted" style="font-family:monospace;">Ref: <?= htmlspecialchars($doc['reference_number'] ?? '—') ?></span>
                </div>
            </div>
            <div class="doc-header-actions">
                <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary btn-sm">
                    <i data-lucide="download"></i> Download
                </a>
                <?php if ($canEdit): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('statusModal').classList.add('open')">
                    <i data-lucide="refresh-cw"></i> Status
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                    <i data-lucide="upload-cloud"></i> New Version
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('editModal').classList.add('open')">
                    <i data-lucide="pencil"></i> Edit
                </button>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <form method="POST" style="display:inline;" data-confirm-title="Move to Recycle Bin" data-confirm-message="Move this document to the Recycle Bin?" data-confirm-text="Move" data-confirm-icon="trash-2" data-confirm-class="btn-danger" data-confirm-icon-main="trash-2" data-confirm-tone="red">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_document" value="1">
                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--apple-red); border-color:rgba(255,59,48,0.25);" title="Delete Document">
                        <i data-lucide="trash-2"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($doc['description']): ?>
        <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border-hairline);">
            <p style="font-size:13.5px; color:var(--text-secondary); line-height:1.6;"><?= nl2br(htmlspecialchars($doc['description'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($doc['tags']): ?>
        <div style="margin-top:14px; display:flex; gap:6px; flex-wrap:wrap;">
            <?php foreach (explode(',', $doc['tags']) as $tag): ?>
            <span style="background:rgba(0,0,0,0.04); color:var(--text-secondary); font-size:11.5px; font-weight:500; padding:3px 10px; border-radius:var(--radius-pill);">
                #<?= htmlspecialchars(trim($tag)) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i data-lucide="clipboard-list" style="color:var(--apple-blue);margin-right:8px;"></i>Record Metadata</span></div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;">
        <div><div class="text-sm text-muted">Reference Number</div><div class="fw-600" style="font-family:monospace;margin-top:3px;"><?= htmlspecialchars($doc['reference_number'] ?? '—') ?></div></div>
        <div><div class="text-sm text-muted">File Type</div><div class="fw-600" style="margin-top:3px;"><?= htmlspecialchars(strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION))) ?></div></div>
        <div><div class="text-sm text-muted">Created</div><div class="fw-600" style="margin-top:3px;"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div></div>
        <div><div class="text-sm text-muted">Expires</div><div class="fw-600" style="margin-top:3px;"><?= $doc['expires_at'] ? date('M d, Y', strtotime($doc['expires_at'])) : 'No expiry' ?></div></div>
        <div><div class="text-sm text-muted">File Size</div><div class="fw-600" style="margin-top:3px;"><?= number_format($doc['file_size']/1024,1) ?> KB</div></div>
    </div>
</div>
