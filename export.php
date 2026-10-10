<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';

$auth = new Auth();
$auth->requireLogin();

$filters = [
    'status'        => $_GET['status'] ?? '',
    'category_id'   => $_GET['category_id'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
    'priority'      => $_GET['priority'] ?? '',
    'search'        => $_GET['search'] ?? '',
    'date'          => $_GET['date'] ?? '',
    'date_from'     => $_GET['date_from'] ?? '',
    'date_to'       => $_GET['date_to'] ?? '',
    'uploader_id'   => $_GET['uploader_id'] ?? '',
];

$docClass = new Document();
$docClass->exportCsv($filters);
