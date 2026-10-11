<?php
/**
 * Trackify — Document Preview Scripts Loader
 * Modular component extracted from document_view.php
 */
defined('APP_NAME') or exit('Direct script access denied.');

if ($hasDiskFile) {
    include __DIR__ . '/preview_scripts_pdf.php';
    include __DIR__ . '/preview_scripts_office.php';
}
