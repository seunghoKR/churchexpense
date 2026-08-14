<?php
/**
 * 세종새누리교회 비용지출요청 메인 게이트웨이 (PHP 8.4)
 */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 로그인 세션이 이미 존재할 경우 메인 앱(index.html)으로 이동
if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $queryParams = http_build_query([
        'email' => $user['email'] ?? '',
        'name' => $user['name'] ?? '',
        'role' => $user['role'] ?? 'APPLICANT',
        'status' => $user['status'] ?? 'PENDING',
        'provider' => $user['provider'] ?? 'google'
    ]);
    header("Location: index.html?" . $queryParams);
    exit;
} else {
    // 로그인 세션이 없으면 바로 로그인 관문 페이지(login.html)로 자동 연결
    header("Location: login.html");
    exit;
}

