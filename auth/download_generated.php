<?php
require_once __DIR__ . '/../includes/guards.php';

require_login();

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$i = isset($_GET['i']) ? (int)$_GET['i'] : -1;

if ($token === '' || !preg_match('/^[A-Fa-f0-9]{32}$/', $token) || $i < 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid request.';
    exit;
}

$store = $_SESSION['generated_downloads'] ?? null;
if (!is_array($store) || !isset($store[$token]) || !is_array($store[$token])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Download not found or expired.';
    exit;
}

$rec = $store[$token];
$expiresAt = isset($rec['expires_at']) ? (int)$rec['expires_at'] : 0;
if ($expiresAt > 0 && time() > $expiresAt) {
    unset($_SESSION['generated_downloads'][$token]);
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Download expired.';
    exit;
}

$files = $rec['files'] ?? null;
if (!is_array($files) || !isset($files[$i]) || !is_array($files[$i])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found.';
    exit;
}

$filename = (string)($files[$i]['filename'] ?? 'Document.docx');
$path = (string)($files[$i]['path'] ?? '');

if ($path === '' || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File missing.';
    exit;
}

// If this is the last file in the list, remove the token to avoid reuse.
if ($i >= (count($files) - 1)) {
    unset($_SESSION['generated_downloads'][$token]);
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($path);
exit;
