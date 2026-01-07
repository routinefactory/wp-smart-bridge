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
        add_action('wp_ajax_sb_generate_backup', array(__CLASS__, 'ajax_generate_backup'));
    }

    /**
     * AJAX 백업 생성 핸들러
     */
    public static function ajax_generate_backup()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        check_ajax_referer('sb_admin_nonce', 'nonce');

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1000;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : uniqid('sb_backup_');
        $total_links = isset($_POST['total_links']) ? intval($_POST['total_links']) : 0;

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sb-backups';
        $backup_url = $upload_dir['baseurl'] . '/sb-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        if (!file_exists($backup_dir . '/.htaccess')) {
            file_put_contents($backup_dir . '/.htaccess', 'Options -Indexes');
        }

        $zip_path = $backup_dir . '/' . $file_id . '.zip';
        $zip_url = $backup_url . '/' . $file_id . '.zip';

        if (!class_exists('ZipArchive')) {
            wp_send_json_error(array('message' => 'ZipArchive not available'));
        }

        $zip = new ZipArchive();
        $zip_mode = ($offset === 0) ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::CREATE;

        if ($zip->open($zip_path, $zip_mode) !== true) {
            wp_send_json_error(array('message' => 'Could not create ZIP file'));
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        global $wpdb;
        $post_type = defined('SB_Post_Type::POST_TYPE') ? SB_Post_Type::POST_TYPE : 'sb_link';

        $posts_query = $wpdb->prepare(
            "SELECT p.post_name, pm.meta_value as target_url 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s 
             AND p.post_status = 'publish'
             AND pm.meta_key = 'sb_target_url'
             LIMIT %d OFFSET %d",
            $post_type,
            $limit,
            $offset
        );
        $links = $wpdb->get_results($posts_query);

        if ($offset === 0 && $total_links === 0) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(p.ID) 
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s 
                 AND p.post_status = 'publish'
                 AND pm.meta_key = 'sb_target_url'",
                $post_type
            );
            $total_links = (int) $wpdb->get_var($count_query);

            self::add_assets_to_zip($zip);
        }

        $processed_count = 0;
        foreach ($links as $link) {
            $slug = $link->post_name;
            $target_url = $link->target_url;

            if ($slug && $target_url) {
                $html = self::generate_html_content($target_url);
                $zip->addFromString('go/' . $slug . '/index.html', $html);
                $processed_count++;
            }
        }

        $zip->close();

        $next_offset = $offset + $limit;
        $is_finished = ($next_offset >= $total_links);

        wp_send_json_success(array(
            'offset' => $next_offset,
            'processed' => $processed_count,
            'total' => $total_links,
            'file_id' => $file_id,
            'finished' => $is_finished,
            'download_url' => $is_finished ? $zip_url : null
        ));
    }

    /**
     * 공통 에셋 추가
     */
    private static function add_assets_to_zip($zip)
    {
        $loader_js = '(function(){var s=document.currentScript;var t=s.getAttribute("data-target");if(t){window.location.replace(t);}})();';
        $zip->addFromString('sb-assets/loader.js', $loader_js);
        $zip->addFromString('sb-assets/style.css', '/* Custom Styles */');
        $zip->addFromString('index.html', '<h1>Direct Access Forbidden</h1>');
    }

    /**
     * HTML 생성
     */
    private static function generate_html_content($target_url)
    {
        $safe_url = esc_url($target_url);
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1.0">';
        $html .= '<meta name="robots" content="noindex,nofollow">';
        $html .= '<script src="../../sb-assets/loader.js" data-target="' . $safe_url . '"></script>';
        $html .= '<noscript><meta http-equiv="refresh" content="0;url=' . $safe_url . '"></noscript>';
        $html .= '</head><body>';
        $html .= '<p>Redirecting...</p>';
        $html .= '</body></html>';
        return $html;
    }
}
