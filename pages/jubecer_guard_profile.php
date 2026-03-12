<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('admin');
require_company();

if ((string)($_SESSION['company'] ?? '') !== 'jubecer') {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$isEmbed = isset($_GET['embed']) && (string)$_GET['embed'] === '1';

$formError = '';

$dbNeedsMigration = false;
$hasUploadColumns = false;

$guardId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($guardId <= 0) {
    header('Location: jubecer_guards.php');
    exit;
}

$pdo = db();

try {
    $colStmt = $pdo->prepare(
        "SELECT COUNT(*) AS c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'guard_requirements'
           AND COLUMN_NAME IN ('document_path','document_original_name','document_mime','document_size')"
    );
    $colStmt->execute();
    $count = (int)($colStmt->fetchColumn());
    $hasUploadColumns = $count >= 4;

    $notesStmt = $pdo->prepare(
        "SELECT COUNT(*) AS c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'guard_requirements'
           AND COLUMN_NAME = 'notes'"
    );
    $notesStmt->execute();
    $hasNotesColumn = ((int)$notesStmt->fetchColumn()) >= 1;

    $dbNeedsMigration = !$hasUploadColumns;
} catch (Throwable $e) {
    $dbNeedsMigration = true;
    $hasUploadColumns = false;
    $hasNotesColumn = true;
}

$guardStmt = $pdo->prepare('SELECT id, guard_no, last_name, first_name, middle_name, suffix, birthdate, age, agency, full_name, contact_no, status FROM guards WHERE id = :id LIMIT 1');
$guardStmt->execute(['id' => $guardId]);
$guard = $guardStmt->fetch();

if (!$guard) {
    header('Location: jubecer_guards.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'save_requirement') {
        if ($dbNeedsMigration) {
            $formError = 'Database update required. Please run the ALTER TABLE migration for guard_requirements.';
        }

        if ($formError !== '') {
            // Do not continue saving when schema is not migrated (prevents SQL errors on missing columns)
        } else {
        $reqTypeId = isset($_POST['requirement_type_id']) ? (int)$_POST['requirement_type_id'] : 0;
        $documentNo = trim((string)($_POST['document_no'] ?? ''));
        $issuedDateRaw = trim((string)($_POST['issued_date'] ?? ''));
        $expiryDateRaw = trim((string)($_POST['expiry_date'] ?? ''));

        $issuedDate = $issuedDateRaw !== '' ? $issuedDateRaw : null;
        $expiryDate = $expiryDateRaw !== '' ? $expiryDateRaw : null;

        $reqTypeStmt = $pdo->prepare('SELECT code, expires FROM requirement_types WHERE id = :id LIMIT 1');
        $reqTypeStmt->execute(['id' => $reqTypeId]);
        $reqType = $reqTypeStmt->fetch();
        $reqCode = $reqType ? (string)$reqType['code'] : '';

        if ($reqCode !== 'SECURITY_LICENSE') {
            $issuedDate = null;
            $expiryDate = null;
        }

        $documentPath = null;
        $documentOriginalName = null;
        $documentMime = null;
        $documentSize = null;

        $file = $_FILES['document_file'] ?? null;
        $hasNewUpload = is_array($file) && isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasNewUpload) {
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                $formError = 'Upload failed. Please try again.';
            } else {
                $maxBytes = 8 * 1024 * 1024;
                $size = isset($file['size']) ? (int)$file['size'] : 0;
                if ($size <= 0 || $size > $maxBytes) {
                    $formError = 'File must be less than 8MB.';
                } else {
                    $originalName = (string)($file['name'] ?? 'document');
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                    if ($ext !== '' && !in_array($ext, $allowed, true)) {
                        $formError = 'Allowed file types: PDF, JPG, JPEG, PNG.';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/guard_requirements';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0775, true);
                        }

                        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                            $formError = 'Upload folder is not writable.';
                        } else {
                            $safeExt = $ext !== '' ? ('.' . $ext) : '';
                            $storedName = 'g' . (int)$guard['id'] . '_t' . (int)$reqTypeId . '_' . bin2hex(random_bytes(10)) . $safeExt;
                            $targetPath = $uploadDir . '/' . $storedName;

                            if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                                $formError = 'Unable to save uploaded file.';
                            } else {
                                $documentPath = 'uploads/guard_requirements/' . $storedName;
                                $documentOriginalName = $originalName;
                                $documentMime = (string)($file['type'] ?? '');
                                $documentSize = $size;
                            }
                        }
                    }
                }
            }
        }

        if ($formError === '' && $reqCode === 'SECURITY_LICENSE' && ($expiryDate === null || $expiryDate === '')) {
            $formError = 'Expiry date is required for Security License.';
        }

        if ($formError === '' && $reqTypeId > 0) {
            $existingReqSql = 'SELECT id';
            if ($hasUploadColumns) {
                $existingReqSql .= ', document_path';
            }
            $existingReqSql .= ' FROM guard_requirements WHERE guard_id = :guard_id AND requirement_type_id = :requirement_type_id LIMIT 1';

            $existingReqStmt = $pdo->prepare($existingReqSql);
            $existingReqStmt->execute([
                'guard_id' => (int)$guard['id'],
                'requirement_type_id' => $reqTypeId,
            ]);
            $existingReq = $existingReqStmt->fetch();

            $existingPath = '';
            if ($hasUploadColumns) {
                $existingPath = $existingReq ? (string)($existingReq['document_path'] ?? '') : '';
            }
            if ($existingPath === '' && !$hasNewUpload) {
                $formError = 'Please upload the document file.';
            }
        }

        if ($formError === '' && $reqTypeId > 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO guard_requirements (
                    guard_id,
                    requirement_type_id,
                    document_no,
                    issued_date,
                    expiry_date,
                    document_path,
                    document_original_name,
                    document_mime,
                    document_size
                 )
                 VALUES (
                    :guard_id,
                    :requirement_type_id,
                    :document_no,
                    :issued_date,
                    :expiry_date,
                    :document_path,
                    :document_original_name,
                    :document_mime,
                    :document_size
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
                'guard_id' => (int)$guard['id'],
                'requirement_type_id' => $reqTypeId,
                'document_no' => $documentNo !== '' ? $documentNo : null,
                'issued_date' => $issuedDate,
                'expiry_date' => $expiryDate,
                'document_path' => $documentPath,
                'document_original_name' => $documentOriginalName,
                'document_mime' => $documentMime,
                'document_size' => $documentSize,
            ]);
        }

        if ($formError === '') {
            $redirect = 'jubecer_guard_profile.php?id=' . (int)$guard['id'];
            if ($isEmbed) {
                $redirect .= '&embed=1';
            }
            header('Location: ' . $redirect);
            exit;
        }

        }
    }

    if ($action === 'update_guard') {
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $middleName = trim((string)($_POST['middle_name'] ?? ''));
        $suffix = trim((string)($_POST['suffix'] ?? ''));
        $birthdate = trim((string)($_POST['birthdate'] ?? ''));
        $ageRaw = trim((string)($_POST['age'] ?? ''));
        $agency = trim((string)($_POST['agency'] ?? ''));
        $contactNo = trim((string)($_POST['contact_no'] ?? ''));
        $status = isset($_POST['status']) ? (string)$_POST['status'] : 'active';
        if ($status !== 'active' && $status !== 'inactive') {
            $status = 'active';
        }

        $age = null;
        if ($ageRaw !== '' && ctype_digit($ageRaw)) {
            $age = (int)$ageRaw;
        }

        $birthdateVal = $birthdate !== '' ? $birthdate : null;

        $fullNameParts = [];
        if ($lastName !== '') {
            $fullNameParts[] = $lastName . ',';
        }
        if ($firstName !== '') {
            $fullNameParts[] = $firstName;
        }
        if ($middleName !== '') {
            $fullNameParts[] = $middleName;
        }
        if ($suffix !== '') {
            $fullNameParts[] = $suffix;
        }
        $fullName = trim(implode(' ', $fullNameParts));

        if ($lastName !== '' && $firstName !== '') {
            $stmt = $pdo->prepare(
                'UPDATE guards\n'
                . 'SET last_name = :last_name, first_name = :first_name, middle_name = :middle_name, suffix = :suffix, birthdate = :birthdate, age = :age, agency = :agency, full_name = :full_name, contact_no = :contact_no, status = :status\n'
                . 'WHERE id = :id'
            );
            $stmt->execute([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'suffix' => $suffix !== '' ? $suffix : null,
                'birthdate' => $birthdateVal,
                'age' => $age,
                'agency' => $agency !== '' ? $agency : null,
                'full_name' => $fullName,
                'contact_no' => $contactNo !== '' ? $contactNo : null,
                'status' => $status,
                'id' => (int)$guard['id'],
            ]);
        }

        $redirect = 'jubecer_guard_profile.php?id=' . (int)$guard['id'];
        if ($isEmbed) {
            $redirect .= '&embed=1';
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$reqTypes = $pdo->query('SELECT id, code, name, expires, is_required FROM requirement_types WHERE is_required = 1 ORDER BY id')->fetchAll();

$reqStmtSql = '';
if ($hasUploadColumns) {
    $reqStmtSql = 'SELECT gr.requirement_type_id, gr.document_no, gr.issued_date, gr.expiry_date, gr.document_path, gr.document_original_name
                   FROM guard_requirements gr
                   WHERE gr.guard_id = :guard_id';
} else {
    $reqStmtSql = 'SELECT gr.requirement_type_id, gr.document_no, gr.issued_date, gr.expiry_date
                   FROM guard_requirements gr
                   WHERE gr.guard_id = :guard_id';
}

$reqStmt = $pdo->prepare($reqStmtSql);
$reqStmt->execute(['guard_id' => (int)$guard['id']]);
$reqRows = $reqStmt->fetchAll();

$reqByType = [];
foreach ($reqRows as $row) {
    $reqByType[(int)$row['requirement_type_id']] = $row;
}

function requirement_status(string $code, ?string $expiryDate, ?string $documentPath): string
{
    if ($documentPath === null || $documentPath === '') {
        return 'Missing';
    }

    if ($code !== 'SECURITY_LICENSE') {
        return 'Encoded';
    }

    if ($expiryDate === null || $expiryDate === '') {
        return 'Missing';
    }

    $today = new DateTimeImmutable('today');
    $expiry = DateTimeImmutable::createFromFormat('Y-m-d', $expiryDate);
    if (!$expiry) {
        return 'Missing';
    }

    if ($expiry < $today) {
        return 'Expired';
    }

    $soon = $today->modify('+6 months');
    if ($expiry <= $soon) {
        return 'Expiring';
    }

    return 'Valid';
}

$userName = (string)($_SESSION['user_name'] ?? 'User');
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
if ($userInitials === '') {
    $userInitials = 'U';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Guard Profile | ERMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<?php if ($isEmbed): ?>
    <div style="padding: 18px;">
        <div style="display:flex; align-items:center; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
            <div>
                <div style="font-weight: 700; font-size: 18px;">Guard Profile</div>
                <div style="opacity: 0.8; font-size: 13px; margin-top: 2px;">Guard: <?php echo htmlspecialchars((string)$guard['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div style="display:flex; gap: 8px;">
                <a class="primary-btn btn-sm" href="jubecer_guard_profile.php?id=<?php echo (int)$guard['id']; ?>" target="_blank" rel="noopener">Open Full Page</a>
            </div>
        </div>

        <section class="section" style="margin-top: 0;">
            <div class="section-title">Requirements</div>
            <div class="panel">
                <?php if ($dbNeedsMigration): ?>
                    <div style="padding: 12px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); background: rgba(249, 115, 22, 0.10); color: #7c2d12; font-weight: 800;">
                        Database not migrated yet. Please run the ALTER TABLE migration to enable uploads.
                    </div>
                <?php endif; ?>
                <?php if ($formError !== ''): ?>
                    <div style="padding: 12px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); background: rgba(239, 68, 68, 0.08); color: #991b1b; font-weight: 800;">
                        <?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php
                    $reqTotal = count($reqTypes);
                    $reqMissing = 0;
                    $reqExpired = 0;
                    $reqExpiring = 0;
                    $reqOk = 0;

                    foreach ($reqTypes as $rtCount) {
                        $typeIdCount = (int)$rtCount['id'];
                        $existingCount = $reqByType[$typeIdCount] ?? null;
                        $docNoCount = $existingCount ? (string)($existingCount['document_no'] ?? '') : '';
                        $issuedCount = $existingCount ? (string)($existingCount['issued_date'] ?? '') : '';
                        $expiryCount = $existingCount ? (string)($existingCount['expiry_date'] ?? '') : '';
                        $docPathCount = $existingCount ? (string)($existingCount['document_path'] ?? '') : '';

                        $st = 'Missing';
                        if ((string)$rtCount['code'] === 'SECURITY_LICENSE') {
                            $st = requirement_status((string)$rtCount['code'], $expiryCount !== '' ? $expiryCount : null, $docPathCount !== '' ? $docPathCount : null);
                        } else {
                            $st = requirement_status((string)$rtCount['code'], null, $docPathCount !== '' ? $docPathCount : null);
                        }

                        if ($st === 'Missing') {
                            $reqMissing++;
                        } elseif ($st === 'Expired') {
                            $reqExpired++;
                        } elseif ($st === 'Expiring') {
                            $reqExpiring++;
                        } else {
                            $reqOk++;
                        }
                    }
                ?>

                <div class="req-toolbar">
                    <div class="req-chips">
                        <div class="req-chip req-chip--danger">Missing: <?php echo (int)$reqMissing; ?></div>
                        <div class="req-chip req-chip--danger">Expired: <?php echo (int)$reqExpired; ?></div>
                        <div class="req-chip req-chip--warn">Expiring: <?php echo (int)$reqExpiring; ?></div>
                        <div class="req-chip req-chip--ok">OK: <?php echo (int)$reqOk; ?> / <?php echo (int)$reqTotal; ?></div>
                    </div>
                    <div class="req-search">
                        <input class="input" id="reqSearch" type="text" placeholder="Search requirement..." autocomplete="off" />
                    </div>
                </div>

                <div class="req-table-wrap">
                    <table class="req-table" id="reqTable">
                        <thead>
                            <tr>
                                <th>Requirement</th>
                                <th>Status</th>
                                <th>Expiry</th>
                                <th>File</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqTypes as $rt): ?>
                                <?php
                                    $typeId = (int)$rt['id'];
                                    $existing = $reqByType[$typeId] ?? null;

                                    $docNo = $existing ? (string)($existing['document_no'] ?? '') : '';
                                    $issued = $existing ? (string)($existing['issued_date'] ?? '') : '';
                                    $expiry = $existing ? (string)($existing['expiry_date'] ?? '') : '';
                                    $docPath = $hasUploadColumns ? ($existing ? (string)($existing['document_path'] ?? '') : '') : '';
                                    $docOrig = $hasUploadColumns ? ($existing ? (string)($existing['document_original_name'] ?? '') : '') : '';

                                    $statusText = 'Missing';
                                    if ((string)$rt['code'] === 'SECURITY_LICENSE') {
                                        $statusText = requirement_status((string)$rt['code'], $expiry !== '' ? $expiry : null, $docPath !== '' ? $docPath : null);
                                    } else {
                                        $statusText = requirement_status((string)$rt['code'], null, $docPath !== '' ? $docPath : null);
                                    }

                                    $badgeClass = 'badge badge--missing';
                                    if ($statusText === 'Valid') {
                                        $badgeClass = 'badge badge--valid';
                                    } elseif ($statusText === 'Expiring') {
                                        $badgeClass = 'badge badge--expiring';
                                    } elseif ($statusText === 'Expired') {
                                        $badgeClass = 'badge badge--expired';
                                    } elseif ($statusText === 'Encoded') {
                                        $badgeClass = 'badge badge--encoded';
                                    }

                                    $expiryDisplay = '';
                                    if ((string)$rt['code'] === 'SECURITY_LICENSE') {
                                        $expiryDisplay = $expiry;
                                    }
                                    $isProblem = ($statusText === 'Missing' || $statusText === 'Expired' || $statusText === 'Expiring');
                                    $hasFile = $hasUploadColumns && $docPath !== '';
                                ?>
                                <tr class="req-row" data-req-name="<?php echo htmlspecialchars(strtolower((string)$rt['name']), ENT_QUOTES, 'UTF-8'); ?>" data-req-problem="<?php echo $isProblem ? '1' : '0'; ?>">
                                    <td>
                                        <div style="font-weight: 850; color: #0f172a;">
                                            <?php echo htmlspecialchars((string)$rt['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td><span class="<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars($expiryDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if (!$hasUploadColumns): ?>
                                            <span style="opacity: 0.7;">Migration needed</span>
                                        <?php elseif ($hasFile): ?>
                                            <a class="table-link" href="download_guard_requirement.php?guard_id=<?php echo (int)$guard['id']; ?>&requirement_type_id=<?php echo $typeId; ?>" target="_blank" rel="noopener">Download</a>
                                        <?php else: ?>
                                            <span style="opacity: 0.7;">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="primary-btn btn-sm" type="button" data-req-edit="<?php echo $typeId; ?>">Edit</button>
                                    </td>
                                </tr>
                                <tr class="req-editor-row" data-req-editor="<?php echo $typeId; ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="req-editor">
                                            <form class="form" method="post" <?php echo $hasUploadColumns ? 'enctype="multipart/form-data"' : ''; ?> action="jubecer_guard_profile.php?id=<?php echo (int)$guard['id']; ?>&embed=1">
                                                <input type="hidden" name="action" value="save_requirement" />
                                                <input type="hidden" name="requirement_type_id" value="<?php echo $typeId; ?>" />
                                                <div class="form-grid">
                                                    <div class="field">
                                                        <label class="label">Document No</label>
                                                        <input class="input" name="document_no" type="text" value="<?php echo htmlspecialchars($docNo, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    </div>
                                                    <?php if ((string)$rt['code'] === 'SECURITY_LICENSE'): ?>
                                                        <div class="field">
                                                            <label class="label">Issued Date</label>
                                                            <input class="input" name="issued_date" type="date" value="<?php echo htmlspecialchars($issued, ENT_QUOTES, 'UTF-8'); ?>" />
                                                        </div>
                                                        <div class="field">
                                                            <label class="label">Expiry Date</label>
                                                            <input class="input" name="expiry_date" type="date" value="<?php echo htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8'); ?>" />
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($hasUploadColumns): ?>
                                                        <div class="field form-grid-span-2">
                                                            <label class="label">Upload Document</label>
                                                            <input class="input" name="document_file" type="file" accept=".pdf,.jpg,.jpeg,.png" <?php echo $hasFile ? '' : 'required'; ?> />
                                                            <?php if ($hasFile): ?>
                                                                <div style="margin-top: 6px; font-size: 12px; opacity: 0.85;">
                                                                    Current: <?php echo htmlspecialchars($docOrig !== '' ? $docOrig : basename($docPath), ENT_QUOTES, 'UTF-8'); ?>
                                                                    • <a class="table-link" href="download_guard_requirement.php?guard_id=<?php echo (int)$guard['id']; ?>&requirement_type_id=<?php echo $typeId; ?>" target="_blank" rel="noopener">Download</a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="field form-grid-span-2">
                                                            <label class="label">Upload Document</label>
                                                            <input class="input" type="text" value="Run DB migration first" disabled />
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="form-actions">
                                                    <button class="primary-btn" type="submit">Save</button>
                                                    <button class="secondary-btn" type="button" data-req-cancel="<?php echo $typeId; ?>">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="section">
            <details class="req-item">
                <summary>
                    <div class="req-title">
                        <div class="req-title-main">Guard Information</div>
                        <div class="req-title-sub">Click to view/update guard details</div>
                    </div>
                    <span class="badge badge--encoded">EDIT</span>
                </summary>
                <div class="req-body">
                    <form class="form" method="post" action="jubecer_guard_profile.php?id=<?php echo (int)$guard['id']; ?>&embed=1">
                        <input type="hidden" name="action" value="update_guard" />
                        <div class="form-grid">
                            <div class="field">
                                <label class="label">Guard No</label>
                                <input class="input" type="text" value="<?php echo htmlspecialchars((string)$guard['guard_no'], ENT_QUOTES, 'UTF-8'); ?>" disabled />
                            </div>
                            <div class="field">
                                <label class="label" for="last_name">Last Name</label>
                                <input class="input" id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars((string)($guard['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                            </div>
                            <div class="field">
                                <label class="label" for="first_name">First Name</label>
                                <input class="input" id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars((string)($guard['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                            </div>
                            <div class="field">
                                <label class="label" for="middle_name">Middle Name</label>
                                <input class="input" id="middle_name" name="middle_name" type="text" value="<?php echo htmlspecialchars((string)($guard['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="suffix">Suffix</label>
                                <input class="input" id="suffix" name="suffix" type="text" value="<?php echo htmlspecialchars((string)($guard['suffix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="birthdate">Birthdate</label>
                                <input class="input" id="birthdate" name="birthdate" type="date" value="<?php echo htmlspecialchars((string)($guard['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="age">Age</label>
                                <input class="input" id="age" name="age" type="number" min="0" max="130" value="<?php echo htmlspecialchars((string)($guard['age'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="agency">Agency</label>
                                <input class="input" id="agency" name="agency" type="text" value="<?php echo htmlspecialchars((string)($guard['agency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="contact_no">Contact No</label>
                                <input class="input" id="contact_no" name="contact_no" type="text" value="<?php echo htmlspecialchars((string)($guard['contact_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="field">
                                <label class="label" for="status">Status</label>
                                <input class="input" id="status" name="status" type="text" list="status_list" value="<?php echo htmlspecialchars((string)$guard['status'], ENT_QUOTES, 'UTF-8'); ?>" required />
                                <datalist id="status_list">
                                    <option value="active">
                                    <option value="inactive">
                                </datalist>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="primary-btn" type="submit">Save Info</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <script>
            (function () {
                var search = document.getElementById('reqSearch');
                var table = document.getElementById('reqTable');
                if (!table) return;

                function closeAllEditors() {
                    var editors = table.querySelectorAll('[data-req-editor]');
                    editors.forEach(function (row) {
                        row.style.display = 'none';
                    });
                }

                function openEditor(typeId) {
                    closeAllEditors();
                    var row = table.querySelector('[data-req-editor="' + String(typeId) + '"]');
                    if (!row) return;
                    row.style.display = '';
                    var input = row.querySelector('input,select,textarea');
                    if (input) {
                        input.focus();
                    }
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target && e.target.closest && e.target.closest('[data-req-edit]');
                    if (btn) {
                        var id = btn.getAttribute('data-req-edit');
                        if (id) {
                            openEditor(id);
                        }
                        return;
                    }

                    var cancel = e.target && e.target.closest && e.target.closest('[data-req-cancel]');
                    if (cancel) {
                        closeAllEditors();
                        return;
                    }
                });

                if (search) {
                    search.addEventListener('input', function () {
                        var q = String(search.value || '').trim().toLowerCase();
                        var rows = table.querySelectorAll('tr.req-row');
                        rows.forEach(function (r) {
                            var name = (r.getAttribute('data-req-name') || '');
                            var show = q === '' || name.indexOf(q) !== -1;
                            r.style.display = show ? '' : 'none';

                            var typeId = null;
                            var editBtn = r.querySelector('[data-req-edit]');
                            if (editBtn) {
                                typeId = editBtn.getAttribute('data-req-edit');
                            }
                            if (typeId) {
                                var editorRow = table.querySelector('[data-req-editor="' + String(typeId) + '"]');
                                if (editorRow) {
                                    editorRow.style.display = 'none';
                                }
                            }
                        });
                    });
                }

                var firstProblem = table.querySelector('tr.req-row[data-req-problem="1"] [data-req-edit]');
                if (firstProblem) {
                    openEditor(firstProblem.getAttribute('data-req-edit'));
                }
            })();
        </script>
    </div>
<?php else: ?>
    <div class="layout">
        <aside class="sidebar" aria-label="Sidebar Navigation">
            <div class="sidebar-top">
                <div class="brand">
                    <div class="brand-logo" aria-hidden="true">J</div>
                    <div class="brand-text">
                        <div class="brand-title">ERMS</div>
                        <div class="brand-subtitle">Jubecer</div>
                    </div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item" href="home.php">
                    <span class="nav-item-content">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 13h8V3H3v10Z" />
                                <path d="M13 21h8V11h-8v10Z" />
                                <path d="M13 3h8v6h-8V3Z" />
                                <path d="M3 17h8v4H3v-4Z" />
                            </svg>
                        </span>
                        <span class="nav-label">Dashboard</span>
                    </span>
                </a>
                <a class="nav-item is-active" href="jubecer_guards.php">
                    <span class="nav-item-content">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 21a8 8 0 1 0-16 0" />
                                <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                            </svg>
                        </span>
                        <span class="nav-label">Guards</span>
                    </span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="profile-dropdown" id="profileDropdownJubecerProfile">
                    <div
                        class="profile profile-trigger"
                        role="button"
                        tabindex="0"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="Account menu"
                    >
                        <div class="avatar"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="profile-text">
                            <div class="profile-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="profile-role">Administrator</div>
                        </div>
                        <span class="profile-chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </div>

                    <div class="profile-menu" role="menu" aria-label="Account actions">
                        <a class="profile-menu-item" role="menuitem" href="../auth/switch_company.php">
                            <span class="profile-menu-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21 12a9 9 0 1 1-3.03-6.72" />
                                    <path d="M21 3v6h-6" />
                                </svg>
                            </span>
                            Switch Company
                        </a>
                        <a class="profile-menu-item" role="menuitem" href="../auth/logout.php">
                            <span class="profile-menu-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10 17l-1 4 4-1" />
                                    <path d="M3 12h11" />
                                    <path d="M10 8l4 4-4 4" />
                                    <path d="M14 4h6v16h-6" />
                                </svg>
                            </span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button
                        class="menu-btn"
                        type="button"
                        aria-label="Toggle navigation"
                        onclick="document.body.classList.toggle('sidebar-open')"
                    >
                        ☰
                    </button>
                    <div class="page-title">
                        <div class="page-title-main-row">
                            <div class="page-title-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M20 21a8 8 0 1 0-16 0" />
                                    <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                </svg>
                            </div>
                            <div class="page-title-text">
                                <div class="page-title-main">Guard Profile</div>
                                <div class="page-title-sub"><?php echo htmlspecialchars((string)$guard['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <a class="primary-btn btn-sm" href="jubecer_guards.php">Back to list</a>
                </div>
            </header>

            <main class="content">
                <section class="section">
                    <div class="section-title">Guard Information</div>
                    <div class="panel">
                        <form class="form" method="post" action="jubecer_guard_profile.php?id=<?php echo (int)$guard['id']; ?>">
                            <input type="hidden" name="action" value="update_guard" />
                            <div class="form-grid">
                                <div class="field">
                                    <label class="label">Guard No</label>
                                    <input class="input" type="text" value="<?php echo htmlspecialchars((string)$guard['guard_no'], ENT_QUOTES, 'UTF-8'); ?>" disabled />
                                </div>
                                <div class="field">
                                    <label class="label" for="last_name">Last Name</label>
                                    <input class="input" id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars((string)($guard['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                                </div>
                                <div class="field">
                                    <label class="label" for="first_name">First Name</label>
                                    <input class="input" id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars((string)($guard['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                                </div>
                                <div class="field">
                                    <label class="label" for="middle_name">Middle Name</label>
                                    <input class="input" id="middle_name" name="middle_name" type="text" value="<?php echo htmlspecialchars((string)($guard['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="suffix">Suffix</label>
                                    <input class="input" id="suffix" name="suffix" type="text" value="<?php echo htmlspecialchars((string)($guard['suffix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="birthdate">Birthdate</label>
                                    <input class="input" id="birthdate" name="birthdate" type="date" value="<?php echo htmlspecialchars((string)($guard['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="age">Age</label>
                                    <input class="input" id="age" name="age" type="number" min="0" max="130" value="<?php echo htmlspecialchars((string)($guard['age'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="agency">Agency</label>
                                    <input class="input" id="agency" name="agency" type="text" value="<?php echo htmlspecialchars((string)($guard['agency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="contact_no">Contact No</label>
                                    <input class="input" id="contact_no" name="contact_no" type="text" value="<?php echo htmlspecialchars((string)($guard['contact_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                                <div class="field">
                                    <label class="label" for="status">Status</label>
                                    <input class="input" id="status" name="status" type="text" list="status_list" value="<?php echo htmlspecialchars((string)$guard['status'], ENT_QUOTES, 'UTF-8'); ?>" required />
                                    <datalist id="status_list">
                                        <option value="active">
                                        <option value="inactive">
                                    </datalist>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="primary-btn" type="submit">Save Info</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="section">
                    <div class="section-title">Requirements</div>
                    <div class="panel">
                        <div class="requirements">
                            <?php foreach ($reqTypes as $rt): ?>
                                <?php
                                    $typeId = (int)$rt['id'];
                                    $existing = $reqByType[$typeId] ?? null;

                                    $docNo = $existing ? (string)($existing['document_no'] ?? '') : '';
                                    $issued = $existing ? (string)($existing['issued_date'] ?? '') : '';
                                    $expiry = $existing ? (string)($existing['expiry_date'] ?? '') : '';
                                    $docPath = $existing ? (string)($existing['document_path'] ?? '') : '';
                                    $docOrig = $existing ? (string)($existing['document_original_name'] ?? '') : '';

                                    $statusText = 'Missing';
                                    if ((string)$rt['code'] === 'SECURITY_LICENSE') {
                                        $statusText = requirement_status((string)$rt['code'], $expiry !== '' ? $expiry : null, $docPath !== '' ? $docPath : null);
                                    } else {
                                        $statusText = requirement_status((string)$rt['code'], null, $docPath !== '' ? $docPath : null);
                                    }

                                    $badgeClass = 'badge badge--missing';
                                    if ($statusText === 'Valid') {
                                        $badgeClass = 'badge badge--valid';
                                    } elseif ($statusText === 'Expiring') {
                                        $badgeClass = 'badge badge--expiring';
                                    } elseif ($statusText === 'Expired') {
                                        $badgeClass = 'badge badge--expired';
                                    } elseif ($statusText === 'Encoded') {
                                        $badgeClass = 'badge badge--encoded';
                                    }

                                    $shouldOpen = ($statusText === 'Missing' || $statusText === 'Expired' || $statusText === 'Expiring');
                                    $meta = 'Status: ' . $statusText;
                                    if ((string)$rt['code'] === 'SECURITY_LICENSE' && $expiry !== '') {
                                        $meta .= ' • Expiry: ' . $expiry;
                                    }

                                    $hasFile = $docPath !== '';
                                ?>
                                <details class="req-item" <?php echo $shouldOpen ? 'open' : ''; ?>>
                                    <summary>
                                        <div class="req-title">
                                            <div class="req-title-main"><?php echo htmlspecialchars((string)$rt['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="req-title-sub"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <span class="<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </summary>
                                    <div class="req-body">
                                        <form class="form" method="post" enctype="multipart/form-data" action="jubecer_guard_profile.php?id=<?php echo (int)$guard['id']; ?>">
                                            <input type="hidden" name="action" value="save_requirement" />
                                            <input type="hidden" name="requirement_type_id" value="<?php echo $typeId; ?>" />
                                            <div class="form-grid">
                                                <div class="field">
                                                    <label class="label">Document No</label>
                                                    <input class="input" name="document_no" type="text" value="<?php echo htmlspecialchars($docNo, ENT_QUOTES, 'UTF-8'); ?>" />
                                                </div>
                                                <?php if ((string)$rt['code'] === 'SECURITY_LICENSE'): ?>
                                                    <div class="field">
                                                        <label class="label">Issued Date</label>
                                                        <input class="input" name="issued_date" type="date" value="<?php echo htmlspecialchars($issued, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    </div>
                                                    <div class="field">
                                                        <label class="label">Expiry Date</label>
                                                        <input class="input" name="expiry_date" type="date" value="<?php echo htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    </div>
                                                <?php endif; ?>
                                                <div class="field form-grid-span-2">
                                                    <label class="label">Upload Document</label>
                                                    <input class="input" name="document_file" type="file" accept=".pdf,.jpg,.jpeg,.png" <?php echo $hasFile ? '' : 'required'; ?> />
                                                    <?php if ($hasFile): ?>
                                                        <div style="margin-top: 6px; font-size: 12px; opacity: 0.85;">
                                                            Current: <?php echo htmlspecialchars($docOrig !== '' ? $docOrig : basename($docPath), ENT_QUOTES, 'UTF-8'); ?>
                                                            • <a class="table-link" href="download_guard_requirement.php?guard_id=<?php echo (int)$guard['id']; ?>&requirement_type_id=<?php echo $typeId; ?>" target="_blank" rel="noopener">Download</a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="form-actions">
                                                <button class="primary-btn" type="submit">Save <?php echo htmlspecialchars((string)$rt['name'], ENT_QUOTES, 'UTF-8'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <div class="backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    </div>

    <script>
        (function () {
            var dropdown = document.getElementById('profileDropdownJubecerProfile');
            if (!dropdown) return;

            var trigger = dropdown.querySelector('.profile-trigger');
            if (!trigger) return;

            function setExpanded(isOpen) {
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            function openMenu() {
                dropdown.classList.add('is-open');
                setExpanded(true);
            }

            function closeMenu() {
                dropdown.classList.remove('is-open');
                setExpanded(false);
            }

            function toggleMenu() {
                if (dropdown.classList.contains('is-open')) {
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
                if (!dropdown.classList.contains('is-open')) return;
                if (dropdown.contains(e.target)) return;
                closeMenu();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeMenu();
                }
            });
        })();
    </script>
</body>
</html>

<?php endif; ?>
