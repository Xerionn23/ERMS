<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$users = [
    [
        'employee_id' => 'admin',
        'full_name' => 'Administrator',
        'role' => 'admin',
        'password' => 'admin123'
    ],
    [
        'employee_id' => 'employee',
        'full_name' => 'Employee',
        'role' => 'employee',
        'password' => 'employee123'
    ],
];

$stmt = $pdo->prepare('INSERT INTO users (employee_id, full_name, role, password_hash, is_active) VALUES (:employee_id, :full_name, :role, :password_hash, 1)');

foreach ($users as $u) {
    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1');
    $existsStmt->execute(['employee_id' => $u['employee_id']]);
    $existing = $existsStmt->fetch();

    if ($existing) {
        continue;
    }

    $stmt->execute([
        'employee_id' => $u['employee_id'],
        'full_name' => $u['full_name'],
        'role' => $u['role'],
        'password_hash' => password_hash($u['password'], PASSWORD_BCRYPT),
    ]);
}

echo "Seed complete\n";
