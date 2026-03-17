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
<body>
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
                            <span class="company-logo placeholder" aria-hidden="true">J</span>
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
                    Logged in as <?php echo htmlspecialchars((string)($_SESSION['user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?> · <a href="../auth/logout.php">Logout</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
