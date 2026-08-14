# ⛪ 세종새누리교회 스마트 비용지출요청서 시스템 디자인 & 개발 가이드 (DESIGN.md)

> **"어르신도 시니어 성도님도 터치 한 번으로 쉽게 소통하는 친절한 온라인 재정 마당"**

---

## 🎨 1. Design System & Aesthetics (디자인 시스템)

### Primary Color Palette (주요 색상 체계)
- **Main Primary Green**: `#059669` (세종새누리교회 맑고 싱그러운 숲색)
- **Sub Primary Emerald**: `#10b981` (터치 강조 및 버튼 그라데이션)
- **Secondary Blue**: `#2563eb` (신청자 정보 및 링크 강조)
- **Accent Purple**: `#7c3aed` (관리자 전용 대시보드 테마)
- **Background Soft Light**: `#f4f7f4` ~ `#e8f5e9` (시니어 눈의 피로를 최소화하는 따뜻한 파스텔 배경)

### Senior Easy UI Typography & UX Rules (시니어 친화 UI 규칙)
1. **글씨 크기 및 시인성 최우선**:
   - 최소 본문 폰트 14px 이상, 버튼 및 주요 타이틀 16px~18px 대형 서체 적용.
2. **선명한 터치 영역 (Touch Targets)**:
   - 최소 버튼 높이 46px 이상 확보하여 스마트폰 터치 오작동 방지.
3. **높은 명암비 & 가독성**:
   - 옅은 회색 글씨를 지양하고 `#1e293b`, `#334155` 등 진한 대비 색상 적용.

---

## 🛠️ 2. Technical Stack & Environment (기술 스택)

- **Backend Language**: PHP 8.4 (UTF-8 인코딩)
- **Database**: MariaDB 10.X (utf8mb4_unicode_ci)
- **Frontend**: Custom Senior Easy CSS (`css/style.css`), Vanilla JavaScript (`js/app.js`)
- **Authentication**: Kakao OAuth 2.0, Google OAuth 2.0, Local Session Auth
- **Domain**: `https://expense.sjsnr.kr` (HTTPS SSL 보안 서버 적용)
- **FTP Auto Deploy Script**: `deploy_ftp.ps1` (PowerShell 자동 배포)

---

## 📂 3. File Structure & Role (파일 구조)

```
y:/SynologyDrive/00.withAI/지출요청서/
├── schema.sql                 # MariaDB 10.X 데이터베이스 스키마
├── README.md                  # 프로젝트 안내서
├── DESIGN.md                  # 디자인 시스템 및 시스템 가이드
├── install.php                # DB 테이블 7개 자동 생성 설치 스크립트
├── deploy_ftp.ps1             # 닷홈 호스팅 서버 자동 FTP 배포 파워셸 스크립트
├── config/
│   └── db.php                 # MariaDB PDO 연동 파일 (DB: nuriohga)
├── auth/
│   ├── kakao_login.php        # 카카오톡 OAuth 소셜 로그인 연동 모듈
│   └── google_login.php       # 구글 OAuth 소셜 로그인 연동 모듈
├── api/
│   └── request_action.php     # 지출요청 등록/승인/반려/지급 처리 API
├── public/
│   ├── index.php              # 메인 애플리케이션 (신청/현황/재정부/관리자/마이페이지)
│   ├── login.html             # 카카오톡/구글 소셜 로그인 관문 랜딩
│   ├── css/
│   │   └── style.css          # Easy Senior UI 전용 스타일시트
│   ├── js/
│   │   └── app.js             # 실시간 인터랙션 및 자동 계산 JS
│   └── images/
│       └── logo.png           # 세종새누리교회 캘리그라피 로고
└── uploads/                   # 영수증 증빙 이미지 저장소
```

---

## 🔑 4. Social Login API Key Summary (소셜 로그인 설정 정보)

### 카카오톡 간편 로그인
- **REST API Key**: `ce26064239879368e6adaaa9f396dc48`
- **Redirect URI**: `https://expense.sjsnr.kr/auth/kakao_login.php`

### 구글 간편 로그인
- **Client ID**: `644924037586-***` (OAuth 앱)
- **Client Secret**: `GOCSPX-***` (보안 설정)
- **Redirect URI**: `https://expense.sjsnr.kr/auth/google_login.php`

---

## 🚀 5. How to Work on Another PC (다른 컴퓨터에서 작업하는 방법)

1. **시놀로지 드라이브(Synology Drive) 이용 시**:
   - `y:\SynologyDrive\00.withAI\지출요청서` 폴더가 자동 동기화되므로 다른 PC에서 해당 폴더를 그대로 열어 작업하시면 됩니다.

2. **백업 ZIP 파일 활용 시**:
   - `지출요청서_backup_20260812.zip` 압축 해제 후 작업 진행.

3. **원클릭 배포 (수정 후 서버 반영)**:
   - 파일 수정 후 파워셸에서 아래 명령어 실행:
     ```powershell
     powershell -ExecutionPolicy Bypass -File .\deploy_ftp.ps1
     ```

---
*Created with 💖 by 영자 (AI UI/UX 디자이너 & 개발 파트너)*
