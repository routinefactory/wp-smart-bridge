<?php
/**
 * 관리자 페이지 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Admin
{

    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;

    /**
     * 싱글톤 패턴
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 생성자
     */
    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_sb_generate_api_key', [$this, 'ajax_generate_api_key']);
        add_action('wp_ajax_sb_delete_api_key', [$this, 'ajax_delete_api_key']);
        add_action('wp_ajax_sb_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_sb_dismiss_welcome', [$this, 'ajax_dismiss_welcome']);
        add_action('wp_ajax_sb_force_check_update', [$this, 'ajax_force_check_update']);
        add_action('wp_ajax_sb_save_redirect_template', [$this, 'ajax_save_redirect_template']);
        add_action('wp_ajax_sb_reset_redirect_template', [$this, 'ajax_reset_redirect_template']);
        add_action('wp_ajax_sb_download_backup', [$this, 'ajax_download_backup']);
        add_action('wp_ajax_sb_download_backup', [$this, 'ajax_download_backup']);
        add_action('wp_ajax_sb_restore_backup', [$this, 'ajax_restore_backup']);
        add_action('wp_ajax_sb_health_check', [$this, 'ajax_health_check']);
    }

    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu()
    {
        // 메인 메뉴
        add_menu_page(
            'Smart Bridge',
            'Smart Bridge',
            'edit_posts',
            'smart-bridge',
            [$this, 'render_dashboard'],
            'dashicons-admin-links',
            30
        );

        // 대시보드 서브메뉴
        add_submenu_page(
            'smart-bridge',
            '대시보드',
            '대시보드',
            'edit_posts',
            'smart-bridge',
            [$this, 'render_dashboard']
        );

        // 링크 관리 서브메뉴
        add_submenu_page(
            'smart-bridge',
            '링크 관리',
            '링크 관리',
            'edit_posts',
            'edit.php?post_type=sb_link'
        );

        // 설정 서브메뉴
        add_submenu_page(
            'smart-bridge',
            '설정',
            '설정',
            'manage_options',
            'smart-bridge-settings',
            [$this, 'render_settings']
        );
    }

    /**
     * 에셋 로드
     */
    public function enqueue_assets($hook)
    {
        // Smart Bridge 페이지에서만 로드
        if (
            strpos($hook, 'smart-bridge') === false &&
            strpos($hook, 'sb_link') === false
        ) {
            return;
        }

        // Chart.js CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        // 관리자 CSS
        wp_enqueue_style(
            'sb-admin',
            SB_PLUGIN_URL . 'admin/css/sb-admin.css',
            [],
            SB_VERSION
        );

        // 관리자 JS
        wp_enqueue_script(
            'sb-admin',
            SB_PLUGIN_URL . 'admin/js/sb-admin.js',
            ['jquery', 'chartjs'],
            SB_VERSION,
            true
        );

        // JS 변수 전달
        wp_localize_script('sb-admin', 'sbAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sb/v1/'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxNonce' => wp_create_nonce('sb_admin_nonce'),
        ]);
    }

    /**
     * 대시보드 페이지 렌더링
     */
    public function render_dashboard()
    {
        include SB_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * 설정 페이지 렌더링
     */
    public function render_settings()
    {
        include SB_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * API 키 생성 AJAX
     */
    public function ajax_generate_api_key()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

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
    public function ajax_delete_api_key()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;

        if (!$key_id) {
            wp_send_json_error(['message' => '유효하지 않은 키 ID입니다.']);
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
    public function ajax_save_settings()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

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
    public function ajax_dismiss_welcome()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        update_option('sb_first_install_notice', false);

        wp_send_json_success();
    }

    /**
     * 수동 업데이트 강제 체크 AJAX
     */
    public function ajax_force_check_update()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

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
    public function ajax_save_redirect_template()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

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
    public function ajax_reset_redirect_template()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

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
    public function ajax_download_backup()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        SB_Backup::download_backup();
    }

    /**
     * 백업 복원 AJAX
     */
    public function ajax_restore_backup()
    {
        SB_Backup::handle_restore_upload();
    }

    /**
     * 시스템 상태 점검 (퍼마링크 404 감지)
     */
    public function ajax_health_check()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

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

        // 3. 상태 판단
        // - 200: 정상 (리다이렉트 화면이 바로 뜰 경우)
        // - 301, 302: 정상 (리다이렉트 응답)
        // - 404: 비정상 (퍼마링크 깨짐) 🚨
        if ($response_code === 404) {
            wp_send_json_success([
                'status' => 'error_404',
                'test_url' => $test_url
            ]);
        } else {
            wp_send_json_success([
                'status' => 'ok',
                'code' => $response_code
            ]);
        }
    }
}
