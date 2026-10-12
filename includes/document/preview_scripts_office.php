<?php
/**
 * Trackify — Office & Archive Preview Scripts (DOCX, XLSX, PPTX, ZIP)
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');
?>

<?php if ($ext === 'docx'): ?>
<script src="<?= APP_URL ?>/assets/vendor/docx/docx-preview.min.js"></script>
<script>
(function() {
    const docxContainer = document.getElementById('docxPreviewContainer');
    const loading = document.getElementById('docxLoading');
    if (!docxContainer) return;
    if (typeof docx === 'undefined') {
        if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Word viewer library could not be loaded. Please download the file to view.</div>';
        return;
    }

    fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
        .then(res => {
            if (!res.ok) throw new Error('Network error loading DOCX');
            return res.blob();
        })
        .then(blob => {
            if (loading) loading.style.display = 'none';
            docx.renderAsync(blob, docxContainer, null, {
                className: 'docx-rendered-body',
                inWrapper: false,
                ignoreWidth: true,
                ignoreHeight: false
            }).then(() => {
                // Ensure all rendered sections and tables respect fluid screen fitting
                const sections = docxContainer.querySelectorAll('section, .docx, table, img');
                sections.forEach(el => {
                    el.style.maxWidth = '100%';
                    el.style.boxSizing = 'border-box';
                });
            }).catch(err => {
                console.error('docx preview render error:', err);
                docxContainer.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Unable to parse document layout. Please download the file to view.</div>';
            });
        })
        .catch(err => {
            console.error('docx fetch error:', err);
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not load document preview. Please download to view.</div>';
        });
})();
</script>
<?php endif; ?>

<?php if (in_array($ext, ['xls', 'xlsx'])): ?>
<script src="<?= APP_URL ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
(function() {
    const tableWrapper = document.getElementById('xlsxTableWrapper');
    const sheetsContainer = document.getElementById('xlsxSheetsContainer');
    const loading = document.getElementById('xlsxLoading');
    const metaBadge = document.getElementById('xlsxGridMetaBadge');
    if (!tableWrapper) return;
    if (typeof XLSX === 'undefined') {
        if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Spreadsheet viewer library could not be loaded. Please download the file to view.</div>';
        return;
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function getColLetter(idx) {
        let s = '';
        while (idx >= 0) {
            s = String.fromCharCode((idx % 26) + 65) + s;
            idx = Math.floor(idx / 26) - 1;
        }
        return s;
    }

    fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.arrayBuffer();
        })
        .then(buffer => {
            const workbook = XLSX.read(buffer, { type: 'array' });
            if (loading) loading.style.display = 'none';

            if (!workbook.SheetNames || !workbook.SheetNames.length) {
                tableWrapper.innerHTML = '<div class="text-muted text-center" style="padding:20px;">No sheets found in spreadsheet.</div>';
                return;
            }

            function renderSheet(name) {
                const sheet = workbook.Sheets[name];
                const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false });

                if (!rows || !rows.length) {
                    tableWrapper.innerHTML = '<div class="text-muted text-center" style="padding:40px;">This sheet is empty.</div>';
                    if (metaBadge) metaBadge.style.display = 'none';
                    return;
                }

                let maxCols = 0;
                rows.forEach(r => { if (r && r.length > maxCols) maxCols = r.length; });
                if (maxCols === 0) maxCols = 1;

                if (metaBadge) {
                    metaBadge.textContent = `${rows.length} Rows × ${maxCols} Cols`;
                    metaBadge.style.display = 'inline-block';
                }

                // Build authentic Excel grid table directly inside the responsive scroll wrapper
                let html = '<table class="excel-table">';
                
                // Column Letters Header Row (A, B, C...)
                html += '<thead><tr>';
                html += '<th></th>';
                for (let c = 0; c < maxCols; c++) {
                    html += `<th>${getColLetter(c)}</th>`;
                }
                html += '</tr></thead><tbody>';

                // Data Rows with Row Numbers (1, 2, 3...)
                rows.forEach((row, rIdx) => {
                    html += '<tr>';
                    html += `<th>${rIdx + 1}</th>`;
                    for (let c = 0; c < maxCols; c++) {
                        const rawVal = row ? row[c] : '';
                        const strVal = (rawVal !== undefined && rawVal !== null) ? String(rawVal) : '';
                        const isNum = !isNaN(strVal) && strVal.trim() !== '' && !/^[A-Za-z]/.test(strVal);
                        const alignStyle = isNum ? 'text-align:right; font-variant-numeric:tabular-nums;' : 'text-align:left;';
                        html += `<td style="white-space:nowrap; max-width:320px; overflow:hidden; text-overflow:ellipsis; ${alignStyle}">${escHtml(strVal)}</td>`;
                    }
                    html += '</tr>';
                });

                html += '</tbody></table>';
                tableWrapper.innerHTML = html;
            }

            // Render sheet tabs styled like Excel
            sheetsContainer.innerHTML = '';
            workbook.SheetNames.forEach((name, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = idx === 0 ? 'btn btn-sm' : 'btn btn-outline btn-sm';
                if (idx === 0) {
                    btn.style.cssText = 'background:#107c41; color:#fff; border:none; font-size:11.5px; padding:4px 12px; font-weight:600; border-radius:6px;';
                } else {
                    btn.style.cssText = 'font-size:11.5px; padding:4px 12px; border-radius:6px;';
                }
                btn.textContent = name;
                btn.onclick = () => {
                    sheetsContainer.querySelectorAll('button').forEach(b => {
                        b.className = 'btn btn-outline btn-sm';
                        b.style.cssText = 'font-size:11.5px; padding:4px 12px; border-radius:6px;';
                    });
                    btn.className = 'btn btn-sm';
                    btn.style.cssText = 'background:#107c41; color:#fff; border:none; font-size:11.5px; padding:4px 12px; font-weight:600; border-radius:6px;';
                    renderSheet(name);
                };
                sheetsContainer.appendChild(btn);
            });

            renderSheet(workbook.SheetNames[0]);
        })
        .catch(err => {
            console.error('XLSX render error:', err);
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not load spreadsheet data. Please download the file to view.</div>';
        });
})();
</script>
<?php endif; ?>

<?php if (in_array($ext, ['ppt', 'pptx'])): ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/pptxjs/pptxjs.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/pptxjs/nv.d3.min.css">
<style>
/* ── Trackify PowerPoint Visual Presentation Styling ── */
#pptxVisualContainer {
    position: relative;
    box-sizing: border-box;
}

#pptxResult .slide {
    margin: 0 auto !important;
    border-radius: 8px !important;
    box-shadow: 0 6px 26px rgba(0, 0, 0, 0.22) !important;
    border: 1px solid var(--border-hairline) !important;
    transition: transform 0.15s ease-out;
}

.pptx-slide-card {
    background: var(--bg-card);
    border: 1px solid var(--border-hairline);
    border-radius: 10px;
    overflow: hidden;
    margin: 0 auto 24px auto;
    width: 100%;
    max-width: 960px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
}

.pptx-slide-card-header {
    background: var(--bg-card-subtle);
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-hairline);
}

.pptx-slide-stage {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 16px;
    background: var(--bg-card-subtle);
    overflow: hidden;
    box-sizing: border-box;
}

.pptx-thumb-btn {
    border: 2px solid transparent;
    border-radius: 6px;
    background: var(--bg-card-subtle);
    color: var(--text-primary);
    padding: 6px 12px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.pptx-thumb-btn:hover {
    background: var(--bg-card);
    border-color: var(--apple-blue);
}

.pptx-thumb-btn.active {
    background: var(--apple-blue);
    color: #ffffff;
    border-color: var(--apple-blue);
}
</style>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/jquery.min.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/jszip.min.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/filereader.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/d3.min.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/nv.d3.min.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/pptxjs.js"></script>
<script src="<?= APP_URL ?>/assets/vendor/pptxjs/divs2slides.js"></script>
<script>
(function() {
    function initPptxVisualViewer() {
        var $ = window.jQuery;
        if (!$) return;

        var loadingEl      = document.getElementById('pptxLoading');
        var viewModesEl    = document.getElementById('pptxViewModes');
        var badgeEl        = document.getElementById('pptxSlideCountBadge');
        var containerEl    = document.getElementById('pptxVisualContainer');
        var resultEl       = document.getElementById('pptxResult');
        var deckNavEl      = document.getElementById('pptxDeckNav');
        var deckThumbEl    = document.getElementById('pptxDeckThumbnails');
        var deckIndicator  = document.getElementById('pptxDeckIndicator');
        var prevBtn        = document.getElementById('pptxPrevBtn');
        var nextBtn        = document.getElementById('pptxNextBtn');
        var btnStacked     = document.getElementById('pptxBtnStacked');
        var btnDeck        = document.getElementById('pptxBtnDeck');
        var btnFullscreen  = document.getElementById('pptxBtnFullscreen');
        var fallbackEl     = document.getElementById('pptxFallback');
        var fallbackMsgEl  = document.getElementById('pptxFallbackMsg');

        var currentMode = 'stacked';
        var currentDeckSlide = 0;
        var slideCards = [];
        var totalSlideCount = 0;

        function showFallback(msg) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (viewModesEl) viewModesEl.style.display = 'none';
            if (containerEl) containerEl.style.display = 'none';
            if (deckNavEl) deckNavEl.style.display = 'none';
            if (deckThumbEl) deckThumbEl.style.display = 'none';
            if (fallbackEl) {
                if (fallbackMsgEl) fallbackMsgEl.textContent = msg || 'This presentation could not be rendered directly.';
                fallbackEl.style.display = 'block';
            }
            if (window.lucide) window.lucide.createIcons();
        }

        // Responsive scaler for all slides
        function fitSlideCards() {
            if (!containerEl || !slideCards.length) return;
            var availWidth = Math.max(220, containerEl.clientWidth - 48);

            slideCards.forEach(function(cardInfo) {
                var slideEl    = cardInfo.slide;
                var viewportEl = cardInfo.viewport;
                var natW = cardInfo.width;
                var natH = cardInfo.height;

                var scale = 1;
                if (availWidth < natW) {
                    scale = availWidth / natW;
                }

                var scaledW = Math.round(natW * scale);
                var scaledH = Math.round(natH * scale);

                viewportEl.style.width  = scaledW + 'px';
                viewportEl.style.height = scaledH + 'px';

                slideEl.style.transform = 'scale(' + scale + ')';
                slideEl.style.transformOrigin = '0 0';
            });
        }

        function setMode(mode) {
            currentMode = mode;
            if (btnStacked) btnStacked.className = mode === 'stacked' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
            if (btnDeck)    btnDeck.className    = mode === 'deck'    ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';

            if (mode === 'stacked') {
                if (deckNavEl)   deckNavEl.style.display   = 'none';
                if (deckThumbEl) deckThumbEl.style.display = 'none';
                slideCards.forEach(function(c) {
                    c.card.style.display = 'block';
                });
            } else if (mode === 'deck') {
                if (deckNavEl)   deckNavEl.style.display   = 'flex';
                if (deckThumbEl) deckThumbEl.style.display = 'flex';
                updateDeckView(currentDeckSlide);
            }
            fitSlideCards();
        }

        function updateDeckView(idx) {
            if (!slideCards.length) return;
            currentDeckSlide = Math.max(0, Math.min(idx, slideCards.length - 1));

            slideCards.forEach(function(c, i) {
                c.card.style.display = (i === currentDeckSlide) ? 'block' : 'none';
            });

            if (deckIndicator) {
                deckIndicator.textContent = 'Slide ' + (currentDeckSlide + 1) + ' of ' + slideCards.length;
            }
            if (prevBtn) prevBtn.disabled = (currentDeckSlide === 0);
            if (nextBtn) nextBtn.disabled = (currentDeckSlide === slideCards.length - 1);

            // Update active thumbnail
            if (deckThumbEl) {
                var thumbBtns = deckThumbEl.querySelectorAll('.pptx-thumb-btn');
                thumbBtns.forEach(function(tb, tIdx) {
                    if (tIdx === currentDeckSlide) {
                        tb.classList.add('active');
                        tb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    } else {
                        tb.classList.remove('active');
                    }
                });
            }
        }

        if (prevBtn) prevBtn.onclick = function() { updateDeckView(currentDeckSlide - 1); };
        if (nextBtn) nextBtn.onclick = function() { updateDeckView(currentDeckSlide + 1); };

        if (btnStacked) btnStacked.onclick = function() { setMode('stacked'); };
        if (btnDeck)    btnDeck.onclick    = function() { setMode('deck'); };

        if (btnFullscreen) {
            btnFullscreen.onclick = function() {
                var card = document.getElementById('pptxMainCard');
                if (!card) return;
                if (!document.fullscreenElement) {
                    if (card.requestFullscreen) {
                        card.requestFullscreen();
                    } else if (card.webkitRequestFullscreen) {
                        card.webkitRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            };
        }

        // Keyboard arrow navigation
        window.addEventListener('keydown', function(e) {
            if (currentMode !== 'deck') return;
            if (e.key === 'ArrowLeft') {
                updateDeckView(currentDeckSlide - 1);
            } else if (e.key === 'ArrowRight') {
                updateDeckView(currentDeckSlide + 1);
            }
        });

        window.addEventListener('resize', fitSlideCards);

        // Begin PPTX rendering via PPTXjs
        var targetUrl = '<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>';

        try {
            $('#pptxResult').pptxToHtml({
                pptxFileUrl: targetUrl,
                slideMode: false
            });
        } catch (err) {
            console.error('PPTXjs invocation error:', err);
            showFallback('Could not initialize presentation viewer.');
            return;
        }

        // Wait for PPTXjs to finish generating slides
        var maxWaitSeconds = 25;
        var startTime = Date.now();

        var pollInterval = setInterval(function() {
            var slides = resultEl ? resultEl.querySelectorAll('.slide') : [];
            var isMsgDone = document.querySelectorAll('.slides-loadnig-msg').length === 0;

            if (slides.length > 0 && isMsgDone) {
                clearInterval(pollInterval);
                onSlidesRendered(slides);
            } else if (Date.now() - startTime > maxWaitSeconds * 1000) {
                clearInterval(pollInterval);
                if (slides.length > 0) {
                    onSlidesRendered(slides);
                } else {
                    showFallback('Presentation rendering timed out. Please download the file to view.');
                }
            }
        }, 150);

        function onSlidesRendered(slidesNodeList) {
            totalSlideCount = slidesNodeList.length;
            if (!totalSlideCount) {
                showFallback('No presentation slides could be parsed.');
                return;
            }

            // Hide loading, show viewmodes & badge
            if (loadingEl) loadingEl.style.display = 'none';
            if (badgeEl) {
                badgeEl.textContent = totalSlideCount + ' Slide' + (totalSlideCount > 1 ? 's' : '');
                badgeEl.style.display = 'inline-block';
            }
            if (viewModesEl) viewModesEl.style.display = 'flex';
            if (containerEl) containerEl.style.display = 'block';

            // Wrap each slide into an elegant presentation card
            slideCards = [];
            if (deckThumbEl) deckThumbEl.innerHTML = '';

            Array.from(slidesNodeList).forEach(function(slide, idx) {
                var sNum = idx + 1;
                var natW = parseFloat(slide.style.width) || 960;
                var natH = parseFloat(slide.style.height) || 540;

                // Card wrapper
                var card = document.createElement('div');
                card.className = 'pptx-slide-card';
                card.setAttribute('data-slide-index', idx);

                // Header
                var header = document.createElement('div');
                header.className = 'pptx-slide-card-header';
                header.innerHTML = '<span><i data-lucide="presentation" style="width:13px; height:13px; display:inline-block; vertical-align:middle; margin-right:4px; color:var(--apple-orange);"></i> Slide ' + sNum + ' of ' + totalSlideCount + '</span><span style="font-size:11px; color:var(--text-tertiary); font-weight:normal;">' + Math.round(natW) + ' × ' + Math.round(natH) + ' px</span>';

                // Stage & Viewport wrapper for pixel-perfect transform scaling
                var stage = document.createElement('div');
                stage.className = 'pptx-slide-stage';

                var viewport = document.createElement('div');
                viewport.className = 'pptx-slide-viewport';
                viewport.style.cssText = 'position:relative; margin:0 auto; overflow:hidden; border-radius:6px; box-shadow:0 4px 20px rgba(0,0,0,0.25);';

                slide.style.position = 'absolute';
                slide.style.left = '0';
                slide.style.top = '0';
                slide.style.margin = '0';
                slide.style.transformOrigin = '0 0';

                // Insert into card
                slide.parentNode.insertBefore(card, slide);
                card.appendChild(header);
                card.appendChild(stage);
                stage.appendChild(viewport);
                viewport.appendChild(slide);

                slideCards.push({
                    card: card,
                    stage: stage,
                    viewport: viewport,
                    slide: slide,
                    width: natW,
                    height: natH
                });

                // Thumbnail button in Deck mode
                if (deckThumbEl) {
                    var thumbBtn = document.createElement('button');
                    thumbBtn.type = 'button';
                    thumbBtn.className = 'pptx-thumb-btn' + (idx === 0 ? ' active' : '');
                    thumbBtn.textContent = 'Slide ' + sNum;
                    thumbBtn.onclick = function() {
                        updateDeckView(idx);
                    };
                    deckThumbEl.appendChild(thumbBtn);
                }
            });

            if (window.lucide) window.lucide.createIcons();
            setMode('stacked');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPptxVisualViewer);
    } else {
        initPptxVisualViewer();
    }
})();
</script>
<?php endif; ?>



<?php if ($ext === 'zip'): ?>
<script>
(function() {
    const loading = document.getElementById('zipListLoading');
    const headerTitle = document.getElementById('zipHeaderTitle');
    const badgeCount = document.getElementById('zipBadgeCount');
    const viewModes = document.getElementById('zipViewModes');
    const pagesContainer = document.getElementById('zipPagesContainer');
    const pagesWrapper = document.getElementById('zipPagesWrapper');
    const deckContainer = document.getElementById('zipDeckContainer');
    const listWrapper = document.getElementById('zipFileListWrapper');
    const btnPages = document.getElementById('zipBtnPages');
    const btnDeck = document.getElementById('zipBtnDeck');
    const btnFiles = document.getElementById('zipBtnFiles');

    const activeImg = document.getElementById('zipActiveImage');
    const activeLink = document.getElementById('zipActiveImageLink');
    const prevBtn = document.getElementById('zipPrevBtn');
    const nextBtn = document.getElementById('zipNextBtn');
    const indicator = document.getElementById('zipSlideIndicator');
    const fileNameEl = document.getElementById('zipCurrentFileName');
    const thumbStrip = document.getElementById('zipThumbStrip');

    let extractedImages = [];
    let currentDeckIdx = 0;

    fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.arrayBuffer();
        })
        .then(buffer => {
            if (typeof JSZip === 'undefined') throw new Error('JSZip unavailable');
            return JSZip.loadAsync(buffer);
        })
        .then(async zip => {
            if (loading) loading.style.display = 'none';

            const allFiles = [];
            const imageEntries = [];

            zip.forEach((path, entry) => {
                if (!entry.dir && !path.startsWith('__MACOSX/') && !path.includes('/.')) {
                    allFiles.push(entry);
                    if (/\.(png|jpe?g|webp|gif|svg)$/i.test(path)) {
                        imageEntries.push(entry);
                    }
                }
            });

            // Sort images naturally
            imageEntries.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

            // Build file listing
            if (listWrapper) {
                let html = '<div style="display:flex; flex-direction:column; gap:6px;">';
                allFiles.forEach(file => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    const isImg = ['png','jpg','jpeg','webp','gif','svg'].includes(ext);
                    html += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--bg-card-subtle); border-radius:6px; font-size:12px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i data-lucide="${isImg ? 'image' : 'file'}" style="color:${isImg ? 'var(--apple-blue)' : 'var(--text-secondary)'}; width:15px; height:15px;"></i>
                                <span style="font-weight:500; font-family:monospace;">${file.name}</span>
                            </div>
                            <span class="text-muted" style="font-size:11px;">${ext.toUpperCase()}</span>
                        </div>
                    `;
                });
                html += '</div>';
                listWrapper.innerHTML = html;
            }

            if (imageEntries.length > 0) {
                if (badgeCount) {
                    badgeCount.textContent = `${imageEntries.length} Page${imageEntries.length > 1 ? 's' : ''}`;
                    badgeCount.style.display = 'inline-block';
                }
                if (viewModes) viewModes.style.display = 'flex';

                extractedImages = [];
                for (let i = 0; i < imageEntries.length; i++) {
                    const entry = imageEntries[i];
                    const blob = await entry.async('blob');
                    const url = URL.createObjectURL(blob);
                    extractedImages.push({
                        name: entry.name.split('/').pop(),
                        url: url,
                        index: i + 1
                    });
                }

                // 1. Build Extracted Pages View (stacked cards)
                if (pagesWrapper) {
                    pagesWrapper.innerHTML = '';
                    extractedImages.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'extracted-page-card';
                        card.style.cssText = 'background:#1c1c1e; border-radius:8px; overflow:hidden; width:100%; box-shadow:0 4px 18px rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.08);';
                        card.innerHTML = `
                            <div class="extracted-page-header" style="background:#2c2c2e; padding:6px 14px; font-size:11.5px; color:#f1f1f1; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:600;">Page / Visual ${item.index} of ${extractedImages.length}</span>
                                <span style="color:#a1a1a6; font-size:11px;">${item.name}</span>
                            </div>
                            <div style="text-align:center; padding:12px; background:#1c1c1e;">
                                <a href="${item.url}" target="_blank" title="Click to view full image in new tab">
                                    <img src="${item.url}" alt="${item.name}" style="max-width:100%; max-height:560px; object-fit:contain; border-radius:4px; cursor:zoom-in;">
                                </a>
                            </div>
                        `;
                        pagesWrapper.appendChild(card);
                    });
                }

                // 2. Build Slide Deck View
                function showSlide(idx) {
                    if (!extractedImages.length) return;
                    currentDeckIdx = Math.max(0, Math.min(idx, extractedImages.length - 1));
                    const cur = extractedImages[currentDeckIdx];

                    if (activeImg) activeImg.src = cur.url;
                    if (activeLink) activeLink.href = cur.url;
                    if (indicator) indicator.textContent = `Page ${cur.index} of ${extractedImages.length}`;
                    if (fileNameEl) fileNameEl.textContent = cur.name;
                    if (prevBtn) prevBtn.disabled = (currentDeckIdx === 0);
                    if (nextBtn) nextBtn.disabled = (currentDeckIdx === extractedImages.length - 1);

                    if (thumbStrip) {
                        thumbStrip.querySelectorAll('.preview-thumb-item').forEach((el, tIdx) => {
                            if (tIdx === currentDeckIdx) {
                                el.style.border = '2px solid var(--apple-blue)';
                                el.style.opacity = '1';
                                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                            } else {
                                el.style.border = '2px solid transparent';
                                el.style.opacity = '0.6';
                            }
                        });
                    }
                }

                if (thumbStrip) {
                    thumbStrip.innerHTML = '';
                    extractedImages.forEach((item, tIdx) => {
                        const thumb = document.createElement('img');
                        thumb.className = 'preview-thumb-item';
                        thumb.src = item.url;
                        thumb.alt = item.name;
                        thumb.onclick = () => showSlide(tIdx);
                        thumbStrip.appendChild(thumb);
                    });
                }

                if (prevBtn) prevBtn.onclick = () => showSlide(currentDeckIdx - 1);
                if (nextBtn) nextBtn.onclick = () => showSlide(currentDeckIdx + 1);

                // 3. Mode Switching
                function setZipMode(mode) {
                    [btnPages, btnDeck, btnFiles].forEach(b => { if (b) b.className = 'btn btn-outline btn-sm'; });
                    if (pagesContainer) pagesContainer.style.display = 'none';
                    if (deckContainer) deckContainer.style.display = 'none';
                    if (listWrapper) listWrapper.style.display = 'none';

                    if (mode === 'pages' && pagesContainer) {
                        if (btnPages) btnPages.className = 'btn btn-primary btn-sm';
                        pagesContainer.style.display = 'block';
                    } else if (mode === 'deck' && deckContainer) {
                        if (btnDeck) btnDeck.className = 'btn btn-primary btn-sm';
                        deckContainer.style.display = 'block';
                        showSlide(currentDeckIdx);
                    } else if (mode === 'files' && listWrapper) {
                        if (btnFiles) btnFiles.className = 'btn btn-primary btn-sm';
                        listWrapper.style.display = 'block';
                    }
                }

                if (btnPages) btnPages.onclick = () => setZipMode('pages');
                if (btnDeck) btnDeck.onclick = () => setZipMode('deck');
                if (btnFiles) btnFiles.onclick = () => setZipMode('files');

                // Default to Extracted Pages View
                setZipMode('pages');
            } else {
                if (listWrapper) listWrapper.style.display = 'block';
            }

            if (window.lucide) lucide.createIcons();
        })
        .catch(err => {
            console.error('ZIP reader error:', err);
            if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Could not inspect archive files. Please download to view.</div>';
        });
})();
</script>
<?php endif; ?>
