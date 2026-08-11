<?php
/**
 * 세종새누리교회 비용지출요청
 */
session_start();

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

$currentUser = $_SESSION['user'] ?? ['id' => 1, 'name' => '김성도', 'title_name' => '집사', 'role' => 'APPLICANT', 'dept' => '청년부'];
$userRole = $currentUser['role'] ?? 'APPLICANT';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>세종새누리교회 비용지출요청</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- 🌿 깔끔하고 맑은 상단 헤더 -->
    <header class="app-header" style="padding: 10px 14px;">
        <div style="display:flex; align-items:center;">
            <img src="images/logo.png" alt="기독교한국침례회 세종새누리교회" style="height:46px; border:none; background:transparent; box-shadow:none; border-radius:0; filter:drop-shadow(0 1px 2px rgba(0,0,0,0.15));">
        </div>
        <button onclick="location.href='login.html'" style="background:rgba(255,255,255,0.22); color:#fff; border:1px solid rgba(255,255,255,0.5); padding:6px 12px; border-radius:8px; font-size:13px; font-weight:bold; cursor:pointer;">
            🚪 화면닫기
        </button>
    </header>

    <div class="container">
        <!-- 메인 컨텐츠 -->
    </div>
</body>
</html>
