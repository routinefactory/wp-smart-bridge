<?php
/**
 * 플러그인 삭제 시 정리 작업
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

// WordPress에서 호출된 것이 아니면 종료
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * ⚠️ 중요: 데이터 보존 정책
 * 
 * 기본적으로 플러그인 삭제 시에도 사용자 데이터를 보존합니다.
 * 이유:
 * 1. 제휴 링크는 외부에 공유된 중요한 자산입니다.
 * 2. 분석 데이터(클릭 로그)는 복구 불가능한 귀중한 정보입니다.
 * 3. API 키 재생성 시 외부 EXE 클라이언트 재설정이 필요합니다.
 * 4. 플러그인 재설치 또는 테스트 시 데이터 손실 방지가 필요합니다.
 * 
 * 데이터 삭제를 원하는 경우:
 * - WordPress 관리자 → Smart Bridge → 설정 → "플러그인 삭제 시 데이터 완전 삭제" 활성화
 * - 그 후 플러그인 삭제 시 아래 로직이 실행됩니다.
 */

// 옵션 삭제 여부 확인 (설정에서 제어 가능)
$settings = get_option('sb_settings', []);

// ✅ 기본값 변경: false (데이터 보존)
// 사용자가 명시적으로 설정에서 활성화한 경우에만 삭제
$delete_data = isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : false;

if (!$delete_data) {
    // 데이터 보존 모드: 아무것도 삭제하지 않음
    return;
}

/**
 * ⚠️⚠️⚠️ 주의: 이 섹션은 사용자가 설정에서 명시적으로
 * "플러그인 삭제 시 데이터 완전 삭제"를 활성화한 경우에만 실행됩니다.
 * 
 * 삭제되는 데이터:
 * - 커스텀 테이블: wp_sb_analytics_logs (모든 클릭 분석 데이터)
 * - 커스텀 테이블: wp_sb_api_keys (모든 API 키)
 * - 포스트 타입: sb_link (모든 단축 링크)
 * - 옵션: sb_version, sb_settings
 * 
 * ⚠️ 이 작업은 되돌릴 수 없습니다!
 */

global $wpdb;

// 1. 커스텀 테이블 삭제
$analytics_table = $wpdb->prefix . 'sb_analytics_logs';
$api_keys_table = $wpdb->prefix . 'sb_api_keys';
$groups_table = $wpdb->prefix . 'sb_link_groups';

$wpdb->query("DROP TABLE IF EXISTS $analytics_table");
$wpdb->query("DROP TABLE IF EXISTS $api_keys_table");
$wpdb->query("DROP TABLE IF EXISTS $groups_table");

// 2. sb_link 포스트 및 메타 삭제
$posts = get_posts([
    'post_type' => 'sb_link',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($posts as $post_id) {
    wp_delete_post($post_id, true); // true = 완전 삭제 (휴지통 우회)
}

// 3. 플러그인 옵션 삭제
delete_option('sb_version');
delete_option('sb_settings');
delete_option('sb_first_install_notice'); // 설치 안내 배너 상태

// 4. Rewrite 규칙 플러시
flush_rewrite_rules();
