<?php
/**
 * 리다이렉션 핸들러 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Redirect
{

    /**
     * 리다이렉션 경로 프리픽스
     */
    const PREFIX = 'go';

    /**
     * 초기화
     * 
     * v4.0.0: 파라미터 방식(?go=slug)으로 변경
     * - Rewrite rules 불필요
     * - Query vars 불필요
     */
    public static function init()
    {
        // v4.0.0: 파라미터 방식으로 rewrite rules 제거
        // self::add_rewrite_rules(); // 더 이상 사용 안 함
        // add_filter('query_vars', [__CLASS__, 'add_query_vars']); // 더 이상 사용 안 함

        // 템플릿 리다이렉트 처리 (핵심)
        add_action('template_redirect', [__CLASS__, 'handle_redirect']);
    }

    /**
     * Rewrite 규칙 추가
     * 
     * @deprecated v4.0.0 파라미터 방식으로 더 이상 사용 안 함
     * 호환성을 위해 메소드는 유지하되 호출되지 않음
     */
    public static function add_rewrite_rules()
    {
        // v4.0.0: 파라미터 방식으로 이 메소드는 더 이상 호출되지 않음
        // 기존 코드 주석 처리 (히스토리 보존)
        /*
        add_rewrite_rule(
            '^' . self::PREFIX . '/([^/]+)/?$',
            'index.php?sb_slug=$matches[1]',
            'top'
        );
        */
    }

    /**
     * Query vars 추가
     * 
     * @deprecated v4.0.0 파라미터 방식으로 더 이상 사용 안 함
     */
    public static function add_query_vars($vars)
    {
        // v4.0.0: 파라미터 방식으로 이 메소드는 더 이상 호출되지 않음
        // $vars[] = 'sb_slug';
        return $vars;
    }

    /**
     * 리다이렉트 처리
     * 
     * v4.0.0: 파라미터 방식(?go=slug)으로 변경
     */
    public static function handle_redirect()
    {
        // v4.0.0: 파라미터 방식 - $_GET['go']에서 슬러그 추출
        // 보안: sanitize_text_field()로 XSS 방지
        $slug = isset($_GET['go']) ? sanitize_text_field($_GET['go']) : '';

        if (empty($slug)) {
            return;
        }

        // Slug로 링크 조회
        $link = SB_Helpers::get_link_by_slug($slug);

        if (!$link) {
            // 404 처리
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // 타겟 URL 조회
        $target_url = SB_Helpers::get_target_url($link->ID);
        $target_url = esc_url_raw($target_url);
        if (empty($target_url) || !SB_Helpers::validate_url($target_url)) {
            // 타겟 URL이 없으면 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Settings - Redirect Delay
        $settings = get_option('sb_settings');
        $redirect_delay = isset($settings['redirect_delay']) ? floatval($settings['redirect_delay']) : 0.0;

        // v3.0.0 Async Logging Implementation
        // Instead of logging first then redirecting, we hand off to the async logger.

        // v4.1.4 Critical Fix: Ignore HEAD requests for logging to prevent double counting
        // (Browsers/Bots often send HEAD before GET)
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'HEAD') {
            // Just Redirect, Don't Log
            if ($redirect_delay <= 0) {
                nocache_headers();
                wp_redirect($target_url, 302);
                header("Connection: close");
                exit;
            }
            nocache_headers();
            wp_redirect($target_url, 302);
            exit;
        }

        // v4.1.5 Fix: Ignore Browser Prefetching & Internal Health Checks
        // 1. Browser Prefetch Headers
        $headers = function_exists('getallheaders') ? getallheaders() : $_SERVER;
        $is_prefetch = false;

        // Normalize headers for checking
        $check_headers = [
            'HTTP_X_PURPOSE' => ['preview', 'prefetch'],
            'HTTP_PURPOSE' => ['prefetch'],
            'HTTP_SEC_PURPOSE' => ['prefetch', 'preview'],
            'HTTP_X_MOZ' => ['prefetch'],
            'Purpose' => ['prefetch'], // Generic
            'Sec-Purpose' => ['prefetch', 'preview'], // Standard
            'X-Purpose' => ['preview', 'prefetch']
        ];

        foreach ($check_headers as $header => $values) {
            // Check formatted header (e.g. from getallheaders) or server var (e.g. HTTP_X_PURPOSE)
            $value = null;
            if (isset($headers[$header]))
                $value = $headers[$header];
            if (isset($_SERVER[$header]))
                $value = $_SERVER[$header]; // Fallback

            if ($value && in_array(strtolower($value), $values)) {
                $is_prefetch = true;
                break;
            }
        }

        // 2. Internal Health Check Parameter (_sb_health)
        // Used by SB_Admin_Ajax::ajax_health_check()
        $is_health_check = isset($_GET['_sb_health']);

        if ($is_prefetch || $is_health_check) {
            // Skip Logging for Prefetch or Health Check
            if ($redirect_delay <= 0) {
                nocache_headers();
                wp_redirect($target_url, 302);
                exit;
            }
            nocache_headers();
            wp_redirect($target_url, 302);
            exit;
        }

        SB_Async_Logger::log_and_redirect($link->ID, $target_url, $redirect_delay);
        exit;
    }

    /**
     * 클릭 로깅 (동기식 - Fallback or Delayed Redirect용)
     * 
     * @param int $link_id 링크 ID
     */
    public static function log_click_sync($link_id)
    {
        // 방문자 정보 수집
        // v3.0.0 Refactor: Use consolidated SB_Helpers::get_client_ip()
        $visitor_ip = SB_Helpers::get_client_ip();
        $hashed_ip = SB_Helpers::hash_ip($visitor_ip);
        $platform = SB_Helpers::get_platform($link_id);
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

        // v2.9.22: Log-time UA Parsing (Scalability Fix)
        // v2.9.24 Optimization: Static call to reduce memory overhead
        $parsed_ua = [];
        if ($user_agent) {
            $parsed_ua = SB_Analytics::parse_user_agent($user_agent); // User-Agent 파싱
        }

        // 로그 저장
        SB_Database::log_click($link_id, $hashed_ip, $platform, $referer, $user_agent, $parsed_ua);

        // 캐시 업데이트 (로그 저장 후 - UV 중복 체크용)
        SB_Helpers::update_stats_cache_after_log($link_id);
    }


    /**
     * 리다이렉트 중간 페이지 표시
     * 
     * @param int $link_id 링크 ID
     * @param string $target_url 타겟 URL
     * @param int $delay 딜레이 (초)
     */
    public static function show_redirect_page($link_id, $target_url, $delay)
    {
        // v2.9.22 Security: No-Cache & No-Index for intermediate pages
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');

        // 변수가 템플릿에서 사용 가능하도록 설정
        $target_url = esc_url($target_url);
        $delay = floatval($delay);

        // 리다이렉트 페이지 템플릿 로드
        include SB_PLUGIN_DIR . 'public/views/redirect.php';
    }
}
