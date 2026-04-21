<?php
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'employee' && $role !== 'admin') {
    header('Location: ../pages/home.php');
    exit;
}

if ($role === 'admin') {
    require_company();
    if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
        header('Location: ../pages/home.php');
        exit;
    }
}
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/**
 * Ensure the generated_documents table exists.
 */
function ensure_generated_documents_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS generated_documents ("
        . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
        . "company VARCHAR(40) NOT NULL,"
        . "document_type ENUM('neuro','drug_test') NOT NULL,"
        . "document_date DATE NULL,"
        . "full_name VARCHAR(180) NOT NULL,"
        . "purpose VARCHAR(40) NULL,"
        . "purpose_specify VARCHAR(120) NULL,"
        . "folder_name VARCHAR(120) NOT NULL,"
        . "file_name VARCHAR(255) NOT NULL,"
        . "file_path VARCHAR(255) NOT NULL,"
        . "created_by_user_id INT UNSIGNED NULL,"
        . "created_by_employee_id VARCHAR(50) NULL,"
        . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,"
        . "PRIMARY KEY (id),"
        . "UNIQUE KEY uq_generated_documents_file (company, file_path),"
        . "KEY idx_generated_documents_company (company),"
        . "KEY idx_generated_documents_doc_date (document_date),"
        . "KEY idx_generated_documents_type (document_type),"
        . "KEY idx_generated_documents_created_at (created_at)"
        . ") ENGINE=InnoDB"
    );
}

/**
 * Ensure the attendance_records table exists.
 */
function ensure_attendance_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_records ("
        . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
        . "company VARCHAR(40) NOT NULL,"
        . "folder_name VARCHAR(120) NOT NULL,"
        . "document_type ENUM('neuro','drug_test') NOT NULL,"
        . "document_date DATE NULL,"
        . "first_name VARCHAR(80) NOT NULL,"
        . "middle_name VARCHAR(40) NULL,"
        . "last_name VARCHAR(80) NOT NULL,"
        . "full_name VARCHAR(200) NOT NULL,"
        . "home_address VARCHAR(200) NULL,"
        . "agency VARCHAR(120) NULL,"
        . "detachment VARCHAR(120) NULL,"
        . "birth_date DATE NULL,"
        . "gender VARCHAR(20) NULL,"
        . "created_by_user_id INT UNSIGNED NULL,"
        . "created_by_employee_id VARCHAR(50) NULL,"
        . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,"
        . "PRIMARY KEY (id),"
        . "KEY idx_attendance_company (company),"
        . "KEY idx_attendance_folder (folder_name),"
        . "KEY idx_attendance_type (document_type),"
        . "KEY idx_attendance_doc_date (document_date),"
        . "KEY idx_attendance_created_at (created_at),"
        . "KEY idx_attendance_name (last_name, first_name)"
        . ") ENGINE=InnoDB"
    );
}

function ensure_attendance_columns(PDO $pdo): void
{
    $cols = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM attendance_records');
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[strtolower((string)($row['Field'] ?? ''))] = true;
        }
    }

    if (!isset($cols['folder_name'])) {
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN folder_name VARCHAR(120) NOT NULL DEFAULT '' AFTER company");
    }

    if (!isset($cols['detachment'])) {
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN detachment VARCHAR(120) NULL AFTER agency");
    }

    $indexes = [];
    $idxStmt = $pdo->query('SHOW INDEX FROM attendance_records');
    if ($idxStmt) {
        foreach ($idxStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[(string)($row['Key_name'] ?? '')] = true;
        }
    }
    if (!isset($indexes['idx_attendance_folder'])) {
        $pdo->exec('CREATE INDEX idx_attendance_folder ON attendance_records (folder_name)');
    }
}

/**
 * Normalize a YYYY-MM-DD string into a safe DATE value.
 */
function normalize_document_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d');
}

/**
 * Persist a generated document row into MySQL.
 */
function save_generated_document(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO generated_documents '
        . '(company, document_type, document_date, full_name, purpose, purpose_specify, folder_name, file_name, file_path, created_by_user_id, created_by_employee_id) '
        . 'VALUES '
        . '(:company, :document_type, :document_date, :full_name, :purpose, :purpose_specify, :folder_name, :file_name, :file_path, :created_by_user_id, :created_by_employee_id) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'document_date = VALUES(document_date), '
        . 'full_name = VALUES(full_name), '
        . 'purpose = VALUES(purpose), '
        . 'purpose_specify = VALUES(purpose_specify), '
        . 'document_type = VALUES(document_type), '
        . 'folder_name = VALUES(folder_name), '
        . 'file_name = VALUES(file_name), '
        . 'created_by_user_id = VALUES(created_by_user_id), '
        . 'created_by_employee_id = VALUES(created_by_employee_id)'
    );
    $stmt->execute($data);
}

/**
 * Persist an attendance record linked to document generation.
 */
function save_attendance_record(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO attendance_records '
        . '(company, folder_name, document_type, document_date, first_name, middle_name, last_name, full_name, home_address, agency, detachment, birth_date, gender, created_by_user_id, created_by_employee_id) '
        . 'VALUES '
        . '(:company, :folder_name, :document_type, :document_date, :first_name, :middle_name, :last_name, :full_name, :home_address, :agency, :detachment, :birth_date, :gender, :created_by_user_id, :created_by_employee_id)'
    );
    $stmt->execute($data);
}

function next_series_code(): string
{
    $file = __DIR__ . '/series_code.txt';
    $cur = 'AA';
    if (is_file($file)) {
        $v = trim((string)@file_get_contents($file));
        if (preg_match('/^[A-Z]{2}$/', $v)) {
            $cur = $v;
        }
    }

    $a = ord($cur[0]) - 65;
    $b = ord($cur[1]) - 65;
    $n = $a * 26 + $b;
    $n2 = ($n + 1) % (26 * 26);
    $next = chr(65 + intdiv($n2, 26)) . chr(65 + ($n2 % 26));

    @file_put_contents($file, $next);
    return $cur;
}

/**
 * Get the next drug test batch counters, resetting when the batch key changes.
 * Returns [seriesCode, seqNo].
 */
function next_drug_batch_counters(string $batchKey): array
{
    $batchKey = trim($batchKey);
    $batchFile = __DIR__ . '/drug_batch.txt';
    $codeFile = __DIR__ . '/drug_series_code.txt';
    $seqFile = __DIR__ . '/drug_seq.txt';

    $lastBatch = '';
    if (is_file($batchFile)) {
        $lastBatch = trim((string)@file_get_contents($batchFile));
    }

    $reset = ($batchKey !== '' && $batchKey !== $lastBatch);

    $cur = 'AA';
    if (!$reset && is_file($codeFile)) {
        $v = trim((string)@file_get_contents($codeFile));
        if (preg_match('/^[A-Z]{2}$/', $v)) {
            $cur = $v;
        }
    }

    $a = ord($cur[0]) - 65;
    $b = ord($cur[1]) - 65;
    $n = $a * 26 + $b;
    $n2 = ($n + 1) % (26 * 26);
    $next = chr(65 + intdiv($n2, 26)) . chr(65 + ($n2 % 26));

    $seq = 1;
    if (!$reset && is_file($seqFile)) {
        $seq = (int)trim((string)@file_get_contents($seqFile));
        if ($seq < 1) {
            $seq = 1;
        }
    }

    @file_put_contents($codeFile, $next);
    @file_put_contents($seqFile, (string)($seq + 1));
    if ($batchKey !== '') {
        @file_put_contents($batchFile, $batchKey);
    }

    return [$cur, $seq];
}

function add_seconds_to_ampm_time(string $time, int $seconds): string
{
    $time = strtoupper(trim($time));
    if ($time === '') {
        return '';
    }

    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})(AM|PM)$/', $time) !== 1) {
        return '';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d h:i:sA', '2000-01-01 ' . $time);
    if (!$dt) {
        return '';
    }

    $dt2 = $dt->modify(($seconds >= 0 ? '+' : '') . (string)$seconds . ' seconds');
    if (!$dt2) {
        return '';
    }

    return $dt2->format('h:i:sA');
}

/**
 * Get the next drug test times, resetting when the batch key changes.
 * Returns [transactionTime, reportTime].
 */
function next_drug_times(string $batchKey, bool $reset): array
{
    $batchKey = trim($batchKey);

    $timeFile = __DIR__ . '/drug_time.txt';
    $baseTransTime = '08:00:01AM';
    $stepSeconds = 61;

    $transTime = $baseTransTime;
    $fp = @fopen($timeFile, 'c+');
    if ($fp) {
        if (@flock($fp, LOCK_EX)) {
            $prev = '';
            if (!$reset) {
                $prev = trim((string)stream_get_contents($fp));
            }

            if (!$reset && $prev !== '') {
                $next = add_seconds_to_ampm_time($prev, $stepSeconds);
                if ($next !== '') {
                    $transTime = $next;
                }
            }

            @ftruncate($fp, 0);
            @rewind($fp);
            @fwrite($fp, $transTime);
            @fflush($fp);
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);
    }

    $reportTime = add_seconds_to_ampm_time($transTime, $stepSeconds);
    if ($reportTime === '') {
        $reportTime = '08:01:02AM';
    }

    return [$transTime, $reportTime];
}

function compute_series_suffix(string $ageRaw, string $dobRaw): string
{
    $age = (int)trim($ageRaw);
    if ($age < 0) {
        $age = 0;
    }
    if ($age > 99) {
        $age = $age % 100;
    }
    $age2 = str_pad((string)$age, 2, '0', STR_PAD_LEFT);

    $day = '00';
    $yr2 = '00';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($dobRaw), $m)) {
        $yr2 = substr($m[1], -2);
        $day = $m[3];
    }

    return $age2 . $day . $yr2;
}

/**
 * Drug test suffix uses age + month + year (e.g., 27 08 98).
 */
function compute_drug_series_suffix(string $ageRaw, string $dobRaw): string
{
    $age = (int)trim($ageRaw);
    if ($age < 0) {
        $age = 0;
    }
    if ($age > 99) {
        $age = $age % 100;
    }
    $age2 = str_pad((string)$age, 2, '0', STR_PAD_LEFT);

    $day = '00';
    $yr2 = '00';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($dobRaw), $m)) {
        $yr2 = substr($m[1], -2);
        $day = $m[3];
    }

    return $age2 . $day . $yr2;
}

function title_case_text(string $value): string
{
    $value = norm_text($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_convert_case')) {
        $lower = mb_convert_case($value, MB_CASE_LOWER, 'UTF-8');
        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
}

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

function replace_docx_placeholders(string $docxPath, array $values, bool $isDrugTest = false): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is required to post-process the DOCX file.');
    }

    $requiredMacros = ['{{chk_firearm}}', '{{chk_security}}', '{{chk_lto}}', '{{chk_others}}'];
    $foundAnyRequired = false;

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

    $extractTextFromXml = function (string $xml): string {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (@$dom->loadXML($xml) !== true) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $nodes = $xpath->query('//w:t');
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        $text = '';
        foreach ($nodes as $n) {
            if ($n) {
                $text .= (string)$n->nodeValue;
            }
        }

        return $text;
    };

    $applyDrugTestValues = function (string $xml) use ($values, $replaceInXmlRuns, $extractTextFromXml): string {
        $plain = $extractTextFromXml($xml);
        if ($plain === '') {
            return $xml;
        }

        $fullName = trim((string)($values['full_name'] ?? $values['name'] ?? ''));
        $age = trim((string)($values['age'] ?? ''));
        $sex = trim((string)($values['sex'] ?? ''));
        $drugSeriesCode = trim((string)($values['drug_series_code'] ?? $values['series_code'] ?? ''));
        $drugSeriesSuffix = trim((string)($values['drug_series_suffix'] ?? $values['series_suffix'] ?? ''));
        $drugSeriesFull = trim((string)($values['drug_series_full'] ?? ''));
        if ($drugSeriesFull === '' && $drugSeriesCode !== '' && $drugSeriesSuffix !== '') {
            $drugSeriesFull = $drugSeriesCode . $drugSeriesSuffix;
        }

        $seqNo = (int)($values['drug_seq_no'] ?? 0);
        $seqText = $seqNo > 0 ? str_pad((string)$seqNo, 2, '0', STR_PAD_LEFT) : '';

        $parseDate = static function (string $value): ?DateTime {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            $dt = DateTime::createFromFormat('Y-m-d', $value);
            if ($dt instanceof DateTime) {
                return $dt;
            }
            $ts = strtotime($value);
            if ($ts === false) {
                return null;
            }
            $dt = new DateTime();
            $dt->setTimestamp($ts);
            return $dt;
        };

        $formatShortDate = static function (?DateTime $dt): string {
            if (!$dt) {
                return '';
            }
            return $dt->format('m/d/Y');
        };

        $birthDate = $formatShortDate($parseDate((string)($values['date_of_birth_raw'] ?? $values['date_of_birth'] ?? '')));
        $transDateObj = $parseDate((string)($values['document_date_raw'] ?? $values['document_date'] ?? ''));
        $transDate = $formatShortDate($transDateObj);
        $reportDate = '';
        if ($transDateObj) {
            $reportDateObj = clone $transDateObj;
            $reportDateObj->modify('+1 day');
            $reportDate = $formatShortDate($reportDateObj);
        }

        $transTime = trim((string)($values['drug_trans_time'] ?? ''));
        $reportTime = trim((string)($values['drug_report_time'] ?? ''));
        if ($transTime === '') {
            $transTime = '08:00:01AM';
        }
        if ($reportTime === '') {
            $reportTime = add_seconds_to_ampm_time($transTime, 61);
            if ($reportTime === '') {
                $reportTime = '08:01:02AM';
            }
        }

        $gender = strtoupper($sex);
        if ($gender === 'MALE') {
            $gender = 'M';
        } elseif ($gender === 'FEMALE') {
            $gender = 'F';
        } elseif ($gender !== '') {
            $gender = strtoupper(substr($gender, 0, 1));
        }

        $replaceToken = function (string $old, string $new) use (&$xml, $replaceInXmlRuns): void {
            $old = (string)$old;
            $new = (string)$new;
            if ($old === '' || $new === '' || $old === $new) {
                return;
            }
            $xml = $replaceInXmlRuns($xml, $old, xml_safe_text($new));
        };

        $hasNameMacro = !empty($values['drug_template_has_name_macro']);
        if ($hasNameMacro) {
            if ($drugSeriesCode !== '') {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = true;
                $dom->formatOutput = false;
                if (@$dom->loadXML($xml) === true) {
                    $xpath = new DOMXPath($dom);
                    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $nodes = $xpath->query('//w:t');
                    if ($nodes && $nodes->length > 0) {
                        foreach ($nodes as $t) {
                            $val = trim((string)$t->nodeValue);
                            if ($val === '') {
                                continue;
                            }

                            // Force the top marker to letters only (AA/AB/etc).
                            if (preg_match('/^[A-Z]{2}\d{1,2}$/', $val) === 1) {
                                $t->nodeValue = $drugSeriesCode;
                                $t->setAttribute('xml:space', 'preserve');
                                continue;
                            }

                            if ($val === 'AP') {
                                $t->nodeValue = $drugSeriesCode;
                                $t->setAttribute('xml:space', 'preserve');
                            }
                        }
                    }
                    $xml = $dom->saveXML();
                }
            }

            if ($drugSeriesFull !== '' || $seqText !== '') {
                if (preg_match('~DRUG\s*TEST\s*REPORT\s*([A-Z]{2}\d{6})\s*(\d{1,2})\s*CCF\s*No\s*:\s*([0-9]+)~i', $plain, $m)) {
                    if ($drugSeriesFull !== '') {
                        $replaceToken($m[1], $drugSeriesFull);
                    }
                    if ($seqText !== '') {
                        $replaceToken($m[2], $seqText);
                    }
                    if ($seqText !== '') {
                        $ccf = $m[3];
                        if (strlen($ccf) >= 2) {
                            $ccf = substr($ccf, 0, -2) . $seqText;
                        } else {
                            $ccf = $seqText;
                        }
                        $replaceToken($m[3], $ccf);
                    }
                } elseif ($seqText !== '' && preg_match('~CCF\s*No\s*:\s*([0-9]+)~i', $plain, $m)) {
                    $ccf = $m[1];
                    if (strlen($ccf) >= 2) {
                        $ccf = substr($ccf, 0, -2) . $seqText;
                    } else {
                        $ccf = $seqText;
                    }
                    $replaceToken($m[1], $ccf);
                }
            }

            if ($drugSeriesFull !== '' && preg_match('~\bAP\d{6}\b~', $plain, $m)) {
                $replaceToken($m[0], $drugSeriesFull);
            }

            if ($fullName !== '') {
                $replaceToken('{{name}}', $fullName);
            }

            if ($birthDate !== '' && preg_match('~Birth\s*date\s*:\s*([0-9/]+)\s*Age\s*:\s*~i', $plain, $m)) {
                $replaceToken($m[1], $birthDate);
            }
            if ($age !== '' && preg_match('~Age\s*:\s*([0-9]+)\s*Gender\s*:\s*~i', $plain, $m)) {
                $replaceToken($m[1], $age);
            }
            if ($gender !== '' && preg_match('~Gender\s*:\s*([A-Za-z]+)\s*TEST\s*METHOD~i', $plain, $m)) {
                $replaceToken($m[1], $gender);
            }
            if ($transDate !== '' && $transTime !== '' && preg_match('~Transaction\s*Date\s*Time\s*:\s*([0-9/]+)\s*([0-9:APMapm]+)\s*Name\s*:\s*~i', $plain, $m)) {
                $replaceToken($m[1], $transDate);
                $replaceToken($m[2], $transTime);
            }
            if ($reportDate !== '' && $reportTime !== '' && preg_match('~Report\s*Date\s*Time\s*:\s*([0-9/]+)\s*([0-9:APMapm]+)\s*Birth\s*date\s*:\s*~i', $plain, $m)) {
                $replaceToken($m[1], $reportDate);
                $replaceToken($m[2], $reportTime);
            }
            if ($seqText !== '' && preg_match('~(\d{1,2})\s+ANN\s+PANAGSAGAN\s+CENTENO~i', $plain, $m)) {
                $replaceToken($m[1], $seqText);
            }
            if ($seqText !== '' && preg_match('~DR\.\s*LESTER\s+D\.\s+ABELEDA\s+(\d{1,2})\s*Analyst~i', $plain, $m)) {
                $replaceToken($m[1], $seqText);
            }

            return $xml;
        }

        $fixedWidth = static function (string $value, int $len): string {
            $value = trim($value);
            $curLen = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($curLen > $len) {
                return function_exists('mb_substr') ? mb_substr($value, 0, $len, 'UTF-8') : substr($value, 0, $len);
            }
            if ($curLen < $len) {
                $pad = str_repeat("\xC2\xA0", $len - $curLen);
                return $value . $pad;
            }
            return $value;
        };

        $replaceOnce = static function (string $text, string $old, string $new): string {
            if ($old === '' || $new === '' || $old === $new) {
                return $text;
            }
            $pos = strpos($text, $old);
            if ($pos === false) {
                return $text;
            }
            return substr($text, 0, $pos) . $new . substr($text, $pos + strlen($old));
        };

        if ($drugSeriesCode !== '' && preg_match('~DEPARMENT\s*OF\s*HEALTH\s*([A-Z]{2})\s*Report\s*ID~i', $plain, $m)) {
            $replaceToken($m[1], $drugSeriesCode);
        }

        $oldCcf = '';
        if (preg_match('~CCF\s*No\s*:\s*([0-9]+)~i', $plain, $m)) {
            $oldCcf = $m[1];
        }

        $oldTransDate = '';
        $oldTransTime = '';
        if (preg_match('~Transaction\s*Date\s*Time\s*:\s*([0-9/]+)\s*([0-9:APMapm]+)\s*Name\s*:\s*~i', $plain, $m)) {
            $oldTransDate = $m[1];
            $oldTransTime = $m[2];
        }

        $oldName = '';
        if (preg_match('~Name\s*:\s*(.*?)\s*Report\s*Date\s*Time\s*:\s*~i', $plain, $m)) {
            $oldName = $m[1];
        }

        $oldReportDate = '';
        $oldReportTime = '';
        if (preg_match('~Report\s*Date\s*Time\s*:\s*([0-9/]+)\s*([0-9:APMapm]+)\s*Birth\s*date\s*:\s*~i', $plain, $m)) {
            $oldReportDate = $m[1];
            $oldReportTime = $m[2];
        }

        $oldBirthDate = '';
        if (preg_match('~Birth\s*date\s*:\s*([0-9/]+)\s*Age\s*:\s*~i', $plain, $m)) {
            $oldBirthDate = $m[1];
        }

        $oldAge = '';
        if (preg_match('~Age\s*:\s*([0-9]+)\s*Gender\s*:\s*~i', $plain, $m)) {
            $oldAge = $m[1];
        }

        $oldGender = '';
        if (preg_match('~Gender\s*:\s*([A-Za-z]+)\s*TEST\s*METHOD~i', $plain, $m)) {
            $oldGender = $m[1];
        }

        $oldSeq = '';
        if (preg_match('~(\d{1,2})\s+ANN\s+PANAGSAGAN\s+CENTENO~i', $plain, $m)) {
            $oldSeq = $m[1];
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml) === true) {
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paras = $xpath->query('//w:p');
            if ($paras && $paras->length > 0) {
                foreach ($paras as $p) {
                    $tNodes = $xpath->query('.//w:t', $p);
                    if (!$tNodes || $tNodes->length === 0) {
                        continue;
                    }
                    $pText = '';
                    foreach ($tNodes as $t) {
                        $pText .= (string)$t->nodeValue;
                    }
                    if (strpos($pText, 'Transaction Date Time') === false) {
                        continue;
                    }

                    $newText = $pText;
                    if ($oldCcf !== '' && $seqText !== '') {
                        $newCcf = $oldCcf;
                        if (strlen($newCcf) >= 2) {
                            $newCcf = substr($newCcf, 0, -2) . $seqText;
                        } else {
                            $newCcf = $seqText;
                        }
                        $newText = $replaceOnce($newText, $oldCcf, $fixedWidth($newCcf, strlen($oldCcf)));
                    }

                    if ($oldTransDate !== '' && $transDate !== '') {
                        $newText = $replaceOnce($newText, $oldTransDate, $fixedWidth($transDate, strlen($oldTransDate)));
                    }
                    if ($oldTransTime !== '' && $transTime !== '') {
                        $newText = $replaceOnce($newText, $oldTransTime, $fixedWidth($transTime, strlen($oldTransTime)));
                    }
                    if ($oldName !== '' && $fullName !== '') {
                        $newText = $replaceOnce($newText, $oldName, $fixedWidth($fullName, strlen($oldName)));
                    }
                    if ($oldReportDate !== '' && $reportDate !== '') {
                        $newText = $replaceOnce($newText, $oldReportDate, $fixedWidth($reportDate, strlen($oldReportDate)));
                    }
                    if ($oldReportTime !== '' && $reportTime !== '') {
                        $newText = $replaceOnce($newText, $oldReportTime, $fixedWidth($reportTime, strlen($oldReportTime)));
                    }
                    if ($oldBirthDate !== '' && $birthDate !== '') {
                        $newText = $replaceOnce($newText, $oldBirthDate, $fixedWidth($birthDate, strlen($oldBirthDate)));
                    }
                    if ($oldAge !== '' && $age !== '') {
                        $newText = $replaceOnce($newText, $oldAge, $fixedWidth($age, strlen($oldAge)));
                    }
                    if ($oldGender !== '' && $gender !== '') {
                        $newText = $replaceOnce($newText, $oldGender, $fixedWidth($gender, strlen($oldGender)));
                    }
                    if ($oldSeq !== '' && $seqText !== '') {
                        $newText = $replaceOnce($newText, $oldSeq, $fixedWidth($seqText, strlen($oldSeq)));
                    }

                    if ($newText !== $pText) {
                        $first = $tNodes->item(0);
                        if ($first) {
                            $first->nodeValue = $newText;
                            $first->setAttribute('xml:space', 'preserve');
                        }
                        for ($i = 1; $i < $tNodes->length; $i++) {
                            $tNodes->item($i)->nodeValue = '';
                        }
                    }
                    break;
                }
            }
            $xml = $dom->saveXML();
        }

        return $xml;
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

        foreach ($requiredMacros as $m) {
            if (strpos($xml, $m) !== false) {
                $foundAnyRequired = true;
                break;
            }
        }

        if (!$foundAnyRequired) {
            foreach ($requiredMacros as $m) {
                if (strpos($xml, str_replace(['{', '}'], '', $m)) !== false) {
                    $foundAnyRequired = true;
                    break;
                }
            }
        }

        foreach ($values as $key => $val) {
            $macro = '{{' . $key . '}}';
            $safeVal = htmlspecialchars((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8');

            $xml = str_replace($macro, $safeVal, $xml);

            $xml = $replaceInXmlRuns($xml, $macro, $safeVal);
        }

        $purpose = (string)($values['purpose'] ?? '');
        $purpose = trim($purpose);
        if ($purpose !== '') {
            $labels = [
                'firearm' => 'Firearm License/PTCFOR',
                'security' => 'Security Guard License/ SO License',
                'lto' => 'L T O',
                'others' => 'Others',
            ];

            // Normalize any single-space boxes to two-space boxes for consistent formatting.
            foreach ($labels as $k => $label) {
                foreach (['[ ] ' . $label, '  [ ] ' . $label, '[ ]' . $label, '  [ ]' . $label] as $pat) {
                    $xml = $replaceInXmlRuns($xml, $pat, str_replace('[ ]', '[  ]', $pat));
                }
            }

            foreach ($labels as $k => $label) {
                foreach (['[X] ' . $label, '  [X] ' . $label, '[X]' . $label, '  [X]' . $label] as $pat) {
                    $xml = $replaceInXmlRuns($xml, $pat, str_replace('[X]', '[  ]', $pat));
                }
            }

            if (isset($labels[$purpose])) {
                $label = $labels[$purpose];
                foreach (['[  ] ' . $label, '  [  ] ' . $label, '[  ]' . $label, '  [  ]' . $label] as $pat) {
                    $xml = $replaceInXmlRuns($xml, $pat, str_replace('[  ]', '[X]', $pat));
                }
            }


            $xml = preg_replace_callback(
                '~(<w:p\b[\s\S]*?</w:p>)~u',
                static function (array $m) use ($labels, $purpose) {
                    $p = $m[1];
                    $hasLabel = false;
                    foreach ($labels as $k => $label) {
                        if (strpos($p, $label) !== false) {
                            $hasLabel = true;
                            break;
                        }
                    }
                    if (!$hasLabel) {
                        return $p;
                    }

                    // Reset any checked box inside purpose paragraphs.
                    $p2 = str_replace(['[X]', '[x]'], '[  ]', $p);

                    // Set the selected purpose checkbox in its paragraph.
                    $selLabel = $labels[$purpose] ?? '';
                    if ($selLabel !== '') {
                        $plain = preg_replace('~<[^>]+>~u', '', $p2);
                        if ($plain === null) {
                            $plain = $p2;
                        }
                        // Decode common entities so text matching works even after XML escaping.
                        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_XML1, 'UTF-8');

                        if (strpos($plain, $selLabel) !== false) {
                            $p3 = preg_replace('~\[\s*\]~u', '[X]', $p2, 1);
                        if ($p3 !== null) {
                            return $p3;
                        }
                    }
                    }

                    return $p2;
                },
                $xml
            );

            if ($purpose === 'others') {
                $spec = trim((string)($values['purpose_specify'] ?? ''));
                if ($spec !== '') {
                    $suffix = ' (' . $spec . ')';

                    $replaced = false;

                    // First try to match the actual checked line in the Purpose section.
                    if (strpos($xml, 'Others (Specify)' . $suffix) === false) {
                        $targets = [
                            '[X] Others (Specify) __________',
                            '[X] Others (Specify) ____________',
                            '[X] Others (Specify) ________________',
                            '[X] Others (Specify)',
                            'Others (Specify) __________',
                            'Others (Specify) ____________',
                            'Others (Specify) ________________',
                            'Others (Specify)',
                        ];

                        foreach ($targets as $t) {
                            if (strpos($xml, $t) === false) {
                                continue;
                            }

                            $clean = preg_replace('~\s*_+\s*$~', '', $t);
                            $xml2 = $replaceInXmlRuns($xml, $t, $clean . $suffix);
                            if ($xml2 !== $xml) {
                                $xml = $xml2;
                                $replaced = true;
                                break;
                            }
                        }
                    }

                    // TEMPLATE.docx uses underscore blanks after Others (Specify). Replace that whole chunk.
                    $underscoreVariants = [
                        'Others (Specify) __________',
                        'Others (Specify) ____________',
                        'Others (Specify) ________________',
                    ];

                    $replaced = false;
                    foreach ($underscoreVariants as $pat) {
                        if (strpos($xml, $pat) !== false) {
                            $xml = $replaceInXmlRuns($xml, $pat, 'Others (Specify)' . $suffix);
                            $replaced = true;
                        }
                    }

                    if (!$replaced && strpos($xml, 'Others (Specify)' . $suffix) === false) {
                        $xml2 = $replaceInXmlRuns($xml, 'Others (Specify)', 'Others (Specify)' . $suffix);
                        if ($xml2 !== null && $xml2 !== $xml) {
                            $xml = $xml2;
                            $replaced = true;
                        }
                    }

                    // If the template still contains the literal word '(Specify)', replace it with the user-provided suffix.
                    // Desired result: 'Others (SPEC)' (not 'Others (Specify) (SPEC)').
                    if (!$replaced) {
                        $safeSuffix = htmlspecialchars($suffix, ENT_QUOTES | ENT_XML1, 'UTF-8');

                        // Plain text case
                        $xml2 = $replaceInXmlRuns($xml, 'Others (Specify)', 'Others' . $suffix);
                        if ($xml2 !== $xml) {
                            $xml = $xml2;
                            $replaced = true;
                        }

                        // Run-split case: 'Others' is one <w:t>, and ' (Specify)' is another <w:t>.
                        if (!$replaced) {
                            $pattern = '~(Others</w:t>[\s\S]*?<w:t[^>]*>)\s*\(Specify\)([\s\S]*?</w:t>)~us';
                            $xml2 = preg_replace_callback(
                                $pattern,
                                static function (array $m) use ($safeSuffix) {
                                    return $m[1] . ltrim($safeSuffix) . $m[2];
                                },
                                $xml,
                                1
                            );
                            if ($xml2 !== null && $xml2 !== $xml) {
                                $xml = $xml2;
                                $replaced = true;
                            }
                        }
                    }

                    if (!$replaced) {
                        // Some templates split 'Others' and '(Specify)' across different runs.
                        // Append after '(Specify)' only when preceded by 'Others'.
                        $safeSuffix = htmlspecialchars($suffix, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $pattern = '~(Others</w:t>[\s\S]*?<w:t[^>]*>\s*\(Specify\))([\s\S]*?</w:t>)~us';
                        $xml2 = preg_replace_callback(
                            $pattern,
                            static function (array $m) use ($safeSuffix) {
                                return $m[1] . $safeSuffix . $m[2];
                            },
                            $xml,
                            1
                        );
                        if ($xml2 !== null && $xml2 !== $xml) {
                            $xml = $xml2;
                            $replaced = true;
                        }
                    }

                    // Final fallback: only modify the Purpose section region.
                    if (strpos($xml, 'Others (Specify)' . $suffix) === false) {
                        $pPos = strpos($xml, 'Purpose:');
                        if ($pPos !== false) {
                            $start = (int)$pPos;
                            $len = 20000;
                            if ($start + $len > strlen($xml)) {
                                $len = strlen($xml) - $start;
                            }
                            if ($len > 0) {
                                $seg = substr($xml, $start, $len);
                                $safe = htmlspecialchars($suffix, ENT_QUOTES | ENT_XML1, 'UTF-8');
                                $seg2 = preg_replace('~Others\s*\(Specify\)\s*_+~u', 'Others (Specify)' . $safe, $seg, 1);
                                if ($seg2 === $seg) {
                                    $seg2 = preg_replace('~Others\s*\(Specify\)~u', 'Others (Specify)' . $safe, $seg, 1);
                                }
                                if ($seg2 !== null && $seg2 !== $seg) {
                                    $xml = substr($xml, 0, $start) . $seg2 . substr($xml, $start + $len);
                                    $replaced = true;
                                }
                            }
                        }
                    }

                    // Super-robust fallback for the Purpose checkbox line:
                    // find the paragraph that has brackets (checkbox), plus 'Others' and '(Specify)', and append the suffix.
                    if (!$replaced) {
                        $safe = htmlspecialchars($suffix, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $done = false;
                        $xml = preg_replace_callback(
                            '~(<w:p\b[\s\S]*?</w:p>)~u',
                            static function (array $m) use ($safe, &$done) {
                                $p = $m[1];
                                if ($done) {
                                    return $p;
                                }
                                if (strpos($p, 'Others') === false || strpos($p, 'Specify') === false) {
                                    return $p;
                                }
                                if (strpos($p, '[') === false || strpos($p, ']') === false) {
                                    return $p;
                                }
                                if (strpos($p, $safe) !== false) {
                                    return $p;
                                }
                                // Replace '(Specify)' optionally followed by underscores with '(Specify) (SPEC)'
                                $p2 = preg_replace('~\(Specify\)\s*_+~u', '(Specify)' . $safe, $p, 1);
                                if ($p2 !== null && $p2 !== $p) {
                                    $done = true;
                                    return $p2;
                                }
                                $p2 = preg_replace('~\(Specify\)~u', '(Specify)' . $safe, $p, 1);
                                if ($p2 !== null && $p2 !== $p) {
                                    $done = true;
                                    return $p2;
                                }
                                return $p;
                            },
                            $xml
                        );
                    }

                    // Final cleanup: if anything still contains the literal word 'Specify', remove it.
                    // Desired output: 'Others (SPEC)' (no 'Others (Specify)').
                    $xml2 = $replaceInXmlRuns($xml, 'Others (Specify)' . $suffix, 'Others' . $suffix);
                    if ($xml2 !== $xml) {
                        $xml = $xml2;
                    }
                    $xml2 = $replaceInXmlRuns($xml, 'Others (Specify)', 'Others');
                    if ($xml2 !== $xml) {
                        $xml = $xml2;
                    }
                    // Run-split cleanup: remove '(Specify)' when it appears in a separate <w:t>.
                    $safeSuffix = htmlspecialchars($suffix, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $xml2 = preg_replace_callback(
                        '~(Others</w:t>[\s\S]*?<w:t[^>]*>)\s*\(Specify\)\s*' . preg_quote($safeSuffix, '~') . '([\s\S]*?</w:t>)~us',
                        static function (array $m) use ($safeSuffix) {
                            return $m[1] . ltrim($safeSuffix) . $m[2];
                        },
                        $xml
                    );
                    if ($xml2 !== null && $xml2 !== $xml) {
                        $xml = $xml2;
                    }
                    $xml2 = preg_replace_callback(
                        '~(Others</w:t>[\s\S]*?<w:t[^>]*>)\s*\(Specify\)([\s\S]*?</w:t>)~us',
                        static function (array $m) use ($safeSuffix) {
                            return $m[1] . ltrim($safeSuffix) . $m[2];
                        },
                        $xml
                    );
                    if ($xml2 !== null && $xml2 !== $xml) {
                        $xml = $xml2;
                    }
                }
            }
        }

        if ($isDrugTest && $name === 'word/document.xml') {
            $xml = $applyDrugTestValues($xml);
        }

        // Ensure no raw ampersands remain. A raw '&' will break XML and make Word refuse to open the DOCX.
        // This escapes '&' characters that are not already part of a valid XML entity.
        $xml2 = preg_replace('/&(?!#\d+;|#x[0-9A-Fa-f]+;|[A-Za-z][A-Za-z0-9]+;)/u', '&amp;', $xml);
        if ($xml2 !== null) {
            $xml = $xml2;
        }

        // Ensure we replace the existing file entry to avoid duplicate ZIP members
        // which can make Word think the DOCX is corrupted.
        @$zip->deleteName($name);
        $zip->addFromString($name, $xml);
    }

    $zip->close();
}

function assert_docx_template_ok(string $path, string $label): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException($label . ' template file not found or not readable.');
    }

    $zip = new ZipArchive();
    $res = $zip->open($path);
    if ($res !== true) {
        throw new RuntimeException($label . ' template is not a valid .docx file. Please re-save it as Word Document (.docx).');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException($label . ' template is not a valid .docx file (missing word/document.xml). Please re-save it as Word Document (.docx).');
    }
}

function assert_generated_docx_ok(string $path, string $label): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException($label . ' output file was not created or not readable.');
    }

    $zip = new ZipArchive();
    $res = $zip->open($path);
    if ($res !== true) {
        throw new RuntimeException($label . ' output is corrupted (cannot open as .docx zip).');
    }

    $contentTypes = $zip->getFromName('[Content_Types].xml');
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($contentTypes === false || $documentXml === false) {
        throw new RuntimeException($label . ' output is corrupted (missing required DOCX parts).');
    }

    // Word can still refuse to open a DOCX if word/document.xml is not well-formed XML.
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    $prev = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $ok = @$dom->loadXML($documentXml);
    $errs = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($ok !== true) {
        $detail = '';
        if (is_array($errs) && !empty($errs)) {
            $e = $errs[0];
            $detail = ' (' . trim((string)($e->message ?? 'XML parse error')) . ' at line ' . (int)($e->line ?? 0) . ', col ' . (int)($e->column ?? 0) . ')';
        }
        throw new RuntimeException($label . ' output is corrupted (invalid word/document.xml after replacements)' . $detail . '.');
    }
}

$documentType = norm_text((string)($_POST['effective_document_type'] ?? ($_POST['document_type'] ?? 'neuro')));
if (!in_array($documentType, ['neuro', 'drug_test', 'both'], true)) {
    $documentType = 'neuro';
}

$needDrugPhoto = ($documentType === 'drug_test' || $documentType === 'both');

$required = [
    'document_type',
    'sex',
    'document_date',
    'last_name',
    'first_name',
    'age',
    'date_of_birth',
    'company_requesting_agency',
];

if ($documentType !== 'drug_test') {
    $required[] = 'home_address';
    $required[] = 'contact_no';
    $required[] = 'np_clearance';
    $required[] = 'purpose';
    $required[] = 'civil_status';
    $required[] = 'occupation';
    $required[] = 'position';
    $required[] = 'educational';
    $required[] = 'religion';
}

foreach ($required as $key) {
    if (!isset($_POST[$key]) || trim((string)$_POST[$key]) === '') {
        http_response_code(400);
        echo 'Missing required field: ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        exit;
    }
}

$drugPhotoExt = '';
if ($needDrugPhoto) {
    $drugPhotoExt = '';
    if (isset($_FILES['drug_photo']) && is_array($_FILES['drug_photo'])) {
        $drugPhotoErr = (int)($_FILES['drug_photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($drugPhotoErr === UPLOAD_ERR_OK) {
            $drugPhotoTmp = (string)($_FILES['drug_photo']['tmp_name'] ?? '');
            if ($drugPhotoTmp !== '' && is_uploaded_file($drugPhotoTmp)) {
                $drugPhotoSize = (int)($_FILES['drug_photo']['size'] ?? 0);
                if ($drugPhotoSize > 0 && $drugPhotoSize <= 8 * 1024 * 1024) {
                    $imgInfo = @getimagesize($drugPhotoTmp);
                    $mime = (is_array($imgInfo) && isset($imgInfo['mime'])) ? strtolower((string)$imgInfo['mime']) : '';
                    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                        $drugPhotoExt = 'jpg';
                    } elseif ($mime === 'image/png') {
                        $drugPhotoExt = 'png';
                    }
                }
            }
        }
    }
}

$neuroTemplatePath = __DIR__ . '/../TEMPLATE.docx';
$drugTemplatePath = __DIR__ . '/../TEMPLATE_DRUG_TEST.docx';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Composer autoload not found. Please run composer install.';
    exit;
}

require_once $autoload;

use PhpOffice\PhpWord\TemplateProcessor;

function xml_safe_text(string $value): string
{
    // Remove characters that are not allowed in XML 1.0.
    // Allowed: tab (0x09), LF (0x0A), CR (0x0D), and 0x20..0xD7FF, 0xE000..0xFFFD
    // (We don't include higher planes here to keep it safe for Word/DOMDocument.)
    $value = (string)$value;

    // Ensure string is valid UTF-8; drop invalid byte sequences.
    if (function_exists('iconv')) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($clean)) {
            $value = $clean;
        }
    }

    // Filter by Unicode code points allowed in XML 1.0.
    $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
    if ($value === null) {
        // Last resort: keep basic printable ASCII + whitespace.
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', (string)$value);
        if ($value === null) {
            return '';
        }
    }

    return $value;
}

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

function upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_OK:
            return 'OK';
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds upload_max_filesize.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by a PHP extension.';
        default:
            return 'Unknown upload error.';
    }
}

function save_uploaded_drug_test_photo(string $inputName, string $exportDir, string $ext): string
{
    if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
        throw new RuntimeException('Missing photo upload.');
    }

    $err = (int)($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed: ' . upload_error_text($err));
    }

    $tmp = (string)($_FILES[$inputName]['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Photo upload failed: invalid upload.');
    }

    $photoDir = $exportDir . '/drug_photos';
    if (!is_dir($photoDir)) {
        if (!@mkdir($photoDir, 0777, true)) {
            throw new RuntimeException('Failed to create photo directory.');
        }
    }

    $safeExt = ($ext === 'png') ? 'png' : 'jpg';
    $name = 'drug-photo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $safeExt;
    $dest = $photoDir . '/' . $name;

    if (!@move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Photo upload failed: cannot save the file.');
    }

    return $dest;
}

function exif_normalize_image($img, string $srcPath)
{
    // Normalize common phone EXIF rotations for JPEG so the embedded photo is upright.
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $ext = strtolower((string)pathinfo($srcPath, PATHINFO_EXTENSION));
    if ($ext !== 'jpg' && $ext !== 'jpeg') {
        return $img;
    }
    $exif = @exif_read_data($srcPath);
    if (!is_array($exif)) {
        return $img;
    }
    $orientation = (int)($exif['Orientation'] ?? 0);
    if ($orientation <= 1) {
        return $img;
    }

    // 2: flip horizontal
    // 3: rotate 180
    // 4: flip vertical
    // 5: transpose
    // 6: rotate 90 CW
    // 7: transverse
    // 8: rotate 90 CCW
    $rotated = $img;
    switch ($orientation) {
        case 2:
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $rotated = imagerotate($rotated, 180, 0);
            break;
        case 4:
            imageflip($rotated, IMG_FLIP_VERTICAL);
            break;
        case 5:
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
            $rotated = imagerotate($rotated, -90, 0);
            break;
        case 6:
            $rotated = imagerotate($rotated, -90, 0);
            break;
        case 7:
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
            $rotated = imagerotate($rotated, 90, 0);
            break;
        case 8:
            $rotated = imagerotate($rotated, 90, 0);
            break;
        default:
            break;
    }

    return $rotated;
}

function make_cover_fitted_jpeg(string $srcPath, string $destJpegPath, float $targetAspect, int $outMax = 1200): void
{
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
        // If GD is unavailable, fall back to the original file.
        if (!@copy($srcPath, $destJpegPath)) {
            throw new RuntimeException('Failed to prepare photo (GD extension missing).');
        }
        return;
    }

    $info = @getimagesize($srcPath);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        throw new RuntimeException('Invalid image file.');
    }

    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    $mime = strtolower((string)($info['mime'] ?? ''));

    if ($mime === 'image/png') {
        $srcImg = @imagecreatefrompng($srcPath);
    } else {
        $srcImg = @imagecreatefromjpeg($srcPath);
    }
    if (!$srcImg) {
        throw new RuntimeException('Failed to read uploaded image.');
    }

    $srcImg = exif_normalize_image($srcImg, $srcPath);
    $srcW = imagesx($srcImg);
    $srcH = imagesy($srcImg);
    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($srcImg);
        throw new RuntimeException('Invalid image dimensions.');
    }

    if ($targetAspect <= 0) {
        $targetAspect = 1.0;
    }

    $srcAspect = $srcW / $srcH;

    // Crop center to target aspect (cover behavior).
    $cropX = 0;
    $cropY = 0;
    $cropW = $srcW;
    $cropH = $srcH;
    if ($srcAspect > $targetAspect) {
        // Too wide -> crop width
        $cropW = (int)round($srcH * $targetAspect);
        $cropX = (int)floor(($srcW - $cropW) / 2);
    } elseif ($srcAspect < $targetAspect) {
        // Too tall -> crop height
        $cropH = (int)round($srcW / $targetAspect);
        $cropY = (int)floor(($srcH - $cropH) / 2);
    }

    if ($cropW <= 0 || $cropH <= 0) {
        imagedestroy($srcImg);
        throw new RuntimeException('Failed to crop image.');
    }

    // Determine output size (cap by outMax; preserve aspect).
    $outW = $outMax;
    $outH = (int)round($outW / $targetAspect);
    if ($outH > $outMax) {
        $outH = $outMax;
        $outW = (int)round($outH * $targetAspect);
    }
    if ($outW < 200) {
        $outW = 200;
        $outH = (int)round($outW / $targetAspect);
    }

    $dstImg = imagecreatetruecolor($outW, $outH);
    imagealphablending($dstImg, true);
    imagesavealpha($dstImg, false);
    $white = imagecolorallocate($dstImg, 255, 255, 255);
    imagefilledrectangle($dstImg, 0, 0, $outW, $outH, $white);

    imagecopyresampled($dstImg, $srcImg, 0, 0, $cropX, $cropY, $outW, $outH, $cropW, $cropH);

    if (!@imagejpeg($dstImg, $destJpegPath, 92)) {
        imagedestroy($dstImg);
        imagedestroy($srcImg);
        throw new RuntimeException('Failed to write fitted photo.');
    }

    imagedestroy($dstImg);
    imagedestroy($srcImg);
}

function prepare_drug_photo_frame_from_template(string $templatePath): array
{
    // Returns: [patchedTemplatePath, widthPx, heightPx, aspect]
    $tp = sys_get_temp_dir() . '/erms-drug-template-' . bin2hex(random_bytes(8)) . '.docx';
    if (!@copy($templatePath, $tp)) {
        throw new RuntimeException('Failed to prepare Drug Test template.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tp) !== true) {
        throw new RuntimeException('Drug Test template is not readable.');
    }

    $xml = $zip->getFromName('word/document.xml');
    if (!is_string($xml)) {
        $zip->close();
        throw new RuntimeException('Drug Test template is missing word/document.xml.');
    }

    $pos = stripos($xml, 'drug_photo');
    if ($pos === false) {
        $zip->close();
        // Template might already be processed; fall back to original copy.
        return [$tp, 170, 170, 1.0];
    }

    $before = substr($xml, 0, $pos);
    $rectStart = strripos($before, '<v:rect');
    $widthPx = 170;
    $heightPx = 170;
    $aspect = 1.0;

    if ($rectStart !== false) {
        $tagEnd = strpos($xml, '>', $rectStart);
        if ($tagEnd !== false) {
            $tag = substr($xml, $rectStart, $tagEnd - $rectStart + 1);

            // Extract width/height from style="...width:60.45pt;height:56.5pt..." to match the frame.
            if (preg_match('/style="[^"]*width:([0-9.]+)pt;[^"]*height:([0-9.]+)pt/i', $tag, $m)) {
                $wPt = (float)$m[1];
                $hPt = (float)$m[2];
                if ($wPt > 0 && $hPt > 0) {
                    $aspect = $wPt / $hPt;
                    // Convert points to pixels assuming 96 DPI: px = pt * 96/72.
                    $widthPx = (int)max(60, round($wPt * 4 / 3));
                    $heightPx = (int)max(60, round($hPt * 4 / 3));
                }
            }

            // Disable rectangle stroke so the border line doesn't show in the output.
            if (stripos($tag, 'stroked=') === false) {
                $newTag = rtrim(substr($tag, 0, -1)) . ' stroked="f" strokeweight="0pt">';
                $xml = substr($xml, 0, $rectStart) . $newTag . substr($xml, $tagEnd + 1);
            }
        }
    }

    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    return [$tp, $widthPx, $heightPx, $aspect];
}

$values = [
    'contact_no' => normalize_contact_no((string)$_POST['contact_no']),
    'np_clearance' => norm_text((string)($_POST['np_clearance'] ?? '')),
    'document_date' => fmt_long_date(norm_text($_POST['document_date'])),
    'last_name' => up((string)$_POST['last_name']),
    'first_name' => up((string)$_POST['first_name']),
    'middle_name' => middle_initial((string)($_POST['middle_name'] ?? '')),
    'suffix' => up((string)($_POST['suffix'] ?? '')),
    'age' => norm_text($_POST['age']),
    'sex' => norm_text($_POST['sex']),
    'civil_status' => norm_text($_POST['civil_status']),
    'home_address' => norm_text((string)($_POST['home_address'] ?? '')),
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

$fullNameTitle = title_case_text(norm_text((string)($_POST['last_name'] ?? '')));
if ($fullNameTitle !== '') {
    $fullNameTitle = $fullNameTitle . ', ' . title_case_text(norm_text((string)($_POST['first_name'] ?? '')));
} else {
    $fullNameTitle = title_case_text(norm_text((string)($_POST['first_name'] ?? '')));
}
if (trim((string)($_POST['middle_name'] ?? '')) !== '') {
    $fullNameTitle = trim($fullNameTitle . ' ' . middle_initial((string)($_POST['middle_name'] ?? '')));
}
if (trim((string)($_POST['suffix'] ?? '')) !== '') {
    $fullNameTitle = trim($fullNameTitle . ' ' . title_case_text(norm_text((string)($_POST['suffix'] ?? ''))));
}

$remarkText = get_next_remark($values['sex']);

$purpose = norm_text((string)($_POST['purpose'] ?? ''));
$purposeSpecify = norm_text($_POST['purpose_specify'] ?? '');

$blank = ' ';
$chkFirearm  = ($purpose === 'firearm')   ? 'X' : $blank;
$chkSecurity = ($purpose === 'security')  ? 'X' : $blank;
$chkLto      = ($purpose === 'lto')       ? 'X' : $blank;
$chkOthers   = ($purpose === 'others')    ? 'X' : $blank;

$values += [
    'name'          => $fullName,
    'full_name'     => $fullName,
    'remark'        => $remarkText,
    'remarks'       => $remarkText,
    'purpose'       => $purpose,
    'chk_firearm'   => $chkFirearm,
    'chk_security'  => $chkSecurity,
    'chk_lto'       => $chkLto,
    'chk_others'    => $chkOthers,
    'purpose_specify' => $purposeSpecify,
    'first_name_title' => title_case_text(norm_text((string)($_POST['first_name'] ?? ''))),
    'middle_name_title' => middle_initial((string)($_POST['middle_name'] ?? '')),
    'last_name_title' => title_case_text(norm_text((string)($_POST['last_name'] ?? ''))),
    'birth_date_raw' => norm_text((string)($_POST['date_of_birth'] ?? '')),
];

$values += [
    'document_date_raw' => norm_text($_POST['document_date']),
    'date_of_birth_raw' => norm_text($_POST['date_of_birth']),
];

$seriesCode = next_series_code();
$seriesSuffix = compute_series_suffix((string)$values['age'], (string)$values['date_of_birth_raw']);
$seriesFull = $seriesCode . $seriesSuffix;

$values += [
    'series_code' => $seriesCode,
    'series_code_top' => $seriesCode,
    'series_code_bottom' => $seriesCode,
    'series_suffix' => $seriesSuffix,
    'series_full' => $seriesFull,
    'series_full_top' => $seriesFull,
    'series_full_bottom' => $seriesFull,
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
    $pdo = db();
    ensure_generated_documents_table($pdo);
    ensure_attendance_table($pdo);
    ensure_attendance_columns($pdo);
    $company = (string)($_SESSION['company'] ?? 'brainmaster');
    $createdByUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if ($createdByUserId === 0) {
        $createdByUserId = null;
    }
    $createdByEmployeeId = (string)($_SESSION['user_employee_id'] ?? '');
    if ($createdByEmployeeId === '') {
        $createdByEmployeeId = null;
    }
    $docDateDb = normalize_document_date((string)($values['document_date_raw'] ?? ''));
    $folderName = trim((string)($_POST['folder_name'] ?? ''));
    $folderName = preg_replace('/[^A-Za-z0-9 _.-]/', '', $folderName);
    $folderName = trim(preg_replace('/\s+/', ' ', $folderName));
    if ($folderName === '') {
        $folderName = 'Default';
    }
    $birthDateDb = normalize_document_date((string)($_POST['date_of_birth'] ?? ''));
    $attFirst = title_case_text(norm_text((string)($_POST['first_name'] ?? '')));
    $attLast = title_case_text(norm_text((string)($_POST['last_name'] ?? '')));
    $attMiddle = middle_initial((string)($_POST['middle_name'] ?? ''));
    $attFull = $fullNameTitle !== '' ? $fullNameTitle : trim($attLast . ', ' . $attFirst . ($attMiddle !== '' ? ' ' . $attMiddle : ''));
    $attHome = norm_text((string)($_POST['home_address'] ?? ''));
    $attAgency = norm_text((string)($_POST['company_requesting_agency'] ?? ''));
    $attDetachment = norm_text((string)($_POST['detachment'] ?? ''));
    $attGender = norm_text((string)($_POST['sex'] ?? ''));
    $attendanceBase = [
        'company' => $company,
        'folder_name' => $folderName,
        'document_date' => $docDateDb,
        'first_name' => $attFirst !== '' ? $attFirst : $values['first_name'],
        'middle_name' => $attMiddle !== '' ? $attMiddle : null,
        'last_name' => $attLast !== '' ? $attLast : $values['last_name'],
        'full_name' => $attFull !== '' ? $attFull : $fullName,
        'home_address' => $attHome !== '' ? $attHome : null,
        'agency' => $attAgency !== '' ? $attAgency : null,
        'detachment' => $attDetachment !== '' ? $attDetachment : null,
        'birth_date' => $birthDateDb,
        'gender' => $attGender !== '' ? $attGender : null,
        'created_by_user_id' => $createdByUserId,
        'created_by_employee_id' => $createdByEmployeeId,
    ];

    $exportBase = dirname(__DIR__) . '/export_nuero';
    $exportDir = $exportBase . '/' . $folderName;

    $needNeuro = ($documentType === 'neuro' || $documentType === 'both');
    $needDrug = ($documentType === 'drug_test' || $documentType === 'both');

    $drugSeriesCode = $seriesCode;
    $drugSeriesSuffix = $seriesSuffix;
    $drugSeqNo = 0;

    if ($needDrug) {
        $drugBatchFile = __DIR__ . '/drug_batch.txt';
        $lastDrugBatch = '';
        if (is_file($drugBatchFile)) {
            $lastDrugBatch = trim((string)@file_get_contents($drugBatchFile));
        }
        $drugReset = ($folderName !== '' && $folderName !== $lastDrugBatch);

        [$drugSeriesCode, $drugSeqNo] = next_drug_batch_counters($folderName);
        $drugSeriesSuffix = compute_drug_series_suffix((string)$values['age'], (string)$values['date_of_birth_raw']);

        [$drugTransTime, $drugReportTime] = next_drug_times($folderName, $drugReset);
        $values['drug_trans_time'] = $drugTransTime;
        $values['drug_report_time'] = $drugReportTime;
    }

    if ($needDrug) {
        $values['drug_template_has_name_macro'] = false;
        $zip = new ZipArchive();
        if ($zip->open($drugTemplatePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if (is_string($xml) && preg_match('/\{\{\s*name\s*\}\}/i', $xml)) {
                $values['drug_template_has_name_macro'] = true;
            }
            $zip->close();
        }
    }

    $drugSeriesFull = ($drugSeriesCode !== '' && $drugSeriesSuffix !== '')
        ? $drugSeriesCode . $drugSeriesSuffix
        : '';

    $values += [
        'drug_series_code' => $drugSeriesCode,
        'drug_series_suffix' => $drugSeriesSuffix,
        'drug_series_full' => $drugSeriesFull,
        'drug_seq_no' => $drugSeqNo,
        'drug_seq_no_padded' => $drugSeqNo > 0 ? str_pad((string)$drugSeqNo, 2, '0', STR_PAD_LEFT) : '',
    ];

    if ($needNeuro) {
        assert_docx_template_ok($neuroTemplatePath, 'Neuro');
    }
    if ($needDrug) {
        assert_docx_template_ok($drugTemplatePath, 'Drug Test');
    }

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

$drugPhotoImage = null;
    $drugTemplatePrepared = null;
    if ($needDrug && $drugPhotoExt !== '') {
        [$drugTemplatePrepared, $frameWpx, $frameHpx, $frameAspect] = prepare_drug_photo_frame_from_template($drugTemplatePath);
        register_shutdown_function(static function () use ($drugTemplatePrepared) {
            if (is_string($drugTemplatePrepared) && $drugTemplatePrepared !== '' && is_file($drugTemplatePrepared)) {
                @unlink($drugTemplatePrepared);
            }
        });

        $drugPhotoOriginal = save_uploaded_drug_test_photo('drug_photo', $exportDir, $drugPhotoExt);
        $fittedDir = $exportDir . '/drug_photos_fitted';
        if (!is_dir($fittedDir)) {
            @mkdir($fittedDir, 0777, true);
        }
        $drugPhotoFitted = $fittedDir . '/drug-photo-fitted-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.jpg';
        make_cover_fitted_jpeg($drugPhotoOriginal, $drugPhotoFitted, (float)$frameAspect, 1200);

        $drugPhotoImage = [
            'path' => $drugPhotoFitted,
            'width' => (int)$frameWpx,
            'height' => (int)$frameHpx,
            'ratio' => false,
        ];
    }

    $writeDocMeta = static function (
        string $savePath,
        string $filename,
        string $folderName,
        array $values,
        string $docType,
        string $fullName,
        string $fullNameTitle,
        string $purpose,
        string $purposeSpecify
    ): void {
        return;
    };

    $genDocx = static function (string $templatePath, string $defaultName, array $values, string $exportDir, bool $isDrugTest, ?array $drugPhotoImage) use ($fullName, $drugTemplatePrepared) {
        // PhpWord's TemplateProcessor uses static macro delimiters and also runs macro-fixing logic
        // during construction. Ensure {{ }} is configured before the real processing instance is created.
        if ($isDrugTest && is_string($drugTemplatePrepared) && $drugTemplatePrepared !== '' && is_file($drugTemplatePrepared)) {
            $templatePath = $drugTemplatePrepared;
        }

        $tpInit = new TemplateProcessor($templatePath);
        $tpInit->setMacroChars('{{', '}}');
        unset($tpInit);

        $template = new TemplateProcessor($templatePath);
        $template->setMacroChars('{{', '}}');

        if ($isDrugTest && is_array($drugPhotoImage)) {
            try {
                $vars = $template->getVariables();
                if (is_array($vars) && !in_array('drug_photo', $vars, true)) {
                    throw new RuntimeException('Drug Test template is missing the {{drug_photo}} placeholder. Put {{drug_photo}} (no spaces) inside the photo frame in TEMPLATE_DRUG_TEST.docx.');
                }
                $template->setImageValue('drug_photo', $drugPhotoImage);
            } catch (Throwable $e) {
                throw new RuntimeException('Failed to insert Drug Test photo. ' . $e->getMessage());
            }
        }

        $safeValues = [];
        foreach ($values as $k => $v) {
            $safeValues[(string)$k] = xml_safe_text((string)$v);
        }

        foreach ($safeValues as $key => $val) {
            $template->setValue($key, (string)$val);
        }

        $filename = $fullName . ' - ' . $defaultName . '.docx';
        $filename = preg_replace('/[^A-Za-z0-9 _.,\-()]/', '', $filename);
        $filename = trim($filename);
        if ($filename === '' || $filename === '.docx') {
            $filename = $defaultName . '.docx';
        }

        $savePath = $exportDir . '/' . $filename;
        $template->saveAs($savePath);
        replace_docx_placeholders($savePath, $safeValues, $isDrugTest);
        assert_generated_docx_ok($savePath, $defaultName);
        if (!is_file($savePath)) {
            throw new RuntimeException('DOCX file was not written.');
        }
        return [$filename, $savePath];
    };

    $generated = [];
    if ($needNeuro) {
        $generated[] = $genDocx($neuroTemplatePath, 'Neuro Document', $values, $exportDir, false, null);
        [$filename, $savePath] = $generated[count($generated) - 1];
        $writeDocMeta($savePath, $filename, $folderName, $values, 'neuro', $fullName, $fullNameTitle, $purpose, $purposeSpecify);
        save_generated_document($pdo, [
            'company' => $company,
            'document_type' => 'neuro',
            'document_date' => $docDateDb,
            'full_name' => $fullNameTitle !== '' ? $fullNameTitle : $fullName,
            'purpose' => $purpose !== '' ? $purpose : null,
            'purpose_specify' => $purposeSpecify !== '' ? $purposeSpecify : null,
            'folder_name' => $folderName,
            'file_name' => $filename,
            'file_path' => 'export_nuero/' . $folderName . '/' . $filename,
            'created_by_user_id' => $createdByUserId,
            'created_by_employee_id' => $createdByEmployeeId,
        ]);
        save_attendance_record($pdo, $attendanceBase + [
            'document_type' => 'neuro',
        ]);
    }
    if ($needDrug) {
        $drugValues = $values;
        // Drug Test output expects the full name in ALL CAPS.
        $drugName = $fullNameTitle !== '' ? up($fullNameTitle) : up($fullName);
        if ($drugName !== '') {
            $drugValues['name'] = $drugName;
            $drugValues['full_name'] = $drugName;
        }
        $generated[] = $genDocx($drugTemplatePath, 'Drug Test', $drugValues, $exportDir, true, $drugPhotoImage);
        [$filename, $savePath] = $generated[count($generated) - 1];
        $writeDocMeta($savePath, $filename, $folderName, $values, 'drug_test', $fullName, $fullNameTitle, $purpose, $purposeSpecify);
        save_generated_document($pdo, [
            'company' => $company,
            'document_type' => 'drug_test',
            'document_date' => $docDateDb,
            'full_name' => $fullNameTitle !== '' ? $fullNameTitle : $fullName,
            'purpose' => $purpose !== '' ? $purpose : null,
            'purpose_specify' => $purposeSpecify !== '' ? $purposeSpecify : null,
            'folder_name' => $folderName,
            'file_name' => $filename,
            'file_path' => 'export_nuero/' . $folderName . '/' . $filename,
            'created_by_user_id' => $createdByUserId,
            'created_by_employee_id' => $createdByEmployeeId,
        ]);
        save_attendance_record($pdo, $attendanceBase + [
            'document_type' => 'drug_test',
        ]);
    }

    if (count($generated) === 1) {
        [$filename, $savePath] = $generated[0];
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($savePath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        readfile($savePath);
        exit;
    }

    // Both docs requested: trigger two separate downloads (no ZIP).
    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION['generated_downloads']) || !is_array($_SESSION['generated_downloads'])) {
        $_SESSION['generated_downloads'] = [];
    }

    $_SESSION['generated_downloads'][$token] = [
        'expires_at' => time() + 300,
        'files' => array_map(static function (array $row) {
            return [
                'filename' => (string)$row[0],
                'path' => (string)$row[1],
            ];
        }, $generated),
    ];

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $base = '../auth/download_generated.php?token=' . rawurlencode($token);
    echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
    echo '<script>';
    echo '(function(){';
    echo 'try {';
    echo 'var p = window.parent || window;';
    echo 'var d = p.document;';
    echo 'function dl(i){';
    echo '  var f = d.createElement("iframe");';
    echo '  f.style.display="none";';
    echo '  f.src=' . json_encode($base . '&i=') . ' + String(i);';
    echo '  d.body.appendChild(f);';
    echo '}';
    echo 'dl(0); setTimeout(function(){ dl(1); }, 700);';
    echo '} catch(e) {}';
    echo '})();';
    echo '</script>';
    echo 'Preparing downloads...';
    echo '</body></html>';
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[ERMS][generate_neuro_document] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to generate document: ' . $e->getMessage();
    exit;
}
