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
} elseif ($action === 'update_status') {
    handleUpdateStatus($currentUser);
} elseif ($action === 'update_mypage') {
    handleUpdateMyPage($currentUser);
} elseif ($action === 'add_department') {
    handleAddDepartment();
} elseif ($action === 'delete_department') {
    handleDeleteDepartment();
} elseif ($action === 'add_title') {
    handleAddTitle();
} elseif ($action === 'delete_title') {
    handleDeleteTitle();
} elseif ($action === 'auto_update_user') {
    handleAutoUpdateUser();
} else {
    header('Location: ../public/index.php');
    exit;
}

function handleUpdateMyPage(array $user) {
    $pdo = getDbConnection();
    $name = trim($_POST['name'] ?? $user['name']);
    $titleName = trim($_POST['title_name'] ?? '성도');
    $deptName = trim($_POST['department'] ?? '청년부');
    $bank = trim($_POST['default_bank'] ?? '');
    $account = trim($_POST['default_account'] ?? '');
    $holder = trim($_POST['default_holder'] ?? '');
    $mode = $_POST['preferred_mode'] ?? 'wizard';
    $theme = $_POST['preferred_theme'] ?? 'green';

    if ($pdo) {
        $stmt = $pdo->prepare("
            UPDATE z_ch_saenuri_users 
            SET name = ?, title_name = ?, department = ?, default_bank = ?, default_account = ?, default_holder = ?, preferred_mode = ?, preferred_theme = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $titleName, $deptName, $bank, $account, $holder, $mode, $theme, $user['id']]);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['title_name'] = $titleName;
        $_SESSION['user']['dept'] = $deptName;
    }

    header('Location: ../public/index.php?tab=mypage&msg=saved');
    exit;
}

function handleCreateRequest(array $user) {
    $pdo = getDbConnection();
    
    $department = trim($_POST['department'] ?? '청년부');
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? '일반지출';
    $purpose = trim($_POST['purpose'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountHolder = trim($_POST['account_holder'] ?? '');
    $signatureData = $_POST['signature_data'] ?? '';
    $totalAmount = (float)($_POST['total_amount'] ?? 0);

    $docNo = 'EXP-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $requestDate = date('Y-m-d');

    if ($pdo) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_expense_requests (
                    doc_no, user_id, applicant_name, department, request_date, expense_date, 
                    category, purpose, total_amount, bank_name, account_number, account_holder, signature_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $docNo, $user['id'], $user['name'], $department, $requestDate, $expenseDate,
                $category, $purpose, $totalAmount, $bankName, $accountNumber, $accountHolder, $signatureData
            ]);

            $requestId = $pdo->lastInsertId();

            $itemNames = $_POST['item_name'] ?? [];
            $amounts = $_POST['amount'] ?? [];
            $notes = $_POST['note'] ?? [];

            $itemStmt = $pdo->prepare("
                INSERT INTO z_ch_saenuri_expense_items (request_id, item_order, item_name, amount, note)
                VALUES (?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($itemNames); $i++) {
                if (!empty($itemNames[$i])) {
                    $itemStmt->execute([
                        $requestId,
                        $i + 1,
                        trim($itemNames[$i]),
                        (float)($amounts[$i] ?? 0),
                        trim($notes[$i] ?? '')
                    ]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    header('Location: ../public/index.php?msg=success');
    exit;
}

function handleUpdateStatus(array $user) {
    $pdo = getDbConnection();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? 'APPROVED';
    $rejectReason = trim($_POST['reject_reason'] ?? '');

    if ($pdo && $requestId > 0) {
        $stmt = $pdo->prepare("UPDATE z_ch_saenuri_expense_requests SET status = ?, reject_reason = ? WHERE id = ?");
        $stmt->execute([$status, $rejectReason, $requestId]);
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
