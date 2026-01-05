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
        $short_link = SB_Helpers::get_short_link_url($post->post_title);
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

        // 타겟 URL 저장
        if (isset($_POST['sb_target_url'])) {
            $target_url = esc_url_raw($_POST['sb_target_url']);

            if (SB_Helpers::validate_url($target_url)) {
                update_post_meta($post_id, 'target_url', $target_url);

                // 플랫폼 자동 재태깅
                $platform = SB_Helpers::detect_platform($target_url);
                update_post_meta($post_id, 'platform', $platform);
            }
        }
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
        $new_columns['click_count'] = __('클릭 수', 'sb');
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

            case 'click_count':
                $count = (int) get_post_meta($post_id, 'click_count', true);
                echo '<strong>' . number_format($count) . '</strong>';
                break;
        }
    }

    /**
     * 정렬 가능한 컬럼
     */
    public static function sortable_columns($columns)
    {
        $columns['click_count'] = 'click_count';
        return $columns;
    }

    /**
     * 클릭 수 정렬을 위한 쿼리 수정
     */
    public static function handle_click_count_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'click_count') {
            $query->set('meta_key', 'click_count');
            $query->set('orderby', 'meta_value_num');
        }
    }
}
