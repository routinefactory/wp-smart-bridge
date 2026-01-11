<?php
/**
 * 링크 그룹 관리 클래스
 * 
 * 링크를 캠페인/폴더별로 분류하는 기능
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.23
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Groups
{
    /**
     * 그룹 테이블명
     */
    private static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'sb_link_groups';
    }

    /**
     * 그룹 생성
     * 
     * @param string $name 그룹명
     * @param string $color 색상 코드 (#hex)
     * @param string|null $description 설명
     * @return int|false 생성된 그룹 ID 또는 false
     */
    public static function create($name, $color = '#667eea', $description = null)
    {
        global $wpdb;

        SB_Database::start_transaction();

        try {
            // Sort Order 계산의 안전성을 위해 트랜잭션 내에서 실행
            $result = $wpdb->insert(
                self::get_table_name(),
                [
                    'name' => sanitize_text_field($name),
                    'color' => sanitize_hex_color($color) ?: '#667eea',
                    'description' => $description ? sanitize_text_field($description) : null,
                    'user_id' => get_current_user_id(),
                    'sort_order' => self::get_next_sort_order(),
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%d', '%d', '%s']
            );

            if ($result) {
                $insert_id = $wpdb->insert_id;
                SB_Database::commit();
                return $insert_id;
            } else {
                SB_Database::rollback();
                return false;
            }
        } catch (Exception $e) {
            SB_Database::rollback();
            return false;
        }
    }

    /**
     * 다음 정렬 순서 가져오기
     */
    private static function get_next_sort_order()
    {
        global $wpdb;
        $table = self::get_table_name();
        $max = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $table");
        return $max + 1;
    }

    /**
     * 그룹 목록 조회
     * 
     * @param int|null $user_id 사용자 ID (null이면 전체)
     * @return array 그룹 목록
     */
    public static function get_all($user_id = null)
    {
        global $wpdb;
        $table = self::get_table_name();
        $postmeta = $wpdb->postmeta;

        // 테이블 존재 여부 확인
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        $sql = "SELECT g.*, COUNT(DISTINCT pm.post_id) as link_count 
                FROM $table g
                LEFT JOIN $postmeta pm ON pm.meta_value = g.id AND pm.meta_key = 'link_group'
                WHERE 1=1";

        $params = [];

        if ($user_id) {
            $sql .= " AND g.user_id = %d";
            $params[] = $user_id;
        }

        $sql .= " GROUP BY g.id ORDER BY g.sort_order ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * 단일 그룹 조회
     * 
     * @param int $id 그룹 ID
     * @return array|null 그룹 정보
     */
    public static function get($id)
    {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * 그룹 수정
     * 
     * @param int $id 그룹 ID
     * @param array $data 수정할 데이터
     * @return bool 성공 여부
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']) ?: '#667eea';
            $format[] = '%s';
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_text_field($data['description']);
            $format[] = '%s';
        }

        if (isset($data['sort_order'])) {
            $update_data['sort_order'] = intval($data['sort_order']);
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update($table, $update_data, ['id' => $id], $format, ['%d']) !== false;
    }

    /**
     * 그룹 삭제
     * 
     * @param int $id 그룹 ID
     * @return bool 성공 여부
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::get_table_name();

        // 그룹에 속한 링크들의 그룹 해제 (고속 처리)
        $wpdb->delete($wpdb->postmeta, [
            'meta_key' => 'link_group',
            'meta_value' => $id
        ], ['%s', '%d']);

        return $wpdb->delete($table, ['id' => $id], ['%d']) !== false;
    }

    /**
     * 링크에 그룹 할당
     * 
     * @param int $link_id 링크 ID
     * @param int|null $group_id 그룹 ID (null이면 해제)
     * @return bool 성공 여부
     */
    public static function assign_link($link_id, $group_id)
    {
        if ($group_id === null) {
            return delete_post_meta($link_id, 'link_group');
        }
        return update_post_meta($link_id, 'link_group', intval($group_id));
    }

    /**
     * 링크의 그룹 조회
     * 
     * @param int $link_id 링크 ID
     * @return int|null 그룹 ID
     */
    public static function get_link_group($link_id)
    {
        $group_id = get_post_meta($link_id, 'link_group', true);
        return $group_id ? intval($group_id) : null;
    }

    /**
     * 그룹별 링크 수 조회
     * 
     * @param int $group_id 그룹 ID
     * @return int 링크 수
     */
    public static function get_link_count($group_id)
    {
        $query = new WP_Query([
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'meta_key' => 'link_group',
            'meta_value' => $group_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        return $query->found_posts;
    }

    /**
     * 그룹별 링크 목록 조회
     * 
     * @param int $group_id 그룹 ID
     * @param int $limit 개수
     * @return array 링크 목록
     */
    public static function get_links_by_group($group_id, $limit = 20)
    {
        $query = new WP_Query([
            'post_type' => SB_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'meta_key' => 'link_group',
            'meta_value' => $group_id,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return $query->posts;
    }

    /**
     * 그룹 정렬 순서 일괄 업데이트
     * 
     * @param array $order_map [group_id => new_order, ...]
     * @return bool 성공 여부
     */
    public static function update_sort_order($order_map)
    {
        global $wpdb;
        $table = self::get_table_name();

        foreach ($order_map as $group_id => $new_order) {
            $wpdb->update(
                $table,
                ['sort_order' => intval($new_order)],
                ['id' => intval($group_id)],
                ['%d'],
                ['%d']
            );
        }

        return true;
    }
}
