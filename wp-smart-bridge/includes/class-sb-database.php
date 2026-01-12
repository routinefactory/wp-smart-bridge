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
     * 데이터베이스 스키마 버전
     *
     * 스키마 변경 시 이 버전을 증가시키고 마이그레이션 함수를 추가하세요.
     *
     * @since 4.2.0
     */
    const DB_VERSION = '4.3.0';

    /**
     * 메타 데이터 포맷 버전
     *
     * stats_today_pv, stats_today_uv, stats_total_uv 메타 데이터의 형식 버전
     *
     * @since 4.2.0
     */
    const META_FORMAT_VERSION = '1.0';

    /**
     * API 키 상태 상수
     *
     * @since 4.2.0
     */
    const API_STATUS_ACTIVE = 'active';
    const API_STATUS_INACTIVE = 'inactive';
    const API_STATUS_REVOKED = 'revoked';

    /**
     * 유효한 API 키 상태 목록
     *
     * @since 4.2.0
     */
    const VALID_API_STATUSES = [
        self::API_STATUS_ACTIVE,
        self::API_STATUS_INACTIVE,
        self::API_STATUS_REVOKED
    ];

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
            PRIMARY KEY  (id),
            KEY idx_platform (platform),
            KEY idx_device (device),
            KEY idx_os (os),
            KEY idx_browser (browser),
            KEY idx_visitor_ip (visitor_ip),
            KEY idx_link_visited (link_id, visited_at),
            KEY idx_ip_date (visitor_ip, visited_at)
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
            status VARCHAR(20) DEFAULT 'active' COMMENT 'API 키 상태 (active/inactive/revoked)',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL COMMENT 'API 키 만료일 (NULL = 무기한)',
            PRIMARY KEY  (id),
            UNIQUE KEY idx_api_key (api_key),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";

        /**
         * 📂 링크 그룹 테이블 (wp_sb_link_groups)
         * 
         * 용도: 링크를 캠페인/폴더별로 분류
         */
        $groups_table = $wpdb->prefix . 'sb_link_groups';
        $sql_groups = "CREATE TABLE $groups_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL COMMENT 'group name',
            color VARCHAR(20) DEFAULT '#667eea' COMMENT '그룹 색상',
            description TEXT NULL COMMENT '설명',
            user_id BIGINT(20) UNSIGNED NOT NULL COMMENT '생성자 ID',
            sort_order INT(11) DEFAULT 0 COMMENT '정렬 순서',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;";

        /**
         * 📈 일별 요약 통계 테이블 (wp_sb_daily_stats)
         * 
         * 용도: 대시보드 성능 최적화를 위한 일별 집계 데이터
         * 특징: O(N) 쿼리를 O(1)로 변경
         */
        $stats_table = $wpdb->prefix . 'sb_daily_stats';
        $sql_stats = "CREATE TABLE $stats_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            stats_date DATE NOT NULL,
            total_clicks INT UNSIGNED DEFAULT 0,
            unique_visitors INT UNSIGNED DEFAULT 0,
            platform_share TEXT COMMENT 'JSON Encoded',
            referers TEXT COMMENT 'JSON Encoded',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_stats_date (stats_date)
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
        dbDelta($sql_groups);
        dbDelta($sql_stats);

        // 데이터베이스 버전 저장 및 마이그레이션 실행
        self::update_db_version();
    }

    /**
     * 데이터베이스 버전 업데이트 및 마이그레이션 실행
     *
     * @since 4.2.0
     */
    private static function update_db_version()
    {
        $current_version = get_option('sb_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            // 마이그레이션 실행
            self::run_migrations($current_version);

            // 버전 업데이트
            update_option('sb_db_version', self::DB_VERSION);
        }
    }

    /**
     * 데이터베이스 마이그레이션 실행
     *
     * @param string $from_version 이전 버전
     * @since 4.2.0
     */
    private static function run_migrations($from_version)
    {
        global $wpdb;

        // 4.2.0 이전 버전에서 마이그레이션
        if (version_compare($from_version, '4.2.0', '<')) {
            // ENUM 타입을 VARCHAR로 변경 (dbDelta가 자동 처리)
            // 단일 인덱스 제거 (dbDelta가 자동 처리)
             
            // 기존 ENUM 값 검증 및 변환
            $api_keys_table = $wpdb->prefix . 'sb_api_keys';
            $wpdb->query("
                UPDATE $api_keys_table
                SET status = 'active'
                WHERE status NOT IN ('active', 'inactive', 'revoked')
            ");
        }

        // 4.3.0 이전 버전에서 마이그레이션
        if (version_compare($from_version, '4.3.0', '<')) {
            // expires_at 컬럼 추가 (dbDelta가 자동 처리)
            // 기존 API 키는 만료일 없음으로 설정 (NULL)
        }
    }

    /**
     * 테이블 삭제 (uninstall 시 사용)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'sb_analytics_logs';
        $api_keys_table = $wpdb->prefix . 'sb_api_keys';
        $groups_table = $wpdb->prefix . 'sb_link_groups';

        $wpdb->query("DROP TABLE IF EXISTS $analytics_table");
        $wpdb->query("DROP TABLE IF EXISTS $api_keys_table");
        $wpdb->query("DROP TABLE IF EXISTS $groups_table");
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "sb_daily_stats");
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

        // 애플리케이션 레벨 참조 무결성 검증: link_id가 유효한 포스트인지 확인
        if (!self::validate_link_exists($link_id)) {
            error_log(sprintf('[SB_Database] Invalid link_id: %d', $link_id));
            return false;
        }

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
     * 링크 포스트 존재 여부 검증 (애플리케이션 레벨 참조 무결성)
     *
     * @param int $link_id 링크 포스트 ID
     * @return bool 존재 여부
     * @since 4.2.0
     */
    public static function validate_link_exists($link_id)
    {
        global $wpdb;

        $post = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE ID = %d AND post_type = 'sb_link' AND post_status IN ('publish', 'draft', 'trash')",
            $link_id
        ));

        return !empty($post);
    }

    /**
     * 사용자 존재 여부 검증 (애플리케이션 레벨 참조 무결성)
     *
     * @param int $user_id 사용자 ID
     * @return bool 존재 여부
     * @since 4.2.0
     */
    public static function validate_user_exists($user_id)
    {
        global $wpdb;

        $user = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE ID = %d",
            $user_id
        ));

        return !empty($user);
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

        // 애플리케이션 레벨 참조 무결성 검증: user_id가 유효한 사용자인지 확인
        if (!self::validate_user_exists($user_id)) {
            error_log(sprintf('[SB_Database] Invalid user_id: %d', $user_id));
            return false;
        }

        $table = $wpdb->prefix . 'sb_api_keys';

        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'status' => self::API_STATUS_ACTIVE,
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
            "SELECT secret_key FROM $table WHERE api_key = %s AND status = %s",
            $api_key,
            self::API_STATUS_ACTIVE
        ));
    }

    /**
     * API 키 만료 여부 확인
     *
     * @param string $api_key 공개 키
     * @return bool 만료되었으면 true, 유효하면 false
     * @since 4.3.0
     */
    public static function is_api_key_expired($api_key)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        $expires_at = $wpdb->get_var($wpdb->prepare(
            "SELECT expires_at FROM $table WHERE api_key = %s AND status = %s",
            $api_key,
            self::API_STATUS_ACTIVE
        ));

        // expires_at이 NULL이면 무기한으로 유효
        if ($expires_at === null) {
            return false;
        }

        // 현재 시간과 비교
        $current_time = current_time('mysql');
        return $expires_at < $current_time;
    }

    /**
     * API 키 만료일 설정
     *
     * @param string $api_key 공개 키
     * @param string $expires_at 만료일 (Y-m-d H:i:s), NULL = 무기한
     * @return bool 성공 여부
     * @since 4.3.0
     */
    public static function set_api_key_expiration($api_key, $expires_at)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->update(
            $table,
            ['expires_at' => $expires_at],
            ['api_key' => $api_key],
            ['%s'],
            ['%s']
        ) !== false;
    }

    /**
     * 곧 만료될 API 키 목록 조회 (관리자 알림용)
     *
     * @param int $days 만료까지 남은 일수
     * @return array 만료 예정인 API 키 목록
     * @since 4.3.0
     */
    public static function get_expiring_api_keys($days = 7)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';
        $expiry_threshold = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT ak.*, u.user_login
             FROM $table ak
             INNER JOIN {$wpdb->users} u ON ak.user_id = u.ID
             WHERE ak.status = %s
               AND ak.expires_at IS NOT NULL
               AND ak.expires_at <= %s
               AND ak.expires_at > %s
             ORDER BY ak.expires_at ASC",
            self::API_STATUS_ACTIVE,
            $expiry_threshold,
            current_time('mysql')
        ), ARRAY_A);
    }

    /**
     * 만료된 API 키 목록 조회
     *
     * @return array 만료된 API 키 목록
     * @since 4.3.0
     */
    public static function get_expired_api_keys()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_api_keys';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT ak.*, u.user_login
             FROM $table ak
             INNER JOIN {$wpdb->users} u ON ak.user_id = u.ID
             WHERE ak.status = %s
               AND ak.expires_at IS NOT NULL
               AND ak.expires_at <= %s
             ORDER BY ak.expires_at DESC",
            self::API_STATUS_ACTIVE,
            current_time('mysql')
        ), ARRAY_A);
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

        // 상태 값 검증
        if (!in_array($status, self::VALID_API_STATUSES, true)) {
            error_log(sprintf('[SB_Database] Invalid API status: %s', $status));
            return false;
        }

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
     * 유효한 API 키 상태인지 검증
     *
     * @param string $status 상태 값
     * @return bool 유효 여부
     * @since 4.2.0
     */
    public static function is_valid_api_status($status)
    {
        return in_array($status, self::VALID_API_STATUSES, true);
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

    /**
     * 트랜잭션 시작 (v3.0.0 Update: Data Integrity)
     * 
     * @return bool 성공 여부
     */
    public static function start_transaction()
    {
        global $wpdb;
        return $wpdb->query('START TRANSACTION') !== false;
    }

    /**
     * 트랜잭션 커밋
     * 
     * @return bool 성공 여부
     */
    public static function commit()
    {
        global $wpdb;
        return $wpdb->query('COMMIT') !== false;
    }

    /**
     * 트랜잭션 롤백
     * 
     * @return bool 성공 여부
     */
    public static function rollback()
    {
        global $wpdb;
        return $wpdb->query('ROLLBACK') !== false;
    }

    /**
     * JSON 데이터 검증 및 저장
     *
     * @param mixed $data JSON으로 변환할 데이터
     * @return string|false 유효한 JSON 문자열 또는 false
     * @since 4.2.0
     */
    public static function validate_and_encode_json($data)
    {
        if (empty($data)) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        // JSON 유효성 검증
        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            error_log(sprintf('[SB_Database] JSON encode error: %s', json_last_error_msg()));
            return false;
        }

        return $json;
    }

    /**
     * JSON 데이터 디코딩
     *
     * @param string $json JSON 문자열
     * @return mixed|null 디코딩된 데이터 또는 null
     * @since 4.2.0
     */
    public static function decode_json($json)
    {
        if (empty($json)) {
            return null;
        }

        $data = json_decode($json, true);

        // JSON 유효성 검증
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(sprintf('[SB_Database] JSON decode error: %s', json_last_error_msg()));
            return null;
        }

        return $data;
    }

    /**
     * 일별 통계 저장 (JSON 검증 포함)
     *
     * @param string $stats_date 통계 날짜 (Y-m-d)
     * @param int $total_clicks 총 클릭 수
     * @param int $unique_visitors 고유 방문자 수
     * @param array $platform_share 플랫폼별 비율 데이터
     * @param array $referers 리퍼러 데이터
     * @return bool 성공 여부
     * @since 4.2.0
     */
    public static function save_daily_stats($stats_date, $total_clicks, $unique_visitors, $platform_share = [], $referers = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_daily_stats';

        // JSON 데이터 검증 및 인코딩
        $platform_share_json = self::validate_and_encode_json($platform_share);
        $referers_json = self::validate_and_encode_json($referers);

        if ($platform_share_json === false || $referers_json === false) {
            return false;
        }

        $result = $wpdb->replace(
            $table,
            [
                'stats_date' => $stats_date,
                'total_clicks' => $total_clicks,
                'unique_visitors' => $unique_visitors,
                'platform_share' => $platform_share_json,
                'referers' => $referers_json,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * 일별 통계 조회
     *
     * @param string $stats_date 통계 날짜 (Y-m-d)
     * @return array|null 통계 데이터
     * @since 4.2.0
     */
    public static function get_daily_stats($stats_date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sb_daily_stats';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE stats_date = %s",
            $stats_date
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        // JSON 데이터 디코딩
        $row['platform_share'] = self::decode_json($row['platform_share']);
        $row['referers'] = self::decode_json($row['referers']);

        return $row;
    }
}
