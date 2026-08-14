# 📝 세종새누리교회 비용지출요청 시스템 작업 기록 및 기능 상세 문서 (WORK_LOG.md)

> **작성자**: AI 디자인실장 영자 (Youngja)  
> **최종 작성 일시**: 2026년 8월 14일  
> **프로젝트 웹사이트**: [https://expense.sjsnr.kr](https://expense.sjsnr.kr)  
> **깃허브 저장소**: [seunghoKR/churchexpense](https://github.com/seunghoKR/churchexpense)

---

## 📌 1. 프로젝트 개요 & 시스템 기술 스택

- **시스템명**: 세종새누리교회 스마트 비용지출요청서 시스템
- **운영 환경**: PHP 8.4 + MariaDB 10.X (하이브리드 파일-DB 구조)
- **프론트엔드**: Vanilla HTML5, CSS3 (Custom Properties), Vanilla JavaScript (SPA Single Page Architecture)
- **인증 방식**: Google OAuth 2.0 & Kakao OAuth 2.0 (1인 듀얼 계정 병합 지원)
- **배포 방식**: Python `deploy_ftp.py` / PowerShell `deploy_ftp.ps1` (대상 경로: `/public_html/expense`)

---

## 🎨 2. 최근 작업 완수 내역 (2026년 8월 14일 상세 기록)

### 1) SNS 연동 이메일 정보 직관 표출 (Google & Kakao)
- **배경**: 소셜 로그인 연동 관리 카드에서 단순히 "카카오 듀얼 계정 연동됨" 문구만 표출되어 어느 이메일로 연동되었는지 확인이 어려움.
- **작업 내용**:
  - `api/request_action.php` 내 `handleGetSocialLinks()` API를 확장하여 듀얼 계정 매핑 데이터(`social_links.json` 및 DB)에서 구글 이메일(`google_email`)과 카카오 이메일(`kakao_email`)을 각각 분리 추출 및 응답하도록 구현.
  - `public/index.html` 내 소셜 로그인 카드 UI를 개편하여 연동된 실제 이메일 주소를 **선명한 블루 강조 텍스트**로 표출.

### 2) FTP 배포 경로 안전 검증 가드 구축 (`/public_html/expense` 전용)
- **배경**: 작업 완료 후 상위 폴더인 `/public_html` 루트에 파일이 오배포되어 라이브 서비스(`expense.sjsnr.kr`)에 변경사항이 미반영되는 현상 방지 요구.
- **작업 내용**:
  - `deploy_ftp.py`에 원격 경로 안전 검증(Safety Assertion) 로직 추가 (`if not remote_path.startswith('/public_html/expense'): raise ValueError(...)`).
  - `deploy_ftp.ps1` 배포 대상을 `/public_html/expense` 단일 경로로 통합하여 배포 안전성 100% 확보.

### 3) 카카오 OAuth 실제 이메일 수집 및 매핑 보정
- **배경**: 카카오 앱 로그인 시 카카오 회원 고유 ID 기반 식별 이메일(`kakao_5035521659@kakao.com`)이 표시되는 현상.
- **작업 내용**:
  - `auth/kakao_login.php` 인증 요청 URL에 `scope=account_email` 파라미터 추가.
  - 백엔드 연동 API(`handleGetSocialLinks`)에서 식별용 이메일 대신 사용자 실제 카카오 이메일(`leeshkr@kakao.com`)을 우선순위로 매핑해 UI에 표출되도록 고도화.

### 4) 회원 권한 변경 원상복구 버그 수정 (Dynamic Role Persistence)
- **배경**: 관리자 대시보드에서 회원(예: 김태봉 목사님)의 권한을 '재정부'로 변경하면 변경 성공 팝업이 뜨지만, 1초 후 실시간 목록 폴링(Polling) 시 다시 '관리자'로 원상복구되는 문제.
- **원인**: 개발 초기 지정된 하드코딩 배열(`$adminEmails = ['leeshkr@gmail.com', 'ktbmks@hanmail.net']`)이 `handleGetApprovedUsers`, `handleCheckUserStatus`, OAuth 로그인 로직에서 매번 `role = 'ADMIN'`으로 강제 덮어쓰고 있었음.
- **작업 내용**:
  - `api/request_action.php`, `auth/google_login.php`, `auth/kakao_login.php`, `public/index.html`에서 하드코딩 강제 덮어쓰기 로직 전면 삭제.
  - 관리자 UI에서 설정한 권한(`APPLICANT`, `TREASURER`, `ADMIN`)이 DB 및 `pending_users.json`에 영구히 저장되고 실시간 유지되도록 수정 완료.

### 5) 등급별 메뉴 탭 및 테스트용 모드 전환기 (Role Switcher) 엄격 권한 분리
- **배경**: 재정담당자로 로그인 시 관리자 전용 모드 전환 버튼이 노출되거나, 탭 메뉴가 과도하게 보이는 현상 방지 요구.
- **작업 내용**:
  - `public/index.html` 내 `switchRoleMode(targetMode)` 및 `switchRole(role)` 함수 전면 고도화.
  - **모드 전환 테스트 툴바 (`admin-test-mode-buttons`)**: 오직 사이트 관리자(`ADMIN`) 계정에만 표출되고 일반 재정담당자(`TREASURER`) 및 신청자(`APPLICANT`) 접속 시 100% 숨김 처리.
  - **재정담당자(`TREASURER`) 탭 규칙**: `📋 지출요청 목록` 및 `👤 마이페이지` 탭만 깔끔하게 노출하고, 기본 탭을 `📋 지출요청 목록`으로 자동 설정.
  - **신청자(`APPLICANT`) 탭 규칙**: `📄 요청서 작성`, `📋 진행 현황`, `👤 마이페이지` 탭 노출.
  - **사이트 관리자(`ADMIN`) 탭 규칙**: 전체 5개 탭 전면 노출 및 모드 테스트 툴바 사용 가능.

---

## 🛠️ 3. 핵심 시스템 기능 명세 (System Features)

1. **원페이지 스크롤 지출요청서 작성**:
   - 지출 목적(부서 선택), 사용 내역, 금액, 영수증 첨부, 환급 계좌 입력이 한 화면에서 이뤄지는 원페이지 폼 구조.
2. **실시간 회원 승인 & 대시보드 동기화**:
   - 5초 간격 실시간 Polling을 통해 신규 회원 가입 및 승인 상태가 1초 내로 갱신.
3. **1인 듀얼 소셜 로그인 계정 병합**:
   - 구글 계정과 카카오 계정을 동일 1인 교인 프로필로 병합 관리.
4. **역할 기반 탭 및 접근 권한 제어**:
   - `APPLICANT` (신청자): 지출요청 작성 및 본인 신청 내역 조회.
   - `TREASURER` (재정부): 지출요청 심사, 승인/반려 처리, 엑셀 다운로드.
   - `ADMIN` (사이트 관리자): 회원 승인/권한 관리, 사역 부서 생성/삭제, 테스트 모드 전환기 사용.

---

## 🚀 4. 배포 및 유지보수 가이드

```bash
# 1. 최신 코드 배포 (PowerShell)
powershell -ExecutionPolicy Bypass -File deploy_ftp.ps1

# 2. 깃허브 커밋 & 푸시
git add .
git commit -m "feat: SNS 이메일 표출, 회원 권한 동적 유지, 테스트 모드 전환기 및 안전 배포 파이프라인 완성"
git push origin main
```

---

*“영자와 함께 가꾼 깨끗하고 안전한 코드base입니다! 대표님 파이팅! 🎨✨💖”*
