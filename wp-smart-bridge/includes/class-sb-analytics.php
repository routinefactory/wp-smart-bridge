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
    public function get_growth_rate()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();

        $today = new DateTime('now', $timezone);
        $yesterday = new DateTime('yesterday', $timezone);

        // 오늘 클릭 수
        $today_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE DATE(visited_at) = %s",
            $today->format('Y-m-d')
        ));

        // 어제 클릭 수
        $yesterday_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE DATE(visited_at) = %s",
            $yesterday->format('Y-m-d')
        ));

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
    public function get_clicks_by_hour($start_date, $end_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(visited_at) as hour, COUNT(*) as clicks 
             FROM $table 
             WHERE visited_at BETWEEN %s AND %s 
             GROUP BY HOUR(visited_at) 
             ORDER BY hour",
            $start_date,
            $end_date
        ), ARRAY_A);

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
    public function get_daily_trend($days = 30)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();

        $end = new DateTime('now', $timezone);
        $start = clone $end;
        $start->modify('-' . ($days - 1) . ' days');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(visited_at) as date, COUNT(*) as clicks 
             FROM $table 
             WHERE visited_at BETWEEN %s AND %s 
             GROUP BY DATE(visited_at) 
             ORDER BY date",
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59')
        ), ARRAY_A);

        // 날짜별 데이터 맵 생성
        $date_map = [];
        foreach ($results as $row) {
            $date_map[$row['date']] = (int) $row['clicks'];
        }

        // 모든 날짜에 대해 데이터 생성 (없는 날짜는 0)
        $daily_trend = [];
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
     * 인기 링크 TOP N 조회
     * 
     * @param int $limit 개수
     * @param int $days 기간 (일)
     * @return array 링크 목록
     */
    public function get_top_links($limit = 10, $days = 30)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();

        $end = new DateTime('now', $timezone);
        $start = clone $end;
        $start->modify('-' . ($days - 1) . ' days');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT link_id, COUNT(*) as clicks 
             FROM $table 
             WHERE visited_at BETWEEN %s AND %s 
             GROUP BY link_id 
             ORDER BY clicks DESC 
             LIMIT %d",
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
            $limit
        ), ARRAY_A);

        $top_links = [];

        foreach ($results as $row) {
            $post = get_post($row['link_id']);
            if ($post) {
                $top_links[] = [
                    'id' => $post->ID,
                    'slug' => $post->post_title,
                    'short_link' => SB_Helpers::get_short_link_url($post->post_title),
                    'target_url' => get_post_meta($post->ID, 'target_url', true),
                    'platform' => get_post_meta($post->ID, 'platform', true),
                    'clicks' => (int) $row['clicks'],
                ];
            }
        }

        return $top_links;
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
     * 오늘 인기 링크 TOP N 조회
     * 
     * @param int $limit 개수
     * @return array 링크 목록
     */
    public function get_today_top_links($limit = 100)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';
        $timezone = wp_timezone();
        $today = new DateTime('now', $timezone);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT link_id, COUNT(*) as clicks 
             FROM $table 
             WHERE visited_at BETWEEN %s AND %s 
             GROUP BY link_id 
             ORDER BY clicks DESC 
             LIMIT %d",
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59'),
            $limit
        ), ARRAY_A);

        $top_links = [];

        foreach ($results as $row) {
            $post = get_post($row['link_id']);
            if ($post) {
                $top_links[] = [
                    'id' => $post->ID,
                    'slug' => $post->post_title,
                    'short_link' => SB_Helpers::get_short_link_url($post->post_title),
                    'target_url' => get_post_meta($post->ID, 'target_url', true),
                    'platform' => get_post_meta($post->ID, 'platform', true),
                    'clicks' => (int) $row['clicks'],
                ];
            }
        }

        return $top_links;
    }

    /**
     * 누적 인기 링크 TOP N 조회
     * 
     * @param int $limit 개수
     * @return array 링크 목록
     */
    public function get_all_time_top_links($limit = 100)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT link_id, COUNT(*) as clicks 
             FROM $table 
             GROUP BY link_id 
             ORDER BY clicks DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);

        $top_links = [];

        foreach ($results as $row) {
            $post = get_post($row['link_id']);
            if ($post) {
                $top_links[] = [
                    'id' => $post->ID,
                    'slug' => $post->post_title,
                    'short_link' => SB_Helpers::get_short_link_url($post->post_title),
                    'target_url' => get_post_meta($post->ID, 'target_url', true),
                    'platform' => get_post_meta($post->ID, 'platform', true),
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
}
