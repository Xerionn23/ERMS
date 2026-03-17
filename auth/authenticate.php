<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($username === '' || $password === '') {
    header('Location: ../pages/login.php?error=1');
    exit;
}

try {
    $pdo = db();

    try {
        $stmt = $pdo->prepare(
            'SELECT '
            . 'u.id, u.employee_id, u.password_hash, u.is_active AS user_active, '
            . 'e.full_name, e.role, e.is_active AS emp_active '
            . 'FROM users u '
            . 'JOIN employees e ON e.employee_id = u.employee_id '
            . 'WHERE u.employee_id = :employee_id '
            . 'LIMIT 1'
        );
        $stmt->execute(['employee_id' => $username]);
        $user = $stmt->fetch();

        if (
            !$user
            || (int)($user['user_active'] ?? 0) !== 1
            || (int)($user['emp_active'] ?? 0) !== 1
            || !password_verify($password, (string)($user['password_hash'] ?? ''))
        ) {
            header('Location: ../pages/login.php?error=1');
            exit;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_employee_id'] = (string)$user['employee_id'];
        $_SESSION['user_name'] = (string)$user['full_name'];
        $_SESSION['user_role'] = (string)$user['role'];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare('SELECT id, employee_id, full_name, role, password_hash, is_active FROM users WHERE employee_id = :employee_id LIMIT 1');
        $stmt->execute(['employee_id' => $username]);
        $user = $stmt->fetch();

        if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
            header('Location: ../pages/login.php?error=1');
            exit;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_employee_id'] = (string)$user['employee_id'];
        $_SESSION['user_name'] = (string)$user['full_name'];
        $_SESSION['user_role'] = (string)$user['role'];
    }
} catch (Throwable $e) {
    header('Location: ../pages/login.php?error=1');
    exit;
}

unset($_SESSION['company']);

$role = (string)($_SESSION['user_role'] ?? '');

if ($role === 'employee') {
    $_SESSION['company'] = 'brainmaster';
    header('Location: ../pages/neuro_documents.php');
    exit;
}

if ($role === 'security_operation') {
    $_SESSION['company'] = 'jubecer';
    header('Location: ../pages/home.php');
    exit;
}

header('Location: ../pages/choose_company.php');
exit;
