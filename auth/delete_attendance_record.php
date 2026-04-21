<?php
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$role = (string)($_SESSION['user_role'] ?? '');
if ($role !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Admin only.']);
    exit;
}

require_company();
if ((string)($_SESSION['company'] ?? '') !== 'brainmaster') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Company not authorized.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$csrf = (string)($_POST['csrf'] ?? '');
$csrfSession = (string)($_SESSION['csrf_attendance'] ?? '');
if ($csrf === '' || $csrfSession === '' || !hash_equals($csrfSession, $csrf)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request token.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id.']);
    exit;
}

try {
    $pdo = db();
    $company = (string)($_SESSION['company'] ?? 'brainmaster');

    $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = :id AND company = :company');
    $stmt->execute(['id' => $id, 'company' => $company]);

    if ($stmt->rowCount() <= 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Record not found.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[ERMS][delete_attendance_record] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error.']);
    exit;
}
