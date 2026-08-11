<?php
/**
 * 세종새누리교회 스마트 재정행정시스템 - 원격 DB 테이블 자동 설치/초기화 스크립트
 */

header('Content-Type: text/html; charset=utf-8');

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

echo "<h2>⛪ 세종새누리교회 스마트 재정행정시스템 DB 설치/업데이트 스크립트</h2>";

$pdo = getDbConnection();
if (!$pdo) {
    die("<p style='color:red;'>❌ DB 연결 실패! config/db.php 접속 정보를 확인하세요.</p>");
}

try {
    // 1. 회원 사용자 테이블에 마이페이지 관련 컬럼 보강
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
        preferred_mode VARCHAR(20) DEFAULT 'wizard',
        preferred_theme VARCHAR(20) DEFAULT 'green',
        default_bank VARCHAR(30) NULL,
        default_account VARCHAR(50) NULL,
        default_holder VARCHAR(50) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_oauth (oauth_provider, oauth_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 기존 테이블 컬럼 보강 시도
    try { $pdo->exec("ALTER TABLE z_ch_saenuri_users ADD COLUMN preferred_mode VARCHAR(20) DEFAULT 'wizard';"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE z_ch_saenuri_users ADD COLUMN preferred_theme VARCHAR(20) DEFAULT 'green';"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE z_ch_saenuri_users ADD COLUMN default_bank VARCHAR(30) NULL;"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE z_ch_saenuri_users ADD COLUMN default_account VARCHAR(50) NULL;"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE z_ch_saenuri_users ADD COLUMN default_holder VARCHAR(50) NULL;"); } catch(Exception $e){}

    echo "<p style='color:green; font-weight:bold; font-size:16px;'>🎉 z_ch_saenuri_ 마이페이지 DB 컬럼 업데이트가 성공적으로 완료되었습니다!</p>";
    echo "<p><a href='index.html'>👉 메인 화면(index.html)으로 이동하기</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ 설치 오류 발생: " . htmlspecialchars($e->getMessage()) . "</p>";
}
