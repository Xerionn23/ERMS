<?php
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'admin') {
    header('Location: neuro_documents.php');
    exit;
}

require_company();
if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
    header('Location: home.php');
    exit;
}

$userName = (string)($_SESSION['user_name'] ?? 'User');
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
if ($userInitials === '') {
    $userInitials = 'U';
}

$roleLabel = $role === 'admin' ? 'Administrator' : 'Employee';

$documents = [];
$exportBase = dirname(__DIR__) . '/export_nuero';
$company = (string)($_SESSION['company'] ?? 'brainmaster');

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

function format_doc_type_label(string $docType): string
{
    return $docType === 'drug_test' ? 'Drug Test' : 'Neuro';
}

function format_purpose_label(string $purpose, string $purposeSpecify): string
{
    $purpose = strtolower(trim($purpose));
    $purposeSpecify = trim($purposeSpecify);
    $map = [
        'firearm' => 'Firearm',
        'security' => 'Security',
        'lto' => 'LTO',
        'others' => 'Others',
    ];

    $label = $map[$purpose] ?? '';
    if ($label === 'Others' && $purposeSpecify !== '') {
        $label = $label . ' (' . $purposeSpecify . ')';
    }

    return $label;
}

function safe_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

/**
 * Normalize dates into [display, raw] values.
 */
function format_doc_dates(?string $docDate, ?string $createdAt): array
{
    $display = '';
    $raw = '';

    if ($docDate) {
        $ts = strtotime($docDate);
        if ($ts !== false) {
            $display = date('F j, Y', $ts);
            $raw = date('Y-m-d', $ts);
        }
    }

    if ($display === '' && $createdAt) {
        $ts = strtotime($createdAt);
        if ($ts !== false) {
            $display = date('F j, Y', $ts);
            $raw = date('Y-m-d', $ts);
        }
    }

    return ['display' => $display, 'raw' => $raw];
}

/**
 * Scan the export folder and build a document list for seeding.
 */
function scan_documents_from_filesystem(string $exportBase): array
{
    $documents = [];
    if (!is_dir($exportBase)) {
        return $documents;
    }

    $folders = glob($exportBase . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($folders as $folderPath) {
        $folder = basename($folderPath);
        $docxFiles = glob($folderPath . '/*.docx') ?: [];
        foreach ($docxFiles as $filePath) {
            $fileName = basename($filePath);
            $meta = [];
            $metaPath = $filePath . '.json';
            if (is_file($metaPath)) {
                $raw = @file_get_contents($metaPath);
                $json = json_decode((string)$raw, true);
                if (is_array($json)) {
                    $meta = $json;
                }
            }

            $docType = (string)($meta['document_type'] ?? '');
            $fullName = (string)($meta['full_name'] ?? '');
            $docDate = (string)($meta['document_date'] ?? '');
            $docDateRaw = (string)($meta['document_date_raw'] ?? '');
            $purpose = (string)($meta['purpose'] ?? '');
            $purposeSpecify = (string)($meta['purpose_specify'] ?? '');

            if ($docType === '' || $fullName === '') {
                $baseName = basename($fileName, '.docx');
                if (stripos($baseName, ' - neuro document') !== false) {
                    $docType = $docType !== '' ? $docType : 'neuro';
                    $baseName = str_ireplace(' - neuro document', '', $baseName);
                } elseif (stripos($baseName, ' - drug test') !== false) {
                    $docType = $docType !== '' ? $docType : 'drug_test';
                    $baseName = str_ireplace(' - drug test', '', $baseName);
                } elseif (stripos($baseName, 'drug test') !== false) {
                    $docType = $docType !== '' ? $docType : 'drug_test';
                }

                if ($fullName === '') {
                    $fullName = trim($baseName);
                }
            }

            if ($docType === '') {
                $docType = 'neuro';
            }

            if ($docDate === '' || $docDateRaw === '') {
                $mtime = @filemtime($filePath) ?: time();
                if ($docDate === '') {
                    $docDate = date('F j, Y', $mtime);
                }
                if ($docDateRaw === '') {
                    $docDateRaw = date('Y-m-d', $mtime);
                }
            }

            if ($docDateRaw === '') {
                $ts = strtotime($docDate);
                if ($ts !== false) {
                    $docDateRaw = date('Y-m-d', $ts);
                }
            }

            $documents[] = [
                'date' => $docDate,
                'date_raw' => $docDateRaw,
                'name' => $fullName,
                'purpose' => $purpose,
                'purpose_specify' => $purposeSpecify,
                'type' => $docType,
                'folder' => $folder,
                'file' => $fileName,
                'file_path' => 'export_nuero/' . $folder . '/' . $fileName,
            ];
        }
    }

    return $documents;
}

try {
    $pdo = db();
    ensure_generated_documents_table($pdo);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_documents WHERE company = :company');
    $stmt->execute(['company' => $company]);
    $existingCount = (int)$stmt->fetchColumn();

    if ($existingCount === 0) {
        $seedDocs = scan_documents_from_filesystem($exportBase);
        if (!empty($seedDocs)) {
            $insert = $pdo->prepare(
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
                . 'file_name = VALUES(file_name)'
            );

            foreach ($seedDocs as $doc) {
                $insert->execute([
                    'company' => $company,
                    'document_type' => (string)$doc['type'],
                    'document_date' => $doc['date_raw'] !== '' ? $doc['date_raw'] : null,
                    'full_name' => (string)$doc['name'],
                    'purpose' => $doc['purpose'] !== '' ? (string)$doc['purpose'] : null,
                    'purpose_specify' => $doc['purpose_specify'] !== '' ? (string)$doc['purpose_specify'] : null,
                    'folder_name' => (string)$doc['folder'],
                    'file_name' => (string)$doc['file'],
                    'file_path' => (string)$doc['file_path'],
                    'created_by_user_id' => null,
                    'created_by_employee_id' => null,
                ]);
            }
        }
    }

    $stmt = $pdo->prepare(
        'SELECT document_date, full_name, purpose, purpose_specify, document_type, folder_name, file_name, created_at '
        . 'FROM generated_documents '
        . 'WHERE company = :company '
        . 'ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute(['company' => $company]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $dates = format_doc_dates((string)($row['document_date'] ?? ''), (string)($row['created_at'] ?? ''));
        $documents[] = [
            'date' => $dates['display'] !== '' ? $dates['display'] : 'Unknown',
            'date_raw' => $dates['raw'],
            'name' => (string)($row['full_name'] ?? ''),
            'purpose' => (string)($row['purpose'] ?? ''),
            'purpose_specify' => (string)($row['purpose_specify'] ?? ''),
            'type' => (string)($row['document_type'] ?? ''),
            'folder' => (string)($row['folder_name'] ?? ''),
            'file' => (string)($row['file_name'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    $documents = scan_documents_from_filesystem($exportBase);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Documents List | ERMS</title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/css/home_redesign.css" />
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
                <a class="sb-item" href="attendance.php" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/></svg>
                    <span style="flex:1">Attendance</span>
                </a>
                <a class="sb-item on" href="neuro_documents_list.php" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                    <span style="flex:1">Documents List</span>
                </a>
            </div>

            <div class="sb-foot">
                <div class="sb-dd" id="bmAccountMenu">
                    <button class="sb-trigger" type="button" aria-haspopup="menu" aria-expanded="false">
                        <div class="sb-av"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="sb-meta">
                            <div class="sb-uname" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="sb-urole"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
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
                        <div class="tb-pg">Documents List</div>
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
                        <div class="bm-title">Documents List</div>
                        <div class="bm-panel">
                            <div class="bm-form">
                                <div class="bm-grid">
                                    <div class="bm-field bm-span-2">
                                        <label class="bm-label" for="q">Search</label>
                                        <input class="bm-input" id="q" type="search" placeholder="Search name / folder / purpose..." />
                                    </div>
                                    <div class="bm-field">
                                        <label class="bm-label" for="purpose">Type</label>
                                        <select class="bm-input" id="purpose">
                                            <option value="">All types</option>
                                            <option value="neuro">Neuro</option>
                                            <option value="drug_test">Drug Test</option>
                                        </select>
                                    </div>
                                    <div class="bm-field">
                                        <label class="bm-label" for="date_from">Date From</label>
                                        <input class="bm-input" id="date_from" type="date" />
                                    </div>
                                    <div class="bm-field">
                                        <label class="bm-label" for="date_to">Date To</label>
                                        <input class="bm-input" id="date_to" type="date" />
                                    </div>
                                    <div class="bm-field">
                                        <label class="bm-label" for="pageSize">Rows</label>
                                        <select class="bm-input" id="pageSize">
                                            <option value="10">10 rows</option>
                                            <option value="20">20 rows</option>
                                            <option value="50">50 rows</option>
                                        </select>
                                    </div>
                                    <div class="bm-field" style="display:flex;align-items:flex-end;">
                                        <button class="btn btn-s" type="button" id="resetFilters">Reset</button>
                                    </div>
                                </div>

                                <div class="card" style="margin-top:12px;overflow:auto;">
                                    <table style="width:100%;border-collapse:collapse;min-width:880px;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">Document Date</th>
                                                <th style="text-align:left;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">Full Name</th>
                                                <th style="text-align:left;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">Type</th>
                                                <th style="text-align:left;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">Folder</th>
                                                <th style="text-align:left;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">File</th>
                                                <th style="text-align:right;padding:12px 14px;font-size:12px;color:var(--gray-600);font-weight:700;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="rows">
                                            <?php if (empty($documents)): ?>
                                                <tr>
                                                    <td colspan="6" style="padding:16px 14px;color:var(--gray-500);">No records yet.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($documents as $doc): ?>
                                                    <?php
                                                        $typeLabel = format_doc_type_label((string)$doc['type']);
                                                        $purposeLabel = format_purpose_label((string)$doc['purpose'], (string)$doc['purpose_specify']);
                                                        $purposeDisplay = $typeLabel;
                                                        $name = $doc['name'] !== '' ? $doc['name'] : 'Unknown';
                                                        $folder = (string)$doc['folder'];
                                                        $fileName = (string)$doc['file'];
                                                        $downloadUrl = '../auth/download_export.php?folder=' . rawurlencode($folder) . '&file=' . rawurlencode($fileName);
                                                        $searchText = safe_lower($name . ' ' . $folder . ' ' . $fileName . ' ' . $typeLabel . ' ' . $purposeLabel);
                                                    ?>
                                                    <tr class="doc-row" data-doc-row="1" data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo htmlspecialchars((string)$doc['type'], ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo htmlspecialchars((string)$doc['date_raw'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <td style="padding:12px 14px;font-size:13px;color:var(--gray-700);white-space:nowrap;">
                                                            <?php echo htmlspecialchars((string)$doc['date'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </td>
                                                        <td style="padding:12px 14px;font-size:13px;color:var(--gray-800);font-weight:600;">
                                                            <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                                        </td>
                                                        <td style="padding:12px 14px;font-size:12px;color:var(--gray-600);">
                                                            <?php echo htmlspecialchars($purposeDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                                        </td>
                                                        <td style="padding:12px 14px;font-size:12px;color:var(--gray-600);">
                                                            <?php echo htmlspecialchars($folder, ENT_QUOTES, 'UTF-8'); ?>
                                                        </td>
                                                        <td style="padding:12px 14px;font-size:12px;color:var(--gray-600);white-space:nowrap;">
                                                            <?php echo htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8'); ?>
                                                        </td>
                                                        <td style="padding:12px 14px;text-align:right;">
                                                            <a class="btn btn-s sm" href="<?php echo htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8'); ?>">Download</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr id="noMatchesRow" style="display:none;">
                                                    <td colspan="6" style="padding:16px 14px;color:var(--gray-500);">No matching records.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($documents)): ?>
                                    <div class="pager">
                                        <div class="pager-info" id="pagerInfo">Showing 0 of 0</div>
                                        <div class="pager-controls">
                                            <div class="pager-buttons" id="pageButtons"></div>
                                            <button class="btn btn-s sm" type="button" id="pagePrev">Prev</button>
                                            <button class="btn btn-s sm" type="button" id="pageNext">Next</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-backdrop" aria-hidden="true"></div>
    </div>

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
            function tick() {
                var el = document.getElementById('topbarDateTimeEmployee');
                if (!el) return;
                var d = new Date();
                var s = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
                el.textContent = s;
            }
            tick();
            setInterval(tick, 1000 * 30);
        })();
    </script>

    <script>
        (function () {
            var btn = document.getElementById('resetFilters');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var q = document.getElementById('q');
                var p = document.getElementById('purpose');
                var df = document.getElementById('date_from');
                var dt = document.getElementById('date_to');
                if (q) q.value = '';
                if (p) p.value = '';
                if (df) df.value = '';
                if (dt) dt.value = '';
                if (typeof window.applyDocFilters === 'function') {
                    window.applyDocFilters();
                }
            });
        })();
    </script>

    <script>
        (function () {
            var q = document.getElementById('q');
            var purpose = document.getElementById('purpose');
            var df = document.getElementById('date_from');
            var dt = document.getElementById('date_to');
            var pageSizeSelect = document.getElementById('pageSize');
            var pagePrev = document.getElementById('pagePrev');
            var pageNext = document.getElementById('pageNext');
            var pageButtons = document.getElementById('pageButtons');
            var pagerInfo = document.getElementById('pagerInfo');
            var noMatchesRow = document.getElementById('noMatchesRow');
            var rows = Array.prototype.slice.call(document.querySelectorAll('[data-doc-row]'));
            var currentPage = 1;

            function normalize(value) {
                return (value || '').toString().toLowerCase().trim();
            }

            function toDate(value) {
                if (!value) return null;
                var parts = value.split('-');
                if (parts.length !== 3) return null;
                var y = parseInt(parts[0], 10);
                var m = parseInt(parts[1], 10) - 1;
                var d = parseInt(parts[2], 10);
                if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
                return new Date(y, m, d, 0, 0, 0, 0);
            }

            function matchesDate(rowDate, fromDate, toDateValue) {
                if (!rowDate) return true;
                var current = toDate(rowDate);
                if (!current) return true;
                if (fromDate && current < fromDate) return false;
                if (toDateValue && current > toDateValue) return false;
                return true;
            }

            function getPageSize() {
                var val = pageSizeSelect ? parseInt(pageSizeSelect.value, 10) : 10;
                return isNaN(val) || val <= 0 ? 10 : val;
            }

            function renderPager(totalRows, totalPages) {
                if (!pagerInfo || !pageButtons || !pagePrev || !pageNext) return;
                var pageSize = getPageSize();
                var start = totalRows === 0 ? 0 : (currentPage - 1) * pageSize + 1;
                var end = Math.min(currentPage * pageSize, totalRows);
                pagerInfo.textContent = totalRows === 0
                    ? 'Showing 0 of 0'
                    : 'Showing ' + start + '-' + end + ' of ' + totalRows;

                pagePrev.disabled = currentPage <= 1;
                pageNext.disabled = currentPage >= totalPages;

                pageButtons.innerHTML = '';
                if (totalPages <= 1) return;

                var maxButtons = 7;
                var startPage = 1;
                var endPage = totalPages;

                if (totalPages > maxButtons) {
                    startPage = Math.max(1, currentPage - 2);
                    endPage = Math.min(totalPages, startPage + maxButtons - 1);
                    if (endPage - startPage < maxButtons - 1) {
                        startPage = Math.max(1, endPage - maxButtons + 1);
                    }
                }

                function addPageButton(page) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-s sm';
                    btn.textContent = String(page);
                    if (page === currentPage) {
                        btn.disabled = true;
                    }
                    btn.addEventListener('click', function () {
                        currentPage = page;
                        applyFilters();
                    });
                    pageButtons.appendChild(btn);
                }

                function addEllipsis() {
                    var span = document.createElement('span');
                    span.className = 'pager-ellipsis';
                    span.textContent = '...';
                    pageButtons.appendChild(span);
                }

                if (startPage > 1) {
                    addPageButton(1);
                    if (startPage > 2) {
                        addEllipsis();
                    }
                }

                for (var p = startPage; p <= endPage; p++) {
                    addPageButton(p);
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        addEllipsis();
                    }
                    addPageButton(totalPages);
                }
            }

            function applyFilters() {
                var query = normalize(q ? q.value : '');
                var purposeVal = normalize(purpose ? purpose.value : '');
                var fromDate = toDate(df ? df.value : '');
                var toDateValue = toDate(dt ? dt.value : '');
                var pageSize = getPageSize();
                var matches = [];

                rows.forEach(function (row) {
                    var search = normalize(row.getAttribute('data-search'));
                    var rowType = normalize(row.getAttribute('data-type'));
                    var rowDate = row.getAttribute('data-date') || '';

                    var matchesQuery = !query || search.indexOf(query) !== -1;
                    var matchesPurpose = true;

                    if (purposeVal) {
                        matchesPurpose = rowType === purposeVal;
                    }

                    var matchesRange = matchesDate(rowDate, fromDate, toDateValue);
                    if (matchesQuery && matchesPurpose && matchesRange) {
                        matches.push(row);
                    } else {
                        row.style.display = 'none';
                    }
                });

                var totalRows = matches.length;
                var totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
                if (currentPage > totalPages) {
                    currentPage = totalPages;
                }

                matches.forEach(function (row, index) {
                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;
                    row.style.display = index >= startIndex && index < endIndex ? '' : 'none';
                });

                if (noMatchesRow) {
                    noMatchesRow.style.display = totalRows === 0 ? '' : 'none';
                }

                renderPager(totalRows, totalPages);
            }

            window.applyDocFilters = applyFilters;

            if (q) q.addEventListener('input', applyFilters);
            if (purpose) purpose.addEventListener('change', function () {
                currentPage = 1;
                applyFilters();
            });
            if (df) df.addEventListener('change', function () {
                currentPage = 1;
                applyFilters();
            });
            if (dt) dt.addEventListener('change', function () {
                currentPage = 1;
                applyFilters();
            });

            if (pageSizeSelect) {
                pageSizeSelect.addEventListener('change', function () {
                    currentPage = 1;
                    applyFilters();
                });
            }

            if (pagePrev) {
                pagePrev.addEventListener('click', function () {
                    if (currentPage > 1) {
                        currentPage -= 1;
                        applyFilters();
                    }
                });
            }

            if (pageNext) {
                pageNext.addEventListener('click', function () {
                    currentPage += 1;
                    applyFilters();
                });
            }

            applyFilters();
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
