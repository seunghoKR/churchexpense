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
    // 1. 카카오 인가 코드 요청 URL 생성 및 이동 (이메일 및 메시지 스코프 포함)
    $kakaoAuthUrl = "https://kauth.kakao.com/oauth/authorize?client_id=" . KAKAO_REST_API_KEY . "&redirect_uri=" . urlencode(KAKAO_REDIRECT_URI) . "&response_type=code&scope=account_email,talk_message&state=" . urlencode($statePayload);
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
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = (int)($tokenData['expires_in'] ?? 21600);

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

        // 💾 카카오 토큰 영구 저장 (나에게 카톡 메시지 보내기용)
        $tokenFile = __DIR__ . '/../api/kakao_tokens.json';
        $tokens = file_exists($tokenFile) ? (json_decode(file_get_contents($tokenFile), true) ?? []) : [];
        $tokenRecord = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => time() + $expiresIn,
            'kakao_id' => $kakaoId,
            'nickname' => $nickname,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $tokens[strtolower($email)] = $tokenRecord;
        $tokens['kakao_' . $kakaoId] = $tokenRecord;

        // 🔗 듀얼 소셜 연동 처리 (OAuth state 및 Session 지원)
        $linkFile = __DIR__ . '/../api/social_links.json';
        $links = file_exists($linkFile) ? (json_decode(file_get_contents($linkFile), true) ?? []) : [];

        $isKakaoMasterAdmin = (strtolower($email) === 'leeshkr@gmail.com');

        if ($isKakaoMasterAdmin) {
            unset($_SESSION['linking_primary_email']);
            if (isset($links['leeshkr@gmail.com'])) {
                unset($links['leeshkr@gmail.com']);
                file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            $stateData = json_decode($_GET['state'] ?? '{}', true);
            $linkingPrimary = $_SESSION['linking_primary_email'] ?? $stateData['primary_email'] ?? null;
            if (!empty($linkingPrimary) && strtolower($linkingPrimary) !== strtolower($email) && strtolower($linkingPrimary) !== 'leeshkr@gmail.com') {
                $links[strtolower($email)] = [
                    'primary_email' => strtolower($linkingPrimary),
                    'provider' => 'kakao',
                    'linked_at' => date('Y-m-d H:i:s')
                ];
                file_put_contents($linkFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $email = strtolower($linkingPrimary);
                $tokens[strtolower($linkingPrimary)] = $tokenRecord; // 기본 이메일 키로도 토큰 매핑!
                unset($_SESSION['linking_primary_email']);
            } elseif (!empty($links[strtolower($email)]['primary_email'])) {
                $primaryEmailMapped = strtolower($links[strtolower($email)]['primary_email']);
                $tokens[$primaryEmailMapped] = $tokenRecord;
                $email = $primaryEmailMapped;
            }
        }
        file_put_contents($tokenFile, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
                    if (!empty($uRow['name'])) $nickname = $uRow['name'];
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
                    if (!empty($fUser['name'])) $nickname = $fUser['name'];
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
            "[%s] Kakao Login SUCCESS - Email: %s | Name: %s | Role: %s | Status: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $email,
            $nickname,
            $role,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        );
        file_put_contents(__DIR__ . '/login_debug.log', $logMessage, FILE_APPEND);

        // 🌿 신규/미승인(PENDING) 성도 로그인 시 카카오톡 가입 환영 및 승인 대기 / 마이페이지 확인 요청 메시지 즉시 발송!
        if ($status === 'PENDING' && !empty($accessToken)) {
            $displayName = trim(str_replace(['성도', '성도님'], '', $nickname));
            if (empty($displayName) || $displayName === '카카오') $displayName = '성도';
            $callName = ($displayName === '성도' ? '성도님' : $displayName . ' 성도님');

            $welcomeTitle = "🌿 [세종새누리교회] 회원가입을 환영합니다!";
            $welcomeDesc = sprintf(
                "%s, 주님의 이름으로 환영합니다! ✨\n\n교회 스마트 비용지출요청시스템에 성공적으로 등록되었습니다.\n\n⏳ 현재 성도님의 계정은 [관리자 승인 대기] 상태입니다.\n관리자의 교인 확인 및 승인이 완료되면 지출요청서 작성이 활성화되며 카카오톡으로 승인 완료 알림을 보내드립니다.\n\n📌 [필수 확인 요청]\n승인을 기다리시는 동안 [마이페이지]에 접속하셔서 성함과 환급받으실 계좌 정보가 올바르게 입력되어 있는지 꼭 확인 및 저장해 주시기를 부탁드립니다. 💳\n\n• 가입 계정: %s\n• 상태: 승인 대기 중 (마이페이지 설정 가능)",
                $callName,
                $email
            );

            // 카카오톡 '나에게 보내기' API 즉시 발송
            $templateObject = [
                'object_type' => 'text',
                'text' => $welcomeTitle . "\n\n" . $welcomeDesc,
                'link' => [
                    'web_url' => 'https://expense.sjsnr.kr/',
                    'mobile_web_url' => 'https://expense.sjsnr.kr/'
                ],
                'button_title' => '스마트 지출요청서 열기'
            ];

            $chMsg = curl_init();
            curl_setopt($chMsg, CURLOPT_URL, "https://kapi.kakao.com/v2/api/talk/memo/default/send");
            curl_setopt($chMsg, CURLOPT_POST, true);
            curl_setopt($chMsg, CURLOPT_POSTFIELDS, http_build_query([
                'template_object' => json_encode($templateObject, JSON_UNESCAPED_UNICODE)
            ]));
            curl_setopt($chMsg, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chMsg, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/x-www-form-urlencoded;charset=utf-8'
            ]);
            curl_setopt($chMsg, CURLOPT_SSL_VERIFYPEER, false);
            $msgResp = curl_exec($chMsg);
            curl_close($chMsg);

            file_put_contents(__DIR__ . '/login_debug.log', sprintf("[%s] Kakao Welcome Msg to %s: %s\n", date('Y-m-d H:i:s'), $email, $msgResp), FILE_APPEND);
        }

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

