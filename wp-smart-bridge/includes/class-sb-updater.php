<?php
/**
 * 수동 업데이트 체크 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Updater
{
    /**
     * GitHub 릴리스 체크 (수동 업데이트 안내용)
     * 
     * @return array|null ['version' => '2.7.0', 'download_url' => '...'] 또는 null
     */
    public static function check_github_release()
    {
        $cache_key = 'sb_github_release_check';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $api_url = 'https://api.github.com/repos/routinefactory/wp-smart-bridge/releases/latest';

        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ]
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['tag_name'])) {
            return null;
        }

        $version = ltrim($data['tag_name'], 'v');
        $download_url = '';

        // assets에서 ZIP 파일 찾기
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['name']) && strpos($asset['name'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        $result = [
            'version' => $version,
            'download_url' => $download_url,
            'release_url' => $data['html_url'] ?? '',
        ];

        // 12시간 캐시
        set_transient($cache_key, $result, 43200);

        return $result;
    }

    /**
     * 캐시 무시하고 즉시 GitHub 릴리스 체크 (강제 새로고침)
     * 
     * @return array|null
     */
    public static function force_check_release()
    {
        // 캐시 삭제
        delete_transient('sb_github_release_check');

        // 즉시 체크
        return self::check_github_release();
    }
}
