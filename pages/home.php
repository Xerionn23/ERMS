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
                                <div class="card-icon blue"></div>
                                <div class="card-body">
                                    <div class="card-label">Total Employees</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon green"></div>
                                <div class="card-body">
                                    <div class="card-label">Pending Requests</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon orange"></div>
                                <div class="card-body">
                                    <div class="card-label">Completed Today</div>
                                    <div class="card-value">--</div>
                                    <div class="card-footnote">Placeholder</div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-icon purple"></div>
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
                        <div class="section-title">Jubecer</div>
                        <div class="panel">
                            <div class="row">
                                <div class="chip">J</div>
                                <div class="row-text">
                                    <div class="row-main">Dashboard is not set up yet.</div>
                                    <div class="row-sub">Add your Jubecer logo and modules.</div>
                                </div>
                                <div class="badge">Active</div>
                            </div>
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
