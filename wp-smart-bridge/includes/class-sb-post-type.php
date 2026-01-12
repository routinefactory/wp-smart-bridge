<?php
/**
 * 커스텀 포스트 타입 클래스
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Post_Type
{

    /**
     * 포스트 타입 이름
     */
    const POST_TYPE = 'sb_link';

    /**
     * 포스트 타입 등록
     */
    public static function register()
    {
        // 포스트 타입 등록
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('단축 링크', 'sb'),
                'singular_name' => __('단축 링크', 'sb'),
                'menu_name' => __('단축 링크', 'sb'),
                'add_new' => __('새 링크 추가', 'sb'),
                'add_new_item' => __('새 단축 링크 추가', 'sb'),
                'edit_item' => __('단축 링크 수정', 'sb'),
                'new_item' => __('새 단축 링크', 'sb'),
                'view_item' => __('단축 링크 보기', 'sb'),
                'search_items' => __('단축 링크 검색', 'sb'),
                'not_found' => __('단축 링크가 없습니다', 'sb'),
                'not_found_in_trash' => __('휴지통에 단축 링크가 없습니다', 'sb'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // 커스텀 메뉴 사용
            'show_in_rest' => false,
            'supports' => ['title'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow', // 생성 권한 차단
            ],
            'map_meta_cap' => true,
        ]);

        // 메타 박스 추가
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);

        // 메타 저장
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);

        // 생성 권한 차단 필터
        add_filter('user_has_cap', [__CLASS__, 'filter_capabilities'], 10, 3);

        // 컬럼 커스터마이징
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);

        // 클릭 수 정렬 쿼리 처리
        add_action('pre_get_posts', [__CLASS__, 'handle_click_count_sorting']);

        // Row Actions 필터 (단축 링크 열기 버튼 추가)
        add_filter('post_row_actions', [__CLASS__, 'add_row_actions'], 10, 2);

        // 고급 필터 UI (wp_restrict_manage_posts 사용 권장)
        add_action('restrict_manage_posts', [__CLASS__, 'render_filter_dropdowns']);

        // 일괄 작업(Bulk Actions) 처리
        add_action('admin_init', [__CLASS__, 'handle_bulk_actions']);

        // 기본 날짜 필터 숨기기
        add_filter('disable_months_dropdown', [__CLASS__, 'disable_date_dropdown'], 10, 2);
    }

    /**
     * 기본 날짜 필터 비활성화
     */
    public static function disable_date_dropdown($disable, $post_type)
    {
        return $post_type === self::POST_TYPE ? true : $disable;
    }

    /**
     * 메타 박스 추가
     */
    public static function add_meta_boxes()
    {
        add_meta_box(
            'sb_link_details',
            __('링크 상세 정보', 'sb'),
            [__CLASS__, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sb_link_stats',
            __('클릭 통계', 'sb'),
            [__CLASS__, 'render_stats_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * 상세 정보 메타 박스 렌더링
     */
    public static function render_meta_box($post)
    {
        wp_nonce_field('sb_link_meta', 'sb_link_meta_nonce');

        $target_url = get_post_meta($post->ID, 'target_url', true);
        $platform = get_post_meta($post->ID, 'platform', true) ?: 'Etc'; // Fixed potential warnings
        $short_link = SB_Helpers::get_short_link_url($post->post_name);
        $click_count = get_post_meta($post->ID, 'click_count', true) ?: 0;

        // UV 통계 조회
        $analytics = new SB_Analytics();
        $today_uv = $analytics->get_link_today_uv($post->ID);
        $total_uv = $analytics->get_link_total_uv($post->ID);
        $today_clicks = $analytics->get_link_today_clicks($post->ID);

        ?>
        <!-- 클릭 통계 섹션 -->
        <div class="sb-stats-section">
            <h4>📊 <?php _e('클릭 통계', 'sb'); ?></h4>
            <div class="sb-stats-grid">
                <div class="sb-stat-box">
                    <span class="sb-stat-label"><?php _e('오늘 클릭 (PV)', 'sb'); ?></span>
                    <span class="sb-stat-value"><?php echo number_format($today_clicks); ?></span>
                </div>
                <div class="sb-stat-box">
                    <span class="sb-stat-label"><?php _e('오늘 방문자 (UV)', 'sb'); ?></span>
                    <span class="sb-stat-value"><?php echo number_format($today_uv); ?></span>
                </div>
                <div class="sb-stat-box">
                    <span class="sb-stat-label"><?php _e('누적 클릭 (PV)', 'sb'); ?></span>
                    <span class="sb-stat-value"><?php echo number_format($click_count); ?></span>
                </div>
                <div class="sb-stat-box">
                    <span class="sb-stat-label"><?php _e('누적 방문자 (UV)', 'sb'); ?></span>
                    <span class="sb-stat-value"><?php echo number_format($total_uv); ?></span>
                </div>
            </div>
        </div>

        <table class="form-table">
            <tr>
                <th><label><?php _e('단축 URL', 'sb'); ?></label></th>
                <td>
                    <input type="text" value="<?php echo esc_url($short_link); ?>" class="large-text" readonly>
                    <p class="description">
                        <button type="button" class="button button-secondary sb-copy-link"
                            data-link="<?php echo esc_url($short_link); ?>" aria-label="<?php esc_attr_e('단축 링크 복사', 'sb'); ?>">
                            <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                            <?php _e('복사', 'sb'); ?>
                        </button>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="sb_target_url"><?php _e('타겟 URL', 'sb'); ?></label></th>
                <td>
                    <input type="url" id="sb_target_url" name="sb_target_url" value="<?php echo esc_url($target_url); ?>"
                        class="large-text" required placeholder="https://example.com">
                    <p class="description">
                        <span class="sb-text-subtle"><?php _e('이동할 최종 목적지 URL을 입력하세요. (Protocol 필수)', 'sb'); ?></span>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('플랫폼', 'sb'); ?></label></th>
                <td>
                    <span class="sb-platform-badge sb-platform-<?php echo esc_attr(strtolower($platform)); ?>">
                        <?php echo esc_html($platform); ?>
                    </span>
                    <p class="description">
                        <?php _e('타겟 URL의 도메인을 기반으로 자동 분류됩니다.', 'sb'); ?><br>
                        <span class="sb-text-subtle">
                            💡 <?php _e('타겟 URL 변경 시 플랫폼도 자동으로 업데이트됩니다. 단, 기존 클릭 로그는 변경 전 플랫폼으로 유지됩니다.', 'sb'); ?>
                        </span>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * 통계 메타 박스 렌더링
     */
    public static function render_stats_meta_box($post)
    {
        $click_count = (int) get_post_meta($post->ID, 'click_count', true);
        $created = get_the_date('Y-m-d H:i:s', $post);

        ?>
        <div class="sb-side-stats-box">
            <div class="sb-side-stat-item">
                <span class="sb-side-stat-label"><?php _e('총 클릭 수', 'sb'); ?></span>
                <span class="sb-side-stat-value">
                    <?php echo number_format($click_count); ?>
                </span>
            </div>
            <div class="sb-side-stat-item">
                <span class="sb-side-stat-label"><?php _e('생성일', 'sb'); ?></span>
                <span class="sb-side-stat-value">
                    <?php echo esc_html($created); ?>
                </span>
            </div>
        </div>
        <?php
    }

    /**
     * 메타 저장
     */
    public static function save_meta($post_id, $post)
    {
        // Nonce 확인
        if (
            !isset($_POST['sb_link_meta_nonce']) ||
            !wp_verify_nonce($_POST['sb_link_meta_nonce'], 'sb_link_meta')
        ) {
            return;
        }

        // 자동 저장 제외
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 권한 확인
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // P1 Performance: 플랫폼 변경 감지를 위한 기존 값 저장
        $old_platform = get_post_meta($post_id, 'platform', true);

        // 타겟 URL 저장
        if (isset($_POST['sb_target_url'])) {
            $target_url = esc_url_raw($_POST['sb_target_url']);

            if (SB_Helpers::validate_url($target_url)) {
                update_post_meta($post_id, 'target_url', $target_url);

                // 플랫폼 자동 재태깅
                $platform = SB_Helpers::detect_platform($target_url);
                update_post_meta($post_id, 'platform', $platform);

                // P1 Performance: 플랫폼이 변경된 경우 관련 캐시 무효화
                if ($old_platform && $old_platform !== $platform) {
                    SB_Helpers::invalidate_cache_by_tags([
                        SB_Helpers::CACHE_TAG_ANALYTICS,
                        SB_Helpers::CACHE_TAG_PLATFORMS,
                        SB_Helpers::CACHE_TAG_STATS
                    ]);
                }
            }
        }

        // P1 Performance: 링크 수정 시 분석 캐시 무효화
        SB_Helpers::invalidate_cache_by_tags([
            SB_Helpers::CACHE_TAG_ANALYTICS,
            SB_Helpers::CACHE_TAG_LINKS,
            SB_Helpers::CACHE_TAG_STATS
        ]);
    }

    /**
     * 생성 권한 차단 필터
     */
    public static function filter_capabilities($allcaps, $caps, $args)
    {
        // 새 글 작성 권한 체크
        if (isset($args[0]) && $args[0] === 'edit_post') {
            // 새 포스트 생성 시도 감지 (post_id가 없는 경우)
            if (isset($_GET['post_type']) && $_GET['post_type'] === self::POST_TYPE) {
                if (!isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
                    // 새 글 작성 페이지 차단
                    $allcaps['edit_posts'] = false;
                }
            }
        }

        return $allcaps;
    }

    /**
     * 커스텀 컬럼 정의
     */
    public static function custom_columns($columns)
    {
        $new_columns = [];

        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Slug';
        $new_columns['target_url'] = __('타겟 URL', 'sb');
        $new_columns['platform'] = __('플랫폼', 'sb');
        // 4개 통계 컬럼 (플랫폼과 생성일 사이)
        $new_columns['today_pv'] = __('오늘 PV', 'sb');
        $new_columns['today_uv'] = __('오늘 UV', 'sb');
        $new_columns['total_pv'] = __('누적 PV', 'sb');
        $new_columns['total_uv'] = __('누적 UV', 'sb');
        $new_columns['date'] = __('생성일', 'sb');

        return $new_columns;
    }

    /**
     * 컬럼 내용 렌더링
     */
    public static function column_content($column, $post_id)
    {
        switch ($column) {
            case 'target_url':
                $url = get_post_meta($post_id, 'target_url', true);
                echo '<a href="' . esc_url($url) . '" target="_blank">' .
                    esc_html(mb_strimwidth($url, 0, 50, '...')) . '</a>';
                break;

            case 'platform':
                $platform = get_post_meta($post_id, 'platform', true) ?: 'Etc';
                echo '<span class="sb-platform-badge sb-platform-' . esc_attr(strtolower($platform)) . '">' .
                    esc_html($platform) . '</span>';
                break;

            case 'today_pv':
                $count = SB_Helpers::get_today_stat($post_id, 'stats_today_pv');
                echo $count > 0
                    ? '<strong class="sb-stat-pv">' . number_format($count) . '</strong>'
                    : '<span class="sb-text-muted">0</span>';
                break;

            case 'today_uv':
                $count = SB_Helpers::get_today_stat($post_id, 'stats_today_uv');
                echo $count > 0
                    ? '<strong class="sb-stat-uv">' . number_format($count) . '</strong>'
                    : '<span class="sb-text-muted">0</span>';
                break;

            case 'total_pv':
                $count = (int) get_post_meta($post_id, 'click_count', true);
                echo '<strong class="sb-stat-pv">' . number_format($count) . '</strong>';
                break;

            case 'total_uv':
                $count = (int) get_post_meta($post_id, 'stats_total_uv', true);
                echo $count > 0
                    ? '<strong class="sb-stat-uv">' . number_format($count) . '</strong>'
                    : '<span class="sb-text-muted">0</span>';
                break;
        }
    }

    /**
     * 정렬 가능한 컬럼
     */
    public static function sortable_columns($columns)
    {
        // 4개 통계 컬럼 모두 정렬 지원
        $columns['today_pv'] = 'today_pv';
        $columns['today_uv'] = 'today_uv';
        $columns['total_pv'] = 'total_pv';
        $columns['total_uv'] = 'total_uv';
        return $columns;
    }

    /**
     * 쿼리 수정 (정렬 + 필터)
     * 
     * @note v3.1.0: 고급 필터 기능 추가
     * @note v4.1.3: LEFT JOIN 방식 정렬 (0값 포함)
     */
    /**
     * 쿼리 수정 (정렬 + 필터)
     * 
     * @note v3.1.0: 고급 필터 기능 추가
     * @note v4.1.3: LEFT JOIN 방식 정렬 (0값 포함)
     */
    public static function handle_click_count_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        // =====================
        // 1. 필터 처리 (기존 유지)
        // =====================
        $meta_query = $query->get('meta_query') ?: [];

        // 플랫폼 필터
        if (!empty($_GET['sb_platform'])) {
            $meta_query[] = [
                'key' => 'platform',
                'value' => sanitize_text_field($_GET['sb_platform']),
            ];
        }

        // 클릭수 필터
        if (!empty($_GET['sb_clicks'])) {
            $clicks = sanitize_text_field($_GET['sb_clicks']);

            switch ($clicks) {
                case '0':
                    $meta_query[] = [
                        'relation' => 'OR',
                        ['key' => 'click_count', 'compare' => 'NOT EXISTS'],
                        ['key' => 'click_count', 'value' => '0', 'compare' => '='],
                    ];
                    break;
                case '1-100':
                    $meta_query[] = [
                        'key' => 'click_count',
                        'value' => [1, 100],
                        'type' => 'NUMERIC',
                        'compare' => 'BETWEEN',
                    ];
                    break;
                case '100-1000':
                    $meta_query[] = [
                        'key' => 'click_count',
                        'value' => [100, 1000],
                        'type' => 'NUMERIC',
                        'compare' => 'BETWEEN',
                    ];
                    break;
                case '1000+':
                    $meta_query[] = [
                        'key' => 'click_count',
                        'value' => 1000,
                        'type' => 'NUMERIC',
                        'compare' => '>=',
                    ];
                    break;
            }
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $query->set('meta_query', $meta_query);
        }

        // 생성일 필터
        if (!empty($_GET['sb_date_range'])) {
            $range = sanitize_text_field($_GET['sb_date_range']);
            $date_query = [];

            switch ($range) {
                case 'today':
                    $date_query = ['after' => 'today', 'inclusive' => true];
                    break;
                case '7d':
                    $date_query = ['after' => '7 days ago'];
                    break;
                case '30d':
                    $date_query = ['after' => '30 days ago'];
                    break;
                case '90d':
                    $date_query = ['after' => '90 days ago'];
                    break;
            }

            if (!empty($date_query)) {
                $query->set('date_query', [$date_query]);
            }
        }

        // =====================
        // 2. 정렬 처리 (LEFT JOIN 개선)
        // =====================
        $orderby = $query->get('orderby');
        $valid_columns = ['total_pv', 'total_uv', 'today_pv', 'today_uv'];

        if (in_array($orderby, $valid_columns)) {
            // WordPress 기본 meta_key 사용 시 INNER JOIN 발생으로 0값 행 사라짐 방지
            add_filter('posts_join', [__CLASS__, 'join_stats_meta'], 10, 2);
            add_filter('posts_orderby', [__CLASS__, 'orderby_stats_meta'], 10, 2);
        }
    }

    /**
     * 통계 정렬을 위한 LEFT JOIN (0값 포함)
     * 
     * @since 4.1.3
     */
    public static function join_stats_meta($join, $query)
    {
        global $wpdb;

        if (!$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) {
            return $join;
        }

        $orderby = $query->get('orderby');
        $meta_key = '';

        switch ($orderby) {
            case 'total_pv':
                $meta_key = 'click_count';
                break;
            case 'total_uv':
                $meta_key = 'stats_total_uv';
                break;
            case 'today_pv':
                $meta_key = 'stats_today_pv';
                break;
            case 'today_uv':
                $meta_key = 'stats_today_uv';
                break;
            default:
                return $join;
        }

        // alias: sb_stats_meta
        $join .= " LEFT JOIN {$wpdb->postmeta} AS sb_stats_meta ON ({$wpdb->posts}.ID = sb_stats_meta.post_id AND sb_stats_meta.meta_key = '{$meta_key}') ";

        return $join;
    }

    /**
     * 통계 정렬 ORDER BY 절 생성 (NULL -> 0 처리)
     * 
     * @since 4.1.3
     */
    public static function orderby_stats_meta($orderby_sql, $query)
    {
        global $wpdb;

        if (!$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) {
            return $orderby_sql;
        }

        $orderby = $query->get('orderby');
        $order = strtoupper($query->get('order')) === 'DESC' ? 'DESC' : 'ASC';
        $today_date = current_time('Y-m-d');

        switch ($orderby) {
            case 'total_pv':
            case 'total_uv':
                // 단순 숫자형
                $orderby_sql = "COALESCE(sb_stats_meta.meta_value+0, 0) {$order}";
                break;

            case 'today_pv':
            case 'today_uv':
                // 날짜 포맷 (count|date)
                $orderby_sql = "
                    (CASE 
                        WHEN sb_stats_meta.meta_value IS NULL THEN 0
                        WHEN SUBSTRING_INDEX(sb_stats_meta.meta_value, '|', -1) = '{$today_date}' THEN SUBSTRING_INDEX(sb_stats_meta.meta_value, '|', 1)+0
                        ELSE 0 
                    END) {$order}
                ";
                break;
        }

        return $orderby_sql;
    }

    /**
     * 필터 드롭다운 UI 렌더링
     *
     * @param string $post_type 현재 포스트 타입
     */
    public static function render_filter_dropdowns($post_type)
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        global $wpdb;

        // 현재 선택값 가져오기
        $current_platform = isset($_GET['sb_platform']) ? sanitize_text_field($_GET['sb_platform']) : '';
        $current_clicks = isset($_GET['sb_clicks']) ? sanitize_text_field($_GET['sb_clicks']) : '';
        $current_date_range = isset($_GET['sb_date_range']) ? sanitize_text_field($_GET['sb_date_range']) : '';

        // DB에서 사용 중인 플랫폼 목록 조회 (캐싱 적용)
        $platforms = self::get_platforms_cached();

        // 총 링크 수 계산
        $total_links = self::get_filtered_link_count($current_platform, $current_clicks, $current_date_range);

        ?>
        <div class="sb-filter-bar">
            <div class="sb-filter-header">
                <h2 class="sb-filter-title"><?php _e('링크 관리', 'sb'); ?></h2>
                <a href="#" class="button button-primary sb-add-link-btn" id="sb-open-add-link-modal">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('새 링크 추가', 'sb'); ?>
                </a>
            </div>
            <div class="sb-filter-group">
                <div class="sb-filter-item">
                    <label><?php _e('플랫폼', 'sb'); ?></label>
                    <select name="sb_platform" class="sb-admin-filter">
                        <option value=""><?php _e('모든 플랫폼', 'sb'); ?></option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo esc_attr($platform); ?>" <?php selected($current_platform, $platform); ?>>
                                <?php echo esc_html($platform); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sb-filter-item">
                    <label><?php _e('클릭수', 'sb'); ?></label>
                    <select name="sb_clicks" class="sb-admin-filter">
                        <option value=""><?php _e('모든 클릭수', 'sb'); ?></option>
                        <option value="0" <?php selected($current_clicks, '0'); ?>><?php _e('0회', 'sb'); ?></option>
                        <option value="1-100" <?php selected($current_clicks, '1-100'); ?>><?php _e('1-100회', 'sb'); ?></option>
                        <option value="100-1000" <?php selected($current_clicks, '100-1000'); ?>>
                            <?php _e('100-1,000회', 'sb'); ?>
                        </option>
                        <option value="1000+" <?php selected($current_clicks, '1000+'); ?>><?php _e('1,000회+', 'sb'); ?>
                        </option>
                    </select>
                </div>

                <div class="sb-filter-item">
                    <label><?php _e('생성일', 'sb'); ?></label>
                    <select name="sb_date_range" class="sb-admin-filter">
                        <option value=""><?php _e('전체 기간', 'sb'); ?></option>
                        <option value="today" <?php selected($current_date_range, 'today'); ?>><?php _e('오늘', 'sb'); ?></option>
                        <option value="7d" <?php selected($current_date_range, '7d'); ?>><?php _e('최근 7일', 'sb'); ?></option>
                        <option value="30d" <?php selected($current_date_range, '30d'); ?>><?php _e('최근 30일', 'sb'); ?></option>
                        <option value="90d" <?php selected($current_date_range, '90d'); ?>><?php _e('최근 90일', 'sb'); ?></option>
                        <option value="180d" <?php selected($current_date_range, '180d'); ?>><?php _e('최근 180일', 'sb'); ?></option>
                        <option value="365d" <?php selected($current_date_range, '365d'); ?>><?php _e('최근 365일', 'sb'); ?></option>
                    </select>
                </div>

                <div class="sb-filter-item sb-filter-count">
                    <span class="sb-filter-count-label"><?php _e('검색 결과', 'sb'); ?></span>
                    <span class="sb-filter-count-value"><?php echo number_format($total_links); ?></span>
                </div>

                <div class="sb-filter-item sb-filter-btn-item">
                    <button type="button" id="sb_clear_sorting" class="button button-secondary">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('정렬 해제', 'sb'); ?>
                    </button>
                </div>
            </div>

            <!-- 일괄 작업(Bulk Actions) UI -->
            <div class="sb-bulk-actions-container" id="sb-bulk-actions-container" style="display: none;">
                <div class="sb-filter-item">
                    <label><?php _e('일괄 작업', 'sb'); ?></label>
                    <select name="sb_bulk_action" id="sb_bulk_action" class="sb-admin-filter">
                        <option value=""><?php _e('작업 선택', 'sb'); ?></option>
                        <option value="sb_bulk_delete"><?php _e('선택한 링크 삭제', 'sb'); ?></option>
                        <option value="sb_bulk_update_platform"><?php _e('플랫폼 변경', 'sb'); ?></option>
                    </select>
                </div>

                <div class="sb-filter-item sb-bulk-platform-select" id="sb_bulk_platform_select" style="display: none;">
                    <label><?php _e('새 플랫폼', 'sb'); ?></label>
                    <select name="sb_bulk_platform" id="sb_bulk_platform" class="sb-admin-filter">
                        <option value=""><?php _e('플랫폼 선택', 'sb'); ?></option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo esc_attr($platform); ?>">
                                <?php echo esc_html($platform); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sb-filter-item sb-filter-btn-item">
                    <button type="submit" id="sb_bulk_apply" class="button button-primary">
                        <?php _e('적용', 'sb'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 링크 추가 모달 -->
        <div id="sb-add-link-modal" class="sb-modal sb-hidden">
            <div class="sb-modal-overlay"></div>
            <div class="sb-modal-content">
                <div class="sb-modal-header">
                    <h2><?php _e('새 링크 추가', 'sb'); ?></h2>
                    <button type="button" class="sb-modal-close" aria-label="<?php esc_attr_e('닫기', 'sb'); ?>">&times;</button>
                </div>
                <div class="sb-modal-body">
                    <form id="sb-add-link-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="sb_modal_target_url"><?php _e('타겟 URL', 'sb'); ?></label></th>
                                <td>
                                    <input type="url" id="sb_modal_target_url" name="target_url" class="large-text" required placeholder="https://example.com">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="sb_modal_slug"><?php _e('Slug (선택사항)', 'sb'); ?></label></th>
                                <td>
                                    <input type="text" id="sb_modal_slug" name="slug" class="large-text" placeholder="자동 생성됨">
                                    <p class="description">
                                        <?php _e('비워있으면 자동으로 생성됩니다.', 'sb'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="sb_modal_platform"><?php _e('플랫폼 (선택사항)', 'sb'); ?></label></th>
                                <td>
                                    <select id="sb_modal_platform" name="platform" class="large-text">
                                        <option value=""><?php _e('자동 감지', 'sb'); ?></option>
                                        <option value="Coupang"><?php _e('쿠팡', 'sb'); ?></option>
                                        <option value="Naver"><?php _e('네이버', 'sb'); ?></option>
                                        <option value="Kakao"><?php _e('카카오', 'sb'); ?></option>
                                        <option value="Etc"><?php _e('기타', 'sb'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="sb-modal-footer">
                    <button type="button" id="sb-modal-cancel" class="button button-secondary"><?php _e('취소', 'sb'); ?></button>
                    <button type="button" id="sb-modal-submit" class="button button-primary"><?php _e('생성', 'sb'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 플랫폼 목록 캐싱 (성능 최적화)
     *
     * @return array 플랫폼 목록
     */
    public static function get_platforms_cached()
    {
        global $wpdb;
        
        $cache_key = 'sb_platforms_list';
        $platforms = get_transient($cache_key);
        
        if ($platforms !== false) {
            return $platforms;
        }
        
        // DB에서 사용 중인 플랫폼 목록 조회
        $platforms = $wpdb->get_col("
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'platform' AND meta_value != ''
            ORDER BY meta_value
        ");
        
        // 1시간 캐싱
        set_transient($cache_key, $platforms, HOUR_IN_SECONDS);
        
        return $platforms;
    }

    /**
     * 필터링된 링크 수 계산
     *
     * @param string $platform 플랫폼 필터
     * @param string $clicks 클릭수 필터
     * @param string $date_range 기간 필터
     * @return int 필터링된 링크 수
     */
    public static function get_filtered_link_count($platform = '', $clicks = '', $date_range = '')
    {
        global $wpdb;
        
        $where = ["p.post_type = '" . SB_Post_Type::POST_TYPE . "'", "p.post_status = 'publish'"];
        $join = [];
        
        // 플랫폼 필터
        if (!empty($platform)) {
            $join[] = "INNER JOIN {$wpdb->postmeta} pm_platform ON (p.ID = pm_platform.post_id AND pm_platform.meta_key = 'platform')";
            $where[] = $wpdb->prepare("pm_platform.meta_value = %s", $platform);
        }
        
        // 클릭수 필터
        if (!empty($clicks)) {
            $join[] = "INNER JOIN {$wpdb->postmeta} pm_clicks ON (p.ID = pm_clicks.post_id AND pm_clicks.meta_key = 'click_count')";
            
            switch ($clicks) {
                case '0':
                    $where[] = "(pm_clicks.meta_value = '0' OR pm_clicks.meta_value IS NULL)";
                    break;
                case '1-100':
                    $where[] = "(CAST(pm_clicks.meta_value AS UNSIGNED) BETWEEN 1 AND 100)";
                    break;
                case '100-1000':
                    $where[] = "(CAST(pm_clicks.meta_value AS UNSIGNED) BETWEEN 100 AND 1000)";
                    break;
                case '1000+':
                    $where[] = "(CAST(pm_clicks.meta_value AS UNSIGNED) >= 1000)";
                    break;
            }
        }
        
        // 기간 필터
        if (!empty($date_range)) {
            switch ($date_range) {
                case 'today':
                    $where[] = "DATE(p.post_date) = CURDATE()";
                    break;
                case '7d':
                    $where[] = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case '30d':
                    $where[] = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case '90d':
                    $where[] = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                    break;
                case '180d':
                    $where[] = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
                    break;
                case '365d':
                    $where[] = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                    break;
            }
        }
        
        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
        
        if (!empty($join)) {
            $sql .= ' ' . implode(' ', $join);
        }
        
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        return (int) $wpdb->get_var($sql);
    }

    /**
     * 일괄 작업(Bulk Actions) 처리
     *
     * @since 4.2.0
     */
    public static function handle_bulk_actions()
    {
        // 링크 관리 페이지 확인
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== self::POST_TYPE) {
            return;
        }

        // 일괄 작업 확인
        if (!isset($_POST['sb_bulk_action']) || empty($_POST['sb_bulk_action'])) {
            return;
        }

        // Nonce 확인
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-posts')) {
            wp_die(__('보안 확인에 실패했습니다.', 'sb'));
        }

        // 권한 확인
        if (!current_user_can('delete_posts')) {
            wp_die(__('이 작업을 수행할 권한이 없습니다.', 'sb'));
        }

        $action = sanitize_text_field($_POST['sb_bulk_action']);
        $link_ids = isset($_POST['post']) ? array_map('intval', $_POST['post']) : [];

        if (empty($link_ids)) {
            return;
        }

        switch ($action) {
            case 'sb_bulk_delete':
                // 대량 삭제
                foreach ($link_ids as $link_id) {
                    if (current_user_can('delete_post', $link_id)) {
                        wp_delete_post($link_id, true); // 휴지통으로 이동하지 않고 완전 삭제
                    }
                }
                // P1 Performance: 링크 삭제 시 관련 캐시 무효화
                SB_Helpers::invalidate_cache_by_tags([
                    SB_Helpers::CACHE_TAG_ANALYTICS,
                    SB_Helpers::CACHE_TAG_LINKS,
                    SB_Helpers::CACHE_TAG_PLATFORMS,
                    SB_Helpers::CACHE_TAG_STATS
                ]);
                self::clear_platforms_cache();
                wp_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE . '&deleted=' . count($link_ids)));
                exit;

            case 'sb_bulk_update_platform':
                // 플랫폼 변경
                if (!isset($_POST['sb_bulk_platform'])) {
                    wp_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE . '&error=no_platform'));
                    exit;
                }

                $new_platform = sanitize_text_field($_POST['sb_bulk_platform']);
                foreach ($link_ids as $link_id) {
                    if (current_user_can('edit_post', $link_id)) {
                        update_post_meta($link_id, 'platform', $new_platform);
                    }
                }
                // P1 Performance: 플랫폼 변경 시 관련 캐시 무효화
                SB_Helpers::invalidate_cache_by_tags([
                    SB_Helpers::CACHE_TAG_ANALYTICS,
                    SB_Helpers::CACHE_TAG_PLATFORMS,
                    SB_Helpers::CACHE_TAG_STATS
                ]);
                self::clear_platforms_cache();
                wp_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE . '&updated=' . count($link_ids)));
                exit;
        }
    }

    /**
     * 캐시 초기화 (플랫폼 목록)
     */
    public static function clear_platforms_cache()
    {
        delete_transient('sb_platforms_list');
    }

    /**
     * 행 액션에 '단축 링크 열기' 버튼 추가
     * 
     * @param array   $actions 기존 액션 배열
     * @param WP_Post $post    포스트 객체
     * @return array 수정된 액션 배열
     */
    public static function add_row_actions($actions, $post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $actions;
        }

        $short_link = SB_Helpers::get_short_link_url($post->post_name);

        // '단축 링크 열기' 액션 추가 (맨 앞에 배치)
        $new_actions = [];
        $new_actions['view_shortlink'] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="sb-action-shortlink" aria-label="%s">%s</a>',
            esc_url($short_link),
            esc_attr__('단축 링크를 새 탭에서 열기', 'sb'),
            __('단축 링크 열기', 'sb')
        );

        return array_merge($new_actions, $actions);
    }
}
