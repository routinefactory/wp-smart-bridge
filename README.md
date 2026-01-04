# WP Smart Bridge

> 제휴 마케팅용 단축 링크 자동화 WordPress 플러그인

[![Version](https://img.shields.io/badge/version-2.6.4-blue.svg)](https://github.com/routinefactory/wp-smart-bridge/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)

---

## 🚀 주요 기능

### 보안
- **HMAC-SHA256 인증**: EXE 클라이언트 전용 API
- **Timestamp 검증**: Replay Attack 방어 (60초 타임윈도우)
- **IP 해싱**: GDPR 준수 (SHA-256 + Salt)

### 분석
- **고유 방문자(UV)**: IP 기반 중복 제거
- **시간대별 분석**: 0-23시 클릭 분포
- **플랫폼 자동 감지**: 22개 제휴 플랫폼 (Coupang, AliExpress 등)
- **실시간 대시보드**: Chart.js 시각화

### 업데이트
- **GitHub 기반 자동 업데이트**: WordPress 대시보드에서 원클릭 업데이트
- **데이터 보존**: 업데이트 시 기존 링크/분석 데이터 100% 보존
- **자동 DB 마이그레이션**: 버전 업그레이드 시 스키마 자동 변경

---

## 📦 설치 방법

### 1. 최신 버전 다운로드
[Release 페이지](https://github.com/routinefactory/wp-smart-bridge/releases)에서 `wp-smart-bridge.zip` 다운로드

### 2. WordPress 업로드
1. WordPress 관리자 → 플러그인 → 새로 추가 → 플러그인 업로드
2. ZIP 파일 선택 후 설치
3. 플러그인 활성화
4. **중요**: 설정 → 퍼마링크에서 "변경사항 저장" 클릭 (최초 1회 필수)

### 3. API 키 발급
1. Smart Bridge → 설정
2. "새 API 키 발급" 클릭
3. API Key와 Secret Key를 EXE 프로그램에 입력

---

## 🛠️ 기술 스택

| 항목 | 기술 |
|------|------|
| **Framework** | WordPress 5.0+ |
| **Language** | PHP 7.4+ |
| **Database** | MySQL 5.7+ |
| **Security** | HMAC-SHA256, Timestamp Validation |
| **Frontend** | Chart.js, Vanilla JavaScript |
| **Encoding** | Base62 (Slug 생성) |

---

## 📊 데이터베이스 구조

### wp_sb_analytics_logs
클릭 이벤트 상세 로그

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `link_id` | BIGINT | 링크 ID (wp_posts 참조) |
| `visitor_ip` | VARCHAR(64) | IP 주소 (SHA-256 해싱) |
| `platform` | VARCHAR(50) | 플랫폼 태그 |
| `visited_at` | DATETIME | 클릭 시간 |

### wp_sb_api_keys
API 인증 키 관리

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `api_key` | VARCHAR(100) | 공개 키 (sb_live_xxx) |
| `secret_key` | VARCHAR(100) | 비밀 키 (HMAC 서명용) |
| `status` | ENUM | active / inactive |

---

## 🔗 REST API 엔드포인트

### POST `/wp-json/sb/v1/links`
단축 링크 생성 (EXE 클라이언트 전용)

**Headers**:
```http
X-SB-API-KEY: sb_live_xxx
X-SB-TIMESTAMP: 1704380400
X-SB-SIGNATURE: abc123...
User-Agent: SB-Client/Win64-v2.0
```

**Body**:
```json
{
  "target_url": "https://example.com/product",
  "slug": "custom-slug"  // 선택
}
```

### GET `/wp-json/sb/v1/stats`
분석 데이터 조회

**Parameters**:
- `range`: today, yesterday, 7d, 30d, custom
- `platform_filter`: Coupang, AliExpress, etc.
- `start_date`, `end_date`: YYYY-MM-DD (custom 시)

---

## 📝 문서

- **[PRD](prd.md)**: 제품 요구사항 명세서 (개발자 전용, Git 미追적)
- **[Python Client Example](python_client_example.py)**: EXE 클라이언트 구현 예시 (Git 미추적)
- **[플러그인 README](wp-smart-bridge/README.md)**: WordPress 플러그인 상세 설명

---

## 🔐 보안 정책

### HMAC 서명 생성 (Python 예시)
```python
import hashlib
import hmac
import time
import json

api_key = "sb_live_xxx"
secret_key = "sk_secret_yyy"

body = json.dumps({"target_url": "https://example.com"})
timestamp = str(int(time.time()))
payload = body + timestamp

signature = hmac.new(
    secret_key.encode(),
    payload.encode(),
    hashlib.sha256
).hexdigest()

headers = {
    "X-SB-API-KEY": api_key,
    "X-SB-TIMESTAMP": timestamp,
    "X-SB-SIGNATURE": signature,
    "User-Agent": "SB-Client/Win64-v2.0"
}
```

### User-Agent 제한
- **허용**: `SB-Client/Win64-v2.0` (EXE만)
- **차단**: Postman, cURL, 브라우저 등

---

## 🚀 자동 업데이트

### 사용자 경험
1. GitHub에 새 릴리스 공개 (예: v2.6.5)
2. 12시간 후 WordPress가 자동으로 업데이트 감지
3. 대시보드에 "업데이트 가능" 알림 표시
4. "지금 업데이트" 클릭 → 자동 설치
5. **모든 링크/분석 데이터 보존됨**

### 개발자 릴리스 절차
```bash
# 1. 버전 확인
grep "Version:" wp-smart-bridge/wp-smart-bridge.php
# Version: 2.6.5

# 2. 태그 생성 및 푸시
git tag v2.6.5 -m "v2.6.5 - Production Ready with Complete Dashboard"
git push origin v2.6.5

# 3. GitHub Actions 자동 실행
# → ZIP 생성 → Release 공개 → 사용자 자동 알림
```

---

## 📈 로드맵

### v2.6.5 (현재)
- ✅ 대시보드 UI 완성
- ✅ 데이터 보존 시스템 강화
- ✅ .gitignore 최적화
- ✅ GitHub Actions 자동 릴리스

### v2.7.0 (예정)
- 📱 모바일 앱 연동
- 📧 Webhook 알림
- 🔗 Bulk 링크 생성
- 📤 CSV Export

---

## 🤝 기여

현재 비공개 프로젝트입니다.

---

## 📄 라이선스

Proprietary - All Rights Reserved  
© 2026 Routine Factory

---

## 💬 지원

- **이슈**: [GitHub Issues](https://github.com/routinefactory/wp-smart-bridge/issues)
- **이메일**: support@routinefactory.com
- **웹사이트**: [https://antigravity.kr](https://antigravity.kr)

---

**Made with ❤️ by Routine Factory**
