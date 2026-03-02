<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('employee');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

function get_next_remark(string $sex): string
{
    $remarksFile = __DIR__ . '/remarks_list.php';
    $indexFile = __DIR__ . '/remarks_index.txt';

    $remarks = require $remarksFile;
    if (!is_array($remarks) || empty($remarks)) {
        return '';
    }

    $index = 0;
    if (file_exists($indexFile)) {
        $index = (int)file_get_contents($indexFile);
    }

    if ($index >= count($remarks)) {
        $index = 0;
    }

    $remark = $remarks[$index];

    // Update index for next person
    file_put_contents($indexFile, (string)($index + 1));

    // Replace gender words based on sex
    $sexLower = strtolower(trim($sex));
    if ($sexLower === 'female') {
        $remark = str_replace(
            [' he ', ' He ', ' his ', ' His ', ' him ', ' Him ', ' himself ', ' Himself '],
            [' she ', ' She ', ' her ', ' Her ', ' her ', ' Her ', ' herself ', ' Herself '],
            ' ' . $remark . ' '
        );
        $remark = trim($remark);
    }

    return $remark;
}

function replace_docx_placeholders(string $docxPath, array $values): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is required to post-process the DOCX file.');
    }

    $replaceInXmlRuns = function (string $xml, string $macro, string $replacement): string {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (@$dom->loadXML($xml) !== true) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $nodes = $xpath->query('//w:t');
        if (!$nodes || $nodes->length === 0) {
            return $dom->saveXML();
        }

        $texts = [];
        for ($i = 0; $i < $nodes->length; $i++) {
            $n = $nodes->item($i);
            $texts[] = $n ? (string)$n->nodeValue : '';
        }

        $macroLen = strlen($macro);
        if ($macroLen === 0) {
            return $dom->saveXML();
        }

        $i = 0;
        while ($i < count($texts)) {
            $concat = '';
            $j = $i;

            while ($j < count($texts) && strlen($concat) < $macroLen) {
                $concat .= $texts[$j];
                $j++;
            }

            if (substr($concat, 0, $macroLen) === $macro) {
                $firstNode = $nodes->item($i);
                if ($firstNode) {
                    $firstNode->nodeValue = $replacement;
                }

                for ($k = $i + 1; $k < $j; $k++) {
                    $n = $nodes->item($k);
                    if ($n) {
                        $n->nodeValue = '';
                    }
                }

                $texts[$i] = $replacement;
                for ($k = $i + 1; $k < $j; $k++) {
                    $texts[$k] = '';
                }

                $i = $j;
                continue;
            }

            $i++;
        }

        return $dom->saveXML();
    };

    $zip = new ZipArchive();
    $res = $zip->open($docxPath);
    if ($res !== true) {
        throw new RuntimeException('Failed to open generated DOCX for post-processing.');
    }

    $candidateFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || !isset($stat['name'])) continue;
        $name = (string)$stat['name'];

        if (substr($name, -4) !== '.xml') {
            continue;
        }

        if (substr($name, 0, 5) === 'word/' || substr($name, 0, 10) === 'customXml/') {
            $candidateFiles[] = $name;
        }
    }

    foreach ($candidateFiles as $name) {
        $xml = $zip->getFromName($name);
        if ($xml === false) continue;

        foreach ($values as $key => $val) {
            $macro = '{{' . $key . '}}';
            $safeVal = htmlspecialchars((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8');

            $xml = str_replace($macro, $safeVal, $xml);

            $xml = $replaceInXmlRuns($xml, $macro, $safeVal);
        }

        $zip->addFromString($name, $xml);
    }

    $zip->close();
}

$required = [
    'contact_no',
    'np_clearance',
    'document_date',
    'last_name',
    'first_name',
    'age',
    'civil_status',
    'home_address',
    'occupation',
    'position',
    'educational',
    'religion',
    'company_requesting_agency',
    'date_of_birth',
];

foreach ($required as $key) {
    if (!isset($_POST[$key]) || trim((string)$_POST[$key]) === '') {
        http_response_code(400);
        echo 'Missing required field: ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        exit;
    }
}

$templatePath = __DIR__ . '/../TEMPLATE.docx';
if (!is_file($templatePath)) {
    http_response_code(500);
    echo 'Template file not found.';
    exit;
}

if (!is_readable($templatePath)) {
    http_response_code(500);
    echo 'Template file is not readable.';
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Composer autoload not found. Please run composer install.';
    exit;
}

require_once $autoload;

use PhpOffice\PhpWord\TemplateProcessor;

function norm_text($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return $value;
}

function up(string $value): string
{
    $value = norm_text($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($value, 'UTF-8');
    }

    return strtoupper($value);
}

function middle_initial(string $value): string
{
    $value = norm_text($value);
    if ($value === '') {
        return '';
    }

    $firstChar = mb_substr($value, 0, 1, 'UTF-8');
    $firstChar = up($firstChar);
    if ($firstChar === '') {
        return '';
    }

    return $firstChar . '.';
}

function normalize_contact_no(string $value): string
{
    $raw = preg_replace('/\s+/u', '', trim($value));
    $digits = preg_replace('/\D+/', '', $raw);

    if ($digits === '') {
        return '';
    }

    if (substr($digits, 0, 2) === '63') {
        $digits = '0' . substr($digits, 2);
    }

    if (substr($raw, 0, 3) === '+63' && substr($digits, 0, 1) !== '0') {
        $digits = '0' . $digits;
    }

    if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
        $digits = '0' . $digits;
    }

    return $digits;
}

function fmt_long_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return $value;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('F j, Y', $ts);
}

$values = [
    'contact_no' => normalize_contact_no((string)$_POST['contact_no']),
    'np_clearance' => norm_text($_POST['np_clearance']),
    'document_date' => fmt_long_date(norm_text($_POST['document_date'])),
    'last_name' => up((string)$_POST['last_name']),
    'first_name' => up((string)$_POST['first_name']),
    'middle_name' => middle_initial((string)($_POST['middle_name'] ?? '')),
    'suffix' => up((string)($_POST['suffix'] ?? '')),
    'age' => norm_text($_POST['age']),
    'sex' => norm_text($_POST['sex']),
    'civil_status' => norm_text($_POST['civil_status']),
    'home_address' => norm_text($_POST['home_address']),
    'occupation' => norm_text($_POST['occupation']),
    'position' => norm_text($_POST['position']),
    'educational' => norm_text($_POST['educational']),
    'religion' => norm_text($_POST['religion']),
    'company_requesting_agency' => norm_text($_POST['company_requesting_agency']),
    'date_of_birth' => fmt_long_date(norm_text($_POST['date_of_birth'])),
];

$fullName = $values['last_name'] . ', ' . $values['first_name'];
if (trim($values['middle_name']) !== '') {
    $fullName = $fullName . ' ' . $values['middle_name'];
}
if (trim($values['suffix']) !== '') {
    $fullName = $fullName . ' ' . $values['suffix'];
}
$fullName = trim($fullName);

$remarkText = get_next_remark($values['sex']);

$values += [
    'name' => $fullName,
    'full_name' => $fullName,
    'remark' => $remarkText,
    'remarks' => $remarkText,
];

$values += [
    'document_date_raw' => norm_text($_POST['document_date']),
    'date_of_birth_raw' => norm_text($_POST['date_of_birth']),
];

$values += [
    'contact no' => $values['contact_no'],
    'np clearance' => $values['np_clearance'],
    'document date' => $values['document_date'],
    'civil status' => $values['civil_status'],
    'home address' => $values['home_address'],
    'company requesting agency' => $values['company_requesting_agency'],
    'date of birth' => $values['date_of_birth'],
];

try {
    $folderName = trim((string)($_POST['folder_name'] ?? ''));
    $folderName = preg_replace('/[^A-Za-z0-9 _.-]/', '', $folderName);
    $folderName = trim(preg_replace('/\s+/', ' ', $folderName));
    if ($folderName === '') {
        $folderName = 'Default';
    }

    $exportBase = dirname(__DIR__) . '/export_nuero';
    $exportDir = $exportBase . '/' . $folderName;

    if (!is_dir($exportBase)) {
        if (!@mkdir($exportBase, 0777, true)) {
            throw new RuntimeException('Failed to create export directory.');
        }
    }

    if (!is_dir($exportDir)) {
        if (!@mkdir($exportDir, 0777, true)) {
            throw new RuntimeException('Failed to create folder: ' . $folderName);
        }
    }

    // Check for duplicate person in the folder
    $existingFiles = glob($exportDir . '/*.docx');
    if ($existingFiles) {
        $newNameLower = strtolower(trim($values['last_name'] . ' ' . $values['first_name'] . ' ' . $values['middle_name'] . ' ' . $values['suffix']));
        $newNameLower = preg_replace('/\s+/', ' ', $newNameLower);

        foreach ($existingFiles as $existingFile) {
            $existingName = basename($existingFile, '.docx');
            $existingNameLower = strtolower(trim(preg_replace('/\s+/', ' ', $existingName)));

            if ($existingNameLower === $newNameLower) {
                throw new RuntimeException('Duplicate entry: A document for this person already exists in this folder.');
            }
        }
    }

    $template = new TemplateProcessor($templatePath);
    $template->setMacroChars('{{', '}}');

    foreach ($values as $key => $val) {
        $template->setValue($key, $val);
    }

    $safeName = preg_replace('/[^A-Za-z0-9 _.,-]/', '', $fullName);
    $safeName = trim(preg_replace('/\s+/', ' ', $safeName));
    if ($safeName === '') {
        $safeName = 'NeuroDocument';
    }

    $filename = $safeName . '.docx';

    $savePath = $exportDir . '/' . $filename;

    $template->saveAs($savePath);

    replace_docx_placeholders($savePath, $values);

    if (!is_file($savePath)) {
        throw new RuntimeException('DOCX file was not written.');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Content-Length: ' . filesize($savePath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    readfile($savePath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[ERMS][generate_neuro_document] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to generate document. Please contact the administrator.';
    exit;
}
