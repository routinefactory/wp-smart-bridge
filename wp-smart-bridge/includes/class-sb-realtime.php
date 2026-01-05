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

        // 초기 마지막 ID 조회
        global $wpdb;
        $table = $wpdb->prefix . 'sb_analytics_logs';
        $last_id = (int) $wpdb->get_var("SELECT MAX(id) FROM $table");

        // 안전한 종료를 위한 시작 시간 기록
        $start_time = time();
        $last_heartbeat = time();
        $timeout = 25; // v3.0.0 Refined: 클라이언트 재접속 주기와 맞춤

        while (true) {
            // 1. 세션 락 검증 (Concurrency Control)
            // 다른 탭에서 새 연결이 들어오면 Transient 값이 변경됨 -> 현재 프로세스 종료
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

            // 새 클릭 확인 (Last ID보다 큰 ID)
            $new_clicks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE id > %d ORDER BY id ASC LIMIT 50",
                $last_id
            ), ARRAY_A);

            if ($new_clicks) {
                foreach ($new_clicks as $click) {
                    // 링크 정보 조회 (슬러그 등)
                    $post = get_post($click['link_id']);
                    $click['slug'] = $post ? $post->post_name : 'unknown';
                    $click['target_url'] = get_post_meta($click['link_id'], 'target_url', true);

                    // 이벤트 전송
                    self::send_event('click', $click);
                    $last_id = $click['id'];
                }
            } else {
                // Heartbeat (연결 유지용) - 15초마다 전송
                if (time() - $last_heartbeat > 15) {
                    self::send_event('heartbeat', ['time' => time()]);
                    $last_heartbeat = time();
                }
            }

            // 출력 버퍼 비우기 (중요)
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            // 클라이언트 연결 끊김 확인
            if (connection_aborted()) {
                break;
            }

            // 3. 성능 최적화: 3초 -> 5초 대기 (CPU 부하 완화)
            sleep(5);
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
