<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('admin');
require_company();

if ((string)($_SESSION['company'] ?? '') !== 'jubecer') {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$guardId = isset($_GET['guard_id']) ? (int)$_GET['guard_id'] : 0;
$reqTypeId = isset($_GET['requirement_type_id']) ? (int)$_GET['requirement_type_id'] : 0;

if ($guardId <= 0 || $reqTypeId <= 0) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT gr.document_path, gr.document_original_name, gr.document_mime, gr.document_size
     FROM guard_requirements gr
     WHERE gr.guard_id = :guard_id
       AND gr.requirement_type_id = :requirement_type_id
     LIMIT 1'
);
$stmt->execute([
    'guard_id' => $guardId,
    'requirement_type_id' => $reqTypeId,
]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$relPath = (string)($row['document_path'] ?? '');
if ($relPath === '' || strpos($relPath, 'uploads/guard_requirements/') !== 0) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$fullPath = realpath(__DIR__ . '/../' . $relPath);
$allowedBase = realpath(__DIR__ . '/../uploads/guard_requirements');

if (!$fullPath || !$allowedBase || strpos($fullPath, $allowedBase) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$originalName = (string)($row['document_original_name'] ?? 'document');
$mime = (string)($row['document_mime'] ?? '');
if ($mime === '') {
    $mime = 'application/octet-stream';
}

$size = (int)($row['document_size'] ?? 0);
if ($size <= 0) {
    $size = (int)filesize($fullPath);
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $originalName) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($fullPath);
exit;
