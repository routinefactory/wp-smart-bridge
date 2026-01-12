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
                $slug = get_post_field('post_name', $link->ID);
                if (!$slug) {
                    $slug = $link->post_title;
                }

                $backup['data']['links'][] = [
                    'id' => $link->ID,
                    'slug' => $slug,
                    'title' => $link->post_title,
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
                $slug = isset($link_data['slug']) ? $link_data['slug'] : '';
                $title = isset($link_data['title']) ? $link_data['title'] : $slug;
                $post_name = sanitize_title($slug);

                if ($post_name === '') {
                    continue;
                }

                if (SB_Helpers::slug_exists($post_name)) {
                    continue;
                }

                $post_id = wp_insert_post([
                    'post_type' => SB_Post_Type::POST_TYPE,
                    'post_title' => $title,
                    'post_name' => $post_name,
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
                $slug = isset($link['slug']) ? $link['slug'] : '';
                $title = isset($link['title']) ? $link['title'] : $slug;
                $post_name = sanitize_title($slug);
                if ($post_name === '') {
                    continue;
                }
                if (SB_Helpers::slug_exists($post_name))
                    continue;
                $pid = wp_insert_post([
                    'post_type' => SB_Post_Type::POST_TYPE,
                    'post_title' => $title,
                    'post_name' => $post_name,
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

        // v4.2.4 Security: 파일 업로드 검증 추가 (Path Traversal 방지)
        if (!is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => '파일 업로드 실패: 유효하지 않은 파일입니다.']);
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

    // =========================================================================
    // 정적 HTML 백업 기능 (v4.0.0 - 5파일 아키텍처)
    // =========================================================================

    /**
     * 정적 백업 생성 (v4.0.0 - 5파일 아키텍처)
     * 
     * 기존: 링크당 1개 HTML 파일 (100만 링크 = 100만 파일)
     * 신규: 5개 파일만 생성 (index.html, links.json, config.js, loader.js, style.css)
     * 
     * 장점:
     * - 파일 수 대폭 감소 (N개 → 5개)
     * - ZIP 크기 감소 (~500MB → ~15MB)
     * - 중앙 설정 가능 (config.js)
     * 
     * @param int $offset 시작 위치 (배치 처리용, v4.0.0에서는 무시)
     * @param int $limit 배치 크기 (v4.0.0에서는 무시)
     * @param string $file_id 파일 식별자
     * @param int $total_links 전체 링크 수
     * @return array 결과 데이터
     */
    public static function generate_static_backup($offset = 0, $limit = 1000, $file_id = '', $total_links = 0)
    {
        // ZipArchive 체크
        if (!class_exists('ZipArchive')) {
            return array(
                'success' => false,
                'message' => 'ZipArchive PHP extension is not available on this server.'
            );
        }

        // 시간 제한 해제
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        // 메모리 제한 증가
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // 파일 ID 생성
        if (empty($file_id)) {
            $file_id = 'sb_static_' . date('Ymd_His') . '_' . wp_generate_password(6, false);
        }

        // 업로드 디렉토리 설정
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-static-backups';
        $backup_url = $upload_dir['baseurl'] . '/sb-static-backups';

        // 디렉토리 생성
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // 보안: 디렉토리 접근 제한
        $htaccess_path = $backup_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Options -Indexes\n");
        }

        $zip_path = $backup_dir . '/' . $file_id . '.zip';
        $zip_url = $backup_url . '/' . $file_id . '.zip';

        // ZIP 생성
        $zip = new ZipArchive();
        $result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            return array(
                'success' => false,
                'message' => 'Failed to create ZIP file. Error code: ' . $result
            );
        }

        // 모든 링크 조회 (한 번에)
        global $wpdb;
        $post_type = SB_Post_Type::POST_TYPE;

        $query = $wpdb->prepare(
            "SELECT p.post_name as slug, pm.meta_value as target_url 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s 
             AND p.post_status = 'publish'
             AND pm.meta_key = 'target_url'",
            $post_type
        );
        $links = $wpdb->get_results($query);
        $total_links = count($links);

        // 1. links.json 생성 (슬러그 → URL 매핑)
        $links_map = array();
        foreach ($links as $link) {
            if (!empty($link->slug) && !empty($link->target_url)) {
                $links_map[$link->slug] = $link->target_url;
            }
        }
        $links_json = json_encode($links_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('links.json', $links_json);

        // 2. config.js 생성 (워드프레스 설정 반영)
        $config_js = self::generate_config_js();
        $zip->addFromString('config.js', $config_js);

        // 3. loader.js 생성 (라우팅 + 리다이렉트 로직)
        $loader_js = self::generate_loader_js();
        $zip->addFromString('loader.js', $loader_js);

        // 4. style.css 생성 (브릿지 페이지 스타일)
        $style_css = self::generate_style_css();
        $zip->addFromString('style.css', $style_css);

        // 5. index.html 생성 (라우터 페이지)
        $index_html = self::generate_index_html();
        $zip->addFromString('index.html', $index_html);

        $zip->close();

        return array(
            'success' => true,
            'offset' => $total_links,
            'processed' => $total_links,
            'total' => $total_links,
            'file_id' => $file_id,
            'finished' => true,
            'download_url' => $zip_url
        );
    }

    /**
     * config.js 생성 (중앙 설정)
     * 
     * 워드프레스 설정값을 정적 파일에 포함
     * 
     * @return string JavaScript 코드
     */
    private static function generate_config_js()
    {
        $settings = get_option('sb_settings', array());
        $delay = isset($settings['redirect_delay']) ? floatval($settings['redirect_delay']) : 2;
        $delay_ms = intval($delay * 1000);

        $config = "/**\n";
        $config .= " * Smart Bridge Static Backup - Config v4.0.0\n";
        $config .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= " * \n";
        $config .= " * 이 파일을 수정하면 모든 링크에 즉시 반영됩니다.\n";
        $config .= " */\n";
        $config .= "window.SB_CONFIG = {\n";
        $config .= "  delay: " . $delay_ms . ",\n";
        $config .= "  message: '잠시 후 이동합니다...',\n";
        $config .= "  showCountdown: true,\n";
        $config .= "  showSpinner: true,\n";
        $config .= "  notFoundMessage: '요청하신 링크를 찾을 수 없습니다.',\n";
        $config .= "  notFoundUrl: null\n";
        $config .= "};\n";

        return $config;
    }

    /**
     * loader.js 생성 (라우팅 + 리다이렉트 로직)
     * 
     * v4.0.7: 커스텀 템플릿 지원
     * - URL에서 ?go=slug 파라미터 추출
     * - links.json에서 타겟 URL 조회
     * - [data-sb-target] 속성을 가진 요소에 href 설정
     * 
     * @return string JavaScript 코드
     */
    private static function generate_loader_js()
    {
        $js = "/**\n";
        $js .= " * Smart Bridge Static Backup - Loader v4.0.8\n";
        $js .= " */\n";
        $js .= "document.addEventListener('DOMContentLoaded', function() {\n";
        $js .= "  'use strict';\n\n";

        // URL 파라미터 추출
        $js .= "  var params = new URLSearchParams(window.location.search);\n";
        $js .= "  var slug = params.get('go');\n\n";

        $js .= "  if (!slug) {\n";
        $js .= "    showNotFound();\n";
        $js .= "    return;\n";
        $js .= "  }\n\n";

        // links.json 로드
        $js .= "  fetch('links.json')\n";
        $js .= "    .then(function(r) { return r.ok ? r.json() : Promise.reject('Network'); })\n";
        $js .= "    .then(function(links) {\n";
        $js .= "      var target = links[slug];\n";
        $js .= "      if (target) {\n";
        $js .= "        setupRedirect(target);\n";
        $js .= "      } else {\n";
        $js .= "        showNotFound();\n";
        $js .= "      }\n";
        $js .= "    })\n";
        $js .= "    .catch(function() { showNotFound(); });\n\n";

        // 리다이렉트 설정 함수
        $js .= "  function setupRedirect(targetUrl) {\n";
        $js .= "    var config = window.SB_CONFIG || { delay: 2000 };\n";
        $js .= "    var delay = config.delay || 2000;\n\n";

        // data-sb-target 속성을 가진 모든 요소에 href 설정
        $js .= "    // [data-sb-target] 요소에 타겟 URL 설정\n";
        $js .= "    var targets = document.querySelectorAll('[data-sb-target]');\n";
        $js .= "    targets.forEach(function(el) {\n";
        $js .= "      el.href = targetUrl;\n";
        $js .= "    });\n\n";

        // 리다이렉트 타이머
        $js .= "    // 딜레이 후 리다이렉트\n";
        $js .= "    setTimeout(function() {\n";
        $js .= "      window.location.replace(targetUrl);\n";
        $js .= "    }, delay);\n";
        $js .= "  }\n\n";

        // 404 처리
        $js .= "  function showNotFound() {\n";
        $js .= "    var config = window.SB_CONFIG || {};\n";
        $js .= "    var msg = config.notFoundMessage || '404 - Link not found';\n";
        $js .= "    document.body.innerHTML = '<div style=\"text-align:center;margin-top:100px;font-family:sans-serif\"><h2>' + msg + '</h2></div>';\n";
        $js .= "  }\n";
        $js .= "});\n";

        return $js;
    }

    /**
     * style.css 생성 (브릿지 페이지 스타일)
     * 
     * v4.0.7: 커스텀 템플릿에 inline <style> 포함
     * 이 파일은 하위 호환성/fallback 용도로 최소화
     * 
     * @return string CSS 코드
     */
    private static function generate_style_css()
    {
        return "/**\n * Smart Bridge Static Backup - Style v4.0.7\n * Styles are embedded in index.html (custom template)\n */\n";
    }

    /**
     * index.html 생성 (라우터 페이지)
     * 
     * v4.0.7: 커스텀 템플릿 지원
     * - sb_redirect_template 옵션에서 사용자 템플릿 로드
     * - {{TARGET_URL}} → data-sb-target 속성으로 변환
     * - loader.js가 동적으로 href 설정
     * 
     * @return string HTML 코드
     */
    private static function generate_index_html()
    {
        // 1. 커스텀 템플릿 로드
        $template = get_option('sb_redirect_template', '');
        if (empty($template)) {
            $template = SB_Helpers::get_default_redirect_template();
        }

        // 2. 설정값
        $settings = get_option('sb_settings', []);
        $delay = isset($settings['redirect_delay']) ? floatval($settings['redirect_delay']) : 2;

        // 3. {{TARGET_URL}} → data-sb-target 속성 추가 (정규식)
        // <a href="{{TARGET_URL}}" ...> → <a href="javascript:void(0)" data-sb-target ...>
        $html = preg_replace(
            '/(<a[^>]*)\s*href=["\']?\{\{TARGET_URL\}\}["\']?([^>]*>)/i',
            '$1 href="javascript:void(0)" data-sb-target$2',
            $template
        );

        // 4. Fallback: 정규식 실패 시 단순 치환
        if (strpos($html, '{{TARGET_URL}}') !== false) {
            $html = str_replace('{{TARGET_URL}}', 'javascript:void(0)', $html);
        }

        // 5. 나머지 플레이스홀더 치환
        $html = str_replace([
            '{{DELAY_SECONDS}}',
            '{{COUNTDOWN_ID}}',
            '{{COUNTDOWN_SCRIPT}}',
        ], [
            number_format($delay, 1),
            'countdown',
            '<script src="config.js"></script><script src="loader.js"></script>',
        ], $html);

        // 6. Quote escaping 처리 (DB 저장 시 이스케이핑된 경우 복원)
        $html = stripslashes($html);
        $html = htmlspecialchars_decode($html, ENT_QUOTES);

        return $html;
    }

    // =========================================================================
    // 롤백 기능 (P3 기능 개선)
    // =========================================================================

    /**
     * 롤백 전 데이터 백업 (자동 백업)
     *
     * @return array ['success' => bool, 'message' => string, 'backup_file' => string]
     */
    public static function create_rollback_backup()
    {
        // 자동 백업 디렉토리 설정
        $upload_dir = wp_upload_dir();
        $rollback_dir = $upload_dir['basedir'] . '/sb-rollback-backups';

        if (!file_exists($rollback_dir)) {
            wp_mkdir_p($rollback_dir);
        }

        // 보안: .htaccess 생성
        $htaccess_path = $rollback_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n");
        }

        // 백업 데이터 생성
        $backup = self::create_backup();

        // 파일명 생성 (타임스탬프 포함)
        $filename = 'rollback-backup-' . date('Y-m-d-His') . '.json';
        $filepath = $rollback_dir . '/' . $filename;

        // 파일 저장
        $result = file_put_contents($filepath, wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($result === false) {
            return [
                'success' => false,
                'message' => '롤백 백업 파일 생성에 실패했습니다.'
            ];
        }

        // 로그 기록
        self::log_rollback('backup', '', '롤백 전 백업 생성: ' . $filename);

        return [
            'success' => true,
            'message' => '롤백 전 백업이 생성되었습니다.',
            'backup_file' => $filename,
            'backup_path' => $filepath
        ];
    }

    /**
     * 롤백 실행
     *
     * @param string $backup_file 백업 파일명
     * @param bool $auto_backup 롤백 전 자동 백업 여부
     * @return array ['success' => bool, 'message' => string, 'stats' => array]
     */
    public static function perform_rollback($backup_file, $auto_backup = true)
    {
        // 백업 파일 경로 확인
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-rollback-backups';
        $filepath = $backup_dir . '/' . $backup_file;

        // 파일 존재 확인
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'message' => '백업 파일을 찾을 수 없습니다: ' . $backup_file
            ];
        }

        // 롤백 전 자동 백업
        $pre_rollback_backup = null;
        if ($auto_backup) {
            $pre_result = self::create_rollback_backup();
            if ($pre_result['success']) {
                $pre_rollback_backup = $pre_result['backup_file'];
            }
        }

        // 백업 파일 읽기
        $json_content = file_get_contents($filepath);
        $backup_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log_rollback('fail', $backup_file, 'JSON 파싱 실패: ' . json_last_error_msg());
            return [
                'success' => false,
                'message' => '유효하지 않은 백업 파일입니다: ' . json_last_error_msg()
            ];
        }

        // 데이터 무결성 검증 (롤백 전)
        $validation = self::validate_backup_integrity($backup_data);
        if (!$validation['valid']) {
            self::log_rollback('fail', $backup_file, '데이터 무결성 검증 실패: ' . $validation['message']);
            return [
                'success' => false,
                'message' => '백업 파일 무결성 검증 실패: ' . $validation['message']
            ];
        }

        // 기존 데이터 백업 (임시)
        $current_backup = self::create_backup();

        // 롤백 실행 (기존 데이터 삭제 후 복원)
        try {
            // 1. 기존 링크 삭제
            $existing_links = get_posts([
                'post_type' => SB_Post_Type::POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ]);

            foreach ($existing_links as $link) {
                wp_delete_post($link->ID, true);
            }

            // 2. 분석 로그 삭제
            global $wpdb;
            $table = $wpdb->prefix . 'sb_analytics_logs';
            $wpdb->query("TRUNCATE TABLE $table");

            // 3. 백업 데이터 복원
            $restore_result = self::restore_backup($backup_data);

            if (!$restore_result['success']) {
                // 복원 실패 시 롤백 (이전 상태로 복구)
                self::restore_backup($current_backup);
                self::log_rollback('fail', $backup_file, '복원 실패 후 원복 완료');
                
                return [
                    'success' => false,
                    'message' => '복원 실패: ' . $restore_result['message'] . ' (이전 상태로 복구됨)'
                ];
            }

            // 롤백 성공 로그
            $log_message = '롤백 성공';
            if ($pre_rollback_backup) {
                $log_message .= ' (사전 백업: ' . $pre_rollback_backup . ')';
            }
            self::log_rollback('success', $backup_file, $log_message);

            // 롤백 후 데이터 무결성 검증
            $post_validation = self::validate_rollback_integrity($backup_data);
            if (!$post_validation['valid']) {
                self::log_rollback('warning', $backup_file, '롤백 후 무결성 검증 경고: ' . $post_validation['message']);
            }

            return [
                'success' => true,
                'message' => '롤백이 성공적으로 완료되었습니다.',
                'stats' => $restore_result['stats'],
                'pre_rollback_backup' => $pre_rollback_backup,
                'post_validation' => $post_validation
            ];

        } catch (Exception $e) {
            // 예외 발생 시 롤백 (이전 상태로 복구)
            self::restore_backup($current_backup);
            self::log_rollback('fail', $backup_file, '예외 발생 후 원복: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '롤백 중 오류 발생: ' . $e->getMessage() . ' (이전 상태로 복구됨)'
            ];
        }
    }

    /**
     * 백업 데이터 무결성 검증
     *
     * @param array $backup_data 백업 데이터
     * @return array ['valid' => bool, 'message' => string]
     */
    private static function validate_backup_integrity($backup_data)
    {
        // 필수 필드 확인
        if (!isset($backup_data['version'])) {
            return [
                'valid' => false,
                'message' => '버전 정보가 없습니다.'
            ];
        }

        if (!isset($backup_data['data'])) {
            return [
                'valid' => false,
                'message' => '데이터 섹션이 없습니다.'
            ];
        }

        // 링크 데이터 구조 확인
        if (isset($backup_data['data']['links']) && is_array($backup_data['data']['links'])) {
            foreach ($backup_data['data']['links'] as $index => $link) {
                if (!isset($link['slug']) || !isset($link['target_url'])) {
                    return [
                        'valid' => false,
                        'message' => '링크 데이터 구조가 올바르지 않습니다 (인덱스: ' . $index . ')'
                    ];
                }
            }
        }

        // 분석 로그 구조 확인
        if (isset($backup_data['data']['analytics']) && is_array($backup_data['data']['analytics'])) {
            foreach ($backup_data['data']['analytics'] as $index => $log) {
                if (!isset($log['link_id']) || !isset($log['visited_at'])) {
                    return [
                        'valid' => false,
                        'message' => '분석 로그 구조가 올바르지 않습니다 (인덱스: ' . $index . ')'
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'message' => '무결성 검증 통과'
        ];
    }

    /**
     * 롤백 후 데이터 무결성 검증
     *
     * @param array $expected_data 예상 데이터
     * @return array ['valid' => bool, 'message' => string, 'differences' => array]
     */
    private static function validate_rollback_integrity($expected_data)
    {
        $differences = [];

        // 링크 수 비교
        $expected_links = isset($expected_data['data']['links']) ? count($expected_data['data']['links']) : 0;
        $actual_links = wp_count_posts(SB_Post_Type::POST_TYPE)->publish;

        if ($expected_links !== $actual_links) {
            $differences[] = "링크 수: 예상 {$expected_links}, 실제 {$actual_links}";
        }

        // 분석 로그 수 비교
        $expected_logs = isset($expected_data['data']['analytics']) ? count($expected_data['data']['analytics']) : 0;
        global $wpdb;
        $table = $wpdb->prefix . 'sb_analytics_logs';
        $actual_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        if ($expected_logs !== (int) $actual_logs) {
            $differences[] = "분석 로그 수: 예상 {$expected_logs}, 실제 {$actual_logs}";
        }

        // 설정 비교
        if (isset($expected_data['data']['settings'])) {
            $current_settings = get_option('sb_settings', []);
            $expected_settings = $expected_data['data']['settings'];
            
            $setting_diffs = array_diff_assoc($expected_settings, $current_settings);
            if (!empty($setting_diffs)) {
                $differences[] = "설정 차이: " . count($setting_diffs) . "개 항목";
            }
        }

        if (empty($differences)) {
            return [
                'valid' => true,
                'message' => '롤백 후 무결성 검증 통과',
                'differences' => []
            ];
        }

        return [
            'valid' => false,
            'message' => '데이터 불일치 발견',
            'differences' => $differences
        ];
    }

    /**
     * 롤백 로그 기록
     *
     * @param string $action 동작 (backup, success, fail, warning)
     * @param string $backup_file 백업 파일명
     * @param string $message 메시지
     * @return bool
     */
    private static function log_rollback($action, $backup_file, $message)
    {
        $logs = get_option('sb_rollback_logs', []);
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'backup_file' => $backup_file,
            'message' => $message,
            'user_id' => get_current_user_id(),
        ];

        // 최대 50개 로그 유지
        array_unshift($logs, $log_entry);
        if (count($logs) > 50) {
            $logs = array_slice($logs, 0, 50);
        }

        return update_option('sb_rollback_logs', $logs);
    }

    /**
     * 롤백 로그 조회
     *
     * @param int $limit 조회할 로그 수
     * @return array
     */
    public static function get_rollback_logs($limit = 20)
    {
        $logs = get_option('sb_rollback_logs', []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * 롤백 로그 삭제
     *
     * @return bool
     */
    public static function clear_rollback_logs()
    {
        return update_option('sb_rollback_logs', []);
    }

    /**
     * 사용 가능한 롤백 백업 파일 목록 조회
     *
     * @return array
     */
    public static function get_rollback_backups()
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-rollback-backups';

        if (!is_dir($backup_dir)) {
            return [];
        }

        $backups = [];

        foreach (glob($backup_dir . '/rollback-backup-*.json') as $filepath) {
            $filename = basename($filepath);
            $filetime = filemtime($filepath);
            $filesize = filesize($filepath);

            // 파일에서 버전 정보 추출
            $json_content = file_get_contents($filepath);
            $backup_data = json_decode($json_content, true);
            $version = isset($backup_data['version']) ? $backup_data['version'] : 'Unknown';

            $backups[] = [
                'filename' => $filename,
                'filepath' => $filepath,
                'version' => $version,
                'created_at' => date('Y-m-d H:i:s', $filetime),
                'size' => self::format_filesize($filesize),
                'size_bytes' => $filesize
            ];
        }

        // 생성일 역순 정렬
        usort($backups, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $backups;
    }

    /**
     * 롤백 백업 파일 삭제
     *
     * @param string $filename 파일명
     * @return array
     */
    public static function delete_rollback_backup($filename)
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-rollback-backups';
        $filepath = $backup_dir . '/' . $filename;

        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'message' => '파일을 찾을 수 없습니다.'
            ];
        }

        if (@unlink($filepath)) {
            self::log_rollback('delete', $filename, '롤백 백업 파일 삭제');
            return [
                'success' => true,
                'message' => '파일이 삭제되었습니다.'
            ];
        }

        return [
            'success' => false,
            'message' => '파일 삭제에 실패했습니다.'
        ];
    }

    /**
     * 오래된 롤백 백업 파일 정리
     *
     * @param int $days_old 보관할 일수
     * @return int 삭제된 파일 수
     */
    public static function cleanup_rollback_backups($days_old = 30)
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-rollback-backups';

        if (!is_dir($backup_dir)) {
            return 0;
        }

        $deleted_count = 0;
        $cutoff_time = time() - ($days_old * DAY_IN_SECONDS);

        foreach (glob($backup_dir . '/rollback-backup-*.json') as $filepath) {
            if (filemtime($filepath) < $cutoff_time) {
                $filename = basename($filepath);
                if (@unlink($filepath)) {
                    self::log_rollback('cleanup', $filename, '오래된 백업 자동 삭제');
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * 파일 크기 포맷팅
     *
     * @param int $bytes 바이트
     * @return string
     */
    private static function format_filesize($bytes)
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}
