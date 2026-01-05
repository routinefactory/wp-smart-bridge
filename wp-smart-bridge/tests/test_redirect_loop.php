<?php
/**
 * Test Script: Redirect Loop Vulnerability Check (Standalone)
 */

// 1. Mock WordPress Functions & Constants
define('ABSPATH', __DIR__ . '/../');
define('SB_PLUGIN_DIR', __DIR__ . '/../');

function home_url($path = '')
{
    return 'http://localhost/site' . $path;
}

function __($text, $domain = 'default')
{
    return $text;
}

function sprintf($format, ...$args)
{
    return vsprintf($format, $args);
}

// 2. Load the Target Class
require_once __DIR__ . '/../includes/class-sb-helpers.php';

echo "Starting Redirect Loop Vulnerability Test (Standalone)...\n";

$home_url = home_url();
$short_link_base = home_url('/go/');

echo "Mock Home URL: $home_url\n";
echo "Mock Short Link Base: $short_link_base\n\n";

// Test Cases
$test_urls = [
    'External (Safe)' => 'https://google.com',
    'Self (Vulnerable)' => $home_url,
    'Self Deep (Vulnerable)' => $home_url . '/some-page',
    'Short Link Base (Vulnerable)' => $short_link_base . 'loop',
    'Self Mixed Scheme' => str_replace('http://', 'https://', $home_url), // Test different scheme
];

$results = [];

foreach ($test_urls as $label => $url) {
    echo "Testing: [$label] $url ... ";
    $is_valid = SB_Helpers::validate_url($url);

    // Logic: validate_url returns true (valid) or false (invalid).
    $status = $is_valid ? "ALLOWED" : "BLOCKED";
    echo "$status\n";
    $results[$label] = $is_valid;
}

echo "\n--- Summary ---\n";
$failed_security_checks = 0;
foreach ($results as $label => $is_allowed) {
    if (strpos($label, 'Vulnerable') !== false && $is_allowed) {
        echo "[FAIL] '$label' should be BLOCKED but was ALLOWED.\n";
        $failed_security_checks++;
    } elseif (strpos($label, 'Vulnerable') !== false && !$is_allowed) {
        echo "[PASS] '$label' was correctly BLOCKED.\n";
    }
}

if ($failed_security_checks > 0) {
    echo "\n⚠️  Vulnerability Confirmed: $failed_security_checks checks failed.\n";
} else {
    echo "\n✅ System is Secure. No redirect loops allowed.\n";
}
