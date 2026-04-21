<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $stmt = $pdo->query(
        'SELECT u.id, u.employee_id, e.full_name, e.role, u.is_active AS user_active, e.is_active AS emp_active '
        . 'FROM users u '
        . 'JOIN employees e ON e.employee_id = u.employee_id '
        . 'ORDER BY u.id ASC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'server_error']);
}
