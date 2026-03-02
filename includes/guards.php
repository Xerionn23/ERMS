<?php

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
        if (isset($_SESSION['user_role']) && (string)$_SESSION['user_role'] === 'employee') {
            header('Location: ../pages/neuro_documents.php');
            exit;
        }

        header('Location: ../pages/home.php');
        exit;
    }
}
