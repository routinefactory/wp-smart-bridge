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
     * 링크 생성 권한 확인 (HMAC 인증)
     */
    public static function check_create_permission(WP_REST_Request $request)
    {
        $auth_result = SB_Security::authenticate_request($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
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
        // v2.9.22 보안 강화: 입력값 명시적 Sanitize
        $target_url = isset($params['target_url']) ? esc_url_raw($params['target_url']) : '';
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
            'post_type' => 'sb_link',
            'post_status' => 'publish',
            'meta_input' => [
                'target_url' => $target_url,
                'platform' => $platform,
                'click_count' => 0,
            ],
        ]);

        // v2.9.22 Security: Verify proper slug assignment (Race Condition Check)
        // 만약 커스텀 슬러그 요청이었는데, WP가 중복으로 인해 'slug-2'로 변경했다면 실패 처리
        if ($custom_slug) {
            $inserted_post = get_post($post_id);
            if ($inserted_post->post_name !== $custom_slug) {
                // 중복 발생 (Race Condition caught)
                wp_delete_post($post_id, true);
                return new WP_Error(
                    'conflict',
                    'Slug collision detected. Please try again.',
                    ['status' => 409]
                );
            }
        } else {
            // 자동 생성의 경우, 변경된 slug(post_name)를 최종 slug로 채택
            $post_name = get_post_field('post_name', $post_id);
            if ($post_name) {
                $slug = $post_name;
            }
        }

        // 에러 체크: WP_Error 또는 0 반환
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
            'top_referers' => $analytics->get_referer_stats(
                $date_range['start'],
                $date_range['end'],
                $platform_filter,
                10
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
     */
    public static function get_period_comparison(WP_REST_Request $request)
    {
        $params = self::parse_date_params($request);
        $platform_filter = $params['platform'];

        // 현재 기간
        $current_start = $request->get_param('current_start');
        $current_end = $request->get_param('current_end');
        $previous_start = $request->get_param('previous_start');
        $previous_end = $request->get_param('previous_end');

        // 지정되지 않은 경우 자동 계산 (최근 7일 vs 이전 7일)
        if (!$current_start || !$current_end) {
            $today = new DateTime('now', wp_timezone());
            $current_end = $today->format('Y-m-d 23:59:59');
            $current_start = (clone $today)->modify('-6 days')->format('Y-m-d 00:00:00');
            $previous_end = (clone $today)->modify('-7 days')->format('Y-m-d 23:59:59');
            $previous_start = (clone $today)->modify('-13 days')->format('Y-m-d 00:00:00');
        } else {
            $current_start .= ' 00:00:00';
            $current_end .= ' 23:59:59';
            $previous_start .= ' 00:00:00';
            $previous_end .= ' 23:59:59';
        }

        $analytics = new SB_Analytics();

        $data = $analytics->get_period_comparison(
            $current_start,
            $current_end,
            $previous_start,
            $previous_end,
            $platform_filter
        );

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
            'weekday_pattern' => $analytics->get_weekday_pattern(
                $date_range['start'],
                $date_range['end'],
                $platform_filter
            ),
            'returning_visitors' => $analytics->get_returning_visitors(
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
        if (!$post || $post->post_type !== 'sb_link') {
            return new WP_Error(
                'not_found',
                '링크를 찾을 수 없습니다.',
                ['status' => 404]
            );
        }

        $analytics = new SB_Analytics();

        $data = [
            'link_info' => [
                'id' => $link_id,
                'slug' => $post->post_title,
                'short_link' => SB_Helpers::get_short_link_url($post->post_title),
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
            'post_type' => 'sb_link',
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
                'slug' => $post->post_title,
                'short_link' => SB_Helpers::get_short_link_url($post->post_title),
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

