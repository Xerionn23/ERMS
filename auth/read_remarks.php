<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = dirname(__DIR__) . '/remarks.xlsx';

if (!file_exists($filePath)) {
    echo "File not found: $filePath";
    exit;
}

$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

echo "Contents of remarks.xlsx:\n\n";
foreach ($rows as $i => $row) {
    $line = implode(' | ', array_filter($row, fn($v) => $v !== null && $v !== ''));
    if (trim($line) !== '') {
        echo ($i + 1) . ": $line\n";
    }
}
