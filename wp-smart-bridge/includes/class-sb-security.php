<?php
/**
 * 보안 검증 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Security
{

    /**
     * 허용된 User-Agent
     */
    const ALLOWED_USER_AGENT = 'SB-Client/Win64-v2.0';

    /**
     * Timestamp 허용 범위 (초) - v4.3.0 Tightened to 10s
     */
    const TIMESTAMP_TOLERANCE = 10;

    /**
     * 레이트 리미트 설정
     */
    const RATE_LIMIT_MAX_REQUESTS = 100; // 1분당 최대 요청 수
    const RATE_LIMIT_WINDOW = 60; // 레이트 리미트 윈도우 (초)

    /**
     * User-Agent 버전 패턴 정규식
     */
    const USER_AGENT_VERSION_PATTERN = '/^SB-Client\/\d+\.\d+\.\d+$/';

    /**
     * User-Agent 검증
     * 
     * @param WP_REST_Request $request REST 요청
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function verify_user_agent($request)
    {
        $user_agent = $request->get_header('User-Agent');

        // v4.3.0 개선: 버전 패턴 검증 강화
        // User-Agent는 정확히 'SB-Client/X.Y.Z' 형식이어야 함 (예: SB-Client/2.0.1)
        if (empty($user_agent)) {
            return new WP_Error(
                'forbidden',
                'Unauthorized client. User-Agent header is missing.',
                ['status' => 403]
            );
        }

        // 버전 패턴 검증 (정규식: SB-Client/숫자.숫자.숫자)
        if (!preg_match(self::USER_AGENT_VERSION_PATTERN, $user_agent)) {
            return new WP_Error(
                'forbidden',
                'Unauthorized client. Invalid User-Agent format. Expected format: SB-Client/X.Y.Z',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Timestamp 검증 (Replay Attack 방지)
     * 
     * @param int $timestamp 요청 타임스탬프
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function verify_timestamp($timestamp)
    {
        $current_time = time();
        $time_diff = abs($current_time - $timestamp);

        if ($time_diff > self::TIMESTAMP_TOLERANCE) {
            return new WP_Error(
                'expired',
                // v3.0.0 UX Fix: Helpful error for Time Drift
                'Request expired. Timestamp difference exceeds ' . self::TIMESTAMP_TOLERANCE . ' seconds. Server Time: ' . date('c', $current_time) . '. Check client clock.',
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * HMAC 서명 검증
     * 
     * @param string $api_key 공개 API 키
     * @param string $body 요청 본문 (raw JSON)
     * @param int $timestamp 타임스탬프
     * @param string $signature 클라이언트 서명
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function verify_signature($api_key, $body, $timestamp, $signature)
    {
        // API Key로 Secret Key 조회
        $secret_key = SB_Database::get_secret_key($api_key);

        if (!$secret_key) {
            return new WP_Error(
                'invalid_key',
                'Invalid API key. Key not found or inactive.',
                ['status' => 403]
            );
        }

        // v4.3.0: API 키 만료 검증
        if (SB_Database::is_api_key_expired($api_key)) {
            return new WP_Error(
                'expired_key',
                'API key has expired. Please generate a new API key.',
                ['status' => 403]
            );
        }

        // 서버측 서명 생성
        // 서명 공식: HMAC_SHA256(Body + Timestamp + Nonce, SecretKey)
        // v2.9.22: Added nonce to payload for better entropy
        $nonce = $_SERVER['HTTP_X_SB_NONCE'] ?? '';
        $payload = $body . $timestamp . $nonce;
        $expected_signature = hash_hmac('sha256', $payload, $secret_key);

        // 타이밍 공격 방지를 위한 안전한 비교
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error(
                'invalid_signature',
                'HMAC signature verification failed.',
                ['status' => 403]
            );
        }

        // API 키 마지막 사용 시간 업데이트
        SB_Database::update_api_key_last_used($api_key);

        return true;
    }

    /**
     * 전체 인증 프로세스 수행
     * 
     * @param WP_REST_Request $request REST 요청
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function authenticate_request($request)
    {
        // 1. User-Agent 검증
        $ua_check = self::verify_user_agent($request);
        if (is_wp_error($ua_check)) {
            return $ua_check;
        }

        // 2. 필수 헤더 추출
        $api_key = $request->get_header('X-SB-API-KEY');
        $timestamp = $request->get_header('X-SB-TIMESTAMP');
        $signature = $request->get_header('X-SB-SIGNATURE');
        $nonce = $request->get_header('X-SB-NONCE'); // v2.9.22 Added nonce

        // 필수 헤더 확인
        if (empty($api_key) || empty($timestamp) || empty($signature) || empty($nonce)) {
            return new WP_Error(
                'missing_headers',
                'Required authentication headers are missing (API Key, Timestamp, Signature, Nonce).',
                ['status' => 400]
            );
        }

        // 3. Timestamp 검증
        $timestamp = intval($timestamp);
        $ts_check = self::verify_timestamp($timestamp);
        if (is_wp_error($ts_check)) {
            return $ts_check;
        }

        // 3-1. Nonce 검증 (Replay Attack 방지)
        // Nonce + API Key 조합으로 유니크하게 관리
        $nonce_key = 'sb_nonce_' . md5($api_key . $nonce);
        if (get_transient($nonce_key)) {
            return new WP_Error(
                'nonce_used',
                'Nonce already used. Replay attack detected.',
                ['status' => 401]
            );
        }
        // Nonce 유효 시간은 Timestamp 허용 범위와 동일하게 설정
        set_transient($nonce_key, true, self::TIMESTAMP_TOLERANCE);

        // 4. 레이트 리미트 검증 (v4.3.0)
        $rate_limit_check = self::check_rate_limit($request);
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }

        // 5. 서명 검증
        $body = $request->get_body();
        $sig_check = self::verify_signature($api_key, $body, $timestamp, $signature);
        if (is_wp_error($sig_check)) {
            return $sig_check;
        }

        return true;
    }

    /**
     * WordPress Nonce 검증 (Dashboard API용)
     * 
     * @param WP_REST_Request $request REST 요청
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function verify_nonce($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                'Invalid or missing security nonce.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * IP 기반 레이트 리미트 검증 (v4.3.0)
     *
     * IP별 요청 수를 제한하여 무차별 공격 방지
     * WordPress Transient API를 활용한 캐싱
     *
     * @param WP_REST_Request $request REST 요청
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function check_rate_limit($request)
    {
        // 클라이언트 IP 주소 추출
        $client_ip = self::get_client_ip();
        
        if (empty($client_ip)) {
            // IP를 확인할 수 없는 경우 요청 허용 (프록시 환경 등)
            return true;
        }

        // 레이트 리미트 키 생성 (IP 기반)
        $rate_limit_key = 'sb_rate_limit_' . md5($client_ip);
        
        // 현재 요청 수 조회
        $request_count = get_transient($rate_limit_key);
        
        if ($request_count === false) {
            // 첫 요청인 경우
            set_transient($rate_limit_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }

        // 요청 수 확인
        if ($request_count >= self::RATE_LIMIT_MAX_REQUESTS) {
            // 레이트 리미트 초과
            $retry_after = get_option('_transient_timeout_' . $rate_limit_key);
            $retry_after = $retry_after ? ($retry_after - time()) : self::RATE_LIMIT_WINDOW;
            
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                [
                    'status' => 429,
                    'Retry-After' => $retry_after
                ]
            );
        }

        // 요청 수 증가
        set_transient($rate_limit_key, $request_count + 1, self::RATE_LIMIT_WINDOW);

        return true;
    }

    /**
     * 클라이언트 IP 주소 추출 (v4.3.0)
     *
     * 프록시/로드밸런서 환경에서도 실제 클라이언트 IP를 추출
     *
     * @return string|null 클라이언트 IP 주소
     */
    private static function get_client_ip()
    {
        $ip = null;

        // 다양한 헤더에서 IP 주소 추출 (프록시 환경 고려)
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // 일반적인 프록시
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_CLIENT_IP',           // 일부 프록시
            'REMOTE_ADDR'               // 직접 연결
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // X-Forwarded-For 헤더의 경우 첫 번째 IP만 사용
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                break;
            }
        }

        // IP 주소 검증
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return null;
    }

    /**
     * 관리자 권한 확인
     * 
     * @return bool 관리자 여부
     */
    public static function is_admin_user()
    {
        return current_user_can('manage_options');
    }

    /**
     * 편집 권한 확인
     * 
     * @return bool 편집 권한 여부
     */
    public static function can_edit_links()
    {
        return current_user_can('edit_posts');
    }

    /**
     * AJAX 요청 인증 (REST API 우회용)
     * 
     * ⚠️ [경고: 삭제 금지] ⚠️
     * 이 메소드는 `admin-ajax.php`를 통해 들어오는 외부 API 요청을 인증하는 핵심 보안 로직입니다.
     * REST API(`/wp-json/`) 차단 문제를 해결하기 위해 필수적이므로, 절대 삭제하거나 변경하지 마십시오.
     * 
     * 동작 원리:
     * 1. `$_SERVER` 전역 변수에서 커스텀 헤더(`HTTP_X_SB_...`)를 직접 추출합니다.
     *    (워드프레스 AJAX 핸들러는 WP_REST_Request 객체를 제공하지 않기 때문)
     * 2. 추출된 헤더와 Request Body(`php://input`)를 사용하여 HMAC 서명을 검증합니다.
     * 
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function authenticate_ajax_request()
    {
        // 1. 헤더 추출 (Apache/Nginx 표준: HTTP_ prefix)
        $api_key = $_SERVER['HTTP_X_SB_API_KEY'] ?? '';
        $timestamp = $_SERVER['HTTP_X_SB_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_SB_SIGNATURE'] ?? '';
        $nonce_req = $_SERVER['HTTP_X_SB_NONCE'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 필수 헤더 확인
        if (empty($api_key) || empty($timestamp) || empty($signature) || empty($nonce_req)) {
            return new WP_Error(
                'missing_headers',
                'Required authentication headers are missing (API Key, Timestamp, Signature, Nonce).',
                ['status' => 400]
            );
        }

        // 1-1. User-Agent 검증 (v4.3.0)
        if (empty($user_agent) || !preg_match(self::USER_AGENT_VERSION_PATTERN, $user_agent)) {
            return new WP_Error(
                'forbidden',
                'Unauthorized client. Invalid User-Agent format. Expected format: SB-Client/X.Y.Z',
                ['status' => 403]
            );
        }

        // 2. Timestamp 검증
        $timestamp = intval($timestamp);
        $ts_check = self::verify_timestamp($timestamp);
        if (is_wp_error($ts_check)) {
            return $ts_check;
        }

        // 3. Nonce 검증
        $nonce_key = 'sb_nonce_' . md5($api_key . $nonce_req);
        if (get_transient($nonce_key)) {
            return new WP_Error(
                'nonce_used',
                'Nonce already used. Replay attack detected.',
                ['status' => 401]
            );
        }
        set_transient($nonce_key, true, self::TIMESTAMP_TOLERANCE);

        // 4. 레이트 리미트 검증 (v4.3.0)
        $rate_limit_check = self::check_rate_limit_ajax();
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }

        // 5. 서명 검증
        $body = file_get_contents('php://input');
        $sig_check = self::verify_signature($api_key, $body, $timestamp, $signature);
        if (is_wp_error($sig_check)) {
            return $sig_check;
        }

        return true;
    }

    /**
     * IP 기반 레이트 리미트 검증 (AJAX용) (v4.3.0)
     *
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    private static function check_rate_limit_ajax()
    {
        // 클라이언트 IP 주소 추출
        $client_ip = self::get_client_ip();
        
        if (empty($client_ip)) {
            // IP를 확인할 수 없는 경우 요청 허용 (프록시 환경 등)
            return true;
        }

        // 레이트 리미트 키 생성 (IP 기반)
        $rate_limit_key = 'sb_rate_limit_' . md5($client_ip);
        
        // 현재 요청 수 조회
        $request_count = get_transient($rate_limit_key);
        
        if ($request_count === false) {
            // 첫 요청인 경우
            set_transient($rate_limit_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }

        // 요청 수 확인
        if ($request_count >= self::RATE_LIMIT_MAX_REQUESTS) {
            // 레이트 리미트 초과
            $retry_after = get_option('_transient_timeout_' . $rate_limit_key);
            $retry_after = $retry_after ? ($retry_after - time()) : self::RATE_LIMIT_WINDOW;
            
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                [
                    'status' => 429,
                    'Retry-After' => $retry_after
                ]
            );
        }

        // 요청 수 증가
        set_transient($rate_limit_key, $request_count + 1, self::RATE_LIMIT_WINDOW);

        return true;
    }
}
