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

        // AJAX 핸들러 초기화 (Controller 분리)
        SB_Admin_Ajax::init();
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

        // 관리자 JS Modules
        wp_enqueue_script(
            'sb-chart',
            SB_PLUGIN_URL . 'admin/js/sb-chart.js',
            ['jquery', 'chartjs'],
            SB_VERSION,
            true
        );

        wp_enqueue_script(
            'sb-ui',
            SB_PLUGIN_URL . 'admin/js/sb-ui.js',
            ['jquery'],
            SB_VERSION,
            true
        );

        // 관리자 JS (Main Entry)
        wp_enqueue_script(
            'sb-admin',
            SB_PLUGIN_URL . 'admin/js/sb-admin.js',
            ['jquery', 'chartjs', 'sb-chart', 'sb-ui'], // Dependencies added
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
        // -------------------------------------------------------------------------
        // View Logic Extraction (Architecture Refactoring)
        // -------------------------------------------------------------------------

        // 1. API 키 상태 확인
        $user_api_keys = SB_Database::get_user_api_keys(get_current_user_id());
        $has_api_keys = !empty($user_api_keys);

        // 2. 분석 데이터 준비
        $analytics = new SB_Analytics();
        $date_range = SB_Helpers::get_date_range('30d');

        // 일일 통계 (오늘)
        $today_total_clicks = $analytics->get_today_total_clicks();
        $today_unique_visitors = $analytics->get_today_unique_visitors();

        // 누적 통계 (전체 기간)
        $cumulative_total_clicks = $analytics->get_cumulative_total_clicks();
        $cumulative_unique_visitors = $analytics->get_cumulative_unique_visitors();

        // 기존 통계 호환성 유지
        $total_clicks = $analytics->get_total_clicks($date_range['start'], $date_range['end']);
        $unique_visitors = $analytics->get_unique_visitors($date_range['start'], $date_range['end']);
        $growth_rate = $analytics->get_growth_rate();
        $active_links = $analytics->get_active_links_count();
        $clicks_by_hour = $analytics->get_clicks_by_hour($date_range['start'], $date_range['end']);
        $platform_share = $analytics->get_platform_share($date_range['start'], $date_range['end']);
        $daily_trend = $analytics->get_daily_trend($date_range['start'], $date_range['end']);

        // 메타데이터
        $available_platforms = $analytics->get_available_platforms();

        // 인기 링크 (현재 필터 적용)
        $top_links = $analytics->get_top_links(
            $date_range['start'],
            $date_range['end'],
            null
        );

        // 전체 기간 인기 링크
        $alltime_top_links = $analytics->get_all_time_top_links(20);

        // 3. 업데이트 정보 확인
        $update_info = SB_Updater::check_github_release();
        $has_update = false;
        $latest_version = SB_VERSION;
        $download_url = '';

        if ($update_info && version_compare($update_info['version'], SB_VERSION, '>')) {
            $has_update = true;
            $latest_version = $update_info['version'];
            $download_url = $update_info['download_url'];
        }

        // View 로드 (위의 변수들이 View 내에서 사용 가능)
        include SB_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * 설정 페이지 렌더링
     */
    public function render_settings()
    {
        // -------------------------------------------------------------------------
        // View Logic Extraction
        // -------------------------------------------------------------------------
        $user_id = get_current_user_id();
        $api_keys = SB_Database::get_user_api_keys($user_id);
        $settings = get_option('sb_settings', []);
        $redirect_delay = isset($settings['redirect_delay']) ? $settings['redirect_delay'] : 0;

        include SB_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // AJAX Methods Moved to includes/class-sb-admin-ajax.php
}
