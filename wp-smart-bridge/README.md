# WP Smart Bridge

제휴 마케팅용 단축 링크 자동화 플러그인 - HMAC-SHA256 보안 인증, 분석 기능 포함

## ✨ 주요 기능

- 🔐 **보안 API**: HMAC-SHA256 서명 + 60초 타임스탬프 검증
- 🔗 **단축 링크**: 파라미터 방식 (예: `?go=aB3xY9`)
- 📊 **분석 대시보드**: UV/PV, 증감률, 시간대별 히트맵, 플랫폼별 점유율
- 🏪 **플랫폼 자동 태깅**: 쿠팡, 알리익스프레스, 아마존, 테무 등
- 🛡️ **GDPR 준수**: IP SHA256 해싱

## 📦 설치 방법

1. [Releases](https://github.com/routinefactory/wp-smart-bridge/releases)에서 최신 버전 다운로드
2. 워드프레스 관리자 → 플러그인 → 새로 추가 → 플러그인 업로드
3. 활성화 후 **설정 → 퍼마링크 → 변경사항 저장** 클릭

## 🚀 빠른 시작

1. **API 키 발급**: Smart Bridge → 설정 → API 키 생성
2. **EXE 프로그램 설정**: 발급받은 API Key와 Secret Key 입력
3. **링크 생성**: EXE에서 타겟 URL 입력 → 단축 링크 자동 생성

## 🔒 보안

- User-Agent 검증 (`SB-Client/Win64-v2.0`)
- HMAC-SHA256 서명 검증
- 60초 타임스탬프 유효성 검사
- WordPress Nonce (대시보드 API)

## 📄 라이선스

GPL v2 or later
