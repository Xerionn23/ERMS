<?php
header('Content-Type: application/json; charset=utf-8');

$folderName = trim((string)($_GET['folder_name'] ?? ''));
$folderName = preg_replace('/[^A-Za-z0-9 _.-]/', '', $folderName);
$folderName = trim(preg_replace('/\s+/', ' ', $folderName));

if ($folderName === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$exportBase = dirname(__DIR__) . '/export_nuero';
$folderPath = $exportBase . '/' . $folderName;

echo json_encode(['exists' => is_dir($folderPath)]);
