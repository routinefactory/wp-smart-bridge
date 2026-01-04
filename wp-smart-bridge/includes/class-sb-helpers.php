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
     * 플랫폼 감지 (도메인 자동 추출)
     * 
     * @param string $target_url 타겟 URL
     * @return string 플랫폼 (메인 도메인)
     */
    public static function detect_platform($target_url)
    {
        $host = parse_url($target_url, PHP_URL_HOST);

        if (empty($host)) {
            return 'Unknown';
        }

        $host = strtolower($host);

        // www. 제거
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        // 서브도메인 처리 - 주요 도메인만 추출
        // 예: s.click.aliexpress.com → aliexpress.com
        //     link.coupang.com → coupang.com
        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 2) {
            // .co.kr, .com.cn 등 2단계 TLD 처리
            $twoLevelTlds = ['co.kr', 'com.cn', 'co.jp', 'co.uk', 'com.au'];
            $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];

            if (in_array($lastTwo, $twoLevelTlds) && $count >= 3) {
                // 예: amazon.co.kr → amazon.co.kr
                $host = $parts[$count - 3] . '.' . $lastTwo;
            } else {
                // 예: s.click.aliexpress.com → aliexpress.com
                $host = $parts[$count - 2] . '.' . $parts[$count - 1];
            }
        }

        return $host;
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

    /**
     * 기본 리다이렉션 템플릿 가져오기
     * 
     * @return string HTML 템플릿 (placeholder 포함)
     */
    public static function get_default_redirect_template()
    {
        return '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>리다이렉트 중...</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .redirect-container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            margin: 20px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-message {
            font-size: 18px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .countdown {
            font-size: 14px;
            color: #666;
        }

        .countdown span {
            font-weight: bold;
            color: #667eea;
        }

        .skip-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .skip-link:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="redirect-container">
        <div class="spinner"></div>
        <div class="loading-message">
            {{LOADING_MESSAGE}}
        </div>
        <div class="countdown">
            <span id="countdown">{{DELAY_SECONDS}}</span>초 후 이동합니다...
        </div>
        <a href="{{TARGET_URL}}" class="skip-link">바로 이동</a>
    </div>
    {{COUNTDOWN_SCRIPT}}
</body>
</html>';
    }

    /**
     * 필수 템플릿 placeholder 목록
     * 
     * @return array placeholder 배열
     */
    public static function get_required_placeholders()
    {
        return [
            '{{LOADING_MESSAGE}}',
            '{{DELAY_SECONDS}}',
            '{{TARGET_URL}}',
            '{{COUNTDOWN_SCRIPT}}',
            'id="countdown"', // JavaScript가 참조하는 필수 ID
        ];
    }

    /**
     * 템플릿 검증
     * 
     * @param string $template 검증할 템플릿
     * @return array ['valid' => bool, 'missing' => array]
     */
    public static function validate_template($template)
    {
        $required = self::get_required_placeholders();
        $missing = [];

        foreach ($required as $placeholder) {
            if (strpos($template, $placeholder) === false) {
                $missing[] = $placeholder;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
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
1. {{LOADING_MESSAGE}} - 로딩 메시지 placeholder
2. {{DELAY_SECONDS}} - 딜레이 초 placeholder  
3. {{TARGET_URL}} - 타겟 URL placeholder
4. {{COUNTDOWN_SCRIPT}} - 카운트다운 스크립트 placeholder
5. id="countdown" - 카운트다운을 표시할 요소의 ID (반드시 이 ID를 가진 요소가 있어야 함)

디자인 변경 예시:
- 배경을 다크 모드로 변경
- 애니메이션을 더 부드럽게
- 글꼴을 모던한 스타일로
- 버튼 스타일을 3D 효과로

현재 템플릿:
[아래에 현재 템플릿 붙여넣기]';
    }
}
