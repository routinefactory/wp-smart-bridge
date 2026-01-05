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
                'post_type' => SB_Post_Type::POST_TYPE,
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
                    'click_count' => (int) get_post_meta($link->ID, 'click_count', true),
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
    /**
     * 백업 데이터 복원 (Chunk 방식 - v3.0.0 Scalability)
     * 
     * @param array $chunk_data 부분 데이터 ['links' => [...], 'analytics' => [...]]
     * @param array $options 복원 옵션 (예: 설정 복원 여부)
     * @return array 통계
     */
    public static function restore_chunk($chunk_data, $options = [])
    {
        global $wpdb;

        $stats = [
            'links' => 0,
            'analytics' => 0,
            'settings' => 0,
        ];

        // v3.0.0 Scalability: Transient-based ID Map for large batches
        $session_id = isset($options['session_id']) ? $options['session_id'] : '';
        $transient_key = 'sb_restore_map_' . $session_id;
        $id_map = [];

        if ($session_id) {
            $current_map = get_transient($transient_key);
            if (is_array($current_map)) {
                $id_map = $current_map;
            }
        } elseif (isset($options['id_map'])) {
            // Fallback for smaller non-session restores
            $id_map = $options['id_map'];
        }

        // 1. 링크 복원
        if (!empty($chunk_data['links'])) {
            foreach ($chunk_data['links'] as $link_data) {
                if (SB_Helpers::slug_exists($link_data['slug'])) {
                    continue;
                }

                $post_id = wp_insert_post([
                    'post_type' => SB_Post_Type::POST_TYPE,
                    'post_title' => $link_data['slug'],
                    'post_status' => $link_data['status'] ?? 'publish',
                    'post_date' => $link_data['created_at'] ?? current_time('mysql'),
                ]);

                if (is_wp_error($post_id) || $post_id === 0) {
                    continue;
                }

                update_post_meta($post_id, 'target_url', esc_url_raw($link_data['target_url']));
                update_post_meta($post_id, 'platform', $link_data['platform']);
                // v3.0.0 Data Integrity: Restore click_count
                if (isset($link_data['click_count'])) {
                    update_post_meta($post_id, 'click_count', intval($link_data['click_count']));
                } else {
                    update_post_meta($post_id, 'click_count', 0);
                }

                // Click count는 Analytics 복원 시 재집계 될 수 있으나, 백업된 순간의 snapshot을 원하면?
                // 메타가 백업 데이터에 포함되어 있다면 복원.
                // 현재 구조는 'links' 배열에 메타가 포함되어 있지 않을 수 있음. (코드 확인 필요)
                // -> create_backup() 에서는 target_url, platform 만 저장함. 
                // click_count는? -> backup create 시 누락되어 있었음. (Audit Finding!)
                // 추후 개선사항: create_backup()에 click_count 추가.
                // 일단 지금은 analytics 로그가 복원되면 재집계 가능.

                // ID 매핑 기록
                if (isset($link_data['id'])) {
                    $id_map[$link_data['id']] = $post_id;
                }

                $stats['links']++;
            }

            // 링크 배치 처리 후 맵 저장
            if ($session_id) {
                set_transient($transient_key, $id_map, HOUR_IN_SECONDS);
            }
        }

        // 2. 분석 로그 복원
        if (!empty($chunk_data['analytics'])) {
            $table = $wpdb->prefix . 'sb_analytics_logs';
            foreach ($chunk_data['analytics'] as $log) {
                // Link ID Mapping Issue:
                // 이전 DB의 link_id와 현재 DB의 post_id는 다름.
                // 따라서 로그 복원 시 link_id가 아닌 'slug' 기반으로 매핑해야 함.
                // 하지만 로그엔 slug가 없음. (link_id만 있음)
                // -> 문제점: 백업 파일 구조상 링크와 로그가 분리되어 있고, 로그는 구 ID를 참조함.
                // 해결책: 
                // A) 한 번에(Classic) 복원할 때는 메모리 내에 ID Map을 구축했음.
                // B) 청크(Batch) 복원 시에는 ID Map을 유지하기 어려움 (세션/Transients 필요).

                // 매핑 로직은 호출부(Controller)에서 관리하거나, 여기서 처리.
                // $options['id_map'] 이 전달된다고 가정.

                $original_id = (int) $log['link_id'];
                $new_id = 0;

                // 맵에서 새 ID 조회
                if (isset($id_map[$original_id])) {
                    $new_id = $id_map[$original_id];
                } else {
                    continue; // 매핑 없으면 스킵
                }

                if (!get_post($new_id))
                    continue;

                // 트랜잭션 내에서 중복 체크는 성능 저하 유발 가능.
                // 하지만 로그 중복은 막아야 함.
                // 여기서는 INSERT IGNORE가 없으므로 SELECT check.
                // 대량 복원 시 속도 저하 요인 -> 향후 최적화 포인트.
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE link_id = %d AND visited_at = %s",
                    $new_id,
                    $log['visited_at']
                ));

                if ($exists == 0) {
                    $wpdb->insert($table, [
                        'link_id' => $new_id,
                        'visitor_ip' => $log['visitor_ip'],
                        'platform' => $log['platform'],
                        'visited_at' => $log['visited_at'],
                    ]);
                    $stats['analytics']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Deprecated: Use batch restore logic in SB_Admin
     */
    public static function restore_backup($backup)
    {
        // Legacy Support for small files
        // ... (Original logic kept as fallback) ...
        return self::original_restore_logic($backup);
    }

    // Internal helper to keep code clean (renamed original restore_backup)
    private static function original_restore_logic($backup)
    {
        global $wpdb;
        // ... (Logic from previous restore_backup) ...
        // For simplicity in this diff, we are mostly replacing the whole block logic 
        // or we can keep the file largely as is and add the new method.
        // Let's Keep the original restore_backup fully functional for small files!

        // RE-INSTATE ORIGINAL LOGIC FOR BACKWARD COMPAT (Code Reuse)
        // (Simply calling restore_chunk in a loop effectively recreates it?)
        // No, because of the ID Mapping state.

        // ... Original Code Body ...
        if (!isset($backup['data'])) {
            return ['success' => false, 'message' => 'Invalid Format'];
        }

        $stats = ['links' => 0, 'analytics' => 0];
        $id_map = [];

        // 1. Links
        if (isset($backup['data']['links'])) {
            foreach ($backup['data']['links'] as $link) {
                if (SB_Helpers::slug_exists($link['slug']))
                    continue;
                $pid = wp_insert_post([
                    'post_type' => SB_Post_Type::POST_TYPE,
                    'post_title' => $link['slug'],
                    'post_status' => $link['status'] ?? 'publish',
                    'post_date' => $link['created_at']
                ]);
                if (!is_wp_error($pid) && $pid) {
                    update_post_meta($pid, 'target_url', esc_url_raw($link['target_url']));
                    update_post_meta($pid, 'platform', $link['platform']);
                    if (isset($link['click_count'])) {
                        update_post_meta($pid, 'click_count', intval($link['click_count']));
                    }
                    if (isset($link['id']))
                        $id_map[$link['id']] = $pid;
                    $stats['links']++;
                }
            }
        }

        // 2. Logs
        if (isset($backup['data']['analytics'])) {
            $table = $wpdb->prefix . 'sb_analytics_logs';
            foreach ($backup['data']['analytics'] as $log) {
                $oid = (int) $log['link_id'];
                $nid = $id_map[$oid] ?? $oid; // Map or keep (if ID match by luck/force)

                if (!get_post($nid))
                    continue; // Orphan check

                $wpdb->insert($table, [
                    'link_id' => $nid,
                    'visitor_ip' => $log['visitor_ip'],
                    'platform' => $log['platform'],
                    'visited_at' => $log['visited_at']
                ]);
                $stats['analytics']++;
            }
        }

        // 3. Settings
        // ... (Settings logic) ...
        if (isset($backup['data']['settings'])) {
            update_option('sb_settings', array_merge(get_option('sb_settings', []), $backup['data']['settings']));
            $stats['settings_restored'] = true;
        }

        return ['success' => true, 'message' => 'Restored (Legacy)', 'stats' => $stats];
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
