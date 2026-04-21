<?php
require_once __DIR__ . '/../includes/db.php';

function random_password(int $length = 16): string
{
    return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
}

function reset_passwords(PDO $pdo, array $employeeIds): array
{
    $results = [];
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE employee_id = :employee_id');

    foreach ($employeeIds as $employeeId) {
        $employeeId = (string) $employeeId;
        $newPass = random_password(12);
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        try {
            $stmt->execute(['hash' => $hash, 'employee_id' => $employeeId]);
            $updated = $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            $updated = false;
        }

        $results[] = [
            'employee_id' => $employeeId,
            'new_password' => $updated ? $newPass : null,
            'updated' => $updated,
        ];
    }

    return $results;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connection_failed']);
    exit;
}

if (php_sapi_name() === 'cli') {
    $args = array_slice($argv, 1);
    if (count($args) === 0) {
        echo "Usage: php scripts/reset_passwords.php EMP_ID [EMP_ID ...]\n";
        exit(1);
    }

    $res = reset_passwords($pdo, $args);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

// Web mode: accept POST with employee_ids[] or JSON body
$inputIds = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['employee_ids']) && is_array($_POST['employee_ids'])) {
        $inputIds = $_POST['employee_ids'];
    } else {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['employee_ids']) && is_array($json['employee_ids'])) {
            $inputIds = $json['employee_ids'];
        }
    }
}

if (!is_array($inputIds) || count($inputIds) === 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'no_employee_ids_provided']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(reset_passwords($pdo, $inputIds), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
