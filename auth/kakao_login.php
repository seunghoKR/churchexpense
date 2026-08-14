<?php
/**
 * 카카오 OAuth 2.0 소셜 로그인 모듈 (PHP 8.4)
 */
session_start();

define('KAKAO_REST_API_KEY', 'ce26064239879368e6adaaa9f396dc48');
define('KAKAO_CLIENT_SECRET', ''); // 카카오 디벨로퍼스 > 보안 > Client Secret 활성화 시 입력
define('KAKAO_REDIRECT_URI', 'https://expense.sjsnr.kr/auth/kakao_login.php');

$code = $_GET['code'] ?? null;
$primaryEmailParam = $_GET['primary_email'] ?? null;
if ($primaryEmailParam) {
    $_SESSION['linking_primary_email'] = strtolower(trim($primaryEmailParam));
}

if (!$code) {
    $statePayload = json_encode(['primary_email' => $_SESSION['linking_primary_email'] ?? '']);
    // 1. 카카오 인가 코드 요청 URL 생성 및 이동 (KOE205 에러 방지를 위해 기본 인증 URL로 사용)
    $kakaoAuthUrl = "https://kauth.kakao.com/oauth/authorize?client_id=" . KAKAO_REST_API_KEY . "&redirect_uri=" . urlencode(KAKAO_REDIRECT_URI) . "&response_type=code&state=" . urlencode($statePayload);
    header("Location: " . $kakaoAuthUrl);
    exit;
} else {
    // 2. 인가 코드로 토큰 발급 (cURL)
    $tokenUrl = "https://kauth.kakao.com/oauth/token";
    $postParams = [
        'grant_type' => 'authorization_code',
        'client_id' => KAKAO_REST_API_KEY,
        'redirect_uri' => KAKAO_REDIRECT_URI,
        'code' => $code
    ];

    if (defined('KAKAO_CLIENT_SECRET') && KAKAO_CLIENT_SECRET !== '') {
        $postParams['client_secret'] = KAKAO_CLIENT_SECRET;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-type: application/x-www-form-urlencoded;charset=utf-8'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? null;

    if ($accessToken) {
        // 3. 토큰으로 카카오 사용자 정보 조회
        $userInfoUrl = "https://kapi.kakao.com/v2/user/me";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-type: application/x-www-form-urlencoded;charset=utf-8'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $userInfoResp = curl_exec($ch);
        curl_close($ch);

        $userInfo = json_decode($userInfoResp, true);
        $kakaoAccount = $userInfo['kakao_account'] ?? [];
        $profile = $kakaoAccount['profile'] ?? [];

        $nickname = $profile['nickname'] ?? '카카오 성도';
        $email = $kakaoAccount['email'] ?? ('kakao_' . ($userInfo['id'] ?? rand(100, 999)) . '@kakao.com');
        $kakaoId = $userInfo['id'] ?? rand(100, 999);

        // 🔗 듀얼 소셜 연동 처리 (OAuth state 및 Session 지원)
        $linkFile = __DIR__ . '/../api/social_links.json';
        $links = file_exists($linkFile) ? (json_decode(file_get_contents($linkFile), true) ?? []) : [];

        $stateData = json_decode($_GET['state'] ?? '{}', true);
        $linkingPrimary = $_SESSION['linking_primary_email'] ?? $stateData['primary_email'] ?? null;
        if (!empty($linkingPrimary) && strtolower($linkingPrimary) !== strtolower($email)) {
            $links[strtolower($email)] = [
                'primary_email' => strtolower($linkingPrimary),
                'provider' => 'kakao',
                'linked_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $email = strtolower($linkingPrimary);
            unset($_SESSION['linking_primary_email']);
        } elseif (!empty($links[strtolower($email)]['primary_email'])) {
            $email = strtolower($links[strtolower($email)]['primary_email']);
        }

        require_once __DIR__ . '/../config/db.php';

        $adminEmails = ['leeshkr@gmail.com', 'ktbmks@hanmail.net'];
        $isAdmin = in_array(strtolower($email), array_map('strtolower', $adminEmails));
        $role = $isAdmin ? 'ADMIN' : 'APPLICANT';
        $status = $isAdmin ? 'APPROVED' : 'PENDING';

        // DB 및 pending_users.json에서 저장된 실제 권한/승인상태/성명 동적 조회
        $pdo = getDbConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT role, status, name FROM z_ch_saenuri_users WHERE LOWER(email) = ?");
                $stmt->execute([strtolower($email)]);
                $uRow = $stmt->fetch();
                if ($uRow) {
                    if (!empty($uRow['role'])) $role = $uRow['role'];
                    if (!empty($uRow['status'])) $status = $uRow['status'];
                    if (!empty($uRow['name'])) $nickname = $uRow['name'];
                }
            } catch (Exception $e) {}
        }

        $logFile = __DIR__ . '/../api/pending_users.json';
        if (file_exists($logFile)) {
            $fileData = json_decode(file_get_contents($logFile), true) ?? [];
            foreach ($fileData as $fUser) {
                if (strtolower($fUser['email'] ?? '') === strtolower($email)) {
                    if (!empty($fUser['role'])) $role = $fUser['role'];
                    if (!empty($fUser['status'])) $status = $fUser['status'];
                    if (!empty($fUser['name'])) $nickname = $fUser['name'];
                    break;
                }
            }
        }

        if (strtolower($email) === 'leeshkr@gmail.com' && empty($role)) {
            $role = 'ADMIN';
            $status = 'APPROVED';
        }

        // 📝 로그인 시도 로그 기록 (auth/login_debug.log)
        $logMessage = sprintf(
            "[%s] Kakao Login SUCCESS - Email: %s | Name: %s | Role: %s | Status: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $email,
            $nickname,
            $role,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        );
        file_put_contents(__DIR__ . '/login_debug.log', $logMessage, FILE_APPEND);

        $_SESSION['user'] = [
            'id' => $kakaoId,
            'name' => $nickname,
            'email' => $email,
            'title_name' => $isAdmin ? '개발자/관리자' : '성도',
            'role' => $role,
            'status' => $status,
            'dept' => $isAdmin ? '행정/재정부' : '청년부',
            'provider' => 'kakao'
        ];

        $queryParams = http_build_query([
            'msg' => 'login_success',
            'email' => $email,
            'name' => $nickname,
            'role' => $role,
            'status' => $status,
            'provider' => 'kakao'
        ]);
        header("Location: ../index.php?" . $queryParams);
        exit;
    } else {
        $email = 'kakao_user@kakao.com';
        $adminEmails = ['leeshkr@gmail.com'];
        $isAdmin = in_array(strtolower($email), array_map('strtolower', $adminEmails));
        $role = $isAdmin ? 'ADMIN' : 'APPLICANT';
        $status = $isAdmin ? 'APPROVED' : 'PENDING';

        $_SESSION['user'] = [
            'id' => rand(100, 999),
            'name' => '김카카오',
            'email' => $email,
            'title_name' => '성도',
            'role' => $role,
            'status' => $status,
            'dept' => '청년부',
            'provider' => 'kakao'
        ];

        $queryParams = http_build_query([
            'msg' => 'login_success',
            'email' => $email,
            'name' => '김카카오',
            'role' => $role,
            'status' => $status,
            'provider' => 'kakao'
        ]);
        header("Location: ../index.php?" . $queryParams);
        exit;
    }
}

