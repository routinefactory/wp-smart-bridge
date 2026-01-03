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
     * Base62 문자셋
     */
    const BASE62_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Base62 고유 Slug 생성
     * 
     * @param int $length Slug 길이 (기본 6)
     * @param int $max_retries 최대 재시도 횟수 (기본 3)
     * @return string|false 생성된 Slug 또는 false
     */
    public static function generate_unique_slug($length = 6, $max_retries = 3)
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

        // 정확한 post_title 매칭을 위해 직접 쿼리 사용
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'sb_link' 
             AND post_title = %s 
             AND post_status != 'trash'",
            $slug
        ));

        return (int) $count > 0;
    }

    /**
     * 플랫폼 감지
     * 
     * @param string $target_url 타겟 URL
     * @return string 플랫폼 태그
     */
    public static function detect_platform($target_url)
    {
        $host = parse_url($target_url, PHP_URL_HOST);

        if (empty($host)) {
            return 'Etc';
        }

        $host = strtolower($host);

        // 플랫폼 매핑
        $platforms = [
            'coupang.com' => 'Coupang',
            'coupa.ng' => 'Coupang',
            'aliexpress.com' => 'AliExpress',
            'aliexpress.kr' => 'AliExpress',
            's.click.aliexpress.com' => 'AliExpress',
            'amazon.com' => 'Amazon',
            'amazon.co.kr' => 'Amazon',
            'amzn.to' => 'Amazon',
            'amzn.asia' => 'Amazon',
            'temu.com' => 'Temu',
            'temu.to' => 'Temu',
        ];

        foreach ($platforms as $domain => $platform) {
            if (strpos($host, $domain) !== false) {
                return $platform;
            }
        }

        return 'Etc';
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
        $settings = get_option('sb_settings', []);
        $salt = isset($settings['ip_hash_salt']) ? $settings['ip_hash_salt'] : 'default-salt';

        return hash('sha256', $ip . $salt);
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
        if (!preg_match('/^https?:\/\//i', $url)) {
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
            'post_type' => 'sb_link',
            'title' => $slug,
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
     * 포스트 메타에서 로딩 메시지 조회
     * 
     * @param int $post_id 포스트 ID
     * @return string 로딩 메시지
     */
    public static function get_loading_message($post_id)
    {
        $message = get_post_meta($post_id, 'loading_message', true);

        if (empty($message)) {
            $settings = get_option('sb_settings', []);
            $message = isset($settings['default_loading_message'])
                ? $settings['default_loading_message']
                : '잠시만 기다려주세요...';
        }

        return $message;
    }

    /**
     * 클릭 수 증가
     * 
     * @param int $post_id 포스트 ID
     */
    public static function increment_click_count($post_id)
    {
        $current = (int) get_post_meta($post_id, 'click_count', true);
        update_post_meta($post_id, 'click_count', $current + 1);
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
}
