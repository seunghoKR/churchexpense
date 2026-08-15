<?php
/**
 * 구글 OAuth 2.0 소셜 로그인 모듈 (PHP 8.4)
 */
session_start();

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: ('644924037586-h2mttblsajsrb9egmvdjv21bfein0bsf' . '.apps.googleusercontent.com'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: ('GOCSPX-bA4xAQ3vKj6UvJ' . 'WrH8LGs9fdO_rr'));
define('GOOGLE_REDIRECT_URI', 'https://expense.sjsnr.kr/auth/google_login.php');

$code = $_GET['code'] ?? null;
$primaryEmailParam = $_GET['primary_email'] ?? null;
if ($primaryEmailParam) {
    $_SESSION['linking_primary_email'] = strtolower(trim($primaryEmailParam));
}

if (!$code) {
    $statePayload = json_encode(['primary_email' => $_SESSION['linking_primary_email'] ?? '']);
    // 1. 구글 인증 URL 생성 및 이동 (prompt=select_account 로 항상 계정 선택창 표출!)
    $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'state' => $statePayload
    ]);
    header("Location: " . $googleAuthUrl);
    exit;
} else {
    // 2. 인증 코드로 토큰 요청 및 사용자 정보 조회
    $tokenUrl = "https://oauth2.googleapis.com/token";
    $postParams = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? null;

    if ($accessToken) {
        $userInfoUrl = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $accessToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $userInfoResp = curl_exec($ch);
        curl_close($ch);

        $userInfo = json_decode($userInfoResp, true);
        $email = $userInfo['email'] ?? 'google_user@gmail.com';
        $name = $userInfo['name'] ?? '구글성도';
        $googleId = $userInfo['id'] ?? rand(1000, 9999);

        // 🔗 듀얼 소셜 연동 처리 (OAuth state 및 Session 지원)
        $stateData = json_decode($_GET['state'] ?? '{}', true);
        $linkFile = __DIR__ . '/../api/social_links.json';
        $links = file_exists($linkFile) ? (json_decode(file_get_contents($linkFile), true) ?? []) : [];

        $isGoogleMasterAdmin = (strtolower($email) === 'leeshkr@gmail.com');

        if ($isGoogleMasterAdmin) {
            // 최고 관리자 계정은 절대 다른 이메일로 매핑되거나 덮어써지지 않음!
            unset($_SESSION['linking_primary_email']);
            if (isset($links['leeshkr@gmail.com'])) {
                unset($links['leeshkr@gmail.com']);
                file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            $linkingPrimary = $_SESSION['linking_primary_email'] ?? $stateData['primary_email'] ?? null;
            if (!empty($linkingPrimary) && strtolower($linkingPrimary) !== strtolower($email)) {
                $links[strtolower($email)] = [
                    'primary_email' => strtolower($linkingPrimary),
                    'provider' => 'google',
                    'linked_at' => date('Y-m-d H:i:s')
                ];
                file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $email = strtolower($linkingPrimary);
                unset($_SESSION['linking_primary_email']);
            } elseif (!empty($links[strtolower($email)]['primary_email'])) {
                $email = strtolower($links[strtolower($email)]['primary_email']);
            }
        }

        require_once __DIR__ . '/../config/db.php';

        $isAdmin = (strtolower($email) === 'leeshkr@gmail.com');
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
                    if (!$isAdmin && !empty($uRow['role'])) $role = $uRow['role'];
                    if (!$isAdmin && !empty($uRow['status'])) $status = $uRow['status'];
                    if (!empty($uRow['name'])) $name = $uRow['name'];
                }
            } catch (Exception $e) {}
        }

        $logFile = __DIR__ . '/../api/pending_users.json';
        if (file_exists($logFile)) {
            $fileData = json_decode(file_get_contents($logFile), true) ?? [];
            foreach ($fileData as $fUser) {
                if (strtolower($fUser['email'] ?? '') === strtolower($email)) {
                    if (!$isAdmin && !empty($fUser['role'])) $role = $fUser['role'];
                    if (!$isAdmin && !empty($fUser['status'])) $status = $fUser['status'];
                    if (!empty($fUser['name'])) $name = $fUser['name'];
                    break;
                }
            }
        }

        if ($isAdmin) {
            $role = 'ADMIN';
            $status = 'APPROVED';
        }

        // 📝 로그인 시도 로그 기록 (auth/login_debug.log)
        $logMessage = sprintf(
            "[%s] Google Login SUCCESS - Email: %s | Name: %s | Role: %s | Status: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $email,
            $name,
            $role,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        );
        file_put_contents(__DIR__ . '/login_debug.log', $logMessage, FILE_APPEND);

        $_SESSION['user'] = [
            'id' => $googleId,
            'name' => $name,
            'email' => $email,
            'title_name' => $isAdmin ? '개발자/관리자' : '성도',
            'role' => $role,
            'status' => $status,
            'dept' => $isAdmin ? '행정/재정부' : '청년부',
            'provider' => 'google'
        ];

        $queryParams = http_build_query([
            'msg' => 'login_success',
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'status' => $status,
            'provider' => 'google'
        ]);
        header("Location: ../index.php?" . $queryParams);
        exit;
    } else {
        $email = 'google_user@gmail.com';
        $adminEmails = ['leeshkr@gmail.com'];
        $isAdmin = in_array(strtolower($email), array_map('strtolower', $adminEmails));
        $role = $isAdmin ? 'ADMIN' : 'APPLICANT';
        $status = $isAdmin ? 'APPROVED' : 'PENDING';

        $_SESSION['user'] = [
            'id' => rand(100, 999),
            'name' => '김구글',
            'email' => $email,
            'title_name' => '성도',
            'role' => $role,
            'status' => $status,
            'dept' => '청년부',
            'provider' => 'google'
        ];

        $queryParams = http_build_query([
            'msg' => 'login_success',
            'email' => $email,
            'name' => '김구글',
            'role' => $role,
            'status' => $status,
            'provider' => 'google'
        ]);
        header("Location: ../index.html?" . $queryParams);
        exit;
    }
}
