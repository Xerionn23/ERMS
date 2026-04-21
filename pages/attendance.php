<?php
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'employee' && $role !== 'admin') {
    header('Location: home.php');
    exit;
}

if ($role === 'admin') {
    require_company();
    if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
        header('Location: home.php');
        exit;
    }
}

$userName = (string)($_SESSION['user_name'] ?? 'User');
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
if ($userInitials === '') {
    $userInitials = 'U';
}

$roleLabel = $role === 'admin' ? 'Administrator' : 'Employee';
$company = (string)($_SESSION['company'] ?? 'brainmaster');

function date_in_range(?string $date, ?string $from, ?string $to): bool
{
    if ($date === null || $date === '') {
        return ($from === null && $to === null);
    }
    if ($from !== null && $date < $from) {
        return false;
    }
    if ($to !== null && $date > $to) {
        return false;
    }
    return true;
}

function normalize_date(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d');
}

function compute_age(?string $ageRaw, ?string $birthRaw, ?string $birthDisplay): string
{
    $ageRaw = trim((string)$ageRaw);
    if ($ageRaw !== '' && ctype_digit($ageRaw)) {
        return $ageRaw;
    }

    $birth = null;
    $birthRaw = trim((string)$birthRaw);
    if ($birthRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $birthRaw);
        if ($dt) {
            $birth = $dt;
        }
    }

    if ($birth === null) {
        $birthDisplay = trim((string)$birthDisplay);
        if ($birthDisplay !== '') {
            $ts = strtotime($birthDisplay);
            if ($ts !== false) {
                $birth = new DateTime(date('Y-m-d', $ts));
            }
        }
    }

    if ($birth === null) {
        return '';
    }

    $today = new DateTime('today');
    $diff = $birth->diff($today);
    return (string)$diff->y;
}

function normalize_attendance_record(array $meta, string $folderName): array
{
    $docType = (string)($meta['document_type'] ?? 'neuro');
    if (!in_array($docType, ['neuro', 'drug_test'], true)) {
        $docType = 'neuro';
    }

    $docRaw = (string)($meta['document_date_raw'] ?? $meta['document_date_iso'] ?? '');
    $docDate = normalize_date($docRaw);
    $createdAt = (string)($meta['created_at'] ?? '');

    $first = (string)($meta['first_name'] ?? '');
    $middle = (string)($meta['middle_name'] ?? '');
    $last = (string)($meta['last_name'] ?? '');
    $full = (string)($meta['full_name'] ?? '');

    if ($full === '' && ($first !== '' || $last !== '')) {
        $full = trim($last . ', ' . $first . ($middle !== '' ? ' ' . $middle : ''));
    }

    if ($first === '' && $last === '' && $full !== '') {
        if (strpos($full, ',') !== false) {
            [$l, $rest] = array_map('trim', explode(',', $full, 2));
            $last = $l;
            $parts = preg_split('/\s+/', $rest);
            $first = $parts[0] ?? '';
            $middle = $parts[1] ?? '';
        } else {
            $parts = preg_split('/\s+/', $full);
            $first = $parts[0] ?? '';
            $last = $parts[count($parts) - 1] ?? '';
            if (count($parts) > 2) {
                $middle = $parts[1];
            }
        }
    }

    return [
        'folder_name' => (string)($meta['folder_name'] ?? $meta['folder'] ?? $folderName),
        'document_type' => $docType,
        'document_date_raw' => $docRaw,
        'document_date' => $docDate,
        'first_name' => $first,
        'middle_name' => $middle,
        'last_name' => $last,
        'full_name' => $full,
        'home_address' => (string)($meta['home_address'] ?? ''),
        'agency' => (string)($meta['agency'] ?? ''),
        'detachment' => (string)($meta['detachment'] ?? ''),
        'birth_date' => (string)($meta['birth_date_raw'] ?? $meta['birth_date'] ?? ''),
        'birth_date_raw' => (string)($meta['birth_date_raw'] ?? ''),
        'gender' => (string)($meta['gender'] ?? ''),
        'age' => (string)($meta['age'] ?? ''),
        'created_at' => $createdAt,
    ];
}

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

    if (!isset($cols['detachment'])) {
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN detachment VARCHAR(120) NULL AFTER agency");
    }
}

function fetch_batch_list_from_db(PDO $pdo, string $company, ?string $from, ?string $to, string $q, int $limit): array
{
    $sql =
        'SELECT '
        . 'folder_name, '
        . 'MAX(COALESCE(document_date, DATE(created_at))) AS batch_date, '
        . 'COUNT(*) AS record_count, '
        . 'COUNT(DISTINCT full_name) AS people_count, '
        . 'MAX(created_at) AS last_created, '
        . 'GROUP_CONCAT(DISTINCT agency ORDER BY agency SEPARATOR \', \') AS agency_list '
        . 'FROM attendance_records '
        . 'WHERE company = :company '
        . 'AND (:q = \'\' OR folder_name LIKE :qlike) '
        . 'AND (:from1 IS NULL OR COALESCE(document_date, DATE(created_at)) >= :from2) '
        . 'AND (:to1 IS NULL OR COALESCE(document_date, DATE(created_at)) <= :to2) '
        . 'GROUP BY folder_name '
        . 'ORDER BY last_created DESC '
        . 'LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'company' => $company,
        'q' => $q,
        'qlike' => '%' . $q . '%',
        'from1' => $from,
        'from2' => $from,
        'to1' => $to,
        'to2' => $to,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'folder_name' => (string)($r['folder_name'] ?? ''),
            'batch_date' => (string)($r['batch_date'] ?? ''),
            'people_count' => (int)($r['people_count'] ?? 0),
            'record_count' => (int)($r['record_count'] ?? 0),
            'last_created' => (string)($r['last_created'] ?? ''),
            'agency_list' => (string)($r['agency_list'] ?? ''),
        ];
    }
    return $rows;
}

function fetch_batch_records_from_db(PDO $pdo, string $company, string $batch, ?string $from, ?string $to, string $q, string $type, int $limit): array
{
    $sql =
        'SELECT '
        . 'id, folder_name, document_type, '
        . 'COALESCE(document_date, DATE(created_at)) AS document_date, '
        . 'first_name, middle_name, last_name, full_name, home_address, agency, detachment, '
        . 'birth_date, gender, created_at '
        . 'FROM attendance_records '
        . 'WHERE company = :company AND folder_name = :batch '
        . 'AND (:type1 = \'ALL\' OR document_type = :type2) '
        . 'AND (:from1 IS NULL OR COALESCE(document_date, DATE(created_at)) >= :from2) '
        . 'AND (:to1 IS NULL OR COALESCE(document_date, DATE(created_at)) <= :to2) '
        . 'AND (:q = \'\' OR CONCAT_WS(\' \' , full_name, first_name, middle_name, last_name, agency, home_address, detachment) LIKE :qlike) '
        . 'ORDER BY created_at DESC, id DESC '
        . 'LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'company' => $company,
        'batch' => $batch,
        'type1' => $type,
        'type2' => $type,
        'from1' => $from,
        'from2' => $from,
        'to1' => $to,
        'to2' => $to,
        'q' => $q,
        'qlike' => '%' . $q . '%',
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'id' => (int)($r['id'] ?? 0),
            'folder_name' => (string)($r['folder_name'] ?? ''),
            'document_type' => (string)($r['document_type'] ?? ''),
            'document_date' => (string)($r['document_date'] ?? ''),
            'document_date_raw' => (string)($r['document_date'] ?? ''),
            'first_name' => (string)($r['first_name'] ?? ''),
            'middle_name' => (string)($r['middle_name'] ?? ''),
            'last_name' => (string)($r['last_name'] ?? ''),
            'full_name' => (string)($r['full_name'] ?? ''),
            'home_address' => (string)($r['home_address'] ?? ''),
            'agency' => (string)($r['agency'] ?? ''),
            'detachment' => (string)($r['detachment'] ?? ''),
            'birth_date' => (string)($r['birth_date'] ?? ''),
            'birth_date_raw' => (string)($r['birth_date'] ?? ''),
            'gender' => (string)($r['gender'] ?? ''),
            'age' => '',
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }
    return $rows;
}

function read_attendance_meta_files(string $exportBase): array
{
    $records = [];
    if (!is_dir($exportBase)) {
        return $records;
    }

    $folders = glob($exportBase . '/*', GLOB_ONLYDIR);
    if (!$folders) {
        return $records;
    }

    foreach ($folders as $dir) {
        $folderName = basename($dir);
        $files = glob($dir . '/*.json');
        if (!$files) {
            continue;
        }
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $meta = json_decode($raw, true);
            if (!is_array($meta)) {
                continue;
            }
            $records[] = normalize_attendance_record($meta, $folderName);
        }
    }

    return $records;
}

function build_batch_list(array $records, ?string $from, ?string $to, string $q): array
{
    $batches = [];
    foreach ($records as $r) {
        if (!date_in_range($r['document_date'], $from, $to)) {
            continue;
        }
        $folder = $r['folder_name'] ?? '';
        if ($folder === '') {
            continue;
        }
        if ($q !== '' && stripos($folder, $q) === false) {
            continue;
        }
        if (!isset($batches[$folder])) {
            $batches[$folder] = [
                'folder_name' => $folder,
                'batch_date' => $r['document_date'] ?? '',
                'people' => [],
                'record_count' => 0,
                'last_created' => $r['created_at'] ?? '',
                'agencies' => [],
            ];
        }
        $batches[$folder]['record_count']++;
        $nameKey = strtolower((string)($r['full_name'] ?? ''));
        if ($nameKey !== '') {
            $batches[$folder]['people'][$nameKey] = true;
        }
        if (($r['document_date'] ?? '') > ($batches[$folder]['batch_date'] ?? '')) {
            $batches[$folder]['batch_date'] = $r['document_date'] ?? '';
        }
        if (($r['created_at'] ?? '') > ($batches[$folder]['last_created'] ?? '')) {
            $batches[$folder]['last_created'] = $r['created_at'] ?? '';
        }
        // Track unique agencies per batch for the batch list.
        $agency = trim((string)($r['agency'] ?? ''));
        if ($agency !== '') {
            $batches[$folder]['agencies'][strtolower($agency)] = $agency;
        }
    }

    $rows = [];
    foreach ($batches as $b) {
        // Flatten the agency set into a readable list.
        $agencies = array_values($b['agencies']);
        if ($agencies) {
            natcasesort($agencies);
            $agencies = array_values($agencies);
        }
        $rows[] = [
            'folder_name' => $b['folder_name'],
            'batch_date' => $b['batch_date'],
            'people_count' => count($b['people']),
            'record_count' => $b['record_count'],
            'last_created' => $b['last_created'],
            'agency_list' => $agencies ? implode(', ', $agencies) : '',
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcmp((string)($b['last_created'] ?? ''), (string)($a['last_created'] ?? ''));
    });

    return $rows;
}

function build_batch_records(array $records, string $batch, ?string $from, ?string $to, string $q, string $type, int $limit): array
{
    $picked = [];
    foreach ($records as $r) {
        if (($r['folder_name'] ?? '') !== $batch) {
            continue;
        }
        if ($type === 'neuro' || $type === 'drug_test') {
            if (($r['document_type'] ?? '') !== $type) {
                continue;
            }
        }
        if (!date_in_range($r['document_date'], $from, $to)) {
            continue;
        }
        if ($q !== '') {
            $hay = strtolower(
                (string)($r['full_name'] ?? '') . ' ' . (string)($r['agency'] ?? '') . ' ' . (string)($r['home_address'] ?? '')
            );
            if (stripos($hay, strtolower($q)) === false) {
                continue;
            }
        }

        $key = strtolower((string)($r['full_name'] ?? ''));
        if ($key === '') {
            $key = strtolower((string)($r['last_name'] ?? '') . '|' . (string)($r['first_name'] ?? '') . '|' . (string)($r['middle_name'] ?? ''));
        }

        $existing = $picked[$key] ?? null;
        if ($existing === null || (string)($r['created_at'] ?? '') > (string)($existing['created_at'] ?? '')) {
            $picked[$key] = $r;
        }
    }

    $rows = array_values($picked);
    usort($rows, static function ($a, $b) {
        $la = (string)($a['last_name'] ?? '');
        $lb = (string)($b['last_name'] ?? '');
        $cmp = strcmp($la, $lb);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
    });

    return array_slice($rows, 0, $limit);
}

function export_csv(array $rows, bool $isBatchList): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-export.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    if ($isBatchList) {
        fputcsv($out, ['No', 'Batch Name', 'Batch Date', 'Pax', 'Agency']);
        $rowNo = 1;
        foreach ($rows as $r) {
            fputcsv($out, [
                (string)$rowNo,
                (string)($r['folder_name'] ?? ''),
                (string)($r['batch_date'] ?? ''),
                (string)($r['people_count'] ?? ''),
                (string)($r['agency_list'] ?? ''),
            ]);
            $rowNo++;
        }
    } else {
        fputcsv($out, [
            'Batch',
            'Document Type',
            'Document Date',
            'First Name',
            'Middle Name',
            'Last Name',
            'Full Name',
            'Home Address',
            'Agency',
            'Detachment',
            'Birth Date',
            'Gender',
            'Created At',
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string)($r['folder_name'] ?? ''),
                (string)($r['document_type'] ?? ''),
                (string)($r['document_date'] ?? ''),
                (string)($r['first_name'] ?? ''),
                (string)($r['middle_name'] ?? ''),
                (string)($r['last_name'] ?? ''),
                (string)($r['full_name'] ?? ''),
                (string)($r['home_address'] ?? ''),
                (string)($r['agency'] ?? ''),
                (string)($r['detachment'] ?? ''),
                (string)($r['birth_date'] ?? ''),
                (string)($r['gender'] ?? ''),
                (string)($r['created_at'] ?? ''),
            ]);
        }
    }
    fclose($out);
    exit;
}

/**
 * Output a Word-compatible document for a batch detail list, with optional preview mode.
 */
function export_word_batch_detail(array $rows, string $batch, bool $preview): void
{
    if ($preview) {
        header('Content-Type: text/html; charset=utf-8');
    } else {
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance-batch-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $batch) . '.doc"');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $safeBatch = htmlspecialchars($batch, ENT_QUOTES, 'UTF-8');
    $generatedOn = date('F j, Y - g:i A');
    $reportDate = '';
    $agencyMap = [];
    foreach ($rows as $row) {
        $docDate = (string)($row['document_date'] ?? '');
        if ($docDate === '') {
            // Keep scanning agencies even if the document date is missing.
        } else {
            if ($reportDate === '' || $docDate > $reportDate) {
                $reportDate = $docDate;
            }
        }

        $agency = trim((string)($row['agency'] ?? ''));
        if ($agency !== '') {
            $agencyMap[strtolower($agency)] = $agency;
        }
    }
    $reportDateLabel = $reportDate !== '' ? date('F j, Y', strtotime($reportDate)) : '';
    $reportAgencyList = '';
    if ($agencyMap) {
        $agencies = array_values($agencyMap);
        natcasesort($agencies);
        $agencies = array_values($agencies);
        $reportAgencyList = implode(', ', $agencies);
    }
    $chunks = array_chunk($rows, 50);
    if (!$chunks) {
        $chunks = [[]];
    }

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n";
    echo "<title>Attendance Batch - " . $safeBatch . "</title>\n";
    echo "<style>";
    echo "body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;margin:20mm;}";
    echo ".doc-header{width:100%;border-collapse:collapse;margin-bottom:4px;border-bottom:2px solid #1f2937;table-layout:fixed;}";
    echo ".doc-header td{border:none;vertical-align:middle;padding:0 2px 4px 2px;}";
    echo ".doc-logo{width:64px;height:64px;object-fit:contain;display:inline-block;}";
    echo ".doc-title{font-size:18px;font-weight:700;text-align:center;letter-spacing:0.7px;text-transform:uppercase;white-space:nowrap;}";
    echo ".doc-subtitle{font-size:11px;color:#475569;text-align:center;margin-top:2px;line-height:1.35;}";
    echo ".doc-meta{width:100%;border-collapse:collapse;margin:6px 0 10px 0;}";
    echo ".doc-meta td{border:none;font-size:12px;color:#475569;}";
    echo ".doc-meta .right{text-align:right;}";
    echo ".page{display:flex;flex-direction:column;min-height:273mm;}";
    echo ".page-body{flex:1 1 auto;}";
    echo ".doc-footer{margin-top:8px;font-size:11px;color:#475569;font-style:italic;display:flex;justify-content:space-between;align-items:center;}";
    echo ".title{font-size:13px;font-weight:700;margin:10px 0 4px 0;text-transform:uppercase;letter-spacing:0.6px;text-align:center;}";
    echo ".subtitle{font-size:12px;color:#475569;margin-bottom:12px;}";
    echo ".preview-bar{display:flex;gap:10px;align-items:center;margin-bottom:16px;}";
    echo ".btn{display:inline-block;padding:6px 12px;border-radius:6px;border:1px solid #1f3a8a;background:#1f3a8a;color:#fff;text-decoration:none;font-size:12px;}";
    echo ".btn.outline{background:#fff;color:#1f3a8a;}";
    echo "table{width:100%;border-collapse:collapse;margin-top:8px;}";
    echo "th,td{border:1px solid #cbd5f5;padding:6px 8px;font-size:12px;text-align:left;vertical-align:top;}";
    echo "th{background:#e2e8f0;text-transform:uppercase;font-size:11px;letter-spacing:0.5px;color:#1f2937;}";
    echo ".row-alt td{background:#f8fafc;}";
    echo ".num{font-family:Courier,monospace;text-align:center;width:42px;color:#334155;}";
    echo ".page-break{page-break-after:always;break-after:page;margin-top:24px;}";
    echo "@page{size:A4;margin:12mm;}";
    echo "@media print{.preview-bar{display:none;}body{margin:0;} .doc-header{border-bottom:2px solid #0f172a;}}";
    echo "</style>\n</head>\n<body>\n";

    if ($preview) {
        $params = [
            'export' => 'word',
            'batch' => $batch,
            'q' => (string)($_GET['q'] ?? ''),
            'type' => (string)($_GET['type'] ?? ''),
            'from' => (string)($_GET['from'] ?? ''),
            'to' => (string)($_GET['to'] ?? ''),
        ];
        $downloadUrl = 'attendance.php?' . http_build_query($params);
        echo "<div class=\"preview-bar\">";
        echo "<a class=\"btn\" href=\"" . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . "\">Download Word</a>";
        echo "</div>\n";
    }

    $totalPages = count($chunks);
    $rowIndex = 1;
    foreach ($chunks as $pageIndex => $chunk) {
        echo "<div class=\"page\">\n";
        echo "<table class=\"doc-header\">\n<tr>";
        echo "<td style=\"width:14%;text-align:center;\">";
        echo "<img class=\"doc-logo\" src=\"../assets/img/brainmaster.jpg\" alt=\"Brain Master\" style=\"border-radius:50%;\">";
        echo "</td>";
        echo "<td style=\"width:72%;\">";
        echo "<div class=\"doc-title\">Brain Master Diagnostic Center</div>";
        echo "<div class=\"doc-subtitle\">#12 Unit 6 Corner Complex Bldg. New York Ave. Cor. Felix Manalo Brgy. Immaculate Conception, Cubao Quezon City</div>";
        echo "</td>";
        echo "<td style=\"width:14%;text-align:center;\">";
        echo "<img class=\"doc-logo\" src=\"../assets/img/erms-logo.svg\" alt=\"ERMS\">";
        echo "</td>";
        echo "</tr>\n</table>\n";
        echo "<div class=\"title\">Attendance Batch Report</div>\n";
        echo "<table class=\"doc-meta\">\n<tr>";
        echo "<td>Agency: " . htmlspecialchars($reportAgencyList !== '' ? $reportAgencyList : '-', ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td class=\"right\">Date: " . htmlspecialchars($reportDateLabel !== '' ? $reportDateLabel : $safeBatch, ENT_QUOTES, 'UTF-8') . "</td>";
        echo "</tr>\n</table>\n";
        echo "<div class=\"page-body\">\n";
        echo "<table>\n<thead>\n<tr>";
        echo "<th class=\"num\">No</th><th>Last Name</th><th>First Name</th><th>M.I</th><th>Gender</th><th>Birth Date</th><th>Age</th><th>Agency</th><th>Detachment</th>";
        echo "</tr>\n</thead>\n<tbody>\n";

        if (!$chunk) {
            echo "<tr><td colspan=\"9\">No attendance records found for this batch.</td></tr>\n";
        } else {
            foreach ($chunk as $row) {
                $age = compute_age($row['age'] ?? '', $row['birth_date_raw'] ?? '', $row['birth_date'] ?? '');
                $rowClass = ($rowIndex % 2 === 0) ? ' class="row-alt"' : '';
                echo "<tr" . $rowClass . ">";
                echo "<td class=\"num\">" . $rowIndex . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['gender'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td class=\"num\">" . htmlspecialchars((string)$age, ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['agency'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)($row['detachment'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "</tr>\n";
                $rowIndex++;
            }
        }

        echo "</tbody>\n</table>\n";
        echo "</div>\n";
        echo "<div class=\"doc-footer\">";
        echo "<div>" . htmlspecialchars($generatedOn, ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<div>Page " . ($pageIndex + 1) . " of " . $totalPages . "</div>";
        echo "</div>\n";
        echo "</div>\n";
        if ($pageIndex + 1 < $totalPages) {
            echo "<div class=\"page-break\"></div>\n";
        }
    }

    echo "</body>\n</html>";
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'ALL'));
$from = normalize_date($_GET['from'] ?? null);
$to = normalize_date($_GET['to'] ?? null);
$batch = trim((string)($_GET['batch'] ?? ''));
$exportMode = trim((string)($_GET['export'] ?? ''));
$preview = (string)($_GET['preview'] ?? '') === '1';

$limit = $exportMode !== '' ? 5000 : 200;

$exportBase = dirname(__DIR__) . '/export_nuero';
$allRecords = [];
$batchRows = [];
$rows = [];
$usedDb = false;
$companyForQuery = (string)($_SESSION['company'] ?? 'brainmaster');

try {
    $pdo = db();
    try {
        ensure_attendance_table($pdo);
        ensure_attendance_columns($pdo);
    } catch (Throwable $e) {
        // Ignore migration errors here; the core requirement is to display DB data.
    }

    if ($batch !== '') {
        $rows = fetch_batch_records_from_db($pdo, $companyForQuery, $batch, $from, $to, $q, $type, $limit);
    } else {
        $batchRows = fetch_batch_list_from_db($pdo, $companyForQuery, $from, $to, $q, $limit);
    }

    $usedDb = true;
} catch (Throwable $e) {
    $usedDb = false;
    error_log(
        '[ERMS][attendance] DB failed; falling back to meta scan. '
        . 'company=' . $companyForQuery
        . ' batch=' . $batch
        . ' q=' . $q
        . ' from=' . (string)($from ?? '')
        . ' to=' . (string)($to ?? '')
        . ' err=' . $e->getMessage()
    );
}

header('X-ERMS-Attendance-Source: ' . ($usedDb ? 'db' : 'legacy'));
header('X-ERMS-Attendance-Version: 2026-04-20-1');

// CSRF token for inline edit/delete actions.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['csrf_attendance']) || !is_string($_SESSION['csrf_attendance']) || $_SESSION['csrf_attendance'] === '') {
    $_SESSION['csrf_attendance'] = bin2hex(random_bytes(16));
}
$csrfAttendance = (string)$_SESSION['csrf_attendance'];

if (!$usedDb) {
    $allRecords = read_attendance_meta_files($exportBase);
}

if ($batch !== '') {
    if (!$usedDb) {
        $rows = build_batch_records($allRecords, $batch, $from, $to, $q, $type, $limit);
    }
    if ($exportMode === 'word') {
        export_word_batch_detail($rows, $batch, $preview);
    }
    if ($exportMode === '1') {
        export_csv($rows, false);
    }
} else {
    if (!$usedDb) {
        $batchRows = build_batch_list($allRecords, $from, $to, $q);
    }
    if ($exportMode === '1') {
        export_csv($batchRows, true);
    }
}

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Attendance | ERMS</title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/css/home_redesign.css" />
    <style>
        .bm-toolbar{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            align-items:flex-start;
            justify-content:space-between;
            margin-bottom:14px;
        }
        .bm-toolbar .bm-left{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:10px;
            align-items:center;
            flex:1 1 auto;
            min-width:260px;
        }
        .bm-toolbar .bm-right{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            align-items:center;
        }
        .bm-toolbar .btn{height:36px;}
        .bm-panel{box-shadow:var(--sx);}
        .bm-table{width:100%;border-collapse:separate;border-spacing:0;min-width:920px;}
        .bm-table th{
            background:var(--gray-50);
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:0.5px;
            color:var(--gray-500);
            text-align:left;
            padding:11px 12px;
            border-bottom:1px solid var(--gray-200);
            position:sticky;
            top:0;
            z-index:1;
        }
        .bm-table td{
            padding:11px 12px;
            border-bottom:1px solid var(--gray-100);
            font-size:13px;
            color:var(--gray-700);
            vertical-align:middle;
        }
        .bm-table tr:hover td{background:var(--gray-25);}
        .bm-table-wrap{width:100%;overflow:auto;border-radius:12px;border:1px solid var(--gray-200);}
        .bm-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;background:var(--navy-50);color:var(--navy-700);border:1px solid var(--navy-200);}
        .bm-chip.drug{background:var(--orange-50);color:var(--orange-700);border-color:var(--orange-200);}
        .bm-muted{color:var(--gray-400);font-size:12px;}
        .bm-input-inline{min-width:180px;}
        .bm-num{font-family:var(--mono);font-size:12px;color:var(--gray-600);}
        .bm-col-no{width:56px;}
        .bm-col-pax{width:90px;}
        .bm-col-mi{width:70px;}
        .bm-col-age{width:70px;}
        .bm-col-gender{width:90px;}
        .bm-col-birth{width:120px;}
        .bm-col-agency{min-width:160px;}
        .bm-col-batch{min-width:200px;}
        .bm-link{color:var(--navy-700);font-weight:600;text-decoration:none;}
        .bm-link:hover{text-decoration:underline;}
        .bm-col-actions{width:170px;}
        .bm-tbl-input{height:34px;padding:8px 10px;font-size:13px;min-width:120px;}
        .bm-tbl-input.mi{min-width:60px;width:70px;}
        .bm-tbl-input.gender{min-width:90px;width:100px;}
        .bm-tbl-input.birth{min-width:130px;width:140px;}
        .bm-actions-cell{white-space:nowrap;text-align:right;}
        @media(max-width:860px){
            .bm-toolbar{gap:10px;}
            .bm-toolbar .bm-left{grid-template-columns:1fr;}
            .bm-toolbar .bm-right{width:100%;justify-content:flex-start;}
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sb" id="bmSidebar" aria-label="Sidebar Navigation">
            <div class="sb-top">
                <div class="sb-brand">
                    <div class="sb-logo" aria-hidden="true">
                        <img src="../assets/img/brainmaster.jpg" alt="" />
                    </div>
                    <div>
                        <div class="sb-name">ERMS</div>
                        <div class="sb-tagline">Brain Master</div>
                    </div>
                </div>
            </div>

            <div class="sb-nav">
                <div class="sb-nav-label">Navigation</div>
                <a class="sb-item" href="neuro_documents.php" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h10"/><path d="M7 7h10"/><path d="M7 11h10"/><path d="M7 15h7"/><path d="M6 3h-1a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/></svg>
                    <span style="flex:1">Documents</span>
                </a>
                <a class="sb-item on" href="attendance.php" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/></svg>
                    <span style="flex:1">Attendance</span>
                </a>
                <?php if ($role === 'admin'): ?>
                    <a class="sb-item" href="neuro_documents_list.php" style="text-decoration:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                        <span style="flex:1">Documents List</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="sb-foot">
                <div class="sb-dd" id="bmAccountMenu">
                    <button class="sb-trigger" type="button" aria-haspopup="menu" aria-expanded="false">
                        <div class="sb-av"><?php echo h($userInitials); ?></div>
                        <div class="sb-meta">
                            <div class="sb-uname" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo h($userName); ?></div>
                            <div class="sb-urole"><?php echo h($roleLabel); ?></div>
                        </div>
                        <span class="sb-chev" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </span>
                    </button>
                    <div class="sb-menu" role="menu" aria-label="Account actions">
                        <?php if ($role === 'admin'): ?>
                            <a class="sb-mi" role="menuitem" href="../auth/switch_company.php">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3.03-6.72"/><path d="M21 3v6h-6"/></svg>
                                Switch Company
                            </a>
                        <?php endif; ?>
                        <a class="sb-mi d js-logout" role="menuitem" href="../auth/logout.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l-1 4 4-1"/><path d="M3 12h11"/><path d="M10 8l4 4-4 4"/><path d="M14 4h6v16h-6"/></svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <div class="main">
            <div class="topbar">
                <div class="tb-left">
                    <button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-controls="bmSidebar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>
                    </button>
                    <div>
                        <div class="tb-pg">Attendance</div>
                        <div class="tb-crumb">ERMS &rsaquo; Brain Master</div>
                    </div>
                </div>
                <div class="tb-r">
                    <div class="tb-clock" aria-label="Current date and time"><span id="topbarDateTimeEmployee">--</span></div>
                </div>
            </div>

            <div class="content">
                <div class="bm-wrap">
                    <div class="bm-section">
                        <?php if ($batch !== ''): ?>
                            <div class="bm-title">Attendance · Batch <?php echo h($batch); ?></div>
                        <?php else: ?>
                            <div class="bm-title">Attendance Batches</div>
                        <?php endif; ?>
                        <div class="bm-panel" style="padding:16px;">
                            <form method="get" class="bm-toolbar">
                                <div class="bm-left">
                                    <?php if ($batch !== ''): ?>
                                        <input type="hidden" name="batch" value="<?php echo h($batch); ?>" />
                                        <input class="bm-input bm-input-inline" type="text" name="q" placeholder="Search name, agency, or address" value="<?php echo h($q); ?>" />
                                        <select class="bm-input" name="type">
                                            <option value="ALL" <?php echo $type === 'ALL' ? 'selected' : ''; ?>>All Types</option>
                                            <option value="neuro" <?php echo $type === 'neuro' ? 'selected' : ''; ?>>Neuro</option>
                                            <option value="drug_test" <?php echo $type === 'drug_test' ? 'selected' : ''; ?>>Drug Test</option>
                                        </select>
                                        <input class="bm-input" type="date" name="from" value="<?php echo h($from); ?>" />
                                        <input class="bm-input" type="date" name="to" value="<?php echo h($to); ?>" />
                                        <button class="btn btn-s sm" type="submit">Apply</button>
                                        <a class="btn btn-g sm" href="attendance.php?batch=<?php echo urlencode($batch); ?>">Reset</a>
                                    <?php else: ?>
                                        <input class="bm-input bm-input-inline" type="text" name="q" placeholder="Search batch/folder name" value="<?php echo h($q); ?>" />
                                        <input class="bm-input" type="date" name="from" value="<?php echo h($from); ?>" />
                                        <input class="bm-input" type="date" name="to" value="<?php echo h($to); ?>" />
                                        <button class="btn btn-s sm" type="submit">Apply</button>
                                        <a class="btn btn-g sm" href="attendance.php">Reset</a>
                                    <?php endif; ?>
                                </div>
                                <div class="bm-right">
                                    <?php if ($batch !== ''): ?>
                                        <a class="btn btn-s sm" href="attendance.php">Back to Batches</a>
                                        <a
                                            class="btn btn-p sm bm-export-word"
                                            href="attendance.php?export=word&amp;preview=1&amp;batch=<?php echo urlencode($batch); ?>&amp;q=<?php echo urlencode($q); ?>&amp;type=<?php echo urlencode($type); ?>&amp;from=<?php echo urlencode((string)$from); ?>&amp;to=<?php echo urlencode((string)$to); ?>"
                                        >Export</a>
                                    <?php else: ?>
                                        <!-- Export removed for batch list per request. -->
                                    <?php endif; ?>
                                </div>
                            </form>

                            <?php if ($batch !== ''): ?>
                                <div class="bm-table-wrap">
                                    <table class="bm-table">
                                        <thead>
                                            <tr>
                                                <th class="bm-col-no">No.</th>
                                                <th>Last Name</th>
                                                <th>First Name</th>
                                                <th class="bm-col-mi">M.I</th>
                                                <th class="bm-col-gender">Gender</th>
                                                <th class="bm-col-birth">Birth Date</th>
                                                <th class="bm-col-age">Age</th>
                                                <th class="bm-col-agency">Agency</th>
                                                <th>Detachment</th>
                                                <?php if ($role === 'admin' && $usedDb): ?>
                                                    <th class="bm-col-actions" style="text-align:right;">Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$rows): ?>
                                                <tr>
                                                    <td colspan="<?php echo ($role === 'admin' && $usedDb) ? '10' : '9'; ?>" class="bm-muted" style="padding:16px;">No attendance records found for this batch.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($rows as $idx => $row): ?>
                                                    <?php $age = compute_age($row['age'] ?? '', $row['birth_date_raw'] ?? '', $row['birth_date'] ?? ''); ?>
                                                    <tr <?php echo ($usedDb && !empty($row['id'])) ? 'data-attendance-id="' . (int)$row['id'] . '"' : ''; ?>>
                                                        <td class="bm-num" data-field="no"><?php echo (int)$idx + 1; ?></td>
                                                        <td data-field="last_name"><?php echo h($row['last_name'] ?? ''); ?></td>
                                                        <td data-field="first_name"><?php echo h($row['first_name'] ?? ''); ?></td>
                                                        <td data-field="middle_name"><?php echo h($row['middle_name'] ?? ''); ?></td>
                                                        <td data-field="gender"><?php echo h($row['gender'] ?? ''); ?></td>
                                                        <td data-field="birth_date"><?php echo h($row['birth_date'] ?? ''); ?></td>
                                                        <td class="bm-num" data-field="age"><?php echo h($age); ?></td>
                                                        <td data-field="agency"><?php echo h($row['agency'] ?? ''); ?></td>
                                                        <td data-field="detachment"><?php echo h($row['detachment'] ?? ''); ?></td>
                                                        <?php if ($role === 'admin' && $usedDb): ?>
                                                            <td class="bm-actions-cell">
                                                                <?php if (!empty($row['id'])): ?>
                                                                    <button class="btn btn-s sm bm-edit" type="button">Edit</button>
                                                                    <button class="btn btn-p sm bm-save" type="button" style="display:none;">Save</button>
                                                                    <button class="btn btn-g sm bm-cancel" type="button" style="display:none;">Cancel</button>
                                                                    <button class="btn btn-g sm bm-delete" type="button">Delete</button>
                                                                <?php else: ?>
                                                                    <span class="bm-muted">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="bm-muted" style="margin-top:8px;">Showing up to <?php echo (int)$limit; ?> people in this batch.</div>
                            <?php else: ?>
                                <div class="bm-table-wrap">
                                    <table class="bm-table">
                                        <thead>
                                            <tr>
                                                <th class="bm-col-no">No</th>
                                                <th class="bm-col-batch">Batch Name</th>
                                                <th>Batch Date</th>
                                                <th class="bm-col-pax">Pax</th>
                                                <th class="bm-col-agency">Agency</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$batchRows): ?>
                                                <tr>
                                                    <td colspan="5" class="bm-muted" style="padding:16px;">No batches found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($batchRows as $idx => $row): ?>
                                                    <?php $rowNo = (int)$idx + 1; ?>
                                                    <tr>
                                                        <td class="bm-num"><?php echo $rowNo; ?></td>
                                                        <td>
                                                            <a class="bm-link" href="attendance.php?batch=<?php echo urlencode((string)($row['folder_name'] ?? '')); ?>">
                                                                <?php echo h($row['folder_name'] ?? ''); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo h($row['batch_date'] ?? ''); ?></td>
                                                        <td class="bm-num"><?php echo h((string)($row['people_count'] ?? '0')); ?></td>
                                                        <td><?php echo h($row['agency_list'] ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="bm-muted" style="margin-top:8px;">Showing up to <?php echo (int)$limit; ?> batches.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <script>
        (function () {
            var dd = document.getElementById('bmAccountMenu');
            if (!dd) return;
            var trigger = dd.querySelector('.sb-trigger');
            if (!trigger) return;

            function setExpanded(isOpen) {
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            function openMenu() {
                dd.classList.add('is-open');
                setExpanded(true);
            }

            function closeMenu() {
                dd.classList.remove('is-open');
                setExpanded(false);
            }

            function toggleMenu() {
                if (dd.classList.contains('is-open')) {
                    closeMenu();
                    return;
                }
                openMenu();
            }

            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                toggleMenu();
            });

            trigger.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleMenu();
                }
                if (e.key === 'Escape') {
                    closeMenu();
                }
            });

            document.addEventListener('click', function (e) {
                if (!dd.classList.contains('is-open')) return;
                if (dd.contains(e.target)) return;
                closeMenu();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeMenu();
                }
            });
        })();
    </script>

    <script>
        (function () {
            var table = document.querySelector('.bm-table');
            if (!table) return;

            var csrf = <?php echo json_encode($csrfAttendance, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            function qs(root, sel) {
                return root ? root.querySelector(sel) : null;
            }

            function textOf(cell) {
                return (cell && cell.textContent ? cell.textContent : '').trim();
            }

            function computeAgeFromBirth(birthRaw) {
                birthRaw = String(birthRaw || '').trim();
                if (!/^\d{4}-\d{2}-\d{2}$/.test(birthRaw)) return '';
                var parts = birthRaw.split('-');
                var y = parseInt(parts[0], 10);
                var m = parseInt(parts[1], 10) - 1;
                var d = parseInt(parts[2], 10);
                if (isNaN(y) || isNaN(m) || isNaN(d)) return '';
                var birth = new Date(y, m, d);
                if (isNaN(birth.getTime())) return '';
                var today = new Date();
                var age = today.getFullYear() - birth.getFullYear();
                var mm = today.getMonth() - birth.getMonth();
                if (mm < 0 || (mm === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                return age < 0 ? '' : String(age);
            }

            function setEditing(row, isEditing) {
                row.dataset.editing = isEditing ? '1' : '';
                var editBtn = qs(row, '.bm-edit');
                var saveBtn = qs(row, '.bm-save');
                var cancelBtn = qs(row, '.bm-cancel');
                var deleteBtn = qs(row, '.bm-delete');
                if (editBtn) editBtn.style.display = isEditing ? 'none' : '';
                if (deleteBtn) deleteBtn.style.display = isEditing ? 'none' : '';
                if (saveBtn) saveBtn.style.display = isEditing ? '' : 'none';
                if (cancelBtn) cancelBtn.style.display = isEditing ? '' : 'none';
            }

            function makeInput(value, extraClass, type) {
                var input = document.createElement('input');
                input.className = 'bm-input bm-tbl-input' + (extraClass ? (' ' + extraClass) : '');
                input.type = type || 'text';
                input.value = value || '';
                return input;
            }

            function beginEdit(row) {
                if (!row || row.dataset.editing === '1') return;
                var id = row.getAttribute('data-attendance-id');
                if (!id) return;

                var fields = ['last_name', 'first_name', 'middle_name', 'gender', 'birth_date', 'agency', 'detachment'];
                fields.forEach(function (f) {
                    var cell = qs(row, '[data-field="' + f + '"]');
                    if (!cell) return;
                    row.dataset['orig_' + f] = textOf(cell);

                    var current = row.dataset['orig_' + f] || '';
                    var inputType = (f === 'birth_date') ? 'date' : 'text';
                    var cls = '';
                    if (f === 'middle_name') cls = 'mi';
                    if (f === 'gender') cls = 'gender';
                    if (f === 'birth_date') cls = 'birth';

                    var input = makeInput(current, cls, inputType);
                    cell.textContent = '';
                    cell.appendChild(input);
                });

                setEditing(row, true);
            }

            function cancelEdit(row) {
                if (!row) return;
                var fields = ['last_name', 'first_name', 'middle_name', 'gender', 'birth_date', 'agency', 'detachment'];
                fields.forEach(function (f) {
                    var cell = qs(row, '[data-field="' + f + '"]');
                    if (!cell) return;
                    var orig = row.dataset['orig_' + f] || '';
                    cell.textContent = orig;
                });
                setEditing(row, false);
            }

            function setButtonsDisabled(row, disabled) {
                ['.bm-edit', '.bm-save', '.bm-cancel', '.bm-delete'].forEach(function (sel) {
                    var b = qs(row, sel);
                    if (b) b.disabled = !!disabled;
                });
            }

            async function saveEdit(row) {
                if (!row) return;
                var id = row.getAttribute('data-attendance-id');
                if (!id) return;

                function val(field) {
                    var cell = qs(row, '[data-field="' + field + '"]');
                    if (!cell) return '';
                    var input = cell.querySelector('input');
                    return input ? String(input.value || '').trim() : '';
                }

                var payload = {
                    csrf: csrf,
                    id: id,
                    last_name: val('last_name'),
                    first_name: val('first_name'),
                    middle_name: val('middle_name'),
                    gender: val('gender'),
                    birth_date: val('birth_date'),
                    agency: val('agency'),
                    detachment: val('detachment')
                };

                if (!payload.last_name || !payload.first_name) {
                    alert('Last Name and First Name are required.');
                    return;
                }

                setButtonsDisabled(row, true);
                try {
                    var res = await fetch('../auth/update_attendance_record.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams(payload).toString()
                    });
                    var json = null;
                    try { json = await res.json(); } catch (e) {}

                    if (!res.ok || !json || !json.ok) {
                        var msg = (json && json.error) ? json.error : ('Save failed (' + res.status + ').');
                        alert(msg);
                        return;
                    }

                    var rec = json.record || {};
                    var map = {
                        last_name: rec.last_name || payload.last_name,
                        first_name: rec.first_name || payload.first_name,
                        middle_name: (typeof rec.middle_name === 'string') ? rec.middle_name : payload.middle_name,
                        gender: (typeof rec.gender === 'string') ? rec.gender : payload.gender,
                        birth_date: (typeof rec.birth_date === 'string') ? rec.birth_date : payload.birth_date,
                        agency: (typeof rec.agency === 'string') ? rec.agency : payload.agency,
                        detachment: (typeof rec.detachment === 'string') ? rec.detachment : payload.detachment
                    };

                    Object.keys(map).forEach(function (f) {
                        var cell = qs(row, '[data-field="' + f + '"]');
                        if (!cell) return;
                        cell.textContent = map[f] || '';
                        row.dataset['orig_' + f] = map[f] || '';
                    });

                    var ageCell = qs(row, '[data-field="age"]');
                    if (ageCell) {
                        var age = (typeof rec.age === 'string' && rec.age !== '') ? rec.age : computeAgeFromBirth(map.birth_date);
                        ageCell.textContent = age;
                    }

                    setEditing(row, false);
                } finally {
                    setButtonsDisabled(row, false);
                }
            }

            async function deleteRow(row) {
                if (!row) return;
                var id = row.getAttribute('data-attendance-id');
                if (!id) return;
                if (!confirm('Delete this record?')) return;

                setButtonsDisabled(row, true);
                try {
                    var res = await fetch('../auth/delete_attendance_record.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({ csrf: csrf, id: id }).toString()
                    });
                    var json = null;
                    try { json = await res.json(); } catch (e) {}

                    if (!res.ok || !json || !json.ok) {
                        var msg = (json && json.error) ? json.error : ('Delete failed (' + res.status + ').');
                        alert(msg);
                        return;
                    }

                    var tbody = row.parentNode;
                    if (tbody) {
                        tbody.removeChild(row);
                    }

                    // Re-number visible rows.
                    var rows = table.querySelectorAll('tbody tr[data-attendance-id]');
                    for (var i = 0; i < rows.length; i++) {
                        var noCell = rows[i].querySelector('[data-field="no"]');
                        if (noCell) {
                            noCell.textContent = String(i + 1);
                        }
                    }
                } finally {
                    setButtonsDisabled(row, false);
                }
            }

            table.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var row = target.closest('tr');
                if (!row) return;

                if (target.classList.contains('bm-edit')) {
                    beginEdit(row);
                } else if (target.classList.contains('bm-cancel')) {
                    cancelEdit(row);
                } else if (target.classList.contains('bm-save')) {
                    saveEdit(row);
                } else if (target.classList.contains('bm-delete')) {
                    deleteRow(row);
                }
            });
        })();
    </script>

    <script>
        (function () {
            var toggle = document.querySelector('.sidebar-toggle');
            var backdrop = document.querySelector('.sidebar-backdrop');
            if (!toggle || !backdrop) return;

            function openNav() {
                document.body.classList.add('sidebar-open');
            }

            function closeNav() {
                document.body.classList.remove('sidebar-open');
            }

            toggle.addEventListener('click', function () {
                if (document.body.classList.contains('sidebar-open')) {
                    closeNav();
                    return;
                }
                openNav();
            });

            backdrop.addEventListener('click', function () {
                closeNav();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeNav();
                }
            });
        })();
    </script>


    <script>
        (function () {
            var el = document.getElementById('topbarDateTimeEmployee');
            if (!el) return;

            function pad(n) { return String(n).padStart(2, '0'); }

            function render() {
                var d = new Date();
                var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var hours = d.getHours();
                var ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                if (hours === 0) hours = 12;
                var text = days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + pad(d.getDate()) + ' • ' + hours + ':' + pad(d.getMinutes()) + ' ' + ampm;
                el.textContent = text;
            }

            render();
            setInterval(render, 1000);
        })();
    </script>

    <script>
        (function () {
            var trigger = document.querySelector('.bm-export-word');
            if (!trigger) return;

            trigger.addEventListener('click', function (e) {
                e.preventDefault();

                // Print the preview in a hidden iframe so the current page stays in place.
                var iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                iframe.setAttribute('aria-hidden', 'true');
                iframe.src = trigger.getAttribute('href');

                iframe.onload = function () {
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch (err) {
                        window.open(trigger.getAttribute('href'), '_self');
                    }
                    setTimeout(function () {
                        if (iframe.parentNode) {
                            iframe.parentNode.removeChild(iframe);
                        }
                    }, 1000);
                };

                document.body.appendChild(iframe);
            });
        })();
    </script>

    <div class="logout-modal" id="logoutModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="logout-card" role="document">
            <div class="logout-title">Logout</div>
            <div class="logout-text">Are you sure you want to log out?</div>
            <div class="logout-actions">
                <button type="button" class="logout-btn cancel" data-logout-cancel>Cancel</button>
                <button type="button" class="logout-btn confirm" data-logout-confirm>Logout</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('logoutModal');
            var triggers = document.querySelectorAll('.js-logout');
            if (!modal || !triggers.length) return;

            var confirmBtn = modal.querySelector('[data-logout-confirm]');
            var cancelBtn = modal.querySelector('[data-logout-cancel]');
            var href = '';

            function openModal(url) {
                href = url || '../auth/logout.php';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    openModal(trigger.getAttribute('href'));
                });
            });

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    window.location.href = href || '../auth/logout.php';
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    closeModal();
                });
            }

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
