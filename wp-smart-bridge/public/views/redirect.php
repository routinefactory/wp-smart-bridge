<?php
/**
 * 리다이렉션 페이지 (커스텀 템플릿 지원)
 * 
 * @package WP_Smart_Bridge
 * @since 2.6.5
 */

// 디버그 정보 (필요시 주석 해제)
// error_log('[SB Redirect] Delay: ' . $delay . ', Target: ' . $target_url);

// 커스텀 템플릿 로드 (없으면 기본값)
$template = get_option('sb_redirect_template', SB_Helpers::get_default_redirect_template());

// 카운트다운 스크립트 생성
$countdown_script = "
<script>
    (function () {
        var seconds = " . intval($delay) . ";
        var countdown = document.getElementById('countdown');
        var targetUrl = " . json_encode($target_url) . ";

        function updateCountdown() {
            seconds--;
            if (seconds <= 0) {
                window.location.href = targetUrl;
            } else {
                countdown.textContent = seconds;
                setTimeout(updateCountdown, 1000);
            }
        }

        if (seconds > 0) {
            setTimeout(updateCountdown, 1000);
        } else {
            window.location.href = targetUrl;
        }
    })();
</script>";

// Placeholder 치환
$replacements = [
    '{{DELAY_SECONDS}}' => intval($delay),
    '{{TARGET_URL}}' => esc_url($target_url),
    '{{COUNTDOWN_SCRIPT}}' => $countdown_script,
    '{{COUNTDOWN_ID}}' => 'countdown',
];

$output = str_replace(array_keys($replacements), array_values($replacements), $template);

// 출력
echo $output;
exit;