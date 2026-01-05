<?php
// Mock WordPress Environment
define('ABSPATH', true);
define('SB_PLUGIN_DIR', __DIR__ . '/../wp-smart-bridge/');

// Mock Functions
function get_option($key, $default = false)
{
    if ($key === 'sb_settings')
        return ['redirect_delay' => 0];
    return $default;
}
function nocache_headers()
{
    echo "[Mock] Headers: No Cache\n";
}
function wp_redirect($url, $status = 302)
{
    echo "[Mock] Redirecting to $url ($status)\n";
}
function esc_url($url)
{
    return $url;
}
function esc_url_raw($url)
{
    return $url;
}
function get_post_meta($id, $key, $single = false)
{
    return 'test_platform';
}
function wp_salt($scheme = 'auth')
{
    return 'salt';
}

// Mock DB Class
class SB_Database
{
    public static function log_click($link_id, $hashed_ip, $platform, $referer, $user_agent)
    {
        echo "[Mock] DB: Logged Click for ID $link_id\n";
    }
}
class SB_Helpers
{
    public static function get_platform($id)
    {
        return 'test_platform';
    }
    public static function hash_ip($ip)
    {
        return md5($ip);
    }
    public static function increment_click_count($id)
    {
        echo "[Mock] DB: Incremented Click Count for ID $id\n";
    }
}
class SB_Analytics
{
    public static function parse_user_agent($ua)
    {
        return [];
    }
}

// Mock SB_Redirect for static calls
class SB_Redirect
{
    public static function log_click_sync($id)
    {
        echo "[Mock] Sync Log\n";
    }
    public static function show_redirect_page($id, $url, $delay)
    {
        echo "[Mock] Show Redirect Page\n";
    }
}

// Include the class to test
require_once SB_PLUGIN_DIR . 'includes/class-sb-async-logger.php';

// Mock Server Global
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

echo "--- Starting Simulation: Immediate Redirect ---\n";
// Override fastcgi_finish_request for CLI test
function fastcgi_finish_request()
{
    echo "[Mock] ⚡ Connection Closed (Client Released)\n";
}

// Run Test
try {
    SB_Async_Logger::log_and_redirect(123, 'https://example.com', 0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- Simulation Finished ---\n";
