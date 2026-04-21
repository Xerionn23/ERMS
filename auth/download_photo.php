<?php
require_once __DIR__ . '/../includes/guards.php';

require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Admin only.';
    exit;
}

require_company();
if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
    http_response_code(403);
    echo 'Company not authorized.';
    exit;
}

$folder = trim((string)($_GET['folder'] ?? ''));
$sub = trim((string)($_GET['sub'] ?? ''));
$file = trim((string)($_GET['file'] ?? ''));

$folder = preg_replace('/[^A-Za-z0-9 _.-]/', '', $folder);
$sub = in_array($sub, ['drug_photos', 'drug_photos_fitted']) ? $sub : '';
$file = preg_replace('/[^A-Za-z0-9 _.,\-()]/', '', $file);

if ($folder === '' || $sub === '' || $file === '' || !preg_match('/\.(jpg|jpeg|png)$/i', $file)) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$exportBase = dirname(__DIR__) . '/export_nuero';
$path = $exportBase . '/' . $folder . '/' . $sub . '/' . $file;

$realBase = realpath($exportBase);
$realPath = realpath($path);

if (!$realBase || !$realPath || strpos($realPath, $realBase) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    echo 'Photo not found.';
    exit;
}

$mime = 'image/jpeg';
if (str_ireplace(['.jpg', '.jpeg'], '', $file) !== $file) {
    $mime = 'image/jpeg';
} elseif (str_ireplace('.png', '', $file) === $file) {
    $mime = 'image/png';
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($realPath)) . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($realPath);
exit;
?>

