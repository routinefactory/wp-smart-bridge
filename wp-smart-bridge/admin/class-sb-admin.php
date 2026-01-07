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
     * Chart.js Version
     */
    const CHART_JS_VERSION = '4.4.1';

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
            'edit.php?post_type=' . SB_Post_Type::POST_TYPE
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
        // Smart Bridge 페이지 또는 sb_link 포스트 타입 페이지에서만 로드
        $screen = get_current_screen();
        $is_sb_page = strpos($hook, 'smart-bridge') !== false;
        $is_sb_post_type = ($screen && $screen->post_type === SB_Post_Type::POST_TYPE);

        if (!$is_sb_page && !$is_sb_post_type) {
            return;
        }

        // Chart.js CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@' . self::CHART_JS_VERSION . '/dist/chart.umd.min.js',
            [],
            self::CHART_JS_VERSION,
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
            ['jquery', 'chartjs', 'sb-chart', 'sb-ui'],
            SB_VERSION,
            true
        );

        // 대시보드 전용 JS
        if ($is_sb_page && isset($_GET['page']) && $_GET['page'] === 'smart-bridge') {
            wp_enqueue_script(
                'sb-dashboard',
                SB_PLUGIN_URL . 'admin/js/sb-dashboard.js',
                ['jquery', 'sb-admin'],
                SB_VERSION,
                true
            );
        }

        // JS 변수 전달
        wp_localize_script('sb-admin', 'sbAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sb/v1/'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxNonce' => wp_create_nonce('sb_admin_nonce'),
        ]);

        // I18n 문자열 (나중에 __() 함수로 감싸서 .pot 파일 생성 가능)
        wp_localize_script('sb-admin', 'sb_i18n', [
            'confirm_delete' => __('정말 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.', 'sb'),
            'confirm_reset' => __('정말 초기화하시겠습니까? 모든 데이터가 삭제됩니다.', 'sb'),
            'confirm_restore' => __('정말 복원하시겠습니까? 현재 데이터가 덮어씌워집니다.', 'sb'),
            'prompt_reset' => __('초기화하려면 "RESET"이라고 입력하세요.', 'sb'),
            'success_saved' => __('저장되었습니다.', 'sb'),
            'success_deleted' => __('삭제되었습니다.', 'sb'),
            'error_occurred' => __('오류가 발생했습니다.', 'sb'),
            'loading' => __('로딩 중...', 'sb'),
            'no_data' => __('데이터 없음', 'sb'),
            // Dynamic Dashboard Labels (v3.0.7)
            'today_total_clicks' => __('오늘 전체 클릭', 'sb'),
            'today_unique_visitors' => __('오늘 고유 클릭 (UV)', 'sb'),
            'yesterday_total_clicks' => __('어제 전체 클릭', 'sb'),
            'yesterday_unique_visitors' => __('어제 고유 클릭 (UV)', 'sb'),
            'period_total_clicks' => __('선택 기간 전체 클릭', 'sb'),
            'period_unique_visitors' => __('선택 기간 고유 클릭 (UV)', 'sb'),
            'today' => __('📅 Today', 'sb'),
            'yesterday' => __('📅 Yesterday', 'sb'),
            'selected_period' => __('📅 Selected Period', 'sb'),

            'new_version' => __('새 버전({version})이 있습니다! 다운로드 페이지로 이동하시겠습니까?', 'sb'),
            'group_name_placeholder' => __('새 그룹 이름', 'sb'),
            'group_name_empty' => __('그룹 이름을 입력해주세요.', 'sb'),
            'click' => __('클릭', 'sb'),
            'visitor' => __('방문자', 'sb'),
            'close' => __('닫기', 'sb'),
            'retry' => __('재시도', 'sb'),
            'yes' => __('예', 'sb'),
            'no' => __('아니오', 'sb'),
            'title_confirm' => __('확인', 'sb'),
            'title_alert' => __('알림', 'sb'),
            'title_prompt' => __('입력', 'sb'),
            'reset_complete' => __('초기화 완료. 페이지를 새로고침합니다.', 'sb'),
            'latest_version' => __('최신 버전을 사용 중입니다.', 'sb'),
            'download_link' => __('다운로드 이동', 'sb'),
            'cancelled' => __('취소되었습니다.', 'sb'),
            'factory_reset' => __('Factory Reset', 'sb'),
            // Chart A11y Labels
            'chart_daily_trend' => __('일별 트래픽 추세 차트', 'sb'),
            'chart_weekly_trend' => __('주간 트래픽 추세 차트', 'sb'),
            'chart_monthly_trend' => __('월간 트래픽 추세 차트', 'sb'),
            'chart_hourly' => __('시간대별 클릭 통계 차트', 'sb'),
            'chart_platform' => __('플랫폼별 점유율 차트', 'sb'),
            'chart_referer' => __('상위 유입 경로 차트', 'sb'),
            'chart_device' => __('기기 유형별 통계', 'sb'),
            'chart_os' => __('운영체제별 통계', 'sb'),
            'chart_browser' => __('브라우저별 통계', 'sb'),
            'chart_weekday' => __('요일별 클릭 패턴', 'sb'),
            'target_url' => __('타겟 URL', 'sb'),
            'platform' => __('플랫폼', 'sb'),
            'clicks' => __('클릭 수', 'sb'),
            'actions' => __('액션', 'sb'),
            'edit' => __('수정', 'sb'),
            // Dashboard Text
            'top_links_title' => __('📈 인기 링크 (현재 필터 기준)', 'sb'),
            'toggle_advanced_show' => __('OS & 브라우저 상세 보기', 'sb'),
            'toggle_advanced_hide' => __('상세 보기 접기', 'sb'),
            // Additional UI Text
            'hour_suffix' => __('시', 'sb'),
            'current_period' => __('현재 기간', 'sb'),
            'previous_period' => __('이전 기간', 'sb'),
            'link_hourly_chart' => __('링크별 시간대 분포', 'sb'),
            'compare_mode_on' => __('비교 모드 활성화', 'sb'),
            'compare_mode_off' => __('비교 모드 비활성화', 'sb'),
            'group_delete' => __('그룹 삭제', 'sb'),
            'saving' => __('저장 중...', 'sb'),
            'save_failed' => __('저장 실패', 'sb'),
            'template_save' => __('템플릿 저장', 'sb'),
            'template_reset' => __('템플릿 초기화', 'sb'),
            'template_reset_confirm' => __('정말 기본 템플릿으로 복원하시겠습니까? 현재 템플릿은 사라집니다.', 'sb'),
            'reset' => __('초기화', 'sb'),
            'copied_to_clipboard' => __('링크가 클립보드에 복사되었습니다!', 'sb'),
            'slug_cannot_change' => __('단축 주소는 변경할 수 없습니다', 'sb'),
            'delete' => __('삭제', 'sb'),
            // Additional error and validation messages
            'error_prefix' => __('오류', 'sb'),
            'server_error' => __('서버 통신 오류', 'sb'),
            'realtime_not_supported' => __('이 브라우저는 실시간 피드를 지원하지 않습니다.', 'sb'),
            'all_placeholders_ok' => __('모든 필수 Placeholder가 포함되어 있습니다!', 'sb'),
            'missing_placeholders' => __('누락된 Placeholder', 'sb'),
            'network_error' => __('통신 오류가 발생했습니다.', 'sb'),
            'template_restored' => __('기본 템플릿으로 복원되었습니다!', 'sb'),
            'clipboard_fallback' => __('클립보드 접근 실패. 수동으로 복사하세요:', 'sb'),
            'clipboard_not_supported' => __('이 브라우저는 자동 복사를 지원하지 않습니다. 복사하세요:', 'sb'),
            'slug_warning' => __('단축 주소는 생성 후 변경할 수 없습니다. 링크 무결성을 위해 영구적으로 고정됩니다.', 'sb'),
            'click_unit' => __('클릭', 'sb'),
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

        // -------------------------------------------------------------------------
        // View Logic Extraction (Arch Refactoring: P3)
        // -------------------------------------------------------------------------
        // Data is prepared by ViewModel to keep Controller clean
        $data = SB_Admin_View_Model::get_dashboard_data();

        // Extract variables into current scope so the View can use them
        // ($today_total_clicks, $today_unique_visitors, $top_links, etc.)
        extract($data);

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
        // -------------------------------------------------------------------------
        // View Logic Extraction
        // -------------------------------------------------------------------------
        $data = SB_Admin_View_Model::get_settings_data();
        extract($data);

        include SB_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // AJAX Methods Moved to includes/class-sb-admin-ajax.php
}
