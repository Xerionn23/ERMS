<?php
session_start();

require_once __DIR__ . '/../includes/db.php';

$token = trim((string)($_GET['token'] ?? ''));

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

    return rtrim((string)$base, '/');
}

$state = [
    'ok' => false,
    'error' => '',
    'done' => false,
];

$invite = null;

function audit_password(PDO $pdo, string $action, string $employeeId, $detail = null): void
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

try {
    if ($token !== '') {
        $pdo = db();
        $hash = hash('sha256', $token);

        $stmt = $pdo->prepare('SELECT id, employee_id, expires_at, used_at FROM account_invites WHERE token_hash = :h LIMIT 1');
        $stmt->execute(['h' => $hash]);
        $invite = $stmt->fetch();

        if (!$invite) {
            $state['error'] = 'Invalid or expired link.';
            audit_password($pdo, 'account_setup_token_invalid', '', null);
        } elseif (!empty($invite['used_at'])) {
            $state['error'] = 'This link has already been used.';
            audit_password($pdo, 'account_setup_token_used', (string)($invite['employee_id'] ?? ''), null);
        } else {
            $vstmt = $pdo->prepare('SELECT id FROM account_invites WHERE id = :id AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
            $vstmt->execute(['id' => (int)$invite['id']]);
            $valid = $vstmt->fetch();
            if (!$valid) {
                $state['error'] = 'This link has expired. Please request a new one.';
                audit_password($pdo, 'account_setup_token_expired', (string)($invite['employee_id'] ?? ''), null);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state['error'] === '') {
            $pw1 = (string)($_POST['password'] ?? '');
            $pw2 = (string)($_POST['password2'] ?? '');

            if ($pw1 === '' || $pw2 === '') {
                $state['error'] = 'Please enter your new password.';
            } elseif ($pw1 !== $pw2) {
                $state['error'] = 'Passwords do not match.';
            } elseif (strlen($pw1) < 6) {
                $state['error'] = 'Password must be at least 6 characters.';
            } else {
                $employeeId = (string)($invite['employee_id'] ?? '');

                $empStmt = $pdo->prepare('SELECT employee_id, full_name, email, starting_date, role, is_active FROM employees WHERE employee_id = :employee_id LIMIT 1');
                $empStmt->execute(['employee_id' => $employeeId]);
                $emp = $empStmt->fetch();

                if (!$emp || (int)($emp['is_active'] ?? 0) !== 1) {
                    $state['error'] = 'Employee is inactive or not found.';
                    audit_password($pdo, 'account_password_set_failed', $employeeId, ['reason' => 'employee_inactive_or_missing']);
                } else {
                    $role = (string)($emp['role'] ?? 'employee');
                    $fullName = (string)($emp['full_name'] ?? '');
                    $email = (string)($emp['email'] ?? '');
                    $startingDate = $emp['starting_date'] ?? null;

                    $pdo->beginTransaction();

                    $exists = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1');
                    $exists->execute(['employee_id' => $employeeId]);
                    $urow = $exists->fetch();

                    $ph = password_hash($pw1, PASSWORD_BCRYPT);

                    if ($urow) {
                        try {
                            $upd = $pdo->prepare('UPDATE users SET password_hash = :ph, role = :role, is_active = 1, deactivated_at = NULL WHERE employee_id = :employee_id');
                            $upd->execute([
                                'ph' => $ph,
                                'role' => $role,
                                'employee_id' => $employeeId,
                            ]);
                        } catch (Throwable $e) {
                            try {
                                $upd = $pdo->prepare('UPDATE users SET password_hash = :ph, role = :role, full_name = :full_name, email = :email, starting_date = :starting_date WHERE employee_id = :employee_id');
                                $upd->execute([
                                    'ph' => $ph,
                                    'role' => $role,
                                    'full_name' => $fullName,
                                    'email' => $email !== '' ? $email : null,
                                    'starting_date' => $startingDate !== '' ? $startingDate : null,
                                    'employee_id' => $employeeId,
                                ]);
                            } catch (Throwable $e2) {
                                $upd = $pdo->prepare('UPDATE users SET password_hash = :ph, role = :role WHERE employee_id = :employee_id');
                                $upd->execute([
                                    'ph' => $ph,
                                    'role' => $role,
                                    'employee_id' => $employeeId,
                                ]);
                            }
                        }
                    } else {
                        try {
                            $ins = $pdo->prepare('INSERT INTO users (employee_id, role, password_hash, is_active) VALUES (:employee_id, :role, :ph, 1)');
                            $ins->execute([
                                'employee_id' => $employeeId,
                                'role' => $role,
                                'ph' => $ph,
                            ]);
                        } catch (Throwable $e) {
                            try {
                                $ins = $pdo->prepare('INSERT INTO users (employee_id, full_name, email, starting_date, role, password_hash) VALUES (:employee_id, :full_name, :email, :starting_date, :role, :ph)');
                                $ins->execute([
                                    'employee_id' => $employeeId,
                                    'full_name' => $fullName,
                                    'email' => $email !== '' ? $email : null,
                                    'starting_date' => $startingDate !== '' ? $startingDate : null,
                                    'role' => $role,
                                    'ph' => $ph,
                                ]);
                            } catch (Throwable $e2) {
                                $ins = $pdo->prepare('INSERT INTO users (employee_id, role, password_hash) VALUES (:employee_id, :role, :ph)');
                                $ins->execute([
                                    'employee_id' => $employeeId,
                                    'role' => $role,
                                    'ph' => $ph,
                                ]);
                            }
                        }
                    }

                    $chk = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1');
                    $chk->execute(['employee_id' => $employeeId]);
                    $chkRow = $chk->fetch();
                    if (!$chkRow) {
                        throw new RuntimeException('Account creation failed. Please request a new link and try again.');
                    }

                    $mark = $pdo->prepare('UPDATE account_invites SET used_at = NOW() WHERE id = :id');
                    $mark->execute(['id' => (int)$invite['id']]);

                    $pdo->commit();

                    audit_password($pdo, 'account_password_set', $employeeId, null);

                    $state['ok'] = true;
                    $state['done'] = true;
                }
            }
        }
    } else {
        $state['error'] = 'Missing token.';
    }
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $e2) {
    }

    $state['error'] = 'Something went wrong. Please request a new link.';
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $empIdForLog = isset($invite) && is_array($invite) ? (string)($invite['employee_id'] ?? '') : '';
            audit_password($pdo, 'account_password_set_failed', $empIdForLog, ['reason' => 'server_error']);
        }
    } catch (Throwable $e3) {
    }
}

$loginUrl = baseUrl() . '/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ERMS — Create Password</title>
<link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --white:#ffffff;
  --gray-25:#FCFCFD;
  --gray-50:#F9FAFB;
  --gray-100:#F2F4F7;
  --gray-200:#E4E7EC;
  --gray-300:#D0D5DD;
  --gray-400:#98A2B3;
  --gray-500:#667085;
  --gray-700:#344054;
  --gray-900:#101828;
  --navy-50:#EEF4FF;
  --navy-200:#C7D7FD;
  --navy-500:#6172F3;
  --navy-700:#3538CD;
  --navy-800:#2D31A6;
  --error-50:#FEF3F2;
  --error-300:#FDA29B;
  --error-600:#D92D20;
  --success-50:#ECFDF3;
  --success-600:#039855;
  --f:'Plus Jakarta Sans',sans-serif;
  --mono:'JetBrains Mono',monospace;
}
html,body{height:100%;font-family:var(--f);-webkit-font-smoothing:antialiased;}
body{background:var(--gray-900);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;}
.card{width:100%;max-width:460px;background:var(--white);border-radius:20px;padding:34px 34px 30px;box-shadow:0 25px 50px rgba(0,0,0,0.4),0 0 0 1px rgba(255,255,255,0.05);}
.hd{text-align:center;margin-bottom:22px;}
.logo{width:52px;height:52px;border-radius:14px;background:var(--navy-700);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(53,56,205,0.4);overflow:hidden;}
.title{font-size:20px;font-weight:800;color:var(--gray-900);letter-spacing:-0.5px;margin-bottom:4px;}
.sub{font-size:13px;color:var(--gray-500);}
.form{display:flex;flex-direction:column;gap:14px;margin-top:18px;}
.fgrp{display:flex;flex-direction:column;gap:6px;}
.fl{font-size:12px;font-weight:600;color:var(--gray-700);}
.inp{width:100%;padding:10px 14px;background:var(--white);border:1.5px solid var(--gray-300);border-radius:10px;color:var(--gray-900);font-size:14px;outline:none;transition:all .15s;}
.inp:focus{border-color:var(--navy-500);box-shadow:0 0 0 4px rgba(97,114,243,0.1);}
.inp.err{border-color:var(--error-600);box-shadow:0 0 0 4px rgba(217,45,32,0.1);}
.btn{width:100%;padding:12px;border-radius:10px;background:var(--navy-700);color:#fff;font-size:14px;font-weight:800;border:none;cursor:pointer;transition:all .15s;}
.btn:hover{background:var(--navy-800);}
.msg{font-size:12px;font-weight:600;border-radius:10px;padding:10px 12px;}
.err{color:var(--error-600);background:var(--error-50);border:1px solid var(--error-300);}
.ok{color:var(--success-600);background:var(--success-50);border:1px solid rgba(3,152,85,0.2);}
.link{margin-top:14px;text-align:center;font-size:12px;}
.link a{color:var(--navy-700);text-decoration:none;font-weight:700;}
.link a:hover{text-decoration:underline;}
.mono{font-family:var(--mono);}
</style>
</head>
<body>
  <div class="card">
    <div class="hd">
      <div style="display:flex;justify-content:center;margin-bottom:14px;">
        <img src="../assets/img/erms-logo.svg" alt="ERMS" style="height:64;width:64"/>
      </div>
      <div class="title">Create your password</div>
      <div class="sub">Set a password to finish creating your account</div>
    </div>

    <?php if ($state['done'] && $state['ok']): ?>
      <div class="msg ok">Password saved. You can now sign in.</div>
      <div class="link"><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES); ?>">Go to Login</a></div>
    <?php else: ?>
      <?php if ($state['error'] !== ''): ?>
        <div class="msg err"><?php echo htmlspecialchars($state['error'], ENT_QUOTES); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES); ?>">Back to Login</a></div>
      <?php else: ?>
        <form class="form" method="post">
          <div class="fgrp">
            <label class="fl">New Password</label>
            <input class="inp" type="password" name="password" minlength="6" required />
          </div>
          <div class="fgrp">
            <label class="fl">Confirm Password</label>
            <input class="inp" type="password" name="password2" minlength="6" required />
          </div>
          <button class="btn" type="submit">Save Password</button>
          <?php if ($invite && !empty($invite['employee_id'])): ?>
            <div class="link">User ID: <span class="mono"><?php echo htmlspecialchars((string)$invite['employee_id'], ENT_QUOTES); ?></span></div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
