<?php
/**
 * 리다이렉션 페이지 (커스텀 템플릿 지원)
 * 
 * @package WP_Smart_Bridge
 * @since 2.6.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// 디버그 정보 (필요시 주석 해제)
// error_log('[SB Redirect] Delay: ' . $delay . ', Target: ' . $target_url);

// 커스텀 템플릿 로드 (없으면 기본값)
$template = get_option('sb_redirect_template', SB_Helpers::get_default_redirect_template());

// 카운트다운 스크립트 생성
$countdown_script = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var delaySeconds = ' . floatval($delay) . ';
        var targetUrl = ' . json_encode($target_url) . ';
        var countdown = document.getElementById("countdown");

        // 1. Precise Redirect Timer
        setTimeout(function() {
            window.location.replace(targetUrl);
        }, delaySeconds * 1000);

        // 2. Visual Countdown (Integer based)
        var remaining = Math.ceil(delaySeconds);
        
        function updateVisual() {
            remaining--;
            if (remaining >= 0 && countdown) {
                countdown.textContent = remaining;
                if (remaining > 0) {
                    setTimeout(updateVisual, 1000);
                }
            }
        }

        // Start visual countdown if delay is sufficient
        if (remaining > 0) {
            if (countdown) countdown.textContent = remaining;
            setTimeout(updateVisual, 1000);
        }
    });
</script>';

// Placeholder 치환
$replacements = [
    '{{DELAY_SECONDS}}' => number_format(floatval($delay), 1),
    '{{TARGET_URL}}' => esc_url($target_url),
    '{{COUNTDOWN_SCRIPT}}' => $countdown_script,
    '{{COUNTDOWN_ID}}' => 'countdown',
    // New i18n placeholders
    '{{MSG_TITLE}}' => __('페이지로 이동 중입니다...', 'sb'),
    '{{MSG_SUB}}' => __('보안 서버를 통해 안전하게 연결하고 있습니다.', 'sb') . '<br>' . __('잠시만 기다려 주세요.', 'sb'),
    '{{BTN_TEXT}}' => __('즉시 연결하기', 'sb'),
    '{{FOOTER_TEXT}}' => __('Verified Secure Connection', 'sb'),
];

$output = str_replace(array_keys($replacements), array_values($replacements), $template);

/**
 * v3.0.4 CRITICAL FIX: Quote Escaping Issue
 * 
 * PROBLEM: Templates stored in database may have over-escaped quotes
 * appearing as \&quot; or &quot; instead of proper " quotes.
 * This breaks all HTML attributes: class="\&quot;mesh\&quot;" instead of class="mesh"
 * 
 * ROOT CAUSE: Possible double-escaping during save operation or 
 * WordPress magic quotes / addslashes being applied multiple times.
 * 
 * SOLUTION: Decode HTML entities and remove excess slashes before output.
 * Order matters: stripslashes first, then htmlspecialchars_decode.
 * 
 * @see class-sb-admin-ajax.php Line 202 for template save logic
 */
$output = stripslashes($output);
$output = htmlspecialchars_decode($output, ENT_QUOTES);

// 출력
echo $output;
exit;