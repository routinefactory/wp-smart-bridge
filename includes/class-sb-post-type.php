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
                'name' => '단축 링크',
                'singular_name' => '단축 링크',
                'menu_name' => '단축 링크',
                'add_new' => '새 링크 추가',
                'add_new_item' => '새 단축 링크 추가',
                'edit_item' => '단축 링크 수정',
                'new_item' => '새 단축 링크',
                'view_item' => '단축 링크 보기',
                'search_items' => '단축 링크 검색',
                'not_found' => '단축 링크가 없습니다',
                'not_found_in_trash' => '휴지통에 단축 링크가 없습니다',
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

        // 제목(Slug) 필드 비활성화 스크립트
        add_action('edit_form_after_title', [__CLASS__, 'disable_title_field']);

        // "새로 만들기" 버튼 숨김
        add_action('admin_head', [__CLASS__, 'hide_add_new_button']);

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
            '링크 상세 정보',
            [__CLASS__, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sb_link_stats',
            '클릭 통계',
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
        $platform = get_post_meta($post->ID, 'platform', true);
        $loading_message = get_post_meta($post->ID, 'loading_message', true);
        $short_link = SB_Helpers::get_short_link_url($post->post_title);

        ?>
        <table class="form-table">
            <tr>
                <th><label>단축 URL</label></th>
                <td>
                    <input type="text" value="<?php echo esc_url($short_link); ?>" class="large-text" readonly>
                    <p class="description">
                        <button type="button" class="button button-secondary sb-copy-link"
                            data-link="<?php echo esc_url($short_link); ?>">
                            📋 복사
                        </button>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="sb_target_url">타겟 URL</label></th>
                <td>
                    <input type="url" id="sb_target_url" name="sb_target_url" value="<?php echo esc_url($target_url); ?>"
                        class="large-text" required>
                    <p class="description">리다이렉션될 최종 목적지 URL입니다. (http:// 또는 https:// 필수)</p>
                </td>
            </tr>
            <tr>
                <th><label>플랫폼</label></th>
                <td>
                    <span class="sb-platform-badge sb-platform-<?php echo esc_attr(strtolower($platform)); ?>">
                        <?php echo esc_html($platform ?: 'Etc'); ?>
                    </span>
                    <p class="description">타겟 URL의 도메인을 기반으로 자동 분류됩니다.</p>
                </td>
            </tr>
            <tr>
                <th><label for="sb_loading_message">로딩 메시지</label></th>
                <td>
                    <textarea id="sb_loading_message" name="sb_loading_message" class="large-text"
                        rows="3"><?php echo esc_textarea($loading_message); ?></textarea>
                    <p class="description">
                        리다이렉션 중 표시될 메시지입니다.
                        허용 태그: &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;p&gt;, &lt;span&gt;
                    </p>
                </td>
            </tr>
        </table>

        <style>
            .sb-platform-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 4px;
                font-weight: 600;
                font-size: 13px;
            }

            .sb-platform-coupang {
                background: #E31836;
                color: white;
            }

            .sb-platform-aliexpress {
                background: #E62E04;
                color: white;
            }

            .sb-platform-amazon {
                background: #FF9900;
                color: black;
            }

            .sb-platform-temu {
                background: #F97316;
                color: white;
            }

            .sb-platform-etc {
                background: #6B7280;
                color: white;
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                $('.sb-copy-link').on('click', function () {
                    var link = $(this).data('link');
                    navigator.clipboard.writeText(link).then(function () {
                        alert('링크가 클립보드에 복사되었습니다!');
                    });
                });
            });
        </script>
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
        <div class="sb-stats-box">
            <div class="sb-stat-item">
                <span class="sb-stat-label">총 클릭 수</span>
                <span class="sb-stat-value">
                    <?php echo number_format($click_count); ?>
                </span>
            </div>
            <div class="sb-stat-item">
                <span class="sb-stat-label">생성일</span>
                <span class="sb-stat-value">
                    <?php echo esc_html($created); ?>
                </span>
            </div>
        </div>

        <style>
            .sb-stats-box {
                padding: 10px 0;
            }

            .sb-stat-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .sb-stat-item:last-child {
                border-bottom: none;
            }

            .sb-stat-label {
                color: #666;
            }

            .sb-stat-value {
                font-weight: 600;
                color: #1e40af;
            }
        </style>
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

        // 로딩 메시지 저장
        if (isset($_POST['sb_loading_message'])) {
            $loading_message = SB_Security::sanitize_loading_message($_POST['sb_loading_message']);
            update_post_meta($post_id, 'loading_message', $loading_message);
        }
    }

    /**
     * 제목 필드 비활성화
     */
    public static function disable_title_field($post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        ?>
        <script>
            jQuery(document).ready(function ($) {
                // 제목(Slug) 필드 비활성화
                $('#title').prop('disabled', true).prop('readonly', true);
                $('#title-prompt-text').text('단축 주소는 변경할 수 없습니다');

                // 안내 메시지 추가
                $('#title').after('<p class="description" style="color: #d63638; margin-top: 5px;">⚠️ 단축 주소는 생성 후 변경할 수 없습니다. 링크 무결성을 위해 영구적으로 고정됩니다.</p>');
            });
        </script>
        <?php
    }

    /**
     * "새로 만들기" 버튼 숨김
     */
    public static function hide_add_new_button()
    {
        global $typenow;

        if ($typenow === self::POST_TYPE) {
            ?>
            <style>
                .page-title-action,
                #favorite-actions,
                .add-new-h2,
                .wp-heading-inline+.page-title-action {
                    display: none !important;
                }
            </style>
            <?php
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
        $new_columns['target_url'] = '타겟 URL';
        $new_columns['platform'] = '플랫폼';
        $new_columns['click_count'] = '클릭 수';
        $new_columns['date'] = '생성일';

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
