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
        ];

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [__CLASS__, $method]);
        }
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
     * 설정 저장 AJAX
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

        $template = isset($_POST['template']) ? $_POST['template'] : '';

        if (empty($template)) {
            wp_send_json_error(['message' => '템플릿이 비어있습니다.']);
        }

        // 서버사이드 검증
        $validation = SB_Helpers::validate_template($template);

        if ($validation !== true) {
            wp_send_json_error(['message' => $validation]);
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
     * 시스템 상태 점검 (퍼마링크 404 감지)
     */
    public static function ajax_health_check()
    {
        self::check_permission();

        // 1. 테스트할 단축 링크 가져오기 (공개된 것 중 최신 1개)
        $posts = get_posts([
            'post_type' => 'sb_link',
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

        // 2. HTTP 요청 보내기 (Loopback Request)
        $response = wp_remote_get($test_url, [
            'timeout' => 5,
            'redirection' => 0, // 리다이렉트 따라가지 않음 (302/301 받으면 성공)
            'sslverify' => false // 로컬 환경 등 고려
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

        // 일부 테마는 404를 200으로 반환하므로 본문도 체크
        if (!$is_404 && $response_code === 200) {
            $body_lower = mb_strtolower($response_body);
            if (
                strpos($body_lower, '찾을 수 없') !== false ||
                strpos($body_lower, 'not found') !== false ||
                strpos($body_lower, 'page not found') !== false ||
                strpos($body_lower, '404') !== false
            ) {
                $is_404 = true;
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

        // 1. 커스텀 테이블 Truncate (데이터 비우기)
        $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
        $api_keys_table = $wpdb->prefix . 'sb_api_keys';

        $wpdb->query("TRUNCATE TABLE $analytics_table");
        $wpdb->query("TRUNCATE TABLE $api_keys_table");

        // 2. sb_link 포스트 전체 삭제 (Direct SQL로 대량 삭제 최적화)
        $wpdb->query("
            DELETE pm
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'sb_link'
        ");

        $wpdb->query("
            DELETE FROM {$wpdb->posts}
            WHERE post_type = 'sb_link'
        ");

        // 3. 플러그인 옵션 삭제
        delete_option('sb_settings');
        delete_option('sb_redirect_template');
        delete_option('sb_first_install_notice'); // 환영 배너 다시 표시되도록
        // sb_version은 유지 (플러그인 활성화 상태이므로)

        // 4. 캐시 및 Rewrite 규칙 초기화
        wp_cache_flush();
        flush_rewrite_rules();

        wp_send_json_success(['message' => '초기화가 완료되었습니다.']);
    }
}
