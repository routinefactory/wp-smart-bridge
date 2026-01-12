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
    const DEFAULT_SLUG_LENGTH = 7;

    /**
     * 슬러그 생성 최대 재시도 횟수
     */
    const MAX_SLUG_RETRIES = 3;

    /**
     * 메타 데이터 포맷 버전
     *
     * stats_today_pv, stats_today_uv, stats_total_uv 메타 데이터의 형식 버전
     *
     * @since 4.2.0
     */
    const META_FORMAT_VERSION = '1.0';

    /**
     * 메타 데이터 포맷 버전별 구조 정의
     *
     * @since 4.2.0
     */
    const META_FORMATS = [
        '1.0' => [
            'stats_today_pv' => 'count|date',
            'stats_today_uv' => 'count|date',
            'stats_total_uv' => 'count',
        ],
    ];


    /**
     * Base36 문자셋 (소문자 + 숫자)
     * v3.2.0: 대소문자 혼용 시 워드프레스 충돌 방지 위해 Base62 -> Base36 변경
     */
    const SLUG_CHARS = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * Base36 고유 Slug 생성
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
     * 랜덤 문자열 생성 (Base36)
     * 
     * @param int $length 문자열 길이
     * @return string 랜덤 문자열
     */
    public static function generate_random_string($length = 6)
    {
        $chars = self::SLUG_CHARS;
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

        try {
            // v2.9.22 Fix: Check post_name (Unique Index) instead of post_title
            // v3.0.0 Critical Fix: PHP constant must be passed as parameter, not embedded in SQL string
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = %s
                 AND post_name = %s",
                SB_Post_Type::POST_TYPE,
                $slug
            ));

            return (int) $count > 0;
        } catch (Throwable $e) {
            // DB 오류 발생 시 안전하게 false 반환 (슬러그가 없다고 가정)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SB_Helpers::slug_exists() Error: ' . $e->getMessage());
            }
            return false;
        }
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
        // v4.0.0: 파라미터 방식(?go=)으로 변경
        // 자신의 사이트 내의 단축 링크 경로를 타겟으로 설정하는 것을 차단
        $short_link_base = home_url('/?go=');

        // 1. Normalize Base (remove scheme)
        $clean_base = preg_replace('/^(https?:\/\/|\/\/)/i', '', $short_link_base);

        // 2. Normalize Target
        // - Remove scheme
        // - Lowercase (for case-insensitive comparison)
        // v4.2.4 Security: urldecode() 제거 - 인코딩된 악의 URL 검증 우회 방지
        $clean_target = preg_replace('/^(https?:\/\/|\/\/)/i', '', $url);
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
     * v4.0.0: 파라미터 방식(?go=slug)으로 변경
     * 이 함수는 중앙 허브 역할을 하며, 여러 파일에서 호출됨:
     * - class-sb-rest-api.php (Line 330, 696, 785)
     * - class-sb-analytics.php (Line 751, 815, 820)
     * - class-sb-post-type.php (Line 107, 144, 147)
     * - class-sb-admin-ajax.php (Line 343, 915)
     * - dashboard.php (Line 379, 476)
     * 
     * @param string $slug Slug
     * @return string 전체 단축 URL
     */
    public static function get_short_link_url($slug)
    {
        // v4.0.0: 파라미터 방식
        return home_url('/?go=' . $slug);
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
     * @param string $range 범위 (today_7d, 30d, 90d, 180d, 365d)
     * @return array ['start' => DateTime, 'end' => DateTime]
     */
    public static function get_date_range($range)
    {
        $now = new DateTime('now', wp_timezone());
        $start = clone $now;
        $end = clone $now;

        switch ($range) {
            case 'today_7d':
                // 오늘부터 최근 7일 (오늘 포함 7일)
                $start->modify('-6 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case '30d':
                // 최근 30일
                $start->modify('-29 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case '90d':
                // 최근 3개월 (90일)
                $start->modify('-89 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case '180d':
                // 최근 6개월 (180일)
                $start->modify('-179 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;

            case '365d':
                // 최근 12개월 (365일)
                $start->modify('-364 days')->setTime(0, 0, 0);
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

    /**
     * 통계 캐시 업데이트 (로그 저장 후 호출)
     *
     * 대시보드와 100% 데이터 일치를 보장하기 위해
     * 증분 방식이 아닌, 로그 테이블의 실제 Count를 조회하여 저장합니다.
     *
     * @param int $post_id 링크 ID
     */
    public static function update_stats_cache_after_log($post_id)
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'sb_analytics_logs';
            $today = current_time('Y-m-d');

            // 1. 오늘 PV (정확한 Count)
            // 로깅 직후이므로 방금 저장된 로그가 포함됨
            $today_pv = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                 WHERE link_id = %d AND DATE(visited_at) = %s",
                $post_id,
                $today
            ));
            self::set_today_stat($post_id, 'stats_today_pv', $today_pv, $today);

            // 2. 오늘 UV (정확한 Count)
            $today_uv = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT visitor_ip) FROM $table
                 WHERE link_id = %d AND DATE(visited_at) = %s",
                $post_id,
                $today
            ));
            self::set_today_stat($post_id, 'stats_today_uv', $today_uv, $today);

            // 3. 누적 UV (정확한 Count)
            $total_uv = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE link_id = %d",
                $post_id
            ));
            update_post_meta($post_id, 'stats_total_uv', $total_uv);

            // 메타 캐시 초기화 (즉시 반영을 위해)
            wp_cache_delete($post_id, 'post_meta');
        } catch (Throwable $e) {
            // 캐시 업데이트 실패 시 로깅만 수행 (사용자 경험에는 영향 없음)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SB_Helpers::update_stats_cache_after_log() Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 오늘 통계 저장 (버전 관리 포함)
     *
     * @param int $post_id 링크 ID
     * @param string $meta_key 메타 키 (stats_today_pv, stats_today_uv)
     * @param int $count 통계 값
     * @param string $date 날짜 (Y-m-d)
     * @since 4.2.0
     */
    public static function set_today_stat($post_id, $meta_key, $count, $date = null)
    {
        if ($date === null) {
            $date = current_time('Y-m-d');
        }

        $value = self::format_today_stat($count, $date);
        update_post_meta($post_id, $meta_key, $value);
    }

    /**
     * 오늘 통계 포맷팅 (버전 관리)
     *
     * @param int $count 통계 값
     * @param string $date 날짜 (Y-m-d)
     * @return string 포맷팅된 값
     * @since 4.2.0
     */
    public static function format_today_stat($count, $date)
    {
        $format = self::META_FORMATS[self::META_FORMAT_VERSION]['stats_today_pv'];
        
        if ($format === 'count|date') {
            return $count . '|' . $date;
        }
        
        // 향후 다른 포맷이 추가되면 여기서 처리
        return $count . '|' . $date;
    }

    /**
     * 메타 데이터 마이그레이션 실행
     *
     * @param string $from_version 이전 버전
     * @param string $to_version 목표 버전
     * @return bool 성공 여부
     * @since 4.2.0
     */
    public static function migrate_meta_data($from_version, $to_version)
    {
        // 현재는 1.0 버전만 존재하므로 마이그레이션 필요 없음
        // 향후 새로운 포맷이 추가되면 여기서 마이그레이션 로직 구현
        return true;
    }

    /**
     * 메타 데이터 유효성 검증
     *
     * @param string $meta_key 메타 키
     * @param mixed $value 메타 값
     * @return bool 유효 여부
     * @since 4.2.0
     */
    public static function validate_meta_value($meta_key, $value)
    {
        $format = self::META_FORMATS[self::META_FORMAT_VERSION][$meta_key] ?? null;
        
        if ($format === null) {
            return false;
        }

        if ($format === 'count|date') {
            // "count|date" 형식 검증
            if (!is_string($value) || strpos($value, '|') === false) {
                return false;
            }
            
            list($count, $date) = explode('|', $value, 2);
            
            // count가 숫자인지 확인
            if (!is_numeric($count)) {
                return false;
            }
            
            // date가 유효한 날짜 형식인지 확인
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return false;
            }
            
            return true;
        }

        if ($format === 'count') {
            // 단순 숫자 형식 검증
            return is_numeric($value);
        }

        return false;
    }

    /**
     * 오늘 통계 읽기 (날짜 체크 포함)
     * 
     * @param int $post_id 링크 ID
     * @param string $meta_key 메타 키 (stats_today_pv, stats_today_uv)
     * @return int 통계 값 (날짜가 다르면 0 반환)
     */
    public static function get_today_stat($post_id, $meta_key)
    {
        $raw = get_post_meta($post_id, $meta_key, true);

        // 데이터가 없거나 형식이 잘못된 경우 0
        if (!$raw || strpos($raw, '|') === false) {
            return 0;
        }

        list($count, $date) = explode('|', $raw);
        $today = current_time('Y-m-d');

        // 날짜가 오늘인 경우에만 값 반환, 아니면 0 (자동 리셋 효과)
        return ($date === $today) ? intval($count) : 0;
    }

    // =========================================================================
    // Platform List Caching (Link Management Tab Enhancement)
    // =========================================================================

    /**
     * 플랫폼 목록 캐시 키
     */
    const PLATFORM_CACHE_KEY = 'sb_platforms_list';
    const PLATFORM_CACHE_EXPIRATION = 3600; // 1시간

    /**
     * 플랫폼 목록 가져오기 (캐싱 적용)
     *
     * @return array 플랫폼 목록 ['platform_name' => count]
     * @since 4.3.0
     */
    public static function get_platforms_cached()
    {
        // 캐시 확인
        $cached = get_transient(self::PLATFORM_CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        // 캐시 미스 - 데이터베이스 조회
        $platforms = self::get_platforms_from_db();

        // 캐시 저장 (1시간)
        set_transient(self::PLATFORM_CACHE_KEY, $platforms, self::PLATFORM_CACHE_EXPIRATION);

        return $platforms;
    }

    /**
     * 데이터베이스에서 플랫폼 목록 조회
     *
     * @return array 플랫폼 목록 ['platform_name' => count]
     * @since 4.3.0
     */
    private static function get_platforms_from_db()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT pm.meta_value as platform, COUNT(*) as count
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'platform'
             AND p.post_type = '" . SB_Post_Type::POST_TYPE . "'
             AND p.post_status = 'publish'
             GROUP BY pm.meta_value
             ORDER BY count DESC"
        );

        $platforms = [];
        foreach ($results as $row) {
            $platform = !empty($row->platform) ? $row->platform : 'Etc';
            $platforms[$platform] = intval($row->count);
        }

        return $platforms;
    }

    /**
     * 플랫폼 캐시 삭제
     *
     * 링크 생성/수정/삭제 시 호출하여 캐시 갱신
     *
     * @since 4.3.0
     */
    public static function clear_platforms_cache()
    {
        delete_transient(self::PLATFORM_CACHE_KEY);
    }

    // =========================================================================
    // Metadata Format Enhancement (JSON Support)
    // =========================================================================

    /**
     * 메타 데이터 포맷 버전 상수 (JSON 지원)
     */
    const META_FORMAT_VERSION_2 = '2.0';

    /**
     * 메타 데이터 포맷 버전별 구조 정의 (확장)
     *
     * @since 4.3.0
     */
    const META_FORMATS_EXTENDED = [
        '1.0' => [
            'stats_today_pv' => 'count|date',
            'stats_today_uv' => 'count|date',
            'stats_total_uv' => 'count',
        ],
        '2.0' => [
            'stats_today_pv' => 'json',
            'stats_today_uv' => 'json',
            'stats_total_uv' => 'json',
        ],
    ];

    /**
     * JSON 형식의 오늘 통계 저장
     *
     * @param int $post_id 링크 ID
     * @param string $meta_key 메타 키 (stats_today_pv, stats_today_uv)
     * @param int $count 통계 값
     * @param string $date 날짜 (Y-m-d)
     * @since 4.3.0
     */
    public static function set_today_stat_json($post_id, $meta_key, $count, $date = null)
    {
        if ($date === null) {
            $date = current_time('Y-m-d');
        }

        $data = [
            'count' => intval($count),
            'date' => $date,
            'updated_at' => current_time('Y-m-d H:i:s'),
            'version' => self::META_FORMAT_VERSION_2
        ];

        $value = wp_json_encode($data);
        update_post_meta($post_id, $meta_key, $value);
    }

    /**
     * JSON 형식의 오늘 통계 읽기
     *
     * @param int $post_id 링크 ID
     * @param string $meta_key 메타 키 (stats_today_pv, stats_today_uv)
     * @return int 통계 값 (날짜가 다르면 0 반환)
     * @since 4.3.0
     */
    public static function get_today_stat_json($post_id, $meta_key)
    {
        $raw = get_post_meta($post_id, $meta_key, true);

        // 데이터가 없거나 JSON 디코딩 실패 시 0 반환
        if (empty($raw)) {
            return 0;
        }

        $data = json_decode($raw, true);

        // JSON 디코딩 실패 시 기존 형식으로 시도
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            // 기존 "count|date" 형식 호환성 처리
            return self::get_today_stat($post_id, $meta_key);
        }

        // 날짜 확인
        $today = current_time('Y-m-d');
        if (isset($data['date']) && $data['date'] === $today) {
            return isset($data['count']) ? intval($data['count']) : 0;
        }

        return 0;
    }

    /**
     * 메타 데이터 마이그레이션 (1.0 → 2.0)
     *
     * "count|date" 형식을 JSON 형식으로 변환
     *
     * @param int $post_id 링크 ID
     * @return bool 성공 여부
     * @since 4.3.0
     */
    public static function migrate_meta_to_json($post_id)
    {
        $meta_keys = ['stats_today_pv', 'stats_today_uv', 'stats_total_uv'];

        foreach ($meta_keys as $meta_key) {
            $raw = get_post_meta($post_id, $meta_key, true);

            // 데이터가 없으면 건너뜀
            if (empty($raw)) {
                continue;
            }

            // 이미 JSON 형식이면 건너뜀
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                continue;
            }

            // "count|date" 형식인지 확인
            if (strpos($raw, '|') !== false) {
                list($count, $date) = explode('|', $raw, 2);

                // JSON 형식으로 변환
                $data = [
                    'count' => intval($count),
                    'date' => $date,
                    'updated_at' => current_time('Y-m-d H:i:s'),
                    'version' => self::META_FORMAT_VERSION_2
                ];

                $value = wp_json_encode($data);
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        return true;
    }

    /**
     * 모든 링크의 메타 데이터 마이그레이션 (대량 처리)
     *
     * @param int $batch_size 배치 크기
     * @return array ['migrated' => int, 'total' => int, 'completed' => bool]
     * @since 4.3.0
     */
    public static function migrate_all_meta_to_json($batch_size = 100)
    {
        global $wpdb;

        // 마이그레이션 상태 확인
        $migrated_count = get_option('sb_meta_json_migrated_count', 0);
        $total_links = wp_count_posts(SB_Post_Type::POST_TYPE)->publish;

        // 이미 완료된 경우
        if ($migrated_count >= $total_links) {
            return [
                'migrated' => $migrated_count,
                'total' => $total_links,
                'completed' => true
            ];
        }

        // 배치 처리
        $offset = $migrated_count;
        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status = 'publish'
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            SB_Post_Type::POST_TYPE,
            $batch_size,
            $offset
        ));

        $batch_migrated = 0;
        foreach ($posts as $post_id) {
            if (self::migrate_meta_to_json($post_id)) {
                $batch_migrated++;
            }
        }

        // 진행 상황 업데이트
        $new_migrated_count = $migrated_count + $batch_migrated;
        update_option('sb_meta_json_migrated_count', $new_migrated_count);

        $completed = ($new_migrated_count >= $total_links);

        return [
            'migrated' => $new_migrated_count,
            'total' => $total_links,
            'completed' => $completed
        ];
    }

    /**
     * 메타 데이터 마이그레이션 초기화
     *
     * @since 4.3.0
     */
    public static function reset_meta_migration()
    {
        delete_option('sb_meta_json_migrated_count');
    }
    // =========================================================================
    // Cache Tag System (P1 Performance Improvement)
    // =========================================================================

    /**
     * 캐시 태그 그룹 상수
     */
    const CACHE_TAG_ANALYTICS = 'sb_analytics';
    const CACHE_TAG_LINKS = 'sb_links';
    const CACHE_TAG_PLATFORMS = 'sb_platforms';
    const CACHE_TAG_STATS = 'sb_stats';

    /**
     * 캐시 태그 기본 만료 시간 (10분)
     */
    const CACHE_TAG_EXPIRATION = 600;

    /**
     * 캐시 태그 저장소 옵션 키
     */
    const CACHE_TAG_REGISTRY_KEY = 'sb_cache_tag_registry';

    /**
     * 캐시 태그 레지스트리 가져오기
     *
     * @return array 태그 => [cache_key1, cache_key2, ...]
     */
    private static function get_cache_tag_registry()
    {
        $registry = get_option(self::CACHE_TAG_REGISTRY_KEY, []);
        return is_array($registry) ? $registry : [];
    }

    /**
     * 캐시 태그 레지스트리 저장
     *
     * @param array $registry 태그 레지스트리
     */
    private static function set_cache_tag_registry($registry)
    {
        update_option(self::CACHE_TAG_REGISTRY_KEY, $registry, false);
    }

    /**
     * 캐시 키를 태그에 등록
     *
     * @param string $cache_key 캐시 키
     * @param array $tags 태그 배열
     */
    private static function register_cache_tags($cache_key, $tags)
    {
        if (empty($tags)) {
            return;
        }

        $registry = self::get_cache_tag_registry();

        foreach ($tags as $tag) {
            if (!isset($registry[$tag])) {
                $registry[$tag] = [];
            }

            // 중복 방지
            if (!in_array($cache_key, $registry[$tag], true)) {
                $registry[$tag][] = $cache_key;
            }
        }

        self::set_cache_tag_registry($registry);
    }

    /**
     * 태그로 관련 캐시 모두 삭제
     *
     * @param string|array $tags 태그 또는 태그 배열
     */
    public static function invalidate_cache_by_tags($tags)
    {
        if (empty($tags)) {
            return;
        }

        if (!is_array($tags)) {
            $tags = [$tags];
        }

        $registry = self::get_cache_tag_registry();
        $deleted_count = 0;

        foreach ($tags as $tag) {
            if (isset($registry[$tag])) {
                foreach ($registry[$tag] as $cache_key) {
                    delete_transient($cache_key);
                    $deleted_count++;
                }
                unset($registry[$tag]);
            }
        }

        self::set_cache_tag_registry($registry);

        return $deleted_count;
    }

    /**
     * 태그가 적용된 캐시 저장
     *
     * @param string $cache_key 캐시 키
     * @param mixed $value 캐시할 값
     * @param array $tags 태그 배열
     * @param int $expiration 만료 시간 (초)
     * @return bool 성공 여부
     */
    public static function set_cache_with_tags($cache_key, $value, $tags = [], $expiration = null)
    {
        if ($expiration === null) {
            $expiration = self::CACHE_TAG_EXPIRATION;
        }

        $result = set_transient($cache_key, $value, $expiration);

        if ($result) {
            self::register_cache_tags($cache_key, $tags);
        }

        return $result;
    }

    /**
     * 태그가 적용된 캐시 가져오기
     *
     * @param string $cache_key 캐시 키
     * @return mixed 캐시된 값 또는 false
     */
    public static function get_cache_with_tags($cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * 캐시 태그 레지스트리 정리 (오래된 캐시 키 제거)
     *
     * @param int $max_size 최대 레지스트리 크기
     */
    public static function cleanup_cache_tag_registry($max_size = 1000)
    {
        $registry = self::get_cache_tag_registry();
        $total_keys = 0;

        foreach ($registry as $tag => $keys) {
            $total_keys += count($keys);
        }

        if ($total_keys <= $max_size) {
            return;
        }

        // 가장 오래된 캐시 키부터 제거
        global $wpdb;
        $old_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 ORDER BY option_id ASC
                 LIMIT %d",
                '_transient_sb_%',
                $total_keys - $max_size
            )
        );

        foreach ($old_keys as $option_name) {
            // _transient_ 접두사 제거
            $cache_key = str_replace('_transient_', '', $option_name);

            // 레지스트리에서 제거
            foreach ($registry as $tag => $keys) {
                $registry[$tag] = array_diff($keys, [$cache_key]);
                if (empty($registry[$tag])) {
                    unset($registry[$tag]);
                }
            }
        }

        self::set_cache_tag_registry($registry);
    }

    /**
     * 모든 캐시 태그 레지스트리 초기화
     */
    public static function clear_cache_tag_registry()
    {
        delete_option(self::CACHE_TAG_REGISTRY_KEY);
    }
}
