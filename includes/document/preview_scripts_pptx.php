<?php

defined('APP_NAME') or exit('Direct script access denied.');

if (!in_array($ext, ['ppt', 'pptx'], true)) {
    return;
}

?>
<style>
.pptx-slide-card {
    margin: 0 auto 20px;
    overflow: hidden;
    border: 1px solid var(--border-hairline);
    border-radius: 10px;
    background: var(--bg-card);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
}

.pptx-slide-card-header {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border-hairline);
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
}

.pptx-slide-image {
    display: block;
    width: 100%;
    height: auto;
    background: #fff;
}

.pptx-thumb-btn.active {
    border-color: var(--apple-blue);
    background: var(--apple-blue);
    color: #fff;
}

.pptx-slide-error {
    margin: 16px;
    color: var(--text-primary);
    font-size: 14px;
}
</style>
<script src="<?= APP_URL ?>/assets/js/presentation-preview.js?v=4" defer></script>
