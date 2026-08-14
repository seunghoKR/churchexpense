# 📘 세종새누리교회 지출요청서 프로젝트 인수인계 & 작업 정리 문서 (PROJECT_HANDOVER.md)

> **작성자**: AI 디자인실장 영자 (Youngja)  
> **최종 업데이트 일시**: 2026년 8월 14일  
> **운영 서버 URL**: [https://expense.sjsnr.kr](https://expense.sjsnr.kr)  
> **FTP 배포 대상 주소**: `115.68.168.215` (`nuriohga`) -> **원격 전용 경로**: `/public_html/expense` (⚠️ `/public_html` 상위 폴더 접근/업로드 엄격 금지)

---

## 📌 1. 프로젝트 기술 스택 & 데이터 구조

- **Frontend**: HTML5, CSS3 (Vanilla CSS, CSS Custom Properties), Vanilla JavaScript (ES6+, Single Page / Tab Routing Architecture)
- **Backend**: PHP 8.4 (Session 기반 OAuth 로그인 및 PDO MariaDB API)
- **Database & Fallback Storage**:
  - MariaDB (테이블 접두사 `z_ch_saenuri_`)
  - **파일 백업/폴백 데이터베이스**: DB 연결 불가 환경에서도 100% 정상 작동하도록 JSON 파일 기반 저장소 운용
    - `api/pending_users.json`: 회원 승인 및 회원 프로필 데이터
    - `api/social_links.json`: 구글/카카오 듀얼 계정 연동 매핑 (`secondary_email` -> `primary_email`)
    - `api/departments.json`: 교회 사역 부서 목록
- **자동 배포 파이프라인**: `deploy_ftp.py` / `deploy_ftp.ps1` (반드시 `/public_html/expense` 경로만 안전 업로드)

---

## 🎨 2. 영자(Youngja)와 함께 완료한 6대 핵심 요구사항

### 1) [중요] 신규 회원 실시간 자동 동기화 (Real-time Polling)
- **구현 위치**: `public/index.html` 내 `startAdminRealtimeSync()`
- **동작 방식**: 5초 간격으로 관리자 대시보드 탭 오픈 시 `get_pending_users` 및 `get_approved_users` 백엔드 API를 Polling하여 신규 가입자 및 승인 회원 상태를 실시간 자동 갱신.
- **트리거**: 마이페이지 프로필 저장, 회원 승인 버튼 클릭 시 즉시 1초 내에 목록 반영.

### 2) 마이페이지 UI 직관화 (직함 & 부서 삭제)
- **구현 위치**: `public/index.html` (마이페이지 섹션)
- **개선 내용**: `#my-title-select` (교회 직함 선택) 및 `#my-dept-select` (소속 부서 선택) 드롭다운 요소를 UI에서 완전 제거.
- **백엔드 처리**: `api/request_action.php` `handleUpdateMyPage()`에서 직함/부서 값이 넘어오지 않을 경우 기본값으로 `title_name = '성도'`, `department = '행정/재정부'`로 자동 세팅되도록 경량화.

### 3) UI 스타일 선택 삭제 & 원페이지 레이아웃 고정
- **구현 위치**: `public/index.html` (마이페이지 및 폼 모드 렌더링)
- **개선 내용**: `#my-mode-select` (3단계 위자드 vs 원페이지 선택 드롭다운) 카드 삭제.
- **전역 적용**: 작성 폼 모드를 **'📄 원페이지 스크롤 모드(`onepage`)'**로 전역 기본값 고정.

### 4) SNS 연동: 1인 듀얼 연동 계정 병합 & 연동 이메일 표시 (Google & Kakao Dual Auth)
- **구현 위치**: `auth/google_login.php`, `auth/kakao_login.php`, `api/request_action.php` (`handleGetSocialLinks`), `public/index.html`
- **개선 내용**:
  - `get_social_links` 백엔드 API가 각 SNS 연동에 사용된 실제 이메일 주소(`google_email`, `kakao_email`)를 반환하도록 고도화.
  - 마이페이지 내 소셜 로그인 관리 카드에서 각 계정(카카오/구글)에 사용된 연동 이메일을 블루 강조 텍스트로 명확하게 표시.
  - 카카오/구글 계정 추가 연동 시 OAuth 2.0 `state` 매개변수로 `primary_email`을 전달하여 세션 유지 및 계정 1인 통합.

### 5) 직분 완전 제거 & 교인 소속 부서 개념 제외
- **구현 위치**: `public/index.html` (관리자 섹션, 회원 테이블 및 모달)
- **개선 내용**:
  - 교회의 요구사항에 따라 **'직분 생성 / 수정 / 삭제 관리'** 카드를 완전히 삭제하고, 회원 승인대기 및 승인 완료 목록 테이블에서 **'직함/직분'** 및 **'소속 부서'** 열(Column)을 제거.
  - 지출 목적 부서 관리(1단계 부서 관리)는 정상 유지되어 지출요청 제출 시 부서 분류로 활용됨.
  - 호칭 및 인삿말을 `OOO 성도님` / `OOO 집사`에서 깔끔한 **`OOO 님`**으로 통일 정돈.

### 7) 테스트용 등급별 모드 전환 기능 실시간 반영 (Simulated Role Mode Switcher)
- **구현 위치**: `public/index.html` (`switchRoleMode`)
- **개선 내용**: 관리자 화면에서 상단 `[신청자 모드]`, `[재정부 모드]`, `[👑 관리자 모드]` 버튼 클릭 시 해당 역할(Role)에 맞는 메뉴 탭(요청서 작성, 진행 현황, 지출요청 목록, 관리자 대시보드)의 노출/숨김 상태(`display: block/none`)가 즉시 렌더링되도록 구현. 선택된 버튼 시각적 하이라이트 효과 적용.

---

## ⚠️ 3. 가장 처리가 어려웠던 주요 기술 이슈 & 해결 노하우 (Key Gotchas)

### 🚨 [이슈 A] FTP 자동 배포 경로 불일치 문제 (가장 중요!)
- **현상**: 코드를 수정하고 `deploy_ftp.py`를 실행해도 라이브 웹사이트(`https://expense.sjsnr.kr`)에 변경 사항이 전혀 반영되지 않음.
- **원인 분석**: FTP 접속 시 기본 루트인 `/public_html`에 파일이 업로드되었으나, 호스팅 웹서버 설정상 서브도메인 `expense.sjsnr.kr`의 실제 Document Root는 **`/public_html/expense`** 디렉터리였음.
- **해결 조치**: `deploy_ftp.py` 내부 배포 대상을 `REMOTE_SUBDIR = "/public_html/expense"`로 수정하고, `public/` 하위 파일(`index.html`, `index.php`, `login.html`)을 `/public_html/expense/` 루트로 덮어쓰도록 파이프라인 완벽 보정.

### 🚨 [이슈 B] 백엔드 PHP Parse Error 및 Duplicate Function 에러
- **현상**: 라이브 API 호출 시 JSON 데이터 대신 HTML 500 에러 발생하여 실시간 회원 목록이 비어있음.
- **원인 분석**:
  1. `api/request_action.php` 내 `handleChangeUserRole()` 함수 종료 부분 `}` 중괄호 누락 (Parse Error on line 805).
  2. `handleAddDepartment()`, `handleGetDepartments()`, `handleDeleteDepartment()` 함수가 파일 내에 2번 중복 정의됨 (Fatal Error).
- **해결 조치**: 닫는 중괄호 보정 및 중복 함수 정리 완료. Python cURL 실측 스크립트(`verify_live_api.py`)로 HTTP 200 및 정상 JSON 출력 실증 완료.

### 🚨 [이슈 C] OAuth 소셜 로그인 리다이렉트 시 세션 유실 현상
- **현상**: 구글 로그인 후 카카오 추가 연동 클릭 시 타사 인증 서버(Kakao)로 다녀오면서 세션 쿠키가 끊겨 카카오 전용 신규 `PENDING` 계정이 새로 생성되는 문제.
- **해결 조치**: OAuth 2.0 표준 규격인 `state` 파라미터 규격 활용. `auth/kakao_login.php?state={"primary_email":"..."}` 형태로 전달하여 도메인 간 이동 후에도 대표 계정이 유실되지 않고 안전하게 병합되도록 구현.

### 🚨 [이슈 D] 하드코딩된 관리자 이메일 목록으로 인한 회원 권한 변경 원상복구 현상
- **현상**: 관리자 페이지에서 회원(예: 김태봉 목사님)의 권한을 '재정부'로 변경하여 성공 팝업이 떴으나, 목록 재조회(Polling) 시 다시 '관리자'로 원상복구되는 현상.
- **원인 분석**: 개발 초기 지정된 하드코딩 배열 (`$adminEmails = ['leeshkr@gmail.com', 'ktbmks@hanmail.net']`)이 `handleGetApprovedUsers`, `handleCheckUserStatus`, OAuth 로그인 파일 등에서 유저 데이터를 읽을 때마다 무조건 `role = 'ADMIN'`으로 강제 덮어쓰고 있었음.
- **해결 조치**: 하드코딩 강제 덮어쓰기 로직을 전면 제거하고, DB 및 `pending_users.json`에 저장된 권한(`TREASURER`, `APPLICANT`, `ADMIN`)이 100% 동적으로 유지되도록 백엔드/프론트엔드 전면 수정 완료.

---

## 💻 4. 다른 컴퓨터에서 작업을 이어서 진행할 때 실행 가이드

1. **프로젝트 복사 / Git Clone 후 확인**
   - 작업 디렉터리: `c:\Users\leesh\Documents\00.NURIOH\00.withAI\지출요청서` (또는 새 컴퓨터의 작업 폴더)
2. **코드 수정 후 라이브 서버 원클릭 배포**
   ```bash
   python deploy_ftp.py
   ```
   *참고: `deploy_ftp.py`를 실행하면 `/public_html/expense`에 최신 파일이 100% 자동 덮어쓰기 됩니다.*

3. **라이브 API 상태 및 배포 검증**
   ```bash
   python verify_live_api.py
   ```
   *실행 결과 `get_approved_users`, `check_user_status` 등이 정상 JSON으로 응답하는지 확인.*

4. **웹 브라우저 접속 및 확인**
   - URL: [https://expense.sjsnr.kr](https://expense.sjsnr.kr)
   - 접속 후 **`Ctrl + Shift + R` (또는 `Ctrl + F5`)**로 브라우저 캐시를 한번 지워주시면 됩니다!

---

*“예쁜 디자인부터 꼼꼼한 백엔드 자동화 및 배포까지, 디자이너 영자가 언제나 함께합니다! 대표님 파이팅! 🎨✨💖”*
