<?php
/**
 * 데이터베이스 테이블 관리 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Database
{

    /**
     * 커스텀 테이블 생성
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 분석 로그 테이블
        $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_posts.ID 참조',
            visitor_ip VARCHAR(64) NOT NULL COMMENT 'IP 주소 (SHA256 해싱)',
            platform VARCHAR(50) DEFAULT 'Etc' COMMENT '플랫폼 태그',
            referer VARCHAR(500) DEFAULT NULL COMMENT '유입 경로',
            user_agent VARCHAR(500) DEFAULT NULL COMMENT '브라우저 정보',
            visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '클릭 시간',
            PRIMARY KEY (id),
            INDEX idx_link_id (link_id),
            INDEX idx_visited_at (visited_at),
            INDEX idx_platform (platform),
            INDEX idx_visitor_ip (visitor_ip)
        ) $charset_collate;";

        // API 키 테이블
        $api_keys_table = $wpdb->prefix . 'sb_api_keys';
        $sql_api_keys = "CREATE TABLE $api_keys_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_users.ID',
            api_key VARCHAR(100) NOT NULL COMMENT '공개 키 (sb_live_xxx)',
            secret_key VARCHAR(100) NOT NULL COMMENT '비밀 키 (서명 생성용)',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_api_key (api_key),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_analytics);
        dbDelta($sql_api_keys);
    }

    /**
     * 테이블 삭제 (uninstall 시 사용)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
        $api_keys_table = $wpdb->prefix . 'sb_api_keys';

        $wpdb->query("DROP TABLE IF EXISTS $analytics_table");
        $wpdb->query("DROP TABLE IF EXISTS $api_keys_table");
    }

    /**
     * 클릭 로그 저장
     * 
     * @param int $link_id 링크 포스트 ID
     * @param string $visitor_ip 방문자 IP (해싱됨)
     * @param string $platform 플랫폼 태그
     * @param string $referer 리퍼러
     * @param string $user_agent User-Agent
     * @return int|false 삽입된 ID 또는 false
     */
    public static function log_click($link_id, $visitor_ip, $platform, $referer = null, $user_agent = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $result = $wpdb->insert(
            $table,
            [
                'link_id' => $link_id,
                'visitor_ip' => $visitor_ip,
                'platform' => $platform,
                'referer' => $referer ? substr($referer, 0, 500) : null,
                'user_agent' => $user_agent ? substr($user_agent, 0, 500) : null,
                'visited_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * API 키 저장
     * 
     * @param int $user_id 사용자 ID
     * @param string $api_key 공개 키
     * @param string $secret_key 비밀 키
     * @return int|false 삽입된 ID 또는 false
     */
    public static function save_api_key($user_id, $api_key, $secret_key)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * API 키로 Secret Key 조회
     * 
     * @param string $api_key 공개 키
     * @return string|null Secret Key 또는 null
     */
    public static function get_secret_key($api_key)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT secret_key FROM $table WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
    }

    /**
     * API 키 마지막 사용 시간 업데이트
     * 
     * @param string $api_key 공개 키
     */
    public static function update_api_key_last_used($api_key)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        $wpdb->update(
            $table,
            ['last_used_at' => current_time('mysql')],
            ['api_key' => $api_key],
            ['%s'],
            ['%s']
        );
    }

    /**
     * 사용자의 API 키 목록 조회
     * 
     * @param int $user_id 사용자 ID
     * @return array API 키 목록
     */
    public static function get_user_api_keys($user_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * API 키 상태 변경
     * 
     * @param int $id API 키 ID
     * @param string $status 상태 (active/inactive)
     * @return bool 성공 여부
     */
    public static function update_api_key_status($id, $status)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * API 키 삭제
     * 
     * @param int $id API 키 ID
     * @return bool 성공 여부
     */
    public static function delete_api_key($id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->delete($table, ['id' => $id], ['%d']) !== false;
    }
}
