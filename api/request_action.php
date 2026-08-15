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
    $existingRole = '';
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

    $isMasterAdmin = (strtolower($rawEmail) === 'leeshkr@gmail.com');
    $role = $isMasterAdmin ? 'ADMIN' : (!empty($existingRole) ? $existingRole : 'APPLICANT');

    // 1. 대표 이메일(rawEmail) 단일 레코드만 DB에 등록/갱신
    if ($pdo) {
        try {
            $isEmailAdmin = (strtolower($rawEmail) === 'leeshkr@gmail.com');
            $status = 'APPROVED';
            $eRole = $isEmailAdmin ? 'ADMIN' : $role;

            $stmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_users (email, name, title_name, department, default_bank, default_account, default_holder, preferred_mode, preferred_theme, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                name=VALUES(name), title_name=VALUES(title_name), department=VALUES(department), 
                default_bank=VALUES(default_bank), default_account=VALUES(default_account), default_holder=VALUES(default_holder),
                preferred_mode=VALUES(preferred_mode), preferred_theme=VALUES(preferred_theme), role=VALUES(role), status=VALUES(status)
            ");
            $stmt->execute([$rawEmail, $name, $titleName, $deptName, $bank, $account, $holder, $mode, $theme, $eRole, $status]);

            // 이전 연동 이메일이 DB에 별도 행으로 분리되어 있었다면 대표 이메일로 통합하고 이전 행 삭제 (중복 100% 제거)
            if (!empty($origEmail) && $origEmail !== $rawEmail && $origEmail !== 'leeshkr@gmail.com') {
                $delPrev = $pdo->prepare("DELETE FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
                $delPrev->execute([$origEmail]);
            }
        } catch (Exception $e) {}
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
                $fUser['default_bank'] = $bank;
                $fUser['default_account'] = $account;
                $fUser['default_holder'] = $holder;
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
            'default_bank' => $bank,
            'default_account' => $account,
            'default_holder' => $holder,
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
    $accountHolder = trim($_POST['account_holder'] ?? '');
    $applicantName = trim($_POST['applicant_name'] ?? $_POST['name'] ?? $user['name'] ?? '');
    if (empty($applicantName) || $applicantName === '성도') {
        if (!empty($accountHolder)) {
            $applicantName = $accountHolder;
        } elseif (!empty($user['name'])) {
            $applicantName = $user['name'];
        } else {
            $applicantName = '성도';
        }
    }
    $department = trim($_POST['department'] ?? '청년부');
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? '일반지출';
    $purpose = trim($_POST['purpose'] ?? '비용 지출');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
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

    // 🖼️ 영수증 / 증빙 사진 파일 및 Base64 이미지 처리
    $receiptUrls = [];
    $uploadDir = __DIR__ . '/../uploads/receipts/';
    $publicUploadDir = __DIR__ . '/../public/uploads/receipts/';
    if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
    if (!file_exists($publicUploadDir)) @mkdir($publicUploadDir, 0777, true);

    // 1. multipart/form-data 파일 업로드 처리
    if (!empty($_FILES['receipt_files']['name'])) {
        $names = (array)$_FILES['receipt_files']['name'];
        $tmpNames = (array)$_FILES['receipt_files']['tmp_name'];
        foreach ($names as $idx => $fName) {
            if (!empty($tmpNames[$idx]) && is_uploaded_file($tmpNames[$idx])) {
                $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                if (empty($ext) || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
                $saveName = 'rcpt_' . $docNo . '_' . ($idx + 1) . '_' . time() . '.' . $ext;
                $targetFile1 = $uploadDir . $saveName;
                $targetFile2 = $publicUploadDir . $saveName;
                if (move_uploaded_file($tmpNames[$idx], $targetFile1)) {
                    @copy($targetFile1, $targetFile2);
                    $receiptUrls[] = 'uploads/receipts/' . $saveName;
                }
            }
        }
    }

    // 2. Base64 data URL 처리 (스마트폰 바로 촬영 / 이미지 첨부 fallback)
    $rawReceiptImages = $_POST['receipt_images'] ?? '';
    if (empty($receiptUrls) && !empty($rawReceiptImages)) {
        $dataUrls = is_array($rawReceiptImages) ? $rawReceiptImages : (json_decode($rawReceiptImages, true) ?? []);
        foreach ($dataUrls as $idx => $dUrl) {
            if (strpos($dUrl, 'data:image/') === 0) {
                $parts = explode(',', $dUrl);
                if (count($parts) === 2) {
                    $imgData = base64_decode($parts[1]);
                    if ($imgData !== false) {
                        $saveName = 'rcpt_' . $docNo . '_' . ($idx + 1) . '_' . time() . '.png';
                        $targetFile1 = $uploadDir . $saveName;
                        $targetFile2 = $publicUploadDir . $saveName;
                        if (file_put_contents($targetFile1, $imgData)) {
                            @file_put_contents($targetFile2, $imgData);
                            $receiptUrls[] = 'uploads/receipts/' . $saveName;
                        }
                    }
                }
            }
        }
    }

    $receiptUrl = !empty($receiptUrls) ? $receiptUrls[0] : '';

    // 💾 DB z_ch_saenuri_expense_receipts 테이블에 영수증 파일 경로 등록
    if ($pdo && !empty($receiptUrls) && $requestId) {
        try {
            $rStmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_expense_receipts (request_id, original_name, file_path, file_size)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($receiptUrls as $idx => $rPath) {
                $origName = isset($names[$idx]) ? $names[$idx] : ('receipt_' . ($idx + 1) . '.jpg');
                $rStmt->execute([$requestId, $origName, $rPath, 0]);
            }
        } catch (Exception $e) {}
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
        'receipt_url' => $receiptUrl,
        'receipt_urls' => $receiptUrls,
        'status' => 'PENDING',
        'items' => $items,
        'created_at' => date('Y-m-d H:i:s')
    ];
    array_unshift($fileData, $newRecord);
    file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 💬 카카오톡 알림 발송 (신규 지출요청 등록)
    notifyExpenseEvent('NEW_REQUEST', $newRecord);

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

    $jsonMap = [];
    foreach ($requests as $jReq) {
        if (!empty($jReq['doc_no'])) {
            $jsonMap[$jReq['doc_no']] = $jReq;
        }
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

                    // 🖼️ 영수증 목록 DB 및 JSON 동시 조회
                    $rcptStmt = $pdo->prepare("SELECT file_path FROM z_ch_saenuri_expense_receipts WHERE request_id = ? ORDER BY id ASC");
                    $rcptStmt->execute([$row['id']]);
                    $dbReceipts = $rcptStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    $jMatch = $jsonMap[$row['doc_no']] ?? null;
                    $rUrls = !empty($dbReceipts) ? $dbReceipts : (!empty($jMatch['receipt_urls']) ? $jMatch['receipt_urls'] : []);
                    $rUrl = !empty($rUrls) ? $rUrls[0] : ($row['receipt_url'] ?? ($jMatch['receipt_url'] ?? ''));

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
                        'receipt_url' => $rUrl,
                        'receipt_urls' => $rUrls,
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
    $matchedReq = null;

    if ($pdo && !empty($docNo)) {
        try {
            $stmt = $pdo->prepare("UPDATE z_ch_saenuri_expense_requests SET status = ?, reject_reason = ? WHERE doc_no = ? OR id = ?");
            $stmt->execute([$status, $rejectReason, $docNo, $docNo]);

            $selectStmt = $pdo->prepare("SELECT * FROM z_ch_saenuri_expense_requests WHERE doc_no = ? OR id = ?");
            $selectStmt->execute([$docNo, $docNo]);
            $matchedReq = $selectStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    $logFile = __DIR__ . '/expense_requests.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as &$req) {
            if (($req['doc_no'] ?? '') === $docNo || (string)($req['id'] ?? '') === (string)$docNo) {
                $req['status'] = $status;
                if (!empty($rejectReason)) $req['reject_reason'] = $rejectReason;
                if (!$matchedReq) $matchedReq = $req;
                break;
            }
        }
        file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 🔔 요청자에게 상태 변경(승인/반려/집행완료) 카카오톡 알림 발송!
    if ($matchedReq) {
        $matchedReq['status'] = $status;
        if (!empty($rejectReason)) $matchedReq['reject_reason'] = $rejectReason;
        if (empty($matchedReq['applicant_email']) && file_exists($logFile)) {
            $fList = json_decode(file_get_contents($logFile), true) ?? [];
            foreach ($fList as $fReq) {
                if (($fReq['doc_no'] ?? '') === $docNo || (string)($fReq['id'] ?? '') === (string)$docNo) {
                    if (!empty($fReq['applicant_email'])) $matchedReq['applicant_email'] = $fReq['applicant_email'];
                    if (!empty($fReq['applicant_name'])) $matchedReq['applicant_name'] = $fReq['applicant_name'];
                    if (!empty($fReq['total_amount'])) $matchedReq['total_amount'] = $fReq['total_amount'];
                    if (!empty($fReq['bank_name'])) $matchedReq['bank_name'] = $fReq['bank_name'];
                    if (!empty($fReq['account_number'])) $matchedReq['account_number'] = $fReq['account_number'];
                    if (!empty($fReq['account_holder'])) $matchedReq['account_holder'] = $fReq['account_holder'];
                    break;
                }
            }
        }
        notifyExpenseEvent('STATUS_UPDATE', $matchedReq);
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
            $stmt = $pdo->query("SELECT id, email, name, department, title_name, status, created_at FROM z_ch_saenuri_users WHERE UPPER(status) = 'PENDING' ORDER BY id DESC");
            if ($stmt) {
                $pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Exception $e) {
            $pendingList = [];
        }
    }

    // 파일 백업 데이터와 병합
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            $fStatus = strtoupper(trim($fUser['status'] ?? 'PENDING'));
            $fEmail = strtolower(trim($fUser['email'] ?? ''));
            if ($fStatus === 'PENDING' && !empty($fEmail)) {
                $alreadyInList = false;
                foreach ($pendingList as $pUser) {
                    $pEmail = strtolower(trim($pUser['email'] ?? ''));
                    if ($pEmail === $fEmail) {
                        $alreadyInList = true;
                        break;
                    }
                }
                if (!$alreadyInList) {
                    $pendingList[] = [
                        'id' => $fUser['id'] ?? time(),
                        'email' => $fUser['email'],
                        'name' => $fUser['name'] ?? '성도',
                        'department' => $fUser['department'] ?? '청년부',
                        'title_name' => $fUser['title_name'] ?? '성도',
                        'status' => 'PENDING',
                        'created_at' => $fUser['created_at'] ?? date('Y-m-d H:i')
                    ];
                }
            }
        }
    }

    // 사이트 관리자(leeshkr@gmail.com)만 제외하고 모든 PENDING 유저 반환!
    $pendingList = array_values(array_filter($pendingList, function($u) {
        $uEmail = strtolower(trim($u['email'] ?? ''));
        return !empty($uEmail) && $uEmail !== 'leeshkr@gmail.com';
    }));

    echo json_encode(['status' => 'success', 'data' => $pendingList], JSON_UNESCAPED_UNICODE);
    exit;
}

// 👑 회원 승인 처리 API (DB/파일 갱신 + 카카오톡 승인 환영 알림 메시지 즉시 발송!)
function handleApproveUserApi() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 누락되었습니다.']);
        exit;
    }

    $userName = '성도';
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            // 회원 이름 조회
            $stmtN = $pdo->prepare("SELECT name FROM z_ch_saenuri_users WHERE LOWER(email) = ? LIMIT 1");
            $stmtN->execute([$email]);
            $uRow = $stmtN->fetch(PDO::FETCH_ASSOC);
            if (!empty($uRow['name'])) {
                $userName = $uRow['name'];
            }

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
            if (!empty($fUser['name']) && $userName === '성도') {
                $userName = $fUser['name'];
            }
            $found = true;
            break;
        }
    }
    if (!$found) {
        $fileData[] = [
            'id' => time(),
            'email' => $email,
            'name' => $userName,
            'department' => '청년부',
            'title_name' => '성도',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    file_put_contents($logFile, json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 💬 카카오톡 회원 승인 환영 알림 메시지 발송!
    $kakaoTitle = "🎉 [세종새누리교회] 회원 가입 승인 완료 안내";
    $kakaoDesc = sprintf(
        "🌿 %s 성도님, 주님의 이름으로 환영합니다!\n\n교회 스마트 비용지출요청시스템의 정회원으로 승인되었습니다.\n\n이제 모바일에서 편리하게 지출요청서를 작성하고 제출하실 수 있습니다.\n\n• 가입 계정: %s\n• 권한: 정회원 (지출요청서 작성 가능)",
        $userName,
        $email
    );
    sendKakaoNotification($email, $kakaoTitle, $kakaoDesc, 'https://expense.sjsnr.kr/');

    echo json_encode(['status' => 'success', 'message' => $email . ' 계정 승인 및 환영 카톡 발송이 완료되었습니다.']);
    exit;
}

function resolvePrimaryEmail($email) {
    $email = strtolower(trim($email));
    if ($email === 'leeshkr@gmail.com') {
        return 'leeshkr@gmail.com';
    }
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
    if ($primary === 'leeshkr@gmail.com') {
        return ['leeshkr@gmail.com'];
    }
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
    $bank = '';
    $account = '';
    $holder = '';

    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT name, title_name, department, default_bank, default_account, default_holder, status, role FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                if (!empty($u['status'])) $userStatus = $u['status'];
                if (!empty($u['role'])) $role = $u['role'];
                if (!empty($u['name'])) $name = $u['name'];
                if (!empty($u['title_name'])) $titleName = $u['title_name'];
                if (!empty($u['department'])) $dept = $u['department'];
                if (!empty($u['default_bank'])) $bank = $u['default_bank'];
                if (!empty($u['default_account'])) $account = $u['default_account'];
                if (!empty($u['default_holder'])) $holder = $u['default_holder'];

                if ($isDevAdmin) {
                    $role = 'ADMIN';
                    $userStatus = 'APPROVED';
                    if (($u['role'] ?? '') !== 'ADMIN' || ($u['status'] ?? '') !== 'APPROVED') {
                        try {
                            $upFix = $pdo->prepare("UPDATE z_ch_saenuri_users SET role = 'ADMIN', status = 'APPROVED' WHERE LOWER(email) = ?");
                            $upFix->execute([$email]);
                        } catch (Exception $eFix) {}
                    }
                }
            } else {
                // 신규 접속 회원 DB에 자동 등록 (관리자는 APPROVED, 일반 성도는 PENDING으로 승인대기 등록!)
                try {
                    $defName = !empty($name) ? $name : '성도';
                    if ($isDevAdmin) {
                        $inFix = $pdo->prepare("INSERT INTO z_ch_saenuri_users (email, name, title_name, department, role, status) VALUES (?, '이승호 개발자', '개발자/관리자', '행정/재정부', 'ADMIN', 'APPROVED') ON DUPLICATE KEY UPDATE role='ADMIN', status='APPROVED'");
                        $inFix->execute([$email]);
                    } else {
                        $inNew = $pdo->prepare("INSERT INTO z_ch_saenuri_users (email, name, title_name, department, role, status) VALUES (?, ?, '성도', '청년부', 'APPLICANT', 'PENDING')");
                        $inNew->execute([$email, $defName]);
                    }
                } catch (Exception $eFix) {}
            }
        } catch (Exception $e) {}
    }

    // 파일 백업 검사 및 신규 유저 자동 동기화
    $logFile = __DIR__ . '/pending_users.json';
    $foundInFile = false;
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            if (strtolower($fUser['email'] ?? '') === $email) {
                $foundInFile = true;
                if (($fUser['status'] ?? '') === 'APPROVED') $userStatus = 'APPROVED';
                if (!empty($fUser['role'])) $role = $fUser['role'];
                if (empty($name) && !empty($fUser['name'])) $name = $fUser['name'];
                if (empty($titleName) && !empty($fUser['title_name'])) $titleName = $fUser['title_name'];
                if (empty($dept) && !empty($fUser['department'])) $dept = $fUser['department'];
                if (empty($bank) && !empty($fUser['default_bank'])) $bank = $fUser['default_bank'];
                if (empty($account) && !empty($fUser['default_account'])) $account = $fUser['default_account'];
                if (empty($holder) && !empty($fUser['default_holder'])) $holder = $fUser['default_holder'];
                break;
            }
        }
        if (!$foundInFile && !$isDevAdmin) {
            $fileData[] = [
                'id' => time(),
                'email' => $email,
                'name' => !empty($name) ? $name : '성도',
                'title_name' => '성도',
                'department' => '청년부',
                'role' => 'APPLICANT',
                'status' => 'PENDING',
                'created_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($logFile, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    if ($isDevAdmin) {
        $role = 'ADMIN';
        $userStatus = 'APPROVED';
    }

    echo json_encode([
        'status' => 'success',
        'user_status' => $userStatus,
        'role' => $role,
        'name' => $name,
        'title_name' => $titleName,
        'department' => $dept,
        'default_bank' => $bank,
        'default_account' => $account,
        'default_holder' => $holder
    ]);
    exit;
}

// 👑 사이트 관리자 전용 - 승인 완료된 교인 회원 및 관리자 목록 조회 API (status = APPROVED 전용)
function handleGetApprovedUsers() {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDbConnection();
    $approvedList = [];

    if ($pdo) {
        try {
            // 오직 승인된 회원(status = 'APPROVED')만 조회!
            $stmt = $pdo->query("SELECT id, email, name, department, title_name, role, status, created_at FROM z_ch_saenuri_users WHERE UPPER(status) = 'APPROVED' ORDER BY id DESC");
            $approvedList = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Exception $e) {
            $approvedList = [];
        }
    }

    // 파일 백업 데이터와 병합 (오직 status = APPROVED 인 유저만!)
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        foreach ($fileData as $fUser) {
            $fEmail = strtolower(trim($fUser['email'] ?? ''));
            $fStatus = strtoupper(trim($fUser['status'] ?? 'PENDING'));
            if (!empty($fEmail) && $fStatus === 'APPROVED') {
                $alreadyInList = false;
                foreach ($approvedList as &$pUser) {
                    if (strtolower($pUser['email'] ?? '') === $fEmail) {
                        $alreadyInList = true;
                        if (empty($pUser['name']) || $pUser['name'] === '성도님' || $pUser['name'] === '카카오 성도') {
                            if (!empty($fUser['name'])) $pUser['name'] = $fUser['name'];
                        }
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
                        'status' => 'APPROVED',
                        'created_at' => $fUser['created_at'] ?? date('Y-m-d H:i')
                    ];
                }
            }
        }
    }

    // 기본 관리자 계정 기본 보장 (leeshkr@gmail.com, ktbmks@hanmail.net)
    $existingEmails = array_map(function($u) { return strtolower($u['email'] ?? ''); }, $approvedList);
    if (!in_array('ktbmks@hanmail.net', $existingEmails)) {
        $approvedList[] = [
            'id' => 9991,
            'email' => 'ktbmks@hanmail.net',
            'name' => '김태봉 목사님',
            'department' => '행정/재정부',
            'title_name' => '목사',
            'role' => 'TREASURER',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }
    if (!in_array('leeshkr@gmail.com', $existingEmails)) {
        $approvedList[] = [
            'id' => 9992,
            'email' => 'leeshkr@gmail.com',
            'name' => '이승호 개발자',
            'department' => '행정/재정부',
            'title_name' => '관리자',
            'role' => 'ADMIN',
            'status' => 'APPROVED',
            'created_at' => date('Y-m-d H:i')
        ];
    }

    // 🔗 소셜 연동된 서브 계정(primary_email에 종속된 계정) 필터링
    $linkFile = __DIR__ . '/social_links.json';
    $links = file_exists($linkFile) ? (json_decode(file_get_contents($linkFile), true) ?? []) : [];
    $subEmails = [];
    foreach ($links as $secEmail => $info) {
        $p = strtolower($info['primary_email'] ?? '');
        $sec = strtolower($secEmail);
        if (!empty($p) && $p !== $sec) {
            $subEmails[] = $sec;
        }
    }
    $approvedList = array_values(array_filter($approvedList, function($u) use ($subEmails) {
        $uE = strtolower(trim($u['email'] ?? ''));
        return !empty($uE) && !in_array($uE, $subEmails);
    }));

    echo json_encode(['status' => 'success', 'data' => $approvedList], JSON_UNESCAPED_UNICODE);
    exit;
}

// 👑 회원 완전히 삭제 처리 API (DB, 백업, 소셜연동, 토큰 모든 저장소에서 100% 완전 삭제)
function handleDeleteUser() {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 정보가 누락되었습니다.']);
        exit;
    }

    // 1. DB에서 완전 삭제
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
        } catch (Exception $e) {}
    }

    // 2. pending_users.json에서 완전 삭제
    $logFile = __DIR__ . '/pending_users.json';
    if (file_exists($logFile)) {
        $fileData = json_decode(file_get_contents($logFile), true) ?? [];
        $filtered = array_values(array_filter($fileData, function($u) use ($email) {
            return strtolower(trim($u['email'] ?? '')) !== $email;
        }));
        file_put_contents($logFile, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 3. social_links.json에서 완전 삭제
    $socialFile = __DIR__ . '/social_links.json';
    if (file_exists($socialFile)) {
        $socialData = json_decode(file_get_contents($socialFile), true) ?? [];
        if (isset($socialData[$email])) {
            unset($socialData[$email]);
        }
        foreach ($socialData as $k => $s) {
            if (strtolower($s['primary_email'] ?? '') === $email) {
                unset($socialData[$k]);
            }
        }
        file_put_contents($socialFile, json_encode($socialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 4. kakao_tokens.json에서 토큰 완전 삭제
    $tokenFile = __DIR__ . '/kakao_tokens.json';
    if (file_exists($tokenFile)) {
        $tokenData = json_decode(file_get_contents($tokenFile), true) ?? [];
        if (isset($tokenData[$email])) {
            unset($tokenData[$email]);
            file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    echo json_encode(['status' => 'success', 'message' => '회원 계정이 모든 저장소에서 완전히 삭제되었습니다.']);
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

// 🔔 이벤트 발생 시 관리자 및 요청자 카카오톡 알림 발송 래퍼 (중복 발송 100% 차단)
function notifyExpenseEvent($type, $data) {
    $docNo = $data['doc_no'] ?? '-';
    $applicantName = $data['applicant_name'] ?? '성도';
    $applicantEmail = strtolower(trim($data['applicant_email'] ?? ''));
    $department = $data['department'] ?? '부서';
    $totalAmount = (float)($data['total_amount'] ?? 0);
    $purpose = $data['purpose'] ?? '비용 지출';
    $bankName = $data['bank_name'] ?? '';
    $accountNumber = $data['account_number'] ?? '';
    $accountHolder = $data['account_holder'] ?? $applicantName;
    $rejectReason = trim($data['reject_reason'] ?? '');

    // 1️⃣ [요청 등록 완료]: 성도님이 요청서를 제출했을 때
    if ($type === 'NEW_REQUEST') {
        // A. 요청자 본인에게 알림 (1통)
        $userTitle = "📝 [지출요청서 접수 완료]";
        $userDesc = "성도님의 지출요청서가 정상 접수되었습니다.\n\n"
                  . "• 문서번호: " . $docNo . "\n"
                  . "• 요청자: " . $applicantName . "\n"
                  . "• 요청부서: " . $department . "\n"
                  . "• 요청금액: " . number_format($totalAmount) . "원\n"
                  . "• 지출목적: " . $purpose . "\n\n"
                  . "담당 재정부 확인 후 결재가 진행됩니다.";

        $applicantTarget = !empty($applicantEmail) ? $applicantEmail : $applicantName;
        sendKakaoNotification($applicantTarget, $userTitle, $userDesc);

        // B. 재정담당자/관리자에게 도착 알림 (관리자에게만 1통, 본인 신청 시 2중 발송 제외)
        $isAdminApplicant = (strpos($applicantEmail, 'leesh') !== false || strpos($applicantName, '이승호') !== false);
        if (!$isAdminApplicant) {
            $adminTitle = "📌 [신규 지출요청 도착]";
            $adminDesc = "새로운 지출요청서가 접수되었습니다.\n\n"
                       . "• 문서번호: " . $docNo . "\n"
                       . "• 요청자: " . $applicantName . " 성도\n"
                       . "• 요청부서: " . $department . "\n"
                       . "• 요청금액: " . number_format($totalAmount) . "원\n"
                       . "• 지출목적: " . $purpose;
            sendKakaoNotification('leeshkr@gmail.com', $adminTitle, $adminDesc);
        }
    } 
    // 2️⃣ [상태 변경]: 재정부 지출 승인 / 반려 / 집행 완료
    elseif ($type === 'STATUS_UPDATE') {
        $st = $data['status'] ?? 'APPROVED';

        if ($st === 'APPROVED') {
            // 2-1. 승인 완료 알림
            $title = "👍 [지출요청 결재 승인 완료]";
            $desc = "성도님의 지출요청서 결재가 승인되었습니다!\n\n"
                  . "• 문서번호: " . $docNo . "\n"
                  . "• 요청자: " . $applicantName . "\n"
                  . "• 승인금액: " . number_format($totalAmount) . "원\n"
                  . "• 진행상태: 👍 승인 완료 (재정부 입금 집행 대기 중)";
        } elseif ($st === 'REJECTED') {
            // 2-2. 반려 처리 알림
            $title = "❌ [지출요청 반려 안내]";
            $desc = "성도님의 지출요청서가 반려되었습니다.\n\n"
                  . "• 문서번호: " . $docNo . "\n"
                  . "• 요청자: " . $applicantName . "\n"
                  . "• 반려사유: " . ($rejectReason ?: '사유 미기재') . "\n\n"
                  . "확인 후 필요 시 다시 신청해 주시기 바랍니다.";
        } elseif ($st === 'PAID') {
            // 3. 재정부 집행(입금) 완료 알림
            $title = "💰 [지출금 계좌 입금 완료]";
            $desc = "성도님의 등록 계좌로 지출금 입금이 완료되었습니다!\n\n"
                  . "• 문서번호: " . $docNo . "\n"
                  . "• 요청자: " . $applicantName . "\n"
                  . "• 입금금액: " . number_format($totalAmount) . "원\n"
                  . "• 입금계좌: " . $bankName . " " . $accountNumber . " (예금주: " . $accountHolder . ")";
        } else {
            $title = "🔔 [지출요청 상태 안내]";
            $desc = "• 문서번호: " . $docNo . "\n• 진행상태: " . $st;
        }

        $applicantTarget = !empty($applicantEmail) ? $applicantEmail : $applicantName;
        sendKakaoNotification($applicantTarget, $title, $desc);
    }
}

// 💬 카카오톡 메시지 실시간 발송 및 토큰 자동 갱신 (중복 토큰 단 1회 발송 방어)
function sendKakaoNotification($emailOrId, $title, $description, $webUrl = 'https://expense.sjsnr.kr/') {
    static $sentTokensThisRequest = [];

    $targetKey = strtolower(trim($emailOrId));
    if (empty($targetKey)) return false;

    $tokenFile = __DIR__ . '/kakao_tokens.json';
    if (!file_exists($tokenFile)) return false;
    $tokens = json_decode(file_get_contents($tokenFile), true) ?? [];

    $tokenData = $tokens[$targetKey] ?? null;

    // 소셜 연동 맵핑된 키로 재검색
    if (!$tokenData) {
        $linkFile = __DIR__ . '/social_links.json';
        if (file_exists($linkFile)) {
            $links = json_decode(file_get_contents($linkFile), true) ?? [];
            if (!empty($links[$targetKey]['primary_email'])) {
                $pKey = strtolower($links[$targetKey]['primary_email']);
                $tokenData = $tokens[$pKey] ?? null;
            }
        }
    }

    // 이승호님/관리자 계정 토큰 스마트 폴백 매칭
    if (!$tokenData) {
        if (strpos($targetKey, 'leesh') !== false || strpos($targetKey, '이승호') !== false) {
            $tokenData = $tokens['leesh@naver.com'] ?? $tokens['kakao_5035521659'] ?? null;
        }
    }

    if (!$tokenData || empty($tokenData['access_token'])) {
        file_put_contents(__DIR__ . '/kakao_msg_debug.log', sprintf("[%s] NO TOKEN for %s\n", date('Y-m-d H:i:s'), $targetKey), FILE_APPEND);
        return false;
    }

    $accessToken = $tokenData['access_token'];

    // 🛑 동일 요청 내 동일 토큰 중복 발송 방어 (Deduplication)
    $tokenHash = md5($accessToken . '_' . $title);
    if (isset($sentTokensThisRequest[$tokenHash])) {
        return true;
    }
    $sentTokensThisRequest[$tokenHash] = true;

    // 1. 카카오 기본 텍스트 템플릿 구성
    $templateObject = [
        'object_type' => 'text',
        'text' => $title . "\n\n" . $description,
        'link' => [
            'web_url' => $webUrl,
            'mobile_web_url' => $webUrl
        ],
        'button_title' => '스마트 지출요청서 열기'
    ];

    $sendUrl = "https://kapi.kakao.com/v2/api/talk/memo/default/send";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sendUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'template_object' => json_encode($templateObject, JSON_UNESCAPED_UNICODE)
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resData = json_decode($response, true);

    // 2. 만약 access_token 만료(401) 에러 시 refresh_token으로 자동 갱신 후 재전송!
    if ($httpCode === 401 && !empty($tokenData['refresh_token'])) {
        $refreshUrl = "https://kauth.kakao.com/oauth/token";
        $refreshParams = [
            'grant_type' => 'refresh_token',
            'client_id' => 'ce26064239879368e6adaaa9f396dc48',
            'refresh_token' => $tokenData['refresh_token']
        ];
        $rCh = curl_init();
        curl_setopt($rCh, CURLOPT_URL, $refreshUrl);
        curl_setopt($rCh, CURLOPT_POST, true);
        curl_setopt($rCh, CURLOPT_POSTFIELDS, http_build_query($refreshParams));
        curl_setopt($rCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($rCh, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded;charset=utf-8']);
        curl_setopt($rCh, CURLOPT_SSL_VERIFYPEER, false);
        $refreshResp = curl_exec($rCh);
        curl_close($rCh);

        $refreshData = json_decode($refreshResp, true);
        if (!empty($refreshData['access_token'])) {
            $accessToken = $refreshData['access_token'];
            $tokenData['access_token'] = $accessToken;
            if (!empty($refreshData['refresh_token'])) {
                $tokenData['refresh_token'] = $refreshData['refresh_token'];
            }
            $tokenData['expires_at'] = time() + (int)($refreshData['expires_in'] ?? 21600);
            $tokenData['updated_at'] = date('Y-m-d H:i:s');
            $tokens[$targetKey] = $tokenData;
            file_put_contents($tokenFile, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 갱신된 토큰으로 재전송
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sendUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'template_object' => json_encode($templateObject, JSON_UNESCAPED_UNICODE)
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/x-www-form-urlencoded;charset=utf-8'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $resData = json_decode($response, true);
        }
    }

    $logMsg = sprintf("[%s] Kakao Msg To: %s | Result: %s\n", date('Y-m-d H:i:s'), $targetKey, $response);
    file_put_contents(__DIR__ . '/kakao_msg_debug.log', $logMsg, FILE_APPEND);

    return ($resData['result_code'] ?? 1) === 0;
}

