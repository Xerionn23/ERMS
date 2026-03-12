<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

if (!isset($_SESSION['user_role']) || (string)$_SESSION['user_role'] !== 'admin') {
    header('Location: ../pages/home.php');
    exit;
}

unset($_SESSION['company']);

header('Location: ../pages/choose_company.php');
exit;
