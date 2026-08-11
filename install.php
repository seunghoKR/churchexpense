<?php
/**
 * 세종새누리교회 스마트 재정행정시스템 - 원격 DB 테이블 자동 설치/초기화 스크립트
 * 테이블 접두사: z_ch_saenuri_
 */

header('Content-Type: text/html; charset=utf-8');

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

echo "<h2>⛪ 세종새누리교회 스마트 재정행정시스템 DB 설치 스크립트</h2>";

$pdo = getDbConnection();
if (!$pdo) {
    die("<p style='color:red;'>❌ DB 연결 실패! config/db.php 접속 정보를 확인하세요.</p>");
}

try {
    // 1. 부서 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dept_name VARCHAR(50) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. 직함 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_church_titles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title_name VARCHAR(50) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. 회원 등급 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_code VARCHAR(30) UNIQUE NOT NULL,
        role_name VARCHAR(50) NOT NULL,
        description VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. 회원 사용자 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        oauth_provider VARCHAR(20) NOT NULL,
        oauth_id VARCHAR(100) NOT NULL,
        name VARCHAR(50) NOT NULL,
        title_name VARCHAR(30) NOT NULL DEFAULT '성도',
        email VARCHAR(100),
        phone VARCHAR(20),
        department VARCHAR(50) NOT NULL DEFAULT '청년부',
        role VARCHAR(30) NOT NULL DEFAULT 'APPLICANT',
        remember_token VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_oauth (oauth_provider, oauth_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. 지출요청서 헤더 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doc_no VARCHAR(30) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        applicant_name VARCHAR(50) NOT NULL,
        department VARCHAR(50) NOT NULL,
        request_date DATE NOT NULL,
        expense_date DATE NOT NULL,
        category VARCHAR(50) DEFAULT '일반지출',
        purpose TEXT NOT NULL,
        total_amount DECIMAL(12, 0) NOT NULL DEFAULT 0,
        bank_name VARCHAR(30) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        account_holder VARCHAR(50) NOT NULL,
        signature_data LONGTEXT,
        payment_date DATE NULL,
        advance_additional DECIMAL(12, 0) DEFAULT 0,
        advance_return DECIMAL(12, 0) DEFAULT 0,
        advance_return_date DATE NULL,
        status ENUM('PENDING', 'APPROVED', 'REJECTED', 'PAID') DEFAULT 'PENDING',
        reject_reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES z_ch_saenuri_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. 지출 세부 항목 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        item_order INT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        amount DECIMAL(12, 0) NOT NULL,
        note VARCHAR(255) DEFAULT '',
        FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. 영수증 파일 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. 알림 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_id INT NOT NULL,
        type VARCHAR(20) DEFAULT 'STATUS_CHANGE',
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES z_ch_saenuri_users(id) ON DELETE CASCADE,
        FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 시드 데이터 생성
    $pdo->exec("INSERT INTO z_ch_saenuri_departments (dept_name) VALUES 
    ('청년부'), ('주일학교'), ('방송실'), ('찬양대'), ('봉사위원회'), ('사역지원위'), ('재정운영팀')
    ON DUPLICATE KEY UPDATE dept_name=VALUES(dept_name);");

    $pdo->exec("INSERT INTO z_ch_saenuri_church_titles (title_name) VALUES 
    ('성도'), ('집사'), ('안수집사'), ('권사'), ('장로'), ('전도사'), ('강도사'), ('목사')
    ON DUPLICATE KEY UPDATE title_name=VALUES(title_name);");

    $pdo->exec("INSERT INTO z_ch_saenuri_user_roles (role_code, role_name, description) VALUES
    ('APPLICANT', '신청자', '일반 지출 요청 교인 성도님'),
    ('TREASURER', '재정부', '지출 승인 및 재정 집행/반려 담당자'),
    ('ADMIN', '사이트 관리자', '부서 및 회원 등급 총괄 관리자')
    ON DUPLICATE KEY UPDATE role_name=VALUES(role_name);");

    $pdo->exec("INSERT INTO z_ch_saenuri_users (oauth_provider, oauth_id, name, title_name, email, department, role) VALUES 
    ('demo', 'applicant_01', '김성도', '집사', 'applicant@church.org', '청년부', 'APPLICANT'),
    ('demo', 'treasurer_01', '이재정', '장로', 'treasurer@church.org', '재정운영팀', 'TREASURER'),
    ('demo', 'admin_01', '최관리', '목사', 'admin@church.org', '사역지원위', 'ADMIN')
    ON DUPLICATE KEY UPDATE name=VALUES(name);");

    echo "<p style='color:green; font-weight:bold; font-size:16px;'>🎉 z_ch_saenuri_ 테이블 8종 및 시드 데이터 생성이 성공적으로 완료되었습니다!</p>";
    echo "<p><a href='login.html'>👉 로그인 화면(login.html)으로 이동하기</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ 설치 오류 발생: " . htmlspecialchars($e->getMessage()) . "</p>";
}
