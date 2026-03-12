<?php
require_once __DIR__ . '/../includes/guards.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Neuro Documents | ERMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
    <div class="layout">
        <aside class="sidebar" aria-label="Sidebar Navigation">
            <div class="sidebar-top">
                <div class="brand">
                    <div class="brand-logo" aria-hidden="true">
                        <img src="../assets/img/brainmaster.jpg" alt="Brain Master" />
                    </div>
                    <div class="brand-text">
                        <div class="brand-title">ERMS</div>
                        <div class="brand-subtitle">Employee</div>
                    </div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item is-active" href="neuro_documents.php">
                    <span class="nav-item-content">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 3h10" />
                                <path d="M7 7h10" />
                                <path d="M7 11h10" />
                                <path d="M7 15h7" />
                                <path d="M6 3h-1a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1" />
                            </svg>
                        </span>
                        <span class="nav-label">Neuro Documents</span>
                    </span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="profile-dropdown" id="profileDropdownEmployee">
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
                            <div class="profile-role">Employee</div>
                        </div>
                        <span class="profile-chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </div>

                    <div class="profile-menu" role="menu" aria-label="Account actions">
                        <?php if ($role === 'admin'): ?>
                            <a class="profile-menu-item" role="menuitem" href="../auth/switch_company.php">
                                <span class="profile-menu-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M21 12a9 9 0 1 1-3.03-6.72" />
                                        <path d="M21 3v6h-6" />
                                    </svg>
                                </span>
                                Switch Company
                            </a>
                        <?php endif; ?>
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
                                    <path d="M7 3h10" />
                                    <path d="M7 7h10" />
                                    <path d="M7 11h10" />
                                    <path d="M7 15h7" />
                                    <path d="M6 3h-1a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1" />
                                </svg>
                            </div>
                            <div class="page-title-text">
                                <div class="page-title-main">Neuro Documents</div>
                                <div class="page-title-sub">Generated documents</div>
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
                        <span id="topbarDateTimeEmployee">--</span>
                    </div>
                </div>
            </header>

            <main class="content">
                <section class="section">
                    <div class="section-title">Generate Neuro Document</div>
                    <div class="panel">
                        <form class="form" id="neuroDocForm" method="post" action="../auth/generate_neuro_document.php" target="docxDownloadFrame">
                            <div class="field" style="margin-bottom: 16px;">
                                <label class="label" for="folder_name">Folder Name</label>
                                <input class="input" id="folder_name" name="folder_name" type="text" placeholder="e.g., Batch_January_2026" style="max-width: 400px;" required />
                                <div id="folder_name_status" style="margin-top: 4px; font-size: 12px;"></div>
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label class="label" for="contact_no">Contact No</label>
                                    <input class="input" id="contact_no" name="contact_no" type="text" autocomplete="tel" tabindex="1" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="np_clearance">NP Clearance</label>
                                    <input class="input" id="np_clearance" name="np_clearance" type="text" tabindex="2" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="document_date">Date</label>
                                    <input class="input" id="document_date" name="document_date" type="date" tabindex="3" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="last_name">Last Name</label>
                                    <input class="input" id="last_name" name="last_name" type="text" autocomplete="family-name" tabindex="4" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="first_name">First Name</label>
                                    <input class="input" id="first_name" name="first_name" type="text" autocomplete="given-name" tabindex="5" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="middle_name">Middle Initial</label>
                                    <input class="input" id="middle_name" name="middle_name" type="text" autocomplete="additional-name" inputmode="text" maxlength="1" pattern="[A-Za-z]" placeholder="M" tabindex="6" />
                                </div>

                                <div class="field">
                                    <label class="label" for="suffix">Suffix</label>
                                    <input class="input" id="suffix" name="suffix" type="text" placeholder="Jr., Sr., III" tabindex="7" />
                                </div>

                                <div class="field">
                                    <label class="label" for="age">Age</label>
                                    <input class="input" id="age" name="age" type="number" min="0" max="130" tabindex="8" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="sex">Gender</label>
                                    <input class="input" id="sex" name="sex" type="text" list="sex_list" tabindex="9" required />
                                    <datalist id="sex_list">
                                        <option value="Male">
                                        <option value="Female">
                                    </datalist>
                                </div>

                                <div class="field">
                                    <label class="label" for="civil_status">Civil Status</label>
                                    <input class="input" id="civil_status" name="civil_status" type="text" list="civil_status_list" tabindex="10" required />
                                    <datalist id="civil_status_list">
                                        <option value="Single">
                                        <option value="Married">
                                        <option value="Widowed">
                                        <option value="Separated">
                                        <option value="Annulled">
                                    </datalist>
                                </div>

                                <div class="field form-grid-span-2">
                                    <label class="label" for="home_address">Home Address</label>
                                    <input class="input" id="home_address" name="home_address" type="text" autocomplete="street-address" tabindex="11" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="occupation">Occupation</label>
                                    <input class="input" id="occupation" name="occupation" type="text" tabindex="12" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="position">Position</label>
                                    <input class="input" id="position" name="position" type="text" tabindex="13" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="educational">Educational</label>
                                    <input class="input" id="educational" name="educational" type="text" list="educational_list" tabindex="14" required />
                                    <datalist id="educational_list">
                                        <option value="Elementary Level">
                                        <option value="Elementary Graduate">
                                        <option value="High School Level">
                                        <option value="High School Graduate">
                                        <option value="Vocational">
                                        <option value="College Level">
                                        <option value="College Graduate">
                                        <option value="Master's Degree">
                                        <option value="Doctorate Degree">
                                    </datalist>
                                </div>

                                <div class="field">
                                    <label class="label" for="religion">Religion</label>
                                    <input class="input" id="religion" name="religion" type="text" list="religion_list" tabindex="15" required />
                                    <datalist id="religion_list">
                                        <option value="Roman Catholic">
                                        <option value="Iglesia ni Cristo">
                                        <option value="Born Again Christian">
                                        <option value="Protestant">
                                        <option value="Muslim">
                                        <option value="Buddhist">
                                        <option value="Seventh Day Adventist">
                                        <option value="Jehovah's Witness">
                                        <option value="Aglipayan">
                                        <option value="Baptist">
                                        <option value="Methodist">
                                        <option value="Pentecostal">
                                        <option value="Other">
                                    </datalist>
                                </div>

                                <div class="field">
                                    <label class="label" for="company_requesting_agency">Company/Requesting Agency</label>
                                    <input class="input" id="company_requesting_agency" name="company_requesting_agency" type="text" tabindex="16" required />
                                </div>

                                <div class="field">
                                    <label class="label" for="date_of_birth">Date of Birth</label>
                                    <input class="input" id="date_of_birth" name="date_of_birth" type="date" tabindex="17" required />
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="primary-btn" type="button" id="batchResetBtn">New Batch</button>
                                <button class="primary-btn" type="submit">Generate DOCX</button>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        </div>

        <iframe
            name="docxDownloadFrame"
            id="docxDownloadFrame"
            title="DOCX download"
            style="display:none"
        ></iframe>

        <div class="backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    </div>

    <script>
        (function () {
            var dropdown = document.getElementById('profileDropdownEmployee');
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
            var btn = document.getElementById('batchResetBtn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                if (!form) return;
                form.reset();
                var status = document.getElementById('folder_name_status');
                if (status) {
                    status.textContent = '';
                    status.style.color = '';
                }
            });
        })();
    </script>

    <script>
        (function () {
            var folderInput = document.getElementById('folder_name');
            var statusDiv = document.getElementById('folder_name_status');
            if (!folderInput || !statusDiv) return;

            var debounceTimer = null;

            folderInput.addEventListener('input', function () {
                var val = folderInput.value.trim();

                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                if (val === '') {
                    statusDiv.textContent = '';
                    statusDiv.style.color = '';
                    return;
                }

                debounceTimer = setTimeout(function () {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', '../auth/check_folder.php?folder_name=' + encodeURIComponent(val), true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.exists) {
                                    statusDiv.textContent = 'Folder name already exists. Files will be added to this folder.';
                                    statusDiv.style.color = '#e67e22';
                                } else {
                                    statusDiv.textContent = 'New folder will be created.';
                                    statusDiv.style.color = '#27ae60';
                                }
                            } catch (e) {
                                statusDiv.textContent = '';
                            }
                        }
                    };
                    xhr.send();
                }, 300);
            });
        })();
    </script>

    <script>
        (function () {
            var form = document.getElementById('neuroDocForm');
            var frame = document.getElementById('docxDownloadFrame');
            if (!form || !frame) return;

            var shouldReset = false;
            var resetTimer = null;

            function incrementNpClearance() {
                var el = document.getElementById('np_clearance');
                if (!el) return '';

                var val = el.value.trim();
                if (!val) return '';

                var match = val.match(/(\d{4})([^\d]*)$/);
                if (!match) return val;

                var last4 = match[1];
                var suffix = match[2] || '';

                var num = parseInt(last4, 10);
                if (isNaN(num)) return val;

                num = num + 1;
                var newLast4 = String(num).padStart(4, '0');

                var prefix = val.substring(0, val.length - match[0].length);
                return prefix + newLast4 + suffix;
            }

            function selectiveReset() {
                var keepIds = ['position', 'company_requesting_agency', 'occupation', 'document_date', 'folder_name'];
                var saved = {};

                keepIds.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        saved[id] = el.value;
                    }
                });

                var newNp = incrementNpClearance();

                form.reset();

                keepIds.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el && saved.hasOwnProperty(id)) {
                        el.value = saved[id];
                    }
                });

                var npEl = document.getElementById('np_clearance');
                if (npEl && newNp) {
                    npEl.value = newNp;
                }
            }

            form.addEventListener('submit', function () {
                shouldReset = true;
                if (resetTimer) {
                    clearTimeout(resetTimer);
                }
                resetTimer = setTimeout(function () {
                    if (!shouldReset) return;
                    shouldReset = false;
                    selectiveReset();
                }, 1200);
            });

            frame.addEventListener('load', function () {
                if (!shouldReset) return;

                var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                if (doc && doc.body) {
                    var text = (doc.body.textContent || '').trim();
                    if (text) {
                        shouldReset = false;
                        if (resetTimer) {
                            clearTimeout(resetTimer);
                            resetTimer = null;
                        }
                        alert(text);
                        return;
                    }
                }

                shouldReset = false;
                if (resetTimer) {
                    clearTimeout(resetTimer);
                    resetTimer = null;
                }
                selectiveReset();
            });
        })();
    </script>

    <script>
        (function () {
            var el = document.getElementById('topbarDateTimeEmployee');
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
