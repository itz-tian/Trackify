<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Document.php';
require_once __DIR__ . '/classes/PresentationPreview.php';

$auth = new Auth();
$auth->requireLogin();

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId || $documentId < 1) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$isSlideRequest = isset($_GET['slide']);
if ($isSlideRequest) {
    $slide = filter_input(INPUT_GET, 'slide', FILTER_VALIDATE_INT);
    $cacheKey = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';
    $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
    $expires = filter_input(INPUT_GET, 'expires', FILTER_VALIDATE_INT);
    $grant = preg_match('/\A[a-f0-9]{64}\z/', $token)
        ? ($_SESSION['presentation_preview_tokens'][$token] ?? null)
        : null;

    if (
        !$slide
        || !$expires
        || $expires < time()
        || !is_array($grant)
        || (int)($grant['document_id'] ?? 0) !== $documentId
        || !hash_equals((string)($grant['cache_key'] ?? ''), $cacheKey)
        || (int)($grant['expires'] ?? 0) !== $expires
    ) {
        http_response_code(403);
        exit('Invalid or expired slide URL.');
    }

    try {
        $renderer = new PresentationPreview();
        $imagePath = $renderer->getCachedSlide($slide, $cacheKey);
    } catch (InvalidArgumentException $error) {
        http_response_code(400);
        exit($error->getMessage());
    } catch (RuntimeException $error) {
        http_response_code(404);
        exit($error->getMessage());
    }

    $etag = '"' . $cacheKey . '-' . $slide . '-' . filesize($imagePath) . '-' . filemtime($imagePath) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($imagePath));
    header('Cache-Control: private, max-age=3600');
    header('ETag: ' . $etag);
    header("X-Content-Type-Options: nosniff");
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    readfile($imagePath);
    exit;
}

$document = (new Document())->getById($documentId);
if (!$document) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['error' => 'Presentation not found.']));
}

$extension = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['ppt', 'pptx'], true)) {
    http_response_code(415);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['error' => 'This document is not a PowerPoint presentation.']));
}

$uploadRoot = realpath(UPLOAD_PATH);
$sourcePath = realpath(__DIR__ . '/' . ltrim($document['file_path'], '/\\'));
$uploadPrefix = $uploadRoot === false ? '' : rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($sourcePath === false || $uploadPrefix === '' || !str_starts_with($sourcePath, $uploadPrefix) || !is_file($sourcePath)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['error' => 'The presentation file could not be found.']));
}

try {
    $renderer = new PresentationPreview();
    $preview = $renderer->create($sourcePath, $documentId);
    $expires = time() + 3600;
    $tokens = $_SESSION['presentation_preview_tokens'] ?? [];
    foreach ($tokens as $token => $grant) {
        if (!is_array($grant) || (int)($grant['expires'] ?? 0) < time()) {
            unset($tokens[$token]);
        }
    }

    $token = bin2hex(random_bytes(32));
    $tokens[$token] = [
        'document_id' => $documentId,
        'cache_key' => $preview['key'],
        'expires' => $expires,
    ];
    $_SESSION['presentation_preview_tokens'] = $tokens;
    $signedSlides = [];

    foreach ($preview['slides'] as $slideUrl) {
        $signedSlides[] = $slideUrl . '&expires=' . $expires . '&token=' . $token;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['count' => $preview['count'], 'slides' => $signedSlides], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('Presentation preview failed: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error instanceof RuntimeException ? $error->getMessage() : 'The presentation could not be rendered.']);
}
