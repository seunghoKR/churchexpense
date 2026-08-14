<?php
/**
 * 세종새누리교회 스마트 재정행정시스템 - 백엔드 API (PHP 8.4)
 * 테이블 접두사: z_ch_saenuri_
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$currentUser = $_SESSION['user'] ?? [
    'id' => 1,
    'name' => '김성도',
    'title_name' => '집사',
    'role' => 'APPLICANT'
];

if ($action === 'create_request') {
    handleCreateRequest($currentUser);
} elseif ($action === 'get_expense_requests') {
    handleGetExpenseRequests();
} elseif ($action === 'update_status' || $action === 'update_request_status') {
    handleUpdateStatus($currentUser);
} elseif ($action === 'update_mypage') {
    handleUpdateMyPage($currentUser);
} elseif ($action === 'register_pending_user') {
    handleRegisterPendingUser();
} elseif ($action === 'get_pending_users') {
    handleGetPendingUsers();
} elseif ($action === 'get_approved_users') {
    handleGetApprovedUsers();
} elseif ($action === 'check_user_status') {
    handleCheckUserStatus();
} elseif ($action === 'approve_user_api') {
    handleApproveUserApi();
} elseif ($action === 'change_user_role') {
    handleChangeUserRole();
} elseif ($action === 'add_department') {
    handleAddDepartment();
} elseif ($action === 'get_departments') {
    handleGetDepartments();
} elseif ($action === 'delete_department') {
    handleDeleteDepartment();
} elseif ($action === 'add_title') {
    handleAddTitle();
} elseif ($action === 'delete_title') {
    handleDeleteTitle();
} elseif ($action === 'auto_update_user') {
    handleAutoUpdateUser();
} elseif ($action === 'link_sns_account') {
    handleLinkSnsAccount();
} elseif ($action === 'get_social_links') {
    handleGetSocialLinks();
} elseif ($action === 'delete_user') {
    handleDeleteUser();
} else {
    header('Location: ../public/index.php');
    exit;
}

function handleUpdateMyPage(array $user) {
    header('Content-Type: application/json; charset=utf-8');
    $rawEmail = strtolower(trim($_POST['email'] ?? $user['email'] ?? ''));
    $origEmail = strtolower(trim($_POST['orig_email'] ?? $_POST['current_email'] ?? $user['email'] ?? ''));

    if (empty($rawEmail)) {
        echo json_encode(['status' => 'error', 'message' => '이메일이 유효하지 않습니다.']);
        exit;
    }

    $primaryEmail = resolvePrimaryEmail($rawEmail);

    // 사용자가 마이페이지에서 이메일을 변경한 경우, 기존 계정과의 듀얼 연동 & 병합 매핑!
    if (!empty($origEmail) && $origEmail !== $rawEmail) {
        $linkFile = __DIR__ . '/social_links.json';
        $links = file_exists($linkFile) ? (json_decode(file_get_contents($linkFile), true) ?? []) : [];
        $links[$origEmail] = [
            'primary_email' => $rawEmail,
            'provider' => 'social',
            'linked_at' => date('Y-m-d H:i:s')
        ];
        $links[$rawEmail] = [
            'primary_email' => $rawEmail,
            'provider' => 'primary',
            'linked_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $primaryEmail = $rawEmail;
    }

    $emailsToUpdate = getLinkedEmailsForUser($rawEmail);
    if (!empty($origEmail) && !in_array($origEmail, $emailsToUpdate)) {
        $emailsToUpdate[] = $origEmail;
    }
    $emailsToUpdate = array_values(array_unique(array_filter($emailsToUpdate)));

    // 기존 계정에 부여된 높은 권한 (TREASURER / ADMIN) 조회 및 계정 이동 보존
    $existingRole = 'APPLICANT';
    $pdo = getDbConnection();
    if ($pdo) {
        foreach ($emailsToUpdate as $eCheck) {
            try {
                $rStmt = $pdo->prepare("SELECT role FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
                $rStmt->execute([$eCheck]);
                $rRow = $rStmt->fetch();
                if ($rRow && !empty($rRow['role']) && $rRow['role'] !== 'APPLICANT') {
                    $existingRole = $rRow['role'];
                    break;
                }
            } catch (Exception $e) {}
        }
    }

    $logFile = __DIR__ . '/pending_users.json';
    $fileData = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?? []) : [];
    foreach ($fileData as $fUser) {
        $fEmail = strtolower($fUser['email'] ?? '');
        if (in_array($fEmail, $emailsToUpdate)) {
            if (!empty($fUser['role']) && $fUser['role'] !== 'APPLICANT') {
                $existingRole = $fUser['role'];
                break;
            }
        }
    }

    $name = trim($_POST['name'] ?? $user['name'] ?? '성도');
    $titleName = trim($_POST['title_name'] ?? '성도');
    $deptName = trim($_POST['department'] ?? '청년부');
    $bank = trim($_POST['default_bank'] ?? '');
    $account = trim($_POST['default_account'] ?? '');
    $holder = trim($_POST['default_holder'] ?? '');
    $telegramId = trim($_POST['telegram_id'] ?? '');
    $mode = $_POST['preferred_mode'] ?? 'onepage';
    $theme = $_POST['preferred_theme'] ?? 'green';

    $role = !empty($existingRole) ? $existingRole : (strtolower($rawEmail) === 'leeshkr@gmail.com' ? 'ADMIN' : 'APPLICANT');

    foreach ($emailsToUpdate as $email) {
        if (!empty($email)) {
            $status = 'APPROVED';
            $eRole = $role;

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO z_ch_saenuri_users (email, name, title_name, department, default_bank, default_account, default_holder, preferred_mode, preferred_theme, role, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        name=VALUES(name), title_name=VALUES(title_name), department=VALUES(department), 
                        default_bank=VALUES(default_bank), default_account=VALUES(default_account), default_holder=VALUES(default_holder),
                        preferred_mode=VALUES(preferred_mode), preferred_theme=VALUES(preferred_theme), role=VALUES(role), status=VALUES(status)
                    ");
                    $stmt->execute([$email, $name, $titleName, $deptName, $bank, $account, $holder, $mode, $theme, $eRole, $status]);
                } catch (Exception $e) {}
            }
        }
    }

    // pending_users.json 백업에서 이전 origEmail 엔트리를 rawEmail 단일 항목으로 병합 (중복 분리 100% 제거)
    $newFileData = [];
    $rawEmailHandled = false;

    foreach ($fileData as $fUser) {
        $fEmail = strtolower($fUser['email'] ?? '');
        $fPrimary = resolvePrimaryEmail($fEmail);

        if (in_array($fEmail, $emailsToUpdate) || $fPrimary === $primaryEmail) {
            if (!$rawEmailHandled) {
                $fUser['email'] = $rawEmail;
                $fUser['name'] = $name;
                $fUser['title_name'] = $titleName;
                $fUser['department'] = $deptName;
                $fUser['role'] = $role;
                if (!empty($telegramId)) $fUser['telegram_id'] = $telegramId;
                $fUser['status'] = 'APPROVED';
                $newFileData[] = $fUser;
                $rawEmailHandled = true;
            }
        } else {
            $newFileData[] = $fUser;
        }
    }

    if (!$rawEmailHandled) {
        $newFileData[] = [
            'id' => time(),
            'email' => $rawEmail,
            'name' => $name,
            'title_name' => $titleName,
            'department' => $deptName,
            'telegram_id' => $telegramId,
            'role' => $role,
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }

    file_put_contents($logFile, json_encode($newFileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
        echo json_encode(['status' => 'success', 'message' => '프로필이 성공적으로 저장되었습니다.', 'name' => $name, 'role' => $role, 'email' => $rawEmail]);
        exit;
    }

    header('Location: ../public/index.php?tab=mypage&msg=saved');
    exit;
}

function handleCreateRequest(array $user) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    
    $applicantEmail = strtolower(trim($_POST['applicant_email'] ?? $_POST['email'] ?? $user['email'] ?? ''));
    $applicantName = trim($_POST['applicant_name'] ?? $_POST['name'] ?? $user['name'] ?? '성도');
    $department = trim($_POST['department'] ?? '청년부');
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? '일반지출';
    $purpose = trim($_POST['purpose'] ?? '비용 지출');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountHolder = trim($_POST['account_holder'] ?? '');
    $signatureData = $_POST['signature_data'] ?? '';
    $totalAmount = (float)($_POST['total_amount'] ?? 0);
    $requestDate = date('Y-m-d');

    // 🏷️ 일련번호 생성 (YYMMDDNNN 방식: 예 260813001)
    $datePrefix = date('ymd');
    $seq = 1;

    $logFile = __DIR__ . '/expense_requests.json';
    $fileData = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?? []) : [];
    if (!is_array($fileData)) $fileData = [];

    foreach ($fileData as $req) {
        if (strpos($req['doc_no'] ?? '', $datePrefix) === 0) {
            $seq++;
        }
    }
    $docNo = $datePrefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    // 항목 데이터 처리
    $rawItems = $_POST['items'] ?? [];
    $items = [];
    if (is_string($rawItems)) {
        $items = json_decode($rawItems, true) ?? [];
    } elseif (is_array($rawItems)) {
        $items = $rawItems;
    }

    $requestId = time() . rand(100, 999);

    if ($pdo) {
        try {
            $uStmt = $pdo->prepare("SELECT id FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $uStmt->execute([$applicantEmail]);
            $uRow = $uStmt->fetch();
            $userId = $uRow['id'] ?? 1;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_expense_requests (
                    doc_no, user_id, applicant_name, department, request_date, expense_date, 
                    category, purpose, total_amount, bank_name, account_number, account_holder, signature_data, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ");
            $stmt->execute([
                $docNo, $userId, $applicantName, $department, $requestDate, $expenseDate,
                $category, $purpose, $totalAmount, $bankName, $accountNumber, $accountHolder, $signatureData
            ]);
            $dbReqId = $pdo->lastInsertId();
            if ($dbReqId) $requestId = $dbReqId;

            $itemStmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_expense_items (request_id, item_order, item_name, amount, note)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($items as $idx => $item) {
                $iName = trim($item['item_name'] ?? $item['name'] ?? '');
                $iAmt = (float)($item['amount'] ?? 0);
                $iNote = trim($item['note'] ?? '');
                if (!empty($iName)) {
                    $itemStmt->execute([$requestId, $idx + 1, $iName, $iAmt, $iNote]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    // JSON 백업 파일 저장
    $newRecord = [
        'id' => $requestId,
        'doc_no' => $docNo,
        'applicant_email' => $applicantEmail,
        'applicant_name' => $applicantName,
        'department' => $department,
        'request_date' => $requestDate,
        'expense_date' => $expenseDate,
        'category' => $category,
        'purpose' => $purpose,
        'total_amount' => $totalAmount,
        'bank_name' => $bankName,
        'account_number' => $accountNumber,
        'account_holder' => $accountHolder,
        'signature_data' => $signatureData,
        'status' => 'PENDING',
        'items' => $items,
        'created_at' => date('Y-m-d H:i:s')
    ];
    array_unshift($fileData, $newRecord);
    file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
        echo json_encode([
            'status' => 'success',
            'message' => '지출요청서가 정상 제출되었습니다.',
            'doc_no' => $docNo,
            'request_id' => $requestId
        ]);
        exit;
    }

    header('Location: ../public/index.php?msg=success');
    exit;
}

function handleGetExpenseRequests() {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    $requests = [];

    $logFile = __DIR__ . '/expense_requests.json';
    if (file_exists($logFile)) {
        $requests = json_decode(file_get_contents($logFile), true) ?? [];
    }

    if ($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT r.*, u.email as applicant_email 
                FROM z_ch_saenuri_expense_requests r
                LEFT JOIN z_ch_saenuri_users u ON r.user_id = u.id
                ORDER BY r.id DESC
            ");
            $dbRows = $stmt->fetchAll();
            if (!empty($dbRows)) {
                $dbRequests = [];
                foreach ($dbRows as $row) {
                    $iStmt = $pdo->prepare("SELECT item_name as name, amount, note FROM z_ch_saenuri_expense_items WHERE request_id = ? ORDER BY item_order ASC");
                    $iStmt->execute([$row['id']]);
                    $items = $iStmt->fetchAll();

                    $dbRequests[] = [
                        'id' => $row['id'],
                        'doc_no' => $row['doc_no'],
                        'applicant_email' => strtolower($row['applicant_email'] ?? ''),
                        'applicant_name' => $row['applicant_name'],
                        'department' => $row['department'],
                        'request_date' => $row['request_date'],
                        'expense_date' => $row['expense_date'],
                        'category' => $row['category'],
                        'purpose' => $row['purpose'],
                        'total_amount' => (float)$row['total_amount'],
                        'bank_name' => $row['bank_name'],
                        'account_number' => $row['account_number'],
                        'account_holder' => $row['account_holder'],
                        'status' => $row['status'],
                        'reject_reason' => $row['reject_reason'] ?? '',
                        'items' => $items,
                        'created_at' => $row['created_at'] ?? $row['request_date']
                    ];
                }
                $docNos = array_column($dbRequests, 'doc_no');
                foreach ($requests as $r) {
                    if (!in_array($r['doc_no'] ?? '', $docNos)) {
                        $dbRequests[] = $r;
                    }
                }
                $requests = $dbRequests;
            }
        } catch (Exception $e) {}
    }

    echo json_encode(['status' => 'success', 'data' => $requests]);
    exit;
}

function handleUpdateStatus(array $user) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    $docNo = $_POST['doc_no'] ?? $_POST['request_id'] ?? '';
    $status = $_POST['status'] ?? 'APPROVED';
    $rejectReason = trim($_POST['reject_reason'] ?? '');

    if ($pdo && !empty($docNo)) {
        try {
            $stmt = $pdo->prepare("UPDATE z_ch_saenuri_expense_requests SET status = ?, reject_reason = ? WHERE doc_no = ? OR id = ?");
            $stmt->execute([$status, $rejectReason, $docNo, $docNo]);
        } catch (Exception $e) {}
    }

    $logFile = __DIR__ . '/expense_requests.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as &$req) {
            if (($req['doc_no'] ?? '') === $docNo || (string)($req['id'] ?? '') === (string)$docNo) {
                $req['status'] = $status;
                if (!empty($rejectReason)) $req['reject_reason'] = $rejectReason;
                break;
            }
        }
        file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
        echo json_encode(['status' => 'success', 'message' => '지출요청 상태가 변경되었습니다.']);
        exit;
    }

    header('Location: ../public/index.php?tab=admin&msg=updated');
    exit;
}

function handleAutoUpdateUser() {
    $userId = (int)($_POST['user_id'] ?? 0);
    $titleName = trim($_POST['title_name'] ?? '성도');
    $deptName = trim($_POST['department'] ?? '청년부');
    $role = $_POST['role'] ?? 'APPLICANT';

    $pdo = getDbConnection();
    if ($pdo && $userId > 0) {
        $stmt = $pdo->prepare("UPDATE z_ch_saenuri_users SET title_name = ?, department = ?, role = ? WHERE id = ?");
        $stmt->execute([$titleName, $deptName, $role, $userId]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

function handleAddDepartment() {
    $deptName = trim($_POST['dept_name'] ?? '');
    if (!empty($deptName)) {
        $pdo = getDbConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO z_ch_saenuri_departments (dept_name) VALUES (?)");
            $stmt->execute([$deptName]);
        }
    }
    header('Location: ../public/index.php?tab=site_admin');
    exit;
}

function handleDeleteDepartment() {
    $deptId = (int)($_POST['dept_id'] ?? 0);
    $pdo = getDbConnection();
    if ($pdo && $deptId > 0) {
        $stmt = $pdo->prepare("DELETE FROM z_ch_saenuri_departments WHERE id = ?");
        $stmt->execute([$deptId]);
    }
    header('Location: ../public/index.php?tab=site_admin');
    exit;
}

function handleAddTitle() {
    $titleName = trim($_POST['title_name'] ?? '');
    if (!empty($titleName)) {
        $pdo = getDbConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO z_ch_saenuri_church_titles (title_name) VALUES (?)");
            $stmt->execute([$titleName]);
        }
    }
    header('Location: ../public/index.php?tab=site_admin');
    exit;
}

function handleDeleteTitle() {
    $titleId = (int)($_POST['title_id'] ?? 0);
    $pdo = getDbConnection();
    if ($pdo && $titleId > 0) {
        $stmt = $pdo->prepare("DELETE FROM z_ch_saenuri_church_titles WHERE id = ?");
        $stmt->execute([$titleId]);
    }
    header('Location: ../public/index.php?tab=site_admin');
    exit;
}

// 👑 회원 승인대기 등록 API
function handleRegisterPendingUser() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
    $name = trim($_POST['name'] ?? $_GET['name'] ?? '신규 성도');
    $dept = trim($_POST['department'] ?? '청년부');
    $titleName = trim($_POST['title_name'] ?? '성도');

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일이 누락되었습니다.']);
        exit;
    }

    $adminEmails = ['leeshkr@gmail.com'];
    $isAdmin = in_array($email, array_map('strtolower', $adminEmails));
    $status = $isAdmin ? 'APPROVED' : 'PENDING';
    $role = $isAdmin ? 'ADMIN' : 'APPLICANT';

    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                $upStmt = $pdo->prepare("UPDATE z_ch_saenuri_users SET name = ?, department = ?, title_name = ? WHERE LOWER(email) = ?");
                $upStmt->execute([$name, $dept, $titleName, $email]);
                $status = $existing['status'];
            } else {
                $inStmt = $pdo->prepare("INSERT INTO z_ch_saenuri_users (email, name, role, status, department, title_name) VALUES (?, ?, ?, ?, ?, ?)");
                $inStmt->execute([$email, $name, $role, $status, $dept, $titleName]);
            }
        } catch (Exception $e) {
            // DB 오류 시에도 정상 처리 유도
        }
    }

    // 파일 기반 데이터 저장 동기화 (DB 미연결 대비 보조 백업)
    $logFile = __DIR__ . '/pending_users.json';
    $pendingData = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    if (!is_array($pendingData)) $pendingData = [];

    $found = false;
    foreach ($pendingData as &$u) {
        if (strtolower($u['email']) === $email) {
            $u['name'] = $name;
            $u['department'] = $dept;
            $u['title_name'] = $titleName;
            $found = true;
            break;
        }
    }
    if (!$found && !$isAdmin) {
        $pendingData[] = [
            'id' => time(),
            'email' => $email,
            'name' => $name,
            'department' => $dept,
            'title_name' => $titleName,
            'status' => 'PENDING',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    file_put_contents($logFile, json_encode($pendingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo json_encode(['status' => 'success', 'user_status' => $status, 'message' => '승인 대기 상태 저장 완료']);
    exit;
}

// 👑 사이트 관리자 전용 - 모든 승인대기 회원 목록 조회 API
function handleGetPendingUsers() {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    $pendingList = [];

    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT id, email, name, department, title_name, status, created_at FROM z_ch_saenuri_users WHERE status = 'PENDING' ORDER BY id DESC");
            $pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $pendingList = [];
        }
    }

    // 파일 백업 데이터와 병합
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            if (($fUser['status'] ?? 'PENDING') === 'PENDING') {
                $alreadyInList = false;
                foreach ($pendingList as $pUser) {
                    if (strtolower($pUser['email']) === strtolower($fUser['email'])) {
                        $alreadyInList = true;
                        break;
                    }
                }
                if (!$alreadyInList) {
                    $pendingList[] = $fUser;
                }
            }
        }
    }

    // 삭제된 회원 필터링
    $deletedFile = __DIR__ . '/deleted_users.json';
    $deletedList = file_exists($deletedFile) ? (json_decode(file_get_contents($deletedFile), true) ?? []) : [];
    $deletedList = array_map('strtolower', (array)$deletedList);

    $pendingList = array_values(array_filter($pendingList, function($u) use ($deletedList) {
        return !in_array(strtolower($u['email'] ?? ''), $deletedList);
    }));

    echo json_encode(['status' => 'success', 'data' => $pendingList]);
    exit;
}

// 👑 회원 승인 처리 API
function handleApproveUserApi() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 누락되었습니다.']);
        exit;
    }

    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE z_ch_saenuri_users SET status = 'APPROVED', role = 'APPLICANT' WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
        } catch (Exception $e) {}
    }

    // 파일 백업 업데이트 (PENDING -> APPROVED)
    $logFile = __DIR__ . '/pending_users.json';
    $fileData = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?? []) : [];
    
    $found = false;
    foreach ($fileData as &$fUser) {
        if (strtolower($fUser['email'] ?? '') === $email) {
            $fUser['status'] = 'APPROVED';
            $found = true;
            break;
        }
    }
    if (!$found) {
        $fileData[] = [
            'id' => time(),
            'email' => $email,
            'name' => '성도님',
            'department' => '청년부',
            'title_name' => '성도',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    file_put_contents($logFile, json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo json_encode(['status' => 'success', 'message' => $email . ' 계정 승인이 완료되었습니다.']);
    exit;
}

function resolvePrimaryEmail($email) {
    $email = strtolower(trim($email));
    $linkFile = __DIR__ . '/social_links.json';
    if (file_exists($linkFile)) {
        $links = json_decode(file_get_contents($linkFile), true) ?? [];
        $visited = [];
        $current = $email;
        while (!empty($links[$current]['primary_email']) && !in_array($current, $visited)) {
            $visited[] = $current;
            $current = strtolower($links[$current]['primary_email']);
        }
        return $current;
    }
    return $email;
}

function getLinkedEmailsForUser($email) {
    $primary = resolvePrimaryEmail($email);
    $linkFile = __DIR__ . '/social_links.json';
    $linked = [$primary, strtolower(trim($email))];
    if (file_exists($linkFile)) {
        $links = json_decode(file_get_contents($linkFile), true) ?? [];
        foreach ($links as $secEmail => $data) {
            $p = resolvePrimaryEmail($secEmail);
            if ($p === $primary || strtolower($secEmail) === $primary) {
                $linked[] = strtolower($secEmail);
                $linked[] = $p;
            }
        }
    }
    return array_values(array_unique(array_filter($linked)));
}

// 🌐 접속 유저의 서버 실시간 승인 상태 및 마이페이지 프로필 동기화 검증 API
function handleCheckUserStatus() {
    header('Content-Type: application/json; charset=utf-8');
    $rawEmail = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
    $email = resolvePrimaryEmail($rawEmail);

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'user_status' => 'PENDING']);
        exit;
    }

    $isDevAdmin = (strtolower($email) === 'leeshkr@gmail.com');
    $userStatus = $isDevAdmin ? 'APPROVED' : 'PENDING';
    $role = $isDevAdmin ? 'ADMIN' : 'APPLICANT';
    $name = '';
    $titleName = '';
    $dept = '';

    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT name, title_name, department, status, role FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                if (!empty($u['status'])) $userStatus = $u['status'];
                if (!empty($u['role'])) $role = $u['role'];
                if (!empty($u['name'])) $name = $u['name'];
                if (!empty($u['title_name'])) $titleName = $u['title_name'];
                if (!empty($u['department'])) $dept = $u['department'];
            }
        } catch (Exception $e) {}
    }

    // 파일 백업 검사
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            if (strtolower($fUser['email'] ?? '') === $email) {
                if (($fUser['status'] ?? '') === 'APPROVED') $userStatus = 'APPROVED';
                if (!empty($fUser['role'])) $role = $fUser['role'];
                if (empty($name) && !empty($fUser['name'])) $name = $fUser['name'];
                if (empty($titleName) && !empty($fUser['title_name'])) $titleName = $fUser['title_name'];
                if (empty($dept) && !empty($fUser['department'])) $dept = $fUser['department'];
                break;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'user_status' => $userStatus,
        'role' => $role,
        'name' => $name,
        'title_name' => $titleName,
        'department' => $dept
    ]);
    exit;
}

// 👑 사이트 관리자 전용 - 모든 교인 회원 및 관리자 목록 조회 API (관리자 포함 전체 노출!)
function handleGetApprovedUsers() {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    $approvedList = [];

    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT id, email, name, department, title_name, role, status, created_at FROM z_ch_saenuri_users ORDER BY id DESC");
            $approvedList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $approvedList = [];
        }
    }

    // 파일 백업 데이터와 병합 및 저장된 프로필 정보 100% 실시간 덮어쓰기!
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            $fEmail = strtolower($fUser['email'] ?? '');
            if (!empty($fEmail)) {
                $alreadyInList = false;
                foreach ($approvedList as &$pUser) {
                    if (strtolower($pUser['email'] ?? '') === $fEmail) {
                        $alreadyInList = true;
                        if (!empty($fUser['name'])) $pUser['name'] = $fUser['name'];
                        if (!empty($fUser['title_name'])) $pUser['title_name'] = $fUser['title_name'];
                        if (!empty($fUser['department'])) $pUser['department'] = $fUser['department'];
                        if (!empty($fUser['role'])) $pUser['role'] = $fUser['role'];
                        break;
                    }
                }
                if (!$alreadyInList) {
                    $approvedList[] = [
                        'id' => $fUser['id'] ?? time(),
                        'email' => $fUser['email'],
                        'name' => $fUser['name'] ?? '성도님',
                        'department' => $fUser['department'] ?? '행정/재정부',
                        'title_name' => $fUser['title_name'] ?? '성도',
                        'role' => $fUser['role'] ?? ($fEmail === 'leeshkr@gmail.com' ? 'ADMIN' : 'APPLICANT'),
                        'status' => $fUser['status'] ?? 'APPROVED',
                        'created_at' => $fUser['created_at'] ?? date('Y-m-d H:i')
                    ];
                }
            }
        }
    }

    // 삭제된 회원 목록 불러오기
    $deletedFile = __DIR__ . '/deleted_users.json';
    $deletedList = file_exists($deletedFile) ? (json_decode(file_get_contents($deletedFile), true) ?? []) : [];
    $deletedList = array_map('strtolower', (array)$deletedList);

    // 삭제된 회원 필터링
    $approvedList = array_values(array_filter($approvedList, function($u) use ($deletedList) {
        return !in_array(strtolower($u['email'] ?? ''), $deletedList);
    }));

    // 기본 계정들 기본 승인 상태 보장
    foreach ($approvedList as &$userItem) {
        $uEmail = strtolower($userItem['email'] ?? '');
        if ($uEmail === 'leeshkr@gmail.com' && empty($userItem['role'])) {
            $userItem['role'] = 'ADMIN';
        }
        if (in_array($uEmail, ['leeshkr@gmail.com', 'ktbmks@hanmail.net']) && empty($userItem['status'])) {
            $userItem['status'] = 'APPROVED';
        }
    }

    // 기본 관리자 계정들이 아예 비어있고 삭제되지 않았을 경우 고정 기본값 포함!
    $existingEmails = array_map(function($u) { return strtolower($u['email'] ?? ''); }, $approvedList);
    if (!in_array('ktbmks@hanmail.net', $existingEmails) && !in_array('ktbmks@hanmail.net', $deletedList)) {
        $approvedList[] = [
            'id' => 9991,
            'email' => 'ktbmks@hanmail.net',
            'name' => '김태봉',
            'department' => '교육-청년',
            'title_name' => '목사',
            'role' => 'ADMIN',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    if (!in_array('leeshkr@gmail.com', $existingEmails) && !in_array('leeshkr@gmail.com', $deletedList)) {
        $approvedList[] = [
            'id' => 9992,
            'email' => 'leeshkr@gmail.com',
            'name' => '이승호 개발자',
            'department' => '교육-청년',
            'title_name' => '집사',
            'role' => 'ADMIN',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    if (!in_array('nuriohga@gmail.com', $existingEmails) && !in_array('nuriohga@gmail.com', $deletedList)) {
        $approvedList[] = [
            'id' => 9993,
            'email' => 'nuriohga@gmail.com',
            'name' => '누리오(NURIOH)',
            'department' => '교육-청소년',
            'title_name' => '성도',
            'role' => 'APPLICANT',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }

    // 🔗 소셜 듀얼 계정 병합 및 중복 회원 1인 단일화 (Primary Email 기준)
    $mergedMap = [];
    foreach ($approvedList as $uItem) {
        $rEmail = strtolower(trim($uItem['email'] ?? ''));
        if (empty($rEmail)) continue;

        $pEmail = resolvePrimaryEmail($rEmail);
        
        if (!isset($mergedMap[$pEmail])) {
            $uItem['email'] = $pEmail;
            $mergedMap[$pEmail] = $uItem;
        } else {
            $existing = &$mergedMap[$pEmail];
            // 더 높은 권한 (ADMIN > TREASURER > APPLICANT) 승계
            $rolePriority = ['ADMIN' => 3, 'TREASURER' => 2, 'APPLICANT' => 1];
            $currP = $rolePriority[$existing['role'] ?? 'APPLICANT'] ?? 1;
            $newP = $rolePriority[$uItem['role'] ?? 'APPLICANT'] ?? 1;
            if ($newP > $currP) {
                $existing['role'] = $uItem['role'];
            }
            if (!empty($uItem['name']) && $uItem['name'] !== '성도님' && $uItem['name'] !== '카카오 성도') {
                $existing['name'] = $uItem['name'];
            }
        }
    }
    $approvedList = array_values($mergedMap);

    echo json_encode(['status' => 'success', 'data' => $approvedList]);
    exit;
}

// 👑 회원 완전히 삭제 처리 API
function handleDeleteUser() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 누락되었습니다.']);
        exit;
    }

    // 1. DB에서 회원 삭제
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
        } catch (Exception $e) {}
    }

    // 2. pending_users.json에서 회원 삭제
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        $filtered = array_values(array_filter($fileData, function($u) use ($email) {
            return strtolower($u['email'] ?? '') !== $email;
        }));
        file_put_contents($logFile, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 3. social_links.json에서 연동 정보 삭제
    $socialFile = __DIR__ . '/social_links.json';
    if (file_exists($socialFile)) {
        $socialData = json_decode(file_get_contents($socialFile), true) ?? [];
        $socialFiltered = array_values(array_filter($socialData, function($s) use ($email) {
            return strtolower($s['primary_email'] ?? '') !== $email && strtolower($s['secondary_email'] ?? '') !== $email;
        }));
        file_put_contents($socialFile, json_encode($socialFiltered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 4. deleted_users.json에 기록하여 재복구 방지
    $deletedFile = __DIR__ . '/deleted_users.json';
    $deletedList = file_exists($deletedFile) ? (json_decode(file_get_contents($deletedFile), true) ?? []) : [];
    if (!is_array($deletedList)) $deletedList = [];
    if (!in_array($email, $deletedList)) {
        $deletedList[] = $email;
        file_put_contents($deletedFile, json_encode($deletedList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode(['status' => 'success', 'message' => '회원 계정이 완전히 삭제되었습니다.']);
    exit;
}

// 👑 사이트 관리자 전용 - 교인 회원 권한 변경 API (신청자 / 재정부 / 사이트 관리자)
function handleChangeUserRole() {
    header('Content-Type: application/json; charset=utf-8');
    $rawEmail = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
    $newRole = trim($_POST['role'] ?? $_GET['role'] ?? 'APPLICANT');

    if (empty($rawEmail)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 누락되었습니다.']);
        exit;
    }

    $primaryEmail = resolvePrimaryEmail($rawEmail);
    $emailsToUpdate = getLinkedEmailsForUser($rawEmail);

    $pdo = getDbConnection();
    foreach ($emailsToUpdate as $email) {
        if ($pdo && !empty($email)) {
            try {
                $stmt = $pdo->prepare("UPDATE z_ch_saenuri_users SET role = ? WHERE LOWER(email) = ?");
                $stmt->execute([$newRole, $email]);
            } catch (Exception $e) {}
        }
    }

    // 파일 백업 업데이트 (연동 이메일 및 resolvePrimaryEmail 매핑 유저 전체 권한 변경!)
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as &$fUser) {
            $fEmail = strtolower($fUser['email'] ?? '');
            $fPrimary = resolvePrimaryEmail($fEmail);
            if (in_array($fEmail, $emailsToUpdate) || $fPrimary === $primaryEmail) {
                $fUser['role'] = $newRole;
            }
        }
        file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode([
        'status' => 'success',
        'message' => '회원 권한이 정상 변경되었습니다.',
        'role' => $newRole,
        'email' => $rawEmail,
        'primary_email' => $primaryEmail,
        'linked_emails' => $emailsToUpdate
    ]);
    exit;
}

// 🔗 SNS 소셜 로그인 듀얼 계정 병합 연동 API
function handleLinkSnsAccount() {
    header('Content-Type: application/json; charset=utf-8');
    $primaryEmail = strtolower(trim($_POST['primary_email'] ?? $_GET['primary_email'] ?? ''));
    $secondaryEmail = strtolower(trim($_POST['secondary_email'] ?? $_GET['secondary_email'] ?? ''));
    $provider = trim($_POST['provider'] ?? $_GET['provider'] ?? '');

    if (empty($primaryEmail) || empty($secondaryEmail)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 부족합니다.']);
        exit;
    }

    $file = __DIR__ . '/social_links.json';
    $links = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $links[$secondaryEmail] = [
        'primary_email' => $primaryEmail,
        'provider' => $provider,
        'linked_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($file, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // DB 상에서도 secondary_email 계정이 있다면 primary 계정과 동기화
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT role, status, name, title_name, department, default_bank, default_account, default_holder FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$primaryEmail]);
            $pUser = $stmt->fetch();
            if ($pUser) {
                $upStmt = $pdo->prepare("INSERT INTO z_ch_saenuri_users (email, name, title_name, department, default_bank, default_account, default_holder, role, status, oauth_provider, oauth_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), title_name=VALUES(title_name), department=VALUES(department), default_bank=VALUES(default_bank), default_account=VALUES(default_account), default_holder=VALUES(default_holder), role=VALUES(role), status=VALUES(status)");
                $upStmt->execute([$secondaryEmail, $pUser['name'], $pUser['title_name'], $pUser['department'], $pUser['default_bank'], $pUser['default_account'], $pUser['default_holder'], $pUser['role'], $pUser['status'], $provider, $secondaryEmail]);
            }
        } catch (Exception $e) {}
    }

    echo json_encode(['status' => 'success', 'primary_email' => $primaryEmail, 'secondary_email' => $secondaryEmail]);
    exit;
}

// 🔗 SNS 듀얼 계정 연동 현황 조회 API
function handleGetSocialLinks() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
    $primary = resolvePrimaryEmail($email);
    $linkedEmails = getLinkedEmailsForUser($email);

    $file = __DIR__ . '/social_links.json';
    $links = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];

    $googleLinked = false;
    $kakaoLinked = false;
    $googleEmail = '';
    $kakaoEmail = '';

    foreach ($linkedEmails as $e) {
        $p = $links[$e]['provider'] ?? '';
        if (strpos($e, '@gmail.com') !== false || $p === 'google') {
            $googleLinked = true;
            if (empty($googleEmail) || ($googleEmail === $primary && $e !== $primary)) {
                $googleEmail = $e;
            }
        }
        if (strpos($e, '@kakao') !== false || strpos($e, 'kakao_') === 0 || $p === 'kakao') {
            $kakaoLinked = true;
            if (empty($kakaoEmail) || strpos($kakaoEmail, 'kakao_') === 0 || ($kakaoEmail === $primary && $e !== $primary)) {
                $kakaoEmail = $e;
            }
        }
    }

    if (empty($googleEmail) && (strpos($primary, '@gmail.com') !== false || ($links[$primary]['provider'] ?? '') === 'google')) {
        $googleLinked = true;
        $googleEmail = $primary;
    }
    if (empty($kakaoEmail) && (strpos($primary, '@kakao') !== false || strpos($primary, 'kakao_') === 0 || ($links[$primary]['provider'] ?? '') === 'kakao')) {
        $kakaoLinked = true;
        $kakaoEmail = $primary;
    }

    echo json_encode([
        'status' => 'success',
        'primary_email' => $primary,
        'google_linked' => $googleLinked,
        'kakao_linked' => $kakaoLinked,
        'google_email' => $googleEmail,
        'kakao_email' => $kakaoEmail,
        'linked_emails' => $linkedEmails
    ]);
    exit;
}

