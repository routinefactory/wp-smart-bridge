<?php
/**
 * Plugin Name: WP Smart Bridge
 * Plugin URI: https://github.com/routinefactory/wp-smart-bridge
 * Description: 제휴 마케팅용 단축 링크 자동화 플러그인 - HMAC-SHA256 보안 인증, SaaS급 분석 기능 포함
 * Version: 2.6.4
 * Author: Routine Factory
 * Author URI: https://github.com/routinefactory
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-smart-bridge
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * 
 * GitHub Plugin URI: https://github.com/routinefactory/wp-smart-bridge
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('SB_VERSION', '2.6.4');
define('SB_PLUGIN_FILE', __FILE__);
define('SB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 클래스 파일 로드
require_once SB_PLUGIN_DIR . 'includes/class-sb-database.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-helpers.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-security.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-post-type.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-rest-api.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-redirect.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-analytics.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-updater.php';

// 관리자 페이지 로드
if (is_admin()) {
    require_once SB_PLUGIN_DIR . 'admin/class-sb-admin.php';

    // 자동 업데이터 초기화
    SB_Updater::get_instance();
}

/**
 * 플러그인 메인 클래스
 */
class WP_Smart_Bridge
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
        $this->init_hooks();
    }

    /**
     * 훅 초기화
     */
    private function init_hooks()
    {
        // 플러그인 활성화/비활성화 훅
        register_activation_hook(SB_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SB_PLUGIN_FILE, [$this, 'deactivate']);

        // 초기화
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'init_rest_api']);

        // 플러그인 로드 완료
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    /**
     * 플러그인 활성화
     */
    public function activate()
    {
        // 데이터베이스 테이블 생성
        SB_Database::create_tables();

        // 커스텀 포스트 타입 등록
        SB_Post_Type::register();

        // Rewrite 규칙 플러시
        flush_rewrite_rules();

        // 버전 저장
        update_option('sb_version', SB_VERSION);

        // 기본 설정 저장
        if (!get_option('sb_settings')) {
            update_option('sb_settings', [
                'redirect_delay' => 0,
                'default_loading_message' => '잠시만 기다려주세요...',
                'ip_hash_salt' => wp_generate_password(32, true, true),
            ]);
        }
    }

    /**
     * 플러그인 비활성화
     */
    public function deactivate()
    {
        // Rewrite 규칙 플러시
        flush_rewrite_rules();
    }

    /**
     * 초기화
     */
    public function init()
    {
        // 커스텀 포스트 타입 등록
        SB_Post_Type::register();

        // 리다이렉트 핸들러 초기화
        SB_Redirect::init();
    }

    /**
     * REST API 초기화
     */
    public function init_rest_api()
    {
        SB_Rest_API::register_routes();
    }

    /**
     * 플러그인 로드 완료
     */
    public function on_plugins_loaded()
    {
        // 텍스트 도메인 로드
        load_plugin_textdomain(
            'wp-smart-bridge',
            false,
            dirname(SB_PLUGIN_BASENAME) . '/languages'
        );

        // 관리자 페이지 초기화
        if (is_admin()) {
            SB_Admin::get_instance();
        }
    }
}

// 플러그인 인스턴스 시작
WP_Smart_Bridge::get_instance();
