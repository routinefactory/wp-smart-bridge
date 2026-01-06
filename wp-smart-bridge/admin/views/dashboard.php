<?php
/**
 * 대시보드 페이지
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// -------------------------------------------------------------------------
// View Logic Moved to DB_Admin::render_dashboard()
// -------------------------------------------------------------------------

// Data is passed from Controller:
// $today_total_clicks, $today_unique_visitors
// $cumulative_total_clicks, $cumulative_unique_visitors
// $growth_rate, $active_links, $clicks_by_hour, $platform_share, $daily_trend
// $available_platforms, $top_links, $alltime_top_links
// $has_api_keys, $has_update, $update_info
?>

<div class="wrap sb-dashboard">
    <div class="sb-header-with-actions">
        <h1>
            <span class="dashicons dashicons-admin-links"></span>
            <?php _e('Smart Bridge 대시보드', 'sb'); ?>
        </h1>
        <div class="sb-header-actions">
            <button type="button" id="sb-force-check-update" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('업데이트 확인', 'sb'); ?>
            </button>
        </div>
    </div>

    <?php if (!$has_api_keys): ?>
        <!-- API 키 미발급 경고 -->
        <div class="notice notice-warning">
            <p>
                <strong><span class="dashicons dashicons-warning"></span> <?php _e('API 키가 발급되지 않았습니다.', 'sb'); ?></strong>
                <?php printf(__('EXE 프로그램을 사용하려면 먼저 %s설정 페이지%s에서 API 키를 발급받으세요.', 'sb'), '<a href="' . esc_url(admin_url('admin.php?page=smart-bridge-settings')) . '">', '</a>'); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- 필터 영역 -->
    <div class="sb-filters">
        <div class="sb-filter-group">
            <label for="sb-date-range"><?php _e('기간', 'sb'); ?></label>
            <select id="sb-date-range" class="sb-filter-select">
                <option value="today"><?php _e('오늘', 'sb'); ?></option>
                <option value="yesterday"><?php _e('어제', 'sb'); ?></option>
                <option value="7d"><?php _e('최근 7일', 'sb'); ?></option>
                <option value="30d" selected><?php _e('최근 30일', 'sb'); ?></option>
                <option value="custom"><?php _e('사용자 지정', 'sb'); ?></option>
            </select>
        </div>

        <div class="sb-filter-group sb-custom-dates sb-hidden">
            <label for="sb-start-date"><?php _e('시작일', 'sb'); ?></label>
            <input type="date" id="sb-start-date" class="sb-filter-input">
            <label for="sb-end-date"><?php _e('종료일', 'sb'); ?></label>
            <input type="date" id="sb-end-date" class="sb-filter-input">
        </div>

        <div class="sb-filter-group">
            <label for="sb-platform-filter">
                <?php _e('플랫폼', 'sb'); ?>
                <span class="sb-filter-help sb-tooltip-icon"
                    title="<?php esc_attr_e('클릭 로그 기준으로 필터링됩니다. 링크의 타겟 URL을 변경한 경우, 변경 전 클릭도 포함될 수 있습니다.', 'sb'); ?>">
                    <span class="dashicons dashicons-info"></span>
                </span>
            </label>
            <select id="sb-platform-filter" class="sb-filter-select">
                <option value=""><?php _e('전체', 'sb'); ?></option>
                <?php foreach ($available_platforms as $platform): ?>
                    <option value="<?php echo esc_attr($platform); ?>">
                        <?php echo esc_html($platform); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (empty($available_platforms)): ?>
                    <option value="" disabled><?php _e('데이터 없음', 'sb'); ?></option>
                <?php endif; ?>
            </select>
        </div>

        <button type="button" id="sb-apply-filters" class="button button-primary">
            <span class="dashicons dashicons-yes"></span>
            <?php _e('필터 적용', 'sb'); ?>
        </button>
    </div>


    <!-- 요약 카드 -->
    <div class="sb-summary-cards">
        <!-- 오늘 고유 클릭 (UV) -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-uv">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-today-unique">
                    <?php echo number_format($today_unique_visitors); ?>
                </span>
                <span class="sb-card-label"><?php _e('오늘 고유 클릭 (UV)', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('📅 Today', 'sb'); ?></span>
            </div>
        </div>

        <!-- 오늘 전체 클릭 -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-clicks">
                <span class="dashicons dashicons-visibility"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-today-total">
                    <?php echo number_format($today_total_clicks); ?>
                </span>
                <span class="sb-card-label"><?php _e('오늘 전체 클릭', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('📅 Today (중복 포함)', 'sb'); ?></span>
            </div>
        </div>

        <!-- 누적 고유 클릭 (UV) -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-uv-cumulative">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-cumulative-unique">
                    <?php echo number_format($cumulative_unique_visitors); ?>
                </span>
                <span class="sb-card-label"><?php _e('누적 고유 클릭 (UV)', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('📊 All Time', 'sb'); ?></span>
            </div>
        </div>

        <!-- 누적 전체 클릭 -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-clicks-cumulative">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-cumulative-total">
                    <?php echo number_format($cumulative_total_clicks); ?>
                </span>
                <span class="sb-card-label"><?php _e('누적 전체 클릭', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('📊 All Time (중복 포함)', 'sb'); ?></span>
            </div>
        </div>

        <!-- 전일 대비 증감률 -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-growth <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>">
                <span
                    class="dashicons dashicons-<?php echo $growth_rate >= 0 ? 'arrow-up-alt' : 'arrow-down-alt'; ?>"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>"
                    id="sb-growth-rate">
                    <?php echo ($growth_rate >= 0 ? '+' : '') . $growth_rate; ?>%
                </span>
                <span class="sb-card-label"><?php _e('전일 대비 증감률', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('📈 Growth Rate', 'sb'); ?></span>
                <?php if ($growth_rate >= 0): ?>
                    <a href="#sb-today-links"
                        class="sb-card-cta sb-cta-positive"><?php _e('🎉 오늘 효과 있는 링크 보기 →', 'sb'); ?></a>
                <?php else: ?>
                    <a href="#sb-analytics-referer"
                        class="sb-card-cta sb-cta-negative"><?php _e('📉 유입 경로 분석하기 →', 'sb'); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 활성 링크 수 -->
        <div class="sb-card" tabindex="0" role="button">
            <div class="sb-card-icon sb-icon-links">
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-active-links">
                    <?php echo number_format($active_links); ?>
                </span>
                <span class="sb-card-label"><?php _e('활성 링크 수', 'sb'); ?></span>
                <span class="sb-card-sublabel"><?php _e('🔗 Active Links', 'sb'); ?></span>
            </div>
        </div>
    </div>

    <!-- 차트 영역 -->
    <div class="sb-charts-grid">
        <!--
            v3.0.4: Multi-Period Traffic Trend Charts
            
            Displays Daily/Weekly/Monthly trends side by side for comprehensive view.
            These replaced the removed "Period Comparison" feature for cleaner UX.
            
            Data Sources:
            - Daily: $daily_trend from SB_Analytics::get_daily_trend()
            - Weekly: $weekly_trend from SB_Analytics::get_weekly_trend()
            - Monthly: $monthly_trend from SB_Analytics::get_monthly_trend()
            
            JS Renderers: sb-chart.js -> initTrafficTrend(), initWeeklyTrend(), initMonthlyTrend()
        -->

        <!-- 일간 트래픽 추세 -->
        <div class="sb-chart-box">
            <h3><?php _e('📈 일간 추세 (최근 30일)', 'sb'); ?></h3>
            <div class="sb-chart-container">
                <canvas id="sb-traffic-trend-chart"></canvas>
            </div>
        </div>

        <!-- 주간 트래픽 추세 (v3.0.4 신규) -->
        <div class="sb-chart-box">
            <h3><?php _e('📊 주간 추세 (최근 30주)', 'sb'); ?></h3>
            <div class="sb-chart-container">
                <canvas id="sb-weekly-trend-chart"></canvas>
            </div>
        </div>

        <!-- 월간 트래픽 추세 (v3.0.4 신규) -->
        <div class="sb-chart-box">
            <h3><?php _e('📅 월간 추세 (최근 30개월)', 'sb'); ?></h3>
            <div class="sb-chart-container">
                <canvas id="sb-monthly-trend-chart"></canvas>
            </div>
        </div>

        <!-- 시간대별 히트맵 -->
        <div class="sb-chart-box">
            <h3><?php _e('🕐 시간대별 클릭 분포', 'sb'); ?></h3>
            <div class="sb-chart-container">
                <canvas id="sb-hourly-chart"></canvas>
            </div>
        </div>

        <!-- 플랫폼 점유율 -->
        <div class="sb-chart-box">
            <h3><?php _e('🏪 플랫폼별 점유율', 'sb'); ?></h3>
            <div class="sb-chart-container">
                <canvas id="sb-platform-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- 📊 새로운 분석 섹션들 -->

    <!-- 유입 경로 분석 -->
    <div class="sb-analytics-section sb-collapsible" id="sb-analytics-referer">
        <h2 class="sb-section-title sb-section-toggle" data-target="sb-referer-content">
            <span class="dashicons dashicons-migrate"></span>
            <?php _e('유입 경로 분석', 'sb'); ?>
            <span class="sb-section-badge">Phase 2</span>
            <span class="sb-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        </h2>
        <div class="sb-section-content" id="sb-referer-content">
            <div class="sb-charts-grid">
                <div class="sb-chart-box">
                    <h3><?php _e('🔗 유입 경로 TOP 10', 'sb'); ?></h3>
                    <div class="sb-chart-container">
                        <canvas id="sb-referer-chart"></canvas>
                    </div>
                </div>
                <div class="sb-chart-box">
                    <h3><?php _e('📊 유입 그룹 분포', 'sb'); ?></h3>
                    <div class="sb-chart-container">
                        <canvas id="sb-referer-groups-chart"></canvas>
                    </div>
                    <div class="sb-chart-legend" id="sb-referer-groups-legend"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 디바이스/브라우저 분석 -->
    <div class="sb-analytics-section sb-collapsible">
        <h2 class="sb-section-title sb-section-toggle" data-target="sb-device-content">
            <span class="dashicons dashicons-smartphone"></span>
            <?php _e('디바이스 & 브라우저 분석', 'sb'); ?>
            <span class="sb-section-badge">Phase 3</span>
            <span class="sb-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        </h2>
        <div class="sb-section-content" id="sb-device-content">
            <!-- 핵심: 디바이스 분포 (항상 표시) -->
            <div class="sb-charts-grid">
                <div class="sb-chart-box sb-chart-wide">
                    <h3><?php _e('📱 디바이스 분포', 'sb'); ?> <span
                            class="sb-chart-essential"><?php _e('핵심 지표', 'sb'); ?></span></h3>
                    <div class="sb-chart-container">
                        <canvas id="sb-device-chart"></canvas>
                    </div>
                </div>
            </div>
            <!-- 상세: OS/브라우저 (토글로 숨김) -->
            <div class="sb-advanced-toggle">
                <button type="button" class="sb-btn-advanced" id="sb-toggle-advanced-device">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <span><?php _e('OS & 브라우저 상세 보기', 'sb'); ?></span>
                </button>
            </div>
            <div class="sb-advanced-content sb-hidden" id="sb-advanced-device-content">
                <div class="sb-charts-grid">
                    <div class="sb-chart-box">
                        <h3><?php _e('💻 OS 분포', 'sb'); ?></h3>
                        <div class="sb-chart-container">
                            <canvas id="sb-os-chart"></canvas>
                        </div>
                    </div>
                    <div class="sb-chart-box">
                        <h3><?php _e('🌐 브라우저 분포', 'sb'); ?></h3>
                        <div class="sb-chart-container">
                            <canvas id="sb-browser-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 고급 패턴 분석 -->
    <!-- v3.0.4: Removed "Advanced Pattern Analysis" section (weekday chart, visitor types, anomaly detection) - Feature deemed unnecessary by user -->

    <!-- v3.0.4: Removed "Period Comparison" section - Replaced with multi-period trend charts in the traffic section above -->

    <!-- Realtime Click Feed (v2.9.23/24) -->
    <div class="sb-analytics-section sb-realtime-section">
        <h2 class="sb-section-title">
            <span class="dashicons dashicons-rss"></span>
            <?php _e('실시간 클릭 피드', 'sb'); ?>
            <span class="sb-badge-live">LIVE</span>
            <div id="sb-realtime-status" class="sb-status-indicator connected"
                title="<?php esc_attr_e('연결됨', 'sb'); ?>"></div>
        </h2>
        <div id="sb-realtime-feed" class="sb-realtime-list">
            <!-- JS Populated -->
            <div class="sb-feed-placeholder"><?php _e('최근 클릭 데이터를 기다리는 중...', 'sb'); ?></div>
        </div>
    </div>



    <!-- 인기 링크 테이블 (오늘/누적 탭) -->
    <div class="sb-top-links">
        <div class="sb-top-links-header">
            <h3><?php _e('🔥 인기 링크 TOP 20', 'sb'); ?></h3>
            <div class="sb-top-links-tabs">
                <button type="button" class="sb-tab-btn active" data-tab="today"><?php _e('📅 오늘', 'sb'); ?></button>
                <button type="button" class="sb-tab-btn" data-tab="alltime"><?php _e('📊 누적', 'sb'); ?></button>
            </div>
        </div>

        <!-- 오늘 인기 링크 -->
        <div class="sb-top-links-panel active" id="sb-today-links">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="sb-col-id">#</th>
                        <th class="sb-col-slug">Slug</th>
                        <th class="sb-col-target"><?php _e('타겟 URL', 'sb'); ?></th>
                        <th class="sb-col-platform"><?php _e('플랫폼', 'sb'); ?></th>
                        <th class="sb-col-stats"><?php _e('오늘 클릭', 'sb'); ?></th>
                        <th class="sb-col-actions"><?php _e('액션', 'sb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($today_top_links)): ?>
                        <tr>
                            <td colspan="6" class="sb-no-data"><?php _e('오늘 클릭 데이터가 없습니다.', 'sb'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($today_top_links as $index => $link): ?>
                            <tr>
                                <td data-label="#"><?php echo $index + 1; ?></td>
                                <td data-label="Slug">
                                    <a href="<?php echo esc_url($link['short_link']); ?>" target="_blank">
                                        <?php echo esc_html($link['slug']); ?>
                                    </a>
                                </td>
                                <td data-label="<?php esc_attr_e('타겟 URL', 'sb'); ?>">
                                    <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank" class="sb-target-url"
                                        title="<?php echo esc_attr($link['target_url']); ?>">
                                        <?php echo esc_html(mb_strimwidth($link['target_url'], 0, 40, '...')); ?>
                                    </a>
                                </td>
                                <td data-label="<?php esc_attr_e('플랫폼', 'sb'); ?>">
                                    <span
                                        class="sb-platform-badge sb-platform-<?php echo esc_attr(strtolower($link['platform'])); ?>">
                                        <?php echo esc_html($link['platform']); ?>
                                    </span>
                                </td>
                                <td data-label="<?php esc_attr_e('오늘 클릭', 'sb'); ?>">
                                    <strong><?php echo number_format($link['clicks']); ?></strong>
                                </td>
                                <td data-label="<?php esc_attr_e('액션', 'sb'); ?>">
                                    <a href="<?php echo get_edit_post_link($link['id']); ?>"
                                        class="button button-small"><?php _e('수정', 'sb'); ?></a>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 누적 인기 링크 -->
        <div class="sb-top-links-panel sb-hidden" id="sb-alltime-links">
            <?php if (isset($update_info) && version_compare(SB_VERSION, $update_info['version'], '<')): ?>
                <!-- Update Notice - BEFORE table for valid HTML -->
                <div class="notice notice-info sb-notice-custom">
                    <h3 class="sb-notice-title"><?php _e('📢 새로운 버전이 출시되었습니다!', 'sb'); ?></h3>
                    <p>
                        <strong><?php _e('현재 버전:', 'sb'); ?></strong> v<?php echo esc_html(SB_VERSION); ?><br>
                        <strong><?php _e('최신 버전:', 'sb'); ?></strong>
                        v<?php echo esc_html($update_info['version']); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url($update_info['download_url']); ?>"
                            class="button button-primary sb-btn-gap">
                            <?php printf(__('📥 v%s ZIP 다운로드', 'sb'), esc_html($update_info['version'])); ?>
                        </a>
                        <button type="button" id="sb-force-check-update-2" class="button sb-btn-gap">
                            <?php _e('🔄 지금 바로 확인', 'sb'); ?>
                        </button>
                    </p>
                    <details class="sb-mt-15">
                        <summary class="sb-summary-trigger">
                            <?php _e('📖 수동 업데이트 방법 (7단계)', 'sb'); ?>
                        </summary>
                        <ol class="sb-update-steps">
                            <li><?php printf(__('위의 %s"📥 ZIP 다운로드"%s 버튼을 클릭하여 최신 버전 ZIP 파일을 다운로드합니다.', 'sb'), '<strong>', '</strong>'); ?>
                            </li>
                            <li><?php printf(__('%s플러그인 → 설치된 플러그인%s 메뉴로 이동합니다.', 'sb'), '<strong>', '</strong>'); ?></li>
                            <li><?php printf(__('%sWP Smart Bridge%s를 %s비활성화%s합니다. (데이터는 보존됩니다)', 'sb'), '<strong>', '</strong>', '<strong>', '</strong>'); ?>
                            </li>
                            <li><?php printf(__('%s삭제%s 버튼을 클릭합니다. (데이터는 보존됩니다)', 'sb'), '<strong>', '</strong>'); ?></li>
                            <li><?php printf(__('%s플러그인 → 새로 추가 → 플러그인 업로드%s를 클릭합니다.', 'sb'), '<strong>', '</strong>'); ?>
                            </li>
                            <li><?php printf(__('다운로드한 ZIP 파일을 업로드하고 %s지금 설치%s를 클릭합니다.', 'sb'), '<strong>', '</strong>'); ?>
                            </li>
                            <li><?php printf(__('설치 완료 후 %s활성화%s합니다. 모든 데이터가 그대로 유지됩니다!', 'sb'), '<strong>', '</strong>'); ?>
                            </li>
                        </ol>
                        <p class="sb-notice-warning-box">
                            ✅ <strong><?php _e('데이터 안전 보장:', 'sb'); ?></strong>
                            <?php _e('플러그인 삭제 시에도 모든 링크, 통계, API 키가 보존됩니다!', 'sb'); ?>
                        </p>
                    </details>
                </div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="sb-col-id">#</th>
                        <th class="sb-col-slug">Slug</th>
                        <th class="sb-col-target"><?php _e('타겟 URL', 'sb'); ?></th>
                        <th class="sb-col-platform"><?php _e('플랫폼', 'sb'); ?></th>
                        <th class="sb-col-stats"><?php _e('누적 클릭', 'sb'); ?></th>
                        <th class="sb-col-actions"><?php _e('액션', 'sb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alltime_top_links)): ?>
                        <tr>
                            <td colspan="6" class="sb-no-data"><?php _e('아직 데이터가 없습니다.', 'sb'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alltime_top_links as $index => $link): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php echo esc_url($link['short_link']); ?>" target="_blank">
                                        <?php echo esc_html($link['slug']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank" class="sb-target-url"
                                        title="<?php echo esc_attr($link['target_url']); ?>">
                                        <?php echo esc_html(mb_strimwidth($link['target_url'], 0, 40, '...')); ?>
                                    </a>
                                </td>
                                <td>
                                    <span
                                        class="sb-platform-badge sb-platform-<?php echo esc_attr(strtolower($link['platform'])); ?>">
                                        <?php echo esc_html($link['platform']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo number_format($link['clicks']); ?></strong></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($link['id']); ?>"
                                        class="button button-small"><?php _e('수정', 'sb'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 하단 고정 가이드 섹션 -->
    <div class="sb-quick-guide">
        <h3><?php _e('📖 빠른 시작 가이드', 'sb'); ?></h3>
        <div class="sb-guide-grid">
            <div class="sb-guide-item <?php echo $has_api_keys ? 'completed' : ''; ?>">
                <div class="sb-guide-step">1</div>
                <div class="sb-guide-content">
                    <strong><?php _e('API 키 발급', 'sb'); ?></strong>
                    <p><?php printf(__('%s설정 페이지%s에서 API Key와 Secret Key를 발급받으세요.', 'sb'), '<a href="' . admin_url('admin.php?page=smart-bridge-settings') . '">', '</a>'); ?>
                    </p>
                    <?php if ($has_api_keys): ?>
                        <span class="sb-guide-status completed"><?php _e('✅ 완료', 'sb'); ?></span>
                    <?php else: ?>
                        <span class="sb-guide-status pending"><?php _e('⏳ 대기 중', 'sb'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sb-guide-item">
                <div class="sb-guide-step">2</div>
                <div class="sb-guide-content">
                    <strong><?php _e('퍼마링크 새로고침', 'sb'); ?></strong>
                    <p><?php printf(__('%s설정 → 퍼마링크%s에서 "변경사항 저장" 버튼을 클릭해 주세요.', 'sb'), '<a href="' . admin_url('options-permalink.php') . '">', '</a>'); ?>
                    </p>
                    <span class="sb-guide-status info"><?php _e('💡 최초 1회 필수', 'sb'); ?></span>
                </div>
            </div>

            <div class="sb-guide-item">
                <div class="sb-guide-step">3</div>
                <div class="sb-guide-content">
                    <strong><?php _e('EXE 프로그램 설정', 'sb'); ?></strong>
                    <p><?php _e('발급받은 API Key와 Secret Key를 EXE 프로그램에 입력하세요.', 'sb'); ?></p>
                    <span class="sb-guide-status info"><?php _e('💻 로컬 PC', 'sb'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- 링크 상세 분석 모달 -->
<div id="sb-link-detail-modal" class="sb-modal sb-hidden">
    <div class="sb-modal-overlay"></div>
    <div class="sb-modal-content sb-modal-large">
        <div class="sb-modal-header">
            <h2 id="sb-link-modal-title"><?php _e('📊 링크 상세 분석', 'sb'); ?></h2>
            <button type="button" class="sb-modal-close" aria-label="<?php esc_attr_e('닫기', 'sb'); ?>">&times;</button>
        </div>
        <div class="sb-modal-body">
            <div class="sb-link-info-bar" id="sb-link-info-bar">
                <span><strong>Slug:</strong> <span id="sb-link-slug">-</span></span>
                <span><strong><?php _e('플랫폼:', 'sb'); ?></strong> <span id="sb-link-platform">-</span></span>
                <span><strong><?php _e('생성일:', 'sb'); ?></strong> <span id="sb-link-created">-</span></span>
            </div>

            <div class="sb-link-stats-grid">
                <div class="sb-link-stat">
                    <div class="sb-link-stat-value" id="sb-link-total-clicks">-</div>
                    <div class="sb-link-stat-label"><?php _e('총 클릭', 'sb'); ?></div>
                </div>
                <div class="sb-link-stat">
                    <div class="sb-link-stat-value" id="sb-link-unique-visitors">-</div>
                    <div class="sb-link-stat-label"><?php _e('고유 방문자', 'sb'); ?></div>
                </div>
            </div>

            <div class="sb-link-charts-grid">
                <div class="sb-chart-box">
                    <h4><?php _e('🕐 시간대별 분포', 'sb'); ?></h4>
                    <div class="sb-chart-container">
                        <canvas id="sb-link-hourly-chart"></canvas>
                    </div>
                </div>
                <div class="sb-chart-box">
                    <h4><?php _e('🔗 유입 경로', 'sb'); ?></h4>
                    <div class="sb-link-referers" id="sb-link-referers">
                        <!-- JS로 채워짐 -->
                    </div>
                </div>
            </div>

            <div class="sb-link-device-info">
                <h4><?php _e('📱 디바이스 정보', 'sb'); ?></h4>
                <div class="sb-device-bars" id="sb-link-device-bars">
                    <!-- JS로 채워짐 -->
                </div>
            </div>
        </div>
    </div>
</div>





<!-- 차트 데이터 -->
<script>
    /**
     * v3.0.4: Chart Data Injection
     * 
     * This object is consumed by sb-chart.js and sb-admin.js to render charts.
     * Data is prepared by SB_Admin_View_Model::get_dashboard_data()
     * 
     * IMPORTANT: If you add new keys here, you must:
     * 1. Add corresponding method in class-sb-analytics.php
     * 2. Add to return array in class-sb-admin-view-model.php
     * 3. Add init function in admin/js/sb-chart.js
     * 4. Call init function in admin/js/sb-admin.js initCharts()
     */
    var sbChartData = {
        dailyTrend: <?php echo json_encode($daily_trend ?: []); ?>,
        weeklyTrend: <?php echo json_encode($weekly_trend ?: []); ?>,   // v3.0.4: New
        monthlyTrend: <?php echo json_encode($monthly_trend ?: []); ?>, // v3.0.4: New
        clicksByHour: <?php echo json_encode($clicks_by_hour ?: []); ?>,
        platformShare: <?php echo json_encode($platform_share ?: []); ?>
    };
</script>


<!-- 
    ===========================================================================
    HTML Templates (Phase 9 Frontend Modernization)
    Strict separation of HTML structure from JavaScript logic.
    ===========================================================================
-->

<!-- Anomaly Item Template -->
<template id="sb-tmpl-anomaly-item">
    <div class="sb-anomaly-item">
        <span class="sb-tmpl-date"></span>
        <span class="sb-tmpl-info">
            <strong class="sb-tmpl-clicks"></strong>
            <span class="sb-tmpl-desc"></span>
        </span>
    </div>
</template>

<!-- Referer Item Template -->
<template id="sb-tmpl-referer-item">
    <div class="sb-referer-item">
        <span class="sb-tmpl-domain"></span>
        <strong class="sb-tmpl-clicks"></strong>
    </div>
</template>

<!-- Device Bar Template -->
<template id="sb-tmpl-device-bar">
    <div class="sb-device-bar">
        <div class="sb-device-bar-value sb-tmpl-value"></div>
        <div class="sb-device-bar-label sb-tmpl-label"></div>
    </div>
</template>