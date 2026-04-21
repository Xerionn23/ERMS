<?php
require_once __DIR__ . '/../includes/guards.php';

require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden.';
    exit;
}

require_company();
if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
    http_response_code(403);
    echo 'Forbidden.';
    exit;
}

$folder = trim((string)($_GET['folder'] ?? ''));
$file = trim((string)($_GET['file'] ?? ''));

$folder = preg_replace('/[^A-Za-z0-9 _.-]/', '', $folder);
$file = preg_replace('/[^A-Za-z0-9 _.,\-()]/', '', $file);

if ($folder === '' || $file === '' || strtolower(substr($file, -5)) !== '.docx') {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$exportBase = dirname(__DIR__) . '/export_nuero';
$path = $exportBase . '/' . $folder . '/' . $file;

$realBase = realpath($exportBase);
$realPath = realpath($path);

if (!$realBase || !$realPath || strpos($realPath, $realBase) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($realPath)) . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($realPath);
exit;
