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

// 옵션 삭제 여부 확인 (설정에서 제어 가능)
$settings = get_option('sb_settings', []);
$delete_data = isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : true;

if (!$delete_data) {
    return;
}

global $wpdb;

// 1. 커스텀 테이블 삭제
$analytics_table = $wpdb->prefix . 'sb_analytics_logs';
$api_keys_table = $wpdb->prefix . 'sb_api_keys';

$wpdb->query("DROP TABLE IF EXISTS $analytics_table");
$wpdb->query("DROP TABLE IF EXISTS $api_keys_table");

// 2. sb_link 포스트 및 메타 삭제
$posts = get_posts([
    'post_type' => 'sb_link',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($posts as $post_id) {
    wp_delete_post($post_id, true);
}

// 3. 플러그인 옵션 삭제
delete_option('sb_version');
delete_option('sb_settings');

// 4. Rewrite 규칙 플러시
flush_rewrite_rules();
