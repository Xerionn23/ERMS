<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

function audit_auth(PDO $pdo, string $action, string $employeeId, $detail = null, ?int $actorUserId = null): void
{
    try {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $detailJson = null;
        if ($detail !== null) {
            $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($detailJson === false) {
                $detailJson = null;
            }
        }
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (actor_employee_id, actor_user_id, action, target_type, target_id, detail, ip_address, user_agent) '
            . 'VALUES (:actor_employee_id, :actor_user_id, :action, :target_type, :target_id, :detail, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'actor_employee_id' => $employeeId !== '' ? $employeeId : null,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'target_type' => 'auth',
            'target_id' => $employeeId !== '' ? $employeeId : null,
            'detail' => $detailJson,
            'ip_address' => $ip !== '' ? $ip : null,
            'user_agent' => $ua !== '' ? substr($ua, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
    }
}

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
            audit_auth($pdo, 'login_failed', $username, ['reason' => 'invalid_credentials_or_inactive']);
            header('Location: ../pages/login.php?error=1');
            exit;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_employee_id'] = (string)$user['employee_id'];
        $_SESSION['user_name'] = (string)$user['full_name'];
        $_SESSION['user_role'] = (string)$user['role'];

        audit_auth($pdo, 'login_success', (string)$user['employee_id'], null, (int)$user['id']);
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->prepare(
                'SELECT '
                . 'u.id, u.employee_id, u.password_hash, '
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
                || (int)($user['emp_active'] ?? 0) !== 1
                || !password_verify($password, (string)($user['password_hash'] ?? ''))
            ) {
                audit_auth($pdo, 'login_failed', $username, ['reason' => 'invalid_credentials_or_inactive']);
                header('Location: ../pages/login.php?error=1');
                exit;
            }

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_employee_id'] = (string)$user['employee_id'];
            $_SESSION['user_name'] = (string)$user['full_name'];
            $_SESSION['user_role'] = (string)$user['role'];

            audit_auth($pdo, 'login_success', (string)$user['employee_id'], null, (int)$user['id']);
        } catch (Throwable $e2) {
            $stmt = $pdo->prepare('SELECT id, employee_id, full_name, role, password_hash, is_active FROM users WHERE employee_id = :employee_id LIMIT 1');
            $stmt->execute(['employee_id' => $username]);
            $user = $stmt->fetch();

            if (!$user || (int)($user['is_active'] ?? 1) !== 1 || !password_verify($password, (string)$user['password_hash'])) {
                audit_auth($pdo, 'login_failed', $username, ['reason' => 'invalid_credentials_or_inactive']);
                header('Location: ../pages/login.php?error=1');
                exit;
            }

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_employee_id'] = (string)$user['employee_id'];
            $_SESSION['user_name'] = (string)$user['full_name'];
            $_SESSION['user_role'] = (string)$user['role'];

            audit_auth($pdo, 'login_success', (string)$user['employee_id'], null, (int)$user['id']);
        }
    }
} catch (Throwable $e) {
    try {
        $pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : db();
        audit_auth($pdo, 'login_failed', $username, ['reason' => 'server_error']);
    } catch (Throwable $e2) {
    }
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
