<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = dirname(__DIR__) . '/remarks.xlsx';
$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

$remarks = [];
foreach ($rows as $row) {
    $text = trim((string)($row[0] ?? ''));
    if ($text !== '' && !is_numeric($text)) {
        $remarks[] = $text;
    }
}

echo "<?php\n";
echo "// Auto-generated remarks list from remarks.xlsx\n";
echo "return [\n";
foreach ($remarks as $i => $r) {
    $escaped = str_replace("'", "\\'", $r);
    echo "    " . json_encode($r, JSON_UNESCAPED_UNICODE) . ",\n";
}
echo "];\n";
