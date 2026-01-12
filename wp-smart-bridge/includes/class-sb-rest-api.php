<?php
/**
 * REST API 엔드포인트 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Rest_API
{

    /**
     * API 네임스페이스
     */
    const NAMESPACE = 'sb/v1';

    /**
     * REST 라우트 등록
     */
    public static function register_routes()
    {
        // POST /links - 링크 생성 (EXE 전용)
        register_rest_route(self::NAMESPACE , '/links', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_link'],
            'permission_callback' => [__CLASS__, 'check_create_permission'], // v2.9.22 Security Fix
        ]);

        // GET /stats - 통계 조회 (Dashboard용)
        register_rest_route(self::NAMESPACE , '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_stats'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // GET /links - 링크 목록 조회 (Dashboard용)
        register_rest_route(self::NAMESPACE , '/links', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_links'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // ========================================
        // 새로운 분석 API 엔드포인트
        // ========================================

        // GET /analytics/referers - 유입 경로 분석
        register_rest_route(self::NAMESPACE , '/analytics/referers', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_referer_analytics'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // GET /analytics/devices - 디바이스 분석
        register_rest_route(self::NAMESPACE , '/analytics/devices', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_device_analytics'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // GET /analytics/comparison - 기간 비교
        register_rest_route(self::NAMESPACE , '/analytics/comparison', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_period_comparison'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // GET /analytics/patterns - 패턴 분석 (요일, 재방문, 이상치)
        register_rest_route(self::NAMESPACE , '/analytics/patterns', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_pattern_analytics'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);

        // GET /links/{id}/analytics - 개별 링크 상세 분석
        register_rest_route(self::NAMESPACE , '/links/(?P<id>\d+)/analytics', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_link_analytics'],
            'permission_callback' => [__CLASS__, 'check_stats_permission'],
        ]);
    }

    /**
     * 통계 API 권한 확인
     */
    public static function check_stats_permission()
    {
        return current_user_can('edit_posts');
    }

    /**
     * 타사 인증 플러그인 우회 (miniOrange 등)
     * 
     * 외부 보안 플러그인이 모든 REST API 요청을 차단하는 경우,
     * Smart Bridge 엔드포인트에 대해서는 우회하도록 설정합니다.
     * 
     * 동작 원리:
     * - rest_authentication_errors 필터에 우선순위 0으로 등록
     * - Smart Bridge 경로(/sb/v1/links)인 경우 true(인증됨) 리턴
     * - 이후 Smart Bridge 내부의 permission_callback에서 HMAC 인증 수행
     * 
     * 보안 강화 (v3.1.2):
     * - 단순 문자열 포함 여부가 아닌, URL 경로 또는 rest_route 파라미터를 정확히 검사하여
     *   다른 플러그인에 대한 우회 공격(Query Parameter Pollution) 방지
     * 
     * @param mixed $result 현재 인증 결과 (WP_Error, true, null)
     * @return mixed
     */
    public static function bypass_authentication($result)
    {
        // 이미 에러가 있거나 인증된 경우 패스
        if (!empty($result)) {
            return $result;
        }

        // Method Check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $is_sb_endpoint = false;

        // 1. Check REST Route Parameter (Plain Permalinks)
        if (isset($_GET['rest_route'])) {
            if (strpos($_GET['rest_route'], '/' . self::NAMESPACE . '/links') === 0) {
                $is_sb_endpoint = true;
            }
        }
        // 2. Check URL Path (Pretty Permalinks)
        else {
            $request_uri = $_SERVER['REQUEST_URI'];
            $path = parse_url($request_uri, PHP_URL_PATH);
            if ($path && strpos($path, '/' . self::NAMESPACE . '/links') !== false) {
                $is_sb_endpoint = true;
            }
        }

        if ($is_sb_endpoint) {
            return true; // 인증 성공으로 처리하여 타사 플러그인 차단 회피
        }

        return $result;
    }

    /**
     * 링크 생성 권한 확인 (HMAC 인증)
     */
    public static function check_create_permission(WP_REST_Request $request)
    {
        $auth_result = SB_Security::authenticate_request($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }

        // v3.0.0 Security: Rate Limiting
        if (!self::check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Rate Limiting (Token Bucket / Sliding Window simplified)
     * Limit: 60 requests per minute per IP
     */
    private static function check_rate_limit()
    {
        $ip = SB_Helpers::get_client_ip();
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $transient_key = 'sb_rate_limit_' . md5($ip);
        $data = get_transient($transient_key);

        if ($data === false || !is_array($data)) {
            // New Window
            set_transient($transient_key, ['count' => 1, 'start_time' => time()], 60);
            return true;
        }

        if ($data['count'] >= 60) {
            return false;
        }

        // Increment count
        $data['count']++;

        // Preserve original window expiration
        $elapsed = time() - $data['start_time'];
        $remaining = max(1, 60 - $elapsed); // Minimum 1s to ensure it persists

        set_transient($transient_key, $data, $remaining);

        return true;
    }

    /**
     * 링크 생성 API (EXE 전용)
     *
     * @param WP_REST_Request $request REST 요청
     * @return WP_REST_Response|WP_Error 응답
     */
    public static function create_link(WP_REST_Request $request)
    {
        // 1. 인증은 permission_callback에서 처리됨

        // 2. 요청 파라미터 추출
        $params = $request->get_json_params();
        
        // v4.4.0 URL 잘림 방지: 원본 URL 길이 저장
        $original_url = isset($params['target_url']) ? $params['target_url'] : '';
        $original_url_length = strlen($original_url);
        
        // v2.9.22 보안 강화: 입력값 명시적 Sanitize
        $target_url = esc_url_raw($original_url);
        $sanitized_url_length = strlen($target_url);
        
        // v4.4.0 URL 잘림 감지: esc_url_raw()가 URL을 변경했는지 확인
        if ($original_url_length > 0 && $sanitized_url_length !== $original_url_length) {
            // URL이 잘렸거나 변경됨
            error_log(sprintf(
                '[SB_Rest_API] URL was modified by esc_url_raw(). Original: %d bytes, Sanitized: %d bytes. Diff: %d bytes.',
                $original_url_length,
                $sanitized_url_length,
                $original_url_length - $sanitized_url_length
            ));
            
            // URL이 너무 긴 경우 에러 반환 (WordPress 제한: 2083 bytes)
            if ($original_url_length > 2083) {
                return new WP_Error(
                    'url_too_long',
                    sprintf('URL is too long. Maximum allowed length is 2083 bytes. Provided URL length: %d bytes.', $original_url_length),
                    ['status' => 400]
                );
            }
        }
        
        $custom_slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : null;

        // 3. URL 유효성 검증
        if (!SB_Helpers::validate_url($target_url)) {
            return new WP_Error(
                'invalid_url',
                'Invalid target URL format. Must start with http:// or https://',
                ['status' => 400]
            );
        }

        // 4. Slug 생성 또는 검증
        if ($custom_slug) {
            // 커스텀 Slug 유효성 검증
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $custom_slug)) {
                return new WP_Error(
                    'invalid_slug',
                    'Slug can only contain alphanumeric characters, hyphens, and underscores.',
                    ['status' => 400]
                );
            }

            // 중복 체크
            if (SB_Helpers::slug_exists($custom_slug)) {
                return new WP_Error(
                    'conflict',
                    'Slug already exists. Please choose a different one.',
                    ['status' => 409]
                );
            }

            $slug = $custom_slug;
        } else {
            // 자동 생성 (Base62, 6자리)
            $slug = SB_Helpers::generate_unique_slug(SB_Helpers::DEFAULT_SLUG_LENGTH, SB_Helpers::MAX_SLUG_RETRIES);

            if (!$slug) {
                return new WP_Error(
                    'generation_failed',
                    'Failed to generate unique slug after 3 retries.',
                    ['status' => 500]
                );
            }
        }

        // 5. 플랫폼 자동 태깅
        $platform = SB_Helpers::detect_platform($target_url);

        // 6. 워드프레스 포스트로 저장
        $post_id = wp_insert_post([
            'post_title' => $slug,
            'post_name' => $slug,
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
                'target_url' => $target_url,
                'platform' => $platform,
                'click_count' => 0,
            ],
        ]);

        // 6.1. 에러 체크: WP_Error 또는 0 반환 (wp_insert_post 직후에 체크)
        if (is_wp_error($post_id)) {
            return new WP_Error(
                'db_error',
                'Failed to save link: ' . $post_id->get_error_message(),
                ['status' => 500]
            );
        }

        if ($post_id === 0) {
            return new WP_Error(
                'db_error',
                'Failed to create link: wp_insert_post returned 0',
                ['status' => 500]
            );
        }

        // 6.2. 슬러그 충돌 체크 (Race Condition Check)
        // 만약 커스텀 슬러그 요청이었는데, WP가 중복으로 인해 'slug-2'로 변경했다면 실패 처리
        if ($custom_slug) {
            $inserted_post_name = get_post_field('post_name', $post_id);
            $expected_slug = sanitize_title($custom_slug);
            if ($inserted_post_name !== $expected_slug) {
                // 중복 발생 (Race Condition caught)
                wp_delete_post($post_id, true);
                return new WP_Error(
                    'conflict',
                    'Slug collision detected. Please try again.',
                    ['status' => 409]
                );
            }
            $slug = $inserted_post_name;
        } else {
            // 자동 생성의 경우, 변경된 slug(post_name)를 최종 slug로 채택
            // v3.0.0 Security Fix: Strict check for race conditions
            $inserted_post_name = get_post_field('post_name', $post_id);
            if ($inserted_post_name !== $slug) {
                // 경합 발생 (예: sb_link_1234 -> sb_link_1234-2)
                // 자동 생성된 슬러그는 유일해야 하므로, -2가 붙은 것은 의도와 다름 (재시도 유도)
                wp_delete_post($post_id, true);
                return new WP_Error(
                    'conflict',
                    'Slug collision detected during generation. Please try again.',
                    ['status' => 409]
                );
            }
        }
        
        // v4.4.0 URL 무결성 검증: 저장된 URL이 원본과 일치하는지 확인
        $saved_url = get_post_meta($post_id, 'target_url', true);
        if ($saved_url !== $target_url) {
            // URL이 잘렸거나 변경됨
            error_log(sprintf(
                '[SB_Rest_API] URL integrity check failed. Original length: %d bytes, Saved length: %d bytes. Diff: %d bytes.',
                strlen($target_url),
                strlen($saved_url),
                strlen($target_url) - strlen($saved_url)
            ));
            
            // 데이터베이스 문제일 가능성이 있으므로 포스트 삭제 후 에러 반환
            wp_delete_post($post_id, true);
            return new WP_Error(
                'url_truncation_detected',
                sprintf('URL was truncated during save. Original length: %d bytes, Saved length: %d bytes.', strlen($target_url), strlen($saved_url)),
                ['status' => 500]
            );
        }

        // P1 Performance: 링크 생성 시 관련 캐시 무효화
        SB_Helpers::invalidate_cache_by_tags([
            SB_Helpers::CACHE_TAG_ANALYTICS,
            SB_Helpers::CACHE_TAG_LINKS,
            SB_Helpers::CACHE_TAG_PLATFORMS,
            SB_Helpers::CACHE_TAG_STATS
        ]);

        // 7. 성공 응답
        return new WP_REST_Response([
            'success' => true,
            'short_link' => SB_Helpers::get_short_link_url($slug),
            'slug' => $slug,
            'target_url' => $target_url,
            'platform' => $platform,
            'created_at' => current_time('c'),
        ], 200);
    }

    /**
     * 날짜 범위 파라미터 파싱 헬퍼
     */
    private static function parse_date_params(WP_REST_Request $request)
    {
        $range = $request->get_param('range') ?: '30d';
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $platform_filter = $request->get_param('platform_filter');
        // v3.0.7: Accept both 'platform' (from JS getFilterParams) and 'platform_filter'
        $platform_filter = $request->get_param('platform') ?: $platform_filter;
        // v3.0.7: Normalize 'all' to null so analytics methods don't apply platform filter
        if ($platform_filter === 'all' || $platform_filter === '') {
            $platform_filter = null;
        }

        if ($start_date && $end_date) {
            $date_range = [
                'start' => $start_date . ' 00:00:00',
                'end' => $end_date . ' 23:59:59',
            ];
        } else {
            $date_range = SB_Helpers::get_date_range($range);
        }

        return [
            'date_range' => $date_range,
            'platform' => $platform_filter,
            'range_type' => $range,
        ];
    }

    /**
     * 통계 조회 API (Dashboard용) - 필터 정합성 개선
     *
     * @param WP_REST_Request $request REST 요청
     * @return WP_REST_Response 응답
     */
    public static function get_stats(WP_REST_Request $request)
    {
        try {
            $params = self::parse_date_params($request);
            $date_range = $params['date_range'];
            $platform_filter = $params['platform'];

            $analytics = new SB_Analytics();

            // ✅ 모든 데이터에 필터 일괄 적용
            $data = [
                // 선택 기간 + 플랫폼 필터 적용된 클릭/UV
                'total_clicks' => $analytics->get_total_clicks(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                'unique_visitors' => $analytics->get_unique_visitors(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                // ✅ 동적 증감률 (선택 기간 vs 이전 동일 기간)
                'growth_rate_data' => $analytics->get_dynamic_growth_rate(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                'growth_rate' => $analytics->get_dynamic_growth_rate(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                )['rate'],
                'active_links' => $analytics->get_active_links_count(),
                'clicks_by_hour' => $analytics->get_clicks_by_hour(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                // ✅ 플랫폼 점유율도 필터 적용
                'platform_share' => $analytics->get_platform_share_filtered(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                'daily_trend' => $analytics->get_daily_trend(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter
                ),
                'top_links' => $analytics->get_top_links(
                    $date_range['start'],
                    $date_range['end'],
                    $platform_filter,
                    20
                ),
                // 필터 정보 반환 (프론트엔드 확인용)
                'filter_info' => [
                    'start_date' => $date_range['start'],
                    'end_date' => $date_range['end'],
                    'platform' => $platform_filter,
                ],
            ];

            return new WP_REST_Response([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
            // 에러 로깅
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SB_Rest_API::get_stats() Error: ' . $e->getMessage());
            }
            return new WP_Error(
                'stats_error',
                'Failed to retrieve statistics',
                ['status' => 500]
            );
        }
    }

    /**
     * 유입 경로 분석 API
     */
    public static function get_referer_analytics(WP_REST_Request $request)
    {
        $params = self::parse_date_params($request);
        $date_range = $params['date_range'];
        $platform_filter = $params['platform'];

        $analytics = new SB_Analytics();

        $data = [
            // v3.1.0: Increased from TOP 10 to TOP 100 for detailed referrer analysis
            'top_referers' => $analytics->get_referer_stats(
                $date_range['start'],
                $date_range['end'],
                $platform_filter,
                100
            ),
            'referer_groups' => $analytics->get_referer_groups(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * 디바이스 분석 API
     */
    public static function get_device_analytics(WP_REST_Request $request)
    {
        $params = self::parse_date_params($request);
        $date_range = $params['date_range'];
        $platform_filter = $params['platform'];

        $analytics = new SB_Analytics();

        $data = [
            'devices' => $analytics->get_device_stats(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'os' => $analytics->get_os_stats(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'browsers' => $analytics->get_browser_stats(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'mobile_trend' => $analytics->get_mobile_ratio_trend(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'platform_device_matrix' => $analytics->get_platform_device_matrix(
                $date_range['start'],
                $date_range['end']
            ),
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * 기간 비교 API
     * v3.0.3 Fix: Properly handle `type` parameter (wow, mom, custom)
     */
    public static function get_period_comparison(WP_REST_Request $request)
    {
        $params = self::parse_date_params($request);
        $platform_filter = $params['platform'];

        // Get comparison type
        $type = $request->get_param('type') ?: 'wow';

        $today = new DateTime('now', wp_timezone());

        switch ($type) {
            case 'wow': // Week over Week
                // Current: Last 7 days (today - 6 days to today)
                $current_end = clone $today;
                $current_start = (clone $today)->modify('-6 days');
                // Previous: 7 days before that
                $previous_end = (clone $today)->modify('-7 days');
                $previous_start = (clone $today)->modify('-13 days');
                break;

            case 'mom': // Month over Month
                // Current: Last 30 days
                $current_end = clone $today;
                $current_start = (clone $today)->modify('-29 days');
                // Previous: 30 days before that
                $previous_end = (clone $today)->modify('-30 days');
                $previous_start = (clone $today)->modify('-59 days');
                break;

            case 'custom':
                // Use provided dates
                $current_start_str = $request->get_param('current_start');
                $current_end_str = $request->get_param('current_end');
                $previous_start_str = $request->get_param('previous_start');
                $previous_end_str = $request->get_param('previous_end');

                // Fallback to WoW if custom dates not provided
                if (!$current_start_str || !$current_end_str || !$previous_start_str || !$previous_end_str) {
                    $current_end = clone $today;
                    $current_start = (clone $today)->modify('-6 days');
                    $previous_end = (clone $today)->modify('-7 days');
                    $previous_start = (clone $today)->modify('-13 days');
                } else {
                    $current_start = new DateTime($current_start_str, wp_timezone());
                    $current_end = new DateTime($current_end_str, wp_timezone());
                    $previous_start = new DateTime($previous_start_str, wp_timezone());
                    $previous_end = new DateTime($previous_end_str, wp_timezone());
                }
                break;

            default: // Default to WoW
                $current_end = clone $today;
                $current_start = (clone $today)->modify('-6 days');
                $previous_end = (clone $today)->modify('-7 days');
                $previous_start = (clone $today)->modify('-13 days');
        }

        // Format dates with time bounds
        $current_start_fmt = $current_start->format('Y-m-d 00:00:00');
        $current_end_fmt = $current_end->format('Y-m-d 23:59:59');
        $previous_start_fmt = $previous_start->format('Y-m-d 00:00:00');
        $previous_end_fmt = $previous_end->format('Y-m-d 23:59:59');

        $analytics = new SB_Analytics();

        $data = $analytics->get_period_comparison(
            $current_start_fmt,
            $current_end_fmt,
            $previous_start_fmt,
            $previous_end_fmt,
            $platform_filter
        );

        // Add date range info to response for debugging/display
        $data['date_ranges'] = [
            'current' => ['start' => $current_start->format('Y-m-d'), 'end' => $current_end->format('Y-m-d')],
            'previous' => ['start' => $previous_start->format('Y-m-d'), 'end' => $previous_end->format('Y-m-d')],
            'type' => $type
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * 패턴 분석 API (요일, 재방문, 이상치)
     */
    public static function get_pattern_analytics(WP_REST_Request $request)
    {
        $params = self::parse_date_params($request);
        $date_range = $params['date_range'];
        $platform_filter = $params['platform'];

        $analytics = new SB_Analytics();

        $data = [
            /**
             * v3.0.3 FIX: Response Key Names Must Match JavaScript Expectations
             * 
             * PROBLEM: The "Advanced Pattern Analysis" section showed "-" for all values.
             * - Weekday chart was empty
             * - Visitor stats (new/returning/frequent) all showed placeholder dashes
             * 
             * ROOT CAUSE: Key name mismatch between PHP response and JS consumer.
             *   - PHP returned: 'weekday_pattern', 'returning_visitors'
             *   - JS expected: 'weekday_stats', 'visitor_stats'
             * 
             * JS Code (admin/js/sb-admin.js) does:
             *   SB_Chart.renderWeekday(response.data.weekday_stats);  // <-- expects 'weekday_stats'
             *   renderVisitorStats(response.data.visitor_stats);       // <-- expects 'visitor_stats'
             * 
             * SOLUTION: Renamed response keys to match JS expectations.
             * 
             * IMPORTANT: If you change these keys, you MUST also update:
             *   - admin/js/sb-admin.js Lines 189-191 (loadPatternAnalytics success callback)
             * 
             * @since 3.0.3
             */
            'weekday_stats' => $analytics->get_weekday_pattern(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'visitor_stats' => $analytics->get_returning_visitors(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'anomalies' => $analytics->get_anomalies(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'link_age_performance' => $analytics->get_link_age_performance(
                $date_range['start'],
                $date_range['end']
            ),
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * 개별 링크 상세 분석 API
     */
    public static function get_link_analytics(WP_REST_Request $request)
    {
        $link_id = (int) $request->get_param('id');
        $params = self::parse_date_params($request);
        $date_range = $params['date_range'];

        // 링크 존재 확인
        $post = get_post($link_id);
        if (!$post || $post->post_type !== SB_Post_Type::POST_TYPE) {
            return new WP_Error(
                'not_found',
                __('링크를 찾을 수 없습니다.', 'sb'),
                ['status' => 404]
            );
        }

        $analytics = new SB_Analytics();

        $data = [
            'link_info' => [
                'id' => $link_id,
                'slug' => $post->post_name,
                'short_link' => SB_Helpers::get_short_link_url($post->post_name),
                'target_url' => get_post_meta($link_id, 'target_url', true),
                'platform' => get_post_meta($link_id, 'platform', true),
                'created_at' => $post->post_date,
            ],
            'stats' => $analytics->get_link_detailed_stats(
                $link_id,
                $date_range['start'],
                $date_range['end']
            ),
            'referers' => $analytics->get_link_referer_breakdown(
                $link_id,
                $date_range['start'],
                $date_range['end']
            ),
            'devices' => $analytics->get_link_device_breakdown(
                $link_id,
                $date_range['start'],
                $date_range['end']
            ),
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * 링크 목록 조회 API (Dashboard용)
     * 
     * @param WP_REST_Request $request REST 요청
     * @return WP_REST_Response 응답
     */
    public static function get_links(WP_REST_Request $request)
    {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $orderby = $request->get_param('orderby') ?: 'date';
        $order = $request->get_param('order') ?: 'desc';
        $platform = $request->get_param('platform');
        $search = $request->get_param('search');

        // v2.9.22 Security: Enforce upper bound to prevent DoS (OOM)
        $per_page = min(intval($per_page), 100);

        $args = [
            'post_type' => SB_Post_Type::POST_TYPE,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
        ];

        // 정렬
        switch ($orderby) {
            case 'clicks':
                $args['meta_key'] = 'click_count';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'title':
                $args['orderby'] = 'title';
                break;
            default:
                $args['orderby'] = 'date';
        }
        $args['order'] = strtoupper($order);

        // 플랫폼 필터
        if ($platform) {
            $args['meta_query'] = [
                [
                    'key' => 'platform',
                    'value' => $platform,
                ],
            ];
        }

        // 검색
        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $links = [];

        foreach ($query->posts as $post) {
            $links[] = [
                'id' => $post->ID,
                'slug' => $post->post_name,
                'short_link' => SB_Helpers::get_short_link_url($post->post_name),
                'target_url' => get_post_meta($post->ID, 'target_url', true),
                'platform' => get_post_meta($post->ID, 'platform', true),
                'click_count' => (int) get_post_meta($post->ID, 'click_count', true),
                'created_at' => $post->post_date,
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'links' => $links,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
                'current_page' => $page,
            ],
        ], 200);
    }
}

