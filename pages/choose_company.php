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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/login.css" />
</head>
<body>
    <div class="page-shell">
        <main class="login-card">
            <div class="login-card-inner">
                <header class="login-header">
                    <div class="login-title-block">
                        <h1>Select Company</h1>
                        <p>Choose where to continue</p>
                    </div>
                </header>

                <div class="company-grid">
                    <form method="post" action="../auth/set_company.php" class="company-form">
                        <input type="hidden" name="company" value="brainmaster" />
                        <button type="submit" class="company-card" aria-label="Brain Master">
                            <img class="company-logo" src="../assets/img/brainmaster.jpg" alt="Brain Master" />
                        </button>
                    </form>

                    <form method="post" action="../auth/set_company.php" class="company-form">
                        <input type="hidden" name="company" value="jubecer" />
                        <button type="submit" class="company-card" aria-label="Jubecer">
                            <div class="company-logo placeholder">J</div>
                        </button>
                    </form>
                </div>

                <div class="login-footer">
                    Logged in as <?php echo htmlspecialchars((string)($_SESSION['user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
