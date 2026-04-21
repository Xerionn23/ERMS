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

$roleLabel = $role === 'admin' ? 'Administrator' : 'Employee';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Documents | ERMS</title>
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
                <a class="sb-item on" href="neuro_documents.php" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h10"/><path d="M7 7h10"/><path d="M7 11h10"/><path d="M7 15h7"/><path d="M6 3h-1a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/></svg>
                    <span style="flex:1">Documents</span>
                </a>
                <a class="sb-item" href="attendance.php" style="text-decoration:none;">
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
                        <div class="tb-pg">Documents</div>
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
                        <div class="bm-title" id="docFormTitle">Generate Neuro Document</div>
                        <div class="bm-panel">
                            <form class="bm-form" id="neuroDocForm" method="post" action="../auth/generate_neuro_document.php" target="docxDownloadFrame" enctype="multipart/form-data">
                                <div class="bm-field" style="margin-bottom:16px;">
                                    <label class="bm-label" for="folder_name">Folder Name</label>
                                    <input class="bm-input" id="folder_name" name="folder_name" type="text" placeholder="e.g., Batch_January_2026" style="max-width:400px;" required />
                                    <div id="folder_name_status" style="margin-top:4px;font-size:12px;"></div>
                                </div>

                                <div class="bm-field" style="margin-bottom:16px;max-width:400px;">
                                    <label class="bm-label" for="document_type">Document Type</label>
                                    <select class="bm-input" id="document_type" name="document_type" required>
                                        <option value="neuro" selected>Neuro</option>
                                        <option value="drug_test">Drug Test</option>
                                        <option value="both">Both (Neuro + Drug Test)</option>
                                    </select>
                                    <input type="hidden" id="effective_document_type" name="effective_document_type" value="neuro" />
                                </div>

                                <div id="drugModeNote" style="display:none;margin:0 0 18px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px;background:#f8fafc;color:#334155;max-width:760px;">
                                    <div id="drugModeTitle" style="font-weight:700;margin-bottom:4px;">Drug Test mode</div>
                                    <div id="drugModeText" style="font-size:13px;line-height:1.5;">
                                        AP, CCF, transaction time, report time, and conducted/approved counters are generated automatically. You only fill the shared applicant details.
                                    </div>
                                </div>

                                <div class="bm-field" id="drug_photo_field" style="display:none;margin-bottom:16px;max-width:420px;">
                                    <label class="bm-label" for="drug_photo">Drug Test Photo</label>
                                    <input class="bm-input" id="drug_photo" name="drug_photo" type="file" accept="image/*" capture="environment" />
                                    <div style="display:flex;gap:10px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                                        <button class="btn btn-s" type="button" id="drugOpenCameraBtn">Take Photo</button>
                                        <button class="btn btn-s" type="button" id="drugCaptureBtn" style="display:none;">Capture</button>
                                        <button class="btn btn-s" type="button" id="drugCancelCameraBtn" style="display:none;">Cancel</button>
                                    </div>

                                    <div id="drugPhotoStatus" aria-live="polite" style="margin-top:8px;font-size:12px;color:var(--gray-600);"></div>

                                    <div id="drugPhotoActions" style="margin-top:8px;display:none;">
                                        <button class="btn btn-s" type="button" id="drugOpenLocalhostBtn" style="display:none;">Open using localhost</button>
                                    </div>

                                    <div id="drugCameraWrap" style="display:none;margin-top:10px;border:1.5px solid var(--gray-300);border-radius:12px;padding:10px;background:#fff;">
                                        <video id="drugCameraVideo" playsinline autoplay muted style="width:100%;max-width:380px;border-radius:10px;"></video>
                                        <canvas id="drugCameraCanvas" style="display:none;"></canvas>
                                    </div>

                                    <div id="drugPhotoPreviewWrap" style="display:none;margin-top:10px;border:1.5px solid var(--gray-300);border-radius:12px;padding:10px;background:#fff;">
                                        <div style="font-size:12px;color:var(--gray-600);margin-bottom:8px;">Preview</div>
                                        <img id="drugPhotoPreview" alt="Drug test photo preview" style="width:100%;max-width:220px;border-radius:10px;display:block;" />
                                    </div>
                                    <div style="margin-top:6px;font-size:12px;color:#64748b;">
                                        Upload a photo, or click Take Photo to use the camera.
                                    </div>
                                </div>

                                <div class="bm-grid">
                                    <div class="bm-field" id="contact_no_field">
                                        <label class="bm-label" for="contact_no">Contact No</label>
                                        <input class="bm-input" id="contact_no" name="contact_no" type="text" autocomplete="tel" tabindex="1" required />
                                    </div>

                                    <div class="bm-field" id="np_clearance_field">
                                        <label class="bm-label" for="np_clearance">NP Clearance</label>
                                        <input class="bm-input" id="np_clearance" name="np_clearance" type="text" tabindex="2" required />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="document_date" id="document_date_label">Date</label>
                                        <input class="bm-input" id="document_date" name="document_date" type="date" tabindex="3" required />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="last_name">Last Name</label>
                                        <input class="bm-input" id="last_name" name="last_name" type="text" autocomplete="family-name" tabindex="4" required />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="first_name">First Name</label>
                                        <input class="bm-input" id="first_name" name="first_name" type="text" autocomplete="given-name" tabindex="5" required />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="middle_name">Middle Initial</label>
                                        <input class="bm-input" id="middle_name" name="middle_name" type="text" autocomplete="additional-name" inputmode="text" maxlength="1" pattern="[A-Za-z]" placeholder="M" tabindex="6" />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="suffix">Suffix</label>
                                        <input class="bm-input" id="suffix" name="suffix" type="text" placeholder="Jr., Sr., III" tabindex="7" />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="age">Age</label>
                                        <input class="bm-input" id="age" name="age" type="number" min="0" max="130" tabindex="8" required />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="sex">Gender</label>
                                        <input class="bm-input" id="sex" name="sex" type="text" list="sex_list" tabindex="9" required />
                                    <datalist id="sex_list">
                                        <option value="Male">
                                        <option value="Female">
                                    </datalist>
                                    </div>

                                    <div class="bm-field" id="civil_status_field">
                                        <label class="bm-label" for="civil_status">Civil Status</label>
                                        <input class="bm-input" id="civil_status" name="civil_status" type="text" list="civil_status_list" tabindex="10" required />
                                    <datalist id="civil_status_list">
                                        <option value="Single">
                                        <option value="Married">
                                        <option value="Widowed">
                                        <option value="Separated">
                                        <option value="Annulled">
                                    </datalist>
                                    </div>

                                    <div class="bm-field bm-span-2" id="home_address_field">
                                        <label class="bm-label" for="home_address">Home Address</label>
                                        <input class="bm-input" id="home_address" name="home_address" type="text" autocomplete="street-address" tabindex="11" required />
                                    </div>

                                    <div class="bm-field" id="occupation_field">
                                        <label class="bm-label" for="occupation">Occupation</label>
                                        <input class="bm-input" id="occupation" name="occupation" type="text" tabindex="12" required />
                                    </div>

                                    <div class="bm-field" id="position_field">
                                        <label class="bm-label" for="position">Position</label>
                                        <input class="bm-input" id="position" name="position" type="text" tabindex="13" required />
                                    </div>

                                    <div class="bm-field" id="educational_field">
                                        <label class="bm-label" for="educational">Educational</label>
                                        <input class="bm-input" id="educational" name="educational" type="text" list="educational_list" tabindex="14" required />
                                    <datalist id="educational_list">
                                        <option value="Elementary Graduate">
                                        <option value="High School Graduate">
                                        <option value="Senior High School">
                                        <option value="College Graduate">
                                        <option value="Post Graduate">
                                    </datalist>
                                    </div>

                                    <div class="bm-field" id="religion_field">
                                        <label class="bm-label" for="religion">Religion</label>
                                        <input class="bm-input" id="religion" name="religion" type="text" list="religion_list" tabindex="15" required />
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

                                    <div class="bm-field" id="agency_field">
                                        <label class="bm-label" for="company_requesting_agency">Company/Requesting Agency</label>
                                        <input class="bm-input" id="company_requesting_agency" name="company_requesting_agency" type="text" tabindex="16" required />
                                    </div>

                                    <div class="bm-field" id="detachment_field">
                                        <label class="bm-label" for="detachment">Detachment</label>
                                        <input class="bm-input" id="detachment" name="detachment" type="text" tabindex="17" />
                                    </div>

                                    <div class="bm-field">
                                        <label class="bm-label" for="date_of_birth">Date of Birth</label>
                                        <input class="bm-input" id="date_of_birth" name="date_of_birth" type="date" tabindex="17" required />
                                    </div>

                                    <div class="bm-field bm-span-2" id="purpose_field">
                                        <label class="bm-label" for="purpose">Purpose</label>
                                        <input type="hidden" id="purpose" name="purpose" value="" required />
                                        <div id="purpose_choices" style="margin-top:8px;border:1.5px solid var(--gray-300);border-radius:12px;padding:10px 12px;background:#fff;max-width:520px;">
                                            <div class="purpose-opt" data-purpose="firearm" style="display:flex;gap:10px;align-items:center;padding:8px 6px;border-radius:10px;cursor:pointer;">
                                                <span id="pv_firearm" style="font-family:var(--mono);">[  ]</span>
                                                <span>Firearm License/PTCFOR</span>
                                            </div>
                                            <div class="purpose-opt" data-purpose="security" style="display:flex;gap:10px;align-items:center;padding:8px 6px;border-radius:10px;cursor:pointer;">
                                                <span id="pv_security" style="font-family:var(--mono);">[  ]</span>
                                                <span>Security Guard License/ SO License</span>
                                            </div>
                                            <div class="purpose-opt" data-purpose="lto" style="display:flex;gap:10px;align-items:center;padding:8px 6px;border-radius:10px;cursor:pointer;">
                                                <span id="pv_lto" style="font-family:var(--mono);">[  ]</span>
                                                <span>L T O</span>
                                            </div>
                                            <div class="purpose-opt" data-purpose="others" style="display:flex;gap:10px;align-items:center;padding:8px 6px;border-radius:10px;cursor:pointer;">
                                                <span id="pv_others" style="font-family:var(--mono);">[  ]</span>
                                                <span>Others <span id="pv_others_text" style="color:#667085;"></span></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bm-field bm-span-2" id="purpose_specify_field" style="display:none;">
                                        <label class="bm-label" for="purpose_specify">Specify Purpose</label>
                                        <input class="bm-input" id="purpose_specify" name="purpose_specify" type="text" placeholder="Enter purpose details..." tabindex="19" />
                                    </div>
                                </div>

                                <div class="bm-actions">
                                    <button class="btn btn-s" type="button" id="batchResetBtn">New Batch</button>
                                    <button class="btn btn-p" type="submit">Generate DOCX</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <iframe
            name="docxDownloadFrame"
            id="docxDownloadFrame"
            title="DOCX download"
            style="display:none"
        ></iframe>

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
            var btn = document.getElementById('batchResetBtn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                if (!form) return;
                var docType = document.getElementById('document_type');
                var savedType = docType ? docType.value : '';
                form.reset();
                if (docType && savedType) {
                    docType.value = savedType;
                    docType.dispatchEvent(new Event('change', { bubbles: true }));
                }
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
            var form = document.getElementById('neuroDocForm');
            var documentTypeSelect = document.getElementById('document_type');
            var effectiveDocumentType = document.getElementById('effective_document_type');
            var titleEl = document.getElementById('docFormTitle');
            var contactNoField = document.getElementById('contact_no_field');
            var contactNoInput = document.getElementById('contact_no');
            var npClearanceField = document.getElementById('np_clearance_field');
            var npClearanceInput = document.getElementById('np_clearance');
            var documentDateLabel = document.getElementById('document_date_label');
            var purposeField = document.getElementById('purpose_field');
            var drugModeNote = document.getElementById('drugModeNote');
            var drugModeTitle = document.getElementById('drugModeTitle');
            var drugModeText = document.getElementById('drugModeText');
            var drugPhotoField = document.getElementById('drug_photo_field');
            var drugPhotoInput = document.getElementById('drug_photo');
            var drugOpenCameraBtn = document.getElementById('drugOpenCameraBtn');
            var drugCaptureBtn = document.getElementById('drugCaptureBtn');
            var drugCancelCameraBtn = document.getElementById('drugCancelCameraBtn');
            var drugCameraWrap = document.getElementById('drugCameraWrap');
            var drugCameraVideo = document.getElementById('drugCameraVideo');
            var drugCameraCanvas = document.getElementById('drugCameraCanvas');
            var drugPhotoPreviewWrap = document.getElementById('drugPhotoPreviewWrap');
            var drugPhotoPreview = document.getElementById('drugPhotoPreview');
            var drugPhotoStatus = document.getElementById('drugPhotoStatus');
            var drugPhotoActions = document.getElementById('drugPhotoActions');
            var drugOpenLocalhostBtn = document.getElementById('drugOpenLocalhostBtn');
            var homeAddressField = document.getElementById('home_address_field');
            var occupationField = document.getElementById('occupation_field');
            var positionField = document.getElementById('position_field');
            var educationalField = document.getElementById('educational_field');
            var religionField = document.getElementById('religion_field');
            var agencyField = document.getElementById('agency_field');
            var civilStatusField = document.getElementById('civil_status_field');
            var purposeSelect = document.getElementById('purpose');
            var purposeSpecifyField = document.getElementById('purpose_specify_field');
            var purposeSpecifyInput = document.getElementById('purpose_specify');
            var purposeChoices = document.getElementById('purpose_choices');
            var pvFirearm = document.getElementById('pv_firearm');
            var pvSecurity = document.getElementById('pv_security');
            var pvLto = document.getElementById('pv_lto');
            var pvOthers = document.getElementById('pv_others');
            var pvOthersText = document.getElementById('pv_others_text');
            if (!form || !documentTypeSelect || !purposeSelect || !purposeSpecifyField || !purposeChoices) return;

            function isDrugMode() {
                return documentTypeSelect.value === 'drug_test';
            }

            function applyMode() {
                var drugMode = isDrugMode();
                var bothMode = documentTypeSelect.value === 'both';
                var needDrugPhoto = drugMode || bothMode;

                if (effectiveDocumentType) {
                    effectiveDocumentType.value = documentTypeSelect.value || 'neuro';
                }

                if (titleEl) {
                    if (drugMode) {
                        titleEl.textContent = 'Generate Drug Test Document';
                    } else if (bothMode) {
                        titleEl.textContent = 'Generate Both Documents';
                    } else {
                        titleEl.textContent = 'Generate Neuro Document';
                    }
                }

                var showDrugNote = drugMode || bothMode;

                if (drugPhotoField) {
                    drugPhotoField.style.display = needDrugPhoto ? '' : 'none';
                }
                if (drugPhotoInput) {
                    drugPhotoInput.required = needDrugPhoto;
                    if (!needDrugPhoto) {
                        drugPhotoInput.value = '';
                    }
                }

                if (!needDrugPhoto) {
                    stopDrugCamera();
                    hideDrugPreview();
                    setDrugPhotoStatus('');
                    setDrugPhotoActionVisibility(false, false);
                } else {
                    // Proactively explain camera restrictions so users aren't surprised.
                    if (!supportsDrugCamera()) {
                        if (!isSecureForCamera()) {
                            setDrugPhotoStatus('Tip: “Take Photo” is blocked on Not Secure (HTTP). Use Choose File, or open this page via localhost/HTTPS.');
                            var offer = canOfferLocalhostSwitch();
                            setDrugPhotoActionVisibility(offer, offer);
                        } else {
                            setDrugPhotoStatus('Tip: If “Take Photo” doesn’t work, use Choose File to upload a photo.');
                            setDrugPhotoActionVisibility(false, false);
                        }
                    } else {
                        setDrugPhotoStatus('');
                        setDrugPhotoActionVisibility(false, false);
                    }
                }

                if (drugModeNote) {
                    drugModeNote.style.display = showDrugNote ? '' : 'none';
                }

                if (drugModeTitle) {
                    drugModeTitle.textContent = bothMode ? 'Neuro + Drug Test mode' : 'Drug Test mode';
                }

                if (drugModeText) {
                    drugModeText.textContent = bothMode
                        ? 'Drug test counters are generated automatically. Fill the Neuro details below and the Drug Test output will be filled too.'
                        : 'AP, CCF, transaction time, report time, and conducted/approved counters are generated automatically. You only fill the shared applicant details.';
                }

                if (npClearanceField) {
                    npClearanceField.style.display = drugMode ? 'none' : '';
                }

                if (contactNoField) {
                    contactNoField.style.display = drugMode ? 'none' : '';
                }

                if (npClearanceInput) {
                    npClearanceInput.required = !drugMode;
                    if (drugMode) {
                        npClearanceInput.value = '';
                    }
                }

                if (contactNoInput) {
                    contactNoInput.required = !drugMode;
                    if (drugMode) {
                        contactNoInput.value = '';
                    }
                }

                if (documentDateLabel) {
                    if (drugMode) {
                        documentDateLabel.textContent = 'Transaction Date';
                    } else if (bothMode) {
                        documentDateLabel.textContent = 'Date (Neuro) / Transaction Date (Drug Test)';
                    } else {
                        documentDateLabel.textContent = 'Date';
                    }
                }

                if (purposeField) {
                    purposeField.style.display = drugMode ? 'none' : '';
                }

                var profileFields = [occupationField, positionField, educationalField, religionField, civilStatusField];
                for (var i = 0; i < profileFields.length; i++) {
                    if (profileFields[i]) {
                        profileFields[i].style.display = drugMode ? 'none' : '';
                    }
                }

                var profileInputs = ['occupation', 'position', 'educational', 'religion', 'civil_status'];
                for (var j = 0; j < profileInputs.length; j++) {
                    var inputEl = document.getElementById(profileInputs[j]);
                    if (inputEl) {
                        inputEl.required = !drugMode;
                    }
                }

                var homeAddressInput = document.getElementById('home_address');
                if (homeAddressField) {
                    homeAddressField.style.display = drugMode ? 'none' : '';
                }
                if (homeAddressInput) {
                    homeAddressInput.required = !drugMode;
                    if (drugMode) {
                        homeAddressInput.value = '';
                    }
                }

                if (drugMode) {
                    purposeSelect.value = '';
                    if (purposeSpecifyField) {
                        purposeSpecifyField.style.display = 'none';
                    }
                    if (purposeSpecifyInput) {
                        purposeSpecifyInput.required = false;
                        purposeSpecifyInput.value = '';
                    }
                }

                updatePreview();
            }

            var drugCameraStream = null;

            function setDrugPhotoStatus(msg) {
                if (!drugPhotoStatus) return;
                drugPhotoStatus.textContent = msg ? String(msg) : '';
            }

            function setDrugPhotoActionVisibility(showActions, showLocalhost) {
                if (drugPhotoActions) {
                    drugPhotoActions.style.display = showActions ? '' : 'none';
                }
                if (drugOpenLocalhostBtn) {
                    drugOpenLocalhostBtn.style.display = showLocalhost ? '' : 'none';
                }
            }

            function isLocalHostName(hostname) {
                hostname = String(hostname || '').toLowerCase();
                return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
            }

            function canOfferLocalhostSwitch() {
                try {
                    if (window.location.protocol !== 'http:' && window.location.protocol !== 'https:') return false;
                    if (isLocalHostName(window.location.hostname)) return false;
                    return true;
                } catch (e) {
                    return false;
                }
            }

            function isSecureForCamera() {
                // Browsers require HTTPS or localhost for getUserMedia.
                return !!window.isSecureContext;
            }

            function supportsDrugCamera() {
                return !!(isSecureForCamera() && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            }

            function showDrugCameraUi(isOpen) {
                if (drugCameraWrap) drugCameraWrap.style.display = isOpen ? '' : 'none';
                if (drugCaptureBtn) drugCaptureBtn.style.display = isOpen ? '' : 'none';
                if (drugCancelCameraBtn) drugCancelCameraBtn.style.display = isOpen ? '' : 'none';
                if (drugOpenCameraBtn) drugOpenCameraBtn.style.display = isOpen ? 'none' : '';
            }

            function stopDrugCamera() {
                if (drugCameraStream) {
                    try {
                        var tracks = drugCameraStream.getTracks();
                        tracks.forEach(function (t) {
                            try { t.stop(); } catch (e) {}
                        });
                    } catch (e) {}
                    drugCameraStream = null;
                }
                if (drugCameraVideo) {
                    try { drugCameraVideo.srcObject = null; } catch (e) {}
                }
                showDrugCameraUi(false);
            }

            function hideDrugPreview() {
                if (drugPhotoPreviewWrap) drugPhotoPreviewWrap.style.display = 'none';
                if (drugPhotoPreview) {
                    try {
                        if (drugPhotoPreview.src && drugPhotoPreview.src.indexOf('blob:') === 0) {
                            URL.revokeObjectURL(drugPhotoPreview.src);
                        }
                    } catch (e) {}
                    drugPhotoPreview.removeAttribute('src');
                }
            }

            function showDrugPreviewFromFile(file) {
                hideDrugPreview();
                if (!file || !drugPhotoPreview || !drugPhotoPreviewWrap) return;
                var url = URL.createObjectURL(file);
                drugPhotoPreview.src = url;
                drugPhotoPreviewWrap.style.display = '';
            }

            async function openDrugCamera() {
                if (!supportsDrugCamera()) {
                    if (!isSecureForCamera()) {
                        setDrugPhotoStatus('Camera capture is blocked on Not Secure (HTTP). Use Choose File, or open via localhost/HTTPS.');
                        var offer = canOfferLocalhostSwitch();
                        setDrugPhotoActionVisibility(offer, offer);
                    } else {
                        setDrugPhotoStatus('Camera is not available here. Use Choose File to upload a photo.');
                        setDrugPhotoActionVisibility(false, false);
                    }

                    // Fallback: open the file picker.
                    try {
                        if (drugPhotoInput) {
                            drugPhotoInput.click();
                        }
                    } catch (e) {}
                    return;
                }

                try {
                    stopDrugCamera();
                    var constraints = {
                        video: {
                            facingMode: { ideal: 'environment' }
                        },
                        audio: false
                    };

                    drugCameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                    if (drugCameraVideo) {
                        drugCameraVideo.srcObject = drugCameraStream;
                    }
                    showDrugCameraUi(true);
                    setDrugPhotoStatus('Camera is open. Click Capture when ready.');
                    setDrugPhotoActionVisibility(false, false);
                } catch (e) {
                    stopDrugCamera();
                    setDrugPhotoStatus('Unable to open camera. Please allow permission, or upload a photo instead.');
                }
            }

            function captureDrugPhoto() {
                if (!drugCameraVideo || !drugCameraCanvas || !drugPhotoInput) return;
                var w = drugCameraVideo.videoWidth || 0;
                var h = drugCameraVideo.videoHeight || 0;
                if (w <= 0 || h <= 0) {
                    alert('Camera is not ready yet. Try again.');
                    return;
                }

                drugCameraCanvas.width = w;
                drugCameraCanvas.height = h;
                var ctx = drugCameraCanvas.getContext('2d');
                if (!ctx) return;
                ctx.drawImage(drugCameraVideo, 0, 0, w, h);

                drugCameraCanvas.toBlob(function (blob) {
                    if (!blob) {
                        alert('Failed to capture photo.');
                        return;
                    }

                    var file = new File([blob], 'drug_photo.jpg', { type: blob.type || 'image/jpeg' });
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(file);
                        drugPhotoInput.files = dt.files;
                    } catch (e) {
                        // Fallback: user can still upload manually.
                    }

                    showDrugPreviewFromFile(file);
                    stopDrugCamera();
                }, 'image/jpeg', 0.92);
            }

            function setBox(el, checked) {
                if (!el) return;
                el.textContent = checked ? '[X]' : '[  ]';
            }

            function updatePreview() {
                var val = purposeSelect.value;
                setBox(pvFirearm, val === 'firearm');
                setBox(pvSecurity, val === 'security');
                setBox(pvLto, val === 'lto');
                setBox(pvOthers, val === 'others');

                if (pvOthersText) {
                    var t = (purposeSpecifyInput && purposeSpecifyInput.value) ? String(purposeSpecifyInput.value).trim() : '';
                    pvOthersText.textContent = t ? ('(' + t + ')') : '';
                }
            }

            function toggleSpecify() {
                if (isDrugMode()) {
                    purposeSpecifyField.style.display = 'none';
                    if (purposeSpecifyInput) {
                        purposeSpecifyInput.required = false;
                        purposeSpecifyInput.value = '';
                    }
                    updatePreview();
                    return;
                }

                var val = purposeSelect.value;
                if (val === 'others') {
                    purposeSpecifyField.style.display = '';
                    if (purposeSpecifyInput) purposeSpecifyInput.required = true;
                } else {
                    purposeSpecifyField.style.display = 'none';
                    if (purposeSpecifyInput) {
                        purposeSpecifyInput.required = false;
                        purposeSpecifyInput.value = '';
                    }
                }

                updatePreview();
            }

            purposeChoices.addEventListener('click', function (e) {
                if (isDrugMode()) return;
                var el = e.target;
                while (el && el !== purposeChoices && !(el.classList && el.classList.contains('purpose-opt'))) {
                    el = el.parentNode;
                }
                if (!el || el === purposeChoices) return;
                var v = el.getAttribute('data-purpose') || '';
                purposeSelect.value = v;
                toggleSpecify();
            });

            if (purposeSpecifyInput) {
                purposeSpecifyInput.addEventListener('input', updatePreview);
            }

            if (documentTypeSelect) {
                documentTypeSelect.addEventListener('change', applyMode);
            }

            if (drugOpenCameraBtn) {
                drugOpenCameraBtn.addEventListener('click', function () {
                    if (isDrugMode() || (documentTypeSelect && documentTypeSelect.value === 'both')) {
                        openDrugCamera();
                    }
                });
            }
            if (drugCancelCameraBtn) {
                drugCancelCameraBtn.addEventListener('click', function () {
                    stopDrugCamera();
                });
            }
            if (drugCaptureBtn) {
                drugCaptureBtn.addEventListener('click', function () {
                    captureDrugPhoto();
                });
            }
            if (drugPhotoInput) {
                drugPhotoInput.addEventListener('change', function () {
                    stopDrugCamera();
                    var f = drugPhotoInput.files && drugPhotoInput.files[0] ? drugPhotoInput.files[0] : null;
                    if (f) {
                        showDrugPreviewFromFile(f);
                        setDrugPhotoStatus('');
                        setDrugPhotoActionVisibility(false, false);
                    } else {
                        hideDrugPreview();
                    }
                });
            }

            if (drugOpenLocalhostBtn) {
                drugOpenLocalhostBtn.addEventListener('click', function () {
                    try {
                        var url = new URL(window.location.href);
                        url.hostname = 'localhost';
                        window.location.href = url.toString();
                    } catch (e) {
                        // no-op
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function () {
                    if (effectiveDocumentType) {
                        effectiveDocumentType.value = documentTypeSelect ? documentTypeSelect.value : 'neuro';
                    }
                });
            }

            purposeSelect.value = '';
            applyMode();

            document.addEventListener('reset', function (e) {
                var f = e.target;
                if (!f || f.id !== 'neuroDocForm') return;
                setTimeout(function () {
                    applyMode();
                }, 0);
            }, true);
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

            function currentDocumentType() {
                var select = document.getElementById('document_type');
                return select ? select.value : 'neuro';
            }

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
                var docType = currentDocumentType();
                var keepIds = ['position', 'company_requesting_agency', 'occupation', 'document_date', 'folder_name', 'document_type'];
                if (docType !== 'drug_test') {
                    keepIds.push('purpose');
                }
                var saved = {};

                keepIds.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        saved[id] = el.value;
                    }
                });

                var newNp = docType === 'drug_test' ? '' : incrementNpClearance();

                form.reset();

                keepIds.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el && saved.hasOwnProperty(id)) {
                        el.value = saved[id];
                    }
                });

                var docTypeEl = document.getElementById('document_type');
                if (docTypeEl && docType) {
                    docTypeEl.value = docType;
                    docTypeEl.dispatchEvent(new Event('change', { bubbles: true }));
                }

                var npEl = document.getElementById('np_clearance');
                if (npEl && docType !== 'drug_test' && newNp) {
                    npEl.value = newNp;
                    npEl.required = true;
                } else if (npEl && docType === 'drug_test') {
                    npEl.value = '';
                    npEl.required = false;
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
