<?php
/**
 * Trackify — Document View Modal Scripts & Folder-to-Zip Handler
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');
?>
<script src="<?= APP_URL ?>/assets/js/jszip.min.js"></script>
<script>
(function() {
    const verForm         = document.getElementById('versionForm');
    const verDropZone     = document.getElementById('verDropZone');
    const verFileInput    = document.getElementById('verFileInput');
    const verFolderInput  = document.getElementById('verFolderInput');
    const verChooseFileBtn = document.getElementById('verChooseFileBtn');
    const verChooseFolderBtn = document.getElementById('verChooseFolderBtn');
    const verFilePreview  = document.getElementById('verFilePreview');
    const verFileName     = document.getElementById('verFileName');
    const verFileSize     = document.getElementById('verFileSize');
    const verZipProgress  = document.getElementById('verZipProgress');
    const verFolderHint   = document.getElementById('verFolderHint');
    const verSubmitBtn    = document.getElementById('verSubmitBtn');

    let isZipping = false;

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        const units = ['B','KB','MB','GB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + units[i];
    }

    function setInputFiles(input, fileList) {
        try {
            const dt = new DataTransfer();
            fileList.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (e) {
            console.error('DataTransfer assignment failed:', e);
        }
    }

    window.resetVerFile = function() {
        isZipping = false;
        verFileInput.value = '';
        verFolderInput.value = '';
        if (verFilePreview) verFilePreview.style.display = 'none';
        if (verFolderHint) verFolderHint.style.display = 'none';
        if (verZipProgress) verZipProgress.style.display = 'none';
        if (verSubmitBtn) verSubmitBtn.disabled = false;
        if (verDropZone) verDropZone.style.borderColor = 'var(--border-card)';
    };

    function showSelectedFile(file, note) {
        if (!file) return;
        if (verFileName) verFileName.textContent = file.name;
        if (verFileSize) verFileSize.textContent = formatBytes(file.size);
        if (verFilePreview) verFilePreview.style.display = 'inline-flex';
        if (note && verFolderHint) {
            verFolderHint.innerHTML = note;
            verFolderHint.style.display = 'block';
        } else if (verFolderHint) {
            verFolderHint.style.display = 'none';
        }
        if (window.lucide) lucide.createIcons();
    }

    verDropZone?.addEventListener('click', () => verFileInput.click());
    verChooseFileBtn?.addEventListener('click', (e) => { e.stopPropagation(); verFileInput.click(); });
    verChooseFolderBtn?.addEventListener('click', (e) => { e.stopPropagation(); verFolderInput.click(); });

    verFileInput?.addEventListener('change', () => {
        verFolderInput.value = '';
        if (verFileInput.files && verFileInput.files.length) {
            showSelectedFile(verFileInput.files[0]);
        } else {
            resetVerFile();
        }
    });

    verFolderInput?.addEventListener('change', async () => {
        const rawFiles = Array.from(verFolderInput.files || []);
        if (!rawFiles.length) {
            resetVerFile();
            return;
        }

        let folderName = 'archive';
        const firstPath = rawFiles[0].webkitRelativePath || rawFiles[0].name;
        const parts = firstPath.split(/[\\/]/);
        if (parts.length > 1 && parts[0].trim() !== '') {
            folderName = parts[0].trim();
        }

        if (typeof JSZip === 'undefined') {
            alert('Folder compression is currently unavailable because the ZIP library could not load. Please upload a file directly.');
            return;
        }

        const allowedExts = new Set(['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','png','jpg','jpeg','zip']);
        const supportedFiles = rawFiles.filter(f => {
            const ext = f.name.toLowerCase().split('.').pop();
            return allowedExts.has(ext);
        });
        const skippedFiles = rawFiles.filter(f => !supportedFiles.includes(f));

        if (!supportedFiles.length) {
            alert('The selected folder does not contain any supported documents (PDF, Word, Excel, PPT, Text, Images, ZIP).');
            resetVerFile();
            return;
        }

        try {
            isZipping = true;
            if (verSubmitBtn) verSubmitBtn.disabled = true;
            if (verZipProgress) {
                verZipProgress.style.display = 'block';
                if (window.lucide) lucide.createIcons();
            }
            if (verFolderHint) {
                verFolderHint.style.display = 'block';
                verFolderHint.textContent = `Packaging ${supportedFiles.length} file(s) from "${folderName}" into a ZIP archive…`;
            }

            const zip = new JSZip();
            for (const file of supportedFiles) {
                const relPath = file.webkitRelativePath || file.name;
                zip.file(relPath, file);
            }

            const zipBlob = await zip.generateAsync({
                type: 'blob',
                compression: 'DEFLATE',
                compressionOptions: { level: 6 }
            });

            const zipFile = new File([zipBlob], `${folderName}.zip`, { type: 'application/zip' });
            setInputFiles(verFileInput, [zipFile]);
            verFolderInput.value = '';

            if (verZipProgress) verZipProgress.style.display = 'none';
            if (verSubmitBtn) verSubmitBtn.disabled = false;
            isZipping = false;

            let note = `<i data-lucide="archive" style="display:inline; width:13px; height:13px;"></i> Folder "<strong>${folderName}</strong>" compressed into <code>${folderName}.zip</code> (${supportedFiles.length} files).`;
            if (skippedFiles.length) {
                note += `<br><span style="color:var(--apple-orange);">${skippedFiles.length} unsupported file(s) skipped.</span>`;
            }
            showSelectedFile(zipFile, note);
        } catch (err) {
            console.error('Error zipping folder in version modal:', err);
            if (verZipProgress) verZipProgress.style.display = 'none';
            if (verSubmitBtn) verSubmitBtn.disabled = false;
            isZipping = false;
            alert('Could not compress the selected folder into a ZIP archive.');
            resetVerFile();
        }
    });

    // Drag and drop into version modal dropzone
    ['dragenter', 'dragover'].forEach(name => {
        verDropZone?.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            verDropZone.style.borderColor = 'var(--apple-blue)';
        });
    });

    ['dragleave', 'dragend', 'drop'].forEach(name => {
        verDropZone?.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            verDropZone.style.borderColor = 'var(--border-card)';
        });
    });

    verDropZone?.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            try {
                verFileInput.files = e.dataTransfer.files;
            } catch (err) {}
            showSelectedFile(e.dataTransfer.files[0]);
        }
    });

    verForm?.addEventListener('submit', function(e) {
        if (isZipping) {
            e.preventDefault();
            alert('Please wait until the folder finishes compressing into a ZIP file.');
            return false;
        }
        if (!verFileInput.files || !verFileInput.files.length) {
            e.preventDefault();
            alert('Please select a file or folder for the new document version.');
            return false;
        }
        const maxBytes = 20 * 1024 * 1024;
        if (verFileInput.files[0].size > maxBytes) {
            e.preventDefault();
            alert(`The chosen file is ${formatBytes(verFileInput.files[0].size)}, exceeding the 20MB limit.`);
            return false;
        }
    });
})();
</script>
