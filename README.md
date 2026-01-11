# WP Smart Bridge

제휴 마케팅용 단축 링크 자동화 플러그인 - HMAC-SHA256 보안 인증, 분석 기능 포함

## 설명

WP Smart Bridge는 제휴 마케팅을 위한 단축 링크 자동화 플러그인입니다. EXE 프로그램과 연동하여 제휴 링크를 자동으로 단축 링크로 변환하고, 클릭 통계와 분석 기능을 제공합니다.

### 주요 기능

- **보안 인증**: HMAC-SHA256 기반 API 인증 시스템
- **자동 링크 생성**: EXE 프로그램과 연동하여 제휴 링크 자동 단축
- **클릭 분석**: 일별/시간대별/플랫폼별 통계 제공
- **그룹 관리**: 링크를 그룹으로 분류하여 관리
- **백업/복원**: 데이터 백업 및 복원 기능
- **커스텀 템플릿**: 리다이렉션 페이지 디자인 커스터마이징

## 요구 사항

- WordPress 5.0 이상
- PHP 7.4 이상
- MySQL 5.6 이상 또는 MariaDB 10.1 이상

## 설치 방법

### 자동 설치

1. WordPress 관리자 페이지에서 [플러그인] > [새로 추가]로 이동
2. "WP Smart Bridge"를 검색
3. [지금 설치] 버튼 클릭
4. 설치 완료 후 [활성화] 버튼 클릭

### 수동 설치

1. [GitHub Releases](https://github.com/routinefactory/wp-smart-bridge/releases)에서 최신 버전 다운로드
2. 압축 파일을 해제
3. `wp-smart-bridge` 폴더를 WordPress 설치 경로의 `wp-content/plugins/`에 업로드
4. WordPress 관리자 페이지에서 플러그인 활성화

## 사용 방법

### 1. API 키 발급

1. WordPress 관리자 페이지에서 [Smart Bridge] > [설정]으로 이동
2. [새 API 키 발급] 버튼 클릭
3. 발급된 API Key와 Secret Key를 안전한 곳에 저장
   - **주의**: Secret Key는 다시 확인할 수 없습니다.

### 2. EXE 프로그램 연동

1. EXE 프로그램의 설정에서 다음 정보를 입력:
   - API Key: 발급받은 공개 키 (sb_live_xxx)
   - Secret Key: 발급받은 비밀 키 (sk_secret_xxx)
2. EXE 프로그램에서 제휴 링크 생성 시 자동으로 단축 링크로 변환됩니다.

### 3. 링크 관리

- WordPress 관리자 페이지에서 [Smart Bridge] > [대시보드]로 이동
- 생성된 링크 목록 확인
- 클릭 통계 및 분석 데이터 확인

### 4. 설정 커스터마이징

- **리다이렉션 딜레이**: 리다이렉션 대기 시간 설정 (초 단위)
- **커스텀 템플릿**: 리다이렉션 페이지 HTML/CSS 편집

## 화면 캡처

### 대시보드
- 일별 트래픽 추세 차트
- 시간대별 클릭 통계
- 플랫폼별 점유율
- 상위 유입 경로

### 설정 페이지
- API 키 관리
- 일반 설정
- 커스텀 리다이렉션 템플릿
- 백업 및 복원

## 자주 묻는 질문

### **Q: WordPress 관리자 페이지에서 링크를 직접 생성할 수 있나요?**
A: 아니요. 링크 생성은 반드시 EXE 프로그램을 통해서만 가능합니다. WordPress 관리자 페이지에서는 링크 관리와 통계 확인만 가능합니다.

### **Q: Secret Key를 잃어버리면 어떻게 하나요?**
A: Secret Key는 다시 확인할 수 없습니다. 새로운 API 키를 발급받아야 합니다.

### **Q: 데이터는 얼마나 보관되나요?**
A: 클릭 로그는 무기한 보관됩니다. 필요한 경우 백업 기능을 사용하여 데이터를 보관하세요.

### **Q: 플러그인을 비활성화하면 데이터가 삭제되나요?**
A: 아니요. 비활성화해도 데이터는 보존됩니다. 데이터를 삭제하려면 [설정] 페이지의 [Factory Reset] 기능을 사용하세요.

## 개발

### 개발 환경 설정

```bash
# 리포지토리 클론
git clone https://github.com/routinefactory/wp-smart-bridge.git

# WordPress 로컬 환경 설정
# wp-smart-bridge 폴더를 wp-content/plugins/에 복사
```

### 테스트 실행

```bash
# 플랫폼 감지 테스트
php tests/test_platform.php

# 비동기 로깅 시뮬레이션
php tests/simulation_async_logging.php
```

## 변경 로그

### 4.3.1 (2026-01-11)
- 버전 불일치 문제 해결
- Text Domain 통일
- 언어 파일 버전 업데이트

### 4.3.0
- 비동기 로깅 시스템 개선
- 대시보드 필터 기능 강화

### 4.2.0
- 파라미터 방식 리다이렉션 도입 (?go=slug)
- Rewrite Rules 제거로 성능 개선

### 4.0.0
- REST API 기반 아키텍처로 전면 개편
- HMAC-SHA256 보안 인증 도입
- 그룹 관리 기능 추가

전체 변경 로그은 [CHANGELOG.md](CHANGELOG.md)를 참조하세요.

## 기여

이 프로젝트에 기여하고 싶으시다면 다음 단계를 따르세요:

1. Fork 하기
2. 기능 브랜치 생성 (`git checkout -b feature/AmazingFeature`)
3. 커밋 (`git commit -m 'Add some AmazingFeature'`)
4. 브랜치 푸시 (`git push origin feature/AmazingFeature`)
5. Pull Request 생성

## 라이선스

이 플러그인은 GPL v2 이상 라이선스 하에 배포됩니다.

## 지원

- **GitHub Issues**: [버그 신고 및 기능 요청](https://github.com/routinefactory/wp-smart-bridge/issues)
- **이메일**: support@routinefactory.com

## 크레딧

- 개발: [Routine Factory](https://github.com/routinefactory)
- 라이선스: GPL v2 이상

## 링크

- [GitHub 저장소](https://github.com/routinefactory/wp-smart-bridge)
- [WordPress.org](https://wordpress.org/plugins/wp-smart-bridge/)
