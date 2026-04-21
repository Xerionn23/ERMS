<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

function audit_setup(PDO $pdo, string $action, string $employeeId, $detail = null): void
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
            . 'VALUES (:actor_employee_id, NULL, :action, :target_type, :target_id, :detail, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'actor_employee_id' => $employeeId !== '' ? $employeeId : null,
            'action' => $action,
            'target_type' => 'account_setup',
            'target_id' => $employeeId !== '' ? $employeeId : null,
            'detail' => $detailJson,
            'ip_address' => $ip !== '' ? $ip : null,
            'user_agent' => $ua !== '' ? substr($ua, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$employeeId = trim((string)($_POST['employee_id'] ?? ''));
$intent = strtolower(trim((string)($_POST['intent'] ?? 'create')));
if (!in_array($intent, ['create', 'reset'], true)) {
    $intent = 'create';
}
$isReset = $intent === 'reset';

if ($employeeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'User ID is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['acct_setup_last_ts'])) {
    $_SESSION['acct_setup_last_ts'] = 0;
}

$now = time();
if (($now - (int)$_SESSION['acct_setup_last_ts']) < 15) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'Please wait a moment before requesting another setup email.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['acct_setup_last_ts'] = $now;

function baseUrl(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    $base = $scheme . '://' . $host;

    if ($dir !== '' && $dir !== '.') {
        $base .= $dir;
    }

    $base = preg_replace('~/(auth|pages)$~', '', $base);

    return rtrim((string)$base, '/');
}

function logMailOutbox(string $to, string $subject, string $body): void
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = "[" . date('c') . "] to=" . $to . " subject=" . $subject . "\n" . $body . "\n\n";
    @file_put_contents($dir . '/mail_outbox.log', $line, FILE_APPEND | LOCK_EX);
}

function logMailDebug(string $line): void
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $msg = "[" . date('c') . "] " . $line . "\n";
    @file_put_contents($dir . '/mail_debug.log', $msg, FILE_APPEND | LOCK_EX);
}

function loadMailConfig(): array
{
    $p = __DIR__ . '/../includes/mail_config.local.php';
    if (!is_file($p)) {
        return [];
    }

    $cfg = require $p;
    return is_array($cfg) ? $cfg : [];
}

function trySendViaPhpMailer(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        logMailDebug('PHPMailer skipped: vendor/autoload.php not found.');
        return false;
    }

    $cfg = loadMailConfig();
    $smtp = is_array($cfg['smtp'] ?? null) ? (array)$cfg['smtp'] : [];
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 0);
    $username = (string)($smtp['username'] ?? '');
    $appPassword = (string)($smtp['app_password'] ?? '');
    $fromAddress = (string)($smtp['from_address'] ?? $username);
    $fromName = (string)($smtp['from_name'] ?? 'ERMS');

    $username = trim($username);
    $fromAddress = trim($fromAddress);
    $appPassword = preg_replace('~[\s-]+~', '', $appPassword);

    if ($host === '' || $port <= 0 || $username === '' || $appPassword === '' || $fromAddress === '') {
        logMailDebug('PHPMailer skipped: missing smtp config values.');
        return false;
    }

    require_once $autoload;

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        logMailDebug('PHPMailer skipped: PHPMailer class not found (composer dependency missing).');
        return false;
    }

    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->SMTPDebug = 0;
        $m->isSMTP();
        $m->Timeout = 12;
        $m->SMTPKeepAlive = false;
        $m->Host = $host;
        $m->SMTPAuth = true;
        $m->Username = $username;
        $m->Password = $appPassword;
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->Port = $port;

        $m->setFrom($fromAddress, $fromName);
        $m->addAddress($to);

        $m->Subject = $subject;
        $m->isHTML(true);
        $m->Body = $htmlBody;
        $m->AltBody = $textBody;

        $m->send();
        return true;
    } catch (Throwable $e) {
        logMailDebug('PHPMailer send failed: ' . $e->getMessage());
        return false;
    }
}

try {
    $pdo = db();

    $empStmt = $pdo->prepare('SELECT employee_id, email, is_active FROM employees WHERE employee_id = :employee_id LIMIT 1');
    $empStmt->execute(['employee_id' => $employeeId]);
    $emp = $empStmt->fetch();

    $uStmt = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1');
    $uStmt->execute(['employee_id' => $employeeId]);
    $existingUser = $uStmt->fetch();

    if ($existingUser && !$isReset) {
        audit_setup($pdo, 'account_setup_request_failed', $employeeId, ['reason' => 'account_exists', 'intent' => $intent]);
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'An account already exists for this User ID. Please sign in.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$existingUser && $isReset) {
        audit_setup($pdo, 'account_setup_request_failed', $employeeId, ['reason' => 'account_missing', 'intent' => $intent]);
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'No account found for this User ID. Please use Create account.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $okToSend = false;
    $email = '';

    if ($emp && (int)($emp['is_active'] ?? 0) === 1) {
        $email = trim((string)($emp['email'] ?? ''));
        if ($email !== '') {
            $okToSend = true;
        }
    }

    if (!$okToSend) {
        audit_setup($pdo, 'account_setup_request_failed', $employeeId, ['reason' => 'not_found_inactive_or_missing_email', 'intent' => $intent]);
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'User ID not found, inactive, or missing email on file.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isGmail = $email !== '' && preg_match('/^[^@\s]+@gmail\.com$/i', $email) === 1;
    if (!$isGmail) {
        audit_setup($pdo, 'account_setup_request_failed', $employeeId, ['reason' => 'non_gmail_email', 'intent' => $intent]);
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Only Gmail addresses are supported for account links. Please update your Gmail on file.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($okToSend) {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $raw);

        $ins = $pdo->prepare('INSERT INTO account_invites (employee_id, token_hash, expires_at) VALUES (:employee_id, :token_hash, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
        $ins->execute([
            'employee_id' => $employeeId,
            'token_hash' => $hash,
        ]);

        $link = baseUrl() . '/pages/create_password.php?token=' . urlencode($raw);
        $subject = $isReset ? 'ERMS Password Reset' : 'ERMS Account Setup';
        $actionLabel = $isReset ? 'Reset Password' : 'Create Password';
        $actionVerb = $isReset ? 'reset' : 'create';
        $actionTitle = $isReset ? 'Reset your password' : 'Set up your account';
        $bodyText = "Hello,\n\nThis request was made for your ERMS account. Use the link below to "
            . $actionVerb
            . " your password:\n\n" . $link
            . "\n\nThis link is valid for 5 minutes.\n"
            . "Do not share this link with anyone.\n"
            . "If you did not request this, you can ignore this email.\n";
        $bodyHtml = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#1F2937;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F4F6FB;padding:32px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:620px;max-width:100%;background:#ffffff;border:1px solid #E5E7EB;border-radius:16px;overflow:hidden;">'
            . '<tr><td style="padding:16px 22px;background:#EEF2FF;border-bottom:1px solid #E0E7FF;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="font-weight:800;color:#1F2370;font-size:14px;letter-spacing:-0.2px;">ERMS</td>'
            . '<td align="right" style="font-size:11px;color:#6B7280;font-family:monospace;">SECURE ACCESS</td>'
            . '</tr></table>'
            . '</td></tr>'
            . '<tr><td style="padding:22px 22px 6px;">'
            . '<div style="font-size:18px;font-weight:800;color:#0F172A;letter-spacing:-0.2px;">' . $actionTitle . '</div>'
            . '<div style="margin-top:6px;font-size:13px;line-height:1.6;color:#475467;">This request was made for your ERMS account. Use the button below to ' . $actionVerb . ' your password.</div>'
            . '</td></tr>'
            . '<tr><td style="padding:10px 22px 0;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #E5E7EB;background:#F9FAFB;border-radius:12px;">'
            . '<tr><td style="padding:10px 12px;font-size:12px;color:#475467;">Action</td><td align="right" style="padding:10px 12px;font-size:12px;font-weight:700;color:#0F172A;">' . $actionLabel . '</td></tr>'
            . '<tr><td style="padding:10px 12px;font-size:12px;color:#475467;border-top:1px solid #E5E7EB;">Link expires</td><td align="right" style="padding:10px 12px;font-size:12px;font-weight:700;color:#0F172A;border-top:1px solid #E5E7EB;">5 minutes</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 22px 18px;">'
            . '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td align="center" bgcolor="#1F3A8A" style="border-radius:10px;">'
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES) . '" style="display:inline-block;padding:12px 20px;color:#ffffff;text-decoration:none;font-weight:800;font-size:14px;">' . $actionLabel . '</a>'
            . '</td></tr></table>'
            . '<div style="margin-top:12px;font-size:12px;color:#6B7280;line-height:1.5;">If the button does not work, copy and paste this link:</div>'
            . '<div style="margin-top:6px;font-size:12px;line-height:1.6;word-break:break-all;">'
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES) . '" style="color:#1F3A8A;text-decoration:underline;">' . htmlspecialchars($link, ENT_QUOTES) . '</a>'
            . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 22px;background:#F9FAFB;border-top:1px solid #F3F4F6;">'
            . '<div style="font-size:11px;color:#6B7280;line-height:1.6;">Do not share this link with anyone. If you did not request this email, you can ignore it.</div>'
            . '<div style="margin-top:6px;font-size:11px;color:#9CA3AF;">&copy; ' . date('Y') . ' ERMS</div>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';

        $cfg = loadMailConfig();
        $smtp = is_array($cfg['smtp'] ?? null) ? (array)$cfg['smtp'] : [];
        $hasSmtp = trim((string)($smtp['host'] ?? '')) !== ''
            && (int)($smtp['port'] ?? 0) > 0
            && trim((string)($smtp['username'] ?? '')) !== ''
            && trim((string)($smtp['app_password'] ?? '')) !== '';

        $sent = trySendViaPhpMailer($email, $subject, $bodyHtml, $bodyText);

        $headers = "From: ERMS <no-reply@localhost>\r\n";
        if (!$sent && !$hasSmtp) {
            try {
                $sent = @mail($email, $subject, $bodyText, $headers);
            } catch (Throwable $e) {
                $sent = false;
            }
        }

        if (!$sent) {
            logMailOutbox($email, $subject, $bodyText);
        }

        try {
            audit_setup($pdo, 'account_setup_requested', $employeeId, ['email_sent' => $sent ? 1 : 0, 'intent' => $intent]);
        } catch (Throwable $e2) {
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => $isReset
            ? 'If your User ID is eligible, a reset link has been sent to the Gmail on file.'
            : 'If your User ID is eligible, a setup link has been sent to the Gmail on file.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    try {
        $pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : db();
        audit_setup($pdo, 'account_setup_request_failed', $employeeId, ['reason' => 'server_error']);
    } catch (Throwable $e2) {
    }
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Request failed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
