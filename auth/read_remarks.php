<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/separate male and female.xlsx';

if (!file_exists($filePath)) {
    echo "File not found: $filePath";
    exit;
}

$spreadsheet = IOFactory::load($filePath);
function findSheetByNames($spreadsheet, array $names)
{
    $names = array_map('strtolower', $names);
    foreach ($spreadsheet->getSheetNames() as $name) {
        if (in_array(strtolower($name), $names, true)) {
            return $spreadsheet->getSheetByName($name);
        }
    }
    return null;
}

function dumpSheetRows($sheet, string $label): void
{
    if ($sheet === null) {
        echo $label . ": (sheet not found)\n\n";
        return;
    }
    $rows = $sheet->toArray();
    echo $label . ":\n\n";
    foreach ($rows as $i => $row) {
        $line = implode(' | ', array_filter($row, fn($v) => $v !== null && $v !== ''));
        if (trim($line) !== '') {
            echo ($i + 1) . ": $line\n";
        }
    }
    echo "\n";
}

$maleSheet = findSheetByNames($spreadsheet, ['male', 'males', 'man', 'men']);
$femaleSheet = findSheetByNames($spreadsheet, ['female', 'females', 'woman', 'women']);

dumpSheetRows($maleSheet ?: $spreadsheet->getActiveSheet(), 'Male sheet');
dumpSheetRows($femaleSheet ?: $spreadsheet->getActiveSheet(), 'Female sheet');
