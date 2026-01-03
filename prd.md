# WP Smart Bridge Plugin (Premium & Analytics)

## 워드프레스 기반 제휴 단축 링크 자동화 플러그인

**Product Requirements Document v2.5**

---

### 📋 문서 정보

- **작성일**: 2026년 01월 03일
- **최종 수정**: 2026년 01월 03일
- **버전**: 2.5 (Security Hardening against Sniffing)
- **문서 상태**: Final
- **핵심 변경사항**: HMAC 서명 및 타임스탬프 도입으로 패킷 재전송 공격(Replay Attack) 방지

---

## 1. 개요 (Executive Summary)

### 1.1 프로젝트 배경

본 문서는 워드프레스 환경에서 동작하는 **제휴 마케팅용 단축 링크 생성 플러그인**의 상세 기술 명세서입니다. 

### 1.2 v2.5의 주요 특징

**v2.5**에서는 외부 클라이언트(EXE 프로그램)를 통한 생성 독점권을 유지하되, 다음과 같은 보안 강화를 실현합니다:

- **패킷 스니핑 방어**: Postman, Python 등을 통한 API 우회 시도를 원천 차단
- **Replay Attack 방지**: HMAC 기반의 동적 서명 인증 시스템 도입
- **타임스탬프 검증**: 시간 제한이 걸린 요청만 허용하여 캡처된 패킷 무효화
- **고도화된 분석**: Base62 기반 Slug 생성 및 SaaS급 유입 분석 기능

### 1.3 기술 스택

- **Backend**: WordPress REST API, PHP 7.4+
- **Database**: MySQL 5.7+ (Custom Analytics Table)
- **Security**: HMAC-SHA256, Timestamp Validation
- **Encoding**: Base62 for Short URLs
- **Frontend**: WordPress Admin UI, Chart.js for Analytics

---

## 2. 프로젝트 목표 (Project Goals)

### 2.1 비즈니스 목표

#### Lock-in 전략 (강화됨)
- 단순 API Key 인증이 아닌, **시간 제한이 걸린 암호화 서명**을 통해 EXE 프로그램 외의 모든 접근 차단
- Postman, cURL, Python 스크립트 등을 통한 무단 사용 방지
- 패킷 캡처 도구로 API 엔드포인트를 알아내더라도 **60초 후 자동 무효화**

#### SaaS급 인사이트 제공
- 고유 방문자(UV) 추적 (IP/Cookie 기반 중복 제거)
- 플랫폼별 트래픽 분석 (쿠팡, 알리익스프레스, 아마존 등)
- 시간대별 히트맵 (0~24시 클릭 패턴 분석)
- 전일 대비 증감률 (Week over Week, Day over Day)

#### 유연한 유지보수
- 구독 여부와 관계없이 생성된 링크의 **타겟 URL 수정 가능**
- **단축 Slug는 생성 후 영구 불변**으로 설정하여 링크 무결성 유지
- 품절, 링크 변경 등의 상황에서도 기존 단축 URL 재사용 가능

### 2.2 기술 목표

- WordPress 표준 권한 체계(RBAC)와 통합
- GDPR 준수를 위한 IP 해싱 저장
- 60초 이내 Replay Attack 방지
- 초당 100+ 리다이렉션 처리 가능한 경량 아키텍처

---

## 3. 관리자 인터페이스 (Admin Dashboard & Analytics)

### 3.1 통합 대시보드 (Main Dashboard)

기존의 단순 리스트 뷰를 대체하여, **접속 시 최상단에 그래프 기반의 분석 데이터**를 먼저 표시합니다.

#### 3.1.1 요약 위젯 (Summary Cards)

대시보드 상단에 4개의 카드 형태로 핵심 지표 표시:

| 위젯 | 설명 | 데이터 소스 |
|------|------|------------|
| **총 방문수** | 전체 클릭 수 (중복 포함) | `wp_sb_analytics_logs` 테이블 COUNT |
| **일일 고유 방문자** | IP/Cookie 기반 중복 제거 수치 | DISTINCT visitor_ip WHERE DATE(visited_at) = TODAY |
| **전일 대비 증감률** | 어제 vs 오늘 비교 (%) | (오늘 - 어제) / 어제 × 100 |
| **활성 링크 수** | 생성된 총 단축 링크 개수 | `wp_posts` WHERE post_type='sb_link' AND post_status='publish' |

#### 3.1.2 시각화 차트 (Visualization Charts)

**① 트래픽 추세선 (Traffic Trend)**
- **차트 유형**: Line Chart (라인 그래프)
- **기간**: 최근 30일간 일별 방문자 추이
- **X축**: 날짜 (예: 5/1, 5/2, ..., 5/30)
- **Y축**: 클릭 수
- **구현**: Chart.js의 `Line` 컴포넌트 사용

**② 시간대별 히트맵 (Hourly Heatmap)**
- **차트 유형**: Bar Chart (막대 그래프)
- **X축**: 시간대 (0시 ~ 23시)
- **Y축**: 클릭 수
- **용도**: 가장 클릭이 많이 발생하는 황금 시간대 분석
- **예시**: 오후 8시~10시에 트래픽 집중 → 해당 시간대 콘텐츠 발행 전략

**③ 플랫폼 점유율 (Platform Share)**
- **차트 유형**: Pie Chart (파이 차트)
- **데이터 소스**: 타겟 URL 도메인 기반 자동 분류
- **분류 예시**:
  - 쿠팡: 60%
  - 알리익스프레스: 25%
  - 아마존: 10%
  - 기타: 5%
- **활용**: 어느 제휴 플랫폼이 실적이 좋은지 한눈에 파악

#### 3.1.3 필터링 기능 (Advanced Filters)

사용자가 원하는 조건으로 데이터를 필터링:

**날짜 범위 필터**
- 오늘
- 어제
- 이번 주 (월~일)
- 이번 달
- 사용자 지정 (Custom Date Range Picker)

**제휴 플랫폼별 필터**
- 전체 보기
- 쿠팡 링크만 보기
- 알리익스프레스 링크만 보기
- 아마존 링크만 보기
- 기타

**구현 방식**
```php
// AJAX 호출 시 파라미터 전달
GET /wp-json/sb/v1/stats?range=30d&platform=coupang
```

### 3.2 링크 관리 및 수정 (Link Management)

#### 3.2.1 리스트 뷰 (List View)

관리자가 생성된 모든 링크를 테이블 형태로 확인:

| 컬럼명 | 설명 | 예시 |
|--------|------|------|
| **Slug** | 단축 주소 (예: yourdomain.com/go/**AbCd12**) | `AbCd12` |
| **원본 URL** | 리다이렉션될 최종 목적지 | `https://coupa.ng/cfvjXy` |
| **플랫폼** | 자동 분류된 제휴사 태그 | `Coupang` (자동 태깅) |
| **총 클릭 수** | 누적 클릭 수 (실시간 업데이트) | `1,234` |
| **생성일** | 링크 최초 생성 시각 | `2024-05-20 14:32` |
| **액션** | [수정] [삭제] 버튼 | - |

**정렬 기능**
- 클릭 수 내림차순 (인기 링크 우선)
- 생성일 최신순
- Slug 알파벳순

#### 3.2.2 수정 페이지 (Edit Screen)

개별 링크 클릭 시 나타나는 편집 화면:

**① Slug (단축 주소)**
- **상태**: `[읽기 전용 / 비활성화]`
- **이유**: 생성 후 **절대 변경 불가**
- **UI 처리**: `<input type="text" disabled readonly value="AbCd12">`
- **설명 문구**: "단축 주소는 생성 후 변경할 수 없습니다. 링크 무결성을 위해 영구적으로 고정됩니다."

**② Target URL (타겟 URL)**
- **상태**: `[수정 가능]`
- **용도**: 품절, 링크 변경 등의 상황에서 목적지 URL 교체
- **예시 시나리오**:
  - 기존: `https://coupa.ng/old-product`
  - 수정 후: `https://coupa.ng/new-product`
- **검증**: URL 형식 유효성 검사 (http:// 또는 https:// 필수)

**③ Loading Message (로딩 메시지)**
- **상태**: `[수정 가능]`
- **기본값**: "잠시만 기다려주세요..."
- **용도**: 리다이렉션 중 사용자에게 보여줄 커스텀 메시지
- **HTML 허용**: 간단한 스타일링 가능 (`<strong>`, `<em>` 등)

**④ [저장] 버튼**
- **권한**: `edit_posts` 이상 (구독 만료자도 접근 가능)
- **동작**: Target URL 및 Loading Message만 저장
- **알림**: "링크가 성공적으로 업데이트되었습니다."

**⑤ [새로 만들기] 버튼**
- **상태**: `[제거됨 / 숨김 처리]`
- **이유**: UI 내 생성 원천 차단 (EXE 전용)
- **구현**: `display: none;` 또는 권한 기반 조건부 렌더링

---

## 4. 보안 및 권한 관리 (Security & Access Control)

### 4.1 권한 분리 정책 (RBAC Matrix)

워드프레스의 Capability 시스템을 세밀하게 조정하여 비즈니스 로직 강제:

| 행위 | 권한 (Capability) | 허용 여부 | 상태 설명 | 비고 |
|------|------------------|-----------|----------|------|
| **링크 생성** | `create_posts` | ❌ **차단** | Do Not Allow | EXE API를 통해서만 가능 |
| **링크 수정** | `edit_posts` | ✅ **허용** | Allow | Target URL 수정 허용 |
| **링크 삭제** | `delete_posts` | ✅ **허용** | Allow | 불필요한 링크 정리 |
| **설정 변경** | `manage_options` | ✅ **허용** | Allow | 전역 설정 제어 (관리자만) |

#### 4.1.1 권한 제거 구현 예시

```php
// functions.php 또는 플러그인 메인 파일
add_filter('user_has_cap', function($allcaps, $caps, $args) {
    if (isset($args[0]) && $args[0] === 'create_posts') {
        // sb_link 포스트 타입에 대한 생성 권한 제거
        if (get_post_type($args[2]) === 'sb_link') {
            $allcaps['create_posts'] = false;
        }
    }
    return $allcaps;
}, 10, 3);
```

### 4.2 수정 화면 필드 제어 (Field Locking)

#### 4.2.1 Slug 비활성화 처리

WordPress의 `add_meta_box` 및 포스트 타이틀 영역 훅을 사용하여 Slug(제목) 영역을 `disabled` 처리:

```php
add_action('edit_form_after_title', function($post) {
    if ($post->post_type === 'sb_link') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // 제목(Slug) 필드 비활성화
            $('#title').prop('disabled', true).prop('readonly', true);
            $('#title-prompt-text').text('단축 주소는 변경할 수 없습니다');
        });
        </script>
        <?php
    }
});
```

#### 4.2.2 보장되는 효과

- **구독 만료 사용자**도 Target URL은 수정 가능
- **Slug의 고유성 보장**: 링크 공유 후에도 주소 변경 없음
- **SEO 친화적**: 영구 링크(Permalink) 일관성 유지

---

## 5. 데이터베이스 및 시스템 아키텍처

### 5.1 고도화된 로그 테이블 (Custom DB Table)

#### 5.1.1 필요성

단순 `post_meta`의 `click_count` 증가 방식으로는 다음이 불가능:
- 고유 방문자(UV) 계산
- 시간대별 분석
- 플랫폼별 필터링
- 리퍼러 추적

#### 5.1.2 테이블 설계

**테이블명**: `wp_sb_analytics_logs`

**스키마**:

```sql
CREATE TABLE wp_sb_analytics_logs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    link_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_posts.ID 참조',
    visitor_ip VARCHAR(64) NOT NULL COMMENT 'IP 주소 (해싱 저장 권장)',
    platform VARCHAR(50) DEFAULT 'etc' COMMENT '타겟 도메인 분류',
    referer VARCHAR(500) DEFAULT NULL COMMENT '유입 경로 (HTTP_REFERER)',
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '클릭 시간',
    PRIMARY KEY (id),
    INDEX idx_link_id (link_id),
    INDEX idx_visited_at (visited_at),
    INDEX idx_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5.1.3 컬럼 상세 설명

| 컬럼명 | 타입 | 설명 | 용도 |
|--------|------|------|------|
| `id` | BIGINT(20) AUTO_INCREMENT | 기본 키 | 레코드 고유 식별자 |
| `link_id` | BIGINT(20) | 외래 키 (wp_posts.ID) | 어떤 단축 링크가 클릭되었는지 |
| `visitor_ip` | VARCHAR(64) | IP 주소 (해싱 권장) | 고유 방문자 계산용 |
| `platform` | VARCHAR(50) | 플랫폼 태그 | 자동 분류 (coupang, aliexpress 등) |
| `referer` | VARCHAR(500) | HTTP_REFERER | 어디서 유입되었는지 추적 |
| `visited_at` | DATETIME | 클릭 시간 | 시간대별 분석용 |

#### 5.1.4 GDPR 대응: IP 해싱

```php
// 개인정보 보호를 위한 IP 해싱
$hashed_ip = hash('sha256', $visitor_ip . 'your-secret-salt');
```

### 5.2 플랫폼 자동 태깅 로직 (Auto-Tagging)

#### 5.2.1 동작 원리

링크 생성(API) 또는 수정 시, **Target URL의 도메인**을 정규식으로 분석하여 `platform` 메타 데이터를 자동 저장합니다.

#### 5.2.2 태깅 규칙

| 타겟 URL 도메인 | 플랫폼 태그 | 예시 |
|----------------|------------|------|
| `coupang.com` | `Coupang` | `https://coupa.ng/cfvjXy` |
| `a.aliexpress.com` | `AliExpress` | `https://a.aliexpress.com/_EH1Y3N` |
| `amazon.com` | `Amazon` | `https://amzn.to/3xYz123` |
| `temu.com` | `Temu` | `https://temu.to/m/abc123` |
| 매칭 없음 | `Etc` | 기타 모든 URL |

#### 5.2.3 구현 코드

```php
function sb_detect_platform($target_url) {
    $url_host = parse_url($target_url, PHP_URL_HOST);
    
    if (strpos($url_host, 'coupang.com') !== false) {
        return 'Coupang';
    } elseif (strpos($url_host, 'aliexpress.com') !== false) {
        return 'AliExpress';
    } elseif (strpos($url_host, 'amazon.com') !== false) {
        return 'Amazon';
    } elseif (strpos($url_host, 'temu.com') !== false) {
        return 'Temu';
    } else {
        return 'Etc';
    }
}
```

---

## 6. API 명세 (API Specification) - Security Enhanced

### 6.1 Base URL

```
https://yourdomain.com/wp-json/sb/v1
```

### 6.2 링크 생성 (POST /links) - [EXE 전용]

#### 6.2.1 인증 방식

단순 API Key 인증이 아닌, **HMAC-SHA256 서명 인증**을 수행합니다.

#### 6.2.2 요청 헤더 (Required Headers)

| 헤더명 | 설명 | 예시 값 |
|--------|------|---------|
| `X-SB-API-KEY` | 사용자 공개 키 (Public Key) | `sb_live_abc123xyz` |
| `X-SB-TIMESTAMP` | Unix 타임스탬프 (초 단위) | `1716447600` |
| `X-SB-SIGNATURE` | HMAC-SHA256 서명 (Hex 인코딩) | `a3f5d9c8b7e6f1a2d4c5b8e7f9a1d3c6...` |
| `User-Agent` | 클라이언트 식별자 | `SB-Client/Win64-v2.0` |
| `Content-Type` | 요청 본문 타입 | `application/json` |

#### 6.2.3 요청 본문 (Request Body)

```json
{
  "target_url": "https://coupa.ng/cfvjXy",
  "slug": "summer-sale"
}
```

**파라미터 설명**:
- `target_url` (string, required): 리다이렉션될 최종 목적지 URL
- `slug` (string, optional): 커스텀 단축 주소 (미입력 시 자동 생성)

#### 6.2.4 보안 로직 (Server-Side Verification)

**① User-Agent 검증**
```php
if ($_SERVER['HTTP_USER_AGENT'] !== 'SB-Client/Win64-v2.0') {
    return new WP_Error('forbidden', 'Unauthorized client', ['status' => 403]);
}
```

**② Timestamp 검증 (Replay Attack 방지)**

```php
$request_timestamp = intval($_SERVER['HTTP_X_SB_TIMESTAMP']);
$current_timestamp = time();
$time_diff = abs($current_timestamp - $request_timestamp);

// 60초 이상 차이 나면 거부
if ($time_diff > 60) {
    return new WP_Error('expired', 'Request Expired', ['status' => 401]);
}
```

**효과**: 
- 해커가 패킷을 캡처해도 **1분 후에는 무용지물**
- Wireshark, Fiddler 등으로 패킷을 뜯어도 재사용 불가

**③ Signature 검증 (위변조 방지)**

```php
// 1. API Key로 Secret Key 조회
$api_key = $_SERVER['HTTP_X_SB_API_KEY'];
$secret_key = get_user_secret_key($api_key); // DB에서 조회

// 2. 서버측에서 서명 생성
$body = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_SB_TIMESTAMP'];
$payload = $body . $timestamp;
$expected_signature = hash_hmac('sha256', $payload, $secret_key);

// 3. 클라이언트가 보낸 서명과 비교
$client_signature = $_SERVER['HTTP_X_SB_SIGNATURE'];

if (!hash_equals($expected_signature, $client_signature)) {
    return new WP_Error('forbidden', 'Invalid signature', ['status' => 403]);
}
```

**서명 생성 공식**:
```
HMAC_SHA256(Body + Timestamp, SecretKey) → Hex String
```

#### 6.2.5 Slug 생성 전략 (Collision Policy)

**Case 1: 커스텀 Slug 입력 시**
- 중복 체크 수행
- 이미 존재하면 `409 Conflict` 반환
- 사용자가 다른 Slug 입력해야 함

**Case 2: 자동 생성 (slug 미입력 시)**
- Base62 인코딩 사용 (0-9, a-z, A-Z)
- 6자리 랜덤 생성 (예: `aB3xY9`)
- 중복 시 **내부 재시도 3회**
- 3회 실패 시 `500 Internal Server Error`

```php
function generate_unique_slug($retry = 3) {
    for ($i = 0; $i < $retry; $i++) {
        $slug = base62_random(6);
        if (!slug_exists($slug)) {
            return $slug;
        }
    }
    throw new Exception('Failed to generate unique slug');
}
```

#### 6.2.6 응답 (Response)

**성공 (200 OK)**
```json
{
  "success": true,
  "short_link": "https://yourdomain.com/go/aB3xY9",
  "slug": "aB3xY9",
  "target_url": "https://coupa.ng/cfvjXy",
  "platform": "Coupang",
  "created_at": "2024-05-23T14:32:10Z"
}
```

**실패 응답 예시**

| HTTP 상태 | 코드 | 메시지 | 설명 |
|-----------|------|--------|------|
| `401 Unauthorized` | `expired` | Request Expired | 타임스탬프 차이 60초 초과 |
| `403 Forbidden` | `forbidden` | Invalid signature | HMAC 서명 불일치 |
| `403 Forbidden` | `forbidden` | Unauthorized client | User-Agent 불일치 |
| `409 Conflict` | `conflict` | Slug already exists | 커스텀 Slug 중복 |
| `500 Internal Server Error` | `error` | Failed to generate slug | 자동 생성 3회 실패 |

---

### 6.3 통계 데이터 조회 (GET /stats) - [Dashboard용]

#### 6.3.1 용도
플러그인 내부 대시보드 렌더링을 위한 **내부 AJAX API**

#### 6.3.2 인증
WordPress Nonce 기반 인증 (로그인된 관리자만 접근)

#### 6.3.3 요청 파라미터

| 파라미터 | 타입 | 필수 여부 | 설명 | 예시 값 |
|---------|------|----------|------|---------|
| `range` | string | Optional | 조회 기간 | `7d`, `30d`, `custom` |
| `start_date` | string | Optional | 시작 날짜 (YYYY-MM-DD) | `2024-05-01` |
| `end_date` | string | Optional | 종료 날짜 (YYYY-MM-DD) | `2024-05-31` |
| `platform_filter` | string | Optional | 플랫폼 필터 | `Coupang`, `AliExpress` |

#### 6.3.4 요청 예시

```
GET /wp-json/sb/v1/stats?range=30d&platform_filter=Coupang
```

#### 6.3.5 응답 (Response)

```json
{
  "success": true,
  "data": {
    "total_clicks": 1520,
    "unique_visitors": 850,
    "growth_rate": 12.5,
    "clicks_by_hour": [
      5, 3, 1, 0, 2, 8, 15, 32, 45, 67, 89, 120,
      135, 142, 128, 110, 95, 88, 102, 115, 98, 75, 42, 18
    ],
    "platform_share": {
      "Coupang": 912,
      "AliExpress": 380,
      "Amazon": 152,
      "Etc": 76
    },
    "daily_trend": [
      { "date": "2024-05-01", "clicks": 48 },
      { "date": "2024-05-02", "clicks": 52 },
      { "date": "2024-05-03", "clicks": 45 }
    ]
  }
}
```

**데이터 필드 설명**:
- `total_clicks`: 총 클릭 수 (중복 포함)
- `unique_visitors`: 고유 방문자 수 (IP 기반)
- `growth_rate`: 전일 대비 증가율 (%)
- `clicks_by_hour`: 0시~23시 배열 (24개 요소)
- `platform_share`: 플랫폼별 클릭 수 객체
- `daily_trend`: 일별 추세 배열

---

## 7. 클라이언트 EXE 프로그램 명세

### 7.1 EXE 프로그램의 역할

- WordPress 플러그인과 **독립적으로 배포**되는 Windows 실행 파일
- 사용자가 로컬에서 실행하여 단축 링크 생성
- **HMAC 서명 생성 로직**을 내장하여 API 호출

### 7.2 Python EXE - HMAC 서명 생성 및 API 호출 전체 플로우

#### 7.2.1 Python 클라이언트 구현 예시

```python
import hmac
import hashlib
import time
import json
import requests

class SmartBridgeClient:
    def __init__(self, api_key, secret_key, base_url):
        """
        Smart Bridge API 클라이언트 초기화
        
        Args:
            api_key (str): 공개 API 키 (예: sb_live_abc123xyz)
            secret_key (str): 비밀 키 (예: sk_secret_xyz789abc)
            base_url (str): WordPress 사이트 URL (예: https://yourdomain.com)
        """
        self.api_key = api_key
        self.secret_key = secret_key
        self.base_url = base_url.rstrip('/')
        self.api_endpoint = f"{self.base_url}/wp-json/sb/v1/links"
        self.user_agent = "SB-Client/Win64-v2.0"
    
    def generate_signature(self, body_json, timestamp):
        """
        HMAC-SHA256 서명 생성
        
        Args:
            body_json (str): JSON 문자열로 변환된 요청 본문
            timestamp (int): Unix 타임스탬프 (초 단위)
            
        Returns:
            str: Hex 인코딩된 HMAC 서명
        """
        # 서명 생성 공식: HMAC_SHA256(Body + Timestamp, SecretKey)
        payload = f"{body_json}{timestamp}"
        signature = hmac.new(
            key=self.secret_key.encode('utf-8'),
            msg=payload.encode('utf-8'),
            digestmod=hashlib.sha256
        ).hexdigest()
        
        return signature
    
    def create_short_link(self, target_url, custom_slug=None):
        """
        단축 링크 생성 API 호출
        
        Args:
            target_url (str): 리다이렉션될 최종 URL
            custom_slug (str, optional): 커스텀 단축 주소
            
        Returns:
            dict: API 응답 데이터
        """
        # 1. 현재 타임스탬프 생성
        timestamp = int(time.time())
        
        # 2. 요청 본문 생성
        request_body = {
            "target_url": target_url
        }
        if custom_slug:
            request_body["slug"] = custom_slug
        
        # JSON 문자열로 변환 (서명 생성에 사용)
        body_json = json.dumps(request_body, separators=(',', ':'))
        
        # 3. HMAC 서명 생성
        signature = self.generate_signature(body_json, timestamp)
        
        # 4. 요청 헤더 구성
        headers = {
            "Content-Type": "application/json",
            "User-Agent": self.user_agent,
            "X-SB-API-KEY": self.api_key,
            "X-SB-TIMESTAMP": str(timestamp),
            "X-SB-SIGNATURE": signature
        }
        
        # 5. API 호출
        try:
            response = requests.post(
                self.api_endpoint,
                headers=headers,
                data=body_json,
                timeout=10
            )
            
            # 6. 응답 처리
            if response.status_code == 200:
                return {
                    "success": True,
                    "data": response.json()
                }
            else:
                return {
                    "success": False,
                    "error": response.json(),
                    "status_code": response.status_code
                }
                
        except requests.exceptions.RequestException as e:
            return {
                "success": False,
                "error": str(e)
            }


# 사용 예시
if __name__ == "__main__":
    # 클라이언트 초기화
    client = SmartBridgeClient(
        api_key="sb_live_abc123xyz",
        secret_key="sk_secret_xyz789abc",
        base_url="https://yourdomain.com"
    )
    
    # 단축 링크 생성 (자동 Slug)
    result = client.create_short_link(
        target_url="https://coupa.ng/cfvjXy"
    )
    
    if result["success"]:
        print(f"✅ 성공: {result['data']['short_link']}")
        print(f"   Slug: {result['data']['slug']}")
        print(f"   Platform: {result['data']['platform']}")
    else:
        print(f"❌ 실패: {result['error']}")
    
    # 커스텀 Slug로 생성
    result2 = client.create_short_link(
        target_url="https://a.aliexpress.com/_EH1Y3N",
        custom_slug="summer-sale"
    )
```

#### 7.2.2 실제 HTTP 요청 예시 (cURL 형태)

```bash
# 1. 변수 설정
API_KEY="sb_live_abc123xyz"
SECRET_KEY="sk_secret_xyz789abc"
TIMESTAMP=$(date +%s)
TARGET_URL="https://coupa.ng/cfvjXy"
SLUG="summer-sale"

# 2. 요청 본문 (JSON)
BODY='{"target_url":"https://coupa.ng/cfvjXy","slug":"summer-sale"}'

# 3. HMAC 서명 생성
PAYLOAD="${BODY}${TIMESTAMP}"
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET_KEY" | awk '{print $2}')

# 4. API 호출
curl -X POST "https://yourdomain.com/wp-json/sb/v1/links" \
  -H "Content-Type: application/json" \
  -H "User-Agent: SB-Client/Win64-v2.0" \
  -H "X-SB-API-KEY: $API_KEY" \
  -H "X-SB-TIMESTAMP: $TIMESTAMP" \
  -H "X-SB-SIGNATURE: $SIGNATURE" \
  -d "$BODY"
```

#### 7.2.3 서버에서 수신되는 요청 구조

**WordPress PHP에서 받는 $_SERVER 변수 예시:**

```php
// REST API 엔드포인트에서 수신되는 데이터
$_SERVER['HTTP_USER_AGENT'] = 'SB-Client/Win64-v2.0';
$_SERVER['HTTP_X_SB_API_KEY'] = 'sb_live_abc123xyz';
$_SERVER['HTTP_X_SB_TIMESTAMP'] = '1716447600';
$_SERVER['HTTP_X_SB_SIGNATURE'] = 'a3f5d9c8b7e6f1a2d4c5b8e7f9a1d3c6b2e4f7a9c1d5e8b3f6a2d9c7e5f1a4b8';

// 요청 본문 (Raw Body)
$raw_body = file_get_contents('php://input');
// $raw_body = '{"target_url":"https://coupa.ng/cfvjXy","slug":"summer-sale"}'

// JSON 파싱
$body = json_decode($raw_body, true);
// $body = [
//     'target_url' => 'https://coupa.ng/cfvjXy',
//     'slug' => 'summer-sale'
// ]
```

---

## 8. WordPress 플러그인 서버 사이드 구현 가이드

### 8.1 REST API 엔드포인트 등록

```php
<?php
/**
 * Plugin Name: WP Smart Bridge
 * Version: 2.5.0
 */

add_action('rest_api_init', function() {
    register_rest_route('sb/v1', '/links', [
        'methods' => 'POST',
        'callback' => 'sb_create_short_link',
        'permission_callback' => '__return_true', // 커스텀 인증 사용
    ]);
});

function sb_create_short_link(WP_REST_Request $request) {
    // 1. User-Agent 검증
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($user_agent !== 'SB-Client/Win64-v2.0') {
        return new WP_Error(
            'forbidden',
            'Unauthorized client',
            ['status' => 403]
        );
    }
    
    // 2. 헤더 추출
    $api_key = $_SERVER['HTTP_X_SB_API_KEY'] ?? '';
    $timestamp = intval($_SERVER['HTTP_X_SB_TIMESTAMP'] ?? 0);
    $client_signature = $_SERVER['HTTP_X_SB_SIGNATURE'] ?? '';
    
    // 3. Timestamp 검증 (60초 이내)
    $current_time = time();
    if (abs($current_time - $timestamp) > 60) {
        return new WP_Error(
            'expired',
            'Request expired. Timestamp difference exceeds 60 seconds.',
            ['status' => 401]
        );
    }
    
    // 4. Secret Key 조회 (데이터베이스에서)
    $secret_key = sb_get_secret_key($api_key);
    if (!$secret_key) {
        return new WP_Error(
            'invalid_key',
            'Invalid API key',
            ['status' => 403]
        );
    }
    
    // 5. 서명 검증
    $raw_body = $request->get_body();
    $payload = $raw_body . $timestamp;
    $expected_signature = hash_hmac('sha256', $payload, $secret_key);
    
    if (!hash_equals($expected_signature, $client_signature)) {
        return new WP_Error(
            'invalid_signature',
            'HMAC signature verification failed',
            ['status' => 403]
        );
    }
    
    // 6. 요청 파라미터 추출
    $params = $request->get_json_params();
    $target_url = $params['target_url'] ?? '';
    $custom_slug = $params['slug'] ?? null;
    
    // 7. URL 유효성 검증
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        return new WP_Error(
            'invalid_url',
            'Invalid target URL format',
            ['status' => 400]
        );
    }
    
    // 8. Slug 생성 또는 검증
    if ($custom_slug) {
        // 커스텀 Slug 중복 체크
        if (sb_slug_exists($custom_slug)) {
            return new WP_Error(
                'conflict',
                'Slug already exists',
                ['status' => 409]
            );
        }
        $slug = $custom_slug;
    } else {
        // 자동 생성 (Base62, 6자리)
        $slug = sb_generate_unique_slug();
        if (!$slug) {
            return new WP_Error(
                'generation_failed',
                'Failed to generate unique slug after 3 retries',
                ['status' => 500]
            );
        }
    }
    
    // 9. 플랫폼 자동 태깅
    $platform = sb_detect_platform($target_url);
    
    // 10. 워드프레스 포스트로 저장
    $post_id = wp_insert_post([
        'post_title' => $slug,
        'post_type' => 'sb_link',
        'post_status' => 'publish',
        'meta_input' => [
            'target_url' => $target_url,
            'platform' => $platform,
            'click_count' => 0,
        ]
    ]);
    
    if (is_wp_error($post_id)) {
        return new WP_Error(
            'db_error',
            'Database insertion failed',
            ['status' => 500]
        );
    }
    
    // 11. 성공 응답
    return new WP_REST_Response([
        'success' => true,
        'short_link' => home_url("/go/{$slug}"),
        'slug' => $slug,
        'target_url' => $target_url,
        'platform' => $platform,
        'created_at' => current_time('c'),
    ], 200);
}

// Secret Key 조회 함수
function sb_get_secret_key($api_key) {
    global $wpdb;
    $table = $wpdb->prefix . 'sb_api_keys';
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT secret_key FROM $table WHERE api_key = %s AND status = 'active'",
        $api_key
    ));
    
    return $result;
}

// Slug 중복 체크
function sb_slug_exists($slug) {
    $query = new WP_Query([
        'post_type' => 'sb_link',
        'title' => $slug,
        'posts_per_page' => 1,
    ]);
    return $query->have_posts();
}

// Base62 고유 Slug 생성
function sb_generate_unique_slug($length = 6, $max_retries = 3) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    for ($i = 0; $i < $max_retries; $i++) {
        $slug = '';
        for ($j = 0; $j < $length; $j++) {
            $slug .= $chars[random_int(0, 61)];
        }
        
        if (!sb_slug_exists($slug)) {
            return $slug;
        }
    }
    
    return false; // 3회 실패
}

// 플랫폼 감지
function sb_detect_platform($target_url) {
    $host = parse_url($target_url, PHP_URL_HOST);
    
    if (strpos($host, 'coupang.com') !== false) {
        return 'Coupang';
    } elseif (strpos($host, 'aliexpress.com') !== false) {
        return 'AliExpress';
    } elseif (strpos($host, 'amazon.com') !== false) {
        return 'Amazon';
    } elseif (strpos($host, 'temu.com') !== false) {
        return 'Temu';
    }
    
    return 'Etc';
}
```

### 8.2 API Key 관리 테이블

```sql
CREATE TABLE wp_sb_api_keys (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_users.ID',
    api_key VARCHAR(100) NOT NULL UNIQUE COMMENT '공개 키 (예: sb_live_xxx)',
    secret_key VARCHAR(100) NOT NULL COMMENT '비밀 키 (서명 생성용)',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_api_key (api_key),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 8.3 Python 클라이언트 실행 시나리오

```python
# 사용자가 EXE 실행 후 다음 입력
client = SmartBridgeClient(
    api_key="sb_live_abc123xyz",      # 워드프레스 플러그인 설정에서 발급
    secret_key="sk_secret_xyz789abc",  # 플러그인 설정에서 발급
    base_url="https://myshop.com"
)

# 시나리오 1: 쿠팡 링크 자동 생성
result = client.create_short_link("https://coupa.ng/cfvjXy")
# 응답: {"short_link": "https://myshop.com/go/aB3xY9", "platform": "Coupang"}

# 시나리오 2: 알리익스프레스 커스텀 Slug
result = client.create_short_link(
    "https://a.aliexpress.com/_EH1Y3N",
    custom_slug="summer-deal"
)
# 응답: {"short_link": "https://myshop.com/go/summer-deal", "platform": "AliExpress"}