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
        return '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>안전하게 연결 중...</title>
    <style>
        :root {
            --primary: #0066FF;
            --primary-glow: rgba(0, 102, 255, 0.3);
            --bg: #05070A;
            --card-bg: rgba(255, 255, 255, 0.03);
            --text-main: #FFFFFF;
            --text-dim: #94A3B8;
            --border: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Pretendard", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: radial-gradient(circle at 50% 50%, #111827 0%, #05070A 100%);
        }

        /* Animated background mesh */
        .mesh {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.4;
            filter: blur(80px);
        }

        .mesh-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            animation: move 20s infinite alternate;
        }

        @keyframes move {
            from { transform: translate(-10%, -10%); }
            to { transform: translate(10%, 10%); }
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            padding: 48px 32px;
            border-radius: 32px;
            width: 100%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeInScale 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .progress-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 36px;
        }

        .progress-svg {
            transform: rotate(-90deg);
        }

        .track {
            fill: none;
            stroke: var(--border);
            stroke-width: 4;
        }

        .bar {
            fill: none;
            stroke: var(--primary);
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            filter: drop-shadow(0 0 8px var(--primary-glow));
            transition: stroke-dashoffset 1s linear;
        }

        .timer-val {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        p.sub {
            font-size: 15px;
            color: var(--text-dim);
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 32px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 16px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            width: 100%;
            box-shadow: 0 10px 20px -5px var(--primary-glow);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px var(--primary-glow);
            filter: brightness(1.1);
        }

        .security-footer {
            margin-top: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-dim);
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .shield-icon {
            width: 16px;
            height: 16px;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="mesh">
        <div class="mesh-circle" style="width: 400px; height: 400px; background: #003366; top: 10%; left: 10%;"></div>
        <div class="mesh-circle" style="width: 300px; height: 300px; background: #001a33; bottom: 10%; right: 10%; animation-delay: -5s;"></div>
    </div>

    <div class="card">
        <div class="progress-wrapper">
            <svg class="progress-svg" viewBox="0 0 100 100">
                <circle class="track" cx="50" cy="50" r="45"></circle>
                <circle id="progress-ring" class="bar" cx="50" cy="50" r="45"></circle>
            </svg>
            <div class="timer-val" id="countdown">{{DELAY_SECONDS}}</div>
        </div>
        
        <h1>페이지로 이동 중입니다...</h1>
        <p class="sub">보안 서버를 통해 안전하게 연결하고 있습니다.<br>잠시만 기다려 주세요.</p>
        
        <a href="{{TARGET_URL}}" class="action-btn">즉시 연결하기</a>
        
        <div class="security-footer">
            <svg class="shield-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Verified Secure Connection
        </div>
    </div>

    {{COUNTDOWN_SCRIPT}}
    
    <script>
        (function() {
            const total = {{DELAY_SECONDS}};
            const ring = document.getElementById("progress-ring");
            const circumference = 2 * Math.PI * 45;
            
            ring.style.strokeDasharray = circumference;
            ring.style.strokeDashoffset = circumference;
            
            // Subtle entrance sync
            setTimeout(() => {
                ring.style.transition = `stroke-dashoffset ${total}s linear`;
                ring.style.strokeDashoffset = 0;
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
            '{{DELAY_SECONDS}}',
            '{{TARGET_URL}}',
            '{{COUNTDOWN_SCRIPT}}',
            // 'id="countdown"', // JavaScript가 참조하는 필수 ID - Moved to validate_template for regex check
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
        // 필수 플레이스홀더 검사
        $required_placeholders = [
            '{{DELAY_SECONDS}}' => '타이머 숫자',
            '{{TARGET_URL}}' => '타겟 URL', // Added based on get_ai_prompt_example and common sense for this template
            '{{COUNTDOWN_SCRIPT}}' => '카운트다운 스크립트',
            // '{{LOADING_MESSAGE}}' => '로딩 메시지', // 제거됨 (v2.9.14) - 사용자 피드백 반영
        ];

        foreach ($required_placeholders as $placeholder => $name) {
            if (strpos($template, $placeholder) === false) {
                return "오류: 필수 Placeholder가 누락되었습니다: $placeholder ($name)";
            }
        }

        // 필수 HTML ID 검사 (느슨한 검사)
        // id="countdown" 또는 id='countdown' 허용, 공백 허용
        if (!preg_match('/id\s*=\s*["\']countdown["\']/', $template)) {
            return '오류: 필수 HTML ID가 누락되었습니다: id="countdown" (타이머 표시에 필요)';
        }

        // 자바스크립트 보안 검사 (간단한 XSS 방지)
        if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $template)) {
            // 허용된 안전한 스크립트 외에 악성 패턴 검사 가능
            // 현재는 관리자만 템플릿 수정 가능하므로 기본 필터링만 적용
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
4. id="countdown" - 카운트다운을 표시할 요소의 ID (반드시 이 ID를 가진 요소가 있어야 함)

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
