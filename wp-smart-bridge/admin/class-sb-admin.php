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
        add_action('wp_ajax_sb_check_update', [$this, 'ajax_check_update']);
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
            $settings['redirect_delay'] = intval($_POST['redirect_delay']);
        }

        if (isset($_POST['default_loading_message'])) {
            $settings['default_loading_message'] = SB_Security::sanitize_loading_message(
                $_POST['default_loading_message']
            );
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
     * 업데이트 확인 AJAX
     */
    public function ajax_check_update()
    {
        check_ajax_referer('sb_admin_nonce', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        // 업데이트 체크 강제 실행 (캐시 삭제 포함)
        SB_Updater::force_check();

        wp_send_json_success(['message' => '업데이트를 확인했습니다. 최신 버전이 있는 경우 워드프레스 업데이트 메뉴에 표시됩니다.']);
    }
}
