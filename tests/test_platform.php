<?php
/**
 * P2: Platform Detection Logic Verification Script
 * Run with: php tests/test_platform.php
 */

// Mock WordPress Environment (Minimal)
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Include Class
require_once __DIR__ . '/../wp-smart-bridge/includes/class-sb-helpers.php';

// Test Cases
$test_cases = [
    // 1. Standard Domains
    'https://www.coupang.com/vp/products/123' => 'coupang.com',
    'https://link.coupang.com/a/b/c' => 'coupang.com', // or 'Coupang' if mapped
    'https://www.aliexpress.com/item/123.html' => 'aliexpress.com',

    // 2. Subdomains & Specific Services
    'https://blog.naver.com/profile' => 'Naver Blog',
    'https://m.blog.naver.com/profile' => 'Naver Blog', // Mobile
    'https://cafe.naver.com/feco' => 'Naver Cafe',
    'https://smartstore.naver.com/store' => 'Naver Smart Store',
    'https://shopping.naver.com/home' => 'Naver Shopping',

    // 3. Global Platforms
    'https://youtu.be/xyz123' => 'YouTube',
    'https://www.youtube.com/watch?v=xyz' => 'YouTube',
    'https://instagram.com/p/123' => 'Instagram',
    'https://www.instagram.com/reel/123' => 'Instagram',

    // 4. Edge Cases
    'https://s.click.aliexpress.com/e/xyz' => 'AliExpress',
    'http://unknown-site.co.kr' => 'unknown-site.co.kr',
    'invalid-url' => 'Unknown',
];

echo "========================================\n";
echo "Starting Platform Detection Test (P2)\n";
echo "========================================\n";

$passed = 0;
$failed = 0;

foreach ($test_cases as $url => $expected) {
    $result = SB_Helpers::detect_platform($url);

    // Normalize for comparison (case-insensitive)
    $is_match = (strcasecmp($result, $expected) === 0);

    // Specifically check for "Naver Blog" vs "naver.com" distinction
    // If we expect "Naver Blog" but got "naver.com", that is a FAIL for P2.

    if ($is_match) {
        // echo "[PASS] $url -> $result\n";
        $passed++;
    } else {
        echo "[FAIL] $url\n";
        echo "       Expected: $expected\n";
        echo "       Got:      $result\n";
        $failed++;
    }
}

echo "========================================\n";
echo "Results: $passed Passed, $failed Failed\n";
echo "========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
