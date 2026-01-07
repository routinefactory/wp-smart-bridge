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
            'sb_flush_rewrite_rules' => 'ajax_flush_rewrite_rules', // v3.0.8 Auto-fix permalinks
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
                'api_key' => $api_key,
                'secret_key' => $secret_key,
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
        $chunk_data = isset($_POST['chunk_data']) ? json_decode(stripslashes($_POST['chunk_data']), true) : [];
        $options = isset($_POST['options']) ? json_decode(stripslashes($_POST['options']), true) : [];

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
        } catch (Exception $e) {
            SB_Database::rollback();
            wp_send_json_error(['message' => '복원 중 오류 발생: ' . $e->getMessage()]);
        }
    }

    /**
     * v3.0.8: 퍼마링크 규칙 자동 재생성 (Auto-fix)
     * 
     * 404 에러 감지 시 프론트엔드에서 자동으로 호출하여
     * flush_rewrite_rules()를 실행합니다.
     * 
     * 보안: manage_options 권한 필요 (관리자 전용)
     */
    public static function ajax_flush_rewrite_rules()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        // 관리자 권한 필수 (flush_rewrite_rules는 민감한 작업)
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => '관리자 권한이 필요합니다.',
                'can_auto_fix' => false
            ]);
        }

        // WordPress 내장 함수로 퍼마링크 규칙 재생성
        // v3.0.9: Use hard flush (true) to immediately update .htaccess/rules
        flush_rewrite_rules(true);

        wp_send_json_success([
            'message' => '퍼마링크 규칙이 재생성되었습니다.',
            'flushed' => true
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
        $slug = $test_post->post_title;

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
        $confirmation = isset($_POST['confirmation']) ? $_POST['confirmation'] : '';
        if ($confirmation !== 'reset') {
            wp_send_json_error(['message' => '확인 문자가 일치하지 않습니다.']);
        }

        global $wpdb;

        // 트랜잭션 시작 (v3.0.0 Update)
        SB_Database::start_transaction();

        try {
            // 1. 커스텀 테이블 Truncate (데이터 비우기)
            $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
            $api_keys_table = $wpdb->prefix . 'sb_api_keys';
            $groups_table = $wpdb->prefix . 'sb_link_groups';

            $wpdb->query("DELETE FROM $analytics_table");
            $wpdb->query("DELETE FROM $api_keys_table");
            $wpdb->query("DELETE FROM $groups_table");

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
            wp_cache_flush();
            flush_rewrite_rules();

            wp_send_json_success(['message' => '초기화가 완료되었습니다.']);

        } catch (Exception $e) {
            // 실패 시 롤백
            SB_Database::rollback();
            // 에러 로그 기록
            error_log('[Smart Bridge] Factory Reset Failed: ' . $e->getMessage());
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

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '30d';
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        // v3.0.7: Normalize 'all' to null/empty so analytics methods don't apply platform filter
        if ($platform === 'all' || $platform === '') {
            $platform = null;
        }

        // 날짜 범위 계산
        if ($range === 'custom') {
            $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
        } else {
            $dates = SB_Helpers::get_date_range($range);
            $start = $dates['start'];
            $end = $dates['end'];
        }

        $analytics = new SB_Analytics();

        // 1. Daily Trend
        $daily_trend = $analytics->get_daily_trend($start, $end, $platform);

        // v3.0.4: Weekly & Monthly Trends
        $weekly_trend = $analytics->get_weekly_trend(30, $platform); // Note: Weekly/Monthly usually fixed range, but passing platform if supported
        $monthly_trend = $analytics->get_monthly_trend(30, $platform);

        // 2. Hourly Stats
        $clicks_by_hour = $analytics->get_clicks_by_hour($start, $end, $platform);

        // 3. Platform Share
        $platform_share = $analytics->get_platform_share($start, $end, $platform);

        // 4. Summary Stats (Total, Today, Growth) - Filtered
        // Note: For 'today' stats, we might need separate logic if range is not 'today'
        // But dashboard usually shows "Total Clicks (in range)" or "Total Clicks (All Time)"?
        // User wants filters to apply to EVERYTHING.
        // Let's get "Total Clicks in Period" and "Unique Visitors in Period"
        $period_stats = $analytics->get_period_stats($start, $end, $platform); // Need to check if this method exists or create it

        // 5. Top Links (Filtered)
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

    // ========================================
    // Realtime Feed Handler (v2.9.23)
    // ========================================

    /**
     * 실시간 피드 SSE 엔드포인트
     */
    public static function ajax_realtime_feed()
    {
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
            ], 403); // Status code setting is tricky in admin-ajax, but WP sends 200 usually. We rely on JSON 'success': false
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
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
                'target_url' => $target_url,
                'platform' => $platform,
                'click_count' => 0,
            ],
        ]);

        if (is_wp_error($post_id) || $post_id === 0) {
            status_header(500);
            wp_send_json([
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
            status_header(409);
            wp_send_json([
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
}

