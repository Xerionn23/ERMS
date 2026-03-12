<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('admin');
require_company();

$companyLabel = $_SESSION['company'] === 'brainmaster' ? 'Brain Master' : 'Jubecer';
$isBrainMaster = $_SESSION['company'] === 'brainmaster';
$userName = (string)($_SESSION['user_name'] ?? 'User');
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
if ($userInitials === '') {
    $userInitials = 'U';
}

$jubecerLicenseAlerts = [];
$jubecerSummary = [
    'total_guards' => 0,
    'guards_with_missing' => 0,
    'guards_with_expired_license' => 0,
    'guards_with_expiring_license' => 0,
];
if (!$isBrainMaster) {
    require_once __DIR__ . '/../includes/db.php';
    try {
        $pdo = db();

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
        $sumStmt = $pdo->query($summarySql);
        $jubecerSummaryRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($jubecerSummaryRow)) {
            $jubecerSummary = array_merge($jubecerSummary, $jubecerSummaryRow);
        }

        $stmt = $pdo->query(
            "SELECT\n".
            "  g.id AS guard_id,\n".
            "  g.full_name,\n".
            "  g.guard_no,\n".
            "  g.agency,\n".
            "  gr.expiry_date,\n".
            "  DATEDIFF(gr.expiry_date, CURDATE()) AS days_until_expiry,\n".
            "  CASE\n".
            "    WHEN gr.expiry_date < CURDATE() THEN 'Expired'\n".
            "    WHEN gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 'Expiring'\n".
            "    ELSE 'Valid'\n".
            "  END AS alert_status\n".
            "FROM guards g\n".
            "JOIN requirement_types rt ON rt.code = 'SECURITY_LICENSE'\n".
            "JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n".
            "WHERE gr.expiry_date IS NOT NULL\n".
            "  AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)\n".
            "ORDER BY (gr.expiry_date < CURDATE()) DESC, gr.expiry_date ASC, g.full_name ASC\n".
            "LIMIT 20"
        );
        $jubecerLicenseAlerts = $stmt->fetchAll();
    } catch (Throwable $e) {
        $jubecerLicenseAlerts = [];
        $jubecerSummary = [
            'total_guards' => 0,
            'guards_with_missing' => 0,
            'guards_with_expired_license' => 0,
            'guards_with_expiring_license' => 0,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Home | ERMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
    <div class="layout">
        <aside class="sidebar" aria-label="Sidebar Navigation">
            <div class="sidebar-top">
                <div class="brand">
                    <div class="brand-logo" aria-hidden="true">
                        <?php if ($isBrainMaster): ?>
                            <img src="../assets/img/brainmaster.jpg" alt="Brain Master" />
                        <?php else: ?>
                            J
                        <?php endif; ?>
                    </div>
                    <div class="brand-text">
                        <div class="brand-title">ERMS</div>
                        <div class="brand-subtitle"><?php echo htmlspecialchars($companyLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div> 

            <nav class="nav">
                <a class="nav-item is-active" href="home.php">
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
                <?php if (!$isBrainMaster): ?>
                    <a class="nav-item" href="jubecer_guards.php">
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
                <?php else: ?>
                    <a class="nav-item" href="#">
                        <span class="nav-item-content">
                            <span class="nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M20 21a8 8 0 1 0-16 0" />
                                    <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                </svg>
                            </span>
                            <span class="nav-label">User Management</span>
                        </span>
                    </a>
                    <a class="nav-item" href="#">
                        <span class="nav-item-content">
                            <span class="nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M20 21a8 8 0 1 0-16 0" />
                                    <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                </svg>
                            </span>
                            <span class="nav-label">Patient Management</span>
                        </span>
                    </a>
                    <a class="nav-item" href="#">
                        <span class="nav-item-content">
                            <span class="nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 19V5" />
                                    <path d="M4 19h16" />
                                    <path d="M8 15l3-4 3 2 4-6" />
                                </svg>
                            </span>
                            <span class="nav-label">Reports &amp; Analytics</span>
                        </span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-bottom">
                <div class="profile-dropdown" id="profileDropdown">
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
                                    <path d="M3 13h8V3H3v10Z" />
                                    <path d="M13 21h8V11h-8v10Z" />
                                    <path d="M13 3h8v6h-8V3Z" />
                                    <path d="M3 17h8v4H3v-4Z" />
                                </svg>
                            </div>
                            <div class="page-title-text">
                                <div class="page-title-main">Admin Dashboard</div>
                                <div class="page-title-sub">Comprehensive system overview</div>
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
                        <span id="topbarDateTime">--</span>
                    </div>
                </div>
            </header>

            <main class="content">
                <?php if ($isBrainMaster): ?>
                    <section class="section">
                        <div class="section-title">Overview</div>
                        <div class="cards">
                            <div class="card">
                                <div class="card-icon blue" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 13h8V3H3v10Z" />
                                        <path d="M13 21h8V11h-8v10Z" />
                                        <path d="M13 3h8v6h-8V3Z" />
                                        <path d="M3 17h8v4H3v-4Z" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">Total Employees</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M8 12h8" />
                                        <path d="M12 8v8" />
                                        <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">Pending Requests</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon orange" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 19V5" />
                                        <path d="M4 19h16" />
                                        <path d="M8 15l3-4 3 2 4-6" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">Completed Today</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon purple" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                        <path d="M10 3h4" />
                                        <path d="M12 3v3" />
                                        <path d="M8.5 6.5A7 7 0 1 0 15.5 6.5" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">Alerts</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="section">
                        <div class="section-head">
                            <div class="section-title">Recent Activity</div>
                            <a class="section-link" href="#">View All</a>
                        </div>
                        <div class="panel">
                            <div class="row">
                                <div class="chip">BM</div>
                                <div class="row-text">
                                    <div class="row-main">Dashboard initialized</div>
                                    <div class="row-sub">System • Recently</div>
                                </div>
                                <div class="badge">Active</div>
                            </div>
                            <div class="row">
                                <div class="chip purple">HR</div>
                                <div class="row-text">
                                    <div class="row-main">Employee module pending</div>
                                    <div class="row-sub">Setup • Soon</div>
                                </div>
                                <div class="badge">Active</div>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="section">
                        <div class="section-head">
                            <div>
                                <div class="section-title">Jubecer</div>
                                <div class="row-sub">Guard compliance &amp; license monitoring</div>
                            </div>
                            <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                                <a class="secondary-btn btn-sm" href="jubecer_guards.php#add-guard">Add Guard</a>
                                <a class="primary-btn btn-sm" href="jubecer_guards.php">Open Guards</a>
                            </div>
                        </div>

                        <div class="cards">
                            <div class="card">
                                <div class="card-icon blue" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 21a8 8 0 1 0-16 0" />
                                        <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">Total Guards</div>
                                    <div class="card-value"><?php echo (int)($jubecerSummary['total_guards'] ?? 0); ?></div>
                                    <div class="card-footnote">All records</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon orange" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                        <path d="M10 3h4" />
                                        <path d="M12 3v3" />
                                        <path d="M8.5 6.5A7 7 0 1 0 15.5 6.5" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">With Missing Requirements</div>
                                    <div class="card-value"><?php echo (int)($jubecerSummary['guards_with_missing'] ?? 0); ?></div>
                                    <div class="card-footnote">SSS/PAG-IBIG/PhilHealth/License</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon purple" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 8v5l3 2" />
                                        <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">License Expiring (6 mo)</div>
                                    <div class="card-value"><?php echo (int)($jubecerSummary['guards_with_expiring_license'] ?? 0); ?></div>
                                    <div class="card-footnote">Needs renewal soon</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 8v5l3 2" />
                                        <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        <path d="M15.5 15.5l3 3" />
                                    </svg>
                                </div>
                                <div class="card-body">
                                    <div class="card-label">License Expired</div>
                                    <div class="card-value"><?php echo (int)($jubecerSummary['guards_with_expired_license'] ?? 0); ?></div>
                                    <div class="card-footnote">Renew immediately</div>
                                </div>
                            </div>
                        </div>

                        <div class="section-head" style="margin-top: 14px;">
                            <div class="section-title">License Alerts</div>
                            <a class="section-link" href="jubecer_guards.php">Open list</a>
                        </div>

                        <div class="panel">
                            <?php if (empty($jubecerLicenseAlerts)): ?>
                                <div class="row">
                                    <div class="chip">J</div>
                                    <div class="row-text">
                                        <div class="row-main">No license alerts</div>
                                        <div class="row-sub">No guards have an expired/expiring Security License (within 6 months).</div>
                                    </div>
                                    <div class="badge badge--valid">Valid</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($jubecerLicenseAlerts as $a): ?>
                                    <?php
                                        $alertStatus = (string)($a['alert_status'] ?? '');
                                        $alertBadgeClass = 'badge--valid';
                                        if ($alertStatus === 'Expired') {
                                            $alertBadgeClass = 'badge--expired';
                                        } elseif ($alertStatus === 'Expiring') {
                                            $alertBadgeClass = 'badge--expiring';
                                        }

                                        $agency = trim((string)($a['agency'] ?? ''));
                                        $daysUntil = (int)($a['days_until_expiry'] ?? 0);
                                        $daysLabel = '';
                                        if ($alertStatus === 'Expired') {
                                            $daysLabel = 'Expired ' . (string)abs($daysUntil) . ' day' . (abs($daysUntil) === 1 ? '' : 's') . ' ago';
                                        } elseif ($alertStatus === 'Expiring') {
                                            $daysLabel = 'In ' . (string)max(0, $daysUntil) . ' day' . (max(0, $daysUntil) === 1 ? '' : 's');
                                        }
                                    ?>
                                    <div class="row">
                                        <div class="chip">G</div>
                                        <div class="row-text">
                                            <div class="row-main">
                                                <a style="text-decoration:none" href="jubecer_guard_profile.php?id=<?php echo (int)$a['guard_id']; ?>">
                                                    <?php echo htmlspecialchars((string)$a['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </div>
                                            <div class="row-sub">
                                                #<?php echo htmlspecialchars((string)$a['guard_no'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($agency !== ''): ?>
                                                    • <?php echo htmlspecialchars($agency, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                                • Expiry: <?php echo htmlspecialchars((string)$a['expiry_date'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($daysLabel !== ''): ?>
                                                    • <?php echo htmlspecialchars($daysLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="badge <?php echo $alertBadgeClass; ?>"><?php echo htmlspecialchars($alertStatus, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>

        <div class="backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    </div>

    <script>
        (function () {
            var dropdown = document.getElementById('profileDropdown');
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
            var el = document.getElementById('topbarDateTime');
            if (!el) return;

            function pad(n) {
                return String(n).padStart(2, '0');
            }

            function render() {
                var d = new Date();
                var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                var text =
                    days[d.getDay()] + ', ' +
                    months[d.getMonth()] + ' ' +
                    pad(d.getDate()) + ' • ' +
                    pad(d.getHours()) + ':' +
                    pad(d.getMinutes()) + ':' +
                    pad(d.getSeconds());

                el.textContent = text;
            }

            render();
            setInterval(render, 1000);
        })();
    </script>
</body>
</html>
