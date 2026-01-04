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
            'permission_callback' => '__return_true', // 커스텀 인증 사용
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
    }

    /**
     * 통계 API 권한 확인
     */
    public static function check_stats_permission()
    {
        return current_user_can('edit_posts');
    }

    /**
     * 링크 생성 API (EXE 전용)
     * 
     * @param WP_REST_Request $request REST 요청
     * @return WP_REST_Response|WP_Error 응답
     */
    public static function create_link($request)
    {
        // 1. 인증 수행
        $auth_result = SB_Security::authenticate_request($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }

        // 2. 요청 파라미터 추출
        $params = $request->get_json_params();
        $target_url = isset($params['target_url']) ? $params['target_url'] : '';
        $custom_slug = isset($params['slug']) ? $params['slug'] : null;

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
            $slug = SB_Helpers::generate_unique_slug(6, 3);

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

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'db_error',
                'Failed to save link to database.',
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
     * 통계 조회 API (Dashboard용)
     * 
     * @param WP_REST_Request $request REST 요청
     * @return WP_REST_Response 응답
     */
    public static function get_stats($request)
    {
        // 파라미터 추출
        $range = $request->get_param('range') ?: '30d';
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $platform_filter = $request->get_param('platform_filter');

        // 날짜 범위 계산
        if ($start_date && $end_date) {
            $date_range = [
                'start' => $start_date . ' 00:00:00',
                'end' => $end_date . ' 23:59:59',
            ];
        } else {
            $date_range = SB_Helpers::get_date_range($range);
        }

        // 분석 데이터 조회
        $analytics = new SB_Analytics();

        $data = [
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
            'growth_rate' => $analytics->get_growth_rate(),
            'active_links' => $analytics->get_active_links_count(),
            'clicks_by_hour' => $analytics->get_clicks_by_hour(
                $date_range['start'],
                $date_range['end']
            ),
            'platform_share' => $analytics->get_platform_share(
                $date_range['start'],
                $date_range['end']
            ),
            'daily_trend' => $analytics->get_daily_trend($range === '7d' ? 7 : 30),
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
    public static function get_links($request)
    {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $orderby = $request->get_param('orderby') ?: 'date';
        $order = $request->get_param('order') ?: 'desc';
        $platform = $request->get_param('platform');
        $search = $request->get_param('search');

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
