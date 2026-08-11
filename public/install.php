<?php
/**
 * 세종새누리교회 비용지출요청 - DB 테이블 자동 생성 및 설치 스크립트
 */
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$db   = 'nuriohga';
$user = 'nuriohga';
$pass = '#seungho0409';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 1. 교인 회원 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(50) NOT NULL,
        title_name VARCHAR(30) DEFAULT '성도',
        dept_name VARCHAR(50) DEFAULT '청년부',
        role VARCHAR(20) DEFAULT 'APPLICANT',
        bank_name VARCHAR(30) DEFAULT '',
        account_num VARCHAR(50) DEFAULT '',
        account_holder VARCHAR(30) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. 지출요청 마스터 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        req_code VARCHAR(30) NOT NULL UNIQUE,
        user_id INT NOT NULL,
        dept_name VARCHAR(50) NOT NULL,
        purpose VARCHAR(255) NOT NULL,
        bank_name VARCHAR(30) NOT NULL,
        account_num VARCHAR(50) NOT NULL,
        account_holder VARCHAR(30) NOT NULL,
        total_amount DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'PENDING',
        review_memo TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. 지출 항목 상세 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. 사역 부서 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_depts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dept_name VARCHAR(50) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. 직분 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS z_ch_saenuri_titles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title_name VARCHAR(30) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 초기 샘플 데이터 생성
    $pdo->exec("INSERT IGNORE INTO z_ch_saenuri_depts (dept_name, sort_order) VALUES 
        ('청년부', 1), ('주일학교', 2), ('방송실', 3), ('찬양대', 4), ('선교부', 5);");

    $pdo->exec("INSERT IGNORE INTO z_ch_saenuri_titles (title_name, sort_order) VALUES 
        ('성도', 1), ('집사', 2), ('권사', 3), ('장로', 4), ('목사', 5), ('전도사', 6);");

    $pdo->exec("INSERT IGNORE INTO z_ch_saenuri_users (user_id, name, title_name, dept_name, role) VALUES 
        ('kim_applicant', '김성도', '집사', '청년부', 'APPLICANT'),
        ('lee_treasurer', '이재정', '장로', '재정부', 'TREASURER'),
        ('choi_admin', '최관리', '목사', '행정실', 'ADMIN');");

    echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
    echo "<h2 style='color:#059669;'>🎉 MariaDB DB 테이블 자동 생성 및 설치가 완벽하게 완료되었습니다!</h2>";
    echo "<p style='color:#475569;'>생성된 테이블: z_ch_saenuri_users, z_ch_saenuri_requests, z_ch_saenuri_items, z_ch_saenuri_depts, z_ch_saenuri_titles</p>";
    echo "<a href='index.html' style='display:inline-block; margin-top:14px; padding:10px 18px; background:#54824e; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>⛪ 메인으로 이동하기</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h3 style='color:#dc2626;'>❌ DB 설치 오류: " . $e->getMessage() . "</h3>";
}
