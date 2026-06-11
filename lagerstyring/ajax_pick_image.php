<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/image_search.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF mismatch']);
    exit;
}

$result = download_image_to_uploads($_POST['url'] ?? '');
if ($result['ok']) {
    $result['url'] = UPLOAD_URL . rawurlencode($result['filename']);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
