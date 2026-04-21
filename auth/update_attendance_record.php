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

$norm = static function ($value): string {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return $value === null ? '' : $value;
};

$clip = static function (string $value, int $max): string {
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
};

$sanitizeName = static function (string $value): string {
    $value = preg_replace('/[^A-Za-z0-9 .,-]/u', '', $value);
    return $value === null ? '' : trim($value);
};

$sanitizeText = static function (string $value): string {
    $value = preg_replace('/[^A-Za-z0-9 _.,\-()]/u', '', $value);
    return $value === null ? '' : trim($value);
};

$normalizeDate = static function (string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d');
};

$last = $clip($sanitizeName($norm($_POST['last_name'] ?? '')), 80);
$first = $clip($sanitizeName($norm($_POST['first_name'] ?? '')), 80);
$middle = $clip($sanitizeName($norm($_POST['middle_name'] ?? '')), 40);
$gender = $clip($sanitizeName($norm($_POST['gender'] ?? '')), 20);
$agency = $clip($sanitizeText($norm($_POST['agency'] ?? '')), 120);
$detachment = $clip($sanitizeText($norm($_POST['detachment'] ?? '')), 120);
$birthRaw = $norm($_POST['birth_date'] ?? '');
$birth = $normalizeDate($birthRaw);

if ($last === '' || $first === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Last Name and First Name are required.']);
    exit;
}

$full = trim($last . ', ' . $first . ($middle !== '' ? ' ' . $middle : ''));

$computeAge = static function (?string $birthDate): string {
    if ($birthDate === null || $birthDate === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $birthDate);
    if (!$dt) {
        return '';
    }
    $today = new DateTime('today');
    $diff = $dt->diff($today);
    return (string)$diff->y;
};

try {
    $pdo = db();
    $company = (string)($_SESSION['company'] ?? 'brainmaster');

    $stmt = $pdo->prepare('SELECT id FROM attendance_records WHERE id = :id AND company = :company');
    $stmt->execute(['id' => $id, 'company' => $company]);
    $exists = (int)$stmt->fetchColumn();

    if ($exists <= 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Record not found.']);
        exit;
    }

    $upd = $pdo->prepare(
        'UPDATE attendance_records SET '
        . 'first_name = :first_name, '
        . 'middle_name = :middle_name, '
        . 'last_name = :last_name, '
        . 'full_name = :full_name, '
        . 'agency = :agency, '
        . 'detachment = :detachment, '
        . 'birth_date = :birth_date, '
        . 'gender = :gender '
        . 'WHERE id = :id AND company = :company'
    );

    $upd->execute([
        'first_name' => $first,
        'middle_name' => $middle !== '' ? $middle : null,
        'last_name' => $last,
        'full_name' => $full,
        'agency' => $agency !== '' ? $agency : null,
        'detachment' => $detachment !== '' ? $detachment : null,
        'birth_date' => $birth,
        'gender' => $gender !== '' ? $gender : null,
        'id' => $id,
        'company' => $company,
    ]);

    echo json_encode([
        'ok' => true,
        'record' => [
            'id' => $id,
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle,
            'gender' => $gender,
            'birth_date' => $birth ?? '',
            'age' => $computeAge($birth),
            'agency' => $agency,
            'detachment' => $detachment,
            'full_name' => $full,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[ERMS][update_attendance_record] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error.']);
    exit;
}
