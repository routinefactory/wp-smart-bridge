=== WP Smart Bridge ===
Contributors: routinefactory
Tags: affiliate, shortlink, analytics, redirect
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 2.9.23
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WP Smart Bridge는 제휴 마케팅을 위한 강력한 단축 링크 자동화 및 분석 플러그인입니다.

== Description ==

이 플러그인은 외부 앱(EXE)과 연동하여 자동으로 제휴 링크를 단축하고, 상세한 클릭 분석을 제공합니다.

**주요 기능:**

*   **자동 단축 링크 생성:** REST API를 통해 외부에서 링크 생성
*   **보안 강화:** HMAC-SHA256 서명 검증 및 IP 해싱 저장
*   **상세 분석:** 일별/시간대별/플랫폼별 클릭 통계 및 유입 경로 분석
*   **데이터 무결성:** 백업/복원 기능 및 공장 초기화 지원
*   **고성능:** 대용량 데이터 처리에 최적화된 DB 구조
*   **NEW:** 링크 그룹 관리 및 실시간 클릭 피드

== Installation ==

1. `wp-smart-bridge.zip` 파일을 다운로드합니다.
2. 워드프레스 관리자 > 플러그인 > 새로 추가 > 업로드에서 파일을 선택합니다.
3. 플러그인을 활성화합니다.
4. 'Smart Bridge 설정' 메뉴에서 API 키를 발급받습니다.

== Changelog ==

= 2.9.23 =
*   Feature: 링크 그룹(폴더) 기능 추가
*   Feature: 실시간 클릭 피드(SSE) 추가
*   Fix: N+1 쿼리 성능 최적화
*   Fix: Uninstall 시 데이터 Clean-up 누락 수정
*   Security: Realtime Feed 무한 루프 방지 로직 강화

= 2.9.22 =
*   Fix: 백업 복원 시 ID Mismatch로 인한 데이터 오염 문제 해결
*   Fix: REST API User-Agent 검사 로직 유연화 (Prefix Check)
*   Fix: 프론트엔드 XSS 취약점 보안 패치
*   Optimization: Analytics 쿼리 인덱스 성능 최적화 (Range Scan)

= 2.9.21 =
*   Initial Release
