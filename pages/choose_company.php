<?php
require_once __DIR__ . '/../includes/guards.php';
require_role('admin');

if (isset($_SESSION['company'])) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Select Company | ERMS</title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/css/home_redesign.css" />
</head>
<body class="choose-company">
    <div class="page-shell">
        <main class="login-card">
            <div class="login-card-inner">
                <header class="login-header">
                    <div class="login-logo" aria-hidden="true">
                        <img src="../assets/img/erms-logo.svg" alt="ERMS" style="height:56px;width:56px;" />
                    </div>
                    <div class="login-title-block">
                        <h1>Select Company</h1>
                        <p>Choose where to continue</p>
                    </div>
                    <div class="env-pill">ADMIN ACCESS</div>
                </header>

                <div class="company-grid">
                    <form method="post" action="../auth/set_company.php" class="company-form">
                        <input type="hidden" name="company" value="brainmaster" />
                        <button type="submit" class="company-card" aria-label="Brain Master">
                            <span class="company-logo" aria-hidden="true">
                                <img src="../assets/img/brainmaster.jpg" alt="" />
                            </span>
                            <span class="company-text">
                                <span class="company-name">Brain Master</span>
                                <span class="company-meta">Neuro Documents</span>
                            </span>
                            <span class="company-go" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                            </span>
                        </button>
                    </form>

                    <form method="post" action="../auth/set_company.php" class="company-form">
                        <input type="hidden" name="company" value="jubecer" />
                        <button type="submit" class="company-card" aria-label="Jubecer">
                            <span class="company-logo" aria-hidden="true">
                                <img src="../assets/img/jubecer-logo.svg" alt="" />
                            </span>
                            <span class="company-text">
                                <span class="company-name">Jubecer</span>
                                <span class="company-meta">Guard Management</span>
                            </span>
                            <span class="company-go" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                            </span>
                        </button>
                    </form>
                </div>

                <div class="login-footer">
                    Logged in as <?php echo htmlspecialchars((string)($_SESSION['user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?> · <a class="js-logout" href="../auth/logout.php">Logout</a>
                </div>
            </div>
        </main>
    </div>

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
