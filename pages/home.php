<?php
 require_once __DIR__ . '/../includes/guards.php';
 require_login();

 $role = (string)($_SESSION['user_role'] ?? '');

 if ($role === 'employee') {
     $_SESSION['company'] = 'brainmaster';
     header('Location: neuro_documents.php');
     exit;
 }

 if ($role === 'security_operation') {
     if (!isset($_SESSION['company'])) {
         $_SESSION['company'] = 'jubecer';
     }
 }

 if ($role === 'admin') {
     if (!isset($_SESSION['company'])) {
         header('Location: choose_company.php');
         exit;
     }
 }

 require_company();
 
 $company = (string)($_SESSION['company'] ?? '');
 $isBrainMaster = $company === 'brainmaster';
 $companyLabel = $isBrainMaster ? 'Brain Master' : 'Jubecer';
 
 $userName = (string)($_SESSION['user_name'] ?? 'User');
 $userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
 if ($userInitials === '') {
     $userInitials = 'U';
 }
 
 $requiredRequirements = ['SSS', 'PAG-IBIG', 'PhilHealth', 'License'];
 $requiredRequirementTypes = [];
 $jubecerSummary = [
     'total_guards' => 0,
     'guards_with_missing' => 0,
     'guards_with_expired_license' => 0,
     'guards_with_expiring_license' => 0,
 ];
 $jubecerLicenseAlerts = [];
 $jubecerGuards = [];
 $jubecerReports = null;
 
 if (!$isBrainMaster && $company === 'jubecer') {
     require_once __DIR__ . '/../includes/db.php';
     try {
         $pdo = db();

         $reqStmt = $pdo->query("SELECT id, code, name, expires FROM requirement_types WHERE is_required = 1 ORDER BY id");
         $requirementTypes = $reqStmt->fetchAll();
         if (is_array($requirementTypes) && count($requirementTypes) > 0) {
             $requiredRequirements = array_map(static fn($r) => (string)($r['name'] ?? ''), $requirementTypes);
             $requiredRequirements = array_values(array_filter($requiredRequirements, static fn($n) => $n !== ''));
             $requiredRequirementTypes = array_map(static fn($r) => [
                 'id' => (int)($r['id'] ?? 0),
                 'code' => (string)($r['code'] ?? ''),
                 'name' => (string)($r['name'] ?? ''),
                 'expires' => (int)($r['expires'] ?? 0) === 1,
             ], $requirementTypes);
         }
 
         $summarySql = "
 SELECT
     COUNT(*) AS total_guards,
     SUM(CASE WHEN t.missing_count > 0 THEN 1 ELSE 0 END) AS guards_with_missing,
     SUM(CASE WHEN t.expired_license > 0 THEN 1 ELSE 0 END) AS guards_with_expired_license,
     SUM(CASE WHEN t.expiring_license > 0 THEN 1 ELSE 0 END) AS guards_with_expiring_license
 FROM (
     SELECT
         g.id,
         SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count,
         SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license,
         SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license
     FROM guards g
     CROSS JOIN requirement_types rt
     LEFT JOIN guard_requirements gr
         ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
       WHERE rt.is_required = 1 AND g.status = 'active'
     GROUP BY g.id
 ) t
 ";
         $jubecerSummaryRow = $pdo->query($summarySql)->fetch(PDO::FETCH_ASSOC);
         if (is_array($jubecerSummaryRow)) {
             $jubecerSummary = array_merge($jubecerSummary, $jubecerSummaryRow);
         }
 
         $alertsSql =
             "SELECT\n" .
             "  g.id AS guard_id,\n" .
             "  g.full_name,\n" .
             "  g.guard_no,\n" .
             "  g.agency,\n" .
             "  gr.expiry_date,\n" .
             "  DATEDIFF(gr.expiry_date, CURDATE()) AS days_until_expiry,\n" .
             "  CASE\n" .
             "    WHEN gr.expiry_date < CURDATE() THEN 'Expired'\n" .
             "    WHEN gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 'Expiring'\n" .
             "    ELSE 'Valid'\n" .
             "  END AS alert_status\n" .
             "FROM guards g\n" .
             "JOIN requirement_types rt ON rt.code = 'SECURITY_LICENSE'\n" .
             "JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n" .
             "WHERE gr.expiry_date IS NOT NULL\n" .
           "  AND g.status = 'active'\n" .
             "  AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)\n" .
             "ORDER BY (gr.expiry_date < CURDATE()) DESC, gr.expiry_date ASC, g.full_name ASC\n" .
             "LIMIT 20";
         $jubecerLicenseAlerts = $pdo->query($alertsSql)->fetchAll();
 
         $listSql = "
 SELECT
     g.id,
     g.guard_no,
     g.last_name,
     g.first_name,
     g.middle_name,
     g.suffix,
     g.birthdate,
     g.age,
     g.agency,
     g.contact_no,
     g.deployed,
       g.status AS record_status,
     SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count,
     SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license,
     SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license,
     MAX(CASE WHEN rt.code = 'SECURITY_LICENSE' THEN gr.expiry_date ELSE NULL END) AS license_expiry_date
 FROM guards g
 CROSS JOIN requirement_types rt
 LEFT JOIN guard_requirements gr
     ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
     WHERE rt.is_required = 1 AND g.status = 'active'
 GROUP BY g.id
 ORDER BY g.last_name ASC, g.first_name ASC, g.id ASC
 ";
         $rows = $pdo->query($listSql)->fetchAll();
 
         $missingSql = "
 SELECT
     g.id AS guard_id,
     rt.name AS requirement_name
 FROM guards g
 JOIN requirement_types rt ON rt.is_required = 1
 LEFT JOIN guard_requirements gr
     ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id
     WHERE g.status = 'active' AND gr.id IS NULL
 ORDER BY g.id ASC, rt.id ASC
 ";
         $missingRows = $pdo->query($missingSql)->fetchAll();
         $missingByGuard = [];
         foreach ($missingRows as $mr) {
             $gid = (int)($mr['guard_id'] ?? 0);
             $nm = (string)($mr['requirement_name'] ?? '');
             if ($gid > 0 && $nm !== '') {
                 $missingByGuard[$gid][] = $nm;
             }
         }
 
         foreach ($rows as $r) {
             $gid = (int)($r['id'] ?? 0);
             $missingReqs = $missingByGuard[$gid] ?? [];
             $missingCount = (int)($r['missing_count'] ?? 0);
           $recordStatus = (string)($r['record_status'] ?? 'active');
 
             $status = 'VALID';
             if ((int)($r['expired_license'] ?? 0) > 0) {
                 $status = 'EXPIRED';
             } elseif ((int)($r['expiring_license'] ?? 0) > 0) {
                 $status = 'EXPIRING';
             } elseif ($missingCount > 0) {
                 $status = 'MISSING';
             }
 
             $jubecerGuards[] = [
                 'id' => $gid,
                 'no' => (string)($r['guard_no'] ?? ''),
                 'last' => (string)($r['last_name'] ?? ''),
                 'first' => (string)($r['first_name'] ?? ''),
                 'mid' => (string)($r['middle_name'] ?? ''),
                 'suffix' => (string)($r['suffix'] ?? ''),
                 'agency' => (string)($r['agency'] ?? ''),
                 'contact' => (string)($r['contact_no'] ?? ''),
                 'deployed' => (string)($r['deployed'] ?? ''),
                 'bday' => (string)($r['birthdate'] ?? ''),
                 'age' => (int)($r['age'] ?? 0),
               'recordStatus' => $recordStatus,
                 'status' => $status,
                 'expDate' => (string)($r['license_expiry_date'] ?? ''),
                 'missing' => $missingCount,
                 'missingReqs' => $missingReqs,
             ];
         }

         if ($role === 'admin') {
             $expSql =
                 "SELECT\n" .
                 "  g.id AS guard_id,\n" .
                 "  g.guard_no,\n" .
                 "  g.full_name,\n" .
                 "  g.agency,\n" .
                 "  gr.expiry_date,\n" .
                 "  DATEDIFF(gr.expiry_date, CURDATE()) AS days_until_expiry\n" .
                 "FROM guards g\n" .
                 "JOIN requirement_types rt ON rt.code = 'SECURITY_LICENSE'\n" .
                 "JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n" .
               "WHERE g.status = 'active'\n" .
               "  AND gr.expiry_date IS NOT NULL\n" .
                 "ORDER BY gr.expiry_date ASC, g.full_name ASC\n" .
                 "LIMIT 250";
             $expRows = $pdo->query($expSql)->fetchAll();

             $missSql =
                 "SELECT\n" .
                 "  g.id AS guard_id,\n" .
                 "  g.guard_no,\n" .
                 "  g.full_name,\n" .
                 "  g.agency,\n" .
                 "  rt.name AS requirement_name\n" .
                 "FROM guards g\n" .
                 "JOIN requirement_types rt ON rt.is_required = 1\n" .
                 "LEFT JOIN guard_requirements gr\n" .
                 "  ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n" .
               "WHERE g.status = 'active'\n" .
               "  AND gr.id IS NULL\n" .
                 "ORDER BY g.full_name ASC, rt.id ASC";
             $missRows = $pdo->query($missSql)->fetchAll();
             $missBy = [];
             foreach ($missRows as $mr) {
                 $gid = (int)($mr['guard_id'] ?? 0);
                 if ($gid <= 0) {
                     continue;
                 }
                 if (!isset($missBy[$gid])) {
                     $missBy[$gid] = [
                         'id' => $gid,
                         'no' => (string)($mr['guard_no'] ?? ''),
                         'name' => (string)($mr['full_name'] ?? ''),
                         'agency' => (string)($mr['agency'] ?? ''),
                         'missingReqs' => [],
                     ];
                 }
                 $nm = (string)($mr['requirement_name'] ?? '');
                 if ($nm !== '') {
                     $missBy[$gid]['missingReqs'][] = $nm;
                 }
             }
             $missList = array_values($missBy);
             usort($missList, static function ($a, $b) {
                 $ac = is_array($a['missingReqs'] ?? null) ? count($a['missingReqs']) : 0;
                 $bc = is_array($b['missingReqs'] ?? null) ? count($b['missingReqs']) : 0;
                 if ($ac === $bc) {
                     return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                 }
                 return $bc <=> $ac;
             });
             $missList = array_slice($missList, 0, 200);

             $agencySql =
                 "SELECT\n" .
                 "  g.agency,\n" .
                 "  COUNT(*) AS total_guards,\n" .
                 "  SUM(CASE WHEN t.missing_count > 0 THEN 1 ELSE 0 END) AS guards_with_missing,\n" .
                 "  SUM(CASE WHEN t.expired_license > 0 THEN 1 ELSE 0 END) AS guards_with_expired_license,\n" .
                 "  SUM(CASE WHEN t.expiring_license > 0 THEN 1 ELSE 0 END) AS guards_with_expiring_license\n" .
                 "FROM (\n" .
                 "  SELECT\n" .
                 "    g.id,\n" .
                 "    g.agency,\n" .
                 "    SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count,\n" .
                 "    SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license,\n" .
                 "    SUM(CASE WHEN rt.code = 'SECURITY_LICENSE' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license\n" .
                 "  FROM guards g\n" .
                 "  CROSS JOIN requirement_types rt\n" .
                 "  LEFT JOIN guard_requirements gr\n" .
                 "    ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id\n" .
                 "  WHERE rt.is_required = 1\n" .
                 "  GROUP BY g.id\n" .
                 ") t\n" .
                 "JOIN guards g ON g.id = t.id\n" .
                 "GROUP BY g.agency\n" .
                 "ORDER BY total_guards DESC, g.agency ASC";
             $agencyRows = $pdo->query($agencySql)->fetchAll();

             $jubecerReports = [
                 'generatedAt' => date('c'),
                 'licenseExpiries' => $expRows,
                 'missingByGuard' => $missList,
                 'agencySummary' => $agencyRows,
             ];
         }
     } catch (Throwable $e) {
         error_log('home.php Jubecer dashboard load failed: ' . $e->getMessage());
         $jubecerSummary = [
             'total_guards' => 0,
             'guards_with_missing' => 0,
             'guards_with_expired_license' => 0,
             'guards_with_expiring_license' => 0,
         ];
         $jubecerLicenseAlerts = [];
         $jubecerGuards = [];
         $requiredRequirementTypes = [];
         $jubecerReports = null;
     }
 }

 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api'])) {
     header('Content-Type: application/json; charset=utf-8');

     if ($company !== 'jubecer') {
         http_response_code(403);
         echo json_encode(['ok' => false, 'error' => 'Unsupported company.'], JSON_UNESCAPED_UNICODE);
         exit;
     }

     require_once __DIR__ . '/../includes/db.php';
     $pdo = db();
     $api = (string)($_POST['api'] ?? '');

     $auditLog = static function (PDO $pdo, string $action, ?string $targetType = null, ?string $targetId = null, $detail = null): void {
         try {
             $actorEmpId = (string)($_SESSION['user_employee_id'] ?? '');
             $actorUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
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
                 'actor_employee_id' => $actorEmpId !== '' ? $actorEmpId : null,
                 'actor_user_id' => $actorUserId,
                 'action' => $action,
                 'target_type' => $targetType,
                 'target_id' => $targetId,
                 'detail' => $detailJson,
                 'ip_address' => $ip !== '' ? $ip : null,
                 'user_agent' => $ua !== '' ? substr($ua, 0, 255) : null,
             ]);
         } catch (Throwable $e) {
             // Do not break main flow if audit logging fails.
         }
     };

    try {
        if (
            $api === 'list_users'
            || $api === 'list_employees'
            || $api === 'create_employee'
            || $api === 'update_employee'
            || $api === 'toggle_employee_active'
            || $api === 'create_user'
            || $api === 'update_user'
            || $api === 'toggle_user_active'
            || $api === 'delete_user'
            || $api === 'sync_users_from_employees'
            || $api === 'list_audit_logs'
        ) {
            if (!isset($_SESSION['user_role']) || (string)$_SESSION['user_role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Unauthorized.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($api === 'list_audit_logs') {
            $q = trim((string)($_POST['q'] ?? ''));
            $action = trim((string)($_POST['action'] ?? ''));
            $actor = trim((string)($_POST['actor_employee_id'] ?? ''));
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per = isset($_POST['per']) ? (int)$_POST['per'] : 25;
            if ($page < 1) {
                $page = 1;
            }
            if ($per < 5) {
                $per = 5;
            }
            if ($per > 100) {
                $per = 100;
            }
            $offset = ($page - 1) * $per;

            $where = [];
            $params = [];

            if ($action !== '') {
                $where[] = 'al.action = :action';
                $params['action'] = $action;
            }
            if ($actor !== '') {
                $where[] = 'al.actor_employee_id = :actor_employee_id';
                $params['actor_employee_id'] = $actor;
            }
            if ($q !== '') {
                $where[] = '(al.target_id LIKE :q OR al.detail LIKE :q OR al.action LIKE :q OR al.actor_employee_id LIKE :q)';
                $params['q'] = '%' . $q . '%';
            }

            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM audit_logs al ' . $whereSql);
            $countStmt->execute($params);
            $total = (int)(($countStmt->fetch())['c'] ?? 0);

            $sql =
                'SELECT al.id, al.actor_employee_id, al.actor_user_id, al.action, al.target_type, al.target_id, al.detail, al.ip_address, al.user_agent, al.created_at '
                . 'FROM audit_logs al '
                . $whereSql . ' '
                . 'ORDER BY al.id DESC '
                . 'LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            echo json_encode([
                'ok' => true,
                'logs' => $rows,
                'total' => $total,
                'page' => $page,
                'per' => $per,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'sync_users_from_employees') {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE users u '
                    . 'JOIN employees e ON e.employee_id = u.employee_id '
                    . 'SET u.full_name = e.full_name, u.email = e.email, u.starting_date = e.starting_date'
                );
                $stmt->execute();
                $updated = $stmt->rowCount();
                echo json_encode(['ok' => true, 'updated' => $updated, 'message' => 'User data synced from employees.'], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Sync failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($api === 'list_users') {
            // Get filter parameter (default: active only)
            $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'active';
            
            try {
                $sql = 'SELECT '
                    . 'u.id, u.employee_id, u.role, u.is_active, u.deactivated_at, u.created_at, u.updated_at, '
                    . 'e.full_name, e.email, e.starting_date '
                    . 'FROM users u '
                    . 'LEFT JOIN employees e ON e.employee_id = u.employee_id ';
                
                // Filter by status
                if ($statusFilter === 'active') {
                    $sql .= 'WHERE u.is_active = 1 ';
                } elseif ($statusFilter === 'inactive') {
                    $sql .= 'WHERE u.is_active = 0 ';
                }
                
                $sql .= 'ORDER BY u.created_at DESC, u.id DESC';
                
                $rows = $pdo->query($sql)->fetchAll();
            } catch (Throwable $e) {
                // Fallback without is_active filter if column doesn't exist
                try {
                    $rows = $pdo
                        ->query(
                            'SELECT '
                            . 'u.id, u.employee_id, u.role, u.is_active, u.created_at, u.updated_at, '
                            . 'e.full_name, e.email, e.starting_date '
                            . 'FROM users u '
                            . 'LEFT JOIN employees e ON e.employee_id = u.employee_id '
                            . 'ORDER BY u.created_at DESC, u.id DESC'
                        )
                        ->fetchAll();
                } catch (Throwable $e2) {
                    try {
                        $rows = $pdo
                            ->query(
                                'SELECT '
                                . 'u.id, u.employee_id, u.role, '
                                . '1 AS is_active, NULL AS deactivated_at, NULL AS created_at, NULL AS updated_at, '
                                . 'e.full_name, e.email, e.starting_date '
                                . 'FROM users u '
                                . 'LEFT JOIN employees e ON e.employee_id = u.employee_id '
                                . 'ORDER BY u.id DESC'
                            )
                            ->fetchAll();
                    } catch (Throwable $e3) {
                        $rows = $pdo
                            ->query(
                                "SELECT id, employee_id, role, 1 AS is_active, NULL AS deactivated_at, NULL AS created_at, NULL AS updated_at, '' AS full_name, '' AS email, '' AS starting_date FROM users ORDER BY id DESC"
                            )
                            ->fetchAll();
                    }
                }
            }
            echo json_encode(['ok' => true, 'users' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'list_employees') {
          $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'active';

          try {
            $sql = 'SELECT id, employee_id, full_name, email, starting_date, role, is_active, deactivated_at, created_at, updated_at FROM employees ';

            if ($statusFilter === 'active') {
              $sql .= 'WHERE is_active = 1 ';
            } elseif ($statusFilter === 'inactive') {
              $sql .= 'WHERE is_active = 0 ';
            }

            $sql .= 'ORDER BY created_at DESC, id DESC';
            $rows = $pdo->query($sql)->fetchAll();
          } catch (Throwable $e) {
            // Fallback for schemas missing the is_active/deactivated_at columns
            try {
              $rows = $pdo
                ->query('SELECT id, employee_id, full_name, email, starting_date, role, 1 AS is_active, NULL AS deactivated_at, created_at, updated_at FROM employees ORDER BY created_at DESC, id DESC')
                ->fetchAll();
            } catch (Throwable $e2) {
              $rows = $pdo
                ->query("SELECT id, employee_id, full_name, email, starting_date, role, 1 AS is_active, NULL AS deactivated_at, NULL AS created_at, NULL AS updated_at FROM employees ORDER BY id DESC")
                ->fetchAll();
            }
          }
            echo json_encode(['ok' => true, 'employees' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'create_employee') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $startingDateRaw = trim((string)($_POST['starting_date'] ?? ''));
            $startingDate = $startingDateRaw !== '' ? $startingDateRaw : null;
            $roleIn = trim((string)($_POST['role'] ?? ''));

            if ($fullName === '' || $roleIn === '') {
                throw new RuntimeException('Please complete all required fields.');
            }

            $roleAllowed = ['admin', 'security_operation', 'employee'];
            if (!in_array($roleIn, $roleAllowed, true)) {
                throw new RuntimeException('Invalid role.');
            }

            $prefix = 'EMP';
            if ($roleIn === 'admin') {
                $prefix = 'ADMIN';
            } elseif ($roleIn === 'security_operation') {
                $prefix = 'SO';
            }

            $max = $pdo->prepare('SELECT employee_id FROM employees WHERE employee_id LIKE :p ORDER BY id DESC LIMIT 1');
            $max->execute(['p' => $prefix . '-%']);
            $last = (string)(($max->fetch())['employee_id'] ?? '');
            $n = 1;
            if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
                $n = (int)$m[1] + 1;
            }
            $employeeId = sprintf('%s-%03d', $prefix, $n);

            $exists = $pdo->prepare('SELECT id FROM employees WHERE employee_id = :employee_id LIMIT 1');
            $exists->execute(['employee_id' => $employeeId]);
            if ($exists->fetch()) {
                throw new RuntimeException('Failed to generate Employee ID. Please try again.');
            }

            $stmt = $pdo->prepare('INSERT INTO employees (employee_id, full_name, email, starting_date, role, is_active) VALUES (:employee_id, :full_name, :email, :starting_date, :role, :is_active)');
            $stmt->execute([
                'employee_id' => $employeeId,
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'starting_date' => $startingDate,
                'role' => $roleIn,
                'is_active' => 1,
            ]);

            $auditLog($pdo, 'create_employee', 'employee', $employeeId, ['role' => $roleIn]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'update_employee') {
            $empId = isset($_POST['employee_id']) ? trim((string)$_POST['employee_id']) : '';
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $startingDateRaw = trim((string)($_POST['starting_date'] ?? ''));
            $startingDate = $startingDateRaw !== '' ? $startingDateRaw : null;
            $roleIn = trim((string)($_POST['role'] ?? ''));

            if ($empId === '' || $fullName === '' || $roleIn === '') {
                throw new RuntimeException('Invalid request.');
            }

            $roleAllowed = ['admin', 'security_operation', 'employee'];
            if (!in_array($roleIn, $roleAllowed, true)) {
                throw new RuntimeException('Invalid role.');
            }

            $stmt = $pdo->prepare('UPDATE employees SET full_name = :full_name, email = :email, starting_date = :starting_date, role = :role WHERE employee_id = :employee_id');
            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'starting_date' => $startingDate,
                'role' => $roleIn,
                'employee_id' => $empId,
            ]);

            $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE employee_id = :employee_id');
            $stmt->execute(['role' => $roleIn, 'employee_id' => $empId]);

            $auditLog($pdo, 'update_employee', 'employee', $empId, ['role' => $roleIn]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'toggle_employee_active') {
            $empId = isset($_POST['employee_id']) ? trim((string)$_POST['employee_id']) : '';
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            if ($empId === '') {
                throw new RuntimeException('Invalid employee.');
            }

            $currentEmpId = (string)($_SESSION['user_employee_id'] ?? '');
            if ($currentEmpId !== '' && $empId === $currentEmpId && $isActive !== 1) {
                throw new RuntimeException('You cannot deactivate your own employee record.');
            }

            if ($isActive !== 1) {
                $cur = $pdo->prepare('SELECT role, is_active FROM employees WHERE employee_id = :employee_id LIMIT 1');
                $cur->execute(['employee_id' => $empId]);
                $row = $cur->fetch();
                if ($row && (string)($row['role'] ?? '') === 'admin' && (int)($row['is_active'] ?? 0) === 1) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM employees WHERE role = 'admin' AND is_active = 1 AND employee_id <> :employee_id");
                    $stmt->execute(['employee_id' => $empId]);
                    $cnt = (int)(($stmt->fetch())['c'] ?? 0);
                    if ($cnt <= 0) {
                        throw new RuntimeException('You must keep at least one active Administrator.');
                    }
                }
            }

            $stmt = $pdo->prepare('UPDATE employees SET is_active = :is_active, deactivated_at = :deactivated_at WHERE employee_id = :employee_id');
            $stmt->execute([
                'is_active' => $isActive === 1 ? 1 : 0,
                'deactivated_at' => $isActive === 1 ? null : date('Y-m-d H:i:s'),
                'employee_id' => $empId,
            ]);

            // Auto-deactivate/activate corresponding user account (with fallback for old schema)
            try {
                $userStmt = $pdo->prepare('UPDATE users SET is_active = :is_active, deactivated_at = :deactivated_at WHERE employee_id = :employee_id');
                $userStmt->execute([
                    'is_active' => $isActive === 1 ? 1 : 0,
                    'deactivated_at' => $isActive === 1 ? null : date('Y-m-d H:i:s'),
                    'employee_id' => $empId,
                ]);
            } catch (Throwable $e) {
                // Try without deactivated_at if column doesn't exist
                try {
                    $userStmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE employee_id = :employee_id');
                    $userStmt->execute([
                        'is_active' => $isActive === 1 ? 1 : 0,
                        'employee_id' => $empId,
                    ]);
                } catch (Throwable $e2) {
                    // Old schema without is_active - skip auto-deactivation
                }
            }

            $auditLog($pdo, 'toggle_employee_active', 'employee', $empId, ['is_active' => $isActive === 1 ? 1 : 0]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'create_user') {
            $employeeId = trim((string)($_POST['employee_id'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($employeeId === '' || $password === '') {
                throw new RuntimeException('Please complete all required fields.');
            }

            if (strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }

            $emp = $pdo->prepare('SELECT employee_id, role, is_active FROM employees WHERE employee_id = :employee_id LIMIT 1');
            $emp->execute(['employee_id' => $employeeId]);
            $erow = $emp->fetch();
            if (!$erow) {
                throw new RuntimeException('Employee not found. Add the employee first.');
            }
            if ((int)($erow['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Employee is inactive.');
            }
            $roleIn = (string)($erow['role'] ?? 'employee');

            $exists = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1');
            $exists->execute(['employee_id' => $employeeId]);
            if ($exists->fetch()) {
                throw new RuntimeException('Account already exists for this employee.');
            }

            $stmt = $pdo->prepare('INSERT INTO users (employee_id, role, password_hash, is_active) VALUES (:employee_id, :role, :password_hash, :is_active)');
            $stmt->execute([
                'employee_id' => $employeeId,
                'role' => $roleIn,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'is_active' => 1,
            ]);

            $auditLog($pdo, 'create_user', 'user', $employeeId, ['role' => $roleIn]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'delete_user') {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user.');
            }

            $currentId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($currentId > 0 && $userId === $currentId) {
                throw new RuntimeException('You cannot delete your own account.');
            }

            $cur = $pdo->prepare('SELECT role, is_active FROM users WHERE id = :id LIMIT 1');
            $cur->execute(['id' => $userId]);
            $row = $cur->fetch();
            if (!$row) {
                throw new RuntimeException('User not found.');
            }

            if ((string)($row['role'] ?? '') === 'admin' && (int)($row['is_active'] ?? 0) === 1) {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id");
                $stmt->execute(['id' => $userId]);
                $cnt = (int)(($stmt->fetch())['c'] ?? 0);
                if ($cnt <= 0) {
                    throw new RuntimeException('You must keep at least one active Administrator.');
                }
            }

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);

            $auditLog($pdo, 'delete_user', 'user', (string)$userId, null);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'update_user') {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $password = (string)($_POST['password'] ?? '');

            if ($userId <= 0) {
                throw new RuntimeException('Invalid request.');
            }

            if ($password !== '' && strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }

            $current = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
            $current->execute(['id' => $userId]);
            $cur = $current->fetch();
            if (!$cur) {
                throw new RuntimeException('User not found.');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'id' => $userId,
                ]);
            }

            $auditLog($pdo, 'update_user', 'user', (string)$userId, ['password_changed' => ($password !== '')]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($api === 'toggle_user_active') {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

            if ($userId <= 0) {
                throw new RuntimeException('Invalid user.');
            }

            $currentId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($currentId > 0 && $userId === $currentId && $isActive !== 1) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }

            if ($isActive !== 1) {
                $cur = $pdo->prepare('SELECT role, is_active FROM users WHERE id = :id LIMIT 1');
                $cur->execute(['id' => $userId]);
                $row = $cur->fetch();
                if ($row && (string)($row['role'] ?? '') === 'admin' && (int)($row['is_active'] ?? 0) === 1) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id");
                    $stmt->execute(['id' => $userId]);
                    $cnt = (int)(($stmt->fetch())['c'] ?? 0);
                    if ($cnt <= 0) {
                        throw new RuntimeException('You must keep at least one active Administrator.');
                    }
                }
            }

            $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active, deactivated_at = :deactivated_at WHERE id = :id');
            $stmt->execute([
                'is_active' => $isActive === 1 ? 1 : 0,
                'deactivated_at' => $isActive === 1 ? null : date('Y-m-d H:i:s'),
                'id' => $userId,
            ]);

            $auditLog($pdo, 'toggle_user_active', 'user', (string)$userId, ['is_active' => $isActive === 1 ? 1 : 0]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

          if ($api === 'list_guards') {
            $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'active';
            $allowed = ['active', 'inactive', 'all'];
            if (!in_array($statusFilter, $allowed, true)) {
              $statusFilter = 'active';
            }

            $where = 'WHERE rt.is_required = 1';
            $params = [];
            if ($statusFilter === 'active' || $statusFilter === 'inactive') {
              $where .= ' AND g.status = :gstatus';
              $params['gstatus'] = $statusFilter;
            }

            $listSql =
              'SELECT '
              . 'g.id, g.guard_no, g.last_name, g.first_name, g.middle_name, g.suffix, g.birthdate, g.age, g.agency, g.contact_no, g.deployed, g.status AS record_status, '
              . 'SUM(CASE WHEN gr.id IS NULL THEN 1 ELSE 0 END) AS missing_count, '
              . 'SUM(CASE WHEN rt.code = \'SECURITY_LICENSE\' AND gr.expiry_date IS NOT NULL AND gr.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_license, '
              . 'SUM(CASE WHEN rt.code = \'SECURITY_LICENSE\' AND gr.expiry_date IS NOT NULL AND gr.expiry_date >= CURDATE() AND gr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS expiring_license, '
              . 'MAX(CASE WHEN rt.code = \'SECURITY_LICENSE\' THEN gr.expiry_date ELSE NULL END) AS license_expiry_date '
              . 'FROM guards g '
              . 'CROSS JOIN requirement_types rt '
              . 'LEFT JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id '
              . $where . ' '
              . 'GROUP BY g.id '
              . 'ORDER BY g.last_name ASC, g.first_name ASC, g.id ASC';

            $stmt = $pdo->prepare($listSql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $missingWhere = 'WHERE rt.is_required = 1 AND gr.id IS NULL';
            $missingParams = [];
            if ($statusFilter === 'active' || $statusFilter === 'inactive') {
              $missingWhere .= ' AND g.status = :gstatus';
              $missingParams['gstatus'] = $statusFilter;
            }
            $missingSql =
              'SELECT g.id AS guard_id, rt.name AS requirement_name '
              . 'FROM guards g '
              . 'JOIN requirement_types rt ON rt.is_required = 1 '
              . 'LEFT JOIN guard_requirements gr ON gr.guard_id = g.id AND gr.requirement_type_id = rt.id '
              . $missingWhere . ' '
              . 'ORDER BY g.id ASC, rt.id ASC';
            $mstmt = $pdo->prepare($missingSql);
            $mstmt->execute($missingParams);
            $missingRows = $mstmt->fetchAll();

            $missingByGuard = [];
            foreach ($missingRows as $mr) {
              $gid = (int)($mr['guard_id'] ?? 0);
              $nm = (string)($mr['requirement_name'] ?? '');
              if ($gid > 0 && $nm !== '') {
                $missingByGuard[$gid][] = $nm;
              }
            }

            $out = [];
            foreach ($rows as $r) {
              $gid = (int)($r['id'] ?? 0);
              $missingReqs = $missingByGuard[$gid] ?? [];
              $missingCount = (int)($r['missing_count'] ?? 0);
              $recordStatus = (string)($r['record_status'] ?? 'active');

              $status = 'VALID';
              if ((int)($r['expired_license'] ?? 0) > 0) {
                $status = 'EXPIRED';
              } elseif ((int)($r['expiring_license'] ?? 0) > 0) {
                $status = 'EXPIRING';
              } elseif ($missingCount > 0) {
                $status = 'MISSING';
              }

              $out[] = [
                'id' => $gid,
                'no' => (string)($r['guard_no'] ?? ''),
                'last' => (string)($r['last_name'] ?? ''),
                'first' => (string)($r['first_name'] ?? ''),
                'mid' => (string)($r['middle_name'] ?? ''),
                'suffix' => (string)($r['suffix'] ?? ''),
                'agency' => (string)($r['agency'] ?? ''),
                'contact' => (string)($r['contact_no'] ?? ''),
                'deployed' => (string)($r['deployed'] ?? ''),
                'bday' => (string)($r['birthdate'] ?? ''),
                'age' => (int)($r['age'] ?? 0),
                'recordStatus' => $recordStatus,
                'status' => $status,
                'expDate' => (string)($r['license_expiry_date'] ?? ''),
                'missing' => $missingCount,
                'missingReqs' => $missingReqs,
              ];
            }

            echo json_encode(['ok' => true, 'guards' => $out], JSON_UNESCAPED_UNICODE);
            exit;
          }

          if ($api === 'toggle_guard_active') {
            $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            if ($guardId <= 0) {
              throw new RuntimeException('Invalid guard.');
            }

            $newStatus = $isActive === 1 ? 'active' : 'inactive';
            $stmt = $pdo->prepare('UPDATE guards SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $newStatus, 'id' => $guardId]);

            $auditLog($pdo, 'toggle_guard_active', 'guard', (string)$guardId, ['status' => $newStatus]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
          }

        if ($api === 'get_guard_requirements') {
            $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
            if ($guardId <= 0) {
                throw new RuntimeException('Invalid guard.');
            }

             $types = $pdo->query('SELECT id, code, name, expires FROM requirement_types WHERE is_required = 1 ORDER BY id')->fetchAll();
             $reqs = $pdo->prepare(
                 'SELECT requirement_type_id, document_no, issued_date, expiry_date, document_path, document_original_name '
                 . 'FROM guard_requirements WHERE guard_id = :guard_id'
             );
             $reqs->execute(['guard_id' => $guardId]);
             $rows = $reqs->fetchAll();
             $byType = [];
             foreach ($rows as $r) {
                 $tid = (int)($r['requirement_type_id'] ?? 0);
                 if ($tid > 0) {
                     $byType[$tid] = [
                         'document_no' => (string)($r['document_no'] ?? ''),
                         'issued_date' => (string)($r['issued_date'] ?? ''),
                         'expiry_date' => (string)($r['expiry_date'] ?? ''),
                         'document_path' => (string)($r['document_path'] ?? ''),
                         'document_original_name' => (string)($r['document_original_name'] ?? ''),
                     ];
                 }
             }

             $out = [];
             foreach ($types as $t) {
                 $tid = (int)($t['id'] ?? 0);
                 $out[] = [
                     'id' => $tid,
                     'code' => (string)($t['code'] ?? ''),
                     'name' => (string)($t['name'] ?? ''),
                     'expires' => (int)($t['expires'] ?? 0) === 1,
                     'value' => $byType[$tid] ?? null,
                 ];
             }

             echo json_encode(['ok' => true, 'requirements' => $out], JSON_UNESCAPED_UNICODE);
             exit;
         }

         if ($api === 'update_guard') {
             $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
             if ($guardId <= 0) {
                 throw new RuntimeException('Invalid guard.');
             }

             $last = trim((string)($_POST['last_name'] ?? ''));
             $first = trim((string)($_POST['first_name'] ?? ''));
             $mid = trim((string)($_POST['middle_name'] ?? ''));
             $suffix = trim((string)($_POST['suffix'] ?? ''));
             $agency = trim((string)($_POST['agency'] ?? ''));
             $contact = trim((string)($_POST['contact_no'] ?? ''));
             $deployed = trim((string)($_POST['deployed'] ?? ''));
             $birthdateRaw = trim((string)($_POST['birthdate'] ?? ''));
             $birthdate = $birthdateRaw !== '' ? $birthdateRaw : null;
             $deployedDate = $deployed !== '' ? $deployed : null;
             $ageRaw = trim((string)($_POST['age'] ?? ''));
             $age = null;
             if ($birthdate !== null) {
               try {
                 $dob = new DateTime($birthdate);
                 $today = new DateTime('today');
                 $age = (int)$dob->diff($today)->y;
               } catch (Throwable $e) {
                 $age = null;
               }
             }
             if ($age === null && $ageRaw !== '' && ctype_digit($ageRaw)) {
               $age = (int)$ageRaw;
             }

             if ($last === '' || $first === '') {
                 throw new RuntimeException('Last Name and First Name are required.');
             }

             $parts = [];
             if ($last !== '') {
                 $parts[] = $last . ',';
             }
             if ($first !== '') {
                 $parts[] = $first;
             }
             if ($mid !== '') {
                 $parts[] = $mid;
             }
             if ($suffix !== '') {
                 $parts[] = $suffix;
             }
             $full = trim(implode(' ', $parts));

             $stmt = $pdo->prepare(
                 'UPDATE guards '
                 . 'SET last_name = :last_name, first_name = :first_name, middle_name = :middle_name, suffix = :suffix, '
                 . 'birthdate = :birthdate, age = :age, agency = :agency, full_name = :full_name, contact_no = :contact_no, deployed = :deployed '
                 . 'WHERE id = :id'
             );
             $stmt->execute([
                 'last_name' => $last,
                 'first_name' => $first,
                 'middle_name' => $mid !== '' ? $mid : null,
                 'suffix' => $suffix !== '' ? $suffix : null,
                 'birthdate' => $birthdate,
                 'age' => $age,
                 'agency' => $agency !== '' ? $agency : null,
                 'full_name' => $full,
                 'contact_no' => $contact !== '' ? $contact : null,
                 'deployed' => $deployedDate,
                 'id' => $guardId,
             ]);

             echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
             exit;
         }

         if ($api === 'create_guard') {
             $last = trim((string)($_POST['last_name'] ?? ''));
             $first = trim((string)($_POST['first_name'] ?? ''));
             $mid = trim((string)($_POST['middle_name'] ?? ''));
             $suffix = trim((string)($_POST['suffix'] ?? ''));
             $agency = trim((string)($_POST['agency'] ?? ''));
             $contact = trim((string)($_POST['contact_no'] ?? ''));
             $deployed = trim((string)($_POST['deployed'] ?? ''));
             $birthdateRaw = trim((string)($_POST['birthdate'] ?? ''));
             $birthdate = $birthdateRaw !== '' ? $birthdateRaw : null;
             $deployedDate = $deployed !== '' ? $deployed : null;
             $ageRaw = trim((string)($_POST['age'] ?? ''));
             $age = null;
             if ($birthdate !== null) {
               try {
                 $dob = new DateTime($birthdate);
                 $today = new DateTime('today');
                 $age = (int)$dob->diff($today)->y;
               } catch (Throwable $e) {
                 $age = null;
               }
             }
             if ($age === null && $ageRaw !== '' && ctype_digit($ageRaw)) {
               $age = (int)$ageRaw;
             }

             if ($last === '' || $first === '') {
                 throw new RuntimeException('Last Name and First Name are required.');
             }

             $parts = [];
             if ($last !== '') {
                 $parts[] = $last . ',';
             }
             if ($first !== '') {
                 $parts[] = $first;
             }
             if ($mid !== '') {
                 $parts[] = $mid;
             }
             if ($suffix !== '') {
                 $parts[] = $suffix;
             }
             $full = trim(implode(' ', $parts));

             $stmt = $pdo->prepare(
                 'INSERT INTO guards (guard_no, last_name, first_name, middle_name, suffix, birthdate, age, agency, full_name, contact_no, deployed) '
                 . 'VALUES (NULL, :last_name, :first_name, :middle_name, :suffix, :birthdate, :age, :agency, :full_name, :contact_no, :deployed)'
             );
             $stmt->execute([
                 'last_name' => $last,
                 'first_name' => $first,
                 'middle_name' => $mid !== '' ? $mid : null,
                 'suffix' => $suffix !== '' ? $suffix : null,
                 'birthdate' => $birthdate,
                 'age' => $age,
                 'agency' => $agency !== '' ? $agency : null,
                 'full_name' => $full,
                 'contact_no' => $contact !== '' ? $contact : null,
                 'deployed' => $deployedDate,
             ]);

             $newId = (int)$pdo->lastInsertId();
             $guardNo = 'JG-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);

             $upd = $pdo->prepare('UPDATE guards SET guard_no = :guard_no WHERE id = :id');
             $upd->execute(['guard_no' => $guardNo, 'id' => $newId]);

             echo json_encode(['ok' => true, 'guard_id' => $newId, 'guard_no' => $guardNo], JSON_UNESCAPED_UNICODE);
             exit;
         }

         if ($api === 'save_requirement') {
             $guardId = isset($_POST['guard_id']) ? (int)$_POST['guard_id'] : 0;
             $typeId = isset($_POST['requirement_type_id']) ? (int)$_POST['requirement_type_id'] : 0;
             if ($guardId <= 0 || $typeId <= 0) {
                 throw new RuntimeException('Invalid request.');
             }

             $docNo = trim((string)($_POST['document_no'] ?? ''));
             $issuedRaw = trim((string)($_POST['issued_date'] ?? ''));
             $expiryRaw = trim((string)($_POST['expiry_date'] ?? ''));
             $issued = $issuedRaw !== '' ? $issuedRaw : null;
             $expiry = $expiryRaw !== '' ? $expiryRaw : null;

             $typeStmt = $pdo->prepare('SELECT code, expires FROM requirement_types WHERE id = :id LIMIT 1');
             $typeStmt->execute(['id' => $typeId]);
             $t = $typeStmt->fetch();
             if (!$t) {
                 throw new RuntimeException('Requirement type not found.');
             }
             $code = (string)($t['code'] ?? '');
             if ($code !== 'SECURITY_LICENSE') {
                 $issued = null;
                 $expiry = null;
             }
             if ($code === 'SECURITY_LICENSE' && ($expiry === null || $expiry === '')) {
                 throw new RuntimeException('Expiry date is required for Security License.');
             }

             $docPath = null;
             $docOrig = null;
             $docMime = null;
             $docSize = null;

             $file = $_FILES['document_file'] ?? null;
             $hasNewUpload = is_array($file) && isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_NO_FILE;
             if ($hasNewUpload) {
                 if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                     throw new RuntimeException('Upload failed. Please try again.');
                 }
                 $maxBytes = 8 * 1024 * 1024;
                 $size = isset($file['size']) ? (int)$file['size'] : 0;
                 if ($size <= 0 || $size > $maxBytes) {
                     throw new RuntimeException('File must be less than 8MB.');
                 }
                 $originalName = (string)($file['name'] ?? 'document');
                 $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                 $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                 if ($ext !== '' && !in_array($ext, $allowed, true)) {
                     throw new RuntimeException('Allowed file types: PDF, JPG, JPEG, PNG.');
                 }
                 $uploadDir = __DIR__ . '/../uploads/guard_requirements';
                 if (!is_dir($uploadDir)) {
                     @mkdir($uploadDir, 0775, true);
                 }
                 if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                     throw new RuntimeException('Upload folder is not writable.');
                 }
                 $safeExt = $ext !== '' ? ('.' . $ext) : '';
                 $storedName = 'g' . $guardId . '_t' . $typeId . '_' . bin2hex(random_bytes(10)) . $safeExt;
                 $targetPath = $uploadDir . '/' . $storedName;
                 if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                     throw new RuntimeException('Unable to save uploaded file.');
                 }
                 $docPath = 'uploads/guard_requirements/' . $storedName;
                 $docOrig = $originalName;
                 $docMime = (string)($file['type'] ?? '');
                 $docSize = $size;
             }

             $existing = $pdo->prepare('SELECT id, document_path FROM guard_requirements WHERE guard_id = :guard_id AND requirement_type_id = :type_id LIMIT 1');
             $existing->execute(['guard_id' => $guardId, 'type_id' => $typeId]);
             $ex = $existing->fetch();
             $existingPath = $ex ? (string)($ex['document_path'] ?? '') : '';
             if ($existingPath === '' && !$hasNewUpload) {
                 throw new RuntimeException('Please upload the document file.');
             }

             $stmt = $pdo->prepare(
                 'INSERT INTO guard_requirements (
                     guard_id, requirement_type_id, document_no, issued_date, expiry_date,
                     document_path, document_original_name, document_mime, document_size
                  ) VALUES (
                     :guard_id, :type_id, :document_no, :issued_date, :expiry_date,
                     :document_path, :document_original_name, :document_mime, :document_size
                  )
                  ON DUPLICATE KEY UPDATE
                     document_no = VALUES(document_no),
                     issued_date = VALUES(issued_date),
                     expiry_date = VALUES(expiry_date),
                     document_path = IFNULL(VALUES(document_path), document_path),
                     document_original_name = IFNULL(VALUES(document_original_name), document_original_name),
                     document_mime = IFNULL(VALUES(document_mime), document_mime),
                     document_size = IFNULL(VALUES(document_size), document_size)'
             );
             $stmt->execute([
                 'guard_id' => $guardId,
                 'type_id' => $typeId,
                 'document_no' => $docNo !== '' ? $docNo : null,
                 'issued_date' => $issued,
                 'expiry_date' => $expiry,
                 'document_path' => $docPath,
                 'document_original_name' => $docOrig,
                 'document_mime' => $docMime,
                 'document_size' => $docSize,
             ]);

             echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
             exit;
         }

         throw new RuntimeException('Unknown API.');
     } catch (Throwable $e) {
         http_response_code(400);
         echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
         exit;
     }
 }
 
 $pageData = [
     'company' => $company,
     'companyLabel' => $companyLabel,
     'isBrainMaster' => $isBrainMaster,
     'userName' => $userName,
     'userInitials' => $userInitials,
     'userEmployeeId' => (string)($_SESSION['user_employee_id'] ?? ''),
     'userRole' => $role,
     'requirements' => $requiredRequirements,
     'requirementTypes' => $requiredRequirementTypes,
     'summary' => [
         'total_guards' => (int)($jubecerSummary['total_guards'] ?? 0),
         'guards_with_missing' => (int)($jubecerSummary['guards_with_missing'] ?? 0),
         'guards_with_expired_license' => (int)($jubecerSummary['guards_with_expired_license'] ?? 0),
         'guards_with_expiring_license' => (int)($jubecerSummary['guards_with_expiring_license'] ?? 0),
     ],
     'licenseAlerts' => $jubecerLicenseAlerts,
     'guards' => $jubecerGuards,
     'reports' => $jubecerReports,
 ];
 ?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ERMS — Guard Management</title>
<link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
<script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
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
  --gray-600:#475467;
  --gray-700:#344054;
  --gray-800:#1D2939;
  --gray-900:#101828;
  --navy-50:#EEF4FF;
  --navy-100:#E0EAFF;
  --navy-200:#C7D7FD;
  --navy-500:#6172F3;
  --navy-600:#444CE7;
  --navy-700:#3538CD;
  --navy-800:#2D31A6;
  --navy-900:#1F2370;
  --success-50:#ECFDF3;
  --success-100:#DCFAE6;
  --success-200:#ABEFC6;
  --success-500:#12B76A;
  --success-600:#039855;
  --success-700:#027A48;
  --warning-50:#FFFAEB;
  --warning-100:#FEF0C7;
  --warning-500:#F79009;
  --warning-600:#DC6803;
  --warning-700:#B54708;
  --error-50:#FEF3F2;
  --error-100:#FEE4E2;
  --error-200:#FECDCA;
  --error-500:#F04438;
  --error-600:#D92D20;
  --error-700:#B42318;
  --orange-50:#FFF6ED;
  --orange-100:#FFEAD5;
  --orange-500:#EF6820;
  --orange-600:#E04F16;
  --orange-700:#B93815;
  --sx:0 1px 2px rgba(16,24,40,0.05);
  --sm:0 1px 3px rgba(16,24,40,0.1),0 1px 2px rgba(16,24,40,0.06);
  --md:0 4px 8px -2px rgba(16,24,40,0.1),0 2px 4px -2px rgba(16,24,40,0.06);
  --lg:0 12px 16px -4px rgba(16,24,40,0.08),0 4px 6px -2px rgba(16,24,40,0.03);
  --xl:0 20px 24px -4px rgba(16,24,40,0.08),0 8px 8px -4px rgba(16,24,40,0.03);
  --r:8px;--rm:10px;--rl:12px;--rxl:16px;
  --f:'Plus Jakarta Sans',sans-serif;
  --mono:'JetBrains Mono',monospace;
}
html,body,#root{height:100%;background:var(--gray-50);}
body{font-family:var(--f);font-size:14px;color:var(--gray-700);-webkit-font-smoothing:antialiased;}
button,input,select{font-family:var(--f);}

.shell{display:flex;height:100vh;overflow:hidden;}

/* SIDEBAR */
.sb{
  width:260px;flex-shrink:0;background:var(--gray-900);
  display:flex;flex-direction:column;overflow:hidden;
}
.sb-top{padding:20px 16px 16px;border-bottom:1px solid rgba(255,255,255,0.06);}
.sb-brand{display:flex;align-items:center;gap:12px;}
.sb-logo{
  width:38px;height:38px;border-radius:12px;
  background:rgba(255,255,255,0.10);
  border:1px solid rgba(255,255,255,0.14);
  display:flex;align-items:center;justify-content:center;
  padding:6px;
  flex-shrink:0;
  box-shadow:0 0 0 1px rgba(255,255,255,0.06),0 10px 20px rgba(0,0,0,0.22);
}
.sb-logo img{width:100%;height:100%;object-fit:contain;display:block;}
.sb-name{font-size:15px;font-weight:700;color:#fff;letter-spacing:-0.3px;}
.sb-tagline{font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;font-weight:500;}

.sb-nav{padding:10px 10px 0;flex:1;}
.sb-nav-label{
  font-size:10px;font-weight:600;letter-spacing:1px;
  color:rgba(255,255,255,0.2);text-transform:uppercase;
  padding:8px 8px 5px;
}
.sb-item{
  display:flex;align-items:center;gap:10px;
  padding:9px 10px;border-radius:var(--r);
  color:rgba(255,255,255,0.45);font-size:13px;font-weight:500;
  cursor:pointer;transition:all .15s;border:none;background:none;
  width:100%;text-align:left;margin-bottom:2px;position:relative;
}
.sb-item:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.8);}
.sb-item.on{background:rgba(97,114,243,0.15);color:#fff;font-weight:600;}
.sb-item.on::before{
  content:'';position:absolute;left:0;top:8px;bottom:8px;
  width:3px;border-radius:0 3px 3px 0;background:var(--navy-500);
}
.sb-item svg{width:15px;height:15px;flex-shrink:0;}
.sb-label{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sb-pill{
  margin-left:auto;background:rgba(240,68,56,0.2);color:#FDA29B;
  font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;
  font-family:var(--mono);
}

.sb-foot{padding:14px 14px 18px;border-top:1px solid rgba(255,255,255,0.06);margin-top:auto;}
.sb-user{display:flex;align-items:center;gap:10px;}
.sb-av{
  width:32px;height:32px;border-radius:50%;
  background:linear-gradient(135deg,var(--navy-600) 0%,#7C3AED 100%);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;color:#fff;flex-shrink:0;
}
.sb-meta{min-width:0;flex:1;display:flex;flex-direction:column;align-items:flex-start;text-align:left;}
.sb-uname{font-size:13px;font-weight:600;color:rgba(255,255,255,0.85);line-height:1.2;}
.sb-urole{font-size:11px;color:rgba(255,255,255,0.3);margin-top:1px;line-height:1.2;}

.sb-actions{display:flex;gap:8px;margin-top:12px;}
.sb-act{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:8px 10px;border-radius:var(--r);
  border:1px solid rgba(255,255,255,0.12);
  background:rgba(255,255,255,0.06);
  color:rgba(255,255,255,0.8);
  font-size:12px;font-weight:600;
  transition:all .15s;
  text-decoration:none;
}
.sb-act:hover{background:rgba(255,255,255,0.1);color:#fff;}

.sb-dd{position:relative;}
.sb-trigger{
  width:100%;
  display:flex;align-items:center;gap:10px;
  padding:10px 10px;
  border-radius:var(--r);
  border:1px solid rgba(255,255,255,0.08);
  background:rgba(255,255,255,0.04);
  color:rgba(255,255,255,0.85);
  cursor:pointer;
  transition:background .15s,border-color .15s;
}
.sb-trigger:hover{background:rgba(255,255,255,0.07);border-color:rgba(255,255,255,0.12);}
.sb-trigger:focus{outline:none;box-shadow:0 0 0 3px rgba(97,114,243,0.25);}
.sb-chev{margin-left:auto;color:rgba(255,255,255,0.45);display:flex;align-items:center;justify-content:center;}
.sb-chev svg{width:16px;height:16px;}
.sb-menu{
  position:absolute;
  left:0;
  right:0;
  bottom:calc(100% + 10px);
  background:rgba(16,24,40,0.98);
  border:1px solid rgba(255,255,255,0.10);
  border-radius:12px;
  box-shadow:0 16px 32px rgba(0,0,0,0.35);
  overflow:hidden;
  z-index:20;
}
.sb-mi{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;
  color:rgba(255,255,255,0.85);
  font-size:13px;
  font-weight:600;
  background:transparent;border:none;width:100%;text-align:left;cursor:pointer;
}
.sb-mi:first-child{border-top:none;}
.sb-mi:hover{background:rgba(255,255,255,0.06);color:#fff;}
.sb-mi svg{width:16px;height:16px;opacity:0.85;}
.sb-mi.d{color:#FDA29B;}
.sb-mi.d:hover{background:rgba(240,68,56,0.12);color:#FEB2B2;}

.sb-backdrop{
  position:fixed;inset:0;background:rgba(16,24,40,0.35);
  opacity:0;pointer-events:none;transition:opacity .2s;z-index:40;
}
.shell.sb-open .sb-backdrop{opacity:1;pointer-events:auto;}

/* MAIN */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;}

/* TOPBAR */
.topbar{
  height:60px;background:var(--white);
  border-bottom:1px solid var(--gray-200);
  padding:0 28px;
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;box-shadow:var(--sx);
}
.tb-left{display:flex;align-items:center;gap:10px;min-width:0;}
.tb-title{min-width:0;}
.tb-pg{font-size:16px;font-weight:700;color:var(--gray-900);letter-spacing:-0.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tb-crumb{font-size:12px;color:var(--gray-400);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tb-r{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;position:relative;}
.tb-clock{
  font-family:var(--mono);font-size:12px;color:var(--gray-500);
  background:var(--gray-50);border:1px solid var(--gray-200);
  padding:5px 12px;border-radius:var(--r);
  display:inline-flex;align-items:center;
}
.tb-menu{
  display:none;align-items:center;justify-content:center;
  width:36px;height:36px;border-radius:var(--r);
  border:1px solid var(--gray-200);background:var(--white);
  color:var(--gray-600);cursor:pointer;transition:all .15s;
}
.tb-menu:hover{background:var(--gray-50);color:var(--gray-800);}
.tb-menu svg{width:18px;height:18px;}
.tb-icobtn{
  width:34px;height:34px;border-radius:var(--r);
  border:1px solid var(--gray-200);background:var(--white);
  color:var(--gray-500);display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;position:relative;
}
.tb-icobtn:hover{background:var(--gray-50);color:var(--gray-700);}
.tb-icobtn svg{width:15px;height:15px;}
.tb-dot{
  position:absolute;top:7px;right:7px;
  width:6px;height:6px;border-radius:50%;
  background:var(--error-500);border:1.5px solid var(--white);
}

.notif-pop{
  position:absolute;right:0;top:44px;width:340px;max-width:90vw;
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:12px;box-shadow:var(--md);z-index:30;overflow:hidden;
}
.notif-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 14px;border-bottom:1px solid var(--gray-100);
  font-size:13px;font-weight:700;color:var(--gray-800);
}
.notif-count{font-size:11px;font-weight:700;color:var(--gray-600);}
.notif-body{max-height:320px;overflow:auto;}
.notif-empty{padding:18px;color:var(--gray-500);font-size:12px;text-align:center;}

/* CONTENT */
.content{flex:1;overflow-y:auto;padding:26px 28px;background:var(--gray-50);}

.bm-wrap{max-width:1240px;margin:0 auto;}

/* PAGE HEADER */
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;}
.ph-title{font-size:22px;font-weight:800;color:var(--gray-900);letter-spacing:-0.5px;}
.ph-sub{font-size:13px;color:var(--gray-400);margin-top:4px;}
.ph-actions{display:flex;gap:10px;margin-top:4px;}

/* STAT CARDS */
.sg{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.sg.sg-user{grid-template-columns:repeat(3,1fr);}
.sc{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);padding:20px 22px;
  box-shadow:var(--sx);transition:box-shadow .2s,border-color .2s;
  cursor:default;
}
.sc:hover{box-shadow:var(--md);border-color:var(--gray-300);}
.sc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
.sc-ico{
  width:42px;height:42px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
}
.sc-ico svg{width:18px;height:18px;}
.sc-ico.b{background:var(--navy-50);}
.sc-ico.b svg{color:var(--navy-700);}
.sc-ico.o{background:var(--orange-50);}
.sc-ico.o svg{color:var(--orange-600);}
.sc-ico.y{background:var(--warning-50);}
.sc-ico.y svg{color:var(--warning-600);}
.sc-ico.r{background:var(--error-50);}
.sc-ico.r svg{color:var(--error-600);}
.sc-label{font-size:13px;font-weight:500;color:var(--gray-500);margin-bottom:4px;}
.sc-val{font-size:32px;font-weight:800;color:var(--gray-900);letter-spacing:-1.5px;line-height:1;}
.sc-bar{height:3px;border-radius:2px;margin-top:14px;background:var(--gray-100);}
.sc-bar-fill{height:100%;border-radius:2px;}
.sc-bar-fill.b{background:var(--navy-600);}
.sc-bar-fill.o{background:var(--orange-500);}
.sc-bar-fill.y{background:var(--warning-500);}
.sc-bar-fill.r{background:var(--error-500);}
.sc-foot{font-size:11px;color:var(--gray-400);margin-top:8px;}

/* CARD */
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--rxl);box-shadow:var(--sx);overflow:hidden;}

/* SECTION HEADER */
.shd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.shd-t{font-size:15px;font-weight:700;color:var(--gray-900);}
.shd-s{font-size:12px;color:var(--gray-400);margin-top:2px;}

/* QUICK ACTIONS */
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px;}
.qa-item{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);padding:18px 20px;
  cursor:pointer;transition:all .15s;box-shadow:var(--sx);
  display:flex;align-items:center;gap:14px;
}
.qa-item:hover{box-shadow:var(--md);border-color:var(--gray-300);transform:translateY(-1px);}
.qa-ico{
  width:42px;height:42px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.qa-ico svg{width:18px;height:18px;}
.qa-ico.b{background:var(--navy-50);}
.qa-ico.b svg{color:var(--navy-700);}
.qa-ico.g{background:var(--success-50);}
.qa-ico.g svg{color:var(--success-600);}
.qa-ico.y{background:var(--warning-50);}
.qa-ico.y svg{color:var(--warning-600);}
.qa-lbl{font-size:13px;font-weight:700;color:var(--gray-800);}
.qa-sub{font-size:12px;color:var(--gray-400);margin-top:2px;}

/* ALERT LIST */
.al-row{
  display:flex;align-items:center;gap:14px;
  padding:14px 20px;border-bottom:1px solid var(--gray-100);
  transition:background .12s;cursor:pointer;
}
.al-row:last-child{border-bottom:none;}
.al-av{
  width:36px;height:36px;border-radius:50%;
  background:var(--navy-50);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;color:var(--navy-700);flex-shrink:0;
}
.al-name{font-size:13px;font-weight:600;color:var(--gray-800);}
.al-meta{font-size:11px;color:var(--gray-400);margin-top:2px;font-family:var(--mono);}
.al-info{flex:1;min-width:0;}

/* BADGES */
.badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 9px;border-radius:20px;
  font-size:11px;font-weight:600;white-space:nowrap;
  flex-shrink:0;
}
.badge::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.bv{background:var(--success-50);color:var(--success-700);}
.bv::before{background:var(--success-500);}
.bx{background:var(--warning-50);color:var(--warning-700);}
.bx::before{background:var(--warning-500);}
.be{background:var(--error-50);color:var(--error-700);}
.be::before{background:var(--error-500);}
.bm{background:var(--orange-50);color:var(--orange-700);}
.bm::before{background:var(--orange-500);}

/* BUTTONS */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:9px 16px;border-radius:var(--r);
  font-size:13px;font-weight:600;
  transition:all .15s;border:none;white-space:nowrap;cursor:pointer;
}
.btn svg{width:14px;height:14px;}
.btn-p{
  background:var(--navy-700);color:#fff;
  box-shadow:0 1px 3px rgba(53,56,205,0.3),var(--sx);
}
.btn-p:hover{background:var(--navy-800);}
.btn-s{
  background:var(--white);color:var(--gray-700);
  border:1px solid var(--gray-300);box-shadow:var(--sx);
}
.btn-s:hover{background:var(--gray-50);border-color:var(--gray-400);}
.btn-g{
  background:transparent;color:var(--gray-600);
  border:1px solid transparent;
}
.btn-g:hover{background:var(--gray-100);color:var(--gray-800);}
.btn-op{
  background:var(--navy-50);color:var(--navy-700);
  border:1px solid var(--navy-200);
}
.btn-op:hover{background:var(--navy-100);}
.sm{padding:6px 12px;font-size:12px;}

/* TABLE */
.tc{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--rxl);overflow:hidden;box-shadow:var(--sx);}
.tctrl{
  padding:14px 20px;border-bottom:1px solid var(--gray-200);
  display:flex;gap:10px;align-items:center;flex-wrap:wrap;
  background:var(--white);
}
.actcol{text-align:right;white-space:nowrap;}
.tact{display:inline-flex;justify-content:flex-end;gap:8px;flex-wrap:nowrap;align-items:center;}
.tact .btn{padding:6px 12px;font-size:12px;min-width:86px;justify-content:center;}
.tact .btn.btn-g{border:1px solid var(--gray-200);background:var(--white);}
.tact .btn.btn-g:hover{background:var(--gray-50);border-color:var(--gray-300);}
.sw{flex:1;min-width:220px;position:relative;}
.sw svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--gray-400);pointer-events:none;}
.si{
  width:100%;padding:8px 12px 8px 34px;
  background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);color:var(--gray-700);font-size:13px;
  outline:none;transition:all .15s;
}
.si::placeholder{color:var(--gray-400);}
.si:focus{background:var(--white);border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}
.ts{
  padding:8px 12px;background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);color:var(--gray-600);font-size:13px;
  outline:none;cursor:pointer;transition:all .15s;font-family:var(--f);
}
.ts:focus{background:var(--white);border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}

table{width:100%;border-collapse:collapse;}
thead{background:var(--gray-50);border-bottom:1px solid var(--gray-200);}
th{
  padding:10px 16px;text-align:left;
  font-size:11px;font-weight:600;letter-spacing:0.4px;
  text-transform:uppercase;color:var(--gray-500);white-space:nowrap;
}
td{
  padding:13px 16px;border-bottom:1px solid var(--gray-100);
  font-size:13px;color:var(--gray-600);vertical-align:middle;
}
tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:var(--gray-25);}
.gno{font-family:var(--mono);font-size:11px;color:var(--gray-400);}
.gnm{font-weight:600;color:var(--navy-700);cursor:pointer;transition:color .12s;}
.gnm:hover{color:var(--navy-600);text-decoration:underline;}
.mc0{font-family:var(--mono);font-size:12px;color:var(--success-600);font-weight:600;}
.mcn{font-family:var(--mono);font-size:12px;color:var(--orange-600);font-weight:600;}

.table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
.table-wrap table{min-width:720px;}

/* PAGINATION */
.pgn{
  padding:12px 20px;display:flex;align-items:center;justify-content:space-between;
  border-top:1px solid var(--gray-200);background:var(--white);
}
.pgi{font-size:12px;color:var(--gray-500);}
.pgb{display:flex;gap:4px;}
.pb{
  min-width:30px;height:30px;border-radius:var(--r);
  background:var(--white);border:1px solid var(--gray-200);
  color:var(--gray-600);font-size:12px;font-weight:500;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;padding:0 6px;
}
.pb:hover{background:var(--gray-50);border-color:var(--gray-300);}
.pb.on{background:var(--navy-700);color:#fff;border-color:var(--navy-700);}
.pb:disabled{opacity:0.35;cursor:default;}

/* MODAL */
.overlay{
  position:fixed;inset:0;background:rgba(16,24,40,0.65);
  display:flex;align-items:center;justify-content:center;
  z-index:100;backdrop-filter:blur(4px);
  animation:fi .15s ease;
}
@keyframes fi{from{opacity:0}to{opacity:1}}
.modal{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:var(--rxl);width:540px;max-width:95vw;
  max-height:90vh;overflow-y:auto;
  box-shadow:var(--xl);
  animation:si .18s ease;
}
.modal-archive{width:920px;max-width:95vw;}
.modal-archive .table-wrap table{min-width:0;table-layout:fixed;}
.modal-archive td{overflow:hidden;text-overflow:ellipsis;}
.modal-archive td:nth-child(2){white-space:normal;}
.modal-archive th:nth-child(1),.modal-archive td:nth-child(1){width:110px;}
.modal-archive th:nth-child(2),.modal-archive td:nth-child(2){width:220px;}
.modal-archive th:nth-child(3),.modal-archive td:nth-child(3){width:160px;}
.modal-archive th:nth-child(4),.modal-archive td:nth-child(4){width:auto;}
.modal-archive th:nth-child(5),.modal-archive td:nth-child(5){width:120px;}
.modal-archive th:nth-child(6),.modal-archive td:nth-child(6){width:140px;}
@keyframes si{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.mhd{
  padding:22px 24px 18px;border-bottom:1px solid var(--gray-200);
  display:flex;align-items:flex-start;justify-content:space-between;
}
.mt{font-size:17px;font-weight:700;color:var(--gray-900);}
.ms{font-size:12px;color:var(--gray-500);margin-top:3px;}
.mc{
  width:30px;height:30px;border-radius:var(--r);
  background:var(--gray-50);border:1px solid var(--gray-200);
  color:var(--gray-500);display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;flex-shrink:0;
}
.mc:hover{background:var(--gray-100);border-color:var(--gray-300);}
.mc svg{width:14px;height:14px;}
.mb{padding:20px 24px 24px;}

/* FORM */
.fg{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px;}
.fg2{grid-template-columns:repeat(2,1fr);}
.fgrp{display:flex;flex-direction:column;gap:5px;}
.fl{font-size:12px;font-weight:600;color:var(--gray-700);}
.fl span{color:var(--error-500);}
.fi{
  padding:9px 13px;background:var(--white);
  border:1px solid var(--gray-300);border-radius:var(--r);
  color:var(--gray-800);font-size:13px;font-family:var(--f);
  outline:none;transition:all .15s;width:100%;
}
.fi::placeholder{color:var(--gray-400);}
.fi:focus{border-color:var(--navy-500);box-shadow:0 0 0 3px rgba(97,114,243,0.12);}
.fi:read-only{background:var(--gray-50);color:var(--gray-400);}
.fac{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);}

/* GUARD PROFILE CARD */
.gpc{
  background:linear-gradient(135deg,var(--navy-800) 0%,var(--navy-700) 100%);
  border-radius:var(--rl);padding:18px 20px;margin-bottom:20px;
  display:flex;align-items:center;gap:14px;
}
.gpa{
  width:46px;height:46px;border-radius:50%;
  background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:700;color:#fff;flex-shrink:0;
}
.gpn{font-size:16px;font-weight:700;color:#fff;}
.gpm{font-size:12px;color:rgba(255,255,255,0.5);margin-top:3px;font-family:var(--mono);}

/* DETAIL */
.ds{margin-bottom:22px;}
.ds:last-child{margin-bottom:0;}
.dsh{
  font-size:11px;font-weight:700;letter-spacing:0.8px;
  text-transform:uppercase;color:var(--navy-600);
  margin-bottom:12px;padding-bottom:8px;
  border-bottom:2px solid var(--navy-50);
}
.dg{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.di{
  background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);padding:10px 14px;
}
.dk{font-size:10px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;}
.dv{font-size:13px;font-weight:600;color:var(--gray-800);}
.ri{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-200);
  border-radius:var(--r);margin-bottom:6px;
}
.ri:last-child{margin-bottom:0;}
.ri svg{width:16px;height:16px;}
.rn{font-size:13px;font-weight:500;color:var(--gray-700);}
.rok{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--success-600);}
.rok svg{width:13px;height:13px;}
.rno{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--error-600);}
.rno svg{width:13px;height:13px;}

/* TOAST */
.toast{
  position:fixed;bottom:24px;right:24px;z-index:200;
  background:var(--gray-900);border:1px solid rgba(255,255,255,0.1);
  border-radius:var(--rm);padding:12px 16px;
  display:flex;align-items:center;gap:10px;
  box-shadow:var(--xl);animation:si .2s ease;
}
.tico{
  width:22px;height:22px;border-radius:50%;
  background:var(--success-500);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.tico svg{width:11px;height:11px;color:#fff;}
.ttxt{font-size:13px;font-weight:500;color:#fff;}

/* EMPTY */
.empty{padding:56px;text-align:center;}
.ei{
  width:46px;height:46px;border-radius:12px;background:var(--gray-100);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;
}
.ei svg{width:20px;height:20px;color:var(--gray-400);}
.et{font-size:14px;font-weight:600;color:var(--gray-600);margin-bottom:4px;}
.es{font-size:13px;color:var(--gray-400);}

.cg{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.ch{border:1px solid var(--gray-200);border-radius:var(--rxl);box-shadow:var(--sx)}
.ch-h{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.ch-t{font-size:14px;font-weight:800;color:var(--gray-900)}
.ch-s{font-size:12px;color:var(--gray-500);margin-top:2px}
.ch-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ch-meta{font-size:11px;color:var(--gray-400);font-family:var(--mono)}
.report-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto}
.ch-c{position:relative;width:100%;height:240px}
.ch-c.sm{height:190px}
.ch-mini{margin-top:10px;display:flex;gap:12px;flex-wrap:wrap}
.ch-mi{font-size:11px;color:var(--gray-500)}

.chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--gray-50);border:1px solid var(--gray-200);font-size:12px;color:var(--gray-600)}
.chip b{font-family:var(--mono);font-size:12px;color:var(--gray-700)}
.chip.ok{background:var(--success-50);border-color:var(--success-200);color:var(--success-700)}
.chip.warn{background:var(--warning-50);border-color:var(--warning-100);color:var(--warning-700)}
.chip.bad{background:var(--error-50);border-color:var(--error-100);color:var(--error-700)}

@media(max-width:980px){.cg{grid-template-columns:1fr}.ch-c{height:240px}}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:10px;}
::-webkit-scrollbar-thumb:hover{background:var(--gray-300);}
@media(max-width:900px){.sg{grid-template-columns:repeat(2,1fr);}.qa{grid-template-columns:1fr 1fr;}.fg{grid-template-columns:repeat(2,1fr);}}
@media(max-width:1024px){.content{padding:22px 20px}.bm-wrap{max-width:100%}}
@media(max-width:860px){
  .topbar{height:auto;padding:12px 16px;gap:10px;flex-wrap:wrap}
  .tb-left{width:100%}
  .tb-r{width:100%;justify-content:flex-start}
  .tb-pg{font-size:15px}
  .tb-crumb{font-size:11px}
  .ph{flex-wrap:wrap;gap:12px}
  .ph-actions{width:100%;justify-content:flex-start;flex-wrap:wrap}
}
@media(max-width:760px){
  .shell{position:relative}
  .sb{position:fixed;left:0;top:0;bottom:0;transform:translateX(-105%);transition:transform .2s;z-index:50;width:min(78vw,280px);box-shadow:0 20px 40px rgba(0,0,0,0.35);overflow-y:auto}
  .shell.sb-open .sb{transform:translateX(0)}
  .tb-menu{display:inline-flex}
  .content{padding:18px 16px}
  .tctrl{flex-direction:column;align-items:stretch}
  .sw{max-width:100% !important}
  .ts{width:100%}
  .tact{flex-wrap:wrap;justify-content:flex-start}
  .actcol{text-align:left}
  .pgn{flex-direction:column;align-items:flex-start;gap:8px}
  .pgb{flex-wrap:wrap}
  .al-row{flex-wrap:wrap;align-items:flex-start}
  .al-row .badge{margin-left:0}
}
@media(max-width:640px){
  .sg{grid-template-columns:1fr}
  .qa{grid-template-columns:1fr}
  .sc{padding:16px}
  .sc-val{font-size:28px}
  .sc-ico{width:38px;height:38px}
  .qa-item{padding:14px 16px;gap:12px}
  .qa-ico{width:38px;height:38px}
  .qa-lbl{font-size:12px}
  .qa-sub{font-size:11px}
  .tb-clock{width:100%;justify-content:center}
}
@media(max-width:520px){
  .tb-pg{font-size:14px}
  .tb-crumb{font-size:10px}
  .tb-icobtn{width:32px;height:32px}
  .tb-clock{font-size:11px;padding:5px 10px}
  .sc-val{font-size:26px}
  .sc-label{font-size:12px}
  .table-wrap table{min-width:640px}
}
 </style>
 </head>
 <body>
 <script>
 window.__ERMS_DATA__ = <?php echo json_encode($pageData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
 </script>
 <div id="root"></div>
<script type="text/babel">
 const {useState,useEffect,useMemo}=React;
 const DATA=(window.__ERMS_DATA__||{});
 const STS=['VALID','EXPIRING','EXPIRED','MISSING'];
 const RQS=Array.isArray(DATA.requirements)&&DATA.requirements.length?DATA.requirements:['SSS','PAG-IBIG','PhilHealth','License'];
 const GD=Array.isArray(DATA.guards)?DATA.guards:[];
 const AG=[...new Set(GD.map(g=>g.agency).filter(Boolean))];
 const SF=['','Jr.','Sr.','III'];

const Ic={
  dash:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>,
  guard:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
  rep:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-7"/></svg>,
  users:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>,
  bell:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>,
  search:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>,
  plus:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M12 5v14M5 12h14"/></svg>,
  x:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>,
  check:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M20 6 9 17l-5-5"/></svg>,
  warn:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="m10.29 3.86-7 12A1 1 0 0 0 4 17h16a1 1 0 0 0 .86-1.5l-7-12a1 1 0 0 0-1.72 0zM12 9v4M12 17h.01"/></svg>,
  clock:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>,
  shield:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>,
  chD:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m6 9 6 6 6-6"/></svg>,
  chU:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m18 15-6-6-6 6"/></svg>,
  chL:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m15 18-6-6 6-6"/></svg>,
  chR:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m9 18 6-6-6-6"/></svg>,
  menu:()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>,
};

function Badge({s}){
  const m={VALID:'badge bv',EXPIRING:'badge bx',EXPIRED:'badge be',MISSING:'badge bm'};
  return <span className={m[s]||'badge bm'}>{s}</span>;
}

function Clock(){
  const [t,setT]=useState(new Date());
  useEffect(()=>{const id=setInterval(()=>setT(new Date()),1000);return()=>clearInterval(id);},[]);
  const D=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const Mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return <span className="tb-clock">{D[t.getDay()]}, {Mo[t.getMonth()]} {t.getDate()} &nbsp;·&nbsp; {t.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}</span>;
}

async function apiPost(fd,urlParams=''){
  const url='home.php'+urlParams;
  const r=await fetch(url,{method:'POST',body:fd,credentials:'same-origin'});
  const j=await r.json().catch(()=>({ok:false,error:'Invalid server response.'}));
  if(!r.ok||!j||j.ok!==true){throw new Error((j&&j.error)?j.error:'Request failed.');}
  return j;
}

function fmtDate(iso){
  if(!iso)return '—';
  const dt=new Date(String(iso).slice(0,10)+'T00:00:00');
  if(Number.isNaN(dt.getTime()))return String(iso);
  return new Intl.DateTimeFormat('en-US',{month:'short',day:'2-digit',year:'numeric'}).format(dt);
}

function guardInitials(name){
  const parts=String(name||'').trim().split(/\s+/).filter(Boolean);
  if(parts.length===0)return 'G';
  const first=parts[0][0]||'';
  const last=parts.length>1?parts[parts.length-1][0]||'':'';
  return (first+last).toUpperCase()||'G';
}

function alertBadge(status){
  const s=String(status||'').toUpperCase();
  if(s==='EXPIRED')return 'EXPIRED';
  if(s==='EXPIRING')return 'EXPIRING';
  return 'EXPIRING';
}

function NotificationMenu({alerts}){
  const [open,setOpen]=useState(false);
  const count=alerts.length;

  useEffect(()=>{
    if(!open)return;
    const onDoc=(e)=>{
      const root=document.getElementById('notifMenu');
      if(root && !root.contains(e.target)) setOpen(false);
    };
    const onKey=(e)=>{if(e.key==='Escape')setOpen(false);};
    document.addEventListener('mousedown',onDoc);
    document.addEventListener('keydown',onKey);
    return ()=>{
      document.removeEventListener('mousedown',onDoc);
      document.removeEventListener('keydown',onKey);
    };
  },[open]);

  return(
    <div id="notifMenu">
      <button
        className="tb-icobtn"
        type="button"
        aria-haspopup="dialog"
        aria-expanded={open?'true':'false'}
        onClick={()=>setOpen(v=>!v)}
        style={{position:'relative'}}
      >
        <Ic.bell/>{count>0&&<div className="tb-dot"/>}
      </button>
      {open&&(
        <div className="notif-pop" role="dialog" aria-label="Notifications">
          <div className="notif-head">
            <div>Notifications</div>
            <div className="notif-count">{count}</div>
          </div>
          <div className="notif-body">
            {count===0
              ?<div className="notif-empty">No notifications yet.</div>
              :alerts.map((a,idx)=>{
                const name=a.full_name||'Unknown Guard';
                const meta=[a.agency||''].filter(Boolean).join(' · ');
                const days=parseInt(a.days_until_expiry,10);
                let dayLabel='';
                if(!Number.isNaN(days)){
                  if(days<0) dayLabel=`${Math.abs(days)} days overdue`;
                  else if(days===0) dayLabel='Due today';
                  else dayLabel=`${days} days left`;
                }
                const metaLine=[`Expiry: ${fmtDate(a.expiry_date)}`,dayLabel].filter(Boolean).join(' · ');
                return(
                  <div className="al-row" key={`${a.guard_id||'g'}-${idx}`}>
                    <div className="al-av">{guardInitials(name)}</div>
                    <div className="al-info">
                      <div className="al-name">{name}</div>
                      {meta&&<div className="al-meta">{meta}</div>}
                      <div className="al-meta">{metaLine}</div>
                    </div>
                    <Badge s={alertBadge(a.alert_status)}/>
                  </div>
                );
              })}
          </div>
        </div>
      )}
    </div>
  );
}

function GuardModal({g,close,onUpdated}){
  if(!g)return null;
  const [edit,setEdit]=useState(false);
  const [saving,setSaving]=useState(false);
  const [toast,setToast]=useState('');
  const [reqs,setReqs]=useState(null);
  const [openReq,setOpenReq]=useState(null);
  const [reqSaving,setReqSaving]=useState(false);
  const [reqFile,setReqFile]=useState({});
  const [f,setF]=useState({
    last:g.last||'',first:g.first||'',mid:g.mid||'',suffix:g.suffix||'',
    bday:g.bday||'',age:String(g.age||''),agency:g.agency||'',contact:g.contact||'',deployed:g.deployed||''
  });
  const showT=m=>{setToast(m);setTimeout(()=>setToast(''),3000);};
  const name=`${g.last}, ${g.first} ${g.mid} ${g.suffix}`.trim();
  const init=(g.first[0]||'')+(g.last[0]||'');
  const displayGuardNo = (g.displayNo !== undefined && g.displayNo !== null && String(g.displayNo) !== '')
    ? String(g.displayNo)
    : '';
  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));

  const calcAgeFromISO=(iso)=>{
    const s=String(iso||'').slice(0,10);
    if(!s)return null;
    const d=new Date(s+'T00:00:00');
    if(Number.isNaN(d.getTime()))return null;
    const now=new Date();
    let a=now.getFullYear()-d.getFullYear();
    const m=now.getMonth()-d.getMonth();
    if(m<0||(m===0&&now.getDate()<d.getDate()))a--;
    return Math.max(0,a);
  };
  const displayAge=calcAgeFromISO(g.bday);

  const deriveComplianceFromReqs=(list)=>{
    const reqList=Array.isArray(list)?list:[];
    const missingReqNames=[];
    let licenseExpiry='';
    const today=new Date();
    today.setHours(0,0,0,0);
    const sixMonths=new Date(today);
    sixMonths.setMonth(sixMonths.getMonth()+6);
    let expired=false;
    let expiring=false;

    for(const rt of reqList){
      const v=(rt&&rt.value)||{};
      const hasFile=!!v.document_path;
      if(!hasFile&&rt&&rt.name)missingReqNames.push(rt.name);

      if(rt&&rt.code==='SECURITY_LICENSE'){
        const ex=v.expiry_date?String(v.expiry_date).slice(0,10):'';
        if(ex)licenseExpiry=ex;
        const dt=ex?new Date(ex+'T00:00:00'):null;
        if(dt&&!Number.isNaN(dt.getTime())){
          if(dt<today)expired=true;
          else if(dt<=sixMonths)expiring=true;
        }
      }
    }

    let status='VALID';
    if(expired)status='EXPIRED';
    else if(expiring)status='EXPIRING';
    else if(missingReqNames.length>0)status='MISSING';

    return {missing:missingReqNames.length,missingReqs:missingReqNames,expDate:licenseExpiry,status};
  };

  const loadReqs=async()=>{
    if(DATA.company!=='jubecer')return;
    const fd=new FormData();
    fd.append('api','get_guard_requirements');
    fd.append('guard_id',String(g.id));
    const j=await apiPost(fd);
    const list=Array.isArray(j.requirements)?j.requirements:[];
    setReqs(list);

    const derived=deriveComplianceFromReqs(list);
    if(typeof onUpdated==='function'){
      const curMissingReqs=Array.isArray(g.missingReqs)?g.missingReqs:[];
      const changed=
        derived.status!==g.status||
        derived.missing!==g.missing||
        derived.expDate!==g.expDate||
        derived.missingReqs.join('\u0000')!==curMissingReqs.join('\u0000');
      if(changed)onUpdated({...g,...derived});
    }
  };

  useEffect(()=>{
    setEdit(false);
    setReqs(null);
    setOpenReq(null);
    setReqFile({});
    setF({
      last:g.last||'',first:g.first||'',mid:g.mid||'',suffix:g.suffix||'',
      bday:g.bday||'',age:String(g.age||''),agency:g.agency||'',contact:g.contact||'',deployed:g.deployed||''
    });
    loadReqs().catch(()=>{});
  },[g.id]);

  const saveProfile=async()=>{
    if(DATA.company!=='jubecer'){showT('Editing is available for Jubecer only.');return;}
    if(!f.last.trim()||!f.first.trim()){showT('Last Name and First Name are required.');return;}
    setSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','update_guard');
      fd.append('guard_id',String(g.id));
      fd.append('last_name',f.last);
      fd.append('first_name',f.first);
      fd.append('middle_name',f.mid);
      fd.append('suffix',f.suffix);
      fd.append('birthdate',f.bday);
      fd.append('age',String(f.age||''));
      fd.append('agency',f.agency);
      fd.append('contact_no',f.contact);
      fd.append('deployed',f.deployed);
      await apiPost(fd);
      const ng={...g,last:f.last,first:f.first,mid:f.mid,suffix:f.suffix,bday:f.bday,age:parseInt(f.age)||0,agency:f.agency,contact:f.contact,deployed:f.deployed};
      if(typeof onUpdated==='function')onUpdated(ng);
      setEdit(false);
      showT('Profile saved.');
    }catch(e){
      showT(e.message||'Failed to save.');
    }finally{setSaving(false);}
  };

  const saveReq=async(rt,local)=>{
    if(DATA.company!=='jubecer')return;
    setReqSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','save_requirement');
      fd.append('guard_id',String(g.id));
      fd.append('requirement_type_id',String(rt.id));
      fd.append('document_no',local.document_no||'');
      fd.append('issued_date',local.issued_date||'');
      fd.append('expiry_date',local.expiry_date||'');
      const fup=reqFile[rt.id];
      if(fup)fd.append('document_file',fup);
      await apiPost(fd);
      setReqFile(p=>{const n={...p};delete n[rt.id];return n;});
      await loadReqs();
      showT('Requirement saved.');
    }catch(e){
      showT(e.message||'Failed to save requirement.');
    }finally{setReqSaving(false);}
  };

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&close()}>
      <div className="modal">
        <div className="mhd">
          <div><div className="mt">Guard Profile</div><div className="ms">View full details and compliance status</div></div>
          <button className="mc" onClick={close}><Ic.x/></button>
        </div>
        <div className="mb">
          {toast&&<div className="toast"><div className="tico"><Ic.check/></div><div className="ttxt">{toast}</div></div>}
          <div className="gpc">
            <div className="gpa">{init}</div>
            <div style={{flex:1}}>
              <div className="gpn">{name}</div>
              <div className="gpm">
                {(displayGuardNo?`#${displayGuardNo}`:'')}
                {(displayGuardNo&&g.agency)?' · ':''}
                {g.agency||''}
              </div>
            </div>
            <Badge s={g.status}/>
          </div>
          <div className="ds">
            <div className="dsh">Personal Information</div>
            {!edit
              ?(
                <div className="dg">
                  <div className="di"><div className="dk">Full Name</div><div className="dv">{name}</div></div>
                  <div className="di"><div className="dk">Guard No.</div><div className="dv" style={{fontFamily:'var(--mono)',fontSize:12}}>{displayGuardNo||'—'}</div></div>
                  <div className="di"><div className="dk">Date of Birth</div><div className="dv">{g.bday}</div></div>
                  <div className="di"><div className="dk">Age</div><div className="dv">{displayAge===null?(g.age>0?g.age:'—'):displayAge} years old</div></div>
                  <div className="di"><div className="dk">Contact</div><div className="dv">{g.contact}</div></div>
                  <div className="di"><div className="dk">Agency</div><div className="dv">{g.agency}</div></div>
                  <div className="di"><div className="dk">Deploy Date</div><div className="dv" style={{fontFamily:'var(--mono)',fontSize:12}}>{fmtDate(g.deployed)}</div></div>
                </div>
              ):(
                <>
                  <div className="fg">
                    <div className="fgrp"><label className="fl">Last Name <span>*</span></label><input className="fi" value={f.last} onChange={u('last')}/></div>
                    <div className="fgrp"><label className="fl">First Name <span>*</span></label><input className="fi" value={f.first} onChange={u('first')}/></div>
                    <div className="fgrp"><label className="fl">Middle Name</label><input className="fi" value={f.mid} onChange={u('mid')}/></div>
                  </div>
                  <div className="fg">
                    <div className="fgrp"><label className="fl">Suffix</label><select className="fi" value={f.suffix} onChange={u('suffix')}>{SF.map(s=><option key={s} value={s}>{s||'None'}</option>)}</select></div>
                    <div className="fgrp"><label className="fl">Birthdate</label><input className="fi" type="date" value={f.bday} onChange={u('bday')}/></div>
                    <div className="fgrp"><label className="fl">Age</label><input className="fi" value={f.age} onChange={u('age')} placeholder="e.g. 25"/></div>
                  </div>
                  <div className="fg fg2">
                    <div className="fgrp"><label className="fl">Agency</label><input className="fi" value={f.agency} onChange={u('agency')} placeholder="Agency"/></div>
                    <div className="fgrp"><label className="fl">Contact No.</label><input className="fi" value={f.contact} onChange={u('contact')} placeholder="09XXXXXXXXX"/></div>
                  </div>
                  <div className="fg fg2">
                    <div className="fgrp" style={{flex:'1 1 100%'}}><label className="fl">Deploy Date</label><input className="fi" type="date" value={f.deployed} onChange={u('deployed')}/></div>
                  </div>
                </>
              )}
          </div>
          <div className="ds">
            <div className="dsh">License Information</div>
            <div className="dg">
              <div className="di"><div className="dk">Status</div><div className="dv"><Badge s={g.status}/></div></div>
              <div className="di"><div className="dk">Expiry Date</div><div className="dv" style={{fontFamily:'var(--mono)',fontSize:12}}>{g.expDate||'—'}</div></div>
            </div>
          </div>
          <div className="ds">
            <div className="dsh">Requirements</div>
            {DATA.company!=='jubecer'
              ?RQS.map(r=>{const ok=!g.missingReqs.includes(r);return(
                <div className="ri" key={r}>
                  <span className="rn">{r}</span>
                  <span style={{display:'inline-flex',alignItems:'center',gap:8}}>
                    {ok?<span className="rok"><Ic.check/>Complete</span>:<span className="rno"><Ic.x/>Missing</span>}
                    <span style={{color:'var(--gray-400)',display:'inline-flex',alignItems:'center'}}><Ic.chD/></span>
                  </span>
                </div>
              );})
              :(
                (reqs===null)
                ?<div className="di"><div className="dk">Loading</div><div className="dv">Fetching requirements…</div></div>
                :reqs.map(rt=>{
                  const v=rt.value||{document_no:'',issued_date:'',expiry_date:'',document_path:'',document_original_name:''};
                  const missing=!v.document_path;
                  const isOpen=openReq===rt.id;
                  return(
                    <div key={rt.id} style={{marginBottom:10}}>
                      <div className="ri" onClick={()=>setOpenReq(p=>p===rt.id?null:rt.id)}>
                        <span className="rn" style={{fontWeight:600}}>{rt.name}</span>
                        <span style={{display:'inline-flex',alignItems:'center',gap:8}}>
                          {missing
                            ?<span className="rno"><Ic.x/>Missing</span>
                            :<span className="rok"><Ic.check/>Complete</span>}
                          <span style={{color:'var(--gray-400)',display:'inline-flex',alignItems:'center'}}>{isOpen?<Ic.chU/>:<Ic.chD/>}</span>
                        </span>
                      </div>
                      {isOpen&&(
                        <div className="di" style={{marginTop:8}}>
                          <div className="fg fg2">
                            <div className="fgrp"><label className="fl">Document No</label><input className="fi" value={v.document_no||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,document_no:e.target.value}}:x))}/></div>
                            <div className="fgrp"><label className="fl">File</label><input className="fi" type="file" onChange={e=>setReqFile(p=>({...p,[rt.id]:(e.target.files&&e.target.files[0])||null}))}/></div>
                          </div>
                          {rt.code==='SECURITY_LICENSE'&&(
                            <div className="fg fg2">
                              <div className="fgrp"><label className="fl">Issued Date</label><input className="fi" type="date" value={v.issued_date||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,issued_date:e.target.value}}:x))}/></div>
                              <div className="fgrp"><label className="fl">Expiry Date <span>*</span></label><input className="fi" type="date" value={v.expiry_date||''} onChange={e=>setReqs(p=>p.map(x=>x.id===rt.id?{...x,value:{...v,expiry_date:e.target.value}}:x))}/></div>
                            </div>
                          )}
                          {v.document_path&&(
                            <div style={{marginTop:10,fontSize:12,color:'var(--gray-500)'}}>
                              Current: <a href={`../${v.document_path}`} target="_blank" rel="noreferrer" style={{color:'var(--navy-700)',textDecoration:'none',fontWeight:600}}>{v.document_original_name||'View file'}</a>
                            </div>
                          )}
                          <div className="fac">
                            <button className="btn btn-s sm" onClick={()=>setOpenReq(null)} disabled={reqSaving}>Close</button>
                            <button className="btn btn-p sm" onClick={()=>saveReq(rt,v)} disabled={reqSaving}>{reqSaving?'Saving…':'Save Requirement'}</button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })
              )}
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={close} disabled={saving||reqSaving}>Close</button>
            {DATA.company==='jubecer'&&(
              edit
                ?<button className="btn btn-p sm" onClick={saveProfile} disabled={saving}>{saving?'Saving…':'Save Profile'}</button>
                :<button className="btn btn-p sm" onClick={()=>setEdit(true)}>Edit Profile</button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function LogoutModal({onClose}){
  const [saving,setSaving]=useState(false);
  const go=()=>{
    setSaving(true);
    window.location.href='../auth/logout.php';
  };
  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal">
        <div className="mhd">
          <div>
            <div className="mt">Logout</div>
            <div className="ms">Are you sure you want to log out?</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="fac" style={{borderTop:'none',paddingTop:0,marginTop:0}}>
            <button className="btn btn-s sm" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="btn btn-p sm" onClick={go} disabled={saving} style={{background:'var(--error-600)'}}>{saving?'Logging out…':'Logout'}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Reports(){
  const rep=(DATA.reports||null);
  const exp=Array.isArray(rep&&rep.licenseExpiries)?rep.licenseExpiries:[];
  const miss=Array.isArray(rep&&rep.missingByGuard)?rep.missingByGuard:[];
  const ag=Array.isArray(rep&&rep.agencySummary)?rep.agencySummary:[];

  const [agency,setAgency]=useState('ALL');
  const [win,setWin]=useState('180');
  const [q,setQ]=useState('');

  const fmtCount=(n)=>new Intl.NumberFormat('en-US').format(Number(n)||0);
  const fmtMonth=(key)=>{
    if(!key)return '';
    const dt=new Date(`${key}-01T00:00:00`);
    if(Number.isNaN(dt.getTime()))return key;
    return new Intl.DateTimeFormat('en-US',{month:'short',year:'2-digit'}).format(dt);
  };

  const fmtDate=(iso)=>{
    if(!iso)return '—';
    const dt=new Date(String(iso).slice(0,10)+'T00:00:00');
    if(Number.isNaN(dt.getTime()))return String(iso);
    return new Intl.DateTimeFormat('en-US',{month:'short',day:'2-digit',year:'numeric'}).format(dt);
  };
  const daysUntil=(iso)=>{
    if(!iso)return null;
    const dt=new Date(String(iso).slice(0,10)+'T00:00:00');
    if(Number.isNaN(dt.getTime()))return null;
    const now=new Date();
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    return Math.round((dt.getTime()-today.getTime())/86400000);
  };
  const expLabel=(d)=>{
    if(d===null)return '—';
    if(d===0)return 'Today';
    if(d<0)return `${Math.abs(d)} day${Math.abs(d)===1?'':'s'} ago`;
    return `${d} day${d===1?'':'s'}`;
  };
  const tone=(d)=>{
    if(d===null)return 'muted';
    if(d<0)return 'bad';
    if(d<=30)return 'warn';
    return 'ok';
  };

  const cssVar=(name)=>{
    if(typeof window==='undefined')return '';
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  };

  const palette={
    navy:cssVar('--navy-600')||'#444CE7',
    navyDark:cssVar('--navy-700')||'#3538CD',
    navyLight:cssVar('--navy-200')||'#C7D7FD',
    success:cssVar('--success-500')||'#12B76A',
    successDark:cssVar('--success-700')||'#027A48',
    warning:cssVar('--warning-500')||'#F79009',
    orange:cssVar('--orange-500')||'#EF6820',
    error:cssVar('--error-600')||'#D92D20',
    errorDark:cssVar('--error-700')||'#B42318',
    grid:cssVar('--gray-200')||'#E4E7EC',
    gray:cssVar('--gray-500')||'#667085',
    text:cssVar('--gray-700')||'#344054',
    white:cssVar('--white')||'#ffffff',
  };

  // EChart wrapper for consistent theming and responsive resize.
  const EChart=({option,heightClass})=>{
    const ref=React.useRef(null);
    const chartRef=React.useRef(null);

    useEffect(()=>{
      if(!ref.current||!window.echarts)return;
      chartRef.current=window.echarts.init(ref.current,null,{renderer:'canvas'});
      const onResize=()=>{if(chartRef.current)chartRef.current.resize();};
      window.addEventListener('resize',onResize);
      return()=>{
        window.removeEventListener('resize',onResize);
        if(chartRef.current){
          chartRef.current.dispose();
          chartRef.current=null;
        }
      };
    },[]);

    useEffect(()=>{
      if(!chartRef.current||!option)return;
      chartRef.current.setOption(option,true);
    },[option]);

    return <div className={`ch-c ${heightClass||''}`.trim()} ref={ref}></div>;
  };

  const s=(DATA.summary||{});
  const tot=s.total_guards||0;
  const missing=s.guards_with_missing||0;
  const expiring=s.guards_with_expiring_license||0;
  const expired=s.guards_with_expired_license||0;
  const valid=Math.max(0,tot-(missing+expiring+expired));
  const sum=valid+missing+expiring+expired;

  const expBuckets=[
    {k:'Expired',v:exp.filter(r=>(Number(r.days_until_expiry)||0)<0).length,c:palette.error},
    {k:'0-30',v:exp.filter(r=>{const d=Number(r.days_until_expiry);return d>=0&&d<=30;}).length,c:palette.warning},
    {k:'31-90',v:exp.filter(r=>{const d=Number(r.days_until_expiry);return d>30&&d<=90;}).length,c:palette.orange},
    {k:'91-180',v:exp.filter(r=>{const d=Number(r.days_until_expiry);return d>90&&d<=180;}).length,c:palette.navy},
    {k:'181+',v:exp.filter(r=>{const d=Number(r.days_until_expiry);return d>180;}).length,c:palette.navyDark},
  ];

  const agencies=['ALL',...Array.from(new Set([
    ...ag.map(r=>String(r.agency||'')),
    ...exp.map(r=>String(r.agency||'')),
    ...miss.map(r=>String(r.agency||'')),
  ].filter(Boolean))).sort((a,b)=>a.localeCompare(b))];

  const lq=q.trim().toLowerCase();
  const winDays=(win==='ALL')?null:parseInt(win,10);
  const matchQ=(r)=>{
    if(!lq)return true;
    const s=String((r.full_name||r.name||'')+' '+(r.guard_no||r.no||'')+' '+(r.agency||'')).toLowerCase();
    return s.includes(lq);
  };

  const expFiltered=exp
    .filter(r=>agency==='ALL'||String(r.agency||'')===agency)
    .filter(matchQ)
    .filter(r=>{
      if(winDays===null)return true;
      const d=Number(r.days_until_expiry);
      if(Number.isNaN(d))return false;
      return d<=winDays;
    });

  const missFiltered=miss
    .filter(r=>agency==='ALL'||String(r.agency||'')===agency)
    .filter(matchQ);

  const expSoon=[...expFiltered]
    .filter(r=>Number(r.days_until_expiry)>=0)
    .sort((a,b)=>Number(a.days_until_expiry)-Number(b.days_until_expiry))
    .slice(0,25);
  const expiredList=[...expFiltered]
    .filter(r=>Number(r.days_until_expiry)<0)
    .sort((a,b)=>Number(a.days_until_expiry)-Number(b.days_until_expiry))
    .slice(0,25);
  const missTop=[...missFiltered]
    .sort((a,b)=>(b.missingReqs||[]).length-(a.missingReqs||[]).length)
    .slice(0,25);

  const topAg=ag.slice(0,8).map(r=>({
    k:String(r.agency||'—'),
    v:Number(r.total_guards)||0,
    c:palette.navy,
  }));

  const missDist=[
    {k:'1',v:miss.filter(r=>(r.missingReqs||[]).length===1).length,c:palette.warning},
    {k:'2',v:miss.filter(r=>(r.missingReqs||[]).length===2).length,c:palette.orange},
    {k:'3+',v:miss.filter(r=>(r.missingReqs||[]).length>=3).length,c:palette.error},
  ];

  const expByMonth=(()=>{
    const map={};
    exp.forEach(r=>{
      const d=String(r.expiry_date||'').slice(0,10);
      if(!d)return;
      const key=d.slice(0,7);
      map[key]=(map[key]||0)+1;
    });
    const keys=Object.keys(map).sort();
    const tail=keys.slice(Math.max(0,keys.length-8));
    return tail.map(k=>({key:k,label:fmtMonth(k),v:map[k]||0}));
  })();

  const axisLabelStyle={
    fontFamily:cssVar('--mono')||'monospace',
    fontSize:11,
    color:palette.gray,
  };

  const gridBase={left:36,right:16,top:20,bottom:24,containLabel:true};

  const donutOption=useMemo(()=>({
    textStyle:{fontFamily:cssVar('--f')||'Plus Jakarta Sans, sans-serif',color:palette.text},
    tooltip:{
      trigger:'item',
      confine:true,
      formatter:(p)=>`${p.name}: ${fmtCount(p.value)} (${Math.round(p.percent)}%)`,
    },
    series:[{
      type:'pie',
      radius:['58%','76%'],
      center:['50%','45%'],
      avoidLabelOverlap:true,
      label:{
        show:true,
        formatter:(p)=>`${p.name}\n${Math.round(p.percent)}%`,
        fontSize:11,
        color:palette.text,
      },
      labelLine:{length:10,length2:10},
      itemStyle:{borderColor:palette.white,borderWidth:2},
      data:[
        {value:valid,name:'Valid',itemStyle:{color:palette.success}},
        {value:missing,name:'Missing',itemStyle:{color:palette.error}},
        {value:expiring,name:'Expiring',itemStyle:{color:palette.warning}},
        {value:expired,name:'Expired',itemStyle:{color:palette.errorDark}},
      ],
    }],
  }),[valid,missing,expiring,expired,palette.success,palette.error,palette.warning,palette.errorDark,palette.white,palette.text]);

  const expWindowOption=useMemo(()=>({
    textStyle:{fontFamily:cssVar('--f')||'Plus Jakarta Sans, sans-serif',color:palette.text},
    grid:gridBase,
    tooltip:{
      trigger:'axis',
      confine:true,
      axisPointer:{type:'shadow'},
      formatter:(params)=>{
        const p=params[0];
        return `${p.axisValue}: ${fmtCount(p.value)} guards`;
      },
    },
    xAxis:{
      type:'category',
      data:expBuckets.map(x=>x.k),
      axisLine:{show:false},
      axisTick:{show:false},
      axisLabel:axisLabelStyle,
    },
    yAxis:{
      type:'value',
      axisLabel:{...axisLabelStyle,formatter:(v)=>fmtCount(v)},
      splitLine:{lineStyle:{color:palette.grid}},
    },
    series:[{
      type:'bar',
      data:expBuckets.map(x=>({value:x.v,itemStyle:{color:x.c}})),
      barWidth:'46%',
      label:{show:true,position:'top',formatter:(p)=>fmtCount(p.value)},
      itemStyle:{borderRadius:[10,10,0,0]},
    }],
  }),[expBuckets,palette.text,palette.grid,axisLabelStyle]);

  const topAgOption=useMemo(()=>({
    textStyle:{fontFamily:cssVar('--f')||'Plus Jakarta Sans, sans-serif',color:palette.text},
    grid:{left:120,right:16,top:16,bottom:16,containLabel:true},
    tooltip:{
      trigger:'axis',
      confine:true,
      axisPointer:{type:'shadow'},
      formatter:(params)=>{
        const p=params[0];
        return `${p.name}: ${fmtCount(p.value)} guards`;
      },
    },
    xAxis:{
      type:'value',
      axisLabel:{...axisLabelStyle,formatter:(v)=>fmtCount(v)},
      splitLine:{lineStyle:{color:palette.grid}},
      axisLine:{show:false},
      axisTick:{show:false},
    },
    yAxis:{
      type:'category',
      data:topAg.map(x=>x.k),
      axisLabel:{
        ...axisLabelStyle,
        formatter:(v)=>String(v).length>20?String(v).slice(0,20)+'…':String(v),
      },
      axisLine:{show:false},
      axisTick:{show:false},
    },
    series:[{
      type:'bar',
      data:topAg.map(x=>x.v),
      barWidth:'58%',
      itemStyle:{color:palette.navy,borderRadius:[0,8,8,0]},
      label:{show:true,position:'right',formatter:(p)=>fmtCount(p.value)},
    }],
  }),[topAg,palette.navy,palette.text,palette.grid,axisLabelStyle]);

  const missOption=useMemo(()=>({
    textStyle:{fontFamily:cssVar('--f')||'Plus Jakarta Sans, sans-serif',color:palette.text},
    grid:gridBase,
    tooltip:{
      trigger:'axis',
      confine:true,
      axisPointer:{type:'shadow'},
      formatter:(params)=>{
        const p=params[0];
        return `${p.axisValue}: ${fmtCount(p.value)} guards`;
      },
    },
    xAxis:{
      type:'category',
      data:missDist.map(x=>x.k),
      axisLine:{show:false},
      axisTick:{show:false},
      axisLabel:axisLabelStyle,
    },
    yAxis:{
      type:'value',
      axisLabel:{...axisLabelStyle,formatter:(v)=>fmtCount(v)},
      splitLine:{lineStyle:{color:palette.grid}},
    },
    series:[{
      type:'bar',
      data:missDist.map(x=>({value:x.v,itemStyle:{color:x.c}})),
      barWidth:'46%',
      label:{show:true,position:'top',formatter:(p)=>fmtCount(p.value)},
      itemStyle:{borderRadius:[10,10,0,0]},
    }],
  }),[missDist,palette.text,palette.grid,axisLabelStyle]);

  const areaFill=(typeof window!=='undefined'&&window.echarts)
    ?new window.echarts.graphic.LinearGradient(0,0,0,1,[
      {offset:0,color:palette.navyLight},
      {offset:1,color:'rgba(255,255,255,0)'}
    ])
    :palette.navyLight;

  const expTrendOption=useMemo(()=>({
    textStyle:{fontFamily:cssVar('--f')||'Plus Jakarta Sans, sans-serif',color:palette.text},
    grid:gridBase,
    tooltip:{
      trigger:'axis',
      confine:true,
      formatter:(params)=>{
        const p=params[0];
        return `${p.axisValue}: ${fmtCount(p.value)} expiries`;
      },
    },
    xAxis:{
      type:'category',
      boundaryGap:false,
      data:expByMonth.map(p=>p.label),
      axisLine:{show:false},
      axisTick:{show:false},
      axisLabel:axisLabelStyle,
    },
    yAxis:{
      type:'value',
      axisLabel:{...axisLabelStyle,formatter:(v)=>fmtCount(v)},
      splitLine:{lineStyle:{color:palette.grid}},
    },
    series:[{
      type:'line',
      data:expByMonth.map(p=>p.v),
      smooth:true,
      symbol:'circle',
      symbolSize:6,
      lineStyle:{width:3,color:palette.navy},
      itemStyle:{color:palette.navy},
      areaStyle:{color:areaFill},
    }],
  }),[expByMonth,areaFill,palette.navy,palette.text,palette.grid,axisLabelStyle]);

  return(
    <div className="bm-wrap">
      {rep&&(
        <div className="sg" style={{marginBottom:18}}>
          {[
            {l:'Total Guards',v:tot,i:'b',icon:<Ic.guard/>,sub:'All registered records',pct:100},
            {l:'Missing Requirements',v:missing,i:'o',icon:<Ic.warn/>,sub:'Need document submission',pct:tot>0?Math.round(missing/tot*100):0},
            {l:'License Expiring',v:expiring,i:'y',icon:<Ic.clock/>,sub:'Within 6 months',pct:tot>0?Math.round(expiring/tot*100):0},
            {l:'License Expired',v:expired,i:'r',icon:<Ic.shield/>,sub:'Immediate action required',pct:tot>0?Math.round(expired/tot*100):0},
          ].map(c=>(
            <div className="sc" key={c.l}>
              <div className="sc-top">
                <div>
                  <div className="sc-label">{c.l}</div>
                  <div className="sc-val">{c.v}</div>
                </div>
                <div className={`sc-ico ${c.i}`}>{c.icon}</div>
              </div>
              <div className="sc-bar"><div className={`sc-bar-fill ${c.i}`} style={{width:`${c.pct}%`}}></div></div>
              <div className="sc-foot">{c.sub}</div>
            </div>
          ))}
        </div>
      )}

      {rep&&(
        <div className="tc" style={{marginBottom:12}}>
          <div className="tctrl">
            <div className="sw" style={{maxWidth:360}}>
              <Ic.search/>
              <input className="si" value={q} onChange={e=>setQ(e.target.value)} placeholder="Search guard no, name, or agency…"/>
            </div>
            <select className="ts" value={agency} onChange={e=>setAgency(e.target.value)}>
              {agencies.map(a=><option key={a} value={a}>{a==='ALL'?'All Agencies':a}</option>)}
            </select>
            <select className="ts" value={win} onChange={e=>setWin(e.target.value)}>
              <option value="30">Expiry ≤ 30 days</option>
              <option value="90">Expiry ≤ 90 days</option>
              <option value="180">Expiry ≤ 180 days</option>
              <option value="365">Expiry ≤ 365 days</option>
              <option value="ALL">All records</option>
            </select>
            <button className="btn btn-g sm" onClick={()=>{setAgency('ALL');setWin('180');setQ('');}}>Reset</button>
          </div>
        </div>
      )}

      {!rep&&(
        <div className="card" style={{marginTop:14}}>
          <div className="empty">
            <div className="ei"><Ic.warn/></div>
            <div className="et">Reports unavailable</div>
            <div className="es">Reports are available for Admin on Jubecer and require database access.</div>
          </div>
        </div>
      )}


      {rep&&(
        <>
          <div className="shd" style={{marginBottom:14}}>
            <div>
              <div className="shd-t">Overview</div>
              <div className="shd-s">Charts based on current database data</div>
            </div>
          </div>

        <div className="cg">
          <div className="card ch" style={{padding:18}}>
            <div className="ch-h">
              <div>
                <div className="ch-t">Overall Status Mix</div>
                <div className="ch-s">Valid vs alerts</div>
              </div>
            </div>
            <EChart option={donutOption} heightClass="sm" />
            <div className="chips">
              <span className="chip ok">Valid <b>{valid}</b></span>
              <span className="chip bad">Missing <b>{missing}</b></span>
              <span className="chip warn">Expiring <b>{expiring}</b></span>
              <span className="chip bad">Expired <b>{expired}</b></span>
            </div>
          </div>

          <div className="card ch" style={{padding:18}}>
            <div className="ch-h">
              <div>
                <div className="ch-t">License Expiry Window</div>
                <div className="ch-s">Distribution by time-to-expiry</div>
              </div>
            </div>
            <EChart option={expWindowOption} />
          </div>

          <div className="card ch" style={{padding:18}}>
            <div className="ch-h">
              <div>
                <div className="ch-t">Top Agencies</div>
                <div className="ch-s">By total guards</div>
              </div>
            </div>
            <EChart option={topAgOption} />
          </div>

          <div className="card ch" style={{padding:18}}>
            <div className="ch-h">
              <div>
                <div className="ch-t">Missing Requirements Severity</div>
                <div className="ch-s">Guards grouped by missing count</div>
              </div>
            </div>
            <EChart option={missOption} />
          </div>

          <div className="card ch" style={{padding:18}}>
            <div className="ch-h">
              <div>
                <div className="ch-t">Expiry Volume Trend</div>
                <div className="ch-s">Count of expiries by month (latest)</div>
              </div>
            </div>
            {expByMonth.length>1
              ?(
                <>
                  <EChart option={expTrendOption} heightClass="sm" />
                  <div className="ch-mini">
                    {expByMonth.map(p=>(
                      <div key={p.key} className="ch-mi">{p.label}: <b style={{fontFamily:'var(--mono)'}}>{p.v}</b></div>
                    ))}
                  </div>
                </>
              )
              :<div className="empty" style={{padding:22}}><div className="es">Not enough data for trend.</div></div>
            }
          </div>
        </div>
        </>
      )}

      {rep&&(
        <div style={{marginTop:10,fontSize:11,color:'var(--gray-400)',fontFamily:'var(--mono)'}}>
          Generated: {fmtDate((rep.generatedAt||'').slice(0,10))}
        </div>
      )}
    </div>
  );
}

function EmployeeModal({mode,init,onClose,onSaved}){
  const isEdit=mode==='edit';
  const [saving,setSaving]=useState(false);
  const [toast,setToast]=useState('');
  const [f,setF]=useState({
    full_name:(init&&init.full_name)||'',
    email:(init&&init.email)||'',
    starting_date:(init&&init.starting_date)||'',
    role:(init&&init.role)||'employee',
  });

  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  const showT=(m)=>{setToast(m);setTimeout(()=>setToast(''),2400);};

  const save=async()=>{
    if(!f.full_name.trim()){showT('Full name is required.');return;}
    if(!f.role){showT('Role is required.');return;}

    setSaving(true);
    try{
      const fd=new FormData();
      if(isEdit){
        fd.append('api','update_employee');
        fd.append('employee_id',String(init.employee_id||''));
      }else{
        fd.append('api','create_employee');
      }
      fd.append('full_name',f.full_name.trim());
      fd.append('email',f.email.trim());
      fd.append('starting_date',f.starting_date);
      fd.append('role',f.role);
      await apiPost(fd);
      if(typeof onSaved==='function')onSaved();
      onClose();
    }catch(e){
      showT(String(e&&e.message?e.message:e));
    }finally{setSaving(false);}
  };

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal">
        <div className="mhd">
          <div>
            <div className="mt">{isEdit?'Edit Employee':'Add Employee'}</div>
            <div className="ms">{isEdit?'Update employee profile':'Add employee to master list'}</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          {toast&&<div className="toast"><div className="tico"><Ic.warn/></div><div className="ttxt">{toast}</div></div>}
          {isEdit&&(
            <div className="fg fg2">
              <div className="fgrp" style={{flex:'1 1 100%'}}>
                <label className="fl">Employee ID</label>
                <input className="fi" value={(init&&init.employee_id)||''} disabled />
              </div>
            </div>
          )}
          <div className="fg">
            <div className="fgrp">
              <label className="fl">Full Name <span>*</span></label>
              <input className="fi" value={f.full_name} onChange={u('full_name')} placeholder="Full name"/>
            </div>
            <div className="fgrp">
              <label className="fl">Role <span>*</span></label>
              <select className="fi" value={f.role} onChange={u('role')}>
                <option value="admin">Administrator</option>
                <option value="security_operation">Security Operation</option>
                <option value="employee">Employee</option>
              </select>
            </div>
          </div>
          <div className="fg">
            <div className="fgrp">
              <label className="fl">Gmail</label>
              <input className="fi" value={f.email} onChange={u('email')} placeholder="name@gmail.com"/>
            </div>
            <div className="fgrp">
              <label className="fl">Starting Date</label>
              <input className="fi" type="date" value={f.starting_date} onChange={u('starting_date')} />
            </div>
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="btn btn-p sm" onClick={save} disabled={saving}>{saving?'Saving…':(isEdit?'Save Changes':'Add Employee')}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function AccountModal({mode,init,employees,onClose,onSaved}){
  const isEdit=mode==='edit';
  const [saving,setSaving]=useState(false);
  const [toast,setToast]=useState('');
  const [f,setF]=useState({
    employee_id:(init&&init.employee_id)||'',
    password:'',
  });

  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  const showT=(m)=>{setToast(m);setTimeout(()=>setToast(''),2400);};

  const save=async()=>{
    if(!isEdit && !f.employee_id){showT('Employee is required.');return;}
    if(!f.password){showT('Password is required.');return;}

    setSaving(true);
    try{
      const fd=new FormData();
      if(isEdit){
        fd.append('api','update_user');
        fd.append('user_id',String(init.id));
      }else{
        fd.append('api','create_user');
        fd.append('employee_id',f.employee_id);
      }
      fd.append('password',f.password);
      await apiPost(fd);
      if(typeof onSaved==='function')onSaved();
      onClose();
    }catch(e){
      showT(String(e&&e.message?e.message:e));
    }finally{setSaving(false);}
  };

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal">
        <div className="mhd">
          <div>
            <div className="mt">{isEdit?'Reset Password':'Create Account'}</div>
            <div className="ms">{isEdit?'Set a new password for this account':'Create login account for an existing employee'}</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          {toast&&<div className="toast"><div className="tico"><Ic.warn/></div><div className="ttxt">{toast}</div></div>}

          {isEdit?(
            <div className="fg fg2">
              <div className="fgrp" style={{flex:'1 1 100%'}}>
                <label className="fl">User ID</label>
                <input className="fi" value={(init&&init.employee_id)||''} disabled />
              </div>
            </div>
          ):(
            <div className="fg fg2">
              <div className="fgrp" style={{flex:'1 1 100%'}}>
                <label className="fl">Employee <span>*</span></label>
                <select className="fi" value={f.employee_id} onChange={u('employee_id')}>
                  <option value="">Select employee…</option>
                  {employees.map(e=>{
                    const label=`${e.employee_id} — ${e.full_name}`;
                    return <option key={e.employee_id} value={e.employee_id}>{label}</option>;
                  })}
                </select>
              </div>
            </div>
          )}

          <div className="fg fg2">
            <div className="fgrp">
              <label className="fl">Password <span>*</span></label>
              <input className="fi" type="password" value={f.password} onChange={u('password')} placeholder="At least 6 characters" />
            </div>
          </div>

          <div className="fac">
            <button className="btn btn-s sm" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="btn btn-p sm" onClick={save} disabled={saving}>{saving?'Saving…':(isEdit?'Save Password':'Create Account')}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function DeleteUserModal({user,onClose,onDeleted}){
  const [saving,setSaving]=useState(false);
  const [toast,setToast]=useState('');
  const showT=(m)=>{setToast(m);setTimeout(()=>setToast(''),2400);};

  const del=async()=>{
    if(!user||!user.id)return;
    setSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','delete_user');
      fd.append('user_id',String(user.id));
      await apiPost(fd);
      if(typeof onDeleted==='function')onDeleted();
      onClose();
    }catch(e){
      showT(String(e&&e.message?e.message:e));
    }finally{setSaving(false);}
  };

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal">
        <div className="mhd">
          <div>
            <div className="mt">Delete User</div>
            <div className="ms">This action cannot be undone</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          {toast&&<div className="toast"><div className="tico"><Ic.warn/></div><div className="ttxt">{toast}</div></div>}
          <div className="card" style={{border:'1px solid var(--error-200)',background:'var(--error-50)',boxShadow:'none',borderRadius:'var(--r)',padding:'12px 14px'}}>
            <div style={{fontWeight:700,color:'var(--error-700)',marginBottom:4}}>Confirm delete</div>
            <div style={{fontSize:12,color:'var(--error-700)'}}>
              You are about to permanently delete:
              <span style={{fontFamily:'var(--mono)',marginLeft:8}}>{(user&&user.employee_id)||'—'}</span>
              <span style={{marginLeft:8,fontWeight:600}}>{(user&&user.full_name)||''}</span>
            </div>
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="btn btn-p sm" onClick={del} disabled={saving} style={{background:'var(--error-600)'}}>{saving?'Deleting…':'Delete User'}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function ArchiveModal({users,onClose,onActivate,kind='employees'}){
  const [q,setQ]=useState('');
  const idLabel=kind==='employees'?'Employee ID':'User ID';
  const sub=kind==='employees'
    ?'Employees deactivated / no longer in the company'
    :'User accounts deactivated / cannot log in';
  const ph=kind==='employees'
    ?'Search employee id, name, role, or gmail…'
    :'Search user id, name, role, or gmail…';

  const roleLabel=(r)=>{
    const x=String(r||'');
    if(x==='security_operation')return 'Security Operation';
    if(x==='employee')return 'Employee';
    if(x==='admin')return 'Administrator';
    return x||'—';
  };

  const lq=q.trim().toLowerCase();
  const fil=users
    .filter(u=>!u.is_active)
    .filter(u=>{
      if(!lq)return true;
      const s=(u.employee_id+' '+u.full_name+' '+u.role+' '+u.email).toLowerCase();
      return s.includes(lq);
    });

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal modal-archive">
        <div className="mhd">
          <div>
            <div className="mt">Archive</div>
            <div className="ms">{sub}</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="tctrl" style={{padding:0,marginBottom:12}}>
            <div className="sw" style={{maxWidth:360}}>
              <Ic.search/>
              <input className="si" value={q} onChange={e=>setQ(e.target.value)} placeholder={ph}/>
            </div>
          </div>

          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>{idLabel}</th>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Gmail</th>
                  <th>Deactivated</th>
                  <th className="actcol">Actions</th>
                </tr>
              </thead>
              <tbody>
                {fil.length===0
                  ?<tr><td colSpan="6" style={{padding:18,color:'var(--gray-500)'}}>No archived employees.</td></tr>
                  :fil.map(u=>{
                    const dt=(u.deactivated_at||'').slice(0,10);
                    return(
                      <tr key={u.id}>
                        <td><span className="gno">{u.employee_id||'—'}</span></td>
                        <td>{u.full_name||'—'}</td>
                        <td style={{fontSize:12,color:'var(--gray-600)'}}>{roleLabel(u.role)}</td>
                        <td style={{fontSize:12,color:'var(--gray-600)'}}>{u.email||'—'}</td>
                        <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{dt||'—'}</td>
                        <td className="actcol">
                          <div className="tact">
                            <button className="btn btn-op sm" onClick={()=>onActivate(u)}>Activate</button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                }
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

function UserManagement(){
  const isAdmin=(DATA.userRole||'')==='admin';
  const [tab,setTab]=useState('employees');

  const [employees,setEmployees]=useState([]);
  const [users,setUsers]=useState([]);
  const [auditLogs,setAuditLogs]=useState([]);
  const [auditTotal,setAuditTotal]=useState(0);
  const [auditPage,setAuditPage]=useState(1);
  const [auditPer,setAuditPer]=useState(25);
  const [auditQ,setAuditQ]=useState('');
  const [auditAction,setAuditAction]=useState('');
  const [auditActor,setAuditActor]=useState('');
  const [auditLoading,setAuditLoading]=useState(false);
  const [auditErr,setAuditErr]=useState('');
  const [loading,setLoading]=useState(false);
  const [err,setErr]=useState('');
  const [q,setQ]=useState('');
  const [rf,setRf]=useState('ALL');
  const [af,setAf]=useState('ALL');

  const [empModal,setEmpModal]=useState(null);
  const [acctModal,setAcctModal]=useState(null);
  const [delModal,setDelModal]=useState(null);
  const [arch,setArch]=useState(false);
  const [archRows,setArchRows]=useState([]);

  const mapRow=(r)=>({
    id:Number(r.id),
    employee_id:String(r.employee_id||''),
    full_name:String(r.full_name||''),
    email:String(r.email||''),
    starting_date:String(r.starting_date||''),
    role:String(r.role||''),
    is_active:Number(r.is_active||0)===1,
    deactivated_at:String(r.deactivated_at||''),
    created_at:String(r.created_at||''),
    updated_at:String(r.updated_at||''),
  });

  const fetchEmployees=async(statusFilter='active')=>{
    if(!isAdmin)return [];
    const fd=new FormData();
    fd.append('api','list_employees');
    const urlParams=statusFilter!=='all'?`?status=${statusFilter}`:'';
    const j=await apiPost(fd,urlParams);
    const arr=Array.isArray(j.employees)?j.employees:[];
    return arr.map(mapRow);
  };

  const fetchUsers=async(statusFilter='active')=>{
    if(!isAdmin)return [];
    const fd=new FormData();
    fd.append('api','list_users');
    const urlParams=statusFilter!=='all'?`?status=${statusFilter}`:'';
    const j=await apiPost(fd,urlParams);
    const arr=Array.isArray(j.users)?j.users:[];
    return arr.map(mapRow);
  };

  const loadEmployees=async()=>{
    setEmployees(await fetchEmployees('active'));
  };

  const loadUsers=async()=>{
    setUsers(await fetchUsers('active'));
  };

  const loadAll=async()=>{
    if(!isAdmin)return;
    setLoading(true);
    setErr('');
    try{
      await loadEmployees();
      await loadUsers();
    }catch(e){
      setErr(String(e&&e.message?e.message:e));
    }finally{setLoading(false);}
  };

  const loadAudit=async(nextPage=auditPage,nextPer=auditPer)=>{
    if(!isAdmin)return;
    setAuditLoading(true);
    setAuditErr('');
    try{
      const fd=new FormData();
      fd.append('api','list_audit_logs');
      fd.append('page',String(nextPage));
      fd.append('per',String(nextPer));
      fd.append('q',auditQ||'');
      fd.append('action',auditAction||'');
      fd.append('actor_employee_id',auditActor||'');
      const j=await apiPost(fd);
      const rows=Array.isArray(j.logs)?j.logs:[];
      setAuditLogs(rows.map(r=>({
        id:Number(r.id),
        actor_employee_id:String(r.actor_employee_id||''),
        actor_user_id:r.actor_user_id===null?null:Number(r.actor_user_id),
        action:String(r.action||''),
        target_type:String(r.target_type||''),
        target_id:String(r.target_id||''),
        detail:String(r.detail||''),
        ip_address:String(r.ip_address||''),
        user_agent:String(r.user_agent||''),
        created_at:String(r.created_at||''),
      })));
      setAuditTotal(Number(j.total||0));
      setAuditPage(Number(j.page||nextPage)||nextPage);
      setAuditPer(Number(j.per||nextPer)||nextPer);
    }catch(e){
      setAuditErr(String(e&&e.message?e.message:e));
    }finally{setAuditLoading(false);}
  };

  const syncUsers=async()=>{
    try{
      const fd=new FormData();
      fd.append('api','sync_users_from_employees');
      const j=await apiPost(fd);
      if(j&&j.ok){
        await loadUsers();
      }
    }catch(err){
      console.warn('Sync users failed:', err);
    }
  };

  useEffect(()=>{loadAll();},[]);

  useEffect(()=>{
    if(tab==='accounts'&&users.length>0){
      syncUsers();
    }
  },[tab]);

  useEffect(()=>{
    if(tab==='audit'){
      loadAudit(1,auditPer);
    }
  },[tab]);

  const roleLabel=(r)=>{
    if(r==='security_operation')return 'Security Operation';
    if(r==='employee')return 'Employee';
    if(r==='admin')return 'Administrator';
    return r||'—';
  };

  const badgeFor=(x)=>{
    return x.is_active
      ?<span className="badge bv">Active</span>
      :<span className="badge bm">Inactive</span>;
  };

  const fmtDT=(iso)=>{
    if(!iso)return '—';
    const d=new Date(String(iso).replace(' ','T'));
    if(Number.isNaN(d.getTime()))return String(iso);
    return new Intl.DateTimeFormat('en-US',{
      year:'numeric',month:'short',day:'2-digit',
      hour:'2-digit',minute:'2-digit'
    }).format(d);
  };

  const actionLabel=(a)=>({
    create_employee:'Created employee',
    update_employee:'Updated employee',
    toggle_employee_active:'Changed employee status',
    create_user:'Created user account',
    update_user:'Updated user account',
    toggle_user_active:'Changed account status',
    delete_user:'Deleted user account',
    login_success:'Login success',
    login_failed:'Login failed',
    account_setup_requested:'Requested account setup link',
    account_setup_request_failed:'Account setup request failed',
    account_setup_token_invalid:'Account setup link invalid',
    account_setup_token_used:'Account setup link already used',
    account_setup_token_expired:'Account setup link expired',
    account_password_set:'Password created',
    account_password_set_failed:'Password creation failed',
  }[a]||a||'—');

  const targetLabel=(type,id)=>{
    const t=String(type||'');
    const v=String(id||'');
    if(!t&&!v)return '—';
    if(t==='employee')return `Employee: ${v||'—'}`;
    if(t==='user'){
      if(/^[A-Z]{2,10}-\d{3,}$/.test(v))return `User: ${v}`;
      return `User ID: ${v||'—'}`;
    }
    return `${t||'Target'}: ${v||'—'}`;
  };

  const detailLabel=(s)=>{
    const raw=String(s||'');
    if(!raw)return '—';
    try{
      const o=JSON.parse(raw);
      if(o&&typeof o==='object'){
        if(Object.prototype.hasOwnProperty.call(o,'is_active')){
          return `Set status to ${Number(o.is_active)===1?'Active':'Inactive'}`;
        }
        if(Object.prototype.hasOwnProperty.call(o,'role')){
          return `Role: ${roleLabel(String(o.role||''))}`;
        }
        if(Object.prototype.hasOwnProperty.call(o,'password_changed')){
          return Number(o.password_changed)===1||o.password_changed===true?'Password changed':'No password change';
        }
        const keys=Object.keys(o);
        if(keys.length===0)return '—';
        return keys.slice(0,6).map(k=>`${k}: ${String(o[k])}`).join(', ');
      }
      return raw;
    }catch(e){
      return raw;
    }
  };

  const toggleEmployee=async(e)=>{
    try{
      const fd=new FormData();
      fd.append('api','toggle_employee_active');
      fd.append('employee_id',String(e.employee_id));
      fd.append('is_active',e.is_active?'0':'1');
      await apiPost(fd);
      await loadAll();
    }catch(err){
      alert(String(err&&err.message?err.message:err));
    }
  };

  const toggleUser=async(u)=>{
    try{
      const fd=new FormData();
      fd.append('api','toggle_user_active');
      fd.append('user_id',String(u.id));
      fd.append('is_active',u.is_active?'0':'1');
      await apiPost(fd);
      await loadUsers();
    }catch(err){
      alert(String(err&&err.message?err.message:err));
    }
  };

  const employeesWithoutAccount=employees
    .filter(e=>e.is_active)
    .filter(e=>!users.some(u=>u.employee_id===e.employee_id));

  const lq=q.trim().toLowerCase();
  const listSrc=(tab==='employees')?employees:((tab==='accounts')?users:[]);
  const data=listSrc
    .filter(x=>{
      if(rf==='ALL')return true;
      return String(x.role||'')===rf;
    })
    .filter(x=>{
      if(af==='ALL')return true;
      return af==='ACTIVE'?x.is_active:!x.is_active;
    })
    .filter(x=>{
      if(!lq)return true;
      const s=(x.employee_id+' '+x.full_name+' '+x.role+' '+x.email).toLowerCase();
      return s.includes(lq);
    });

  const total=data.length;
  const activeCount=data.filter(x=>x.is_active).length;
  const inactiveCount=data.filter(x=>!x.is_active).length;

  return(
    <div className="bm-wrap">
      {arch&&(
        <ArchiveModal
          users={archRows}
          kind={tab}
          onClose={()=>setArch(false)}
          onActivate={async(x)=>{
            if(tab==='employees'){
              await toggleEmployee({...x,is_active:false});
              try{setArchRows(await fetchEmployees('inactive'));}catch(e){}
            }else{
              await toggleUser({...x,is_active:false});
              try{setArchRows(await fetchUsers('inactive'));}catch(e){}
            }
          }}
        />
      )}
      {empModal&&(
        <EmployeeModal
          mode={empModal.mode}
          init={empModal.emp||null}
          onClose={()=>setEmpModal(null)}
          onSaved={loadAll}
        />
      )}
      {acctModal&&(
        <AccountModal
          mode={acctModal.mode}
          init={acctModal.user||null}
          employees={employeesWithoutAccount}
          onClose={()=>setAcctModal(null)}
          onSaved={loadAll}
        />
      )}
      {delModal&&(
        <DeleteUserModal
          user={delModal}
          onClose={()=>setDelModal(null)}
          onDeleted={loadAll}
        />
      )}

      <div className="sg sg-user" style={{marginBottom:18}}>
        {[
          {l:tab==='employees'?'Total Employees':'Total Accounts',v:total,i:'b',icon:<Ic.users/>,sub:tab==='employees'?'All employees (master list)':'All login accounts',pct:100},
          {l:'Active',v:activeCount,i:'g',icon:<Ic.check/>,sub:tab==='employees'?'Still in company':'Can log in',pct:total>0?Math.round(activeCount/total*100):0},
          {l:'Inactive',v:inactiveCount,i:'r',icon:<Ic.x/>,sub:tab==='employees'?'Archived employees':'Deactivated accounts',pct:total>0?Math.round(inactiveCount/total*100):0},
        ].map(c=>{
          const icoTone=c.i==='g'?'b':c.i;
          return(
            <div className="sc" key={c.l}>
              <div className="sc-top">
                <div>
                  <div className="sc-label">{c.l}</div>
                  <div className="sc-val">{c.v}</div>
                </div>
                <div className={`sc-ico ${icoTone}`}>{c.icon}</div>
              </div>
              <div className="sc-bar"><div className={`sc-bar-fill ${c.i==='g'?'b':c.i}`} style={{width:`${c.pct}%`}}></div></div>
              <div className="sc-foot">{c.sub}</div>
            </div>
          );
        })}
      </div>

      <div className="tc">
        <div className="tctrl">
          <button className={tab==='employees'?'btn btn-s sm':'btn btn-g sm'} onClick={()=>setTab('employees')}>Employees</button>
          <button className={tab==='accounts'?'btn btn-s sm':'btn btn-g sm'} onClick={()=>setTab('accounts')}>User Accounts</button>
          <button className={tab==='audit'?'btn btn-s sm':'btn btn-g sm'} onClick={()=>setTab('audit')}>Audit Logs</button>
          <div style={{flex:1}} />

          {tab!=='audit'
            ?(
              <>
                <div className="sw" style={{maxWidth:360}}>
                  <Ic.search/>
                  <input className="si" value={q} onChange={e=>setQ(e.target.value)} placeholder={tab==='employees'?'Search employee id, name, role…':'Search user id, name, role…'} />
                </div>
                <select className="ts" value={rf} onChange={e=>setRf(e.target.value)}>
                  <option value="ALL">All Roles</option>
                  <option value="admin">Administrator</option>
                  <option value="security_operation">Security Operation</option>
                  <option value="employee">Employee</option>
                </select>
                <select className="ts" value={af} onChange={e=>setAf(e.target.value)}>
                  <option value="ALL">All Status</option>
                  <option value="ACTIVE">Active</option>
                  <option value="INACTIVE">Inactive</option>
                </select>

                {tab==='employees'
                  ?<button className="btn btn-p sm" onClick={()=>setEmpModal({mode:'add'})}><Ic.plus/>Add Employee</button>
                  :<button className="btn btn-p sm" onClick={()=>setAcctModal({mode:'add'})} disabled={employeesWithoutAccount.length===0} title={employeesWithoutAccount.length===0?'No active employees without accounts':''}><Ic.plus/>Create Account</button>
                }
                <button className="btn btn-s sm" onClick={async()=>{
                  try{
                    const rows = tab==='employees' ? await fetchEmployees('inactive') : await fetchUsers('inactive');
                    setArchRows(rows);
                    setArch(true);
                  }catch(e){
                    alert(String(e&&e.message?e.message:e));
                  }
                }}>Archive</button>
                <button className="btn btn-g sm" onClick={()=>{setQ('');setRf('ALL');setAf('ALL');}}>Reset</button>
                <button className="btn btn-s sm" onClick={loadAll} disabled={loading}>{loading?'Refreshing…':'Refresh'}</button>
              </>
            )
            :(
              <>
                <div className="sw" style={{maxWidth:360}}>
                  <Ic.search/>
                  <input className="si" value={auditQ} onChange={e=>setAuditQ(e.target.value)} placeholder="Search actor, action, target, or details…" />
                </div>
                <input className="fi" value={auditActor} onChange={e=>setAuditActor(e.target.value)} placeholder="Actor ID (e.g. ADMIN-001)" style={{maxWidth:220}}/>
                <input className="fi" value={auditAction} onChange={e=>setAuditAction(e.target.value)} placeholder="Action (e.g. toggle_user_active)" style={{maxWidth:240}}/>
                <select className="ts" value={String(auditPer)} onChange={e=>setAuditPer(parseInt(e.target.value,10)||25)}>
                  <option value="25">25 / page</option>
                  <option value="50">50 / page</option>
                  <option value="100">100 / page</option>
                </select>
                <button className="btn btn-g sm" onClick={()=>{setAuditQ('');setAuditActor('');setAuditAction('');setAuditPage(1);}}>Reset</button>
                <button className="btn btn-s sm" onClick={()=>loadAudit(1,auditPer)} disabled={auditLoading}>{auditLoading?'Refreshing…':'Refresh'}</button>
              </>
            )
          }
        </div>

        {err&&(
          <div className="empty" style={{padding:18}}>
            <div className="es" style={{color:'var(--error-700)'}}>{err}</div>
          </div>
        )}

        {!err&&tab!=='audit'&&(
          <>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>{tab==='employees'?'Employee ID':'User ID'}</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Gmail</th>
                    <th>Starting Date</th>
                    <th>Status</th>
                    <th className="actcol">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading
                    ?<tr><td colSpan="7" style={{padding:18,color:'var(--gray-500)'}}>Loading…</td></tr>
                    :(data.length===0
                      ?<tr><td colSpan="7" style={{padding:18,color:'var(--gray-500)'}}>No records found.</td></tr>
                      :data.map(x=>{
                        const isSelf=String(DATA.userEmployeeId||'')===String(x.employee_id||'');
                        return(
                          <tr key={tab==='employees'?x.employee_id:x.id}>
                            <td><span className="gno">{x.employee_id||'—'}</span></td>
                            <td>{x.full_name||'—'}</td>
                            <td style={{fontSize:12,color:'var(--gray-600)'}}>{roleLabel(x.role)}</td>
                            <td style={{fontSize:12,color:'var(--gray-600)'}}>{x.email||'—'}</td>
                            <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{x.starting_date||'—'}</td>
                            <td>{badgeFor(x)}</td>
                            <td className="actcol">
                              <div className="tact">
                                {tab==='employees'
                                  ?<button className="btn btn-s sm" onClick={()=>setEmpModal({mode:'edit',emp:x})}>Edit</button>
                                  :<button className="btn btn-s sm" onClick={()=>setAcctModal({mode:'edit',user:x})}>Reset Password</button>
                                }
                                <button className="btn btn-g sm" onClick={()=>tab==='employees'?toggleEmployee(x):toggleUser(x)} disabled={isSelf} title={isSelf?'You cannot deactivate your own record':''}>{x.is_active?'Deactivate':'Activate'}</button>
                                {tab==='accounts'&&(
                                  <button className="btn btn-p sm" onClick={()=>setDelModal(x)} disabled={isSelf} style={{background:'var(--error-600)'}} title={isSelf?'You cannot delete your own account':''}>Delete</button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })
                    )
                  }
                </tbody>
              </table>
            </div>
          </>
        )}

        {tab==='audit'&&(
          <>
            {auditErr&&(
              <div className="empty" style={{padding:18}}>
                <div className="es" style={{color:'var(--error-700)'}}>{auditErr}</div>
              </div>
            )}
            {!auditErr&&(
              <>
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>IP</th>
                        <th>Detail</th>
                      </tr>
                    </thead>
                    <tbody>
                      {auditLoading
                        ?<tr><td colSpan="6" style={{padding:18,color:'var(--gray-500)'}}>Loading…</td></tr>
                        :(auditLogs.length===0
                          ?<tr><td colSpan="6" style={{padding:18,color:'var(--gray-500)'}}>No logs found.</td></tr>
                          :auditLogs.map(l=>{
                            const dt=fmtDT(l.created_at);
                            const tgt=targetLabel(l.target_type,l.target_id);
                            const actor=l.actor_employee_id||'—';
                            const act=actionLabel(l.action);
                            const det=detailLabel(l.detail);
                            return(
                              <tr key={l.id}>
                                <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{dt||'—'}</td>
                                <td><span className="gno">{actor}</span></td>
                                <td style={{fontSize:12,color:'var(--gray-600)',fontWeight:600}}>{act}</td>
                                <td style={{fontSize:12,color:'var(--gray-600)'}}>{tgt||'—'}</td>
                                <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{l.ip_address||'—'}</td>
                                <td style={{fontSize:12,color:'var(--gray-600)'}}>{det}</td>
                              </tr>
                            );
                          })
                        )
                      }
                    </tbody>
                  </table>
                </div>

                <div className="pgn" style={{marginTop:12}}>
                  <div className="pgi">Showing {auditTotal===0?0:((auditPage-1)*auditPer+1)}–{Math.min(auditPage*auditPer,auditTotal)} of {auditTotal} logs</div>
                  <div className="pgb">
                    <button className="pb" onClick={()=>{const np=Math.max(1,auditPage-1);setAuditPage(np);loadAudit(np,auditPer);}} disabled={auditLoading||auditPage<=1}><Ic.chL/></button>
                    <button className="pb" onClick={()=>{const np=auditPage+1;setAuditPage(np);loadAudit(np,auditPer);}} disabled={auditLoading||auditPage*auditPer>=auditTotal}><Ic.chR/></button>
                  </div>
                </div>
              </>
            )}
          </>
        )}
      </div>
    </div>
  );
}

function AddModal({close,save,agencies}){
  const [f,setF]=useState({last:'',first:'',mid:'',suffix:'',bday:'',age:'',agency:'',contact:'',deployed:''});
  const u=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  const bd=e=>{
    const iso=e.target.value;
    const d=new Date(String(iso).slice(0,10)+'T00:00:00');
    if(Number.isNaN(d.getTime())){setF(p=>({...p,bday:iso,age:''}));return;}
    const now=new Date();
    let a=now.getFullYear()-d.getFullYear();
    const m=now.getMonth()-d.getMonth();
    if(m<0||(m===0&&now.getDate()<d.getDate()))a--;
    setF(p=>({...p,bday:iso,age:String(Math.max(0,a))}));
  };
  const go=()=>{if(!f.last.trim()||!f.first.trim()){alert('Last Name and First Name are required.');return;}save(f);close();};
  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&close()}>
      <div className="modal">
        <div className="mhd">
          <div><div className="mt">Add New Guard</div><div className="ms">Complete all required fields to register</div></div>
          <button className="mc" onClick={close}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="fg">
            <div className="fgrp"><label className="fl">Last Name <span>*</span></label><input className="fi" value={f.last} onChange={u('last')} placeholder="Dela Cruz"/></div>
            <div className="fgrp"><label className="fl">First Name <span>*</span></label><input className="fi" value={f.first} onChange={u('first')} placeholder="Juan"/></div>
            <div className="fgrp"><label className="fl">Middle Name</label><input className="fi" value={f.mid} onChange={u('mid')} placeholder="Santos"/></div>
          </div>
          <div className="fg">
            <div className="fgrp"><label className="fl">Suffix</label><select className="fi" value={f.suffix} onChange={u('suffix')}>{SF.map(s=><option key={s} value={s}>{s||'None'}</option>)}</select></div>
            <div className="fgrp"><label className="fl">Birthdate</label><input className="fi" type="date" value={f.bday} onChange={bd}/></div>
            <div className="fgrp"><label className="fl">Age</label><input className="fi" value={f.age} readOnly placeholder="Auto"/></div>
          </div>
          <div className="fg fg2">
            <div className="fgrp">
              <label className="fl">Agency</label>
              <input className="fi" value={f.agency} onChange={u('agency')} list="agency_list_add_guard" placeholder="Type or select agency…"/>
              <datalist id="agency_list_add_guard">
                {(Array.isArray(agencies)?agencies:[]).map(a=><option key={a} value={a}/>) }
              </datalist>
            </div>
            <div className="fgrp"><label className="fl">Contact No.</label><input className="fi" value={f.contact} onChange={u('contact')} placeholder="09XXXXXXXXX"/></div>
          </div>
          <div className="fg fg2">
            <div className="fgrp" style={{flex:'1 1 100%'}}><label className="fl">Deploy Date</label><input className="fi" type="date" value={f.deployed} onChange={u('deployed')}/></div>
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={close}>Cancel</button>
            <button className="btn btn-p sm" onClick={go}><Ic.plus/>Save Guard</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Dashboard({guards,onAdd,onGo,summary}){
  const tot=guards.length;
  const mis=guards.filter(g=>g.missing>0).length;
  const exp=guards.filter(g=>g.status==='EXPIRING').length;
  const ed=guards.filter(g=>g.status==='EXPIRED').length;
  const alerts=guards.filter(g=>g.status==='EXPIRED'||g.status==='MISSING').slice(0,7);
  const cards=[
    {l:'Total Guards',v:tot,i:'blue',icon:<Ic.guard/>,sub:'All registered records',pct:100},
    {l:'Missing Requirements',v:mis,i:'o',icon:<Ic.warn/>,sub:'Need document submission',pct:tot>0?Math.round(mis/tot*100):0},
    {l:'License Expiring',v:exp,i:'y',icon:<Ic.clock/>,sub:'Within 6 months',pct:tot>0?Math.round(exp/tot*100):0},
    {l:'License Expired',v:ed,i:'r',icon:<Ic.shield/>,sub:'Immediate action required',pct:tot>0?Math.round(ed/tot*100):0},
  ];
  return(
    <>
      <div className="sg">
        {cards.map(c=>(
          <div className="sc" key={c.l}>
            <div className="sc-top">
              <div>
                <div className="sc-label">{c.l}</div>
                <div className="sc-val">{c.v}</div>
              </div>
              <div className={`sc-ico ${c.i}`}>{c.icon}</div>
            </div>
            <div className="sc-bar"><div className={`sc-bar-fill ${c.i}`} style={{width:`${c.pct}%`}}></div></div>
            <div className="sc-foot">{c.sub}</div>
          </div>
        ))}
      </div>

      <div className="shd" style={{marginBottom:14}}><div className="shd-t">Quick Actions</div></div>
      <div className="qa" style={{marginBottom:28}}>
        <div className="qa-item" onClick={onAdd}>
          <div className="qa-ico b"><Ic.plus/></div>
          <div><div className="qa-lbl">Add Guard</div><div className="qa-sub">Register a new security guard</div></div>
        </div>
        <div className="qa-item" onClick={()=>onGo()}>
          <div className="qa-ico g"><Ic.guard/></div>
          <div><div className="qa-lbl">Manage Guards</div><div className="qa-sub">View all {tot} records</div></div>
        </div>
        <div className="qa-item" onClick={()=>onGo('EXPIRED')}>
          <div className="qa-ico y"><Ic.clock/></div>
          <div><div className="qa-lbl">Renewal Queue</div><div className="qa-sub">{ed+exp} licenses to renew</div></div>
        </div>
      </div>

      <div className="shd">
        <div><div className="shd-t">License Alerts</div><div className="shd-s">Guards requiring immediate attention</div></div>
        <button className="btn btn-g sm" onClick={()=>onGo()}>View all →</button>
      </div>
      <div className="card">
        {alerts.length===0
          ?<div className="empty"><div className="ei"><Ic.check/></div><div className="et">All Clear</div><div className="es">No alerts at this time</div></div>
          :alerts.map(g=>{
            const nm=`${g.last}, ${g.first} ${g.mid}`;
            const dt=g.status==='EXPIRED'?`Expired ${g.expDate}`:`Missing: ${g.missingReqs.join(', ')}`;
            return(
              <div className="al-row" key={g.id}>
                <div className="al-av">{(g.first[0]||'')+(g.last[0]||'')}</div>
                <div className="al-info">
                  <div className="al-name">{nm}</div>
                  <div className="al-meta">{g.agency} · {dt}</div>
                </div>
                <Badge s={g.status}/>
              </div>
            );
          })}
      </div>
    </>
  );
}

const PER=10;

function GuardsArchiveModal({guards,onClose,onRestore,loading}){
  const [q,setQ]=useState('');
  const lq=q.trim().toLowerCase();
  const fil=(Array.isArray(guards)?guards:[]).filter(g=>{
    if(!lq)return true;
    const s=(String(g.no||'')+' '+String(g.last||'')+' '+String(g.first||'')+' '+String(g.mid||'')+' '+String(g.agency||'')+' '+String(g.contact||'')).toLowerCase();
    return s.includes(lq);
  });

  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal modal-archive">
        <div className="mhd">
          <div>
            <div className="mt">Archive</div>
            <div className="ms">Guards archived / deleted from active list</div>
          </div>
          <button className="mc" onClick={onClose}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="tctrl" style={{padding:0,marginBottom:12}}>
            <div className="sw" style={{maxWidth:360}}>
              <Ic.search/>
              <input className="si" value={q} onChange={e=>setQ(e.target.value)} placeholder="Search guard no, name, agency, or contact…"/>
            </div>
          </div>

          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Guard No.</th>
                  <th>Name</th>
                  <th>Agency</th>
                  <th>Deploy Date</th>
                  <th>Contact</th>
                  <th className="actcol">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading
                  ?<tr><td colSpan="6" style={{padding:18,color:'var(--gray-500)'}}>Loading…</td></tr>
                  :fil.length===0
                    ?<tr><td colSpan="6" style={{padding:18,color:'var(--gray-500)'}}>No archived guards.</td></tr>
                    :fil.map((g,i)=>{
                      const nm=`${g.last||''}, ${g.first||''} ${g.mid||''}`.trim();
                      return(
                        <tr key={g.id}>
                          <td><span className="gno">{i+1}</span></td>
                          <td>{nm||'—'}</td>
                          <td>{g.agency||'—'}</td>
                          <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{fmtDate(g.deployed)}</td>
                          <td style={{fontFamily:'var(--mono)',fontSize:12,color:'var(--gray-600)'}}>{g.contact||'—'}</td>
                          <td className="actcol">
                            <div className="tact">
                              <button className="btn btn-op sm" onClick={()=>onRestore(g)}>Restore</button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                }
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

function ConfirmArchiveGuardModal({g,onClose,onConfirm,saving}){
  if(!g)return null;
  const nm=`${g.last||''}, ${g.first||''} ${g.mid||''}`.trim();
  return(
    <div className="overlay" onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div className="modal">
        <div className="mhd">
          <div>
            <div className="mt">Archive Guard</div>
            <div className="ms">This will move the guard to Archive</div>
          </div>
          <button className="mc" onClick={onClose} disabled={saving}><Ic.x/></button>
        </div>
        <div className="mb">
          <div className="card" style={{border:'1px solid var(--error-200)',background:'var(--error-50)',boxShadow:'none',borderRadius:'var(--r)',padding:'12px 14px'}}>
            <div style={{fontWeight:700,color:'var(--error-700)',marginBottom:4}}>Confirm archive</div>
            <div style={{fontSize:12,color:'var(--error-700)'}}>
              You are about to archive:
              <span style={{fontFamily:'var(--mono)',marginLeft:8}}>{g.displayNo?`#${g.displayNo}`:'—'}</span>
              <span style={{marginLeft:8,fontWeight:600}}>{nm||'—'}</span>
            </div>
          </div>
          <div className="fac">
            <button className="btn btn-s sm" onClick={onClose} disabled={saving}>Cancel</button>
            <button className="btn btn-p sm" onClick={onConfirm} disabled={saving} style={{background:'var(--error-600)'}}>{saving?'Archiving…':'Archive'}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function GuardsList({guards,setGuards,initSt}){
  const [q,setQ]=useState('');
  const [sf,setSf]=useState(initSt||'ALL');
  const [af,setAf]=useState('ALL');
  const [pg,setPg]=useState(1);
  const [view,setView]=useState(null);
  const [add,setAdd]=useState(false);
  const [toast,setToast]=useState('');
  const [archOpen,setArchOpen]=useState(false);
  const [archLoading,setArchLoading]=useState(false);
  const [archGuards,setArchGuards]=useState([]);
  const [confirmArch,setConfirmArch]=useState(null);
  const [confirmSaving,setConfirmSaving]=useState(false);

  const agencyOptions = useMemo(
    () => [...new Set((guards || []).map(g => g && g.agency).filter(Boolean))],
    [guards]
  );

  const daysUntil=(iso)=>{
    if(!iso)return null;
    const dt=new Date(String(iso).slice(0,10)+'T00:00:00');
    if(Number.isNaN(dt.getTime()))return null;
    const now=new Date();
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    return Math.round((dt.getTime()-today.getTime())/86400000);
  };

  const fmtDate=(iso)=>{
    if(!iso)return '—';
    const dt=new Date(String(iso).slice(0,10)+'T00:00:00');
    if(Number.isNaN(dt.getTime()))return String(iso);
    return new Intl.DateTimeFormat('en-GB',{day:'2-digit',month:'long',year:'numeric'}).format(dt);
  };

  const expLabel=(d)=>{
    if(d===null)return '—';
    if(d===0)return 'Today';
    if(d<0)return `${Math.abs(d)} day${Math.abs(d)===1?'':'s'} ago`;
    return `${d} day${d===1?'':'s'}`;
  };

  const expTone=(d)=>{
    if(d===null)return 'muted';
    if(d<0)return 'bad';
    if(d<=30)return 'warn';
    return 'ok';
  };

  const showT=m=>{setToast(m);setTimeout(()=>setToast(''),3000);};

  const openArchive=async()=>{
    setArchOpen(true);
    setArchLoading(true);
    try{
      const fd=new FormData();
      fd.append('api','list_guards');
      const j=await apiPost(fd,'?status=inactive');
      setArchGuards(Array.isArray(j.guards)?j.guards:[]);
    }catch(e){
      setArchGuards([]);
      showT(e.message||'Failed to load archive.');
    }finally{
      setArchLoading(false);
    }
  };

  const restoreGuard=async g=>{
    try{
      const fd=new FormData();
      fd.append('api','toggle_guard_active');
      fd.append('guard_id',String(g.id));
      fd.append('is_active','1');
      await apiPost(fd);
      setArchGuards(p=>p.filter(x=>x.id!==g.id));
      setGuards(p=>[{...g,recordStatus:'active'},...p]);
      showT('Guard restored successfully.');
    }catch(e){
      showT(e.message||'Failed to restore guard.');
    }
  };

  const archiveGuard=async g=>{
    if(!g||!g.id)return;
    setConfirmSaving(true);
    try{
      const fd=new FormData();
      fd.append('api','toggle_guard_active');
      fd.append('guard_id',String(g.id));
      fd.append('is_active','0');
      await apiPost(fd);
      setGuards(p=>p.filter(x=>x.id!==g.id));
      if(view&&view.id===g.id){setView(null);}
      setConfirmArch(null);
      showT('Guard archived successfully.');
    }catch(e){
      showT(e.message||'Failed to archive guard.');
    }finally{
      setConfirmSaving(false);
    }
  };
  const fil=guards.filter(g=>{
    const lq=q.toLowerCase();
    const no=String(g.no||'').toLowerCase();
    const nm=String(`${g.last||''} ${g.first||''}`).toLowerCase();
    const ag=String(g.agency||'').toLowerCase();
    const ct=String(g.contact||'');
    const mq=!q||(no.includes(lq)||nm.includes(lq)||ag.includes(lq)||ct.includes(lq));
    return mq&&(sf==='ALL'||g.status===sf)&&(af==='ALL'||g.agency===af);
  });
  const pages=Math.max(1,Math.ceil(fil.length/PER));
  const rows=fil.slice((pg-1)*PER,pg*PER);
  const s1=(pg-1)*PER+1,s2=Math.min(pg*PER,fil.length);
  useEffect(()=>setPg(1),[q,sf,af]);
  const sv=async form=>{
    if(DATA.company!=='jubecer'){showT('Adding guards is available for Jubecer only.');return;}
    if(!form.last.trim()||!form.first.trim()){showT('Last Name and First Name are required.');return;}
    try{
      const fd=new FormData();
      fd.append('api','create_guard');
      fd.append('last_name',form.last);
      fd.append('first_name',form.first);
      fd.append('middle_name',form.mid);
      fd.append('suffix',form.suffix);
      fd.append('birthdate',form.bday);
      fd.append('age',String(form.age||''));
      fd.append('agency',form.agency);
      fd.append('contact_no',form.contact);
      fd.append('deployed',form.deployed);
      const j=await apiPost(fd);
      const missingReqs=Array.isArray(RQS)?RQS.slice():[];
      const g={id:j.guard_id,no:j.guard_no,
        last:form.last,first:form.first,mid:form.mid,suffix:form.suffix,
        bday:form.bday,age:parseInt(form.age)||0,agency:form.agency,contact:form.contact,deployed:form.deployed,
        recordStatus:'active',status:missingReqs.length>0?'MISSING':'VALID',expDate:'',missing:missingReqs.length,missingReqs};
      setGuards(p=>[g,...p]);
      showT('Guard registered successfully.');
    }catch(e){
      showT(e.message||'Failed to add guard.');
    }
  };
  const pns=[];
  for(let p=Math.max(1,pg-2);p<=Math.min(pages,pg+2);p++)pns.push(p);
  return(
    <>
      {archOpen&&<GuardsArchiveModal guards={archGuards} loading={archLoading} onClose={()=>setArchOpen(false)} onRestore={restoreGuard}/>}
      {confirmArch&&(
        <ConfirmArchiveGuardModal
          g={confirmArch}
          saving={confirmSaving}
          onClose={()=>!confirmSaving&&setConfirmArch(null)}
          onConfirm={()=>archiveGuard(confirmArch)}
        />
      )}
      {view&&<GuardModal g={view} close={()=>setView(null)} onUpdated={(ng)=>{
        setGuards(p=>p.map(x=>x.id===ng.id?{...x,...ng}:x));
        setView(p=>p&&p.id===ng.id?{...p,...ng}:ng);
      }}/>} 
      {add&&<AddModal close={()=>setAdd(false)} save={sv} agencies={agencyOptions}/>} 
      {toast&&<div className="toast"><div className="tico"><Ic.check/></div><div className="ttxt">{toast}</div></div>} 
      <div className="ph">
        <div>
        </div>
        <div className="ph-actions">
          <button className="btn btn-g sm" onClick={openArchive}>Archive</button>
          <button className="btn btn-p sm" onClick={()=>setAdd(true)}><Ic.plus/>Add Guard</button>
        </div>
      </div>
      <div className="tc">
        <div className="tctrl">
          <div className="sw"><Ic.search/><input className="si" placeholder="Search by name, ID, agency or contact…" value={q} onChange={e=>setQ(e.target.value)}/></div>
          <select className="ts" value={sf} onChange={e=>setSf(e.target.value)}>
            <option value="ALL">All Status</option>
            {STS.map(s=><option key={s}>{s}</option>)}
          </select>
          <select className="ts" value={af} onChange={e=>setAf(e.target.value)}>
            <option value="ALL">All Agencies</option>
            {agencyOptions.map(a=><option key={a} value={a}>{a}</option>)}
          </select>
          {(q||sf!=='ALL'||af!=='ALL')&&<button className="btn btn-g sm" onClick={()=>{setQ('');setSf('ALL');setAf('ALL');}}>Clear</button>}
        </div>
        {rows.length===0
          ?<div className="empty"><div className="ei"><Ic.guard/></div><div className="et">No results found</div><div className="es">Try a different search or filter</div></div>
          :<div className="table-wrap">
            <table>
              <thead><tr>
                <th>Guard No.</th><th>Name</th><th>Agency</th><th>Deploy Date</th><th>Contact</th>
                <th>Missing</th><th>License Status</th><th>Expiry Date</th><th className="actcol">Actions</th>
              </tr></thead>
              <tbody>
                {rows.map((g,i)=>{
                  const seqNo=(pg-1)*PER+i+1;
                  const open=()=>setView({...g,displayNo:seqNo});
                  const askArchive=()=>setConfirmArch({...g,displayNo:seqNo});
                  return(
                  <tr key={g.id}>
                    <td><span className="gno">{(pg-1)*PER+i+1}</span></td>
                    <td><span className="gnm" onClick={open}>{g.last}, {g.first} {g.mid}</span></td>
                    <td>{g.agency}</td>
                    <td><span className="dep-cell" style={{fontFamily:'var(--mono)',fontSize:12}}>{fmtDate(g.deployed)}</span></td>
                    <td style={{fontFamily:'var(--mono)',fontSize:12}}>{g.contact}</td>
                    <td><span className={g.missing===0?'mc0':'mcn'}>{g.missing}</span></td>
                    <td><Badge s={g.status}/></td>
                    <td>
                      <div className="exp-cell">
                        <div className="exp-date">{fmtDate(g.expDate)}</div>
                        <div className={`exp-days ${'exp-'+expTone(daysUntil(g.expDate))}`}>{expLabel(daysUntil(g.expDate))}</div>
                      </div>
                    </td>
                    <td className="actcol">
                      <div className="tact">
                        <button className="btn btn-op sm" onClick={open}>Open</button>
                        <button className="btn btn-p sm" onClick={askArchive} style={{background:'var(--error-600)'}}>Delete</button>
                      </div>
                    </td>
                  </tr>
                  );
                })}
              </tbody>
            </table>
          </div>}
        <div className="pgn">
          <div className="pgi">Showing {fil.length>0?s1:0}–{s2} of {fil.length} guards</div>
          <div className="pgb">
            <button className="pb" onClick={()=>setPg(p=>Math.max(1,p-1))} disabled={pg===1}><Ic.chL/></button>
            {pns.map(p=><button key={p} className={`pb${p===pg?' on':''}`} onClick={()=>setPg(p)}>{p}</button>)}
            <button className="pb" onClick={()=>setPg(p=>Math.min(pages,p+1))} disabled={pg===pages}><Ic.chR/></button>
          </div>
        </div>
      </div>
    </>
  );
}

function App(){
  const [pg,setPg]=useState('dashboard');
  const [guards,setGuards]=useState(GD);
  const [addOpen,setAddOpen]=useState(false);
  const [sbOpen,setSbOpen]=useState(false);
  const [fst,setFst]=useState('ALL');
  const expired=guards.filter(g=>g.status==='EXPIRED').length;

  useEffect(()=>{
    if(!sbOpen)return;
    const onKey=e=>{if(e.key==='Escape')setSbOpen(false);};
    window.addEventListener('keydown',onKey);
    return()=>window.removeEventListener('keydown',onKey);
  },[sbOpen]);

  useEffect(()=>{setSbOpen(false);},[pg]);

  const isAdmin=(DATA.userRole||'')==='admin';
  const nav=[
    {id:'dashboard',label:'Dashboard',icon:<Ic.dash/>},
    ...(isAdmin?
      [{id:'users',label:'User Management',icon:<Ic.users/>}]
      :[]
    ),
    {id:'guards',label:'Guards Management',icon:<Ic.guard/>,badge:expired>0?expired:0},
    ...(isAdmin?
      [{id:'reports',label:'Reports',icon:<Ic.rep/>}]
      :[]
    ),
  ];

  const pgTitle=(id)=>({
    dashboard:'Admin Dashboard',
    guards:'Guards Management',
    reports:'Reports',
    users:'User Management',
  }[id]||'Dashboard');

  const roleLabel = (r) => {
    if (r === 'security_operation') return 'Security Operation';
    if (r === 'employee') return 'Employee';
    return 'Administrator';
  };

  const AccountMenu = () => {
    const [open, setOpen] = useState(false);
    const [logoutOpen, setLogoutOpen] = useState(false);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => {
        const root = document.getElementById('sbAccountMenu');
        if (!root) return;
        if (!root.contains(e.target)) setOpen(false);
      };
      document.addEventListener('mousedown', onDoc);
      return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);

    const isAdmin = (DATA.userRole || '') === 'admin';
    return (
      <div className="sb-dd" id="sbAccountMenu">
        {logoutOpen&&<LogoutModal onClose={()=>setLogoutOpen(false)}/>}
        <button
          className="sb-trigger"
          type="button"
          aria-haspopup="menu"
          aria-expanded={open ? 'true' : 'false'}
          onClick={() => setOpen(v => !v)}
        >
          <div className="sb-av">{DATA.userInitials||'U'}</div>
          <div className="sb-meta">
            <div className="sb-uname" style={{whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{DATA.userName||'User'}</div>
            <div className="sb-urole">{roleLabel(DATA.userRole||'')}</div>
          </div>
          <span className="sb-chev" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9l6 6 6-6"/></svg>
          </span>
        </button>

        {open && (
          <div className="sb-menu" role="menu" aria-label="Account actions">
            {isAdmin && (
              <a className="sb-mi" role="menuitem" href="../auth/switch_company.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12a9 9 0 1 1-3.03-6.72"/><path d="M21 3v6h-6"/></svg>
                Switch Company
              </a>
            )}
            <button className="sb-mi d" type="button" role="menuitem" onClick={()=>{setOpen(false);setLogoutOpen(true);}}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 17l-1 4 4-1"/><path d="M3 12h11"/><path d="M10 8l4 4-4 4"/><path d="M14 4h6v16h-6"/></svg>
              Logout
            </button>
          </div>
        )}
      </div>
    );
  };
  const addG=async form=>{
    if(DATA.company!=='jubecer'){alert('Adding guards is available for Jubecer only.');return;}
    if(!form.last.trim()||!form.first.trim()){alert('Last Name and First Name are required.');return;}
    try{
      const fd=new FormData();
      fd.append('api','create_guard');
      fd.append('last_name',form.last);
      fd.append('first_name',form.first);
      fd.append('middle_name',form.mid);
      fd.append('suffix',form.suffix);
      fd.append('birthdate',form.bday);
      fd.append('age',String(form.age||''));
      fd.append('agency',form.agency);
      fd.append('contact_no',form.contact);
      fd.append('deployed',form.deployed);
      const j=await apiPost(fd);
      const missingReqs=Array.isArray(RQS)?RQS.slice():[];
      const g={id:j.guard_id,no:j.guard_no,
        last:form.last,first:form.first,mid:form.mid,suffix:form.suffix,
        bday:form.bday,age:parseInt(form.age)||0,agency:form.agency,contact:form.contact,deployed:form.deployed,
        recordStatus:'active',status:missingReqs.length>0?'MISSING':'VALID',expDate:'',missing:missingReqs.length,missingReqs};
      setGuards(p=>[g,...p]);
      setPg('guards');
    }catch(e){
      alert(e.message||'Failed to add guard.');
    }
  };
  return(
    <div className={`shell${sbOpen?' sb-open':''}`}>
      {addOpen&&<AddModal close={()=>setAddOpen(false)} save={addG}/>}
      <div className="sb-backdrop" aria-hidden="true" onClick={()=>setSbOpen(false)}/>
      <aside className="sb">
        <div className="sb-top">
          <div className="sb-brand">
             <div className="sb-logo" aria-hidden="true">
               <img src="../assets/img/jubecer-logo.svg" alt="" />
             </div>
             <div><div className="sb-name">ERMS</div><div className="sb-tagline">{DATA.companyLabel||'Company'}</div></div>
           </div>
         </div>
       <div className="sb-nav">
          <div className="sb-nav-label">Navigation</div>
          {nav.map(n=>(
            <button key={n.id} className={`sb-item${pg===n.id?' on':''}`} onClick={()=>{setPg(n.id);setSbOpen(false);}}>
              {n.icon}<span className="sb-label">{n.label}</span>
              {n.badge>0&&<span className="sb-pill">{n.badge}</span>}
            </button>
          ))}
        </div>
        <div className="sb-foot">
          <AccountMenu/>
         </div>
       </aside>
      <main className="main">
        <div className="topbar">
          <div className="tb-left">
            <button className="tb-menu" type="button" aria-label="Toggle navigation" aria-expanded={sbOpen?'true':'false'} onClick={()=>setSbOpen(v=>!v)}>
              <Ic.menu/>
            </button>
            <div className="tb-title">
              <div className="tb-pg">{pgTitle(pg)}</div>
              <div className="tb-crumb">ERMS &rsaquo; {pgTitle(pg)}</div>
            </div>
          </div>
          <div className="tb-r">
            <Clock/>
            <NotificationMenu alerts={Array.isArray(DATA.licenseAlerts)?DATA.licenseAlerts:[]} />
          </div>
        </div>
        <div className="content">
           {pg==='dashboard'&&(
             <Dashboard guards={guards} summary={DATA.summary} onAdd={()=>setAddOpen(true)} onGo={(st)=>{if(st)setFst(st);setPg('guards');}}/>
           )}
           {pg==='guards'&&(
             <GuardsList guards={guards} setGuards={setGuards} initSt={fst}/>
           )}
           {pg==='reports'&&isAdmin&&(
             <Reports/>
           )}
           {pg==='users'&&isAdmin&&(
             <UserManagement/>
           )}
        </div>
      </main>
    </div>
  );
}
ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
</script>
</body>
</html>
