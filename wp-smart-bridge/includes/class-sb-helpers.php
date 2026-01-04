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
    <title>Connecting...</title>
    <style>
        :root {
            --primary: #2563EB;
            --surface: #ffffff;
            --bg: #F3F4F6;
            --text: #1F2937;
            --text-secondary: #6B7280;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .card {
            background: var(--surface);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #2563EB, #60A5FA);
        }
        .progress-container {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
        }
        .progress-svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }
        .progress-circle-bg {
            fill: none;
            stroke: #E5E7EB;
            stroke-width: 4;
        }
        .progress-circle {
            fill: none;
            stroke: var(--primary);
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 238;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear;
        }
        .countdown-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        .message {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }
        .sub-message {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn:hover {
            background-color: #1D4ED8;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        .security-badge {
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            opacity: 0.8;
        }
        .icon-lock {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="progress-container">
            <svg class="progress-svg" viewBox="0 0 80 80">
                <circle class="progress-circle-bg" cx="40" cy="40" r="38"></circle>
                <circle class="progress-circle" cx="40" cy="40" r="38" id="progress-ring"></circle>
            </svg>
            <div class="countdown-text" id="countdown">{{DELAY_SECONDS}}</div>
        </div>
        
        <div class="message">{{LOADING_MESSAGE}}</div>
        <div class="sub-message">안전하게 이동 중입니다. 잠시만 기다려주세요.</div>
        
        <a href="{{TARGET_URL}}" class="btn">지금 바로 이동</a>
        
        <div class="security-badge">
            <svg class="icon-lock" viewBox="0 0 24 24">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
            </svg>
            Secure Redirect Technology
        </div>
    </div>
    
    {{COUNTDOWN_SCRIPT}}
    
    <script>
        (function() {
            var totalSeconds = {{DELAY_SECONDS}};
            var circle = document.getElementById("progress-ring");
            var radius = circle.r.baseVal.value;
            var circumference = radius * 2 * Math.PI;
            
            circle.style.strokeDasharray = `${circumference} ${circumference}`;
            circle.style.strokeDashoffset = circumference;
            
            setTimeout(() => {
                circle.style.transition = `stroke-dashoffset ${totalSeconds}s linear`;
                circle.style.strokeDashoffset = "0";
            }, 100);
        })();
    </script>
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
