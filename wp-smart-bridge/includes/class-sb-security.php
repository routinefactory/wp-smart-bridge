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
     * Timestamp 허용 범위 (초)
     */
    const TIMESTAMP_TOLERANCE = 60;

    /**
     * User-Agent 검증
     * 
     * @param WP_REST_Request $request REST 요청
     * @return bool|WP_Error 성공 시 true, 실패 시 WP_Error
     */
    public static function verify_user_agent($request)
    {
        $user_agent = $request->get_header('User-Agent');

        if ($user_agent !== self::ALLOWED_USER_AGENT) {
            return new WP_Error(
                'forbidden',
                'Unauthorized client. Invalid User-Agent.',
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
                'Request expired. Timestamp difference exceeds ' . self::TIMESTAMP_TOLERANCE . ' seconds.',
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

        // 서버측 서명 생성
        // 서명 공식: HMAC_SHA256(Body + Timestamp, SecretKey)
        $payload = $body . $timestamp;
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

        // 필수 헤더 확인
        if (empty($api_key) || empty($timestamp) || empty($signature)) {
            return new WP_Error(
                'missing_headers',
                'Required authentication headers are missing.',
                ['status' => 400]
            );
        }

        // 3. Timestamp 검증
        $timestamp = intval($timestamp);
        $ts_check = self::verify_timestamp($timestamp);
        if (is_wp_error($ts_check)) {
            return $ts_check;
        }

        // 4. 서명 검증
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
     * HTML 살균 (로딩 메시지용)
     * 
     * @param string $html 입력 HTML
     * @return string 살균된 HTML
     */
    public static function sanitize_loading_message($html)
    {
        $allowed_tags = [
            'strong' => [],
            'em' => [],
            'b' => [],
            'i' => [],
            'br' => [],
            'p' => [],
            'span' => ['class' => []],
        ];

        return wp_kses($html, $allowed_tags);
    }
}
