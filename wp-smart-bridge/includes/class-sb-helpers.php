<?php
/**
 * 유틸리티 헬퍼 함수 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Helpers
{
    /**
     * 기본 슬러그 길이
     */
    const DEFAULT_SLUG_LENGTH = 6;

    /**
     * 슬러그 생성 최대 재시도 횟수
     */
    const MAX_SLUG_RETRIES = 3;


    /**
     * Base62 문자셋
     */
    const BASE62_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Base62 고유 Slug 생성
     * 
     * @param int $length Slug 길이 (기본값: self::DEFAULT_SLUG_LENGTH)
     * @param int $max_retries 최대 재시도 횟수 (기본값: self::MAX_SLUG_RETRIES)
     * @return string|false 생성된 Slug 또는 false
     */
    public static function generate_unique_slug($length = self::DEFAULT_SLUG_LENGTH, $max_retries = self::MAX_SLUG_RETRIES)
    {
        for ($i = 0; $i < $max_retries; $i++) {
            $slug = self::generate_random_string($length);

            if (!self::slug_exists($slug)) {
                return $slug;
            }
        }

        return false;
    }

    /**
     * 랜덤 문자열 생성 (Base62)
     * 
     * @param int $length 문자열 길이
     * @return string 랜덤 문자열
     */
    public static function generate_random_string($length = 6)
    {
        $chars = self::BASE62_CHARS;
        $chars_length = strlen($chars);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $chars_length - 1)];
        }

        return $result;
    }

    /**
     * Slug 존재 여부 확인
     * 
     * @param string $slug 확인할 Slug
     * @return bool 존재 여부
     */
    public static function slug_exists($slug)
    {
        global $wpdb;

        // v2.9.22 Fix: Check post_name (Unique Index) instead of post_title
        // v3.0.0 Critical Fix: PHP constant must be passed as parameter, not embedded in SQL string
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_name = %s 
             AND post_status != 'trash'",
            SB_Post_Type::POST_TYPE,
            $slug
        ));

        return (int) $count > 0;
    }

    /**
     * 플랫폼 감지 (도메인 자동 추출)
     * 
     * @param string $target_url 타겟 URL
     * @return string 플랫폼 (메인 도메인)
     */
    public static function detect_platform($target_url)
    {
        if (empty($target_url)) {
            return 'Unknown';
        }

        $host = parse_url($target_url, PHP_URL_HOST);

        if (empty($host)) {
            return 'Unknown';
        }

        $host = strtolower($host);

        // 1. Specific Platform Mappings (Exact Match)
        $map = [
            // YouTube
            'youtu.be' => 'YouTube',
            'youtube.com' => 'YouTube',
            'www.youtube.com' => 'YouTube',
            'm.youtube.com' => 'YouTube',

            // Naver Services
            'blog.naver.com' => 'Naver Blog',
            'm.blog.naver.com' => 'Naver Blog',
            'cafe.naver.com' => 'Naver Cafe',
            'm.cafe.naver.com' => 'Naver Cafe',
            'smartstore.naver.com' => 'Naver Smart Store',
            'm.smartstore.naver.com' => 'Naver Smart Store',
            'shopping.naver.com' => 'Naver Shopping',
            'm.shopping.naver.com' => 'Naver Shopping',
            'post.naver.com' => 'Naver Post',
            'm.post.naver.com' => 'Naver Post',
            'tv.naver.com' => 'Naver TV',
            'm.tv.naver.com' => 'Naver TV',

            // Social Media
            'instagram.com' => 'Instagram',
            'www.instagram.com' => 'Instagram',
            'facebook.com' => 'Facebook',
            'www.facebook.com' => 'Facebook',
            'twitter.com' => 'Twitter',
            'x.com' => 'Twitter', // Rebrand
            't.co' => 'Twitter',

            // Commerce
            'coupang.com' => 'Coupang',
            'www.coupang.com' => 'Coupang',
            'link.coupang.com' => 'Coupang', // Affiliate Links
            'aliexpress.com' => 'AliExpress',
            'www.aliexpress.com' => 'AliExpress',
            'best.aliexpress.com' => 'AliExpress',
            's.click.aliexpress.com' => 'AliExpress', // Affiliate Links

            // ETC
            'bit.ly' => 'Bitly',
        ];

        // v3.0.0 Architecture Improvement: Allow developers to extend platform mapping
        $map = apply_filters('sb_platform_map', $map);

        if (isset($map[$host])) {
            return $map[$host];
        }

        // 2. Remove 'www.' prefix for generic handling
        $clean_host = $host;
        if (strpos($host, 'www.') === 0) {
            $clean_host = substr($host, 4);
        }

        // Re-check map with clean host (e.g. if map has 'naver.com' but not 'www.naver.com')
        if (isset($map[$clean_host])) {
            return $map[$clean_host];
        }

        // 3. Generic Domain Extraction
        $parts = explode('.', $clean_host);
        $count = count($parts);

        if ($count >= 2) {
            // Common Second Level TLDs
            $twoLevelTlds = [
                'co.kr',
                'pe.kr',
                'or.kr',
                'ne.kr',
                'go.kr',
                'ac.kr', // Korea
                'com.cn',
                'net.cn',
                'org.cn', // China
                'co.jp',
                'ne.jp', // Japan
                'co.uk',
                'org.uk',
                'me.uk', // UK
                'com.au',
                'net.au',
                'org.au', // Australia
                'com.br', // Brazil
                'com.sg', // Singapore
            ];

            $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];

            if (in_array($lastTwo, $twoLevelTlds) && $count >= 3) {
                // e.g. amazon.co.kr
                return $parts[$count - 3] . '.' . $lastTwo;
            } else {
                // e.g. google.com
                return $parts[$count - 2] . '.' . $parts[$count - 1];
            }
        }

        return $clean_host;
    }

    /**
     * API Key 생성
     * 
     * @return string API Key (sb_live_xxx 형식)
     */
    public static function generate_api_key()
    {
        return 'sb_live_' . self::generate_random_string(24);
    }

    /**
     * Secret Key 생성
     * 
     * @return string Secret Key (sk_secret_xxx 형식)
     */
    public static function generate_secret_key()
    {
        return 'sk_secret_' . self::generate_random_string(32);
    }

    /**
     * IP 주소 해싱 (GDPR 준수)
     * 
     * @param string $ip IP 주소
     * @return string 해싱된 IP
     */
    public static function hash_ip($ip)
    {
        // v2.9.22 보안 패치: DB에 저장된 Salt 대신 wp-config.php의 Salt 사용
        // Salt가 없으면 자동으로 생성된 키 사용
        $salt = wp_salt('auth');

        return hash('sha256', $ip . $salt);
    }

    /**
     * 클라이언트 IP 주소 가져오기 (Proxy 지원)
     * 
     * @return string IP Address
     */
    public static function get_client_ip()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        else if (isset($_SERVER['HTTP_X_REAL_IP']))
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        else if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ip = $_SERVER['REMOTE_ADDR'];

        // 여러 IP가 올 경우 첫 번째만 취하기 (X-Forwarded-For 등)
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /**
     * URL 유효성 검증
     * 
     * @param string $url 검증할 URL
     * @return bool 유효 여부
     */
    public static function validate_url($url)
    {
        if (empty($url)) {
            return false;
        }

        // http:// 또는 https:// 스키마 필수
        // v3.0.0 Security Fix: Allow protocol-relative URLs (//site.com) but validate stricter later
        if (!preg_match('/^(https?:\/\/|\/\/)/i', $url)) {
            return false;
        }

        // v3.0.0 Security Fix: Prevent Redirect Loop & Chaining
        // 자신의 사이트 내의 단축 링크 경로('/go/')를 타겟으로 설정하는 것을 차단
        $short_link_base = home_url('/go/');

        // 1. Normalize Base (remove scheme)
        $clean_base = preg_replace('/^(https?:\/\/|\/\/)/i', '', $short_link_base);

        // 2. Normalize Target
        // - Remove scheme
        // - URL Decode (to prevent encoded attacks like /%67%6F/)
        // - Lowercase (for case-insensitive comparison)
        $clean_target = preg_replace('/^(https?:\/\/|\/\/)/i', '', $url);
        $clean_target = urldecode($clean_target);
        $clean_target = mb_strtolower($clean_target);

        $clean_base = mb_strtolower($clean_base);

        // 3. Check for Self-Reference
        // startswith check
        if (strpos($clean_target, $clean_base) === 0) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 단축 링크 URL 생성
     * 
     * @param string $slug Slug
     * @return string 전체 단축 URL
     */
    public static function get_short_link_url($slug)
    {
        return home_url('/go/' . $slug);
    }

    /**
     * Slug로 링크 포스트 조회
     * 
     * @param string $slug Slug
     * @return WP_Post|null 포스트 또는 null
     */
    public static function get_link_by_slug($slug)
    {
        $query = new WP_Query([
            'post_type' => SB_Post_Type::POST_TYPE,
            'name' => $slug, // v2.9.22 Fix: Query by valid slug (post_name)
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'no_found_rows' => true,
        ]);

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return null;
    }

    /**
     * 포스트 메타에서 타겟 URL 조회
     * 
     * @param int $post_id 포스트 ID
     * @return string 타겟 URL
     */
    public static function get_target_url($post_id)
    {
        return get_post_meta($post_id, 'target_url', true);
    }

    /**
     * 포스트 메타에서 플랫폼 조회
     * 
     * @param int $post_id 포스트 ID
     * @return string 플랫폼
     */
    public static function get_platform($post_id)
    {
        return get_post_meta($post_id, 'platform', true) ?: 'Etc';
    }


    /**
     * 클릭 수 증가 (Atomic Operation - Race Condition 방지)
     * 
     * 기존 문제:
     * - get_post_meta() → +1 → update_post_meta() 방식은 동시 접속 시 클릭 손실 발생
     * 
     * 해결:
     * - SQL UPDATE ... SET meta_value = meta_value + 1로 원자적 연산 수행
     * - 데이터베이스 레벨에서 동시성 보장
     * 
     * @param int $post_id 포스트 ID
     */
    public static function increment_click_count($post_id)
    {
        global $wpdb;

        // 원자적 증가 연산 (Atomic Increment)
        $table = $wpdb->prefix . 'postmeta';

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET meta_value = CAST(meta_value AS UNSIGNED) + 1 
             WHERE post_id = %d 
               AND meta_key = 'click_count'",
            $post_id
        ));

        // 메타가 없으면 생성
        if ($updated === 0) {
            add_post_meta($post_id, 'click_count', 1, true);
        }

        // 캐시 무효화 (WordPress 메타 캐시)
        wp_cache_delete($post_id, 'post_meta');
    }

    /**
     * 날짜 범위 계산
     * 
     * @param string $range 범위 (today, yesterday, week, month, 7d, 30d)
     * @return array ['start' => DateTime, 'end' => DateTime]
     */
    public static function get_date_range($range)
    {
        $now = new DateTime('now', wp_timezone());
        $start = clone $now;
        $end = clone $now;

        switch ($range) {
            case 'today':
                $start->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case 'yesterday':
                $start->modify('-1 day')->setTime(0, 0, 0);
                $end->modify('-1 day')->setTime(23, 59, 59);
                break;

            case 'week':
            case '7d':
                $start->modify('-6 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case 'month':
            case '30d':
                $start->modify('-29 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            default:
                // 기본 30일
                $start->modify('-29 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 기본 리다이렉션 템플릿 가져오기
     * 
     * @return string HTML 템플릿 (placeholder 포함)
     */
    public static function get_default_redirect_template()
    {
        $file = SB_PLUGIN_DIR . 'includes/defaults/default-redirect.tpl';

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        // Fallback (minimal)
        return '<!DOCTYPE html><html><body>Redirecting... <a href="{{TARGET_URL}}">Click here</a>{{COUNTDOWN_SCRIPT}}</body></html>';
    }

    /**
     * 필수 템플릿 placeholder 목록
     * 
     * @return array placeholder 배열
     */
    public static function get_required_placeholders()
    {
        return [
            '{{DELAY_SECONDS}}',
            '{{TARGET_URL}}',
            '{{COUNTDOWN_SCRIPT}}',
            '{{COUNTDOWN_ID}}',
        ];
    }

    /**
     * 템플릿 유효성 검사
     * 
     * @param string $template 템플릿 HTML
     * @return true|string 성공 시 true, 실패 시 에러 메시지
     */
    public static function validate_template($template)
    {
        // 필수 플레이스홀더 검사 (모든 플레이스홀더를 동일한 방식으로 검증)
        $required_placeholders = [
            '{{DELAY_SECONDS}}' => __('타이머 숫자', 'sb'),
            '{{TARGET_URL}}' => __('타겟 URL', 'sb'),
            '{{COUNTDOWN_SCRIPT}}' => __('카운트다운 스크립트', 'sb'),
            '{{COUNTDOWN_ID}}' => __('카운트다운 요소 ID', 'sb'),
        ];

        foreach ($required_placeholders as $placeholder => $name) {
            if (strpos($template, $placeholder) === false) {
                return sprintf(__('오류: 필수 Placeholder가 누락되었습니다: %s (%s)', 'sb'), $placeholder, $name);
            }
        }


        // 자바스크립트 보안 검사 (v2.9.22 Security Hardening)
        // 관리자라 하더라도 악성 스크립트 삽입 가능성 차단 (필요시 wp_kses 활용 권장)
        if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $template)) {
            // COUNTDOWN_SCRIPT는 우리가 서버에서 생성해서 주입하므로, 
            // 템플릿 자체에 <script> 태그가 있는 것은 보안 정책상 차단 (또는 경고)
            if (!current_user_can('unfiltered_html')) {
                return __('오류: 보안 정책에 따라 템플릿 내에 직접적인 <script> 태그 삽입이 금지되어 있습니다.', 'sb');
            }
        }

        return true;
    }

    /**
     * AI 프롬프트 예시 가져오기
     * 
     * @return string 예시 프롬프트
     */
    public static function get_ai_prompt_example()
    {
        return '아래 HTML 템플릿의 디자인을 세련되게 변경해줘. 단, 다음 규칙을 **반드시** 지켜줘:

필수 유지 항목:
1. {{DELAY_SECONDS}} - 딜레이 초 placeholder  
2. {{TARGET_URL}} - 타겟 URL placeholder
3. {{COUNTDOWN_SCRIPT}} - 카운트다운 스크립트 placeholder
4. {{COUNTDOWN_ID}} - 카운트다운 표시 요소의 ID (예: id="{{COUNTDOWN_ID}}")

디자인 변경 예시:
- 배경을 다크 모드로 변경
- 애니메이션을 더 부드럽게
- 글꼴을 모던한 스타일로
- 버튼 스타일을 3D 효과로
- 로딩 메시지 텍스트를 원하는 대로 변경 (하드코딩)

현재 템플릿:
[아래에 현재 템플릿 붙여넣기]';
    }
}
