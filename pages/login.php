<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee') {
        header('Location: neuro_documents.php');
        exit;
    }

    if (!isset($_SESSION['company'])) {
        header('Location: choose_company.php');
        exit;
    }

    header('Location: home.php');
    exit;
}

$hasError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>ERMS Login | Brain Master Diagnostic Center & Jubecer Security Services Inc.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../assets/css/login.css" />
</head>
<body>
    <div class="page-shell">
        <main class="login-card">
            <div class="login-card-inner">
                <header class="login-header">
                    <div class="login-logo">
                        <img src="../assets/img/brainmaster.jpg" alt="Brain Master Diagnostic Center logo" />
                    </div>
                    <div class="login-title-block">
                        <h1>ERMS Login</h1>
                        <p>Brain Master • Jubecer</p>
                        <div class="env-pill">Secure access</div>
                    </div>
                </header>

                <?php if ($hasError): ?>
                <div class="error-banner">
                    Invalid username or password. Please try again.
                </div>
                <?php endif; ?>

                <form class="login-form" method="post" action="../auth/authenticate.php">
                    <div class="field-group">
                        <div class="field-label-row">
                            <label for="username" class="field-label">Employee ID</label>
                        </div>
                        <div class="field-control">
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="input-base"
                                placeholder="e.g. 2024-0001"
                                title="e.g. 2024-0001"
                                required
                                autocomplete="username"
                            />
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="field-label-row">
                            <label for="password" class="field-label">Password</label>
                        </div>
                        <div class="field-control">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="input-base"
                                placeholder="Your ERMS password"
                                title="Use your assigned ERMS password"
                                required
                                autocomplete="current-password"
                            />
                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility()"
                                aria-label="Show or hide password"
                            ></button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" />
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot?</a>
                    </div>

                    <button type="submit" class="login-button">
                        <span class="icon-dot"></span>
                        <span>Sign in</span>
                    </button>

                    <div class="login-footer">
                        Authorized staff only. Activity is logged.
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById("password");
            const btn = event.currentTarget;
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            btn.classList.toggle("is-visible", isPassword);
        }
    </script>
</body>
</html>
