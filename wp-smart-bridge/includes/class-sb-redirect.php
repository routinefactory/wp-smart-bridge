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
     */
    public static function init()
    {
        // Rewrite 규칙 추가 (직접 호출 - init 훅 내에서 호출되므로)
        self::add_rewrite_rules();

        // Query vars 추가
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);

        // 템플릿 리다이렉트 처리
        add_action('template_redirect', [__CLASS__, 'handle_redirect']);
    }

    /**
     * Rewrite 규칙 추가
     */
    public static function add_rewrite_rules()
    {
        add_rewrite_rule(
            '^' . self::PREFIX . '/([^/]+)/?$',
            'index.php?sb_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Query vars 추가
     */
    public static function add_query_vars($vars)
    {
        $vars[] = 'sb_slug';
        return $vars;
    }

    /**
     * 리다이렉트 처리
     */
    public static function handle_redirect()
    {
        $slug = get_query_var('sb_slug');

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

        if (empty($target_url)) {
            // 타겟 URL이 없으면 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // 클릭 로깅
        self::log_click($link->ID);

        // 클릭 수 증가
        SB_Helpers::increment_click_count($link->ID);

        // 설정 조회
        $settings = get_option('sb_settings', []);
        $redirect_delay = isset($settings['redirect_delay']) ? (float) $settings['redirect_delay'] : 0.0;

        // v2.9.22 Feature: Query Parameter Passthrough
        $query_params = $_GET;
        unset($query_params['sb_slug']); // 내부 파라미터 제외

        if (!empty($query_params)) {
            $target_url = add_query_arg($query_params, $target_url);
        }

        // v2.9.24 Security: Defense in Depth (Protocol validation)
        $allowed_protocols = ['http', 'https'];
        $scheme = parse_url($target_url, PHP_URL_SCHEME);
        if (!in_array($scheme, $allowed_protocols)) {
            // 비정상적인 프로토콜 감지 시 403 Forbidden
            wp_die('Invalid redirect target protocol.', 'Security Error', ['response' => 403]);
        }

        // v2.9.22 Security: Headers
        header('X-Redirect-By: WP-Smart-Bridge');
        header('Referrer-Policy: unsafe-url'); // 마케팅 기여도 추적을 위해 Referrer 전달

        // 딜레이가 있으면 중간 페이지 표시
        if ($redirect_delay > 0) {
            self::show_redirect_page($link->ID, $target_url, $redirect_delay);
            exit;
        }

        // 즉시 리다이렉트 (302 Temporary)
        // v2.9.24 Fix: Ensure no caching for immediate redirects to maintain stats accuracy
        nocache_headers();
        wp_redirect($target_url, 302);
        exit;
    }

    /**
     * 클릭 로깅
     * 
     * @param int $link_id 링크 ID
     */
    private static function log_click($link_id)
    {
        // 방문자 정보 수집
        $visitor_ip = self::get_visitor_ip();
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
    }

    /**
     * 방문자 IP 주소 획득
     * 
     * @return string IP 주소
     */
    private static function get_visitor_ip()
    {
        $ip = '';

        // 프록시 헤더 확인
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                break;
            }
        }

        return $ip;
    }

    /**
     * 리다이렉트 중간 페이지 표시
     * 
     * @param int $link_id 링크 ID
     * @param string $target_url 타겟 URL
     * @param int $delay 딜레이 (초)
     */
    private static function show_redirect_page($link_id, $target_url, $delay)
    {
        // v2.9.22 Security: No-Cache & No-Index for intermediate pages
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');

        // 변수가 템플릿에서 사용 가능하도록 설정
        $target_url = esc_url($target_url);
        $delay = intval($delay);

        // 리다이렉트 페이지 템플릿 로드
        include SB_PLUGIN_DIR . 'public/views/redirect.php';
    }
}
