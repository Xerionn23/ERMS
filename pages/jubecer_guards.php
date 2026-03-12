<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('admin');
require_company();

if ((string)($_SESSION['company'] ?? '') !== 'jubecer') {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'add_guard') {
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $middleName = trim((string)($_POST['middle_name'] ?? ''));
        $suffix = trim((string)($_POST['suffix'] ?? ''));
        $birthdate = trim((string)($_POST['birthdate'] ?? ''));
        $ageRaw = trim((string)($_POST['age'] ?? ''));
        $agency = trim((string)($_POST['agency'] ?? ''));
        $contactNo = trim((string)($_POST['contact_no'] ?? ''));

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
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO guards (guard_no, last_name, first_name, middle_name, suffix, birthdate, age, agency, full_name, contact_no)\n'
                    . 'VALUES (NULL, :last_name, :first_name, :middle_name, :suffix, :birthdate, :age, :agency, :full_name, :contact_no)'
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
                ]);

                $newId = (int)$pdo->lastInsertId();
                $guardNo = 'JG-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);

                $upd = $pdo->prepare('UPDATE guards SET guard_no = :guard_no WHERE id = :id');
                $upd->execute(['guard_no' => $guardNo, 'id' => $newId]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }

        header('Location: jubecer_guards.php');
        exit;
    }
}

$reqStmt = $pdo->query("SELECT id, code, name, expires, is_required FROM requirement_types WHERE is_required = 1 ORDER BY id");
$requirementTypes = $reqStmt->fetchAll();

$requiredCount = count($requirementTypes);

$listSql = "
SELECT
    g.id,
    g.guard_no,
    g.full_name,
    g.agency,
    g.contact_no,
    g.status,
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
ORDER BY g.last_name ASC, g.first_name ASC, g.full_name ASC
";

$guards = $pdo->query($listSql)->fetchAll();

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

$summary = $pdo->query($summarySql)->fetch() ?: [
    'total_guards' => 0,
    'guards_with_missing' => 0,
    'guards_with_expired_license' => 0,
    'guards_with_expiring_license' => 0,
];

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
    <title>Guards | ERMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
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
                <div class="profile-dropdown" id="profileDropdownJubecer">
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
                                <div class="page-title-main">Guards</div>
                                <div class="page-title-sub">Requirements &amp; License monitoring</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="datetime-pill" aria-label="Current date and time">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 8v5l3 2" />
                            <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span id="topbarDateTimeJubecer">--</span>
                    </div>
                </div>
            </header>

            <main class="content">
                <section class="section">
                    <div class="section-title">Overview</div>
                    <div class="cards">
                        <div class="card">
                            <div class="card-icon blue"></div>
                            <div class="card-body">
                                <div class="card-label">Total Guards</div>
                                <div class="card-value"><?php echo (int)$summary['total_guards']; ?></div>
                                <div class="card-footnote">All records</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon orange"></div>
                            <div class="card-body">
                                <div class="card-label">With Missing</div>
                                <div class="card-value"><?php echo (int)$summary['guards_with_missing']; ?></div>
                                <div class="card-footnote">SSS/PAG-IBIG/PhilHealth/License</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon purple"></div>
                            <div class="card-body">
                                <div class="card-label">License Expiring (6 mo)</div>
                                <div class="card-value"><?php echo (int)$summary['guards_with_expiring_license']; ?></div>
                                <div class="card-footnote">Needs renewal soon</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon green"></div>
                            <div class="card-body">
                                <div class="card-label">License Expired</div>
                                <div class="card-value"><?php echo (int)$summary['guards_with_expired_license']; ?></div>
                                <div class="card-footnote">Renew immediately</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <div class="section-head" id="add-guard">
                        <div class="section-title">Add Guard</div>
                    </div>
                    <div class="panel">
                        <form class="form" method="post" action="jubecer_guards.php">
                            <input type="hidden" name="action" value="add_guard" />
                            <div class="form-grid">
                                <div class="field">
                                    <label class="label" for="last_name">Last Name</label>
                                    <input class="input" id="last_name" name="last_name" type="text" required />
                                </div>
                                <div class="field">
                                    <label class="label" for="first_name">First Name</label>
                                    <input class="input" id="first_name" name="first_name" type="text" required />
                                </div>
                                <div class="field">
                                    <label class="label" for="middle_name">Middle Name</label>
                                    <input class="input" id="middle_name" name="middle_name" type="text" />
                                </div>
                                <div class="field">
                                    <label class="label" for="suffix">Suffix</label>
                                    <input class="input" id="suffix" name="suffix" type="text" />
                                </div>
                                <div class="field">
                                    <label class="label" for="birthdate">Birthdate</label>
                                    <input class="input" id="birthdate" name="birthdate" type="date" />
                                </div>
                                <div class="field">
                                    <label class="label" for="age">Age</label>
                                    <input class="input" id="age" name="age" type="number" min="0" max="130" />
                                </div>
                                <div class="field">
                                    <label class="label" for="agency">Agency</label>
                                    <input class="input" id="agency" name="agency" type="text" />
                                </div>
                                <div class="field">
                                    <label class="label" for="contact_no">Contact No</label>
                                    <input class="input" id="contact_no" name="contact_no" type="text" />
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="primary-btn" type="submit">Save Guard</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="section">
                    <div class="section-head">
                        <div class="section-title">Guard List</div>
                        <div class="row-sub">Required items: <?php echo (int)$requiredCount; ?></div>
                    </div>

                    <div class="panel">
                        <?php if (empty($guards)): ?>
                            <div class="row">
                                <div class="chip">J</div>
                                <div class="row-text">
                                    <div class="row-main">No guards yet</div>
                                    <div class="row-sub">Add your first guard above.</div>
                                </div>
                                <div class="badge">Ready</div>
                            </div>
                        <?php else: ?>
                            <div class="req-toolbar" style="margin-bottom: 14px;">
                                <div class="req-chips">
                                    <div class="field" style="margin: 0; min-width: 260px;">
                                        <input class="input" id="guardSearch" type="text" placeholder="Search guard no, name, agency, contact..." autocomplete="off" />
                                    </div>
                                    <div class="field" style="margin: 0; min-width: 180px;">
                                        <select class="input" id="guardStatusFilter">
                                            <option value="">All Status</option>
                                            <option value="Missing">Missing</option>
                                            <option value="Expired">Expired</option>
                                            <option value="Expiring">Expiring</option>
                                            <option value="Valid">Valid</option>
                                        </select>
                                    </div>
                                    <div class="field" style="margin: 0; min-width: 200px;">
                                        <select class="input" id="guardAgencyFilter">
                                            <option value="">All Agencies</option>
                                        </select>
                                    </div>
                                    <div class="field" style="margin: 0; min-width: 160px;">
                                        <select class="input" id="guardPageSize" aria-label="Rows per page">
                                            <option value="5">5 rows</option>
                                            <option value="10" selected>10 rows</option>
                                            <option value="25">25 rows</option>
                                            <option value="50">50 rows</option>
                                        </select>
                                    </div>
                                    <button class="secondary-btn btn-sm" type="button" id="guardClearFilters">Clear</button>
                                </div>
                            </div>
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Guard No</th>
                                            <th>Name</th>
                                            <th>Agency</th>
                                            <th>Contact</th>
                                            <th>Missing</th>
                                            <th>License Status</th>
                                            <th>Expiry Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($guards as $g): ?>
                                            <?php
                                                $missing = (int)($g['missing_count'] ?? 0);
                                                $expired = (int)($g['expired_license'] ?? 0);
                                                $expiring = (int)($g['expiring_license'] ?? 0);
                                                $expiryDate = (string)($g['license_expiry_date'] ?? '');

                                                $badgeText = 'OK';
                                                if ($expired > 0) {
                                                    $badgeText = 'Expired';
                                                } elseif ($expiring > 0) {
                                                    $badgeText = 'Expiring';
                                                } elseif ($missing > 0) {
                                                    $badgeText = 'Missing';
                                                }

                                                $filterStatus = $badgeText === 'OK' ? 'Valid' : $badgeText;

                                                $badgeClass = 'badge--valid';
                                                if ($filterStatus === 'Missing') {
                                                    $badgeClass = 'badge--missing';
                                                } elseif ($filterStatus === 'Expired') {
                                                    $badgeClass = 'badge--expired';
                                                } elseif ($filterStatus === 'Expiring') {
                                                    $badgeClass = 'badge--expiring';
                                                }
                                                $filterAgency = trim((string)($g['agency'] ?? ''));
                                                $filterSearch = strtolower(trim(implode(' ', [
                                                    (string)($g['guard_no'] ?? ''),
                                                    (string)($g['full_name'] ?? ''),
                                                    $filterAgency,
                                                    (string)($g['contact_no'] ?? ''),
                                                ])));
                                            ?>
                                            <tr data-guard-row
                                                data-search="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-agency="<?php echo htmlspecialchars($filterAgency, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td><?php echo htmlspecialchars((string)$g['guard_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <a class="table-link" href="jubecer_guard_profile.php?id=<?php echo (int)$g['id']; ?>">
                                                        <?php echo htmlspecialchars((string)$g['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars((string)($g['agency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string)($g['contact_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $missing; ?></td>
                                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><?php echo htmlspecialchars($expiryDate, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <button class="primary-btn btn-sm" type="button" data-guard-open="<?php echo (int)$g['id']; ?>">Open</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="req-toolbar" style="margin-top: 12px;">
                                <div class="req-chips" style="justify-content: space-between; width: 100%;">
                                    <div class="row-sub" id="guardPagerSummary" aria-live="polite">&nbsp;</div>
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                                        <button class="secondary-btn btn-sm" type="button" id="guardPrevPage">Prev</button>
                                        <div id="guardPageNumbers" style="display: flex; gap: 6px; flex-wrap: wrap;"></div>
                                        <button class="secondary-btn btn-sm" type="button" id="guardNextPage">Next</button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>

        <div class="backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    </div>

    <div class="modal" id="guardModal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__dialog" role="dialog" aria-modal="true" aria-label="Guard Requirements">
            <div class="modal__header">
                <div class="modal__title">Guard Requirements</div>
                <button class="modal__close" type="button" aria-label="Close" data-modal-close>×</button>
            </div>
            <div class="modal__body">
                <iframe class="modal__frame" id="guardModalFrame" title="Guard Profile"></iframe>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var dropdown = document.getElementById('profileDropdownJubecer');
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

    <script>
        (function () {
            var search = document.getElementById('guardSearch');
            var statusFilter = document.getElementById('guardStatusFilter');
            var agencyFilter = document.getElementById('guardAgencyFilter');
            var pageSizeEl = document.getElementById('guardPageSize');
            var clearBtn = document.getElementById('guardClearFilters');

            var prevBtn = document.getElementById('guardPrevPage');
            var nextBtn = document.getElementById('guardNextPage');
            var pageNumbers = document.getElementById('guardPageNumbers');
            var summaryEl = document.getElementById('guardPagerSummary');

            var table = document.querySelector('.data-table');
            if (!table) return;

            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-guard-row]'));
            if (!rows.length) return;

            function normalize(v) {
                return String(v || '').trim();
            }

            var currentPage = 1;
            var pageSize = 10;

            try {
                var saved = window.localStorage ? window.localStorage.getItem('guards_page_size') : '';
                var savedInt = parseInt(String(saved || ''), 10);
                if (!isNaN(savedInt) && savedInt > 0) {
                    pageSize = savedInt;
                }
            } catch (e) {}

            if (pageSizeEl) {
                var ps = parseInt(String(pageSizeEl.value || ''), 10);
                if (!isNaN(ps) && ps > 0) {
                    pageSize = ps;
                }
                pageSizeEl.value = String(pageSize);
            }

            function getFilteredRows() {
                var q = normalize(search && search.value).toLowerCase();
                var status = normalize(statusFilter && statusFilter.value);
                var agency = normalize(agencyFilter && agencyFilter.value);

                return rows.filter(function (row) {
                    var rowSearch = String(row.getAttribute('data-search') || '');
                    var rowStatus = normalize(row.getAttribute('data-status'));
                    var rowAgency = normalize(row.getAttribute('data-agency'));

                    if (q && rowSearch.indexOf(q) === -1) return false;
                    if (status && rowStatus !== status) return false;
                    if (agency && rowAgency !== agency) return false;
                    return true;
                });
            }

            function renderPagination(filtered) {
                var total = filtered.length;
                var totalPages = Math.max(1, Math.ceil(total / pageSize));
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                var startIndex = (currentPage - 1) * pageSize;
                var endIndex = Math.min(startIndex + pageSize, total);

                rows.forEach(function (r) {
                    r.style.display = 'none';
                });

                for (var i = startIndex; i < endIndex; i++) {
                    if (filtered[i]) filtered[i].style.display = '';
                }

                if (summaryEl) {
                    if (total === 0) {
                        summaryEl.textContent = 'No results';
                    } else {
                        summaryEl.textContent = 'Showing ' + String(startIndex + 1) + '–' + String(endIndex) + ' of ' + String(total);
                    }
                }

                if (prevBtn) prevBtn.disabled = currentPage <= 1;
                if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

                if (pageNumbers) {
                    pageNumbers.innerHTML = '';

                    var maxButtons = 7;
                    var half = Math.floor(maxButtons / 2);
                    var from = Math.max(1, currentPage - half);
                    var to = Math.min(totalPages, from + maxButtons - 1);
                    from = Math.max(1, to - maxButtons + 1);

                    function addPageButton(p) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = (p === currentPage) ? 'primary-btn btn-sm' : 'secondary-btn btn-sm';
                        btn.textContent = String(p);
                        btn.addEventListener('click', function () {
                            currentPage = p;
                            applyAndRender();
                        });
                        pageNumbers.appendChild(btn);
                    }

                    if (from > 1) {
                        addPageButton(1);
                        if (from > 2) {
                            var dots1 = document.createElement('span');
                            dots1.className = 'row-sub';
                            dots1.textContent = '…';
                            pageNumbers.appendChild(dots1);
                        }
                    }

                    for (var p = from; p <= to; p++) {
                        addPageButton(p);
                    }

                    if (to < totalPages) {
                        if (to < totalPages - 1) {
                            var dots2 = document.createElement('span');
                            dots2.className = 'row-sub';
                            dots2.textContent = '…';
                            pageNumbers.appendChild(dots2);
                        }
                        addPageButton(totalPages);
                    }
                }
            }

            function applyAndRender() {
                var filtered = getFilteredRows();
                renderPagination(filtered);
            }

            if (agencyFilter) {
                var agencies = {};
                rows.forEach(function (row) {
                    var a = normalize(row.getAttribute('data-agency'));
                    if (!a) return;
                    agencies[a] = true;
                });

                Object.keys(agencies)
                    .sort(function (a, b) { return a.localeCompare(b); })
                    .forEach(function (a) {
                        var opt = document.createElement('option');
                        opt.value = a;
                        opt.textContent = a;
                        agencyFilter.appendChild(opt);
                    });
            }

            if (search) search.addEventListener('input', function () {
                currentPage = 1;
                applyAndRender();
            });
            if (statusFilter) statusFilter.addEventListener('change', function () {
                currentPage = 1;
                applyAndRender();
            });
            if (agencyFilter) agencyFilter.addEventListener('change', function () {
                currentPage = 1;
                applyAndRender();
            });

            if (pageSizeEl) {
                pageSizeEl.addEventListener('change', function () {
                    var v = parseInt(String(pageSizeEl.value || ''), 10);
                    if (!isNaN(v) && v > 0) {
                        pageSize = v;
                        currentPage = 1;
                        try {
                            if (window.localStorage) window.localStorage.setItem('guards_page_size', String(pageSize));
                        } catch (e) {}
                        applyAndRender();
                    }
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    currentPage = Math.max(1, currentPage - 1);
                    applyAndRender();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    currentPage = currentPage + 1;
                    applyAndRender();
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (search) search.value = '';
                    if (statusFilter) statusFilter.value = '';
                    if (agencyFilter) agencyFilter.value = '';
                    currentPage = 1;
                    applyAndRender();
                    if (search) search.focus();
                });
            }

            applyAndRender();
        })();
    </script>

    <script>
        (function () {
            var modal = document.getElementById('guardModal');
            var frame = document.getElementById('guardModalFrame');
            if (!modal || !frame) return;

            function openModal(guardId) {
                frame.src = 'jubecer_guard_profile.php?id=' + encodeURIComponent(String(guardId)) + '&embed=1';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                frame.src = '';
                window.location.reload();
            }

            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('[data-guard-open]');
                if (btn) {
                    var id = btn.getAttribute('data-guard-open');
                    if (id) {
                        openModal(id);
                    }
                    return;
                }

                if (e.target && e.target.closest && e.target.closest('[data-modal-close]')) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (!modal.classList.contains('is-open')) return;
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        })();
    </script>

    <script>
        (function () {
            var el = document.getElementById('topbarDateTimeJubecer');
            if (!el) return;

            function pad(n) {
                return String(n).padStart(2, '0');
            }

            function render() {
                var d = new Date();
                var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                var hours = d.getHours();
                var ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                if (hours === 0) hours = 12;

                var text =
                    days[d.getDay()] + ', ' +
                    months[d.getMonth()] + ' ' +
                    pad(d.getDate()) + ' • ' +
                    hours + ':' +
                    pad(d.getMinutes()) + ' ' +
                    ampm;

                el.textContent = text;
            }

            render();
            setInterval(render, 1000);
        })();
    </script>
</body>
</html>
