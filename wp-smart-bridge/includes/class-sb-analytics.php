<?php
/**
 * 분석 엔진 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Analytics
{

    /**
     * 총 클릭 수 조회
     * 
     * @param string $start_date 시작 날짜
     * @param string $end_date 종료 날짜
     * @param string|null $platform 플랫폼 필터
     * @return int 클릭 수
     */
    public function get_total_clicks($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT COUNT(*) FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * 고유 방문자 수 조회 (UV)
     * 
     * @param string $start_date 시작 날짜
     * @param string $end_date 종료 날짜
     * @param string|null $platform 플랫폼 필터
     * @return int 고유 방문자 수
     */
    public function get_unique_visitors($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * 전일 대비 증감률 계산
     * 
     * @return float 증감률 (%)
     */
    public function get_growth_rate($platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();

        $today = new DateTime('now', $timezone);
        $yesterday = new DateTime('yesterday', $timezone);

        $sql_today = "SELECT COUNT(*) FROM $table WHERE DATE(visited_at) = %s";
        $params_today = [$today->format('Y-m-d')];

        $sql_yesterday = "SELECT COUNT(*) FROM $table WHERE DATE(visited_at) = %s";
        $params_yesterday = [$yesterday->format('Y-m-d')];

        if ($platform) {
            $sql_today .= " AND platform = %s";
            $params_today[] = $platform;
            $sql_yesterday .= " AND platform = %s";
            $params_yesterday[] = $platform;
        }

        // 오늘 클릭 수
        $today_clicks = (int) $wpdb->get_var($wpdb->prepare($sql_today, $params_today));

        // 어제 클릭 수
        $yesterday_clicks = (int) $wpdb->get_var($wpdb->prepare($sql_yesterday, $params_yesterday));

        // 증감률 계산
        if ($yesterday_clicks === 0) {
            return $today_clicks > 0 ? 100.0 : 0.0;
        }

        return round((($today_clicks - $yesterday_clicks) / $yesterday_clicks) * 100, 1);
    }

    /**
     * 활성 링크 수 조회
     * 
     * @return int 링크 수
     */
    public function get_active_links_count()
    {
        $query = new WP_Query([
            'post_type' => 'sb_link',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false,
        ]);

        return $query->found_posts;
    }

    /**
     * 시간대별 클릭 수 조회 (0-23시)
     * 
     * @param string $start_date 시작 날짜
     * @param string $end_date 종료 날짜
     * @return array 24개 요소 배열
     */
    public function get_clicks_by_hour($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT HOUR(visited_at) as hour, COUNT(*) as clicks 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY HOUR(visited_at) ORDER BY hour";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // 24시간 배열 초기화
        $clicks_by_hour = array_fill(0, 24, 0);

        foreach ($results as $row) {
            $clicks_by_hour[(int) $row['hour']] = (int) $row['clicks'];
        }

        return $clicks_by_hour;
    }

    /**
     * 플랫폼별 점유율 조회
     * 
     * @param string $start_date 시작 날짜
     * @param string $end_date 종료 날짜
     * @return array 플랫폼 => 클릭 수
     */
    public function get_platform_share($start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT platform, COUNT(*) as clicks 
             FROM $table 
             WHERE visited_at BETWEEN %s AND %s 
             GROUP BY platform 
             ORDER BY clicks DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        $platform_share = [];

        foreach ($results as $row) {
            $platform_share[$row['platform']] = (int) $row['clicks'];
        }

        return $platform_share;
    }

    /**
     * 일별 추세 조회
     * 
     * @param int $days 일수
     * @return array 날짜별 클릭 수 배열
     */
    public function get_daily_trend($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT DATE(visited_at) as date, COUNT(*) as clicks 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY DATE(visited_at) ORDER BY date";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // 날짜별 데이터 맵 생성
        $date_map = [];
        foreach ($results as $row) {
            $date_map[$row['date']] = (int) $row['clicks'];
        }

        // 모든 날짜에 대해 데이터 생성 (없는 날짜는 0)
        $daily_trend = [];
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $current = clone $start;

        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            $daily_trend[] = [
                'date' => $date_str,
                'clicks' => isset($date_map[$date_str]) ? $date_map[$date_str] : 0,
            ];
            $current->modify('+1 day');
        }

        return $daily_trend;
    }

    /**
     * 특정 링크의 통계 조회
     * 
     * @param int $link_id 링크 ID
     * @param int $days 일수
     * @return array 통계 데이터
     */
    public function get_link_stats($link_id, $days = 30)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();

        $end = new DateTime('now', $timezone);
        $start = clone $end;
        $start->modify('-' . ($days - 1) . ' days');

        $start_date = $start->format('Y-m-d 00:00:00');
        $end_date = $end->format('Y-m-d 23:59:59');

        // 총 클릭
        $total_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $start_date,
            $end_date
        ));

        // UV
        $unique_visitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $start_date,
            $end_date
        ));

        // 일별 추세
        $daily_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(visited_at) as date, COUNT(*) as clicks 
             FROM $table 
             WHERE link_id = %d AND visited_at BETWEEN %s AND %s 
             GROUP BY DATE(visited_at) 
             ORDER BY date",
            $link_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        return [
            'total_clicks' => $total_clicks,
            'unique_visitors' => $unique_visitors,
            'daily_trend' => $daily_results,
        ];
    }


    /**
     * 실제 데이터에서 유니크한 플랫폼 목록 조회
     * 
     * @return array 플랫폼 목록
     */
    public function get_available_platforms()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $platforms = $wpdb->get_col(
            "SELECT DISTINCT platform FROM $table WHERE platform IS NOT NULL AND platform != '' ORDER BY platform ASC"
        );

        return $platforms ?: [];
    }

    /**
     * 기간별 인기 링크 조회 (필터 지원)
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @param int $limit 개수
     * @return array 링크 목록
     */
    public function get_top_links($start_date, $end_date, $platform = null, $limit = 20)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT link_id, COUNT(*) as clicks 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY link_id ORDER BY clicks DESC LIMIT %d";
        $params[] = $limit;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        if (empty($results)) {
            return [];
        }

        // 링크 ID 배열 추출
        $link_ids = [];
        foreach ($results as $result) {
            $link_ids[] = $result->link_id;
        }

        // 포스트 조회 (한 번에)
        $posts = get_posts([
            'post_type' => 'sb_link',
            'include' => $link_ids,
            'posts_per_page' => -1,
        ]);

        // ✅ N+1 쿼리 최적화
        update_meta_cache('post', $link_ids);

        // 포스트 ID를 키로 하는 맵 생성 (빠른 조회를 위해)
        $posts_map = [];
        foreach ($posts as $post) {
            $posts_map[$post->ID] = $post;
        }

        $links = [];
        foreach ($results as $result) {
            if (!isset($posts_map[$result->link_id])) {
                continue; // 삭제된 링크 건너뜀
            }

            $post = $posts_map[$result->link_id];
            $slug = $post->post_title;

            $links[] = [
                'id' => $post->ID,
                'slug' => $slug,
                'short_link' => SB_Helpers::get_short_link_url($slug), // ✅ 실제 단축 URL 생성
                'target_url' => get_post_meta($post->ID, 'target_url', true),
                'platform' => get_post_meta($post->ID, 'platform', true),
                'clicks' => (int) $result->clicks,
            ];
        }

        return $links;
    }

    /**
     * 누적 인기 링크 조회 (필터 지원)
     * 
     * @param int $limit 개수
     * @param string|null $platform 플랫폼 필터
     * @return array 링크 목록
     */
    public function get_all_time_top_links($limit = 100, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT link_id, COUNT(*) as clicks FROM $table";
        $params = [];

        if ($platform) {
            $sql .= " WHERE platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY link_id ORDER BY clicks DESC LIMIT %d";
        $params[] = $limit;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        if (empty($results)) {
            return [];
        }

        // 링크 ID 배열 추출
        $link_ids = array_column($results, 'link_id');

        // ✅ N+1 쿼리 최적화: 모든 메타 데이터 한 번에 로드
        update_meta_cache('post', $link_ids);

        $top_links = [];

        foreach ($results as $row) {
            $post = get_post($row['link_id']);
            if ($post) {
                // Short Link가 명확하게 생성되는지 확인
                $slug = $post->post_title;
                $short_link = SB_Helpers::get_short_link_url($slug);

                $top_links[] = [
                    'id' => $post->ID,
                    'slug' => $slug,
                    'short_link' => $short_link,
                    'target_url' => get_post_meta($post->ID, 'target_url', true), // 캐시에서 로드
                    'platform' => get_post_meta($post->ID, 'platform', true),      // 캐시에서 로드
                    'clicks' => (int) $row['clicks'],
                ];
            }
        }

        return $top_links;
    }

    /**
     * 특정 링크의 오늘 UV 조회
     * 
     * @param int $link_id 링크 ID
     * @return int 오늘 UV
     */
    public function get_link_today_uv($link_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();
        $today = new DateTime('now', $timezone);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_ip) FROM $table 
             WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59')
        ));
    }

    /**
     * 특정 링크의 누적 UV 조회
     * 
     * @param int $link_id 링크 ID
     * @return int 누적 UV
     */
    public function get_link_total_uv($link_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE link_id = %d",
            $link_id
        ));
    }

    /**
     * 특정 링크의 오늘 클릭수 조회
     * 
     * @param int $link_id 링크 ID
     * @return int 오늘 클릭수
     */
    public function get_link_today_clicks($link_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();
        $today = new DateTime('now', $timezone);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59')
        ));
    }

    /**
     * 오늘 총 클릭수 조회 (전체 링크)
     * 
     * @return int 오늘 총 클릭수
     */
    public function get_today_total_clicks()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();
        $today = new DateTime('now', $timezone);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE visited_at BETWEEN %s AND %s",
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59')
        ));
    }

    /**
     * 오늘 고유 방문자 수 조회 (전체 링크)
     * 
     * @return int 오늘 UV
     */
    public function get_today_unique_visitors()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();
        $today = new DateTime('now', $timezone);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE visited_at BETWEEN %s AND %s",
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59')
        ));
    }

    /**
     * 누적 총 클릭수 조회 (전체 링크, 전체 기간)
     * 
     * @return int 누적 총 클릭수
     */
    public function get_cumulative_total_clicks()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * 누적 고유 방문자 수 조회 (전체 링크, 전체 기간)
     * 
     * @return int 누적 UV
     */
    public function get_cumulative_unique_visitors()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT visitor_ip) FROM $table");
    }

    /**
     * 특정 링크의 누적 총 클릭수 조회
     * 
     * @param int $link_id 링크 ID
     * @return int 누적 클릭수
     */
    public function get_link_cumulative_clicks($link_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE link_id = %d",
            $link_id
        ));
    }

    // ========================================
    // Phase 1: Enhanced Filter Methods
    // ========================================

    /**
     * 필터가 적용된 플랫폼 점유율 조회 (단일 플랫폼 선택 시 해당 플랫폼만 표시)
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|array|null $platform 플랫폼 필터 (단일 또는 배열)
     * @return array 플랫폼 점유율
     */
    public function get_platform_share_filtered($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT platform, COUNT(*) as clicks 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            if (is_array($platform)) {
                $placeholders = implode(',', array_fill(0, count($platform), '%s'));
                $sql .= " AND platform IN ($placeholders)";
                $params = array_merge($params, $platform);
            } else {
                $sql .= " AND platform = %s";
                $params[] = $platform;
            }
        }

        $sql .= " GROUP BY platform ORDER BY clicks DESC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $platform_share = [];
        foreach ($results as $row) {
            $platform_share[$row['platform']] = (int) $row['clicks'];
        }

        return $platform_share;
    }

    /**
     * 동적 증감률 계산 (선택 기간 vs 이전 동일 기간)
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array ['current' => int, 'previous' => int, 'rate' => float]
     */
    public function get_dynamic_growth_rate($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        // 현재 기간 일수 계산
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days = $interval->days + 1;

        // 이전 기간 계산
        $prev_end = clone $start;
        $prev_end->modify('-1 day');
        $prev_start = clone $prev_end;
        $prev_start->modify('-' . ($days - 1) . ' days');

        // 현재 기간 클릭수
        $sql_current = "SELECT COUNT(*) FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params_current = [$start_date, $end_date];

        if ($platform) {
            $sql_current .= " AND platform = %s";
            $params_current[] = $platform;
        }

        $current_clicks = (int) $wpdb->get_var($wpdb->prepare($sql_current, $params_current));

        // 이전 기간 클릭수
        $sql_previous = "SELECT COUNT(*) FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params_previous = [
            $prev_start->format('Y-m-d 00:00:00'),
            $prev_end->format('Y-m-d 23:59:59')
        ];

        if ($platform) {
            $sql_previous .= " AND platform = %s";
            $params_previous[] = $platform;
        }

        $previous_clicks = (int) $wpdb->get_var($wpdb->prepare($sql_previous, $params_previous));

        // 증감률 계산
        if ($previous_clicks === 0) {
            $rate = $current_clicks > 0 ? 100.0 : 0.0;
        } else {
            $rate = round((($current_clicks - $previous_clicks) / $previous_clicks) * 100, 1);
        }

        return [
            'current' => $current_clicks,
            'previous' => $previous_clicks,
            'rate' => $rate,
            'period_days' => $days,
        ];
    }

    // ========================================
    // Phase 2: Referer Analysis Methods
    // ========================================

    /**
     * 유입 경로 TOP N 조회
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @param int $limit 개수
     * @return array 리퍼러별 클릭수
     */
    public function get_referer_stats($start_date, $end_date, $platform = null, $limit = 10)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT 
                    CASE 
                        WHEN referer IS NULL OR referer = '' THEN 'Direct'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referer, 'https://', ''), 'http://', ''), '/', 1), '?', 1)
                    END as referer_domain,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT visitor_ip) as unique_visitors
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY referer_domain ORDER BY clicks DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * 유입 경로 그룹핑 (Direct, SNS, Search, Other)
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 그룹별 클릭수
     */
    public function get_referer_groups($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        // SNS 도메인 목록
        $sns_domains = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com', 't.co', 'linkedin.com', 'tiktok.com', 'youtube.com', 'reddit.com', 'pinterest.com', 'threads.net', 'naver.me', 'blog.naver.com', 'cafe.naver.com', 'band.us', 'kakao.com', 'open.kakao.com'];

        // 검색 도메인 목록
        $search_domains = ['google.com', 'google.co.kr', 'bing.com', 'yahoo.com', 'naver.com', 'daum.net', 'zum.com', 'duckduckgo.com', 'baidu.com'];

        $sql = "SELECT referer FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $results = $wpdb->get_col($wpdb->prepare($sql, $params));

        $groups = [
            'Direct' => 0,
            'SNS' => 0,
            'Search' => 0,
            'Other' => 0,
        ];

        foreach ($results as $referer) {
            if (empty($referer)) {
                $groups['Direct']++;
                continue;
            }

            $referer_lower = strtolower($referer);
            $found = false;

            // SNS 체크
            foreach ($sns_domains as $domain) {
                if (strpos($referer_lower, $domain) !== false) {
                    $groups['SNS']++;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // 검색 체크
                foreach ($search_domains as $domain) {
                    if (strpos($referer_lower, $domain) !== false) {
                        $groups['Search']++;
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $groups['Other']++;
            }
        }

        return $groups;
    }

    // ========================================
    // Phase 3: Device/Browser Analysis Methods
    // ========================================

    /**
     * User-Agent 파싱하여 디바이스 정보 추출
     * 
     * @param string $user_agent User-Agent 문자열
     * @return array ['device' => string, 'os' => string, 'browser' => string]
     */
    private function parse_user_agent($user_agent)
    {
        $result = [
            'device' => 'Unknown',
            'os' => 'Unknown',
            'browser' => 'Unknown',
        ];

        if (empty($user_agent)) {
            return $result;
        }

        $ua_lower = strtolower($user_agent);

        // 디바이스 감지
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*(mobile|opera mini)))/i', $user_agent)) {
            $result['device'] = 'Tablet';
        } elseif (preg_match('/(mobile|iphone|ipod|android.*mobile|windows phone|blackberry|bb10|opera mini|iemobile)/i', $user_agent)) {
            $result['device'] = 'Mobile';
        } else {
            $result['device'] = 'Desktop';
        }

        // OS 감지
        if (preg_match('/windows nt/i', $user_agent)) {
            $result['os'] = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $result['os'] = 'macOS';
        } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
            $result['os'] = 'iOS';
        } elseif (preg_match('/android/i', $user_agent)) {
            $result['os'] = 'Android';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $result['os'] = 'Linux';
        }

        // 브라우저 감지
        if (preg_match('/edg\//i', $user_agent)) {
            $result['browser'] = 'Edge';
        } elseif (preg_match('/opr\//i', $user_agent) || preg_match('/opera/i', $user_agent)) {
            $result['browser'] = 'Opera';
        } elseif (preg_match('/chrome/i', $user_agent) && !preg_match('/edg/i', $user_agent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome/i', $user_agent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/firefox/i', $user_agent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/msie|trident/i', $user_agent)) {
            $result['browser'] = 'IE';
        } elseif (preg_match('/samsungbrowser/i', $user_agent)) {
            $result['browser'] = 'Samsung';
        } elseif (preg_match('/kakaotalk/i', $user_agent)) {
            $result['browser'] = 'KakaoTalk';
        } elseif (preg_match('/naver/i', $user_agent)) {
            $result['browser'] = 'Naver';
        }

        return $result;
    }

    /**
     * 디바이스 분포 조회
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 디바이스별 클릭수
     */
    public function get_device_stats($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT user_agent FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $results = $wpdb->get_col($wpdb->prepare($sql, $params));

        $devices = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0, 'Unknown' => 0];

        foreach ($results as $ua) {
            $parsed = $this->parse_user_agent($ua);
            $devices[$parsed['device']]++;
        }

        // Unknown이 0이면 제거
        if ($devices['Unknown'] === 0) {
            unset($devices['Unknown']);
        }

        return $devices;
    }

    /**
     * OS 분포 조회
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array OS별 클릭수
     */
    public function get_os_stats($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT user_agent FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $results = $wpdb->get_col($wpdb->prepare($sql, $params));

        $os_stats = [];

        foreach ($results as $ua) {
            $parsed = $this->parse_user_agent($ua);
            if (!isset($os_stats[$parsed['os']])) {
                $os_stats[$parsed['os']] = 0;
            }
            $os_stats[$parsed['os']]++;
        }

        arsort($os_stats);
        return $os_stats;
    }

    /**
     * 브라우저 분포 조회
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 브라우저별 클릭수
     */
    public function get_browser_stats($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT user_agent FROM $table WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $results = $wpdb->get_col($wpdb->prepare($sql, $params));

        $browser_stats = [];

        foreach ($results as $ua) {
            $parsed = $this->parse_user_agent($ua);
            if (!isset($browser_stats[$parsed['browser']])) {
                $browser_stats[$parsed['browser']] = 0;
            }
            $browser_stats[$parsed['browser']]++;
        }

        arsort($browser_stats);
        return $browser_stats;
    }

    /**
     * 일별 모바일 비율 추세
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 일별 모바일 비율
     */
    public function get_mobile_ratio_trend($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT DATE(visited_at) as date, user_agent 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " ORDER BY date";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $daily_data = [];

        foreach ($results as $row) {
            $date = $row['date'];
            $parsed = $this->parse_user_agent($row['user_agent']);

            if (!isset($daily_data[$date])) {
                $daily_data[$date] = ['mobile' => 0, 'total' => 0];
            }

            $daily_data[$date]['total']++;
            if ($parsed['device'] === 'Mobile') {
                $daily_data[$date]['mobile']++;
            }
        }

        $trend = [];
        foreach ($daily_data as $date => $data) {
            $trend[] = [
                'date' => $date,
                'mobile_ratio' => $data['total'] > 0
                    ? round(($data['mobile'] / $data['total']) * 100, 1)
                    : 0,
                'mobile_count' => $data['mobile'],
                'total_count' => $data['total'],
            ];
        }

        return $trend;
    }

    /**
     * 플랫폼 × 디바이스 매트릭스
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @return array 플랫폼별 디바이스 분포
     */
    public function get_platform_device_matrix($start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT platform, user_agent 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";

        $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date), ARRAY_A);

        $matrix = [];

        foreach ($results as $row) {
            $platform = $row['platform'] ?: 'Unknown';
            $parsed = $this->parse_user_agent($row['user_agent']);
            $device = $parsed['device'];

            if (!isset($matrix[$platform])) {
                $matrix[$platform] = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
            }

            $matrix[$platform][$device]++;
        }

        return $matrix;
    }

    // ========================================
    // Phase 4: Advanced Statistics Methods
    // ========================================

    /**
     * 기간 비교 (현재 기간 vs 이전 기간)
     * 
     * @param string $current_start 현재 시작일
     * @param string $current_end 현재 종료일
     * @param string $previous_start 이전 시작일
     * @param string $previous_end 이전 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 비교 데이터
     */
    public function get_period_comparison($current_start, $current_end, $previous_start, $previous_end, $platform = null)
    {
        $current_clicks = $this->get_total_clicks($current_start, $current_end, $platform);
        $current_uv = $this->get_unique_visitors($current_start, $current_end, $platform);
        $current_trend = $this->get_daily_trend($current_start, $current_end, $platform);

        $previous_clicks = $this->get_total_clicks($previous_start, $previous_end, $platform);
        $previous_uv = $this->get_unique_visitors($previous_start, $previous_end, $platform);
        $previous_trend = $this->get_daily_trend($previous_start, $previous_end, $platform);

        // 증감률 계산
        $clicks_rate = $previous_clicks > 0
            ? round((($current_clicks - $previous_clicks) / $previous_clicks) * 100, 1)
            : ($current_clicks > 0 ? 100 : 0);

        $uv_rate = $previous_uv > 0
            ? round((($current_uv - $previous_uv) / $previous_uv) * 100, 1)
            : ($current_uv > 0 ? 100 : 0);

        return [
            'current' => [
                'clicks' => $current_clicks,
                'unique_visitors' => $current_uv,
                'trend' => $current_trend,
                'start' => $current_start,
                'end' => $current_end,
            ],
            'previous' => [
                'clicks' => $previous_clicks,
                'unique_visitors' => $previous_uv,
                'trend' => $previous_trend,
                'start' => $previous_start,
                'end' => $previous_end,
            ],
            'comparison' => [
                'clicks_change' => $current_clicks - $previous_clicks,
                'clicks_rate' => $clicks_rate,
                'uv_change' => $current_uv - $previous_uv,
                'uv_rate' => $uv_rate,
            ],
        ];
    }

    /**
     * 요일별 클릭 패턴
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 요일별 클릭수
     */
    public function get_weekday_pattern($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT DAYOFWEEK(visited_at) as day_num, COUNT(*) as clicks 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY DAYOFWEEK(visited_at) ORDER BY day_num";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // 요일 매핑 (MySQL DAYOFWEEK: 1=일요일, 7=토요일)
        $days = ['일', '월', '화', '수', '목', '금', '토'];
        $pattern = [];

        // 모든 요일 초기화
        for ($i = 0; $i < 7; $i++) {
            $pattern[$days[$i]] = 0;
        }

        foreach ($results as $row) {
            $day_index = ((int) $row['day_num']) - 1; // 0-indexed
            $pattern[$days[$day_index]] = (int) $row['clicks'];
        }

        return $pattern;
    }

    /**
     * 재방문자 분석
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 재방문 통계
     */
    public function get_returning_visitors($start_date, $end_date, $platform = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT visitor_ip, COUNT(*) as visit_count 
                FROM $table 
                WHERE visited_at BETWEEN %s AND %s";
        $params = [$start_date, $end_date];

        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }

        $sql .= " GROUP BY visitor_ip";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $new_visitors = 0;      // 1회 방문
        $returning = 0;         // 2-5회 방문
        $frequent = 0;          // 6회 이상

        foreach ($results as $row) {
            $count = (int) $row['visit_count'];
            if ($count === 1) {
                $new_visitors++;
            } elseif ($count <= 5) {
                $returning++;
            } else {
                $frequent++;
            }
        }

        $total = $new_visitors + $returning + $frequent;

        return [
            'new_visitors' => $new_visitors,
            'returning' => $returning,
            'frequent' => $frequent,
            'total' => $total,
            'returning_rate' => $total > 0
                ? round((($returning + $frequent) / $total) * 100, 1)
                : 0,
        ];
    }

    /**
     * 이상치 탐지 (일별 클릭수에서 평균 대비 ±2σ 벗어난 날)
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param string|null $platform 플랫폼 필터
     * @return array 이상치 날짜 목록
     */
    public function get_anomalies($start_date, $end_date, $platform = null)
    {
        $daily_trend = $this->get_daily_trend($start_date, $end_date, $platform);

        if (count($daily_trend) < 7) {
            return ['message' => '데이터가 충분하지 않습니다 (최소 7일 필요)'];
        }

        // 평균과 표준편차 계산
        $clicks = array_column($daily_trend, 'clicks');
        $mean = array_sum($clicks) / count($clicks);

        $variance = 0;
        foreach ($clicks as $click) {
            $variance += pow($click - $mean, 2);
        }
        $stddev = sqrt($variance / count($clicks));

        $upper_bound = $mean + (2 * $stddev);
        $lower_bound = max(0, $mean - (2 * $stddev));

        $anomalies = [
            'spikes' => [],     // 급증
            'drops' => [],      // 급감
            'mean' => round($mean, 1),
            'stddev' => round($stddev, 1),
        ];

        foreach ($daily_trend as $day) {
            if ($day['clicks'] > $upper_bound) {
                $anomalies['spikes'][] = [
                    'date' => $day['date'],
                    'clicks' => $day['clicks'],
                    'deviation' => round(($day['clicks'] - $mean) / $stddev, 2),
                ];
            } elseif ($day['clicks'] < $lower_bound && $day['clicks'] > 0) {
                $anomalies['drops'][] = [
                    'date' => $day['date'],
                    'clicks' => $day['clicks'],
                    'deviation' => round(($day['clicks'] - $mean) / $stddev, 2),
                ];
            }
        }

        return $anomalies;
    }

    // ========================================
    // Phase 5: Individual Link Detailed Analysis
    // ========================================

    /**
     * 개별 링크 상세 통계
     * 
     * @param int $link_id 링크 ID
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @return array 상세 통계
     */
    public function get_link_detailed_stats($link_id, $start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        // 기본 통계
        $total_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $start_date,
            $end_date
        ));

        $unique_visitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_ip) FROM $table WHERE link_id = %d AND visited_at BETWEEN %s AND %s",
            $link_id,
            $start_date,
            $end_date
        ));

        // 시간대별 분포
        $hourly = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(visited_at) as hour, COUNT(*) as clicks 
             FROM $table 
             WHERE link_id = %d AND visited_at BETWEEN %s AND %s 
             GROUP BY HOUR(visited_at) ORDER BY hour",
            $link_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        $clicks_by_hour = array_fill(0, 24, 0);
        foreach ($hourly as $row) {
            $clicks_by_hour[(int) $row['hour']] = (int) $row['clicks'];
        }

        // 일별 추세
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(visited_at) as date, COUNT(*) as clicks 
             FROM $table 
             WHERE link_id = %d AND visited_at BETWEEN %s AND %s 
             GROUP BY DATE(visited_at) ORDER BY date",
            $link_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        return [
            'total_clicks' => $total_clicks,
            'unique_visitors' => $unique_visitors,
            'clicks_by_hour' => $clicks_by_hour,
            'daily_trend' => $daily,
        ];
    }

    /**
     * 개별 링크의 유입 경로 분석
     * 
     * @param int $link_id 링크 ID
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @param int $limit 개수
     * @return array 리퍼러 분석
     */
    public function get_link_referer_breakdown($link_id, $start_date, $end_date, $limit = 10)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT 
                    CASE 
                        WHEN referer IS NULL OR referer = '' THEN 'Direct'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referer, 'https://', ''), 'http://', ''), '/', 1), '?', 1)
                    END as referer_domain,
                    COUNT(*) as clicks
                FROM $table 
                WHERE link_id = %d AND visited_at BETWEEN %s AND %s 
                GROUP BY referer_domain 
                ORDER BY clicks DESC 
                LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, $link_id, $start_date, $end_date, $limit), ARRAY_A);
    }

    /**
     * 개별 링크의 디바이스 분포
     * 
     * @param int $link_id 링크 ID
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @return array 디바이스 분포
     */
    public function get_link_device_breakdown($link_id, $start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $sql = "SELECT user_agent FROM $table WHERE link_id = %d AND visited_at BETWEEN %s AND %s";
        $results = $wpdb->get_col($wpdb->prepare($sql, $link_id, $start_date, $end_date));

        $devices = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
        $browsers = [];
        $os_list = [];

        foreach ($results as $ua) {
            $parsed = $this->parse_user_agent($ua);

            $devices[$parsed['device']]++;

            if (!isset($browsers[$parsed['browser']])) {
                $browsers[$parsed['browser']] = 0;
            }
            $browsers[$parsed['browser']]++;

            if (!isset($os_list[$parsed['os']])) {
                $os_list[$parsed['os']] = 0;
            }
            $os_list[$parsed['os']]++;
        }

        arsort($browsers);
        arsort($os_list);

        return [
            'devices' => $devices,
            'browsers' => $browsers,
            'os' => $os_list,
        ];
    }

    /**
     * 링크 연령별 성과 분석
     * 
     * @param string $start_date 시작일
     * @param string $end_date 종료일
     * @return array 연령대별 성과
     */
    public function get_link_age_performance($start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $posts_table = $wpdb->posts;

        // 링크별 생성일 + 클릭수 조회
        $sql = "SELECT 
                    l.link_id,
                    p.post_date,
                    COUNT(*) as clicks
                FROM $table l
                INNER JOIN $posts_table p ON l.link_id = p.ID
                WHERE l.visited_at BETWEEN %s AND %s
                AND p.post_type = 'sb_link'
                GROUP BY l.link_id, p.post_date";

        $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date), ARRAY_A);

        $now = new DateTime();
        $age_groups = [
            '7일 이내' => ['count' => 0, 'clicks' => 0],
            '7-30일' => ['count' => 0, 'clicks' => 0],
            '30-90일' => ['count' => 0, 'clicks' => 0],
            '90일 이상' => ['count' => 0, 'clicks' => 0],
        ];

        foreach ($results as $row) {
            $created = new DateTime($row['post_date']);
            $age_days = $now->diff($created)->days;
            $clicks = (int) $row['clicks'];

            if ($age_days <= 7) {
                $age_groups['7일 이내']['count']++;
                $age_groups['7일 이내']['clicks'] += $clicks;
            } elseif ($age_days <= 30) {
                $age_groups['7-30일']['count']++;
                $age_groups['7-30일']['clicks'] += $clicks;
            } elseif ($age_days <= 90) {
                $age_groups['30-90일']['count']++;
                $age_groups['30-90일']['clicks'] += $clicks;
            } else {
                $age_groups['90일 이상']['count']++;
                $age_groups['90일 이상']['clicks'] += $clicks;
            }
        }

        // 링크당 평균 클릭수 계산
        foreach ($age_groups as $key => &$group) {
            $group['avg_clicks'] = $group['count'] > 0
                ? round($group['clicks'] / $group['count'], 1)
                : 0;
        }

        return $age_groups;
    }
}
