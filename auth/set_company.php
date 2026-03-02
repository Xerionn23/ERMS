<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/choose_company.php');
    exit;
}

$company = isset($_POST['company']) ? (string)$_POST['company'] : '';
$allowed = ['brainmaster', 'jubecer'];

if (!in_array($company, $allowed, true)) {
    header('Location: ../pages/choose_company.php');
    exit;
}

$_SESSION['company'] = $company;

header('Location: ../pages/home.php');
exit;
