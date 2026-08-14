<?php
/**
 * 세종새누리교회 비용지출요청 - DB 테이블 자동 생성 및 설치 스크립트 (PHP 8.4)
 */
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB 설정 정보 로드
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/public/config/db.php')) {
    require_once __DIR__ . '/public/config/db.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nuriohga');
    define('DB_USER', 'nuriohga');
    define('DB_PASS', 'seungho0409#');
    define('DB_CHARSET', 'utf8mb4');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Foreign key check 해제 후 충돌하는 구버전/임시 테이블 완전 정리
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    try {
        $pdo->exec("DROP TABLE IF EXISTS `z_ch_saenuri_items`, `z_ch_saenuri_requests`, `z_ch_saenuri_depts`, `z_ch_saenuri_titles`, `z_ch_saenuri_expense_items`, `z_ch_saenuri_expense_requests`, `z_ch_saenuri_notifications`, `z_ch_saenuri_users`;");
    } catch (Exception $e) {}

    // 1. 교인 회원 테이블 (z_ch_saenuri_users)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `oauth_provider` VARCHAR(20) NOT NULL DEFAULT 'google',
            `oauth_id` VARCHAR(100) NOT NULL DEFAULT 'default_id',
            `name` VARCHAR(50) NOT NULL,
            `title_name` VARCHAR(30) DEFAULT '성도',
            `email` VARCHAR(100),
            `phone` VARCHAR(20) DEFAULT '',
            `department` VARCHAR(50) DEFAULT '청년부',
            `role` VARCHAR(30) DEFAULT 'APPLICANT',
            `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
            `remember_token` VARCHAR(100) NULL,
            `preferred_mode` VARCHAR(20) DEFAULT 'wizard',
            `preferred_theme` VARCHAR(20) DEFAULT 'green',
            `default_bank` VARCHAR(30) NULL,
            `default_account` VARCHAR(50) NULL,
            `default_holder` VARCHAR(50) NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {
        echo "Users create err: " . $e->getMessage() . "<br>";
    }

    // 2. 부서 테이블
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_departments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `dept_name` VARCHAR(50) NOT NULL UNIQUE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    // 3. 직분 테이블
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_church_titles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title_name` VARCHAR(50) NOT NULL UNIQUE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    // 4. 지출요청 마스터 테이블
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_expense_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `doc_no` VARCHAR(30) UNIQUE NOT NULL,
            `user_id` INT NOT NULL,
            `applicant_name` VARCHAR(50) NOT NULL,
            `department` VARCHAR(50) NOT NULL,
            `request_date` DATE NOT NULL,
            `expense_date` DATE NOT NULL,
            `category` VARCHAR(50) DEFAULT '일반지출',
            `purpose` TEXT NOT NULL,
            `total_amount` DECIMAL(12, 0) NOT NULL DEFAULT 0,
            `bank_name` VARCHAR(30) NOT NULL,
            `account_number` VARCHAR(50) NOT NULL,
            `account_holder` VARCHAR(50) NOT NULL,
            `signature_data` LONGTEXT,
            `payment_date` DATE NULL,
            `advance_additional` DECIMAL(12, 0) DEFAULT 0,
            `advance_return` DECIMAL(12, 0) DEFAULT 0,
            `advance_return_date` DATE NULL,
            `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'PAID') DEFAULT 'PENDING',
            `reject_reason` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {
        echo "Requests create err: " . $e->getMessage() . "<br>";
    }

    // 5. 지출 항목 상세 테이블
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_expense_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT NOT NULL,
            `item_order` INT NOT NULL,
            `item_name` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(12, 0) NOT NULL,
            `note` VARCHAR(255) DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    // 6. 알림 테이블
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `z_ch_saenuri_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `request_id` INT NOT NULL,
            `type` VARCHAR(20) DEFAULT 'STATUS_CHANGE',
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 초기 기본 샘플 데이타 셋업
    try {
        $pdo->exec("INSERT IGNORE INTO `z_ch_saenuri_departments` (`dept_name`) VALUES 
            ('청년부'), ('주일학교'), ('방송실'), ('찬양대'), ('선교부'), ('행정/재정부');");
        $pdo->exec("INSERT IGNORE INTO `z_ch_saenuri_church_titles` (`title_name`) VALUES 
            ('성도'), ('집사'), ('권사'), ('장로'), ('목사'), ('전도사');");
        $pdo->exec("INSERT INTO `z_ch_saenuri_users` (`oauth_provider`, `oauth_id`, `name`, `title_name`, `email`, `department`, `role`, `status`)
            VALUES ('google', 'dev_leeshkr', '이승호 개발자', '개발자/관리자', 'leeshkr@gmail.com', '행정/재정부', 'ADMIN', 'APPROVED')
            ON DUPLICATE KEY UPDATE `role`='ADMIN', `status`='APPROVED';");
    } catch (Exception $e) {}

    // 생성된 z_ch_saenuri_ 테이블 목록 조회
    $stmt = $pdo->query("SHOW TABLES LIKE 'z_ch_saenuri_%'");
    $createdTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<!DOCTYPE html><html lang='ko'><head><meta charset='UTF-8'><title>MariaDB 테이블 생성 완료</title></head><body style='font-family:sans-serif; background:#f4f7f4; padding:40px; text-align:center;'>";
    echo "<div style='max-width:560px; margin:0 auto; background:#fff; padding:32px; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); border:1px solid #d1fae5;'>";
    echo "<h2 style='color:#059669; margin-bottom:8px;'>🎉 MariaDB DB 테이블 구축 완료!</h2>";
    echo "<p style='color:#64748b; font-size:14px; margin-bottom:20px;'>DB [nuriohga] 에 실제 생성된 z_ch_saenuri_ 테이블 목록:</p>";
    echo "<ul style='text-align:left; background:#f0fdf4; padding:18px 28px; border-radius:12px; color:#166534; border:1px solid #bbf7d0;'>";
    foreach ($createdTables as $t) {
        echo "<li style='font-weight:bold; font-size:15px; margin-bottom:6px; color:#047857;'>✔ " . htmlspecialchars($t) . "</li>";
    }
    echo "</ul>";
    echo "<a href='index.html' style='display:inline-block; margin-top:20px; padding:14px 24px; background:#059669; color:#fff; text-decoration:none; border-radius:10px; font-weight:bold; font-size:15px;'>⛪ 세종새누리교회 시스템으로 이동하기</a>";
    echo "</div></body></html>";

} catch (PDOException $e) {
    echo "<h3 style='color:#dc2626; text-align:center;'>❌ DB 설치 오류: " . htmlspecialchars($e->getMessage()) . "</h3>";
}

