<?php

// Ensure consistent timestamps across exports/pages.
// Default to Asia/Manila (Brain Master / PH) unless overridden by ERMS_TIMEZONE.
$ermsTz = getenv('ERMS_TIMEZONE');
if (!is_string($ermsTz) || trim($ermsTz) === '') {
    $ermsTz = 'Asia/Manila';
}
if (function_exists('date_default_timezone_set')) {
    // Suppress warnings for invalid timezones; fall back to Asia/Manila.
    if (@date_default_timezone_set($ermsTz) !== true) {
        @date_default_timezone_set('Asia/Manila');
    }
}

function require_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../pages/login.php');
        exit;
    }
}

function require_company(): void
{
    require_login();

    if (!isset($_SESSION['company'])) {
        header('Location: ../pages/choose_company.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if (!isset($_SESSION['user_role']) || (string)$_SESSION['user_role'] !== $role) {
        $currentRole = (string)($_SESSION['user_role'] ?? '');

        if ($currentRole === 'employee') {
            header('Location: ../pages/neuro_documents.php');
            exit;
        }

        if ($currentRole === 'security_operation') {
            header('Location: ../pages/home.php');
            exit;
        }

        if ($currentRole === 'admin') {
            if (!isset($_SESSION['company'])) {
                header('Location: ../pages/choose_company.php');
                exit;
            }

            if ((string)($_SESSION['company'] ?? '') === 'brainmaster') {
                header('Location: ../pages/neuro_documents.php');
                exit;
            }
        }

        header('Location: ../pages/home.php');
        exit;
    }
}
