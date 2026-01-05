<?php
/**
 * Cron Job Handler
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.27
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Cron
{
    /**
     * Init Cron
     */
    public static function init()
    {
        // Add Schedule
        add_filter('cron_schedules', [__CLASS__, 'add_intervals']);

        // Hook Event
        add_action('sb_daily_midnight_cron', [__CLASS__, 'run_daily_aggregation']);

        // Schedule Event if not exists
        if (!wp_next_scheduled('sb_daily_midnight_cron')) {
            // Schedule for next midnight
            $timestamp = strtotime('tomorrow 00:05:00');
            wp_schedule_event($timestamp, 'daily', 'sb_daily_midnight_cron');
        }

        // v3.0.0 Reliability: Self-healing Cron Check
        // If the cron missed its window (e.g. no traffic at midnight), run it now.
        // We check this on admin_init to avoid slowing down front-end too much, 
        // or on 'init' if data accuracy is critical. 'init' is safer for accuracy.
        add_action('init', [__CLASS__, 'check_missed_aggregation']);
    }

    /**
     * Check for missed aggregation
     */
    public static function check_missed_aggregation()
    {
        // Don't run on every single request if possible, maybe transient check?
        // But for "Self-healing", we need to check if yesterday was processed.

        $last_processed = get_option('sb_last_aggregation_date');
        $yesterday = date('Y-m-d', strtotime('yesterday'));

        if (!$last_processed || $last_processed < $yesterday) {
            // It missed! Run it.
            // But beware of concurrency. Lock?
            // For simplicity in WP: set transient lock for 5 mins
            if (get_transient('sb_aggregation_in_progress')) {
                return;
            }

            set_transient('sb_aggregation_in_progress', true, 300);

            // Run distinct process to avoid timeout
            wp_schedule_single_event(time(), 'sb_daily_midnight_cron');
        }
    }

    /**
     * Add Custom Intervals
     */
    public static function add_intervals($schedules)
    {
        // Standard daily is enough, but defining explicitly is sometimes safer
        return $schedules;
    }

    /**
     * Execute Daily Aggregation
     */
    public static function run_daily_aggregation()
    {
        // 1. Calculate Yesterday
        $yesterday = date('Y-m-d', strtotime('yesterday'));

        // 2. Run Aggregation
        $analytics = new SB_Analytics();
        $result = $analytics->aggregate_daily_stats($yesterday);

        // 3. Log Result (Optional, maybe to error log if failed)
        if (!$result) {
            error_log("[WP Smart Bridge] Daily Aggregation Failed for $yesterday");
        } else {
            // Success: Update state
            update_option('sb_last_aggregation_date', $yesterday);
            delete_transient('sb_aggregation_in_progress');
        }
    }
}

// Initialize on plugin load (done in main file)
