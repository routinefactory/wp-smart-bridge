<?php
/**
 * 정적 백업 생성 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Backup
{
    /**
     * 초기화
     */
    public static function init()
    {
        // AJAX 액션 등록
        add_action('wp_ajax_sb_generate_backup', [__CLASS__, 'ajax_generate_backup']);
    }

    /**
     * AJAX 백업 생성 핸들러 (Batch Processing)
     */
    public static function ajax_generate_backup()
    {
        // 권한 체크
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // Nonce 체크
        check_ajax_referer('sb_admin_nonce', 'nonce');

        // 파라미터 받기
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1000;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : uniqid('sb_backup_');
        $total_links = isset($_POST['total_links']) ? intval($_POST['total_links']) : 0;

        // 업로드 디렉토리 설정
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-backups';
        $backup_url = $upload_dir['baseurl'] . '/sb-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // 보호용 .htaccess (디렉토리 리스팅 방지)
        if (!file_exists($backup_dir . '/.htaccess')) {
            file_put_contents($backup_dir . '/.htaccess', 'Options -Indexes');
        }

        $zip_path = $backup_dir . '/' . $file_id . '.zip';
        $zip_url = $backup_url . '/' . $file_id . '.zip';

        // ZipArchive 초기화
        $zip = new ZipArchive();
        $zip_mode = ($offset === 0) ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::OPEN;

        if ($zip->open($zip_path, $zip_mode) !== true) {
            wp_send_json_error(['message' => 'Could not create ZIP file']);
        }

        // 시간 제한 해제 (대용량 처리용)
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        // 링크 데이터 조회 (Performance Optimized: JOIN)
        // 루프 내에서 get_post_meta를 호출하면 1000번의 추가 쿼리가 발생하므로(N+1 문제),
        // JOIN을 통해 한 번의 쿼리로 모든 데이터를 가져옵니다.
        global $wpdb;
        $posts_query = $wpdb->prepare(
            "SELECT p.post_name, pm.meta_value as target_url 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s 
             AND p.post_status = 'publish'
             AND pm.meta_key = 'sb_target_url'
             LIMIT %d OFFSET %d",
            SB_Post_Type::POST_TYPE,
            $limit,
            $offset
        );
        $links = $wpdb->get_results($posts_query);

        // 첫 배치가 실행될 때만 전체 카운트 계산
        if ($offset === 0 && $total_links === 0) {
            // 카운트도 JOIN 조건과 동일하게 정확히 계산
            $count_query = $wpdb->prepare(
                "SELECT COUNT(p.ID) 
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s 
                 AND p.post_status = 'publish'
                 AND pm.meta_key = 'sb_target_url'",
                SB_Post_Type::POST_TYPE
            );
            $total_links = (int) $wpdb->get_var($count_query);

            // 공통 에셋 파일 추가 (Smart Template)
            self::add_assets_to_zip($zip);
        }

        // 링크 루프
        $processed_count = 0;
        foreach ($links as $link) {
            $slug = $link->post_name;
            $target_url = $link->target_url; // JOIN으로 이미 가져옴

            if ($slug && $target_url) {
                // HTML 생성
                $html = self::generate_html_content($target_url);

                // ZIP에 추가: go/{slug}/index.html
                $zip->addFromString('go/' . $slug . '/index.html', $html);
                $processed_count++;
            }
        }

        $zip->close();

        // 완료 여부 확인
        $next_offset = $offset + $limit;
        $is_finished = ($next_offset >= $total_links);

        wp_send_json_success([
            'offset' => $next_offset,
            'processed' => $processed_count,
            'total' => $total_links,
            'file_id' => $file_id,
            'finished' => $is_finished,
            'download_url' => $is_finished ? $zip_url : null
        ]);
    }

    /**
     * 공통 에셋 추가 (Loader JS/CSS)
     */
    private static function add_assets_to_zip($zip)
    {
        // 1. Loader.js
        $loader_js = "(function() {
    var script = document.currentScript;
    var targetUrl = script.getAttribute('data-target');
    
    // 즉시 리다이렉트
    if (targetUrl) {
        window.location.replace(targetUrl);
    }
})();";
        $zip->addFromString('sb-assets/loader.js', $loader_js);

        // 2. Style.css (Optional, 현재는 비워둠)
        $zip->addFromString('sb-assets/style.css', '/* Custom Styles Here */');

        // 3. 404.html (루트용)
        $zip->addFromString('index.html', '<h1>Direct Access Forbidden</h1>');
    }

    /**
     * HTML 컨텐츠 생성 (Smart Template 방식)
     */
    private static function generate_html_content($target_url)
    {
        // 보안 및 에러 방지를 위해 URL 이스케이프
        $safe_url = esc_url($target_url);

        // 초경량 템플릿: Loader.js 위임 + Noscript Fallback
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <script src="../../sb-assets/loader.js" data-target="' . $safe_url . '"></script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=' . $safe_url . '">
    </noscript>
</head>
<body>
    <p>Redirecting to <a href="' . $safe_url . '">' . $safe_url . '</a>...</p>
</body>
</html>';
    }
}
