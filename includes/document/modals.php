<?php
/**
 * Trackify — Document View Modals (Status, New Version, Edit Details)
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');
?>

<!-- ── Apple Sheet Modal for Status Override ── -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="refresh-cw" style="color:var(--apple-blue); margin-right:6px;"></i> Change Document Status</div>
            <button type="button" class="modal-close" onclick="document.getElementById('statusModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Document Status <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <select name="new_status" required>
                    <?php foreach (array_filter(['draft'=>'Draft','pending_review'=>'Submit for Review','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','archived'=>'Archived'], fn($k) => $canApprove || !in_array($k, ['under_review','approved','rejected'], true) || $doc['status'] === $k, ARRAY_FILTER_USE_KEY) as $s => $label): ?>
                    <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks / Notes <span class="text-muted text-sm">(Optional)</span></label>
                <textarea name="remarks" placeholder="Notes on this review or status change…" rows="3"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('statusModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Apple Sheet Modal for Uploading New Version ── -->
<div class="modal-overlay" id="versionModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="upload-cloud" style="color:var(--apple-blue); margin-right:6px;"></i> Upload New Document Version</div>
            <button type="button" class="modal-close" onclick="document.getElementById('versionModal').classList.remove('open')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="versionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="upload_version" value="1">
            <div class="form-group">
                <label>Select New File or Folder <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <div class="apple-dropzone" id="verDropZone" style="padding:20px; border:2px dashed var(--border-card); border-radius:12px; text-align:center; background:var(--bg-card-subtle); cursor:pointer;">
                    <div style="font-weight:600; font-size:13.5px; color:var(--text-primary); margin-bottom:4px;">
                        Choose a file or select a folder
                    </div>
                    <div class="text-muted text-sm" style="margin-bottom:12px;">
                        Supported: PDF, Word, Excel, PPT, Text, Images, ZIP. Folders will be automatically packaged into a .ZIP file. Max 20MB. Current version is v<?= $doc['version'] ?>.
                    </div>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-outline btn-sm" id="verChooseFileBtn"><i data-lucide="file-up"></i> Choose File</button>
                        <button type="button" class="btn btn-outline btn-sm" id="verChooseFolderBtn"><i data-lucide="folder-up"></i> Choose Folder</button>
                    </div>
                    <div id="verFolderHint" class="text-muted text-sm" style="display:none; margin-top:9px;"></div>

                    <!-- Progress indicator while zipping folder -->
                    <div id="verZipProgress" style="display:none; margin-top:12px; font-size:12.5px; color:var(--apple-blue); font-weight:500;">
                        <i data-lucide="loader-circle" class="icon-spin"></i> Compressing folder into ZIP archive…
                    </div>

                    <!-- Selected file preview capsule -->
                    <div id="verFilePreview" style="display:none; margin-top:12px; align-items:center; justify-content:center; gap:8px;">
                        <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(0,113,227,0.12); color:var(--apple-blue); font-weight:600; font-size:12px; padding:5px 12px; border-radius:var(--radius-pill);">
                            <i data-lucide="file-check"></i> <span id="verFileName">File</span> (<span id="verFileSize">0 KB</span>)
                        </span>
                        <button type="button" id="verRemoveFileBtn" onclick="event.stopPropagation(); resetVerFile();" style="border:none; background:rgba(0,0,0,0.06); width:24px; height:24px; border-radius:50%; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; justify-content:center;">
                            <i data-lucide="x" style="font-size:11px;"></i>
                        </button>
                    </div>
                </div>

                <input type="file" id="verFileInput" name="new_version_file" style="display:none;"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.zip">
                <input type="file" id="verFolderInput" style="display:none;" webkitdirectory directory multiple>
            </div>
            <div class="form-group">
                <label>Update Status When Uploading</label>
                <select name="version_status">
                    <option value="">Keep current status (<?= ucfirst(str_replace('_',' ',$doc['status'])) ?>)</option>
                    <option value="draft">Save as Draft</option>
                    <option value="pending_review">Submit for Review</option>
                    <option value="under_review">Under Review</option>
                    <?php if ($canApprove): ?><option value="approved">Approved</option><?php endif; ?>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="form-group">
                <label>Revision Notes <span class="text-muted text-sm">(Optional)</span></label>
                <textarea name="version_notes" placeholder="e.g. Updated financial figures, corrected section 2.1…" rows="3"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('versionModal').classList.remove('open')">Cancel</button>
                <button type="submit" id="verSubmitBtn" class="btn btn-primary">Upload v<?= $doc['version'] + 1 ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ── Apple Sheet Modal for Editing Metadata ── -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:540px;">
        <div class="modal-header">
            <div class="modal-title"><i data-lucide="pencil" style="color:var(--apple-blue); margin-right:6px;"></i> Edit Document Details</div>
            <button type="button" class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_document" value="1">
            <div class="form-group">
                <label>Document Title <span style="color:var(--apple-red); font-weight:700;">*</span></label>
                <input type="text" name="title" value="<?= htmlspecialchars($doc['title']) ?>" required>
            </div>
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" value="<?= htmlspecialchars($doc['reference_number'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($doc['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <?php foreach ($departments as $dep): ?>
                        <option value="<?= $dep['id'] ?>" <?= ($doc['department_id'] == $dep['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dep['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <?php foreach (['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $p => $lbl): ?>
                        <option value="<?= $p ?>" <?= $doc['priority']===$p?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expiration Date</label>
                    <input type="date" name="expires_at" value="<?= $doc['expires_at'] ? date('Y-m-d', strtotime($doc['expires_at'])) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Document Status</label>
                <select name="status">
                    <?php foreach (array_filter(['draft'=>'Draft','pending_review'=>'Submit for Review','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','archived'=>'Archived'], fn($k) => $canApprove || !in_array($k, ['under_review','approved','rejected'], true) || $doc['status'] === $k, ARRAY_FILTER_USE_KEY) as $s => $label): ?>
                    <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tags <span class="text-muted text-sm">(Comma-separated)</span></label>
                <input type="text" name="tags" value="<?= htmlspecialchars($doc['tags'] ?? '') ?>" placeholder="e.g. invoice, q3, budget">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($doc['description'] ?? '') ?></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
