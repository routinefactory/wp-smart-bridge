<?php
/**
 * Plugin Name: WP Smart Bridge
 * Plugin URI: https://github.com/routinefactory/wp-smart-bridge
 * Description: 제휴 마케팅용 단축 링크 자동화 플러그인 - HMAC-SHA256 보안 인증, 분석 기능 포함
 * Version: 3.0.1
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
define('SB_VERSION', '3.3.4');
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
// require_once removed - SB_Backup is safely loaded in init() function with file_exists check
require_once SB_PLUGIN_DIR . 'includes/class-sb-admin-ajax.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-groups.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-realtime.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-async-logger.php';
require_once SB_PLUGIN_DIR . 'includes/class-sb-cron.php';

// 관리자 페이지 로드
if (is_admin()) {
    require_once SB_PLUGIN_DIR . 'admin/class-sb-admin-view-model.php';
    require_once SB_PLUGIN_DIR . 'admin/class-sb-admin.php';
}

/**
 * 플러그인 메인 클래스...
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

        // v3.1.1: Bypass moved to init() for earlier execution

        // 플러그인 로드 완료
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    /**
     * 플러그인 활성화
     * 
     * ⚠️ 중요: 이 함수는 다음 상황에서만 실행됩니다:
     * 1. 플러그인 최초 설치 후 활성화
     * 2. 비활성화 상태에서 재활성화
     * 3. WordPress 업데이트는 이 함수를 실행하지 않음!
     * 
     * 데이터 보존 원칙:
     * - 기존 테이블이 있으면 건드리지 않음 (dbDelta 특성)
     * - 기존 설정이 있으면 덮어쓰지 않음 (if 체크)
     * - 기존 포스트는 절대 삭제하지 않음
     */
    public function activate()
    {
        /**
         * 1. 데이터베이스 테이블 생성 (안전함)
         * 
         * dbDelta()의 동작:
         * - 테이블이 없으면 생성
         * - 테이블이 있으면 스킵 (데이터 보존)
         * - 새 컬럼이 있으면 추가만 함
         * 
         * 결과: 재설치 시에도 기존 API 키와 분석 데이터 완벽 보존
         */
        SB_Database::create_tables();

        /**
         * 2. 커스텀 포스트 타입 등록
         * 
         * 이미 생성된 sb_link 포스트는 영향받지 않음
         */
        SB_Post_Type::register();

        /**
         * 3. Rewrite 규칙 플러시
         * 
         * /go/{slug} URL 라우팅을 위해 필수
         * 데이터에는 영향 없음
         */
        flush_rewrite_rules();

        /**
         * 4. 버전 정보 업데이트
         * 
         * 업그레이드 감지를 위해 항상 최신 버전으로 덮어씀
         */
        update_option('sb_version', SB_VERSION);

        /**
         * 5. 기본 설정 저장 (조건부)
         * 
         * ✅ 핵심: if (!get_option('sb_settings'))
         * 
         * 동작:
         * - 설정이 없으면 (최초 설치): 기본값으로 생성
         * - 설정이 있으면 (재설치/재활성화): 기존 값 유지
         * 
         * 예시 시나리오:
         * 1. 사용자가 플러그인 설치 → 기본 설정 생성
         * 2. 사용자가 API 키 생성 및 커스텀 설정
         * 3. 플러그인 비활성화 (데이터는 DB에 그대로)
         * 4. 플러그인 재활성화 → 이 if문 때문에 기존 설정 보존됨!
         */
        if (!get_option('sb_settings')) {
            update_option('sb_settings', [
                'redirect_delay' => 2, // 2초 딜레이 (브랜딩 및 로깅 확실성)
                'ip_hash_salt' => wp_generate_password(32, true, true),
                'delete_data_on_uninstall' => false, // ✅ 기본값: 데이터 보존
            ]);
        }

        /**
         * 6. 첫 설치 안내 배너 표시
         */
        if (!get_option('sb_first_install_notice')) {
            update_option('sb_first_install_notice', true);
        }

        /**
         * 7. 퍼마링크 자동 플러시 제거
         * 
         * 자동 플러시는 환경에 따라 불안정하므로 제거됨.
         * 대신 대시보드에서 '자가 진단'을 통해 404 발생 시
         * 사용자에게 수동 저장을 안내하는 방식으로 변경 (v2.9.10)
         */
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

        // v3.3.0: Backup Module - TEMPORARILY DISABLED FOR DEBUGGING
        // TODO: Re-enable after confirming plugin activation works
        /*
        if (file_exists(SB_PLUGIN_DIR . 'includes/class-sb-backup.php')) {
            require_once SB_PLUGIN_DIR . 'includes/class-sb-backup.php';
            SB_Backup::init();
        } else {
            error_log('SB_Backup class file not found.');
        }
        */

        // 클래스 초기화
        SB_Security::init();
        SB_Post_Type::init();
        SB_Redirect::init();
        SB_Admin_Ajax::init();
        SB_Realtime::init();
        SB_Async_Logger::init();
        // SB_Backup::init(); // Moved inside if block above
        SB_Cron::init(); // Keep existing Cron init

        // v3.1.4: Bypass 3rd-party auth plugins (e.g., miniOrange)
        // Registered in init() to ensure it catches early blocks
        // Priority PHP_INT_MAX to run absolutely LAST
        add_filter('rest_authentication_errors', ['SB_Rest_API', 'bypass_authentication'], PHP_INT_MAX);
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
        /**
         * ✅ 중요: 자동 데이터베이스 업그레이드
         * 
         * 이 함수는 매 페이지 로드마다 실행되지만,
         * 실제 DB 업그레이드는 버전이 변경되었을 때만 1회 수행됩니다.
         * 
         * 동작 원리:
         * 1. DB에 저장된 버전(sb_version)과 플러그인 코드 버전(SB_VERSION) 비교
         * 2. 플러그인 버전이 더 높으면 DB 업그레이드 실행
         * 3. create_tables()는 dbDelta()를 사용하므로 안전:
         *    - 기존 테이블이 있으면 스킵
         *    - 새 컬럼이 있으면 추가만 함
         *    - 기존 데이터는 절대 삭제하지 않음
         * 
         * 예시 시나리오:
         * - 사용자가 v2.6.4 사용 중
         * - v2.7.0 업데이트 (새 컬럼 'country_code' 추가)
         * - 플러그인 파일만 교체됨 (activate() 훅 실행 안 됨)
         * - 사용자가 WordPress 관리자 접속
         * - 이 함수가 실행되어 자동으로 DB 스키마 업데이트
         * - 기존 링크/분석 데이터는 모두 보존됨
         */
        $this->maybe_upgrade_database();

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

    /**
     * 데이터베이스 자동 업그레이드
     * 
     * 플러그인 업데이트 시 activate() 훅이 실행되지 않으므로,
     * 버전 비교를 통해 자동으로 DB 스키마를 업데이트합니다.
     * 
     * @since 2.6.5
     */
    private function maybe_upgrade_database()
    {
        // 현재 설치된 버전 조회
        $installed_version = get_option('sb_version', '0.0.0');

        // 버전 비교: 플러그인 코드 버전이 더 높으면 업그레이드 필요
        if (version_compare(SB_VERSION, $installed_version, '>')) {

            /**
             * 📝 업그레이드 로그 기록 (디버깅용)
             * 
             * 프로덕션 환경에서는 에러 로그에만 기록되므로
             * 사용자에게 노출되지 않습니다.
             */
            error_log(sprintf(
                '[Smart Bridge] Database upgrade: %s → %s',
                $installed_version,
                SB_VERSION
            ));

            /**
             * 🔄 테이블 스키마 업데이트
             * 
             * dbDelta()의 안전성:
             * - CREATE TABLE IF NOT EXISTS와 유사하게 동작
             * - 새 컬럼 추가: ALTER TABLE ADD COLUMN 자동 실행
             * - 기존 컬럼 유지: 데이터 손실 없음
             * - 인덱스 추가: 성능 향상
             */
            SB_Database::create_tables();

            /**
             * 📌 버전별 특수 마이그레이션
             * 
             * 특정 버전에서만 필요한 데이터 변환 작업을 수행합니다.
             * 예: 컬럼 타입 변경, 기본값 업데이트, 데이터 재계산 등
             * 
             * 향후 확장 예시:
             * if (version_compare($installed_version, '3.0.0', '<')) {
             *     $this->migrate_to_300();
             * }
             */

            // 예시: v2.7.0 전용 마이그레이션 (현재는 주석 처리)
            // if (version_compare($installed_version, '2.7.0', '<')) {
            //     $this->migrate_to_270();
            // }

            /**
             * ✅ 업그レ이드 완료: 새 버전 기록
             * 
             * 중요: 이 값이 업데이트되면 다음 페이지 로드부터는
             * 업그레이드 로직이 실행되지 않습니다.
             */
            update_option('sb_version', SB_VERSION);

            /**
             * v3.0.5 CRITICAL FIX: Flush Rewrite Rules on Upgrade
             * 
             * PROBLEM: After plugin update, WordPress 404s on /go/xxx/ URLs
             * because rewrite rules haven't been refreshed.
             * 
             * ROOT CAUSE: Plugin updates don't trigger activation hook,
             * so flush_rewrite_rules() was never called after update.
             * 
             * SOLUTION: Explicitly register and flush rewrite rules
             * during version upgrade process.
             * 
             * @see class-sb-redirect.php Line 39 for rewrite rule pattern
             */
            SB_Redirect::add_rewrite_rules();
            flush_rewrite_rules();

            /**
             * 🔔 업그레이드 완료 알림 (관리자 전용)
             * 
             * 다음 관리자 페이지 접속 시 안내 배너 표시
             */
            set_transient('sb_show_upgrade_notice', true, 60); // 60초간 유지

            error_log('[Smart Bridge] Database upgrade completed successfully');
        }
    }

    /**
     * 버전별 데이터 마이그레이션 예시 함수
     * 
     * v2.7.0으로 업그레이드하는 사용자 전용
     * 실제 필요 시 주석 해제하여 사용
     * 
     * @since 2.7.0
     * @example
     * private function migrate_to_270()
     * {
     *     global $wpdb;
     *     $table = $wpdb->prefix . 'sb_analytics_logs';
     *     
     *     // 예: 새 컬럼에 기본값 설정
     *     $wpdb->query("
     *         UPDATE $table 
     *         SET country_code = 'KR' 
     *         WHERE country_code IS NULL
     *     ");
     *     
     *     error_log('[Smart Bridge] v2.7.0 migration completed');
     * }
     */
}

// 플러그인 인스턴스 시작
WP_Smart_Bridge::get_instance();
