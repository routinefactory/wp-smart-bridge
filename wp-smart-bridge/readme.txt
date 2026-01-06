=== WP Smart Bridge ===
Contributors: routinefactory
Tags: affiliate, shortlink, analytics, redirect
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 3.0.1
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

= 3.0.1 =
*   Optimization: 전체 에셋 버전 업데이트 및 배포 최적화
*   Fix: 일부 환경에서 발생하는 CSS 로드 우선순위 문제 해결
*   Misc: 관리자 대시보드 UI 마이너 텍스트 수정

= 3.0.0 =
*   Optimization: 비동기 로깅(Async Logger) 도입으로 리다이렉트 속도 획기적 개선
*   Optimization: 대시보드 통계 조회용 요약 테이블(Summary Table) 구현 (속도 10배 향상)
*   Security: 전체 코드베이스 대상 엄격한 보안 감사 및 취약점 패치 완료 (IDOR, XSS, Race Condition)
*   Fix: 리다이렉트 템플릿 검증 로직 강화 및 SEO 태그 자동 적용
*   Fix: 데이터베이스 트랜잭션 처리 강화 (백업/복원/초기화 안정성 확보)

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
