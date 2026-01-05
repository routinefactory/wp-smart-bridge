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
     * 
     * ✅ 안전성 보장:
     * - dbDelta()는 WordPress 표준 함수로 안전하게 테이블을 생성/수정합니다
     * - 기존 테이블이 있으면 스킵하고 데이터를 보존합니다
     * - 새 컬럼이 추가되면 ALTER TABLE로 안전하게 추가합니다
     * - 기존 데이터는 절대 삭제되지 않습니다
     * 
     * 📌 호출 시점:
     * 1. 플러그인 최초 설치 시 (activate() 훅)
     * 2. 플러그인 재활성화 시 (activate() 훅)
     * 3. 플러그인 업데이트 시 (maybe_upgrade_database() 자동 실행)
     * 
     * 🔄 스키마 변경 가이드:
     * 향후 테이블 구조를 변경할 때는 아래 SQL만 수정하면 됩니다.
     * - 새 컬럼 추가: SQL에 컬럼 정의 추가 → 자동 ALTER TABLE
     * - 인덱스 추가: INDEX 정의 추가 → 자동 CREATE INDEX
     * - 기존 데이터: 자동 보존됨
     * 
     * @since 2.5.0
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        /**
         * 📊 분석 로그 테이블 (wp_sb_analytics_logs)
         * 
         * 용도: 모든 클릭 이벤트의 상세 로그 저장
         * 특징: 시간대별, 플랫폼별, UV 분석의 기반 데이터
         * 
         * ⚠️ 주의: 이 테이블은 사용자의 마케팅 성과 분석의 핵심입니다.
         * 절대로 삭제하거나 TRUNCATE하지 마세요!
         */
        $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_posts.ID 참조',
            visitor_ip VARCHAR(64) NOT NULL COMMENT 'IP 주소 (SHA256 해싱)',
            platform VARCHAR(50) DEFAULT 'Etc' COMMENT '플랫폼 태그',
            device VARCHAR(20) DEFAULT 'Unknown' COMMENT '디바이스 (Desktop/Mobile/Tablet)',
            os VARCHAR(30) DEFAULT 'Unknown' COMMENT '운영체제',
            browser VARCHAR(30) DEFAULT 'Unknown' COMMENT '브라우저',
            referer VARCHAR(500) DEFAULT NULL COMMENT '유입 경로',
            user_agent VARCHAR(500) DEFAULT NULL COMMENT '브라우저 정보',
            visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '클릭 시간',
            PRIMARY KEY (id),
            INDEX idx_link_id (link_id),
            INDEX idx_visited_at (visited_at),
            INDEX idx_platform (platform),
            INDEX idx_device (device),
            INDEX idx_os (os),
            INDEX idx_browser (browser),
            INDEX idx_visitor_ip (visitor_ip),
            INDEX idx_link_visited (link_id, visited_at)
        ) $charset_collate;";

        /**
         * 🔑 API 키 테이블 (wp_sb_api_keys)
         * 
         * 용도: EXE 클라이언트 인증용 API 키 저장
         * 특징: HMAC 서명 검증의 Secret Key 보관
         * 
         * ⚠️ 주의: API 키가 삭제되면 외부 EXE 클라이언트를 재설정해야 합니다.
         * 사용자가 명시적으로 삭제하지 않는 한 유지되어야 합니다!
         */
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

        /**
         * 🚀 테이블 생성/업데이트 실행
         * 
         * dbDelta()는 다음과 같이 동작합니다:
         * - 테이블 없음 → CREATE TABLE 실행
         * - 테이블 있음 + 새 컬럼 → ALTER TABLE ADD COLUMN 실행
         * - 테이블 있음 + 컬럼 동일 → 아무것도 안 함 (데이터 보존)
         * 
         * 💡 Tip: dbDelta()는 SQL 포맷에 매우 민감합니다.
         * - PRIMARY KEY와 괄호 사이에 공백 필요
         * - 각 라인은 정확히 한 개의 컬럼 정의만 포함
         * - CREATE TABLE 다음에 반드시 괄호로 열기
         */
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
    public static function log_click($link_id, $visitor_ip, $platform, $referer = null, $user_agent = null, $parsed_ua = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_analytics_logs';

        $result = $wpdb->insert(
            $table,
            [
                'link_id' => $link_id,
                'visitor_ip' => $visitor_ip,
                'platform' => $platform,
                'device' => $parsed_ua['device'] ?? 'Unknown',
                'os' => $parsed_ua['os'] ?? 'Unknown',
                'browser' => $parsed_ua['browser'] ?? 'Unknown',
                'referer' => $referer ? substr($referer, 0, 500) : null,
                'user_agent' => $user_agent ? substr($user_agent, 0, 500) : null,
                'visited_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
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
    /**
     * API 키 소유자 ID 조회
     * 
     * @param int $id API 키 ID
     * @return int|null 소유자 ID 또는 null
     */
    public static function get_api_key_owner($id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $id
        ));
    }
}
