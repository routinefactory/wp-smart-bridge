<?php
/**
 * 실시간 클릭 피드 (SSE) 핸들러
 * 
 * Server-Sent Events를 사용하여 실시간 클릭 데이터를 클라이언트에 전송
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.23
 */

if (!defined('ABSPATH')) {
    exit;
}

class SB_Realtime
{
    /**
     * SSE 스트림 시작
     * 
     * @return void
     */
    public static function start_stream()
    {
        // 권한 체크
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized', 403);
        }

        // 헤더 설정 (SSE 표준)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx 버퍼링 방지

        // 초기 연결 메시지
        self::send_event('connected', ['message' => 'Realtime stream connected']);

        // 무한 루프 (최대 실행 시간 설정)
        // 무한 루프 (최대 실행 시간 설정)
        set_time_limit(0);
        $last_id = 0;
        $user_id = get_current_user_id();
        $lock_key = 'sb_rt_lock_' . $user_id;

        // 세션 락 설정 (현재 프로세스가 락을 점유)
        // 락은 60초마다 갱신 (루프 내부에서)
        set_transient($lock_key, time(), 60);

        // v3.0.5: Platform Filter Support
        $platform_filter = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : '';
        if ($platform_filter === 'all')
            $platform_filter = '';

        // 초기 마지막 ID 조회
        global $wpdb;
        $table = $wpdb->prefix . 'sb_analytics_logs';

        /**
         * v3.0.3 FIX: Send Recent Clicks on Connection for Better UX
         */
        $initial_sql = "SELECT * FROM $table";
        $initial_params = [];

        if ($platform_filter) {
            $initial_sql .= " WHERE platform = %s";
            $initial_params[] = $platform_filter;
        }

        $initial_sql .= " ORDER BY id DESC LIMIT 5";

        if ($platform_filter) {
            $recent_clicks = $wpdb->get_results($wpdb->prepare($initial_sql, $initial_params), ARRAY_A);
        } else {
            $recent_clicks = $wpdb->get_results($initial_sql, ARRAY_A);
        }

        if ($recent_clicks) {
            $recent_clicks = array_reverse($recent_clicks); // Send in chronological order
            foreach ($recent_clicks as $click) {
                // Enrich data
                $post = get_post($click['link_id']);
                $click['slug'] = $post ? $post->post_name : 'unknown';
                $click['target_url'] = get_post_meta($click['link_id'], 'target_url', true);

                // v3.1.3 Fix: Add UTC timestamp for correct timezone handling
                try {
                    $dt = new DateTime($click['visited_at'], wp_timezone());
                    $click['timestamp'] = $dt->getTimestamp();
                } catch (Exception $e) {
                    $click['timestamp'] = time(); // Fallback
                }

                self::send_event('click', $click);
                $last_id = $click['id'];
            }
        } else {
            // 필터링된 상태에서 데이터가 없으면 전체 MAX ID가 아니라
            // 현재 시점 이후의 데이터만 받기 위해 전체 MAX ID를 가져오거나
            // 혹은 그냥 0부터 시작하면 안되고(너무 많음), 
            // 현재 가장 최신 ID를 가져와야 함.
            $last_id = (int) $wpdb->get_var("SELECT MAX(id) FROM $table");
        }

        // 안전한 종료를 위한 시작 시간 기록
        $start_time = time();
        $last_heartbeat = time();
        $timeout = 25; // v3.0.0 Refined: 클라이언트 재접속 주기와 맞춤

        while (true) {
            // ... (Session Lock Check omitted for brevity in diff, assume it's here)

            // 2. 새 로그 조회
            $stream_sql = "SELECT * FROM $table WHERE id > %d";
            $stream_params = [$last_id];

            if ($platform_filter) {
                $stream_sql .= " AND platform = %s";
                $stream_params[] = $platform_filter;
            }

            $stream_sql .= " ORDER BY id ASC LIMIT 5"; // 한 번에 최대 5개 전송 (부하 방지)

            $new_clicks = $wpdb->get_results($wpdb->prepare($stream_sql, $stream_params), ARRAY_A);

            if ($new_clicks) {
                foreach ($new_clicks as $click) {
                    $post = get_post($click['link_id']);
                    $click['slug'] = $post ? $post->post_name : 'unknown';
                    $click['target_url'] = get_post_meta($click['link_id'], 'target_url', true);

                    // v3.1.3 Fix: Add UTC timestamp for correct timezone handling
                    try {
                        $dt = new DateTime($click['visited_at'], wp_timezone());
                        $click['timestamp'] = $dt->getTimestamp();
                    } catch (Exception $e) {
                        $click['timestamp'] = time(); // Fallback
                    }

                    self::send_event('click', $click);
                    $last_id = $click['id'];
                }

                // 데이터 전송 시 하트비트 타이머 리셋
                $last_heartbeat = time();
            } else {
                // 데이터가 없으면 하트비트 체크
                if (time() - $last_heartbeat >= 10) { // 10초마다 하트비트
                    self::send_event('heartbeat', ['time' => time()]);
                    $last_heartbeat = time();
                }
            }
            $lock_owner_time = get_transient($lock_key);
            if ($lock_owner_time && $lock_owner_time > $start_time && $lock_owner_time - $start_time > 2) {
                // 락이 갱신됨 (새로운 탭이 열림) -> 종료
                self::send_event('error', ['message' => 'Concurrent session detected. Closing stream.']);
                break;
            }

            // 락 갱신 (내가 여전히 주인임)
            set_transient($lock_key, $start_time, 60);

            // 2. 타임아웃 체크 (Shared Hosting 안전장치)
            // PHP Max Execution Time이 통상 30~60초이므로, 
            // 안전하게 50초 지점에 종료하여 좀비 프로세스 방지
            $max_execution = 50;
            if (time() - $start_time > $max_execution) {
                break;
            }

            // v3.1.3 Cleanup: Removed duplicated query logic that was causing issues
            // Logic handled above in lines 105-125
            // v3.1.3 Cleanup: Removed duplicated query logic

            // 출력 버퍼 비우기 (중요)
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            // 클라이언트 연결 끊김 확인
            if (connection_aborted()) {
                break;
            }

            // 3. 성능 최적화: 2초 대기 (반응성 개선)
            sleep(2);
        }
    }

    /**
     * SSE 이벤트 전송
     */
    private static function send_event($event, $data)
    {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
    }
}
