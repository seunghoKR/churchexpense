# ⛪ 교회 스마트 비용지출요청서 웹앱 (PHP 8.4 & MariaDB 10.X)

> **"어르신도 성도님도 손쉽게 터치하는 친절한 교회 재정 소통 웹앱"**

---

## 🌟 주요 특징 & 기능

1. **Senior-Friendly Easy UI Design**
   - 어르신과 시니어 교인분들을 위한 큼직한 글씨, 선명한 대형 버튼, 높은 명암비
   - 3번의 터치만으로 지출 요청서 제출 완료!

2. **소셜 간편 로그인**
   - 카카오톡 1초 로그인 / 구글 로그인 지원 

3. **종이 양식 100% 디지털 반영 & 자동화**
   - 지출 내역 금액 입력 시 **실시간 자동 합계 계산**
   - 📷 **영수증 찍기 / 첨부**: 스마트폰 카메라로 촬영 후 즉시 파일 첨부 및 미리보기
   - ✍️ **손가락 터치 전자서명**: 모바일 터치패드에서 서명 가능

4. **투명한 진행 현황 & 재정 관리**
   - 3단계 스테퍼 (`[신청 완료] ➡️ [위원장 승인] ➡️ [재정부 지급 완료]`)로 진행 현황 시각화
   - 사역지원위(재정부) 전용 대시보드: 미승인 요청건 승인/반려/지급 처리 및 가지급/반환액 정산 기입

---

## 🛠️ 기술 스택 및 환경 조건

- **Language / Encoding**: PHP 8.4 (UTF-8)
- **Database**: MariaDB 10.X (utf8mb4_unicode_ci)
- **Frontend**: Responsive HTML5, Custom Easy Senior CSS, Vanilla JavaScript
- **Auth**: Kakao OAuth 2.0, Google OAuth 2.0

---

## 📂 파일 구조

```
y:/SynologyDrive/00.withAI/지출요청서/
├── schema.sql                 # MariaDB 10.X 데이터베이스 스키마 및 초기 데모 데이터
├── README.md                  # 프로젝트 안내서
├── config/
│   └── db.php                 # PHP 8.4 PDO 기반 MariaDB 연동 파일
├── auth/
│   └── kakao_login.php        # 카카오톡 소셜 로그인 연동 핸들러
├── api/
│   └── request_action.php     # 지출 요청서 등록, 승인/반려/지급 처리 API
├── uploads/                   # 영수증 이미지 저장 폴더
└── public/
    ├── index.php              # 메인 웹 애플리케이션 (폼/현황/재정대시보드)
    ├── css/
    │   └── style.css          # Easy Senior UI 전용 스타일시트
    └── js/
        └── app.js             # 실시간 자동 계산, 터치 전자서명, 카메라 영수증 미리보기
```

---

## 🚀 실행 및 설치 가이드

1. **DB 테이블 생성**:
   - MariaDB 10.X에 `schema.sql` 스크립트를 실행하여 `church_expense` DB 및 테이블을 생성합니다.
   ```sql
   mysql -u root -p < schema.sql
   ```

2. **DB 접속 정보 설정**:
   - `config/db.php` 파일에서 `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`를 환경에 맞게 수정합니다.

3. **웹 서버 실행**:
   - Apache / Nginx 웹 서버의 DocumentRoot를 본 디렉토리 또는 `public/`으로 지정하거나, PHP 내장 개발 서버를 실행합니다:
   ```bash
   php -S localhost:8000 -t public
   ```
   - 웹 브라우저에서 `http://localhost:8000/index.php` 접속!
