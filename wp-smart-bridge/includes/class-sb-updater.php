<?php
/**
 * GitHub 기반 자동 업데이터 클래스
 * 
 * 워드프레스 기본 업데이트 시스템과 통합되어
 * 사용자가 별도 플러그인 없이 자동 업데이트 가능
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Updater
{
    /**
     * GitHub 저장소 정보
     */
    private $github_username = 'routinefactory';
    private $github_repo = 'wp-smart-bridge';

    /**
     * 플러그인 정보
     */
    private $plugin_slug;
    private $plugin_file;
    private $current_version;

    /**
     * 캐시 키
     */
    private $cache_key = 'sb_github_update_check';
    private $cache_expiry = 43200; // 12시간

    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;

    /**
     * 싱글톤 패턴
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 생성자
     */
    private function __construct()
    {
        $this->plugin_slug = 'wp-smart-bridge/wp-smart-bridge.php';
        $this->plugin_file = SB_PLUGIN_FILE;
        $this->current_version = SB_VERSION;

        // 업데이트 체크 필터
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);

        // 플러그인 정보 팝업
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        // 업데이트 후 캐시 삭제
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
    }

    /**
     * GitHub API에서 최신 릴리스 정보 가져오기
     */
    private function get_github_release()
    {
        // 캐시 확인
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // GitHub API 호출
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body);

        if (empty($release) || !isset($release->tag_name)) {
            return false;
        }

        // 버전 정보 정리
        $version = ltrim($release->tag_name, 'v');

        // ZIP 다운로드 URL 찾기
        $download_url = '';
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // asset이 없으면 zipball 사용
        if (empty($download_url)) {
            $download_url = $release->zipball_url;
        }

        $data = (object) [
            'version' => $version,
            'download_url' => $download_url,
            'changelog' => $release->body ?? '',
            'published_at' => $release->published_at ?? '',
            'html_url' => $release->html_url ?? '',
        ];

        // 캐시 저장
        set_transient($this->cache_key, $data, $this->cache_expiry);

        return $data;
    }

    /**
     * 업데이트 체크
     */
    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if ($release === false) {
            return $transient;
        }

        // 버전 비교
        if (version_compare($this->current_version, $release->version, '<')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $release->version,
                'package' => $release->download_url,
                'url' => $release->html_url,
                'icons' => [
                    '1x' => SB_PLUGIN_URL . 'admin/images/icon-128x128.png',
                    '2x' => SB_PLUGIN_URL . 'admin/images/icon-256x256.png',
                ],
                'banners' => [
                    'low' => SB_PLUGIN_URL . 'admin/images/banner-772x250.png',
                    'high' => SB_PLUGIN_URL . 'admin/images/banner-1544x500.png',
                ],
            ];
        } else {
            // 업데이트 없음
            $transient->no_update[$this->plugin_slug] = (object) [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $this->current_version,
            ];
        }

        return $transient;
    }

    /**
     * 플러그인 정보 팝업 (세부 정보 보기)
     */
    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_github_release();

        if ($release === false) {
            return $result;
        }

        return (object) [
            'name' => 'WP Smart Bridge',
            'slug' => dirname($this->plugin_slug),
            'version' => $release->version,
            'author' => '<a href="https://antigravity.kr">Antigravity</a>',
            'homepage' => 'https://github.com/' . $this->github_username . '/' . $this->github_repo,
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'download_link' => $release->download_url,
            'trunk' => $release->download_url,
            'last_updated' => $release->published_at,
            'sections' => [
                'description' => '제휴 마케팅용 단축 링크 자동화 플러그인 - HMAC-SHA256 보안 인증, SaaS급 분석 기능 포함',
                'changelog' => $this->format_changelog($release->changelog),
                'installation' => $this->get_installation_text(),
            ],
            'banners' => [
                'low' => SB_PLUGIN_URL . 'admin/images/banner-772x250.png',
                'high' => SB_PLUGIN_URL . 'admin/images/banner-1544x500.png',
            ],
        ];
    }

    /**
     * 체인지로그 포맷팅
     */
    private function format_changelog($markdown)
    {
        if (empty($markdown)) {
            return '<p>변경 사항 정보가 없습니다.</p>';
        }

        // 간단한 마크다운 → HTML 변환
        $html = nl2br(esc_html($markdown));
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);

        return $html;
    }

    /**
     * 설치 안내 텍스트
     */
    private function get_installation_text()
    {
        return '
            <ol>
                <li>플러그인 ZIP 파일을 다운로드합니다.</li>
                <li>워드프레스 관리자 → 플러그인 → 새로 추가 → 플러그인 업로드</li>
                <li>ZIP 파일을 선택하고 설치합니다.</li>
                <li>플러그인을 활성화합니다.</li>
                <li>설정 → 퍼마링크에서 "변경사항 저장"을 클릭합니다.</li>
            </ol>
        ';
    }

    /**
     * 캐시 삭제
     */
    public function clear_cache($upgrader, $options)
    {
        if (
            isset($options['action']) &&
            $options['action'] === 'update' &&
            isset($options['type']) &&
            $options['type'] === 'plugin'
        ) {
            delete_transient($this->cache_key);
        }
    }

    /**
     * 수동 업데이트 체크 (관리자용)
     */
    public static function force_check()
    {
        $instance = self::get_instance();
        delete_transient($instance->cache_key);
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}
