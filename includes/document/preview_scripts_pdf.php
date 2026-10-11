<?php
/**
 * Trackify — PDF.js Responsive Preview Script
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');
?>
<?php if ($ext === 'pdf'): ?>
<script src="<?= APP_URL ?>/assets/vendor/pdfjs/pdf.min.js"></script>
<script>
(function() {
    const spinner = document.getElementById('pdfLoadingSpinner');
    const pagesContainer = document.getElementById('pdfPagesContainer');
    const singleContainer = document.getElementById('pdfSingleContainer');
    const singleCanvas = document.getElementById('pdfSingleCanvas');
    const nativeWrapper = document.getElementById('pdfNativeWrapper');
    const iframe = document.getElementById('pdfIframe');
    const pageBadge = document.getElementById('pdfPageBadge');
    const extractedCount = document.getElementById('pdfExtractedCount');
    const slideNavControls = document.getElementById('pdfSlideNavControls');
    const pageNumEl = document.getElementById('pdfPageNum');
    const pageCountEl = document.getElementById('pdfPageCount');
    const zoomLevelEl = document.getElementById('pdfZoomLevel');
    const viewToggleBtn = document.getElementById('pdfViewToggleBtn');
    const nativeToggleBtn = document.getElementById('pdfNativeToggle');
    const prevBtn = document.getElementById('pdfPrevPage');
    const nextBtn = document.getElementById('pdfNextPage');
    const zoomInBtn = document.getElementById('pdfZoomIn');
    const zoomOutBtn = document.getElementById('pdfZoomOut');
    const pdfUrl = '<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>';

    if (typeof pdfjsLib === 'undefined') {
        if (spinner) {
            spinner.innerHTML = `
                <div style="color:var(--apple-orange); margin-bottom:12px;"><i data-lucide="alert-triangle"></i> PDF reader library could not be loaded.</div>
                <a href="${pdfUrl}" target="_blank" class="btn btn-primary btn-sm">Open PDF in New Tab</a>
            `;
            if (window.lucide) lucide.createIcons();
        }
        return;
    }

    // Configure worker safely
    pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= APP_URL ?>/assets/vendor/pdfjs/pdf.worker.min.js';

    let pdfDoc = null;
    let scale = 1.0;
    let activePage = 1;
    let viewMode = 'all'; // 'all', 'single', or 'native'
    let isRendering = false;

    function updateZoomText() {
        if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
    }

    function getTargetWidth() {
        const wrapper = document.getElementById('pdfCanvasWrapper');
        const containerWidth = wrapper ? wrapper.clientWidth : window.innerWidth;
        const padding = window.innerWidth <= 640 ? 16 : 40;
        const avail = Math.max(containerWidth - padding, 220);
        return Math.min(avail, 860) * scale;
    }

    function renderAllPages() {
        if (!pdfDoc || !pagesContainer) return;
        pagesContainer.innerHTML = '';
        pagesContainer.style.display = 'flex';
        if (singleContainer) singleContainer.style.display = 'none';
        if (nativeWrapper) nativeWrapper.style.display = 'none';
        if (slideNavControls) slideNavControls.style.display = 'none';
        if (viewToggleBtn) {
            viewToggleBtn.innerHTML = '<i data-lucide="presentation" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> Single Slide';
            if (window.lucide) lucide.createIcons();
        }

        const targetWidth = getTargetWidth();

        for (let p = 1; p <= pdfDoc.numPages; p++) {
            const card = document.createElement('div');
            card.className = 'extracted-page-card';
            card.style.cssText = 'background:#fff; max-width:860px; width:100%; box-sizing:border-box; box-shadow:0 6px 20px rgba(0,0,0,0.3); border-radius:6px; overflow:hidden; margin:0 auto 20px;';
            card.innerHTML = `
                <div class="extracted-page-header" style="background:#2c2c2e; color:#f1f1f1; padding:6px 14px; font-size:11.5px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                    <span style="font-weight:600;">Page ${p} of ${pdfDoc.numPages}</span>
                    <a href="${pdfUrl}" target="_blank" style="color:#64d2ff; text-decoration:none; font-size:11px;">View Full Page</a>
                </div>
                <div style="display:flex; justify-content:center; padding:0; background:#fff; overflow:hidden; width:100%; box-sizing:border-box;">
                    <canvas id="pdfCanvasPage_${p}" style="display:block; max-width:100%; height:auto;"></canvas>
                </div>
            `;
            pagesContainer.appendChild(card);

            pdfDoc.getPage(p).then(page => {
                const canvas = document.getElementById(`pdfCanvasPage_${p}`);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const unscaledViewport = page.getViewport({ scale: 1.0 });
                const renderScale = (targetWidth / unscaledViewport.width);
                const viewport = page.getViewport({ scale: renderScale });

                const dpr = window.devicePixelRatio || 1;
                canvas.width = Math.floor(viewport.width * dpr);
                canvas.height = Math.floor(viewport.height * dpr);
                canvas.style.width = '100%';
                canvas.style.maxWidth = Math.floor(viewport.width) + 'px';
                canvas.style.height = 'auto';

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport,
                    transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
                };
                page.render(renderContext);
            });
        }
    }

    function renderSinglePage(num) {
        if (!pdfDoc || !singleCanvas) return;
        activePage = Math.max(1, Math.min(num, pdfDoc.numPages));
        if (pageNumEl) pageNumEl.textContent = activePage;
        if (prevBtn) prevBtn.disabled = (activePage <= 1);
        if (nextBtn) nextBtn.disabled = (activePage >= pdfDoc.numPages);

        pagesContainer.style.display = 'none';
        if (singleContainer) singleContainer.style.display = 'flex';
        if (nativeWrapper) nativeWrapper.style.display = 'none';
        if (slideNavControls) slideNavControls.style.display = 'flex';
        if (viewToggleBtn) {
            viewToggleBtn.innerHTML = '<i data-lucide="layers" style="width:13px; height:13px; display:inline-block; vertical-align:middle;"></i> All Pages';
            if (window.lucide) lucide.createIcons();
        }

        pdfDoc.getPage(activePage).then(page => {
            const ctx = singleCanvas.getContext('2d');
            const targetWidth = getTargetWidth();
            const unscaledViewport = page.getViewport({ scale: 1.0 });
            const renderScale = (targetWidth / unscaledViewport.width);
            const viewport = page.getViewport({ scale: renderScale });

            const dpr = window.devicePixelRatio || 1;
            singleCanvas.width = Math.floor(viewport.width * dpr);
            singleCanvas.height = Math.floor(viewport.height * dpr);
            singleCanvas.style.width = '100%';
            singleCanvas.style.maxWidth = Math.floor(viewport.width) + 'px';
            singleCanvas.style.height = 'auto';

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport,
                transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
            };
            page.render(renderContext);
        });
    }

    // View Mode Toggles
    viewToggleBtn?.addEventListener('click', function() {
        if (viewMode === 'all') {
            viewMode = 'single';
            renderSinglePage(activePage);
        } else {
            viewMode = 'all';
            renderAllPages();
        }
    });

    nativeToggleBtn?.addEventListener('click', function() {
        if (viewMode === 'native') {
            viewMode = 'all';
            nativeToggleBtn.style.background = 'rgba(255,255,255,0.1)';
            renderAllPages();
        } else {
            viewMode = 'native';
            nativeToggleBtn.style.background = 'rgba(0,113,227,0.85)';
            if (pagesContainer) pagesContainer.style.display = 'none';
            if (singleContainer) singleContainer.style.display = 'none';
            if (slideNavControls) slideNavControls.style.display = 'none';
            if (nativeWrapper) {
                nativeWrapper.style.display = 'block';
                if (iframe && !iframe.src) iframe.src = pdfUrl;
            }
        }
    });

    // Pagination
    prevBtn?.addEventListener('click', () => { if (activePage > 1) renderSinglePage(activePage - 1); });
    nextBtn?.addEventListener('click', () => { if (pdfDoc && activePage < pdfDoc.numPages) renderSinglePage(activePage + 1); });

    // Zoom
    zoomInBtn?.addEventListener('click', () => {
        scale = Math.min(scale + 0.25, 2.5);
        updateZoomText();
        if (viewMode === 'single') renderSinglePage(activePage);
        else renderAllPages();
    });

    zoomOutBtn?.addEventListener('click', () => {
        scale = Math.max(scale - 0.25, 0.5);
        updateZoomText();
        if (viewMode === 'single') renderSinglePage(activePage);
        else renderAllPages();
    });

    // Fetch and load PDF
    const loadingTask = pdfjsLib.getDocument({
        url: pdfUrl,
        cMapUrl: '<?= APP_URL ?>/assets/vendor/pdfjs/cmaps/',
        cMapPacked: true
    });

    loadingTask.promise.then(pdf => {
        pdfDoc = pdf;
        if (spinner) spinner.style.display = 'none';
        if (extractedCount) extractedCount.textContent = `${pdf.numPages} Page${pdf.numPages > 1 ? 's' : ''} Extracted`;
        if (pageCountEl) pageCountEl.textContent = pdf.numPages;
        renderAllPages();
    }).catch(err => {
        console.error('PDF.js loading error:', err);
        if (spinner) {
            spinner.innerHTML = `
                <div style="padding:20px; text-align:center;">
                    <div style="color:var(--apple-orange); margin-bottom:10px; font-weight:600;">
                        <i data-lucide="alert-triangle"></i> Browser viewer encountered an issue reading this PDF file.
                    </div>
                    <div style="display:flex; justify-content:center; gap:8px;">
                        <a href="${pdfUrl}" target="_blank" class="btn btn-primary btn-sm">Open Native PDF View</a>
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-outline btn-sm">Download File</a>
                    </div>
                </div>
            `;
            if (window.lucide) lucide.createIcons();
        }
    });
})();
</script>
<?php endif; ?>
