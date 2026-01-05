<?php
/**
 * Test Script: Final Stress Test for URL Validation
 * Covers extreme edge cases to ensure absolute security.
 */

// 1. Mock WordPress Functions
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

// 2. Load Target Class
require_once __DIR__ . '/../includes/class-sb-helpers.php';

echo "💀 Starting Extreme Edge Case Stress Test...\n";
echo "Base URL: " . home_url('/go/') . "\n\n";

$tests = [
    // Standard Blocking
    'Standard Loop' => [
        'url' => 'http://localhost/site/go/loop',
        'expect' => false // Should be BLOCKED (d/t previous logic)
    ],

    // Obscure Formats
    'Protocol Relative' => [
        'url' => '//localhost/site/go/loop',
        'expect' => false // Should be BLOCKED
    ],
    'No Scheme (Invalid)' => [
        'url' => 'localhost/site/go/loop',
        'expect' => false // Should be INVALID
    ],
    'Mixed Case' => [
        'url' => 'HTTP://LOCALHOST/site/go/LOOP',
        'expect' => false // Should be BLOCKED
    ],

    // Query & Fragments
    'With Query' => [
        'url' => 'http://localhost/site/go/loop?source=test',
        'expect' => false // Should be BLOCKED (base match)
    ],
    'With Fragment' => [
        'url' => 'http://localhost/site/go/loop#section',
        'expect' => false // Should be BLOCKED
    ],

    // Encoding Attacks
    'Encoded Path' => [
        'url' => 'http://localhost/site/go/%6C%6F%6F%70', // "loop"
        'expect' => false // Should be BLOCKED (if raw string match works on decoded, or if we decode first)
        // Note: Our current logic uses strpos on the input string.
        // If the attacker uses encoded string, does home_url() match?
        // Let's see if the fix handles this. If not, we might need a stronger check.
    ],

    // Safe URLs
    'External' => [
        'url' => 'https://google.com',
        'expect' => true
    ],
    'Similar Domain' => [
        'url' => 'http://localhost/site/golang', // Starts with 'go', but not '/go/' ? 
        // Wait, base is '/go/'. 
        // 'golang' does NOT contain '/go/'. 
        // But if base is '/go/', then '/golang' might not match depending on slash.
        // home_url('/go/') usually returns '.../go/'.
        'expect' => true
    ]
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $data) {
    echo "Testing [$name]: {$data['url']} ... ";
    $result = SB_Helpers::validate_url($data['url']);

    // Our validate_url returns TRUE for valid/allowed, FALSE for invalid/blocked.
    // If expect is false, we want $result to be false.

    if ($result === $data['expect']) {
        echo "✅ PASS\n";
        $passed++;
    } else {
        echo "❌ FAIL (Expected " . ($data['expect'] ? 'ALLOWED' : 'BLOCKED') . ")\n";
        $failed++;
    }
}

echo "\n--- Final Verdict ---\n";
if ($failed === 0) {
    echo "ALL TESTS PASSED. Logic is robust.\n";
} else {
    echo "$failed TESTS FAILED. Security gap exists.\n";
}
