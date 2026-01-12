<?php
/**
 * 관리자 AJAX 핸들러 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Admin_Ajax
{
    /**
     * AJAX 액션 등록
     */
    public static function init()
    {
        $actions = [
            'sb_generate_api_key' => 'ajax_generate_api_key',
            'sb_delete_api_key' => 'ajax_delete_api_key',
            'sb_save_general_settings' => 'ajax_save_general_settings',
            'sb_save_settings' => 'ajax_save_settings',
            'sb_dismiss_welcome' => 'ajax_dismiss_welcome',
            'sb_force_check_update' => 'ajax_force_check_update',
            'sb_save_redirect_template' => 'ajax_save_redirect_template',
            'sb_reset_redirect_template' => 'ajax_reset_redirect_template',
            'sb_download_backup' => 'ajax_download_backup',
            'sb_restore_backup' => 'ajax_restore_backup',
            'sb_health_check' => 'ajax_health_check',
            'sb_factory_reset' => 'ajax_factory_reset',
            // Link Groups AJAX (v2.9.23)
            'sb_create_group' => 'ajax_create_group',
            'sb_update_group' => 'ajax_update_group',
            'sb_delete_group' => 'ajax_delete_group',
            'sb_get_groups' => 'ajax_get_groups',
            'sb_assign_link_group' => 'ajax_assign_link_group',
            'sb_realtime_feed' => 'ajax_realtime_feed',
            'sb_get_dashboard_stats' => 'ajax_get_dashboard_stats',
            'sb_migrate_daily_stats' => 'ajax_migrate_daily_stats', // v2.9.27
            'sb_restore_backup_chunk' => 'ajax_restore_backup_chunk', // v3.0.0 Scalability
            // 'sb_flush_rewrite_rules' => 'ajax_flush_rewrite_rules', // v4.0.0: 파라미터 방식으로 불필요
            'sb_generate_static_backup' => 'ajax_generate_static_backup', // v3.4.0 Static HTML backup
            // Link Management Tab Enhancements (v4.3.0)
            'sb_bulk_delete_links' => 'ajax_bulk_delete_links',
            'sb_bulk_update_platform' => 'ajax_bulk_update_platform',
            'sb_get_filtered_link_count' => 'ajax_get_filtered_link_count',
            // P3 기능 개선: 업데이트 및 롤백
            'sb_check_update' => 'ajax_check_update',
            'sb_download_update' => 'ajax_download_update',
            'sb_dismiss_update_notice' => 'ajax_dismiss_update_notice',
            'sb_get_update_status' => 'ajax_get_update_status',
            'sb_clear_update_logs' => 'ajax_clear_update_logs',
            'sb_get_rollback_backups' => 'ajax_get_rollback_backups',
            'sb_perform_rollback' => 'ajax_perform_rollback',
            'sb_delete_rollback_backup' => 'ajax_delete_rollback_backup',
            'sb_get_rollback_logs' => 'ajax_get_rollback_logs',
            'sb_clear_rollback_logs' => 'ajax_clear_rollback_logs',
            'sb_cleanup_rollback_backups' => 'ajax_cleanup_rollback_backups',
        ];

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [__CLASS__, $method]);
        }

        // v3.1.6: Public AJAX API for Python Clients (Bypassing REST API blocks)
        add_action('wp_ajax_nopriv_sb_api_create_link', [__CLASS__, 'ajax_api_create_link']);
        add_action('wp_ajax_sb_api_create_link', [__CLASS__, 'ajax_api_create_link']);
    }

    /**
     * 권한 체크 헬퍼
     */
    private static function check_permission()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }
    }

    /**
     * API 키 생성 AJAX
     */
    public static function ajax_generate_api_key()
    {
        self::check_permission();

        $user_id = get_current_user_id();
        $api_key = SB_Helpers::generate_api_key();
        $secret_key = SB_Helpers::generate_secret_key();

        $result = SB_Database::save_api_key($user_id, $api_key, $secret_key);

        if ($result) {
            wp_send_json_success([
                'id' => (int) $result, // $result is insert_id
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'last_used_at' => null,
                'message' => 'API 키가 생성되었습니다.',
            ]);
        } else {
            wp_send_json_error(['message' => 'API 키 생성에 실패했습니다.']);
        }
    }

    /**
     * API 키 삭제 AJAX
     */
    public static function ajax_delete_api_key()
    {
        self::check_permission();

        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;

        if (!$key_id) {
            wp_send_json_error(['message' => '유효하지 않은 키 ID입니다.']);
        }

        // v2.9.22 IDOR Fix: Verify ownership before deletion
        $user_id = get_current_user_id();
        $key_owner = SB_Database::get_api_key_owner($key_id);

        if ($key_owner !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다. 해당 키의 소유자가 아닙니다.']);
        }

        $result = SB_Database::delete_api_key($key_id);

        if ($result) {
            wp_send_json_success(['message' => 'API 키가 삭제되었습니다.']);
        } else {
            wp_send_json_error(['message' => 'API 키 삭제에 실패했습니다.']);
        }
    }

    /**
     * 일반 설정 저장 AJAX (P2 UX 개선)
     */
    public static function ajax_save_general_settings()
    {
        self::check_permission();

        $redirect_delay = isset($_POST['redirect_delay']) ? floatval($_POST['redirect_delay']) : 0;

        // 유효성 검사
        if ($redirect_delay < 0 || $redirect_delay > 10) {
            wp_send_json_error(['message' => '리다이렉션 딜레이는 0~10초 사이여야 합니다.']);
            return;
        }

        $settings = get_option('sb_settings', []);
        $settings['redirect_delay'] = $redirect_delay;

        update_option('sb_settings', $settings);

        wp_send_json_success(['message' => '설정이 저장되었습니다.']);
    }

    /**
     * 설정 저장 AJAX (v2.9.22 Fix: Missing Handler)
     */
    public static function ajax_save_settings()
    {
        self::check_permission();

        $settings = get_option('sb_settings', []);

        if (isset($_POST['redirect_delay'])) {
            $settings['redirect_delay'] = floatval($_POST['redirect_delay']);
        }

        update_option('sb_settings', $settings);

        wp_send_json_success(['message' => '설정이 저장되었습니다.']);
    }

    /**
     * 첫 설치 안내 배너 닫기 AJAX
     */
    public static function ajax_dismiss_welcome()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        // v2.9.22 Security: Added capability check for consistency
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        update_option('sb_first_install_notice', false);

        wp_send_json_success();
    }

    /**
     * 수동 업데이트 강제 체크 AJAX
     */
    public static function ajax_force_check_update()
    {
        self::check_permission();

        // 캐시 삭제 후 즉시 체크
        $update_info = SB_Updater::force_check_release();

        if ($update_info && version_compare($update_info['version'], SB_VERSION, '>')) {
            wp_send_json_success([
                'has_update' => true,
                'current_version' => SB_VERSION,
                'latest_version' => $update_info['version'],
                'download_url' => $update_info['download_url'],
                'release_url' => $update_info['release_url'],
            ]);
        } else {
            wp_send_json_success([
                'has_update' => false,
                'message' => '최신 버전을 사용 중입니다! (v' . SB_VERSION . ')',
            ]);
        }
    }

    /**
     * 리다이렉션 템플릿 저장 AJAX
     */
    public static function ajax_save_redirect_template()
    {
        self::check_permission();

        // v3.1.2 FIX: Strip slashes added by WordPress magic quotes
        $template = isset($_POST['template']) ? stripslashes($_POST['template']) : '';

        if (empty($template)) {
            wp_send_json_error(['message' => '템플릿이 비어있습니다.']);
        }

        // 서버사이드 검증
        $validation = SB_Helpers::validate_template($template);

        if ($validation !== true) {
            wp_send_json_error(['message' => $validation]);
        }

        // v3.0.0 Security: 'unfiltered_html' 권한 체크
        if (!current_user_can('unfiltered_html')) {
            // 권한이 없으면 KSES 필터링 적용 (스크립트 제거됨)
            $template = wp_kses_post($template);
        }

        update_option('sb_redirect_template', $template);

        wp_send_json_success(['message' => '템플릿이 저장되었습니다.']);
    }

    /**
     * 리다이렉션 템플릿 기본값 복원 AJAX
     */
    public static function ajax_reset_redirect_template()
    {
        self::check_permission();

        $default_template = SB_Helpers::get_default_redirect_template();
        update_option('sb_redirect_template', $default_template);

        wp_send_json_success([
            'message' => '기본 템플릿으로 복원되었습니다.',
            'template' => $default_template
        ]);
    }

    /**
     * 백업 다운로드 AJAX
     */
    public static function ajax_download_backup()
    {
        self::check_permission();

        SB_Backup::download_backup();
    }

    /**
     * 백업 복원 AJAX
     */
    public static function ajax_restore_backup()
    {
        self::check_permission(); // v2.9.22: Added missing permission check
        SB_Backup::handle_restore_upload();
    }

    /**
     * 백업 복원 (청크 처리) AJAX (v3.0.0 Scalability)
     */
    public static function ajax_restore_backup_chunk()
    {
        self::check_permission();

        // 1. 데이터 수신
        $chunk_data = isset($_POST['chunk_data']) ? json_decode(wp_unslash($_POST['chunk_data']), true) : [];
        $options = isset($_POST['options']) ? json_decode(wp_unslash($_POST['options']), true) : [];

        // ID Map이 너무 클 경우를 대비해, 클라이언트에서 보내거나 임시 저장소(Transient)를 활용할 수 있음.
        // 여기서는 클라이언트가 보내주는 방식을 가정 (Stateless).
        // 만약 ID Map이 너무 크면 Transient 방식을 고려해야 함.

        if (empty($chunk_data)) {
            wp_send_json_error(['message' => '데이터가 비어있습니다.']);
        }

        // 2. 복원 실행
        // 트랜잭션을 걸어야 할까? 청크 단위라 전체 롤백은 어려움.
        // 청크 내에서는 원자성을 보장하면 좋음.
        SB_Database::start_transaction();

        try {
            $stats = SB_Backup::restore_chunk($chunk_data, $options);
            SB_Database::commit();

            wp_send_json_success([
                'message' => 'Chunk restored',
                'stats' => $stats
            ]);
        } catch (Throwable $e) {
            SB_Database::rollback();
            // 에러 로깅 추가
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SB_Admin_Ajax::ajax_restore_backup_chunk() Error: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
            }
            wp_send_json_error(['message' => '복원 중 오류 발생: ' . $e->getMessage()]);
        }
    }

    /**
     * v3.0.8: 퍼마링크 규칙 자동 재생성 (Auto-fix)
     * 
     * @deprecated v4.0.0 파라미터 방식(?go=slug)으로 더 이상 필요 없음
     * 이 메소드는 호환성을 위해 유지하되 호출되지 않음
     */
    public static function ajax_flush_rewrite_rules()
    {
        // v4.0.0: 파라미터 방식으로 rewrite rules 불필요
        wp_send_json_success([
            'message' => 'v4.0.0: 파라미터 방식으로 퍼마링크 규칙 재생성이 필요 없습니다.',
            'deprecated' => true
        ]);
    }

    /**
     * 시스템 상태 점검 (퍼마링크 404 감지)
     */
    public static function ajax_health_check()
    {
        self::check_permission();

        // 1. 테스트할 단축 링크 가져오기 (공개된 것 중 최신 1개)
        $posts = get_posts([
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        if (empty($posts)) {
            // 테스트할 링크가 없으면 정상(문제없음)으로 간주하되, 
            // 프론트엔드에서 "링크가 없음"을 알 수 있게 상태 전달
            wp_send_json_success(['status' => 'no_links']);
        }

        $test_post = $posts[0];
        $slug = $test_post->post_name;

        // 실제 접속 URL (예: http://site.com/go/abcd)
        $test_url = SB_Helpers::get_short_link_url($slug);

        // v3.0.9: Add cache-busting parameter to bypass CDN/server cache
        $test_url_with_bust = add_query_arg('_sb_health', time(), $test_url);

        // 2. HTTP 요청 보내기 (Loopback Request)
        $response = wp_remote_get($test_url_with_bust, [
            'timeout' => 5,
            'redirection' => 0, // 리다이렉트 따라가지 않음 (302/301 받으면 성공)
            'sslverify' => false, // 로컬 환경 등 고려
            // v3.0.9: Add cache-control headers to prevent cached responses
            'headers' => [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        ]);

        if (is_wp_error($response)) {
            // 연결 실패 (DNS, 방화벽 등)
            // 404는 아니므로 'unknown' 처리하거나, 사용자에게 알림
            wp_send_json_success([
                'status' => 'connection_error',
                'msg' => $response->get_error_message()
            ]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // 3. 상태 판단
        // - 200: 정상 (리다이렉트 화면이 바로 뜰 경우) 또는 404 페이지가 200 반환할 수 있음
        // - 301, 302: 정상 (리다이렉트 응답)
        // - 404: 비정상 (퍼마링크 깨짐) 🚨

        // 404 코드 또는 응답 본문에 "페이지를 찾을 수 없" 포함 시 실패로 판단
        $is_404 = ($response_code === 404);

        /**
         * v3.0.5: Enhanced 404 Detection Patterns
         * 
         * Different WordPress themes and languages return different 404 messages.
         * We need to detect common patterns across:
         * - Korean, English, Japanese, Chinese, German, French, Spanish sites
         * - Popular themes (GeneratePress, Astra, Divi, etc.)
         * 
         * STRATEGY: 
         * 1. Check for common 404 text patterns (negative match)
         * 2. Check for ABSENCE of our bridge page signature (positive match)
         */
        if (!$is_404 && $response_code === 200) {
            $body_lower = mb_strtolower($response_body);

            // Common 404 page indicators across languages/themes
            $error_patterns = [
                // Korean
                '찾을 수 없',     // "찾을 수 없음" / "찾을 수 없습니다"
                '존재하지 않',   // "존재하지 않습니다"
                '페이지가 없',   // "페이지가 없습니다"
                '오류가 발생',   // "오류가 발생했습니다"

                // English
                'not found',
                'page not found',
                'doesn\'t exist',
                'does not exist',
                'no longer available',
                'couldn\'t find',
                'could not find',

                // Japanese
                'ページが見つかりません',
                '見つかりません',

                // German
                'nicht gefunden',
                'seite nicht gefunden',

                // French  
                'page introuvable',
                'n\'existe pas',

                // Spanish
                'página no encontrada',
                'no encontrado',

                // Generic
                'error 404',
                '404 error',
                'oops!',
            ];

            foreach ($error_patterns as $pattern) {
                if (strpos($body_lower, $pattern) !== false) {
                    $is_404 = true;
                    break;
                }
            }

            // Positive check: If our bridge page signature is missing, it's likely 404
            // Our bridge page always contains these unique identifiers
            if (!$is_404) {
                $has_bridge_signature = (
                    strpos($body_lower, 'countdown') !== false ||
                    strpos($body_lower, '즉시 연결') !== false ||
                    strpos($body_lower, 'action-btn') !== false ||
                    strpos($body_lower, 'progress-ring') !== false
                );

                // If none of our bridge page markers found, probably 404
                if (!$has_bridge_signature) {
                    $is_404 = true;
                }
            }
        }

        if ($is_404) {
            wp_send_json_success([
                'status' => 'error_404',
                'test_url' => $test_url,
                'code' => $response_code
            ]);
        } else {
            wp_send_json_success([
                'status' => 'ok',
                'test_url' => $test_url,
                'code' => $response_code
            ]);
        }
    }


    /**
     * 공장 초기화 (Factory Reset)
     * 모든 데이터 삭제 및 초기 상태로 복구
     */
    public static function ajax_factory_reset()
    {
        // v2.9.24: Permission check MUST come first (Security Hygiene)
        self::check_permission();

        // 대량 데이터 삭제 시 타임아웃 방지
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        // 2차 확인 문자열 검증
        $confirmation = isset($_POST['confirmation']) ? sanitize_text_field($_POST['confirmation']) : '';
        if ($confirmation !== 'reset') {
            wp_send_json_error(['message' => '확인 문자가 일치하지 않습니다.']);
        }

        // 요청 ID 생성 (디버깅용)
        $request_id = uniqid('sb_reset_', true);

        global $wpdb;

        // 트랜잭션 시작 (v3.0.0 Update)
        SB_Database::start_transaction();

        try {
            // 1. 커스텀 테이블 Truncate (데이터 비우기)
            $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
            $api_keys_table = $wpdb->prefix . 'sb_api_keys';
            $groups_table = $wpdb->prefix . 'sb_link_groups';
            $stats_table = $wpdb->prefix . 'sb_daily_stats'; // v2.9.27 Added

            $wpdb->query("DELETE FROM $analytics_table");
            $wpdb->query("DELETE FROM $api_keys_table");
            $wpdb->query("DELETE FROM $groups_table");
            $wpdb->query("DELETE FROM $stats_table"); // Fix: Truncate daily stats

            // 2. sb_link 포스트 전체 삭제 (Direct SQL로 대량 삭제 최적화)
            $wpdb->query("
                DELETE pm
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = '" . SB_Post_Type::POST_TYPE . "'
            ");

            $wpdb->query("
                DELETE FROM {$wpdb->posts}
                WHERE post_type = '" . SB_Post_Type::POST_TYPE . "'
            ");

            // 3. 플러그인 옵션 삭제
            delete_option('sb_settings');
            delete_option('sb_redirect_template');
            delete_option('sb_first_install_notice'); // 환영 배너 다시 표시되도록
            // sb_version은 유지 (플러그인 활성화 상태이므로)

            // 성공 시 커밋
            SB_Database::commit();

            // 4. 캐시 및 Rewrite 규칙 초기화
            // v3.1.2: Force clear all transients to fix persistent dashboard numbers
            SB_Analytics::clear_all_cache();

            // v4.0.0: 파라미터 방식으로 flush_rewrite_rules 불필요
            // flush_rewrite_rules();

            wp_send_json_success(['message' => '초기화가 완료되었습니다.']);

        } catch (Throwable $e) {
            // 실패 시 롤백
            SB_Database::rollback();
            // 에러 로그 기록 (요청 ID 및 스택 트레이스 포함)
            error_log('[Smart Bridge] Factory Reset Failed | Request ID: ' . $request_id . ' | Error: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
            wp_send_json_error(['message' => '초기화 중 오류가 발생했습니다. 데이터가 복원되었습니다.']);
        }
    }


    // ========================================
    // Link Groups AJAX Handlers (v2.9.23)
    // ========================================

    /**
     * 그룹 생성 AJAX
     */
    public static function ajax_create_group()
    {
        self::check_permission();

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#667eea';
        $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : null;

        if (empty($name)) {
            wp_send_json_error(['message' => '그룹명을 입력해주세요.']);
        }

        $group_id = SB_Groups::create($name, $color, $description);

        if ($group_id) {
            wp_send_json_success([
                'id' => $group_id,
                'message' => '그룹이 생성되었습니다.',
            ]);
        } else {
            wp_send_json_error(['message' => '그룹 생성에 실패했습니다.']);
        }
    }

    /**
     * 그룹 수정 AJAX
     */
    public static function ajax_update_group()
    {
        self::check_permission();

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $data = [];

        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['color'])) {
            $data['color'] = sanitize_hex_color($_POST['color']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_text_field($_POST['description']);
        }

        if (!$id || empty($data)) {
            wp_send_json_error(['message' => '잘못된 요청입니다.']);
        }

        $result = SB_Groups::update($id, $data);

        if ($result) {
            wp_send_json_success(['message' => '그룹이 수정되었습니다.']);
        } else {
            wp_send_json_error(['message' => '그룹 수정에 실패했습니다.']);
        }
    }

    /**
     * 그룹 삭제 AJAX
     */
    public static function ajax_delete_group()
    {
        self::check_permission();

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(['message' => '잘못된 요청입니다.']);
        }

        $result = SB_Groups::delete($id);

        if ($result) {
            wp_send_json_success(['message' => '그룹이 삭제되었습니다.']);
        } else {
            wp_send_json_error(['message' => '그룹 삭제에 실패했습니다.']);
        }
    }

    /**
     * 그룹 목록 조회 AJAX
     */
    public static function ajax_get_groups()
    {
        self::check_permission();

        $groups = SB_Groups::get_all();



        wp_send_json_success(['groups' => $groups]);
    }

    /**
     * 링크에 그룹 할당 AJAX
     */
    public static function ajax_assign_link_group()
    {
        self::check_permission();

        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;

        // group_id가 0이면 null로 (그룹 해제)
        if ($group_id === 0) {
            $group_id = null;
        }

        if (!$link_id) {
            wp_send_json_error(['message' => '잘못된 요청입니다.']);
        }

        $result = SB_Groups::assign_link($link_id, $group_id);

        if ($result !== false) {
            wp_send_json_success(['message' => '그룹이 할당되었습니다.']);
        } else {
            wp_send_json_error(['message' => '그룹 할당에 실패했습니다.']);
        }
    }



    /**
     * 대시보드 메인 차트 데이터 AJAX (v3.0.0 UX 개선)
     */
    public static function ajax_get_dashboard_stats()
    {
        // v3.0.0 Fix: Relaxed permission to match REST API (Editors can view stats)
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'today_7d';
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        // v3.0.7: Normalize 'all' to null/empty so analytics methods don't apply platform filter
        if ($platform === 'all' || $platform === '') {
            $platform = null;
        }

        // 날짜 범위 계산
        $dates = SB_Helpers::get_date_range($range);
        $start = $dates['start'];
        $end = $dates['end'];

        $analytics = new SB_Analytics();

        // 1. Daily Trend - 사용자가 선택한 기간에 맞게 데이터를 표시
        $daily_trend = $analytics->get_daily_trend(
            substr($start, 0, 10),
            substr($end, 0, 10),
            $platform
        );

        // 2. Weekly & Monthly Trends - 기간 필터에 따라 동적 범위 조정
        // 오늘 + 최근 7일: 최근 2주
        // 최근 30일: 최근 8주
        // 최근 3개월: 최근 12주
        // 최근 6개월: 최근 24주
        // 최근 12개월: 최근 52주
        $weekly_range = self::get_weekly_range_by_filter($range);
        $weekly_trend = $analytics->get_weekly_trend($weekly_range, $platform);
        $monthly_trend = $analytics->get_monthly_trend(30, $platform); // 월간은 30개월 고정

        // 3. Hourly Stats - 선택한 기간의 데이터를 표시
        $clicks_by_hour = $analytics->get_clicks_by_hour($start, $end, $platform);

        // 4. Platform Share - 선택한 기간의 데이터를 표시
        $platform_share = $analytics->get_platform_share($start, $end, $platform);

        // 5. Summary Stats (Total, Today, Growth) - 선택한 기간의 데이터를 표시
        // 증감률 계산: 선택한 기간 vs 이전 기간
        $period_stats = self::get_period_stats_with_growth($start, $end, $range, $platform);

        // 6. Top Links (Filtered) - 선택한 기간의 데이터를 표시
        $top_links = $analytics->get_top_links($start, $end, $platform, 5);

        wp_send_json_success([
            'dailyTrend' => $daily_trend,
            'weeklyTrend' => $weekly_trend,
            'monthlyTrend' => $monthly_trend,
            'clicksByHour' => $clicks_by_hour,
            'platformShare' => $platform_share,
            'summary' => [
                'total_clicks' => $period_stats['total_clicks'] ?? 0,
                'unique_visitors' => $period_stats['unique_visitors'] ?? 0,
                'growth_rate' => $period_stats['growth_rate'] ?? 0,
            ],
            'topLinks' => $top_links
        ]);
    }

    /**
     * 기간 필터에 따른 주간 차트 범위 계산
     *
     * @param string $range 기간 필터 (today_7d, 30d, 90d, 180d, 365d)
     * @return int 주간 차트 범위 (주)
     */
    private static function get_weekly_range_by_filter($range)
    {
        switch ($range) {
            case 'today_7d':
                return 2; // 최근 2주
            case '30d':
                return 8; // 최근 8주
            case '90d':
                return 12; // 최근 12주
            case '180d':
                return 24; // 최근 24주
            case '365d':
                return 52; // 최근 52주
            default:
                return 8; // 기본 8주
        }
    }

    /**
     * 기간 통계 및 증감률 계산
     * 선택한 기간 vs 이전 기간 비교
     *
     * @param string $start 시작일
     * @param string $end 종료일
     * @param string $range 기간 필터
     * @param string|null $platform 플랫폼 필터
     * @return array 통계 데이터
     */
    private static function get_period_stats_with_growth($start, $end, $range, $platform = null)
    {
        global $wpdb;
        $analytics = new SB_Analytics();

        // 1. 현재 기간 통계
        $current_stats = $analytics->get_period_stats($start, $end, $platform);
        
        // 2. 이전 기간 계산
        $previous_dates = self::get_previous_period_range($start, $end, $range);
        $previous_stats = $analytics->get_period_stats(
            $previous_dates['start'],
            $previous_dates['end'],
            $platform
        );

        // 3. 증감률 계산
        $growth_rate = 0;
        if (isset($previous_stats['total_clicks']) && $previous_stats['total_clicks'] > 0) {
            $current_clicks = $current_stats['total_clicks'] ?? 0;
            $previous_clicks = $previous_stats['total_clicks'];
            $growth_rate = (($current_clicks - $previous_clicks) / $previous_clicks) * 100;
            $growth_rate = round($growth_rate, 1);
        }

        return [
            'total_clicks' => $current_stats['total_clicks'] ?? 0,
            'unique_visitors' => $current_stats['unique_visitors'] ?? 0,
            'growth_rate' => $growth_rate,
        ];
    }

    /**
     * 이전 기간 범위 계산
     *
     * @param string $start 현재 기간 시작일
     * @param string $end 현재 기간 종료일
     * @param string $range 기간 필터
     * @return array 이전 기간 ['start' => DateTime, 'end' => DateTime]
     */
    private static function get_previous_period_range($start, $end, $range)
    {
        $current_start = new DateTime($start, wp_timezone());
        $current_end = new DateTime($end, wp_timezone());
        
        // 기간 길이 계산
        $interval = $current_start->diff($current_end);
        $days = $interval->days + 1; // 포함된 일수
        
        // 이전 기간 시작일/종료일 계산
        $previous_end = clone $current_start;
        $previous_end->modify('-1 day')->setTime(23, 59, 59);
        
        $previous_start = clone $previous_end;
        $previous_start->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);

        return [
            'start' => $previous_start->format('Y-m-d H:i:s'),
            'end' => $previous_end->format('Y-m-d H:i:s'),
        ];
    }

    // ========================================
    // Realtime Feed Handler (v2.9.23)
    // ========================================

    /**
     * 실시간 피드 SSE 엔드포인트
     */
    public static function ajax_realtime_feed()
    {
        // v4.2.5 Security: 권한 검증 추가
        check_ajax_referer('sb_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }
        
        // SSE는 일반 AJAX 리턴을 사용하지 않으므로 직접 클래스 호출
        SB_Realtime::start_stream();
        exit;
    }

    /**
     * 📊 일별 통계 데이터 마이그레이션 (Backfill)
     */
    public static function ajax_migrate_daily_stats()
    {
        self::check_permission(); // manage_options 권한

        global $wpdb;

        $log_table = $wpdb->prefix . 'sb_analytics_logs';
        $stats_table = $wpdb->prefix . 'sb_daily_stats';

        // 1. 집계되지 않은 날짜 찾기 (최근 1년 이내, 1회 10일씩)
        // 서브쿼리로 이미 집계된 날짜 제외
        $sql = "SELECT DISTINCT DATE(visited_at) as date
                FROM $log_table
                WHERE visited_at < CURDATE()
                AND DATE(visited_at) NOT IN (SELECT stats_date FROM $stats_table)
                ORDER BY date DESC
                LIMIT 10";

        $dates_to_process = $wpdb->get_col($sql);

        if (empty($dates_to_process)) {
            wp_send_json_success(['message' => '모든 데이터가 최신 상태입니다.', 'completed' => true]);
        }

        $analytics = new SB_Analytics();
        $processed_count = 0;

        foreach ($dates_to_process as $date) {
            $result = $analytics->aggregate_daily_stats($date);
            if ($result) {
                $processed_count++;
            }
        }

        wp_send_json_success([
            'message' => "{$processed_count}일치 데이터가 처리되었습니다.",
            'completed' => false,
            'processed_dates' => $dates_to_process
        ]);
    }
    /**
     * AJAX 기반 링크 생성 API (REST API 대체용)
     * 
     * ⚠️ [중요 아키텍처 결정 사항] ⚠️
     * 이 메소드는 일반적인 REST API (`/wp-json/`) 대신 `admin-ajax.php`를 사용하여
     * 외부 클라이언트(예: Python Script)로부터 링크 생성 요청을 처리합니다.
     * 
     * ❓ 왜 REST API를 쓰지 않는가?
     * - miniOrange, Wordfence 등 일부 강력한 보안 플러그인들은 REST API 엔드포인트(`/wp-json/`) 
     *   진입 자체를 차단하거나, 화이트리스트에 없는 경로를 무조건 거부합니다.
     * - `rest_authentication_errors` 필터로 우회를 시도해도, 플러그인 로드 순서나 
     *   강제적인 차단 정책으로 인해 실패하는 경우가 많습니다.
     * 
     * ✅ 해결책 (AJAX Tunneling)
     * - 워드프레스의 기본 AJAX 채널(`admin-ajax.php`)은 보안 플러그인들이 
     *   기능 호환성을 위해 보통 차단하지 않습니다.
     * - 따라서 이 경로를 통해 요청을 받고, 내부적으로 강력한 자체 보안 인증(HMAC)을 수행합니다.
     * 
     * 🔒 보안
     * - 이 경로는 열려있지만, `SB_Security::authenticate_ajax_request()`를 통과하지 못하면
     *   어떤 작업도 수행하지 않습니다. (HMAC + Timestamp + Nonce 3중 방어)
     * 
     * @since 3.1.6
     */
    public static function ajax_api_create_link()
    {
        // 1. 보안 인증 (HMAC)
        $auth = SB_Security::authenticate_ajax_request();
        if (is_wp_error($auth)) {
            wp_send_json_error([
                'message' => $auth->get_error_message(),
                'code' => $auth->get_error_code()
            ], 403);
        }

        // 2. 요청 파싱
        $body = file_get_contents('php://input');
        $params = json_decode($body, true);

        if (!$params) {
            wp_send_json_error(['message' => 'Invalid JSON body'], 400);
        }

        $target_url = isset($params['target_url']) ? esc_url_raw($params['target_url']) : '';
        $custom_slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : null;

        // 3. URL 검증
        if (!SB_Helpers::validate_url($target_url)) {
            wp_send_json_error(['message' => 'Invalid target URL format'], 400);
        }

        // 4. Slug 처리
        if ($custom_slug) {
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $custom_slug)) {
                wp_send_json_error(['message' => 'Invalid slug format'], 400);
            }
            if (SB_Helpers::slug_exists($custom_slug)) {
                wp_send_json_error(['message' => 'Slug already exists'], 409);
            }
            $slug = $custom_slug;
        } else {
            $slug = SB_Helpers::generate_unique_slug(SB_Helpers::DEFAULT_SLUG_LENGTH, SB_Helpers::MAX_SLUG_RETRIES);
            if (!$slug) {
                wp_send_json_error(['message' => 'Failed to generate unique slug'], 500);
            }
        }

        // 5. 플랫폼 감지
        $platform = SB_Helpers::detect_platform($target_url);

        // 6. 저장
        $post_id = wp_insert_post([
            'post_title' => $slug,
            'post_name' => $slug,
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
                'target_url' => $target_url,
                'platform' => $platform,
                'click_count' => 0,
            ],
        ]);

        if (is_wp_error($post_id) || $post_id === 0) {
            wp_send_json([
                'success' => false,
                'code' => 'db_error',
                'message' => 'Failed to save link',
                'data' => ['status' => 500]
            ]);
        }

        // 7. Race Condition Check
        // 워드프레스는 슬러그 저장 시 자동으로 sanitize_title()을 수행합니다. (예: 대문자 -> 소문자 변환)
        // 따라서 생성된 슬러그($slug)와 저장된 슬러그($final_slug)를 단순 비교하면 대소문자 차이로 인해 실패할 수 있습니다.
        // 이를 방지하기 위해 sanitize_title($slug)와 비교합니다.
        $final_slug = get_post_field('post_name', $post_id);

        if ($final_slug !== sanitize_title($slug) && $final_slug !== $slug) {
            // 진짜 충돌 발생 (suffix가 붙음, 예: abcde-2)
            wp_delete_post($post_id, true);
            wp_send_json([
                'success' => false,
                'code' => 'conflict',
                'message' => 'Slug collision detected',
                'data' => ['status' => 409]
            ]);
        }

        // 8. 성공 응답 (REST API 포맷 유지 - data 래퍼 없이 직접 출력)
        // 중요: 클라이언트에게는 실제로 저장된 $final_slug를 반환해야 합니다. (대소문자 변경 반영)
        wp_send_json([
            'success' => true,
            'short_link' => SB_Helpers::get_short_link_url($final_slug),
            'slug' => $final_slug,
            'target_url' => $target_url,
            'platform' => $platform,
            'created_at' => current_time('c'),
        ]);
    }

    /**
     * 정적 HTML 백업 생성 AJAX (v3.4.0)
     * 
     * 배치 처리: JS에서 반복 호출하여 대용량 링크도 안정적으로 처리
     */
    public static function ajax_generate_static_backup()
    {
        self::check_permission();

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1000;
        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $total_links = isset($_POST['total_links']) ? intval($_POST['total_links']) : 0;

        $result = SB_Backup::generate_static_backup($offset, $limit, $file_id, $total_links);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    // ========================================
    // Link Management Tab Enhancements (v4.3.0)
    // ========================================

    /**
     * 대량 링크 삭제 AJAX
     */
    public static function ajax_bulk_delete_links()
    {
        self::check_permission();

        $link_ids = isset($_POST['link_ids']) ? array_map('intval', $_POST['link_ids']) : [];

        if (empty($link_ids)) {
            wp_send_json_error(['message' => '삭제할 링크를 선택해주세요.']);
        }

        $deleted_count = 0;
        $error_count = 0;

        foreach ($link_ids as $link_id) {
            $result = wp_delete_post($link_id, true); // true: 휴지통으로 이동하지 않고 완전 삭제
            if ($result) {
                $deleted_count++;
            } else {
                $error_count++;
            }
        }

        // 플랫폼 캐시 삭제
        SB_Helpers::clear_platforms_cache();

        wp_send_json_success([
            'message' => sprintf('%d개 링크가 삭제되었습니다.', $deleted_count),
            'deleted_count' => $deleted_count,
            'error_count' => $error_count
        ]);
    }

    /**
     * 대량 플랫폼 변경 AJAX
     */
    public static function ajax_bulk_update_platform()
    {
        self::check_permission();

        $link_ids = isset($_POST['link_ids']) ? array_map('intval', $_POST['link_ids']) : [];
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';

        if (empty($link_ids)) {
            wp_send_json_error(['message' => '변경할 링크를 선택해주세요.']);
        }

        if (empty($platform)) {
            wp_send_json_error(['message' => '플랫폼을 선택해주세요.']);
        }

        $updated_count = 0;
        $error_count = 0;

        foreach ($link_ids as $link_id) {
            $result = update_post_meta($link_id, 'platform', $platform);
            if ($result !== false) {
                $updated_count++;
            } else {
                $error_count++;
            }
        }

        // 플랫폼 캐시 삭제
        SB_Helpers::clear_platforms_cache();

        wp_send_json_success([
            'message' => sprintf('%d개 링크의 플랫폼이 변경되었습니다.', $updated_count),
            'updated_count' => $updated_count,
            'error_count' => $error_count
        ]);
    }

    /**
     * 필터링된 링크 수 조회 AJAX
     */
    public static function ajax_get_filtered_link_count()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        $clicks_min = isset($_POST['clicks_min']) ? intval($_POST['clicks_min']) : 0;
        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '';

        $count = SB_Post_Type::get_filtered_link_count($platform, $clicks_min, $date_range);

        wp_send_json_success([
            'count' => $count,
            'message' => sprintf('총 %d개의 링크', $count)
        ]);
    }

    // ========================================
    // P3 기능 개선: 업데이트 및 롤백 AJAX Handlers
    // ========================================

    /**
     * 업데이트 확인 AJAX
     */
    public static function ajax_check_update()
    {
        self::check_permission();

        $update_status = SB_Updater::get_update_status();

        wp_send_json_success($update_status);
    }

    /**
     * 업데이트 다운로드 AJAX
     */
    public static function ajax_download_update()
    {
        self::check_permission();

        $download_url = isset($_POST['download_url']) ? esc_url_raw($_POST['download_url']) : '';

        if (empty($download_url)) {
            wp_send_json_error(['message' => '다운로드 URL이 없습니다.']);
        }

        $result = SB_Updater::download_update($download_url);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * 업데이트 알림 숨기기 AJAX
     */
    public static function ajax_dismiss_update_notice()
    {
        self::check_permission();

        $version = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : '';

        if (empty($version)) {
            wp_send_json_error(['message' => '버전 정보가 없습니다.']);
        }

        $result = SB_Updater::dismiss_update_notice($version);

        if ($result) {
            wp_send_json_success(['message' => '알림이 숨겨졌습니다.']);
        } else {
            wp_send_json_error(['message' => '알림 숨기기에 실패했습니다.']);
        }
    }

    /**
     * 업데이트 상태 조회 AJAX
     */
    public static function ajax_get_update_status()
    {
        self::check_permission();

        $status = SB_Updater::get_update_status();

        wp_send_json_success($status);
    }

    /**
     * 업데이트 로그 삭제 AJAX
     */
    public static function ajax_clear_update_logs()
    {
        self::check_permission();

        $result = SB_Updater::clear_update_logs();

        if ($result) {
            wp_send_json_success(['message' => '업데이트 로그가 삭제되었습니다.']);
        } else {
            wp_send_json_error(['message' => '로그 삭제에 실패했습니다.']);
        }
    }

    /**
     * 롤백 백업 파일 목록 조회 AJAX
     */
    public static function ajax_get_rollback_backups()
    {
        self::check_permission();

        $backups = SB_Backup::get_rollback_backups();

        wp_send_json_success(['backups' => $backups]);
    }

    /**
     * 롤백 실행 AJAX
     */
    public static function ajax_perform_rollback()
    {
        self::check_permission();

        $backup_file = isset($_POST['backup_file']) ? sanitize_text_field($_POST['backup_file']) : '';
        $auto_backup = isset($_POST['auto_backup']) ? filter_var($_POST['auto_backup'], FILTER_VALIDATE_BOOLEAN) : true;

        if (empty($backup_file)) {
            wp_send_json_error(['message' => '백업 파일명이 없습니다.']);
        }

        // 대량 데이터 처리 시 타임아웃 방지
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $result = SB_Backup::perform_rollback($backup_file, $auto_backup);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * 롤백 백업 파일 삭제 AJAX
     */
    public static function ajax_delete_rollback_backup()
    {
        self::check_permission();

        $filename = isset($_POST['filename']) ? sanitize_text_field($_POST['filename']) : '';

        if (empty($filename)) {
            wp_send_json_error(['message' => '파일명이 없습니다.']);
        }

        $result = SB_Backup::delete_rollback_backup($filename);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * 롤백 로그 조회 AJAX
     */
    public static function ajax_get_rollback_logs()
    {
        self::check_permission();

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $logs = SB_Backup::get_rollback_logs($limit);

        wp_send_json_success(['logs' => $logs]);
    }

    /**
     * 롤백 로그 삭제 AJAX
     */
    public static function ajax_clear_rollback_logs()
    {
        self::check_permission();

        $result = SB_Backup::clear_rollback_logs();

        if ($result) {
            wp_send_json_success(['message' => '롤백 로그가 삭제되었습니다.']);
        } else {
            wp_send_json_error(['message' => '로그 삭제에 실패했습니다.']);
        }
    }

    /**
     * 오래된 롤백 백업 파일 정리 AJAX
     */
    public static function ajax_cleanup_rollback_backups()
    {
        self::check_permission();

        $days_old = isset($_POST['days_old']) ? intval($_POST['days_old']) : 30;
        $deleted_count = SB_Backup::cleanup_rollback_backups($days_old);

        wp_send_json_success([
            'message' => sprintf('%d개의 오래된 백업 파일이 삭제되었습니다.', $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
}

