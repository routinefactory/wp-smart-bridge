<?php
/**
 * Asynchronous Logging Handler
 * 
 * Purpose: Decouple database writes from the user redirect flow.
 * Mechanism: Uses fastcgi_finish_request() (PHP-FPM) or output buffering flushing
 * to send the response to the user immediately, then continue processing
 * logging in the background.
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.25
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Async_Logger
{
    /**
     * Singleton Instance
     */
    private static $instance = null;

    /**
     * Get Instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 초기화 (Hooks)
     */
    public static function init()
    {
        add_action('sb_async_log_event', [__CLASS__, 'handle_async_log_cron']);
    }

    /**
     * Cron Handler for Async Logging
     */
    public static function handle_async_log_cron($context)
    {
        self::process_log($context);
    }

    /**
     * Log Click Asynchronously
     * 
     * This method terminates the client connection immediately
     * and THEN processes the logging.
     * 
     * @param int $link_id The ID of the link being visited
     * @param string $target_url The destination URL
     * @param int $redirect_delay Delay in seconds (if any)
     */
    public static function log_and_redirect($link_id, $target_url, $redirect_delay)
    {
        $target_url = esc_url_raw($target_url);
        if (empty($target_url) || !SB_Helpers::validate_url($target_url)) {
            status_header(404);
            exit;
        }

        // 1. Capture Context BEFORE connection close
        // Some server environments might clear globals after finish_request
        $context = [
            'link_id' => $link_id,
            // v3.0.0 Refactor: Use consolidated SB_Helpers::get_client_ip()
            'visitor_ip' => SB_Helpers::get_client_ip(),
            'platform' => SB_Helpers::get_platform($link_id),
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
        ];

        // 2. Perform Redirect / Send Headers
        if ($redirect_delay <= 0) {
            // Immediate Redirect (302)
            // Ensure no caching for the redirect logic itself
            nocache_headers();
            wp_redirect($target_url, 302);
            header("Connection: close");
            header("Content-Encoding: none");
            header("Content-Length: 0");
        } else {
            // Delayed Redirect handling is tricky with async logging because
            // we need to output HTML content.
            // For delayed redirects, we CANNOT use fast_cgi_finish_request immediately
            // because the user needs to see the countdown.
            // In this specific case, we fall back to synchronous logging or
            // schedule a true background task (wp_cron).
            // 
            // Decision: For delay > 0, the UX is already "waiting", so sync logging 
            // is less critical. We will process it synchronously to ensure simplicity.

            // Fix (P9 Audit): Must increment meta counter manually here since we exit early
            SB_Helpers::increment_click_count($link_id);

            SB_Redirect::log_click_sync($link_id);
            SB_Redirect::show_redirect_page($link_id, $target_url, $redirect_delay);
            exit;
        }

        // 3. Flush & Close Connection (The "Async" Magic) or Cron Fallback
        if (function_exists('fastcgi_finish_request')) {
            self::close_connection();
            // In FPM, we can increment count in background too
            SB_Helpers::increment_click_count($context['link_id']);
            self::process_log($context);
        } else {
            // In Non-FPM, we must increment synchronously to ensure accuracy
            SB_Helpers::increment_click_count($context['link_id']);

            // If WP-Cron is disabled, log synchronously for reliability
            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                self::process_log($context);
                exit;
            }

            // Fallback: Schedule background logging
            $scheduled = wp_schedule_single_event(time(), 'sb_async_log_event', [$context]);
            if (!$scheduled) {
                self::process_log($context);
            }
            exit;
        }

        exit;
    }

    /**
     * Actual Logging Logic (Refactored)
     */
    private static function process_log($context)
    {
        // Dispatch to Analytics
        $hashed_ip = SB_Helpers::hash_ip($context['visitor_ip']);

        // Parse UA (Heavy Operation)
        $parsed_ua = [];
        if ($context['user_agent']) {
            $parsed_ua = SB_Analytics::parse_user_agent($context['user_agent']);
        }

        SB_Database::log_click(
            $context['link_id'],
            $hashed_ip,
            $context['platform'],
            $context['referer'],
            $context['user_agent'],
            $parsed_ua
        );

        // 캐시 업데이트 (로그 저장 후 - UV 중복 체크용)
        // 대시보드 데이터와 100% 일치를 위해 로그 저장 후 정확한 값을 계산하여 캐싱
        SB_Helpers::update_stats_cache_after_log($context['link_id']);
    }

    /**
     * Close the HTTP connection but keep the script running
     */
    private static function close_connection()
    {
        // 1. PHP-FPM (Preferred)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        // 2. Apache/ModPHP Fallback
        // This is less reliable but often works
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
}
