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

        // v2.9.22 Scalability Fix: Increase limits for large datasets
        if (function_exists('set_time_limit')) {
            set_time_limit(300); // 5 mins
        }
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M'); // Suppress warning if disabled
        }

        $backup = [
            'version' => SB_VERSION,
            'created_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'data' => []
        ];

        // 1. 링크 데이터 (Batching)
        $backup['data']['links'] = [];
        $paged = 1;
        $posts_per_page = 500;

        while (true) {
            $links = get_posts([
                'post_type' => 'sb_link',
                'posts_per_page' => $posts_per_page,
                'paged' => $paged,
                'post_status' => 'any',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);

            if (empty($links)) {
                break;
            }

            foreach ($links as $link) {
                $backup['data']['links'][] = [
                    'id' => $link->ID,
                    'slug' => $link->post_title,
                    'target_url' => get_post_meta($link->ID, 'target_url', true),
                    'platform' => get_post_meta($link->ID, 'platform', true),
                    'created_at' => $link->post_date,
                    'status' => $link->post_status,
                ];
            }
            $paged++;

            // Safety break for very large sites (prevent infinite loop)
            if ($paged > 200)
                break; // 100k links limit
        }

        // 2. 분석 로그 (Batching using OFFSET for memory safety)
        $table = $wpdb->prefix . 'sb_analytics_logs';
        $backup['data']['analytics'] = [];

        $offset = 0;
        $limit = 1000;

        while (true) {
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT link_id, visitor_ip, platform, visited_at 
                 FROM $table 
                 ORDER BY id ASC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));

            if (empty($logs)) {
                break;
            }

            foreach ($logs as $log) {
                $backup['data']['analytics'][] = [
                    'link_id' => (int) $log->link_id,
                    'visitor_ip' => $log->visitor_ip,
                    'platform' => $log->platform,
                    'visited_at' => $log->visited_at,
                ];
            }
            $offset += $limit;

            // Safety limit (e.g., 200k logs for JSON backup to stay somewhat portable)
            // Beyond this, JSON might not be the right format.
            if ($offset >= 200000)
                break;
        }

        // 3. 설정
        $backup['data']['settings'] = get_option('sb_settings', []);

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

        $id_map = []; // v2.9.22: Old ID -> New ID 매핑 배열

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

                // 에러 체크
                if (is_wp_error($post_id) || $post_id === 0) {
                    continue; // 실패 시 다음 링크로
                }

                // 메타 데이터 저장
                update_post_meta($post_id, 'target_url', $link_data['target_url']);
                update_post_meta($post_id, 'platform', $link_data['platform']);

                // ID 매핑 기록 (구버전 백업 호환성 고려)
                if (isset($link_data['id'])) {
                    $id_map[$link_data['id']] = $post_id;
                }

                $stats['links']++;
            }
        }

        // 분석 로그 복원
        if (isset($backup['data']['analytics'])) {
            $table = $wpdb->prefix . 'sb_analytics_logs';
            foreach ($backup['data']['analytics'] as $log) {

                // ID 매핑 적용 (복원된 새 ID 사용)
                $original_link_id = (int) $log['link_id'];
                $new_link_id = isset($id_map[$original_link_id]) ? $id_map[$original_link_id] : $original_link_id;

                // 유효성 체크: 해당 포스트가 존재해야 함 (고아 데이터 방지)
                // get_post는 캐시를 사용하므로 성능 영향 적음
                if (!get_post($new_link_id)) {
                    continue;
                }

                // 중복 방지: link_id + visited_at 조합으로 체크
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE link_id = %d AND visited_at = %s",
                    $new_link_id,
                    $log['visited_at']
                ));

                if ($exists == 0) {
                    $wpdb->insert($table, [
                        'link_id' => $new_link_id,
                        'visitor_ip' => $log['visitor_ip'],
                        'platform' => $log['platform'],
                        'visited_at' => $log['visited_at'],
                    ]);
                    $stats['analytics']++;
                }
            }
        }

        // 설정 복원 (백업이 우선)
        if (isset($backup['data']['settings'])) {
            $current_settings = get_option('sb_settings', []);

            // ✅ 올바른 순서: 백업이 현재 설정을 덮어씀
            // array_merge(먼저, 나중) → 나중 것이 우선
            $merged_settings = array_merge($current_settings, $backup['data']['settings']);

            update_option('sb_settings', $merged_settings);
            $stats['settings_restored'] = true;
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
            wp_send_json_error(['message' => '파일 업로드 실패: ' . $file['error']]);
        }

        // 파일 크기 검증 (최대 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            wp_send_json_error([
                'message' => '파일이 너무 큽니다. 최대 크기: 10MB (현재: ' .
                    round($file['size'] / 1024 / 1024, 2) . 'MB)'
            ]);
        }

        // 확장자 검증
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            wp_send_json_error(['message' => 'JSON 파일만 허용됩니다.']);
        }

        // MIME 타입 검증 (보안 강화)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = ['application/json', 'text/plain', 'application/octet-stream'];
            if (!in_array($mime_type, $allowed_mimes)) {
                wp_send_json_error([
                    'message' => '유효하지 않은 파일 형식입니다. (감지된 타입: ' . $mime_type . ')'
                ]);
            }
        }

        // JSON 파일 읽기
        $json_content = file_get_contents($file['tmp_name']);
        $backup = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => '유효하지 않은 JSON 파일입니다: ' . json_last_error_msg()]);
        }

        $result = self::restore_backup($backup);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
