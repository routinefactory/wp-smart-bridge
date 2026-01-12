<?php
/**
 * 자동 업데이트 체크 및 관리 클래스
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
    public static function check_github_release($only_cache = false)
    {
        $cache_key = 'sb_github_release_check';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // v3.0.0 Performance Fix: Non-blocking dashboard
        if ($only_cache) {
            return null;
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
            'release_notes' => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? '',
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

    /**
     * 새 버전이 있는지 확인
     *
     * @return array|false 새 버전 정보 또는 false
     */
    public static function check_new_version()
    {
        $current_version = SB_VERSION;
        $release = self::check_github_release();

        if (!$release) {
            return false;
        }

        // 버전 비교 (새 버전이 있는지 확인)
        if (version_compare($release['version'], $current_version, '>')) {
            return $release;
        }

        return false;
    }

    /**
     * 업데이트 알림 표시 여부 확인
     *
     * @return bool
     */
    public static function should_show_update_notice()
    {
        $new_version = self::check_new_version();
        
        if (!$new_version) {
            return false;
        }

        // 이미 알림을 무시한 경우 확인
        $dismissed_version = get_option('sb_update_dismissed_version', '');
        
        if ($dismissed_version === $new_version['version']) {
            return false;
        }

        return true;
    }

    /**
     * 업데이트 알림 무시
     *
     * @param string $version 무시할 버전
     * @return bool
     */
    public static function dismiss_update_notice($version)
    {
        return update_option('sb_update_dismissed_version', $version);
    }

    /**
     * 업데이트 로그 기록
     *
     * @param string $action 동작 (check, download, install, success, fail)
     * @param string $version 버전
     * @param string $message 메시지
     * @return bool
     */
    public static function log_update($action, $version, $message = '')
    {
        $logs = get_option('sb_update_logs', []);
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'version' => $version,
            'message' => $message,
            'user_id' => get_current_user_id(),
        ];

        // 최대 100개 로그 유지
        array_unshift($logs, $log_entry);
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
        }

        return update_option('sb_update_logs', $logs);
    }

    /**
     * 업데이트 로그 조회
     *
     * @param int $limit 조회할 로그 수
     * @return array
     */
    public static function get_update_logs($limit = 20)
    {
        $logs = get_option('sb_update_logs', []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * 업데이트 로그 삭제
     *
     * @return bool
     */
    public static function clear_update_logs()
    {
        return update_option('sb_update_logs', []);
    }

    /**
     * 자동 업데이트 다운로드 (선택적 기능)
     *
     * @param string $download_url 다운로드 URL
     * @return array ['success' => bool, 'message' => string, 'file_path' => string]
     */
    public static function download_update($download_url)
    {
        if (empty($download_url)) {
            return [
                'success' => false,
                'message' => '다운로드 URL이 없습니다.'
            ];
        }

        // 업로드 디렉토리 확인
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/sb-temp-updates';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // 보안: .htaccess 생성
        $htaccess_path = $temp_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n");
        }

        // 파일명 생성
        $filename = 'wp-smart-bridge-update-' . date('YmdHis') . '.zip';
        $file_path = $temp_dir . '/' . $filename;

        // 파일 다운로드
        $response = wp_remote_get($download_url, [
            'timeout' => 300,
            'stream' => true,
            'filename' => $file_path,
        ]);

        if (is_wp_error($response)) {
            self::log_update('download', '', '다운로드 실패: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => '다운로드 실패: ' . $response->get_error_message()
            ];
        }

        // 파일 존재 확인
        if (!file_exists($file_path)) {
            self::log_update('download', '', '다운로드된 파일을 찾을 수 없습니다.');
            return [
                'success' => false,
                'message' => '다운로드된 파일을 찾을 수 없습니다.'
            ];
        }

        // 파일 크기 확인 (최소 1KB)
        if (filesize($file_path) < 1024) {
            @unlink($file_path);
            self::log_update('download', '', '다운로드된 파일이 너무 작습니다.');
            return [
                'success' => false,
                'message' => '다운로드된 파일이 너무 작습니다.'
            ];
        }

        // ZIP 파일 검증
        if (!class_exists('ZipArchive')) {
            @unlink($file_path);
            return [
                'success' => false,
                'message' => 'ZipArchive PHP 확장이 설치되지 않았습니다.'
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            @unlink($file_path);
            self::log_update('download', '', 'ZIP 파일이 유효하지 않습니다.');
            return [
                'success' => false,
                'message' => 'ZIP 파일이 유효하지 않습니다.'
            ];
        }
        $zip->close();

        self::log_update('download', '', '업데이트 파일 다운로드 성공: ' . $filename);

        return [
            'success' => true,
            'message' => '다운로드가 완료되었습니다.',
            'file_path' => $file_path,
            'file_name' => $filename
        ];
    }

    /**
     * 임시 업데이트 파일 정리
     *
     * @param int $days_old 보관할 일수
     * @return int 삭제된 파일 수
     */
    public static function cleanup_temp_files($days_old = 7)
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/sb-temp-updates';

        if (!is_dir($temp_dir)) {
            return 0;
        }

        $deleted_count = 0;
        $cutoff_time = time() - ($days_old * DAY_IN_SECONDS);

        foreach (glob($temp_dir . '/*.zip') as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (@unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * 자동 업데이트 체크 (Cron 용)
     *
     * @return void
     */
    public static function auto_check_updates()
    {
        $release = self::check_github_release();
        
        if ($release) {
            $new_version = self::check_new_version();
            
            if ($new_version) {
                self::log_update('check', $new_version['version'], '새 버전 발견: ' . $new_version['version']);
            }
        }
    }

    /**
     * 업데이트 상태 정보 반환
     *
     * @return array
     */
    public static function get_update_status()
    {
        $current_version = SB_VERSION;
        $new_version = self::check_new_version();
        $should_show_notice = self::should_show_update_notice();
        $logs = self::get_update_logs(5);

        return [
            'current_version' => $current_version,
            'new_version' => $new_version,
            'has_update' => $new_version !== false,
            'should_show_notice' => $should_show_notice,
            'recent_logs' => $logs,
        ];
    }
}
