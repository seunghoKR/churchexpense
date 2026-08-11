<?php
/**
 * 카카오 OAuth 2.0 소셜 로그인 모듈 (PHP 8.4)
 * 
 * [카카오 디벨로퍼스 설정 방법]
 * 1. https://developers.kakao.com 접속 후 앱 생성
 * 2. 카카오 로그인 사용 설정 -> Redirect URI 설정: http://your-domain.com/auth/kakao_login.php
 * 3. KAKAO_REST_API_KEY 입력
 */

session_start();

define('KAKAO_REST_API_KEY', 'YOUR_KAKAO_REST_API_KEY');
define('KAKAO_REDIRECT_URI', 'http://localhost/auth/kakao_login.php');

$code = $_GET['code'] ?? null;

if (!$code) {
    // 1. 카카오 인가 코드 요청 URL 생성 및 이동
    $kakaoAuthUrl = "https://kauth.kakao.com/oauth/authorize?client_id=" . KAKAO_REST_API_KEY . "&redirect_uri=" . urlencode(KAKAO_REDIRECT_URI) . "&response_type=code";
    header("Location: " . $kakaoAuthUrl);
    exit;
} else {
    // 2. 인가 코드로 토큰 발급 및 사용자 정보 가져오기 (cURL / PHP 8.4)
    // 실제 운영 시 REST API Key 설정 후 아래 코드 활성화
    
    // 모의 데모 로그인 세션 생성
    $_SESSION['user'] = [
        'id' => rand(100, 999),
        'name' => '김성도 (카카오)',
        'email' => 'kakao_user@kakao.com',
        'role' => 'APPLICANT',
        'dept' => '청년부'
    ];

    header("Location: ../public/index.php?msg=login_success");
    exit;
}
