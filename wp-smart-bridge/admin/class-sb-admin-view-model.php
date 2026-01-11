<?php
/**
 * Admin View Model Class
 * 
 * Handles data preparation for Admin Views, separating logic from presentation.
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.27
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Admin_View_Model
{
    /**
     * Prepare data for Dashboard View
     * 
     * @return array Associative array of data required for dashboard view
     */
    public static function get_dashboard_data()
    {
        $user_id = get_current_user_id();

        // 1. Check API Keys
        $user_api_keys = SB_Database::get_user_api_keys($user_id);
        $has_api_keys = !empty($user_api_keys);

        // 2. Prepare Analytics Data
        // Use dependency injection principle if we were strictly OOP, but new SB_Analytics() is fine here.
        $analytics = new SB_Analytics();

        // Default Range: 30 Days
        $date_range = SB_Helpers::get_date_range('30d');
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];

        // Real-time (Today) Stats
        $today_total_clicks = $analytics->get_today_total_clicks();
        $today_unique_visitors = $analytics->get_today_unique_visitors();

        // Cumulative (All-time) Stats
        $cumulative_total_clicks = $analytics->get_cumulative_total_clicks();
        $cumulative_unique_visitors = $analytics->get_cumulative_unique_visitors();

        // Period Stats (Last 30 days default)
        $total_clicks = $analytics->get_total_clicks($start_date, $end_date);
        $unique_visitors = $analytics->get_unique_visitors($start_date, $end_date);

        // Trends & Breakdowns
        $growth_rate = $analytics->get_growth_rate();
        $active_links = $analytics->get_active_links_count();
        $clicks_by_hour = $analytics->get_clicks_by_hour($start_date, $end_date);
        $platform_share = $analytics->get_platform_share($start_date, $end_date);
        $daily_trend = $analytics->get_daily_trend($start_date, $end_date);

        /**
         * v3.0.4: Weekly and Monthly Trend Data for Multi-Period Charts
         * 
         * These are used by the new consolidated traffic trend section
         * which shows Daily/Weekly/Monthly charts side by side.
         * 
         * @see dashboard.php: sb-weekly-trend-chart, sb-monthly-trend-chart canvas elements
         * @see sb-chart.js: initWeeklyTrend(), initMonthlyTrend() functions
         */
        $weekly_trend = $analytics->get_weekly_trend(30);  // Last 30 weeks
        $monthly_trend = $analytics->get_monthly_trend(30); // Last 30 months

        // Metadata
        $available_platforms = $analytics->get_available_platforms();

        // Top Links (Filtered by period)
        $top_links = $analytics->get_top_links(
            $start_date,
            $end_date,
            null // No specific platform filter initially
        );

        // Top Links (All time, limited to 20)
        $alltime_top_links = $analytics->get_all_time_top_links(20);

        // 3. Update Check (Cached)
        $update_info = SB_Updater::check_github_release(true);
        $has_update = false;
        $latest_version = SB_VERSION;
        $download_url = '';

        if ($update_info && version_compare($update_info['version'], SB_VERSION, '>')) {
            $has_update = true;
            $latest_version = $update_info['version'];
            $download_url = $update_info['download_url'];
        }

        // Return all variables expected by view
        return [
            'has_api_keys' => $has_api_keys,
            'today_total_clicks' => $today_total_clicks,
            'today_unique_visitors' => $today_unique_visitors,
            'cumulative_total_clicks' => $cumulative_total_clicks,
            'cumulative_unique_visitors' => $cumulative_unique_visitors,
            'total_clicks' => $total_clicks,
            'unique_visitors' => $unique_visitors,
            'growth_rate' => $growth_rate,
            'active_links' => $active_links,
            'clicks_by_hour' => $clicks_by_hour,
            'platform_share' => $platform_share,
            'daily_trend' => $daily_trend,
            // v3.0.4: New multi-period trend data
            'weekly_trend' => $weekly_trend,
            'monthly_trend' => $monthly_trend,
            'available_platforms' => $available_platforms,
            'top_links' => $top_links, // v4.2.4: dashboard.php에서 $today_top_links 사용
            'today_top_links' => $top_links, // v4.2.4: dashboard.php에서 사용하는 변수명과 일치
            'alltime_top_links' => $alltime_top_links,
            'has_update' => $has_update,
            'update_info' => $update_info,
            'latest_version' => $latest_version,
            'download_url' => $download_url
        ];
    }

    /**
     * Prepare data for Settings View
     * 
     * @return array Associative array of data required for settings view
     */
    public static function get_settings_data()
    {
        $user_id = get_current_user_id();

        $api_keys = SB_Database::get_user_api_keys($user_id);
        $settings = get_option('sb_settings', []);
        $redirect_delay = isset($settings['redirect_delay']) ? $settings['redirect_delay'] : 0;

        return [
            'api_keys' => $api_keys,
            'settings' => $settings,
            'redirect_delay' => $redirect_delay
        ];
    }
}
