<?php
/**
 * 백업/복원 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Backup
{
    /**
     * 전체 데이터 백업 생성
     * 
     * @return array 백업 데이터
     */
    public static function create_backup()
    {
        global $wpdb;

        $backup = [
            'version' => SB_VERSION,
            'created_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'data' => []
        ];

        // 링크 데이터
        $links = get_posts([
            'post_type' => 'sb_link',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        $backup['data']['links'] = [];
        foreach ($links as $link) {
            $backup['data']['links'][] = [
                'slug' => $link->post_title,
                'target_url' => get_post_meta($link->ID, 'target_url', true),
                'platform' => get_post_meta($link->ID, 'platform', true),
                'loading_message' => get_post_meta($link->ID, 'loading_message', true),
                'created_at' => $link->post_date,
                'status' => $link->post_status,
            ];
        }

        // 분석 로그
        $table = $wpdb->prefix . 'sb_analytics_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY visited_at DESC LIMIT 10000", ARRAY_A);
        $backup['data']['analytics'] = $logs;

        // 설정
        $backup['data']['settings'] = get_option('sb_settings', []);

        // API 키 (선택적 - 보안상 제외 가능)
        // $backup['data']['api_keys'] = SB_Database::get_all_api_keys();

        return $backup;
    }

    /**
     * 백업 데이터를 JSON 파일로 다운로드
     */
    public static function download_backup()
    {
        $backup = self::create_backup();

        $filename = 'wp-smart-bridge-backup-' . date('Y-m-d-His') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        echo wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 백업 데이터 복원
     * 
     * @param array $backup 백업 데이터
     * @return array ['success' => bool, 'message' => string, 'stats' => array]
     */
    public static function restore_backup($backup)
    {
        global $wpdb;

        if (!isset($backup['data'])) {
            return [
                'success' => false,
                'message' => '잘못된 백업 파일 형식입니다.'
            ];
        }

        $stats = [
            'links' => 0,
            'analytics' => 0,
        ];

        // 링크 복원
        if (isset($backup['data']['links'])) {
            foreach ($backup['data']['links'] as $link_data) {
                // 기존 링크 확인
                if (SB_Helpers::slug_exists($link_data['slug'])) {
                    continue; // 중복 건너뛰기
                }

                // 링크 생성
                $post_id = wp_insert_post([
                    'post_type' => 'sb_link',
                    'post_title' => $link_data['slug'],
                    'post_status' => $link_data['status'] ?? 'publish',
                    'post_date' => $link_data['created_at'] ?? current_time('mysql'),
                ]);

                if ($post_id) {
                    update_post_meta($post_id, 'target_url', $link_data['target_url']);
                    update_post_meta($post_id, 'platform', $link_data['platform']);
                    if (!empty($link_data['loading_message'])) {
                        update_post_meta($post_id, 'loading_message', $link_data['loading_message']);
                    }
                    $stats['links']++;
                }
            }
        }

        // 분석 로그 복원
        if (isset($backup['data']['analytics'])) {
            $table = $wpdb->prefix . 'sb_analytics_logs';
            foreach ($backup['data']['analytics'] as $log) {
                // 중복 방지: link_id + visited_at 조합으로 체크
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE link_id = %d AND visited_at = %s",
                    $log['link_id'],
                    $log['visited_at']
                ));

                if ($exists == 0) {
                    $wpdb->insert($table, [
                        'link_id' => $log['link_id'],
                        'visitor_ip' => $log['visitor_ip'],
                        'platform' => $log['platform'],
                        'visited_at' => $log['visited_at'],
                    ]);
                    $stats['analytics']++;
                }
            }
        }

        // 설정 복원 (선택적)
        if (isset($backup['data']['settings']) && !empty($backup['data']['settings'])) {
            $current_settings = get_option('sb_settings', []);
            // 현재 설정과 병합 (기존 설정 우선)
            $merged = array_merge($backup['data']['settings'], $current_settings);
            update_option('sb_settings', $merged);
        }

        return [
            'success' => true,
            'message' => '백업이 성공적으로 복원되었습니다!',
            'stats' => $stats
        ];
    }

    /**
     * 백업 파일 업로드 및 복원 (AJAX 핸들러용)
     */
    public static function handle_restore_upload()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        if (!isset($_FILES['backup_file'])) {
            wp_send_json_error(['message' => '파일이 업로드되지 않았습니다.']);
        }

        $file = $_FILES['backup_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => '파일 업로드 오류가 발생했습니다.']);
        }

        $content = file_get_contents($file['tmp_name']);
        $backup = json_decode($content, true);

        if (!$backup) {
            wp_send_json_error(['message' => 'JSON 파싱 실패: 잘못된 백업 파일입니다.']);
        }

        $result = self::restore_backup($backup);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
