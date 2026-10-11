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
<script>
(function() {
    function initPptxPreview() {
    const loading = document.getElementById('pptxLoading');
    const pagesContainer = document.getElementById('pptxPagesContainer');
    const pagesWrapper = document.getElementById('pptxPagesWrapper');
    const deckContainer = document.getElementById('pptxDeckContainer');
    const gridContainer = document.getElementById('pptxGridContainer');
    const textContainer = document.getElementById('pptxTextContainer');
    const viewModes = document.getElementById('pptxViewModes');
    const countBadge = document.getElementById('pptxImageCountBadge');

    const activeImg = document.getElementById('pptxActiveImage');
    const activeLink = document.getElementById('pptxActiveImageLink');
    const prevBtn = document.getElementById('pptxPrevBtn');
    const nextBtn = document.getElementById('pptxNextBtn');
    const indicator = document.getElementById('pptxSlideIndicator');
    const fileNameEl = document.getElementById('pptxCurrentFileName');
    const thumbStrip = document.getElementById('pptxThumbStrip');

    const btnPages = document.getElementById('pptxBtnPages');
    const btnDeck = document.getElementById('pptxBtnDeck');
    const btnGrid = document.getElementById('pptxBtnGrid');

    let slideImages = [];
    let currentSlideIdx = 0;

    // Early guard: JSZip must be available before we even fetch
    if (typeof JSZip === 'undefined') {
        if (loading) loading.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Slide viewer library (JSZip) could not be loaded. Please download the file to view.</div>';
        return;
    }

    fetch('<?= APP_URL . '/preview.php?id=' . (int)$doc['id'] ?>')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.arrayBuffer();
        })
        .then(buffer => {
            return JSZip.loadAsync(buffer);
        })
        .then(async zip => {
            if (loading) loading.style.display = 'none';

            // Find all slide images, drawings, and media files
            const mediaFiles = [];
            zip.forEach((path, entry) => {
                if (!entry.dir && /^ppt\/media\//i.test(path)) {
                    mediaFiles.push(entry);
                }
            });

            // Natural sort order (image1, image2, image3...)
            mediaFiles.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

            if (mediaFiles.length > 0) {
                if (countBadge) {
                    countBadge.textContent = `${mediaFiles.length} Slide Visual${mediaFiles.length > 1 ? 's' : ''}`;
                    countBadge.style.display = 'inline-block';
                }
                if (viewModes) viewModes.style.display = 'flex';

                // Extract image blobs and create object URLs
                slideImages = [];
                for (let i = 0; i < mediaFiles.length; i++) {
                    const entry = mediaFiles[i];
                    const blob = await entry.async('blob');
                    const url = URL.createObjectURL(blob);
                    slideImages.push({
                        name: entry.name.split('/').pop(),
                        url: url,
                        index: i + 1
                    });
                }

                // 1. Build Extracted Slide Pages (Scrollable Stack)
                if (pagesWrapper) {
                    pagesWrapper.innerHTML = '';
                    slideImages.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'extracted-page-card';
                        card.style.cssText = 'background:#1c1c1e; border-radius:8px; overflow:hidden; width:100%; box-shadow:0 4px 18px rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.08);';
                        card.innerHTML = `
                            <div class="extracted-page-header" style="background:#2c2c2e; padding:6px 14px; font-size:11.5px; color:#f1f1f1; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:600;">Slide / Visual ${item.index} of ${slideImages.length}</span>
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
                    if (!slideImages.length) return;
                    currentSlideIdx = Math.max(0, Math.min(idx, slideImages.length - 1));
                    const cur = slideImages[currentSlideIdx];

                    if (activeImg) activeImg.src = cur.url;
                    if (activeLink) activeLink.href = cur.url;
                    if (indicator) indicator.textContent = `Slide ${cur.index} of ${slideImages.length}`;
                    if (fileNameEl) fileNameEl.textContent = cur.name;
                    if (prevBtn) prevBtn.disabled = (currentSlideIdx === 0);
                    if (nextBtn) nextBtn.disabled = (currentSlideIdx === slideImages.length - 1);

                    // Update thumbnail active states
                    if (thumbStrip) {
                        thumbStrip.querySelectorAll('.preview-thumb-item').forEach((el, tIdx) => {
                            if (tIdx === currentSlideIdx) {
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
                    slideImages.forEach((item, tIdx) => {
                        const thumb = document.createElement('img');
                        thumb.className = 'preview-thumb-item';
                        thumb.src = item.url;
                        thumb.alt = item.name;
                        thumb.onclick = () => showSlide(tIdx);
                        thumbStrip.appendChild(thumb);
                    });
                }

                if (prevBtn) prevBtn.onclick = () => showSlide(currentSlideIdx - 1);
                if (nextBtn) nextBtn.onclick = () => showSlide(currentSlideIdx + 1);

                // 3. Mode Toggles
                function setMode(mode) {
                    [btnPages, btnDeck, btnGrid].forEach(b => { if (b) b.className = 'btn btn-outline btn-sm'; });
                    if (pagesContainer) pagesContainer.style.display = 'none';
                    if (deckContainer) deckContainer.style.display = 'none';
                    if (gridContainer) gridContainer.style.display = 'none';

                    if (mode === 'pages' && pagesContainer) {
                        if (btnPages) btnPages.className = 'btn btn-primary btn-sm';
                        pagesContainer.style.display = 'block';
                    } else if (mode === 'deck' && deckContainer) {
                        if (btnDeck) btnDeck.className = 'btn btn-primary btn-sm';
                        deckContainer.style.display = 'block';
                        showSlide(currentSlideIdx);
                    } else if (mode === 'grid' && gridContainer) {
                        if (btnGrid) btnGrid.className = 'btn btn-primary btn-sm';
                        gridContainer.style.display = 'block';
                    }
                }

                if (btnPages) btnPages.onclick = () => setMode('pages');
                if (btnDeck) btnDeck.onclick = () => setMode('deck');
                if (btnGrid) btnGrid.onclick = () => setMode('grid');

                // Default to All Slide Pages View
                setMode('pages');
            } else {
                if (textContainer) {
                    textContainer.innerHTML = '<div class="text-muted text-center" style="padding:30px;">Could not extract slides from this presentation. Please download the file to view.</div>';
                    textContainer.style.display = 'block';
                }
            }
        })
        .catch(err => {
            console.error('PPTX parse error:', err);
            if (loading) {
                loading.innerHTML = `
                    <div style="padding:20px; text-align:center;">
                        <div style="color:var(--text-secondary); margin-bottom:12px; font-size:13px;">This presentation file could not be parsed directly.</div>
                        <a href="<?= APP_URL . '/download.php?id=' . (int)$doc['id'] ?>" class="btn btn-primary btn-sm">
                            <i data-lucide="download"></i> Download Presentation
                        </a>
                    </div>
                `;
                if (window.lucide) lucide.createIcons();
            }
        });
    }

    // Run after DOM is fully loaded to ensure JSZip script has executed
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPptxPreview);
    } else {
        initPptxPreview();
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
