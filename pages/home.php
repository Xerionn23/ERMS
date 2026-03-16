<?php
 require_once __DIR__ . '/../includes/guards.php';
 require_login();

 $role = (string)($_SESSION['user_role'] ?? '');

 if ($role === 'employee') {
     $_SESSION['company'] = 'brainmaster';
     header('Location: neuro_documents.php');
     exit;
 }

 if ($role === 'security_operation') {
     if (!isset($_SESSION['company'])) {
         $_SESSION['company'] = 'jubecer';
     }
 }

 if ($role === 'admin') {
     if (!isset($_SESSION['company'])) {
         header('Location: choose_company.php');
         exit;
     }
 }

 require_company();
 
 $company = (string)($_SESSION['company'] ?? '');
 $isBrainMaster = $company === 'brainmaster';
 $companyLabel = $isBrainMaster ? 'Brain Master' : 'Jubecer';
 
 $userName = (string)($_SESSION['user_name'] ?? 'User');
 $userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
 if ($userInitials === '') {
     $userInitials = 'U';
 }
 
 $requiredRequirements = ['SSS', 'PAG-IBIG', 'PhilHealth', 'License'];
 $requiredRequirementTypes = [];
 $jubecerSummary = [
     'total_guards' => 0,
     'guards_with_missing' => 0,
     'guards_with_expired_license' => 0,
     'guards_with_expiring_license' => 0,
 ];
 $jubecerLicenseAlerts = [];
 $jubecerGuards = [];
 
 if (!$isBrainMaster && $company === 'jubecer') {
     require_once __DIR__ . '/../includes/db.php';
     try {
         $pdo = db();

         $reqStmt = $pdo->query("SELECT id, code, name, expires FROM requirement_types WHERE is_required = 1 ORDER BY id");
         $requirementTypes = $reqStmt->fetchAll();
         if (is_array($requirementTypes) && count($requirementTypes) > 0) {
             $requiredRequirements = array_map(static fn($r) => (string)($r['name'] ?? ''), $requirementTypes);
             $requiredRequirements = array_values(array_filter($requiredRequirements, static fn($n) => $n !== ''));
             $requiredRequirementTypes = array_map(static fn($r) => [
                 'id' => (int)($r['id'] ?? 0),
                 'code' => (string)($r['code'] ?? ''),
                 'name' => (string)($r['name'] ?? ''),
                 'expires' => (int)($r['expires'] ?? 0) === 1,
             ], $requirementTypes);
         }
 
         $summarySql = "
 SELECT
     COUNT(*) AS total_guards,
     SUM(CASE WHEN t.missing_count > 0 THEN 1 ELSE 0 END) AS guards_with_missing,
     SUM(CASE WHEN t.expired_license > 0 THEN 1 ELSE 0 END) AS guards_with_expired_license,
     SUM(CASE WHEN t.expiring_license > 0 THEN 1 ELSE 0 END) AS guards_with_expiring_license
 FROM (
     SELECT
         g.id,
         SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count,
         SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license,
         SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license
     FROM guards g
     CROSS JOIN requirement_types rt
     LEFT JOIN guard_requirements gr
         ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
     WHERE rt.is_required = 1
     GROUP BY g.id
 ) t
 ";
         $jubecerSummaryRow = $pdo->query($summarySql)->fetch(PDO::FETCH_ASSOC);
         if (is_array($jubecerSummaryRow)) {
             $jubecerSummary = array_merge($jubecerSummary, $jubecerSummaryRow);
         }
 
         $alertsSql =
             "SELECT\n" .
             "  g.id AS guard_id,\n" .
             "  g.full_name,\n" .
             "  g.guard_no,\n" .
             "  g.agency,\n" .
             "  gr.expiry_date,\n" .
             "  DATEDIFF(gr.expiry_date, CURDATE()) AS days_until_expiry,\n" .
             "  CASE\n" .
             "    WHEN gr.expiry_date < CURDATE() THEN 'Expired'\n" .
             "    WHEN gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 'Expiring'\n" .
             "    ELSE 'Valid'\n" .
             "  END AS alert_status\n" .
             "FROM guards g\n" .
             "JOIN requirement_types rt ON rt.code = 'SECURITY_LICENSE'\n" .
             "JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n" .
             "WHERE gr.expiry_date IS NOT NULL\n" .
             "  AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)\n" .
             "ORDER BY (gr.expiry_date < CURDATE()) DESC, gr.expiry_date ASC, g.full_name ASC\n" .
             "LIMIT 20";
         $jubecerLicenseAlerts = $pdo->query($alertsSql)->fetchAll();
 
         $listSql = "
 SELECT
     g.id,
     g.guard_no,
     g.last_name,
     g.first_name,
     g.middle_name,
     g.suffix,
     g.birthdate,
     g.age,
     g.agency,
     g.contact_no,
     SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count,
     SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license,
     SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license,
     MAX(CASE WHEN rt.code = 'SECURITY_LICENSE' THEN gr.expiry_date ELSE NULL END) AS license_expiry_date
 FROM guards g
 CROSS JOIN requirement_types rt
 LEFT JOIN guard_requirements gr
     ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
 WHERE rt.is_required = 1
 GROUP BY g.id
 ORDER BY g.last_name ASC, g.first_name ASC, g.id ASC
 ";
         $rows = $pdo->query($listSql)->fetchAll();
 
         $missingSql = "
 SELECT
     g.id AS guard_id,
     rt.name AS requirement_name
 FROM guards g
 JOIN requirement_types rt ON rt.is_required = 1
 LEFT JOIN guard_requirements gr
     ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
 WHERE gr.id IS NULL
 ORDER BY g.id ASC, rt.id ASC
 ";
         $missingRows = $pdo->query($missingSql)->fetchAll();
         $missingByGuard = [];
         foreach ($missingRows as $mr) {
             $gid = (int)($mr['guard_id'] ?? 0);
             $nm = (string)($mr['requirement_name'] ?? '');
             if ($gid > 0 && $nm !== '') {
                 $missingByGuard[$gid][] = $nm;
             }
         }
 
         foreach ($rows as $r) {
             $gid = (int)($r['id'] ?? 0);
             $missingReqs = $missingByGuard[$gid] ?? [];
             $missingCount = (int)($r['missing_count'] ?? 0);
 
             $status = 'VALID';
             if ((int)($r['expired_license'] ?? 0) > 0) {
                 $status = 'EXPIRED';
             } elseif ((int)($r['expiring_license'] ?? 0) > 0) {
                 $status = 'EXPIRING';
             } elseif ($missingCount > 0) {
                 $status = 'MISSING';
             }
 
             $jubecerGuards[] = [
                 'id' => $gid,
                 'no' => (string)($r['guard_no'] ?? ''),
                 'last' => (string)($r['last_name'] ?? ''),
                 'first' => (string)($r['first_name'] ?? ''),
                 'mid' => (string)($r['middle_name'] ?? ''),
                 'suffix' => (string)($r['suffix'] ?? ''),
                 'agency' => (string)($r['agency'] ?? ''),
                 'contact' => (string)($r['contact_no'] ?? ''),
                 'bday' => (string)($r['birthdate'] ?? ''),
                 'age' => (int)($r['age'] ?? 0),
                 'status' => $status,
                 'expDate' => (string)($r['license_expiry_date'] ?? ''),
                 'missing' => $missingCount,
                 'missingReqs' => $missingReqs,
             ];
         }
     } catch (Throwable $e) {
         error_log('home.php Jubecer dashboard load failed: ' . $e->getMessage());
         $jubecerSummary = [
             'total_guards' => 0,
             'guards_with_missing' => 0,
             'guards_with_expired_license' => 0,
             'guards_with_expiring_license' => 0,
         ];
         $jubecerLicenseAlerts = [];
         $jubecerGuards = [];
         $requiredRequirementTypes = [];
     }
 }

 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api'])) {
     header('Content-Type: application/json; charset=utf-8');

     if ($company !== 'jubecer') {
         http_response_code(403);
         echo json_encode(['ok' => false, 'error' => 'Unsupported company.'], JSON_UNESCAPED_UNICODE);
         exit;
     }

     require_once __DIR__ . '/../includes/db.php';
     $pdo = db();
     $api = (string)($_POST['api'] ?? '');

     try {
         if ($api === 'get_guard_requirements') {
             $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
             if ($guardId <= 0) {
                 throw new RuntimeException('Invalid guard.');
             }

             $types = $pdo->query('SELECT id, code, name, expires FROM requirement_types WHERE is_required = 1 ORDER BY id')->fetchAll();
             $reqs = $pdo->prepare(
                 'SELECT requirement_type_id, document_no, issued_date, expiry_date, document_path, document_original_name '
                 . 'FROM guard_requirements WHERE guard_id = :guard_id'
             );
             $reqs->execute(['guard_id' => $guardId]);
             $rows = $reqs->fetchAll();
             $byType = [];
             foreach ($rows as $r) {
                 $tid = (int)($r['requirement_type_id'] ?? 0);
                 if ($tid > 0) {
                     $byType[$tid] = [
                         'document_no' => (string)($r['document_no'] ?? ''),
                         'issued_date' => (string)($r['issued_date'] ?? ''),
                         'expiry_date' => (string)($r['expiry_date'] ?? ''),
                         'document_path' => (string)($r['document_path'] ?? ''),
                         'document_original_name' => (string)($r['document_original_name'] ?? ''),
                     ];
                 }
             }

             $out = [];
             foreach ($types as $t) {
                 $tid = (int)($t['id'] ?? 0);
                 $out[] = [
                     'id' => $tid,
                     'code' => (string)($t['code'] ?? ''),
                     'name' => (string)($t['name'] ?? ''),
                     'expires' => (int)($t['expires'] ?? 0) === 1,
                     'value' => $byType[$tid] ?? null,
                 ];
             }

             echo json_encode(['ok' => true, 'requirements' => $out], JSON_UNESCAPED_UNICODE);
             exit;
         }

         if ($api === 'update_guard') {
             $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
             if ($guardId <= 0) {
                 throw new RuntimeException('Invalid guard.');
             }

             $last = trim((string)($_POST['last_name'] ?? ''));
             $first = trim((string)($_POST['first_name'] ?? ''));
             $mid = trim((string)($_POST['middle_name'] ?? ''));
             $suffix = trim((string)($_POST['suffix'] ?? ''));
             $agency = trim((string)($_POST['agency'] ?? ''));
             $contact = trim((string)($_POST['contact_no'] ?? ''));
             $birthdateRaw = trim((string)($_POST['birthdate'] ?? ''));
             $birthdate = $birthdateRaw !== '' ? $birthdateRaw : null;
             $ageRaw = trim((string)($_POST['age'] ?? ''));
             $age = null;
             if ($ageRaw !== '' && ctype_digit($ageRaw)) {
                 $age = (int)$ageRaw;
             }

             if ($last === '' || $first === '') {
                 throw new RuntimeException('Last Name and First Name are required.');
             }

             $parts = [];
             if ($last !== '') {
                 $parts[] = $last . ',';
             }
             if ($first !== '') {
                 $parts[] = $first;
             }
             if ($mid !== '') {
                 $parts[] = $mid;
             }
             if ($suffix !== '') {
                 $parts[] = $suffix;
             }
             $full = trim(implode(' ', $parts));

             $stmt = $pdo->prepare(
                 'UPDATE guards '
                 . 'SET last_name = :last_name, first_name = :first_name, middle_name = :middle_name, suffix = :suffix, '
                 . 'birthdate = :birthdate, age = :age, agency = :agency, full_name = :full_name, contact_no = :contact_no '
                 . 'WHERE id = :id'
             );
             $stmt->execute([
                 'last_name' => $last,
                 'first_name' => $first,
                 'middle_name' => $mid !== '' ? $mid : null,
                 'suffix' => $suffix !== '' ? $suffix : null,
                 'birthdate' => $birthdate,
                 'age' => $age,
                 'agency' => $agency !== '' ? $agency : null,
                 'full_name' => $full,
                 'contact_no' => $contact !== '' ? $contact : null,
                 'id' => $guardId,
             ]);

             echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
             exit;
         }

         if ($api === 'save_requirement') {
             $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
             $typeId = isset($_POST['requirement_type_id']) ? (int)$_POST['requirement_type_id'] : 0;
             if ($guardId <= 0 || $typeId <= 0) {
                 throw new RuntimeException('Invalid request.');
             }

             $docNo = trim((string)($_POST['document_no'] ?? ''));
             $issuedRaw = trim((string)($_POST['issued_date'] ?? ''));
             $expiryRaw = trim((string)($_POST['expiry_date'] ?? ''));
             $issued = $issuedRaw !== '' ? $issuedRaw : null;
             $expiry = $expiryRaw !== '' ? $expiryRaw : null;

             $typeStmt = $pdo->prepare('SELECT code, expires FROM requirement_types WHERE id = :id LIMIT 1');
             $typeStmt->execute(['id' => $typeId]);
             $t = $typeStmt->fetch();
             if (!$t) {
                 throw new RuntimeException('Requirement type not found.');
             }
             $code = (string)($t['code'] ?? '');
             if ($code !== 'SECURITY_LICENSE') {
                 $issued = null;
                 $expiry = null;
             }
             if ($code === 'SECURITY_LICENSE' && ($expiry === null || $expiry === '')) {
                 throw new RuntimeException('Expiry date is required for Security License.');
             }

             $docPath = null;
             $docOrig = null;
             $docMime = null;
             $docSize = null;

             $file = $_FILES['document_file'] ?? null;
             $hasNewUpload = is_array($file) && isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_NO_FILE;
             if ($hasNewUpload) {
                 if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                     throw new RuntimeException('Upload failed. Please try again.');
                 }
                 $maxBytes = 8 * 1024 * 1024;
                 $size = isset($file['size']) ? (int)$file['size'] : 0;
                 if ($size <= 0 || $size > $maxBytes) {
                     throw new RuntimeException('File must be less than 8MB.');
                 }
                 $originalName = (string)($file['name'] ?? 'document');
                 $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                 $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                 if ($ext !== '' && !in_array($ext, $allowed, true)) {
                     throw new RuntimeException('Allowed file types: PDF, JPG, JPEG, PNG.');
                 }
                 $uploadDir = __DIR__ . '/../uploads/guard_requirements';
                 if (!is_dir($uploadDir)) {
                     @mkdir($uploadDir, 0775, true);
                 }
                 if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                     throw new RuntimeException('Upload folder is not writable.');
                 }
                 $safeExt = $ext !== '' ? ('.' . $ext) : '';
                 $storedName = 'g' . $guardId . '_t' . $typeId . '_' . bin2hex(random_bytes(10)) . $safeExt;
                 $targetPath = $uploadDir . '/' . $storedName;
                 if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                     throw new RuntimeException('Unable to save uploaded file.');
                 }
                 $docPath = 'uploads/guard_requirements/' . $storedName;
                 $docOrig = $originalName;
                 $docMime = (string)($file['type'] ?? '');
                 $docSize = $size;
             }

             $existing = $pdo->prepare('SELECT id, document_path FROM guard_requirements WHERE guard_id = :guard_id AND requirement_type_id = :type_id LIMIT 1');
             $existing->execute(['guard_id' => $guardId, 'type_id' => $typeId]);
             $ex = $existing->fetch();
             $existingPath = $ex ? (string)($ex['document_path'] ?? '') : '';
             if ($existingPath === '' && !$hasNewUpload) {
                 throw new RuntimeException('Please upload the document file.');
             }

             $stmt = $pdo->prepare(
                 'INSERT INTO guard_requirements (
                     guard_id, requirement_type_id, document_no, issued_date, expiry_date,
                     document_path, document_original_name, document_mime, document_size
                  ) VALUES (
                     :guard_id, :type_id, :document_no, :issued_date, :expiry_date,
                     :document_path, :document_original_name, :document_mime, :document_size
                  )
                  ON DUPLICATE KEY UPDATE
                     document_no = VALUES(document_no),
                     issued_date = VALUES(issued_date),
                     expiry_date = VALUES(expiry_date),
                     document_path = IFNULL(VALUES(document_path), document_path),
                     document_original_name = IFNULL(VALUES(document_original_name), document_original_name),
                     document_mime = IFNULL(VALUES(document_mime), document_mime),
                     document_size = IFNULL(VALUES(document_size), document_size)'
             );
             $stmt->execute([
                 'guard_id' => $guardId,
                 'type_id' => $typeId,
                 'document_no' => $docNo !== '' ? $docNo : null,
                 'issued_date' => $issued,
                 'expiry_date' => $expiry,
                 'document_path' => $docPath,
                 'document_original_name' => $docOrig,
                 'document_mime' => $docMime,
                 'document_size' => $docSize,
             ]);

             echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
             exit;
         }

         throw new RuntimeException('Unknown API.');
     } catch (Throwable $e) {
         http_response_code(400);
         echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
         exit;
     }
 }
 
 $pageData = [
     'company' => $company,
     'companyLabel' => $companyLabel,
     'isBrainMaster' => $isBrainMaster,
     'userName' => $userName,
     'userInitials' => $userInitials,
     'userRole' => $role,
     'requirements' => $requiredRequirements,
     'requirementTypes' => $requiredRequirementTypes,
     'summary' => [
         'total_guards' => (int)($jubecerSummary['total_guards'] ?? 0),
         'guards_with_missing' => (int)($jubecerSummary['guards_with_missing'] ?? 0),
         'guards_with_expiring_license' => (int)($jubecerSummary['guards_with_expiring_license'] ?? 0),
         'guards_with_expired_license' => (int)($jubecerSummary['guards_with_expired_license'] ?? 0),
     ],
     'alerts' => $jubecerLicenseAlerts,
     'guards' => $jubecerGuards,
 ];
 ?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ERMS — Guard Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --white:#ffffff;
  --gray-25:#FCFCFD;
  --gray-50:#F9FAFB;
  --gray-100:#F2F4F7;
  --gray-200:#E4E7EC;
  --gray-300:#D0D5DD;
  --gray-400:#98A2B3;
  --gray-500:#667085;
  --gray-600:#475467;
  --gray-700:#344054;
  --gray-800:#1D2939;
  --gray-900:#101828;
  --navy-50:#EEF4FF;
  --navy-100:#E0EAFF;
  --navy-200:#C7D7FD;
  --navy-500:#6172F3;
  --navy-600:#444CE7;
  --navy-700:#3538CD;
  --navy-800:#2D31A6;
  --navy-900:#1F2370;
  --success-50:#ECFDF3;
  --success-100:#DCFAE6;
  --success-200:#ABEFC6;
  --success-500:#12B76A;
  --success-600:#039855;
  --success-700:#027A48;
  --warning-50:#FFFAEB;
  --warning-100:#FEF0C7;
  --warning-500:#F79009;
  --warning-600:#DC6803;
  --warning-700:#B54708;
  --error-50:#FEF3F2;
  --error-100:#FEE4E2;
  --error-200:#FECDCA;
  --error-500:#F04438;
  --error-600:#D92D20;
  --error-700:#B42318;
  --orange-50:#FFF6ED;
  --orange-100:#FFEAD5;
  --orange-500:#EF6820;
  --orange-600:#E04F16;
  --orange-700:#B93815;
  --sx:0 1px 2px rgba(16,24,40,0.05);
  --sm:0 1px 3px rgba(16,24,40,0.1),0 1px 2px rgba(16,24,40,0.06);
  --md:0 4px 8px -2px rgba(16,24,40,0.1),0 2px 4px -2px rgba(16,24,40,0.06);
  --lg:0 12px 16px -4px rgba(16,24,40,0.08),0 4px 6px -2px rgba(16,24,40,0.03);
  --xl:0 20px 24px -4px rgba(16,24,40,0.08),0 8px 8px -4px rgba(16,24,40,0.03);
  --r:8px;--rm:10px;--rl:12px;--rxl:16px;
  --f:'Plus Jakarta Sans',sans-serif;
  --mono:'JetBrains Mono',monospace;
}
html,body,#root{height:100%;background:var(--gray-50);}
body{font-family:var(--f);font-size:14px;color:var(--gray-700);-webkit-font-smoothing:antialiased;}
button,input,select{font-family:var(--f);}

.shell{display:flex;height:100vh;overflow:hidden;}

/* SIDEBAR */
.sb{
  width:260px;flex-shrink:0;background:var(--gray-900);
  display:flex;flex-direction:column;overflow:hidden;
}
.sb-top{padding:20px 16px 16px;border-bottom:1px solid rgba(255,255,255,0.06);}
.sb-brand{display:flex;align-items:center;gap:12px;}
.sb-logo{
  width:36px;height:36px;border-radius:10px;
  background:var(--navy-700);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:15px;color:#fff;flex-shrink:0;
  box-shadow:0 0 0 1px rgba(255,255,255,0.12),0 2px 8px rgba(53,56,205,0.5);
}
.sb-name{font-size:15px;font-weight:700;color:#fff;letter-spacing:-0.3px;}
.sb-tagline{font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;font-weight:500;}

.sb-nav{padding:10px 10px 0;flex:1;}
.sb-nav-label{
  font-size:10px;font-weight:600;letter-spacing:1px;
  color:rgba(255,255,255,0.2);text-transform:uppercase;
  padding:8px 8px 5px;
}
.sb-item{
  display:flex;align-items:center;gap:10px;
  padding:9px 10px;border-radius:var(--r);
  color:rgba(255,255,255,0.45);font-size:13px;font-weight:500;
  cursor:pointer;transition:all .15s;border:none;background:none;
  width:100%;text-align:left;margin-bottom:2px;position:relative;
}
.sb-item:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.8);}
.sb-item.on{background:rgba(97,114,243,0.15);color:#fff;font-weight:600;}
.sb-item.on::before{
  content:'';position:absolute;left:0;top:8px;bottom:8px;
  width:3px;border-radius:0 3px 3px 0;background:var(--navy-500);
}
.sb-item svg{width:15px;height:15px;flex-shrink:0;}
.sb-pill{
  margin-left:auto;background:rgba(240,68,56,0.2);color:#FDA29B;
  font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;
  font-family:var(--mono);
}

.sb-foot{padding:14px 14px 18px;border-top:1px solid rgba(255,255,255,0.06);margin-top:auto;}
.sb-user{display:flex;align-items:center;gap:10px;}
.sb-av{
  width:32px;height:32px;border-radius:50%;
  background:linear-gradient(135deg,var(--navy-600) 0%,#7C3AED 100%);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;color:#fff;flex-shrink:0;
}
.sb-uname{font-size:13px;font-weight:600;color:rgba(255,255,255,0.85);}
.sb-urole{font-size:11px;color:rgba(255,255,255,0.3);margin-top:1px;}

.sb-actions{display:flex;gap:8px;margin-top:12px;}
.sb-act{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:8px 10px;border-radius:var(--r);
  border:1px solid rgba(255,255,255,0.12);
  background:rgba(255,255,255,0.06);
  color:rgba(255,255,255,0.8);
  font-size:12px;font-weight:600;
  transition:all .15s;
  text-decoration:none;
}
.sb-act:hover{background:rgba(255,255,255,0.1);color:#fff;}

.sb-dd{position:relative;}
.sb-trigger{
  width:100%;
  display:flex;align-items:center;gap:10px;
  padding:10px 10px;
  border-radius:var(--r);
  border:1px solid rgba(255,255,255,0.08);
  background:rgba(255,255,255,0.04);
  color:rgba(255,255,255,0.85);
  cursor:pointer;
  transition:background .15s,border-color .15s;
}
.sb-trigger:hover{background:rgba(255,255,255,0.07);border-color:rgba(255,255,255,0.12);}
.sb-trigger:focus{outline:none;box-shadow:0 0 0 3px rgba(97,114,243,0.25);}
.sb-chev{margin-left:auto;color:rgba(255,255,255,0.45);display:flex;align-items:center;justify-content:center;}
.sb-chev svg{width:16px;height:16px;}
.sb-menu{
  position:absolute;
  left:0;
  right:0;
  bottom:calc(100% + 10px);
  background:rgba(16,24,40,0.98);
  border:1px solid rgba(255,255,255,0.10);
  border-radius:12px;
  box-shadow:0 16px 32px rgba(0,0,0,0.35);
  overflow:hidden;
  z-index:20;
}
.sb-mi{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;
  color:rgba(255,255,255,0.85);
  font-size:13px;
  font-weight:600;
  text-decoration:none;
  border-top:1px solid rgba(255,255,255,0.06);
}
.sb-mi:first-child{border-top:none;}
.sb-mi:hover{background:rgba(255,255,255,0.06);color:#fff;}
.sb-mi svg{width:16px;height:16px;opacity:0.85;}
.sb-mi.d{color:#FDA29B;}
.sb-mi.d:hover{background:rgba(240,68,56,0.12);color:#FEB2B2;}

/* MAIN */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;}

/* TOPBAR */
.topbar{
  height:60px;background:var(--white);
  border-bottom:1px solid var(--gray-200);
  padding:0 28px;
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;box-shadow:var(--sx);
}
.tb-pg{font-size:16px;font-weight:700;color:var(--gray-900);letter-spacing:-0.3px;}
.tb-crumb{font-size:12px;color:var(--gray-400);margin-top:2px;}
.tb-r{display:flex;align-items:center;gap:10px;}
.tb-clock{
  font-family:var(--mono);font-size:12px;color:var(--gray-500);
  background:var(--gray-50);border:1px solid var(--gray-200);
  padding:5px 12px;border-radius:var(--r);
}
.tb-icobtn{
  width:34px;height:34px;border-radius:var(--r);
  border:1px solid var(--gray-200);background:var(--white);
  color:var(--gray-500);display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;position:relative;
}
.tb-icobtn:hover{background:var(--gray-50);color:var(--gray-700);}
.tb-icobtn svg{width:15px;height:15px;}
.tb-dot{
  position:absolute;top:7px;right:7px;
  width:6px;height:6px;border-radius:50%;
  background:var(--error-500);border:1.5px solid var(--white);
}

/* CONTENT */
.content{flex:1;overflow-y:auto;padding:26px 28px;background:var(--gray-50);}

/* PAGE HEADER */
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;}
.ph-title{font-size:22px;font-weight:800;color:var(--gray-900);letter-spacing:-0.5px;}
.ph-sub{font-size:13px;color:var(--gray-400);margin-top:4px;}
.ph-actions{display:flex;gap:10px;margin-top:4px;}

/* STAT CARDS */
.sg{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.sc{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);padding:20px 22px;
  box-shadow:var(--sx);transition:box-shadow .2s,border-color .2s;
  cursor:default;
}
.sc:hover{box-shadow:var(--md);border-color:var(--gray-300);}
.sc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
.sc-ico{
  width:42px;height:42px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
}
.sc-ico svg{width:18px;height:18px;}
.sc-ico.b{background:var(--navy-50);}
.sc-ico.b svg{color:var(--navy-700);}
.sc-ico.o{background:var(--orange-50);}
.sc-ico.o svg{color:var(--orange-600);}
.sc-ico.y{background:var(--warning-50);}
.sc-ico.y svg{color:var(--warning-600);}
.sc-ico.r{background:var(--error-50);}
.sc-ico.r svg{color:var(--error-600);}
.sc-label{font-size:13px;font-weight:500;color:var(--gray-500);margin-bottom:4px;}
.sc-val{font-size:32px;font-weight:800;color:var(--gray-900);letter-spacing:-1.5px;line-height:1;}
.sc-bar{height:3px;border-radius:2px;margin-top:14px;background:var(--gray-100);}
.sc-bar-fill{height:100%;border-radius:2px;}
.sc-bar-fill.b{background:var(--navy-600);}
.sc-bar-fill.o{background:var(--orange-500);}
.sc-bar-fill.y{background:var(--warning-500);}
.sc-bar-fill.r{background:var(--error-500);}
.sc-foot{font-size:11px;color:var(--gray-400);margin-top:8px;}

/* SECTION HEADER */
.shd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.shd-t{font-size:15px;font-weight:700;color:var(--gray-900);}
.shd-s{font-size:12px;color:var(--gray-400);margin-top:2px;}

/* CARD */
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--rxl);box-shadow:var(--sx);overflow:hidden;}

/* QUICK ACTIONS */
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px;}
.qa-item{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);padding:18px 20px;
  cursor:pointer;transition:all .15s;box-shadow:var(--sx);
  display:flex;align-items:center;gap:14px;
}
.qa-item:hover{box-shadow:var(--md);border-color:var(--gray-300);transform:translateY(-1px);}
.qa-ico{
  width:42px;height:42px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.qa-ico svg{width:18px;height:18px;}
.qa-ico.b{background:var(--navy-50);}
.qa-ico.b svg{color:var(--navy-700);}
.qa-ico.g{background:var(--success-50);}
.qa-ico.g svg{color:var(--success-600);}
.qa-ico.y{background:var(--warning-50);}
.qa-ico.y svg{color:var(--warning-600);}
.qa-lbl{font-size:13px;font-weight:700;color:var(--gray-800);}
.qa-sub{font-size:12px;color:var(--gray-400);margin-top:2px;}

/* ALERT LIST */
.al-row{
  display:flex;align-items:center;gap:14px;
  padding:14px 20px;border-bottom:1px solid var(--gray-100);
  transition:background .12s;cursor:pointer;
}
.al-row:last-child{border-bottom:none;}
.al-av{
  width:36px;height:36px;border-radius:50%;
  background:var(--navy-50);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;color:var(--navy-700);flex-shrink:0;
}
.al-name{font-size:13px;font-weight:600;color:var(--gray-800);}
.al-meta{font-size:11px;color:var(--gray-400);margin-top:2px;font-family:var(--mono);}
.al-info{flex:1;min-width:0;}

/* BADGES */
.badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 9px;border-radius:20px;
  font-size:11px;font-weight:600;white-space:nowrap;
  flex-shrink:0;
}
.badge::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.bv{background:var(--success-50);color:var(--success-700);}
.bv::before{background:var(--success-500);}
.bx{background:var(--warning-50);color:var(--warning-700);}
.bx::before{background:var(--warning-500);}
.be{background:var(--error-50);color:var(--error-700);}
.be::before{background:var(--error-500);}
.bm{background:var(--orange-50);color:var(--orange-700);}
.bm::before{background:var(--orange-500);}

/* BUTTONS */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:9px 16px;border-radius:var(--r);
  font-size:13px;font-weight:600;
  transition:all .15s;border:none;white-space:nowrap;cursor:pointer;
}
.btn svg{width:14px;height:14px;}
.btn-p{
  background:var(--navy-700);color:#fff;
  box-shadow:0 1px 3px rgba(53,56,205,0.3),var(--sx);
}
.btn-p:hover{background:var(--navy-800);}
.btn-s{
  background:var(--white);color:var(--gray-700);
  border:1px solid var(--gray-300);box-shadow:var(--sx);
}
.btn-s:hover{background:var(--gray-50);border-color:var(--gray-400);}
.btn-g{
  background:transparent;color:var(--gray-600);
  border:1px solid transparent;
}
.btn-g:hover{background:var(--gray-100);color:var(--gray-800);}
.btn-op{
  background:var(--navy-50);color:var(--navy-700);
  border:1px solid var(--navy-200);
}
.btn-op:hover{background:var(--navy-100);}
.sm{padding:6px 12px;font-size:12px;}

/* TABLE */
.tc{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--rxl);overflow:hidden;box-shadow:var(--sx);}
.tctrl{
  padding:14px 20px;border-bottom:1px solid var(--gray-200);
  display:flex;gap:10px;align-items:center;flex-wrap:wrap;
  background:var(--white);
}
.sw{flex:1;min-width:220px;position:relative;}
.sw svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--gray-400);pointer-events:none;}
.si{
  width:100%;padding:8px 12px 8px 34px;
  background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);color:var(--gray-700);font-size:13px;
  outline:none;transition:all .15s;
}
.si::placeholder{color:var(--gray-400);}
.si:focus{background:var(--white);border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}
.ts{
  padding:8px 12px;background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);color:var(--gray-600);font-size:13px;
  outline:none;cursor:pointer;transition:all .15s;font-family:var(--f);
}
.ts:focus{background:var(--white);border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}

table{width:100%;border-collapse:collapse;}
thead{background:var(--gray-50);border-bottom:1px solid var(--gray-200);}
th{
  padding:10px 16px;text-align:left;
  font-size:11px;font-weight:600;letter-spacing:0.4px;
  text-transform:uppercase;color:var(--gray-500);white-space:nowrap;
}
td{
  padding:13px 16px;border-bottom:1px solid var(--gray-100);
  font-size:13px;color:var(--gray-600);vertical-align:middle;
}
tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:var(--gray-25);}
.gno{font-family:var(--mono);font-size:11px;color:var(--gray-400);}
.gnm{font-weight:600;color:var(--navy-700);cursor:pointer;transition:color .12s;}
.gnm:hover{color:var(--navy-600);text-decoration:underline;}
.mc0{font-family:var(--mono);font-size:12px;color:var(--success-600);font-weight:600;}
.mcn{font-family:var(--mono);font-size:12px;color:var(--orange-600);font-weight:600;}

/* PAGINATION */
.pgn{
  padding:12px 20px;display:flex;align-items:center;justify-content:space-between;
  border-top:1px solid var(--gray-200);background:var(--white);
}
.pgi{font-size:12px;color:var(--gray-500);}
.pgb{display:flex;gap:4px;}
.pb{
  min-width:30px;height:30px;border-radius:var(--r);
  background:var(--white);border:1px solid var(--gray-200);
  color:var(--gray-600);font-size:12px;font-weight:500;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;padding:0 6px;
}
.pb:hover{background:var(--gray-50);border-color:var(--gray-300);}
.pb.on{background:var(--navy-700);color:#fff;border-color:var(--navy-700);}
.pb:disabled{opacity:0.35;cursor:default;}

/* MODAL */
.overlay{
  position:fixed;inset:0;background:rgba(16,24,40,0.65);
  display:flex;align-items:center;justify-content:center;
  z-index:100;backdrop-filter:blur(4px);
  animation:fi .15s ease;
}
@keyframes fi{from{opacity:0}to{opacity:1}}
.modal{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);width:540px;max-width:95vw;
  max-height:90vh;overflow-y:auto;
  box-shadow:var(--xl);
  animation:si .18s ease;
}
@keyframes si{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.mhd{
  padding:22px 24px 18px;border-bottom:1px solid var(--gray-200);
  display:flex;align-items:flex-start;justify-content:space-between;
}
.mt{font-size:17px;font-weight:700;color:var(--gray-900);}
.ms{font-size:12px;color:var(--gray-500);margin-top:3px;}
.mc{
  width:30px;height:30px;border-radius:var(--r);
  background:var(--gray-50);border:1px solid var(--gray-200);
  color:var(--gray-500);display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;flex-shrink:0;
}
.mc:hover{background:var(--gray-100);border-color:var(--gray-300);}
.mc svg{width:14px;height:14px;}
.mb{padding:20px 24px 24px;}

/* FORM */
.fg{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px;}
.fg2{grid-template-columns:repeat(2,1fr);}
.fgrp{display:flex;flex-direction:column;gap:5px;}
.fl{font-size:12px;font-weight:600;color:var(--gray-700);}
.fl span{color:var(--error-500);}
.fi{
  padding:9px 13px;background:var(--white);
  border:1px solid var(--gray-300);border-radius:var(--r);
  color:var(--gray-800);font-size:13px;font-family:var(--f);
  outline:none;transition:all .15s;width:100%;
}
.fi::placeholder{color:var(--gray-400);}
.fi:focus{border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}
.fi:read-only{background:var(--gray-50);color:var(--gray-400);}
.fac{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);}

/* GUARD PROFILE CARD */
.gpc{
  background:linear-gradient(135deg,var(--navy-800) 0%,var(--navy-700) 100%);
  border-radius:var(--rl);padding:18px 20px;margin-bottom:20px;
  display:flex;align-items:center;gap:14px;
}
.gpa{
  width:46px;height:46px;border-radius:50%;
  background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:700;color:#fff;flex-shrink:0;
}
.gpn{font-size:16px;font-weight:700;color:#fff;}
.gpm{font-size:12px;color:rgba(255,255,255,0.5);margin-top:3px;font-family:var(--mono);}

/* DETAIL */
.ds{margin-bottom:22px;}
.ds:last-child{margin-bottom:0;}
.dsh{
  font-size:11px;font-weight:700;letter-spacing:0.8px;
  text-transform:uppercase;color:var(--navy-600);
  margin-bottom:12px;padding-bottom:8px;
  border-bottom:2px solid var(--navy-50);
}
.dg{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.di{
  background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);padding:10px 14px;
}
.dk{font-size:10px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;}
.dv{font-size:13px;font-weight:600;color:var(--gray-800);}
.ri{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);margin-bottom:6px;
}
.ri:last-child{margin-bottom:0;}
.ri svg{width:16px;height:16px;}
.rn{font-size:13px;font-weight:500;color:var(--gray-700);}
.rok{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--success-600);}
.rok svg{width:13px;height:13px;}
.rno{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--error-600);}
.rno svg{width:13px;height:13px;}

/* TOAST */
.toast{
  position:fixed;bottom:24px;right:24px;z-index:200;
  background:var(--gray-900);border:1px solid rgba(255,255,255,0.1);
  border-radius:var(--rm);padding:12px 16px;
  display:flex;align-items:center;gap:10px;
  box-shadow:var(--xl);animation:si .2s ease;
}
.tico{
  width:22px;height:22px;border-radius:50%;
  background:var(--success-500);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.tico svg{width:11px;height:11px;color:#fff;}
.ttxt{font-size:13px;font-weight:500;color:#fff;}

/* EMPTY */
.empty{padding:56px;text-align:center;}
.ei{
  width:46px;height:46px;border-radius:12px;background:var(--gray-100);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;
}
.ei svg{width:20px;height:20px;color:var(--gray-400);}
.et{font-size:14px;font-weight:600;color:var(--gray-600);margin-bottom:4px;}
.es{font-size:13px;color:var(--gray-400);}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:10px;}
::-webkit-scrollbar-thumb:hover{background:var(--gray-300);}
@media(max-width:900px){.sg{grid-template-columns:repeat(2,1fr);}.qa{grid-template-columns:1fr 1fr;}.fg{grid-template-columns:repeat(2,1fr);}}
 </style>
 </head>
 <body>
 <script>
 window.__ERMS_DATA__ = <?php echo json_encode($pageData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
 </script>
 <div id="root"></div>
<script type="text/babel">
 const {useState,useEffect}=React;
 const DATA=(window.__ERMS_DATA__||{});
 const STS=['VALID','EXPIRING','EXPIRED','MISSING'];
 const RQS=Array.isArray(DATA.requirements)&&DATA.requirements.length?DATA.requirements:['SSS','PAG-IBIG','PhilHealth','License'];
 const GD=Array.isArray(DATA.guards)?DATA.guards:[];
 const AG=[...new Set(GD.map(g=>g.agency).filter(Boolean))];
 const SF=['','Jr.','Sr.','III'];

const Ic={
  dash:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>,
  guard:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
  bell:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>,
  search:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>,
  plus:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M12 5v14M5 12h14"/></svg>,
  x:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>,
  check:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M20 6 9 17l-5-5"/></svg>,
  warn:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="m10.29 3.86-7 12A1 1 0 0 0 4 17h16a1 1 0 0 0 .86-1.5l-7-12a1 1 0 0 0-1.72 0zM12 9v4M12 17h.01"/></svg>,
  clock:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>,
  shield:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>,
  chD:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m6 9 6 6 6-6"/></svg>,
  chU:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m18 15-6-6-6 6"/></svg>,
  chL:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m15 18-6-6 6-6"/></svg>,
  chR:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m9 18 6-6-6-6"/></svg>,
};

function Badge({s}){
  const m={VALID:'badge bv',EXPIRING:'badge bx',EXPIRED:'badge be',MISSING:'badge bm'};
  return <span className={m[s]||'badge bm'}>{s}</span>;
}

function Clock(){
  const [t,setT]=useState(new Date());
  useEffect(()=>{const id=setInterval(()=>setT(new Date()),1000);return()=>clearInterval(id);},[]);
  const D=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const Mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return <span className="tb-clock">{D[t.getDay()]}, {Mo[t.getMonth()]} {t.getDate()} &nbsp;·&nbsp; {t.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}</span>;
}

async function apiPost(fd){
  const r=await fetch('home.php',{method:'POST',body:fd,credentials:'same-origin'});
  const j=await r.json().catch(()=>({ok:false,error:'Invalid server response.'}));
  if(!r.ok||!j||j.ok!==true){throw new Error((j&&j.error)?j.error:'Request failed.');}
  return j;
}

function GuardModal({g,close,onUpdated}){
  if(!g)return null;
  const [edit,setEdit]=useState(false);
  const [saving,setSaving]=useState(false);
  const [toast,setToast]=useState('');
  const [reqs,setReqs]=useState(null);
  const [openReq,setOpenReq]=useState(null);
  const [reqSaving,setReqSaving]=useState(false);
  const [reqFile,setReqFile]=useState({});
  const [f,setF]=useState({
    last:g.last||'',first:g.first||'',mid:g.mid||'',suffix:g.suffix||'',
    bday:g.bday||'',age:String(g.age||''),agency:g.agency||'',contact:g.contact||''
  });
  const showT=m=>{setToast(m);setTimeout(()=>setToast(''),3000);};
  const name=`${g.last}, ${g.first} ${g.mid} ${g.suffix}`.trim();
  const init=(g.first[0]||'')+(g.last[0]||'');
  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));

  const loadReqs=async()=>{
    if(DATA.company!=='jubecer')return;
    const fd=new FormData();
    fd.append('api','get_guard_requirements');
    fd.append('guard_id',String(g.id));
    const j=await apiPost(fd);
    setReqs(Array.isArray(j.requirements)?j.requirements:[]);
  };

  useEffect(()=>{
    setEdit(false);
    setReqs(null);
    setOpenReq(null);
    setReqFile({});
    setF({
      last:g.last||'',first:g.first||'',mid:g.mid||'',suffix:g.suffix||'',
      bday:g.bday||'',age:String(g.age||''),agency:g.agency||'',contact:g.contact||''
    });
    loadReqs().catch(()=>{});
  },[g.id]);

  const saveProfile=async()=>{
    if(DATA.company!=='jubecer'){showT('Editing is available for Jubecer only.');return;}
    if(!f.last.trim()||!f.first.trim()){showT('Last Name and First Name are required.');return;}
    setSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','update_guard');
      fd.append('guard_id',String(g.id));
      fd.append('last_name',f.last);
      fd.append('first_name',f.first);
      fd.append('middle_name',f.mid);
      fd.append('suffix',f.suffix);
      fd.append('birthdate',f.bday);
      fd.append('age',String(f.age||''));
      fd.append('agency',f.agency);
      fd.append('contact_no',f.contact);
      await apiPost(fd);
      const ng={...g,last:f.last,first:f.first,mid:f.mid,suffix:f.suffix,bday:f.bday,age:parseInt(f.age)||0,agency:f.agency,contact:f.contact};
      if(typeof onUpdated==='function')onUpdated(ng);
      setEdit(false);
      showT('Profile saved.');
    }catch(e){
      showT(e.message||'Failed to save.');
    }finally{setSaving(false);}
  };

  const saveReq=async(rt,local)=>{
    if(DATA.company!=='jubecer')return;
    setReqSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','save_requirement');
      fd.append('guard_id',String(g.id));
      fd.append('requirement_type_id',String(rt.id));
      fd.append('document_no',local.document_no||'');
      fd.append('issued_date',local.issued_date||'');
      fd.append('expiry_date',local.expiry_date||'');
      const fup=reqFile[rt.id];
      if(fup)fd.append('document_file',fup);
      await apiPost(fd);
      setReqFile(p=>{const n={...p};delete n[rt.id];return n;});
      await loadReqs();
      showT('Requirement saved.');
    }catch(e){
      showT(e.message||'Failed to save requirement.');
    }finally{setReqSaving(false);}
  };

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&close()}>
      <div className="modal">
        <div className="mhd">
          <div><div className="mt">Guard Profile</div><div className="ms">View full details and compliance status</div></div>
          <button className="mc" onClick={close}><Ic.x/></button>
        </div>
        <div className="mb">
          {toast&&<div className="toast"><div className="tico"><Ic.check/></div><div className="ttxt">{toast}</div></div>}
          <div className="gpc">
            <div className="gpa">{init}</div>
            <div style={{flex:1}}>
              <div className="gpn">{name}</div>
              <div className="gpm">{g.no} · {g.agency}</div>
            </div>
            <Badge s={g.status}/>
          </div>
          <div className="ds">
            <div className="dsh">Personal Information</div>
            {!edit
              ?(
                <div className="dg">
                  <div className="di"><div className="dk">Full Name</div><div className="dv">{name}</div></div>
                  <div className="di"><div className="dk">Guard No.</div><div className="dv" style={{fontFamily:'var(--mono)',fontSize:12}}>{g.no}</div></div>
                  <div className="di"><div className="dk">Date of Birth</div><div className="dv">{g.bday}</div></div>
                  <div className="di"><div className="dk">Age</div><div className="dv">{g.age} years old</div></div>
                  <div className="di"><div className="dk">Contact</div><div className="dv">{g.contact}</div></div>
                  <div className="di"><div className="dk">Agency</div><div className="dv">{g.agency}</div></div>
                </div>
              ):(
                <>
                  <div className="fg">
                    <div className="fgrp"><label className="fl">Last Name <span>*</span></label><input className="fi" value={f.last} onChange={u('last')}/></div>
                    <div className="fgrp"><label className="fl">First Name <span>*</span></label><input className="fi" value={f.first} onChange={u('first')}/></div>
                    <div className="fgrp"><label className="fl">Middle Name</label><input className="fi" value={f.mid} onChange={u('mid')}/></div>
                  </div>
                  <div className="fg">
                    <div className="fgrp"><label className="fl">Suffix</label><select className="fi" value={f.suffix} onChange={u('suffix')}>{SF.map(s=><option key={s} value={s}>{s||'None'}</option>)}</select></div>
                    <div className="fgrp"><label className="fl">Birthdate</label><input className="fi" type="date" value={f.bday} onChange={u('bday')}/></div>
                    <div className="fgrp"><label className="fl">Age</label><input className="fi" value={f.age} onChange={u('age')} placeholder="e.g. 25"/></div>
                  </div>
                  <div className="fg fg2">
                    <div className="fgrp"><label className="fl">Agency</label><input className="fi" value={f.agency} onChange={u('agency')} placeholder="Agency"/></div>
                    <div className="fgrp"><label className="fl">Contact No.</label><input className="fi" value={f.contact} onChange={u('contact')} placeholder="09XXXXXXXXX"/></div>
                  </div>
                </>
              )}
          </div>
          <div className="ds">
            <div className="dsh">License Information</div>
            <div className="dg">
              <div className="di"><div className="dk">Status</div><div className="dv"><Badge s={g.status}/></div></div>
              <div className="di"><div className="dk">Expiry Date</div><div className="dv" style={{fontFamily:'var(--mono)',fontSize:12}}>{g.expDate||'—'}</div></div>
            </div>
          </div>
          <div className="ds">
            <div className="dsh">Requirements</div>
            {DATA.company!=='jubecer'
              ?RQS.map(r=>{const ok=!g.missingReqs.includes(r);return(
                <div className="ri" key={r}>
                  <span className="rn">{r}</span>
                  <span style={{display:'inline-flex',alignItems:'center',gap:8}}>
                    {ok?<span className="rok"><Ic.check/>Complete</span>:<span className="rno"><Ic.x/>Missing</span>}
                    <span style={{color:'var(--gray-400)',display:'inline-flex',alignItems:'center'}}><Ic.chD/></span>
                  </span>
                </div>
              );})
              :(
                (reqs===null)
                ?<div className="di"><div className="dk">Loading</div><div className="dv">Fetching requirements…</div></div>
                :reqs.map(rt=>{
                  const v=rt.value||{document_no:'',issued_date:'',expiry_date:'',document_path:'',document_original_name:''};
                  const missing=!v.document_path;
                  const isOpen=openReq===rt.id;
                  return(
                    <div key={rt.id} style={{marginBottom:10}}>
                      <div className="ri" onClick={()=>setOpenReq(p=>p===rt.id?null:rt.id)}>
                        <span className="rn" style={{fontWeight:600}}>{rt.name}</span>
                        <span style={{display:'inline-flex',alignItems:'center',gap:8}}>
                          {missing
                            ?<span className="rno"><Ic.x/>Missing</span>
                            :<span className="rok"><Ic.check/>Complete</span>}
                          <span style={{color:'var(--gray-400)',display:'inline-flex',alignItems:'center'}}>{isOpen?<Ic.chU/>:<Ic.chD/>}</span>
                        </span>
                      </div>
                      {isOpen&&(
                        <div className="di" style={{marginTop:8}}>
                          <div className="fg fg2">
                            <div className="fgrp"><label className="fl">Document No</label><input className="fi" value={v.document_no||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,document_no:e.target.value}}:x))}/></div>
                            <div className="fgrp"><label className="fl">File</label><input className="fi" type="file" onChange={e=>setReqFile(p=>({...p,[rt.id]:(e.target.files&&e.target.files[0])||null}))}/></div>
                          </div>
                          {rt.code==='SECURITY_LICENSE'&&(
                            <div className="fg fg2">
                              <div className="fgrp"><label className="fl">Issued Date</label><input className="fi" type="date" value={v.issued_date||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,issued_date:e.target.value}}:x))}/></div>
                              <div className="fgrp"><label className="fl">Expiry Date <span>*</span></label><input className="fi" type="date" value={v.expiry_date||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,expiry_date:e.target.value}}:x))}/></div>
                            </div>
                          )}
                          {v.document_path&&(
                            <div style={{marginTop:10,fontSize:12,color:'var(--gray-500)'}}>
                              Current: <a href={`../${v.document_path}`} target="_blank" rel="noreferrer" style={{color:'var(--navy-700)',textDecoration:'none',fontWeight:600}}>{v.document_original_name||'View file'}</a>
                            </div>
                          )}
                          <div className="fac">
                            <button className="btn btn-s sm" onClick={()=>setOpenReq(null)} disabled={reqSaving}>Close</button>
                            <button className="btn btn-p sm" onClick={()=>saveReq(rt,v)} disabled={reqSaving}>{reqSaving?'Saving…':'Save Requirement'}</button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })
              )}
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={close} disabled={saving||reqSaving}>Close</button>
            {DATA.company==='jubecer'&&(
              edit
                ?<button className="btn btn-p sm" onClick={saveProfile} disabled={saving}>{saving?'Saving…':'Save Profile'}</button>
                :<button className="btn btn-p sm" onClick={()=>setEdit(true)}>Edit Profile</button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function AddModal({close,save}){
  const [f,setF]=useState({last:'',first:'',mid:'',suffix:'',bday:'',age:'',agency:'',contact:''});
  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  const bd=e=>{const d=new Date(e.target.value);setF(p=>({...p,bday:e.target.value,age:isNaN(d)?'':new Date().getFullYear()-d.getFullYear()}));};
  const go=()=>{if(!f.last.trim()||!f.first.trim()){alert('Last Name and First Name are required.');return;}save(f);close();};
  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&close()}>
      <div className="modal">
        <div className="mhd">
          <div><div className="mt">Add New Guard</div><div className="ms">Complete all required fields to register</div></div>
          <button className="mc" onClick={close}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="fg">
            <div className="fgrp"><label className="fl">Last Name <span>*</span></label><input className="fi" value={f.last} onChange={u('last')} placeholder="Dela Cruz"/></div>
            <div className="fgrp"><label className="fl">First Name <span>*</span></label><input className="fi" value={f.first} onChange={u('first')} placeholder="Juan"/></div>
            <div className="fgrp"><label className="fl">Middle Name</label><input className="fi" value={f.mid} onChange={u('mid')} placeholder="Santos"/></div>
          </div>
          <div className="fg">
            <div className="fgrp"><label className="fl">Suffix</label><select className="fi" value={f.suffix} onChange={u('suffix')}>{SF.map(s=><option key={s} value={s}>{s||'None'}</option>)}</select></div>
            <div className="fgrp"><label className="fl">Birthdate</label><input className="fi" type="date" value={f.bday} onChange={bd}/></div>
            <div className="fgrp"><label className="fl">Age</label><input className="fi" value={f.age} readOnly placeholder="Auto"/></div>
          </div>
          <div className="fg fg2">
            <div className="fgrp"><label className="fl">Agency</label><select className="fi" value={f.agency} onChange={u('agency')}><option value="">Select agency…</option>{AG.map(a=><option key={a}>{a}</option>)}</select></div>
            <div className="fgrp"><label className="fl">Contact No.</label><input className="fi" value={f.contact} onChange={u('contact')} placeholder="09XXXXXXXXX"/></div>
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={close}>Cancel</button>
            <button className="btn btn-p sm" onClick={go}><Ic.plus/>Save Guard</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Dashboard({guards,onAdd,onGo,summary}){
  const tot=(summary&&typeof summary.total_guards==='number')?summary.total_guards:guards.length;
  const mis=(summary&&typeof summary.guards_with_missing==='number')?summary.guards_with_missing:guards.filter(g=>g.missing>0).length;
  const exp=(summary&&typeof summary.guards_with_expiring_license==='number')?summary.guards_with_expiring_license:guards.filter(g=>g.status==='EXPIRING').length;
  const ed=(summary&&typeof summary.guards_with_expired_license==='number')?summary.guards_with_expired_license:guards.filter(g=>g.status==='EXPIRED').length;
  const alerts=guards.filter(g=>g.status==='EXPIRED'||g.status==='MISSING').slice(0,7);
  const cards=[
    {l:'Total Guards',v:tot,i:'blue',icon:<Ic.guard/>,sub:'All registered records',pct:100},
    {l:'Missing Requirements',v:mis,i:'o',icon:<Ic.warn/>,sub:'Need document submission',pct:tot>0?Math.round(mis/tot*100):0},
    {l:'License Expiring',v:exp,i:'y',icon:<Ic.clock/>,sub:'Within 6 months',pct:tot>0?Math.round(exp/tot*100):0},
    {l:'License Expired',v:ed,i:'r',icon:<Ic.shield/>,sub:'Immediate action required',pct:tot>0?Math.round(ed/tot*100):0},
  ];
  return(
    <>
      <div className="sg">
        {cards.map(c=>(
          <div className="sc" key={c.l}>
            <div className="sc-top">
              <div>
                <div className="sc-label">{c.l}</div>
                <div className="sc-val">{c.v}</div>
              </div>
              <div className={`sc-ico ${c.i}`}>{c.icon}</div>
            </div>
            <div className="sc-bar"><div className={`sc-bar-fill ${c.i}`} style={{width:`${c.pct}%`}}></div></div>
            <div className="sc-foot">{c.sub}</div>
          </div>
        ))}
      </div>

      <div className="shd" style={{marginBottom:14}}><div className="shd-t">Quick Actions</div></div>
      <div className="qa" style={{marginBottom:28}}>
        <div className="qa-item" onClick={onAdd}>
          <div className="qa-ico b"><Ic.plus/></div>
          <div><div className="qa-lbl">Add Guard</div><div className="qa-sub">Register a new security guard</div></div>
        </div>
        <div className="qa-item" onClick={()=>onGo()}>
          <div className="qa-ico g"><Ic.guard/></div>
          <div><div className="qa-lbl">Manage Guards</div><div className="qa-sub">View all {tot} records</div></div>
        </div>
        <div className="qa-item" onClick={()=>onGo('EXPIRED')}>
          <div className="qa-ico y"><Ic.clock/></div>
          <div><div className="qa-lbl">Renewal Queue</div><div className="qa-sub">{ed+exp} licenses to renew</div></div>
        </div>
      </div>

      <div className="shd">
        <div><div className="shd-t">License Alerts</div><div className="shd-s">Guards requiring immediate attention</div></div>
        <button className="btn btn-g sm" onClick={()=>onGo()}>View all →</button>
      </div>
      <div className="card">
        {alerts.length===0
          ?<div className="empty"><div className="ei"><Ic.check/></div><div className="et">All Clear</div><div className="es">No alerts at this time</div></div>
          :alerts.map(g=>{
            const nm=`${g.last}, ${g.first} ${g.mid}`;
            const dt=g.status==='EXPIRED'?`Expired ${g.expDate}`:`Missing: ${g.missingReqs.join(', ')}`;
            return(
              <div className="al-row" key={g.id}>
                <div className="al-av">{(g.first[0]||'')+(g.last[0]||'')}</div>
                <div className="al-info">
                  <div className="al-name">{nm}</div>
                  <div className="al-meta">{g.no} · {g.agency} · {dt}</div>
                </div>
                <Badge s={g.status}/>
              </div>
            );
          })}
      </div>
    </>
  );
}

const PER=10;
function GuardsList({guards,setGuards,initSt}){
  const [q,setQ]=useState('');
  const [sf,setSf]=useState(initSt||'ALL');
  const [af,setAf]=useState('ALL');
  const [pg,setPg]=useState(1);
  const [view,setView]=useState(null);
  const [add,setAdd]=useState(false);
  const [toast,setToast]=useState('');
  const showT=m=>{setToast(m);setTimeout(()=>setToast(''),3000);};
  const fil=guards.filter(g=>{
    const lq=q.toLowerCase();
    const no=String(g.no||'').toLowerCase();
    const nm=String(`${g.last||''} ${g.first||''}`).toLowerCase();
    const ag=String(g.agency||'').toLowerCase();
    const ct=String(g.contact||'');
    const mq=!q||(no.includes(lq)||nm.includes(lq)||ag.includes(lq)||ct.includes(lq));
    return mq&&(sf==='ALL'||g.status===sf)&&(af==='ALL'||g.agency===af);
  });
  const pages=Math.max(1,Math.ceil(fil.length/PER));
  const rows=fil.slice((pg-1)*PER,pg*PER);
  const s1=(pg-1)*PER+1,s2=Math.min(pg*PER,fil.length);
  useEffect(()=>setPg(1),[q,sf,af]);
  const sv=form=>{
    const g={id:guards.length+1,no:`JG-D${String(100000+guards.length+1).slice(1)}`,
      last:form.last,first:form.first,mid:form.mid,suffix:form.suffix,
      bday:form.bday,age:parseInt(form.age)||0,agency:form.agency,contact:form.contact,
      status:'VALID',expDate:'',missing:0,missingReqs:[]};
    setGuards(p=>[g,...p]);showT('Guard registered successfully.');
  };
  const pns=[];
  for(let p=Math.max(1,pg-2);p<=Math.min(pages,pg+2);p++)pns.push(p);
  return(
    <>
      {view&&<GuardModal g={view} close={()=>setView(null)} onUpdated={(ng)=>{
        setGuards(p=>p.map(x=>x.id===ng.id?{...x,...ng}:x));
        setView(ng);
      }}/>}
      {add&&<AddModal close={()=>setAdd(false)} save={sv}/>}
      {toast&&<div className="toast"><div className="tico"><Ic.check/></div><div className="ttxt">{toast}</div></div>}
      <div className="ph">
        <div>
          <div className="ph-title">Guard Registry</div>
          <div className="ph-sub">{fil.length} records across {AG.length} agencies</div>
        </div>
        <div className="ph-actions">
          <button className="btn btn-p sm" onClick={()=>setAdd(true)}><Ic.plus/>Add Guard</button>
        </div>
      </div>
      <div className="tc">
        <div className="tctrl">
          <div className="sw"><Ic.search/><input className="si" placeholder="Search by name, ID, agency or contact…" value={q} onChange={e=>setQ(e.target.value)}/></div>
          <select className="ts" value={sf} onChange={e=>setSf(e.target.value)}>
            <option value="ALL">All Status</option>
            {STS.map(s=><option key={s}>{s}</option>)}
          </select>
          <select className="ts" value={af} onChange={e=>setAf(e.target.value)}>
            <option value="ALL">All Agencies</option>
            {AG.map(a=><option key={a} value={a}>{a}</option>)}
          </select>
          {(q||sf!=='ALL'||af!=='ALL')&&<button className="btn btn-g sm" onClick={()=>{setQ('');setSf('ALL');setAf('ALL');}}>Clear</button>}
        </div>
        {rows.length===0
          ?<div className="empty"><div className="ei"><Ic.guard/></div><div className="et">No results found</div><div className="es">Try a different search or filter</div></div>
          :<table>
            <thead><tr>
              <th>Guard No.</th><th>Name</th><th>Agency</th><th>Contact</th>
              <th>Missing</th><th>License Status</th><th>Expiry Date</th><th>Action</th>
            </tr></thead>
            <tbody>
              {rows.map(g=>(
                <tr key={g.id}>
                  <td><span className="gno">{g.no}</span></td>
                  <td><span className="gnm" onClick={()=>setView(g)}>{g.last}, {g.first} {g.mid}</span></td>
                  <td>{g.agency}</td>
                  <td style={{fontFamily:'var(--mono)',fontSize:12}}>{g.contact}</td>
                  <td><span className={g.missing===0?'mc0':'mcn'}>{g.missing}</span></td>
                  <td><Badge s={g.status}/></td>
                  <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-400)'}}>{g.expDate||'—'}</td>
                  <td style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap'}}>
                    <button className="btn btn-op sm" onClick={()=>setView(g)}>Open</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>}
        <div className="pgn">
          <div className="pgi">Showing {fil.length>0?s1:0}–{s2} of {fil.length} guards</div>
          <div className="pgb">
            <button className="pb" onClick={()=>setPg(p=>Math.max(1,p-1))} disabled={pg===1}><Ic.chL/></button>
            {pns.map(p=><button key={p} className={`pb${p===pg?' on':''}`} onClick={()=>setPg(p)}>{p}</button>)}
            <button className="pb" onClick={()=>setPg(p=>Math.min(pages,p+1))} disabled={pg===pages}><Ic.chR/></button>
          </div>
        </div>
      </div>
    </>
  );
}

function App(){
  const [pg,setPg]=useState('dashboard');
  const [guards,setGuards]=useState(GD);
  const [addOpen,setAddOpen]=useState(false);
  const [fst,setFst]=useState('ALL');
  const expired=guards.filter(g=>g.status==='EXPIRED').length;

  const roleLabel = (r) => {
    if (r === 'security_operation') return 'Security Operation';
    if (r === 'employee') return 'Employee';
    return 'Administrator';
  };

  const AccountMenu = () => {
    const [open, setOpen] = useState(false);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => {
        const root = document.getElementById('sbAccountMenu');
        if (!root) return;
        if (!root.contains(e.target)) setOpen(false);
      };
      document.addEventListener('mousedown', onDoc);
      return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);

    const isAdmin = (DATA.userRole || '') === 'admin';
    return (
      <div className="sb-dd" id="sbAccountMenu">
        <button
          className="sb-trigger"
          type="button"
          aria-haspopup="menu"
          aria-expanded={open ? 'true' : 'false'}
          onClick={() => setOpen(v => !v)}
        >
          <div className="sb-av">{DATA.userInitials||'U'}</div>
          <div style={{minWidth:0}}>
            <div className="sb-uname" style={{whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{DATA.userName||'User'}</div>
            <div className="sb-urole">{roleLabel(DATA.userRole||'')}</div>
          </div>
          <span className="sb-chev" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9l6 6 6-6"/></svg>
          </span>
        </button>

        {open && (
          <div className="sb-menu" role="menu" aria-label="Account actions">
            {isAdmin && (
              <a className="sb-mi" role="menuitem" href="../auth/switch_company.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12a9 9 0 1 1-3.03-6.72"/><path d="M21 3v6h-6"/></svg>
                Switch Company
              </a>
            )}
            <a className="sb-mi d" role="menuitem" href="../auth/logout.php">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 17l-1 4 4-1"/><path d="M3 12h11"/><path d="M10 8l4 4-4 4"/><path d="M14 4h6v16h-6"/></svg>
              Logout
            </a>
          </div>
        )}
      </div>
    );
  };
  const addG=form=>{
    const g={id:guards.length+1,no:`JG-D${String(100000+guards.length+1).slice(1)}`,
      last:form.last,first:form.first,mid:form.mid,suffix:form.suffix,
      bday:form.bday,age:parseInt(form.age)||0,agency:form.agency,contact:form.contact,
      status:'VALID',expDate:'',missing:0,missingReqs:[]};
    setGuards(p=>[g,...p]);setPg('guards');
  };
  return(
    <div className="shell">
      {addOpen&&<AddModal close={()=>setAddOpen(false)} save={addG}/>}
      <aside className="sb">
        <div className="sb-top">
          <div className="sb-brand">
             <div className="sb-logo">E</div>
             <div><div className="sb-name">ERMS</div><div className="sb-tagline">{DATA.companyLabel||'Company'}</div></div>
           </div>
         </div>
        <div className="sb-nav">
          <div className="sb-nav-label">Navigation</div>
          {[
            {id:'dashboard',label:'Dashboard',icon:<Ic.dash/>},
            {id:'guards',label:'Guards',icon:<Ic.guard/>,badge:expired>0?expired:0},
          ].map(n=>(
            <button key={n.id} className={`sb-item${pg===n.id?' on':''}`} onClick={()=>setPg(n.id)}>
              {n.icon}<span style={{flex:1}}>{n.label}</span>
              {n.badge>0&&<span className="sb-pill">{n.badge}</span>}
            </button>
          ))}
        </div>
        <div className="sb-foot">
          <AccountMenu/>
         </div>
       </aside>
      <main className="main">
        <div className="topbar">
          <div>
            <div className="tb-pg">{pg==='dashboard'?'Admin Dashboard':'Guards'}</div>
            <div className="tb-crumb">ERMS &rsaquo; {pg==='dashboard'?'Dashboard':'Guards'}</div>
          </div>
          <div className="tb-r">
            <Clock/>
            <button className="tb-icobtn" style={{position:'relative'}}>
              <Ic.bell/><div className="tb-dot"/>
            </button>
          </div>
        </div>
        <div className="content">
           {pg==='dashboard'
             ?<Dashboard guards={guards} summary={DATA.summary} onAdd={()=>setAddOpen(true)} onGo={(st)=>{if(st)setFst(st);setPg('guards');}}/>
             :<GuardsList guards={guards} setGuards={setGuards} initSt={fst}/>}
        </div>
      </main>
    </div>
  );
}
ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
</script>
</body>
</html>
