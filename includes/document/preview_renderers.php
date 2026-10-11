<?php
/**
 * Trackify — Document Preview Renderers (Markup)
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');

$diskFile = realpath(__DIR__ . '/../../' . ltrim($doc['file_path'], '/\\'));
$hasDiskFile = ($diskFile && is_file($diskFile));
?>

<!-- Document Preview -->
<div class="card mb-16" style="min-width:0; max-width:100%; overflow:hidden;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span class="card-title">
            <i data-lucide="eye" style="color:var(--apple-blue); margin-right:8px;"></i>
            Document Preview
        </span>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span class="text-sm text-muted" style="font-size:12px;">
                <?= strtoupper($ext) ?> · <?= number_format($doc['file_size'] / 1024, 1) ?> KB
            </span>
            <?php if ($hasDiskFile): ?>
                <?php if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt'])): ?>
                <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Open full screen in a new tab">
                    <i data-lucide="external-link"></i> Open Tab
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm" title="Download original file">
                    <i data-lucide="download"></i> Download
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding:16px; min-width:0; max-width:100%; overflow:hidden;">
        <?php if (!$hasDiskFile): ?>
        <!-- Missing file state: graceful fallback -->
        <div style="text-align:center; padding:38px 20px; background:var(--bg-card-subtle); border-radius:12px; border:1px dashed var(--border-hairline);">
            <div style="width:54px; height:54px; border-radius:16px; background:rgba(255, 149, 0, 0.12); color:var(--apple-orange); display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px;">
                <i data-lucide="file-question"></i>
            </div>
            <h3 style="font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
                File Unavailable in Storage
            </h3>
            <p style="font-size:13px; color:var(--text-secondary); max-width:440px; margin:0 auto 16px; line-height:1.5;">
                The uploaded file for this record is missing from storage (uploaded before persistent volume was attached). Please upload a new version to attach the file and enable preview.
            </p>
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('versionModal').classList.add('open')">
                <i data-lucide="upload"></i> Upload New Version
            </button>
            <?php endif; ?>
        </div>

        <?php elseif ($ext === 'pdf'): ?>
        <!-- Universal In-Browser PDF Preview: Extracted Pages Reader + Slide View + Native Frame -->
        <div id="pdfViewerContainer" style="border-radius:10px; overflow:hidden; border:1px solid var(--border-hairline); background:#525659; width:100%; max-width:100%;">
            <!-- PDF Controls Toolbar -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#323639; padding:8px 14px; color:#f1f1f1; font-size:12.5px; border-bottom:1px solid rgba(255,255,255,0.1); flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span id="pdfPageBadge" class="badge" style="background:rgba(255,255,255,0.15); color:#fff; font-size:11.5px; padding:3px 8px; border-radius:6px;">
                        <span id="pdfExtractedCount">Loading pages…</span>
                    </span>
                    <div id="pdfSlideNavControls" style="display:none; align-items:center; gap:6px;">
                        <button type="button" id="pdfPrevPage" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">‹ Prev</button>
                        <span style="font-weight:500;">
                            Page <span id="pdfPageNum">1</span> / <span id="pdfPageCount">—</span>
                        </span>
                        <button type="button" id="pdfNextPage" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">Next ›</button>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="pdfViewToggleBtn" class="btn btn-sm" style="background:rgba(255,255,255,0.15); color:#fff; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11.5px;">
                        <i data-lucide="layers" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> All Pages View
                    </button>
                    <button type="button" id="pdfZoomOut" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">−</button>
                    <span id="pdfZoomLevel" style="font-size:11.5px; min-width:38px; text-align:center;">100%</span>
                    <button type="button" id="pdfZoomIn" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 9px; border-radius:6px; cursor:pointer;">+</button>
                    <button type="button" id="pdfNativeToggle" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:11.5px;" title="Switch to browser native PDF frame">
                        Native View
                    </button>
                    <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank" class="btn btn-sm" style="background:rgba(0,113,227,0.85); color:#fff; border:none; padding:4px 10px; border-radius:6px; text-decoration:none;">
                        <i data-lucide="external-link" style="width:12px; height:12px; display:inline;"></i> Full Screen
                    </a>
                </div>
            </div>
            <!-- PDF Canvas Viewport -->
            <div id="pdfCanvasWrapper" style="overflow-x:auto; overflow-y:auto; max-height:720px; min-height:480px; padding:16px; background:#525659; -webkit-overflow-scrolling:touch;">
                <div id="pdfLoadingSpinner" style="color:#fff; padding:40px; text-align:center;">
                    <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px;"></i>
                    <div>Extracting PDF pages…</div>
                </div>
                <!-- Container for all extracted pages stacked vertically -->
                <div id="pdfPagesContainer" style="display:none; flex-direction:column; align-items:center; gap:20px; width:100%;"></div>
                <!-- Single slide view container -->
                <div id="pdfSingleContainer" style="display:none; justify-content:center; align-items:flex-start; width:100%;">
                    <canvas id="pdfSingleCanvas" style="box-shadow:0 4px 20px rgba(0,0,0,0.35); max-width:100%; height:auto; background:#fff; border-radius:4px;"></canvas>
                </div>
                <!-- Native iframe container -->
                <div id="pdfNativeWrapper" style="display:none; width:100%; height:680px;">
                    <iframe id="pdfIframe" src="" style="width:100%; height:100%; border:none; border-radius:6px; background:#fff;"></iframe>
                </div>
            </div>
        </div>

        <?php elseif (in_array($ext, ['png', 'jpg', 'jpeg'])): ?>
        <!-- Image Preview -->
        <div style="text-align:center; padding:20px; background:rgba(0,0,0,0.02); border-radius:10px; border:1px solid var(--border-hairline);">
            <a href="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" target="_blank">
                <img src="<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>" 
                     alt="<?= htmlspecialchars(doc_title($doc['title'], $doc['reference_number'] ?? null)) ?>"
                     style="max-width:100%; max-height:560px; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,0.08); object-fit:contain; cursor:zoom-in;">
            </a>
            <div style="font-size:12px; color:var(--text-secondary); margin-top:10px;">
                Tap or click image to view in full resolution
            </div>
        </div>

        <?php elseif ($ext === 'docx'): ?>
        <!-- DOCX Interactive Document Viewer -->
        <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                    <i data-lucide="file-text" style="color:var(--apple-blue); width:18px; height:18px;"></i>
                    Word Document Preview
                </div>
                <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                    <i data-lucide="download"></i> Download .docx
                </a>
            </div>
            <div id="docxPreviewContainer" style="padding:16px; max-height:650px; overflow-x:auto; overflow-y:auto; background:#fff; color:#1d1d1f; line-height:1.6; -webkit-overflow-scrolling:touch; width:100%; box-sizing:border-box;">
                <div id="docxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                    <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-blue);"></i>
                    <div>Rendering Word document…</div>
                </div>
            </div>
        </div>

        <?php elseif (in_array($ext, ['xls', 'xlsx'])): ?>
        <!-- Excel-Styled Interactive Spreadsheet Grid Viewer -->
        <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; background:#107c41; color:#fff; border-radius:4px; font-size:12px; font-weight:700;">X</span>
                    <span>Excel Spreadsheet View</span>
                    <span id="xlsxGridMetaBadge" class="badge" style="background:rgba(16,124,65,0.12); color:#107c41; font-size:11px; padding:2px 8px; border-radius:10px; display:none;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div id="xlsxSheetsContainer" style="display:flex; gap:4px; flex-wrap:wrap;"></div>
                    <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                        <i data-lucide="download"></i> Download .<?= $ext ?>
                    </a>
                </div>
            </div>
            <div id="xlsxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:#107c41;"></i>
                <div>Building spreadsheet grid…</div>
            </div>
            <div id="xlsxTableWrapper" class="excel-grid-wrapper" style="width:100%; max-width:100%; overflow-x:auto !important; overflow-y:auto !important; max-height:580px; -webkit-overflow-scrolling:touch; background:var(--bg-card);"></div>
        </div>

        <?php elseif (in_array($ext, ['ppt', 'pptx'])): ?>
        <!-- PowerPoint Interactive Presentation & Extracted Slide Pages Viewer -->
        <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                    <i data-lucide="presentation" style="color:var(--apple-orange); width:18px; height:18px;"></i>
                    PowerPoint Slides & Pages
                    <span id="pptxImageCountBadge" class="badge" style="background:rgba(255,149,0,0.15); color:var(--apple-orange); font-size:11px; padding:2px 8px; border-radius:12px; display:none;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div id="pptxViewModes" style="display:none; gap:4px; flex-wrap:wrap;">
                        <button type="button" id="pptxBtnPages" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:4px 10px;">All Slide Pages</button>
                        <button type="button" id="pptxBtnDeck" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Slide Deck</button>
                        <button type="button" id="pptxBtnGrid" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Media Grid</button>
                    </div>
                    <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                        <i data-lucide="download"></i> Download .<?= $ext ?>
                    </a>
                </div>
            </div>
            <div id="pptxLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-orange);"></i>
                <div>Extracting presentation slide pages…</div>
            </div>
            <!-- All Extracted Slide Pages (Scrollable Stack) -->
            <div id="pptxPagesContainer" style="display:none; padding:16px; max-height:680px; overflow-y:auto; -webkit-overflow-scrolling:touch;">
                <div id="pptxPagesWrapper" style="display:flex; flex-direction:column; gap:20px; align-items:center; width:100%; max-width:860px; margin:0 auto;"></div>
            </div>
            <!-- Slide Deck View (Featured Slide + Controls + Thumbnails) -->
            <div id="pptxDeckContainer" style="display:none; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; background:var(--bg-card-subtle); padding:8px 14px; border-radius:8px; font-size:12px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" id="pptxPrevBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">‹ Previous</button>
                        <span style="font-weight:600;" id="pptxSlideIndicator">Slide 1 of 1</span>
                        <button type="button" id="pptxNextBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">Next ›</button>
                    </div>
                    <span id="pptxCurrentFileName" class="text-muted" style="font-size:11.5px;"></span>
                </div>
                <div style="text-align:center; background:#1c1c1e; border-radius:8px; padding:16px; min-height:360px; display:flex; align-items:center; justify-content:center;">
                    <a id="pptxActiveImageLink" href="#" target="_blank" title="Click to view full slide in new tab">
                        <img id="pptxActiveImage" src="" alt="Presentation Slide" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,0.5); cursor:zoom-in;">
                    </a>
                </div>
                <!-- Thumbnail Strip -->
                <div id="pptxThumbStrip" style="display:flex; gap:8px; overflow-x:auto; padding:12px 0 4px; margin-top:10px;"></div>
            </div>
            <!-- Grid View of All Visuals -->
            <div id="pptxGridContainer" style="display:none; padding:16px; max-height:600px; overflow-y:auto;">
                <div id="pptxGridWrapper" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:14px;"></div>
            </div>
            <!-- Text Outline Fallback -->
            <div id="pptxTextContainer" style="display:none; padding:20px; max-height:500px; overflow-y:auto;"></div>
        </div>

        <?php elseif ($ext === 'zip'): ?>
        <!-- ZIP Archive & Extracted Presentation Pages Inspector -->
        <div style="border-radius:10px; border:1px solid var(--border-hairline); overflow:hidden; background:var(--bg-card); width:100%; max-width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-subtle); padding:10px 16px; border-bottom:1px solid var(--border-hairline); flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:var(--text-primary);">
                    <i data-lucide="archive" style="color:var(--apple-orange); width:18px; height:18px;"></i>
                    <span id="zipHeaderTitle">Archive & Slide Pages</span>
                    <span id="zipBadgeCount" class="badge" style="background:rgba(255,149,0,0.15); color:var(--apple-orange); font-size:11px; padding:2px 8px; border-radius:12px; display:none;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div id="zipViewModes" style="display:none; gap:4px; flex-wrap:wrap;">
                        <button type="button" id="zipBtnPages" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:4px 10px;">Extracted Pages</button>
                        <button type="button" id="zipBtnDeck" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">Slide Deck</button>
                        <button type="button" id="zipBtnFiles" class="btn btn-outline btn-sm" style="font-size:11.5px; padding:4px 10px;">File Listing</button>
                    </div>
                    <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">
                        <i data-lucide="download"></i> Download ZIP
                    </a>
                </div>
            </div>
            <div id="zipListLoading" style="text-align:center; padding:40px; color:var(--text-secondary);">
                <i data-lucide="loader-circle" class="icon-spin" style="width:28px; height:28px; margin-bottom:8px; color:var(--apple-orange);"></i>
                <div>Reading archive contents & slides…</div>
            </div>
            <!-- Extracted Pages Scrollable View (Page 1, Page 2... stacked) -->
            <div id="zipPagesContainer" style="display:none; padding:16px; max-height:680px; overflow-y:auto; -webkit-overflow-scrolling:touch;">
                <div id="zipPagesWrapper" style="display:flex; flex-direction:column; gap:20px; align-items:center; width:100%; max-width:860px; margin:0 auto;"></div>
            </div>
            <!-- Slide Deck View -->
            <div id="zipDeckContainer" style="display:none; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; background:var(--bg-card-subtle); padding:8px 14px; border-radius:8px; font-size:12px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" id="zipPrevBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">‹ Previous</button>
                        <span style="font-weight:600;" id="zipSlideIndicator">Page 1 of 1</span>
                        <button type="button" id="zipNextBtn" class="btn btn-outline btn-sm" style="padding:3px 10px;">Next ›</button>
                    </div>
                    <span id="zipCurrentFileName" class="text-muted" style="font-size:11.5px;"></span>
                </div>
                <div style="text-align:center; background:#1c1c1e; border-radius:8px; padding:16px; min-height:360px; display:flex; align-items:center; justify-content:center;">
                    <a id="zipActiveImageLink" href="#" target="_blank" title="Click to view full image in new tab">
                        <img id="zipActiveImage" src="" alt="Extracted Slide Page" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,0.5); cursor:zoom-in;">
                    </a>
                </div>
                <!-- Thumbnail Strip -->
                <div id="zipThumbStrip" style="display:flex; gap:8px; overflow-x:auto; padding:12px 0 4px; margin-top:10px;"></div>
            </div>
            <!-- File list wrapper -->
            <div id="zipFileListWrapper" style="display:none; padding:12px; max-height:500px; overflow:auto;"></div>
        </div>

        <?php elseif ($ext === 'txt'): ?>
        <!-- Text File Preview -->
        <?php
        $fullPath = realpath(__DIR__ . '/../../' . ltrim($doc['file_path'], '/\\'));
        $textContent = ($fullPath && is_file($fullPath)) ? file_get_contents($fullPath) : '';
        ?>
        <pre style="background:rgba(0,0,0,0.02); border:1px solid var(--border-hairline); border-radius:10px; padding:18px 20px; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12.5px; line-height:1.6; max-height:500px; overflow:auto; color:var(--text-primary); white-space:pre-wrap; word-break:break-word; max-width:100%;"><?= htmlspecialchars($textContent) ?></pre>

        <?php else: ?>
        <!-- Unsupported / Other Format Quick Look Card -->
        <div style="text-align:center; padding:44px 20px; background:linear-gradient(180deg, rgba(0,0,0,0.01) 0%, rgba(0,0,0,0.03) 100%); border-radius:12px; border:1px dashed var(--border-hairline);">
            <div style="width:68px; height:68px; border-radius:18px; background:<?= $icon[2] ?>; color:<?= $icon[1] ?>; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:14px; box-shadow:0 4px 14px rgba(0,0,0,0.06);">
                <i data-lucide="<?= $icon[0] ?>"></i>
            </div>
            <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
                <?= htmlspecialchars($doc['file_name']) ?>
            </h3>
            <p style="font-size:13px; color:var(--text-secondary); max-width:460px; margin:0 auto 20px; line-height:1.5;">
                This document is in <strong><?= strtoupper($ext) ?></strong> format (<?= htmlspecialchars($doc['file_type']) ?>). Tap below to download and view using your device's native app.
            </p>
            <div style="display:inline-flex; gap:10px; flex-wrap:wrap; justify-content:center;">
                <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary">
                    <i data-lucide="download"></i> Download & Open File
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
