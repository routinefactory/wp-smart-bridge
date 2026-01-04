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

// API 키 발급 여부 확인
$user_api_keys = SB_Database::get_user_api_keys(get_current_user_id());
$has_api_keys = !empty($user_api_keys);

// 분석 데이터 조회
$analytics = new SB_Analytics();
$date_range = SB_Helpers::get_date_range('30d');

// 일일 통계 (오늘)
$today_total_clicks = $analytics->get_today_total_clicks();
$today_unique_visitors = $analytics->get_today_unique_visitors();

// 누적 통계 (전체 기간)
$cumulative_total_clicks = $analytics->get_cumulative_total_clicks();
$cumulative_unique_visitors = $analytics->get_cumulative_unique_visitors();

// 기존 통계 (호환성 유지)
$total_clicks = $analytics->get_total_clicks($date_range['start'], $date_range['end']);
$unique_visitors = $analytics->get_unique_visitors($date_range['start'], $date_range['end']);
$growth_rate = $analytics->get_growth_rate();
$active_links = $analytics->get_active_links_count();
$clicks_by_hour = $analytics->get_clicks_by_hour($date_range['start'], $date_range['end']);
$platform_share = $analytics->get_platform_share($date_range['start'], $date_range['end']);
$daily_trend = $analytics->get_daily_trend($date_range['start'], $date_range['end']);

// 실제 데이터 기반 플랫폼 목록
$available_platforms = $analytics->get_available_platforms();

// 인기 링크 (현재 필터 적용)
$top_links = $analytics->get_top_links(
    $date_range['start'],
    $date_range['end'],
    null
);

// 전체 기간 인기 링크
$alltime_top_links = $analytics->get_all_time_top_links(20);

// 업데이트 확인 (수동 안내용)
$update_info = SB_Updater::check_github_release();
$has_update = false;
$latest_version = SB_VERSION;
$download_url = '';

if ($update_info && version_compare($update_info['version'], SB_VERSION, '>')) {
    $has_update = true;
    $latest_version = $update_info['version'];
    $download_url = $update_info['download_url'];
}
?>

<div class="wrap sb-dashboard">
    <div class="sb-header-with-actions">
        <h1>
            <span class="dashicons dashicons-admin-links"></span>
            Smart Bridge 대시보드
        </h1>
        <div class="sb-header-actions">
            <button type="button" id="sb-force-check-update" class="button">
                <span class="dashicons dashicons-update"></span>
                업데이트 확인
            </button>
        </div>
    </div>

    <?php if (!$has_api_keys): ?>
        <!-- API 키 미발급 경고 -->
        <div class="notice notice-warning">
            <p>
                <strong>⚠️ API 키가 발급되지 않았습니다.</strong>
                EXE 프로그램을 사용하려면 먼저
                <a href="<?php echo admin_url('admin.php?page=smart-bridge-settings'); ?>">설정 페이지</a>에서
                API 키를 발급받으세요.
            </p>
        </div>
    <?php endif; ?>

    <!-- 필터 영역 -->
    <div class="sb-filters">
        <div class="sb-filter-group">
            <label for="sb-date-range">기간</label>
            <select id="sb-date-range" class="sb-filter-select">
                <option value="today">오늘</option>
                <option value="yesterday">어제</option>
                <option value="7d">최근 7일</option>
                <option value="30d" selected>최근 30일</option>
                <option value="custom">사용자 지정</option>
            </select>
        </div>

        <div class="sb-filter-group sb-custom-dates" style="display: none;">
            <label for="sb-start-date">시작일</label>
            <input type="date" id="sb-start-date" class="sb-filter-input">
            <label for="sb-end-date">종료일</label>
            <input type="date" id="sb-end-date" class="sb-filter-input">
        </div>

        <div class="sb-filter-group">
            <label for="sb-platform-filter">플랫폼</label>
            <select id="sb-platform-filter" class="sb-filter-select">
                <option value="">전체</option>
                <?php foreach ($available_platforms as $platform): ?>
                    <option value="<?php echo esc_attr($platform); ?>">
                        <?php echo esc_html($platform); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (empty($available_platforms)): ?>
                    <option value="" disabled>데이터 없음</option>
                <?php endif; ?>
            </select>
            <span class="sb-filter-help" title="클릭 로그 기준으로 필터링됩니다. 링크의 타겟 URL을 변경한 경우, 변경 전 클릭도 포함될 수 있습니다.">ⓘ</span>
        </div>

        <button type="button" id="sb-apply-filters" class="button button-primary">
            <span class="dashicons dashicons-yes"></span>
            필터 적용
        </button>
    </div>


    <!-- 요약 카드 -->
    <div class="sb-summary-cards">
        <!-- 오늘 고유 클릭 (UV) -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-uv">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-today-unique">
                    <?php echo number_format($today_unique_visitors); ?>
                </span>
                <span class="sb-card-label">오늘 고유 클릭 (UV)</span>
                <span class="sb-card-sublabel">📅 Today</span>
            </div>
        </div>

        <!-- 오늘 전체 클릭 -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-clicks">
                <span class="dashicons dashicons-visibility"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-today-total">
                    <?php echo number_format($today_total_clicks); ?>
                </span>
                <span class="sb-card-label">오늘 전체 클릭</span>
                <span class="sb-card-sublabel">📅 Today (중복 포함)</span>
            </div>
        </div>

        <!-- 누적 고유 클릭 (UV) -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-uv-cumulative">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-cumulative-unique">
                    <?php echo number_format($cumulative_unique_visitors); ?>
                </span>
                <span class="sb-card-label">누적 고유 클릭 (UV)</span>
                <span class="sb-card-sublabel">📊 All Time</span>
            </div>
        </div>

        <!-- 누적 전체 클릭 -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-clicks-cumulative">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-cumulative-total">
                    <?php echo number_format($cumulative_total_clicks); ?>
                </span>
                <span class="sb-card-label">누적 전체 클릭</span>
                <span class="sb-card-sublabel">📊 All Time (중복 포함)</span>
            </div>
        </div>

        <!-- 전일 대비 증감률 -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-growth <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>">
                <span
                    class="dashicons dashicons-<?php echo $growth_rate >= 0 ? 'arrow-up-alt' : 'arrow-down-alt'; ?>"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>"
                    id="sb-growth-rate">
                    <?php echo ($growth_rate >= 0 ? '+' : '') . $growth_rate; ?>%
                </span>
                <span class="sb-card-label">전일 대비 증감률</span>
                <span class="sb-card-sublabel">📈 Growth Rate</span>
            </div>
        </div>

        <!-- 활성 링크 수 -->
        <div class="sb-card">
            <div class="sb-card-icon sb-icon-links">
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <div class="sb-card-content">
                <span class="sb-card-value" id="sb-active-links">
                    <?php echo number_format($active_links); ?>
                </span>
                <span class="sb-card-label">활성 링크 수</span>
                <span class="sb-card-sublabel">🔗 Active Links</span>
            </div>
        </div>
    </div>

    <!-- 차트 영역 -->
    <div class="sb-charts-grid">
        <!-- 트래픽 추세선 -->
        <div class="sb-chart-box sb-chart-wide">
            <h3>📈 트래픽 추세 (최근 30일)</h3>
            <div class="sb-chart-container">
                <canvas id="sb-traffic-trend-chart"></canvas>
            </div>
        </div>

        <!-- 시간대별 히트맵 -->
        <div class="sb-chart-box">
            <h3>🕐 시간대별 클릭 분포</h3>
            <div class="sb-chart-container">
                <canvas id="sb-hourly-chart"></canvas>
            </div>
        </div>

        <!-- 플랫폼 점유율 -->
        <div class="sb-chart-box">
            <h3>🏪 플랫폼별 점유율</h3>
            <div class="sb-chart-container">
                <canvas id="sb-platform-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- 📊 새로운 분석 섹션들 -->

    <!-- 유입 경로 분석 -->
    <div class="sb-analytics-section">
        <h2 class="sb-section-title">
            <span class="dashicons dashicons-migrate"></span>
            유입 경로 분석
            <span class="sb-section-badge">Phase 2</span>
        </h2>
        <div class="sb-charts-grid">
            <div class="sb-chart-box">
                <h3>🔗 유입 경로 TOP 10</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-referer-chart"></canvas>
                </div>
            </div>
            <div class="sb-chart-box">
                <h3>📊 유입 그룹 분포</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-referer-groups-chart"></canvas>
                </div>
                <div class="sb-chart-legend" id="sb-referer-groups-legend">
                    <span class="sb-legend-item"><span class="sb-legend-color"
                            style="background:#3b82f6"></span>Direct</span>
                    <span class="sb-legend-item"><span class="sb-legend-color"
                            style="background:#ec4899"></span>SNS</span>
                    <span class="sb-legend-item"><span class="sb-legend-color"
                            style="background:#22c55e"></span>Search</span>
                    <span class="sb-legend-item"><span class="sb-legend-color"
                            style="background:#f59e0b"></span>Other</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 디바이스/브라우저 분석 -->
    <div class="sb-analytics-section">
        <h2 class="sb-section-title">
            <span class="dashicons dashicons-smartphone"></span>
            디바이스 & 브라우저 분석
            <span class="sb-section-badge">Phase 3</span>
        </h2>
        <div class="sb-charts-grid sb-charts-3col">
            <div class="sb-chart-box">
                <h3>📱 디바이스 분포</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-device-chart"></canvas>
                </div>
            </div>
            <div class="sb-chart-box">
                <h3>💻 OS 분포</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-os-chart"></canvas>
                </div>
            </div>
            <div class="sb-chart-box">
                <h3>🌐 브라우저 분포</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-browser-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 고급 패턴 분석 -->
    <div class="sb-analytics-section">
        <h2 class="sb-section-title">
            <span class="dashicons dashicons-chart-area"></span>
            고급 패턴 분석
            <span class="sb-section-badge">Phase 4</span>
        </h2>
        <div class="sb-charts-grid">
            <div class="sb-chart-box">
                <h3>📅 요일별 클릭 패턴</h3>
                <div class="sb-chart-container">
                    <canvas id="sb-weekday-chart"></canvas>
                </div>
            </div>
            <div class="sb-chart-box">
                <h3>👥 방문자 유형</h3>
                <div class="sb-visitor-stats" id="sb-visitor-stats">
                    <div class="sb-stat-card">
                        <div class="sb-stat-icon new">👋</div>
                        <div class="sb-stat-value" id="sb-new-visitors">-</div>
                        <div class="sb-stat-label">신규 방문자 (1회)</div>
                    </div>
                    <div class="sb-stat-card">
                        <div class="sb-stat-icon returning">🔄</div>
                        <div class="sb-stat-value" id="sb-returning-visitors">-</div>
                        <div class="sb-stat-label">재방문자 (2-5회)</div>
                    </div>
                    <div class="sb-stat-card">
                        <div class="sb-stat-icon frequent">⭐</div>
                        <div class="sb-stat-value" id="sb-frequent-visitors">-</div>
                        <div class="sb-stat-label">단골 (6회+)</div>
                    </div>
                    <div class="sb-stat-card highlight">
                        <div class="sb-stat-icon rate">📈</div>
                        <div class="sb-stat-value" id="sb-returning-rate">-</div>
                        <div class="sb-stat-label">재방문율</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 이상치 탐지 -->
        <div class="sb-anomaly-section" id="sb-anomaly-section" style="display: none;">
            <h3>⚠️ 트래픽 이상 탐지</h3>
            <div class="sb-anomaly-content" id="sb-anomaly-content">
                <!-- JS로 채워짐 -->
            </div>
        </div>
    </div>

    <!-- 기간 비교 섹션 -->
    <div class="sb-analytics-section">
        <h2 class="sb-section-title">
            <span class="dashicons dashicons-chart-line"></span>
            기간 비교 분석
            <button type="button" id="sb-toggle-comparison" class="button button-small" style="margin-left: 10px;">
                비교 모드 활성화
            </button>
        </h2>
        <div id="sb-comparison-container" style="display: none;">
            <div class="sb-comparison-controls">
                <select id="sb-comparison-type" class="sb-filter-select">
                    <option value="wow">주간 비교 (WoW)</option>
                    <option value="mom">월간 비교 (MoM)</option>
                    <option value="custom">사용자 지정</option>
                </select>
                <button type="button" id="sb-load-comparison" class="button button-primary">비교 데이터 로드</button>
            </div>
            <div class="sb-comparison-result" id="sb-comparison-result">
                <div class="sb-comparison-stats">
                    <div class="sb-comparison-card">
                        <h4>현재 기간</h4>
                        <div class="sb-comparison-value" id="sb-current-clicks">-</div>
                        <div class="sb-comparison-label">클릭</div>
                    </div>
                    <div class="sb-comparison-card">
                        <h4>이전 기간</h4>
                        <div class="sb-comparison-value" id="sb-previous-clicks">-</div>
                        <div class="sb-comparison-label">클릭</div>
                    </div>
                    <div class="sb-comparison-card highlight">
                        <h4>변화율</h4>
                        <div class="sb-comparison-value" id="sb-comparison-rate">-</div>
                        <div class="sb-comparison-label">증감</div>
                    </div>
                </div>
                <div class="sb-chart-box sb-chart-wide">
                    <h3>📊 기간 비교 트렌드</h3>
                    <div class="sb-chart-container">
                        <canvas id="sb-comparison-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- 인기 링크 테이블 (오늘/누적 탭) -->
    <div class="sb-top-links">
        <div class="sb-top-links-header">
            <h3>🔥 인기 링크 TOP 20</h3>
            <div class="sb-top-links-tabs">
                <button type="button" class="sb-tab-btn active" data-tab="today">📅 오늘</button>
                <button type="button" class="sb-tab-btn" data-tab="alltime">📊 누적</button>
            </div>
        </div>

        <!-- 오늘 인기 링크 -->
        <div class="sb-top-links-panel active" id="sb-today-links">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Slug</th>
                        <th style="width: 35%;">타겟 URL</th>
                        <th style="width: 15%;">플랫폼</th>
                        <th style="width: 15%;">오늘 클릭</th>
                        <th style="width: 15%;">액션</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($today_top_links)): ?>
                        <tr>
                            <td colspan="6" class="sb-no-data">오늘 클릭 데이터가 없습니다.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($today_top_links as $index => $link): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php echo esc_url($link['short_link']); ?>" target="_blank">
                                        <?php echo esc_html($link['slug']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank" class="sb-target-url">
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
                                    <a href="<?php echo get_edit_post_link($link['id']); ?>" class="button button-small">수정</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 누적 인기 링크 -->
        <div class="sb-top-links-panel" id="sb-alltime-links" style="display: none;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Slug</th>
                        <th style="width: 35%;">타겟 URL</th>
                        <th style="width: 15%;">플랫폼</th>
                        <th style="width: 15%;">누적 클릭</th>
                        <th style="width: 15%;">액션</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alltime_top_links)): ?>
                        <tr>
                            <td colspan="6" class="sb-no-data">아직 데이터가 없습니다.</td>
                        </tr>
                    <?php else: ?>
                        <?php if (isset($update_info) && version_compare(SB_VERSION, $update_info['version'], '<')): ?>
                            <div class="notice notice-info"
                                style="border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;">
                                <h3 style="margin-top: 0;">📢 새로운 버전이 출시되었습니다!</h3>
                                <p>
                                    <strong>현재 버전:</strong> v<?php echo esc_html(SB_VERSION); ?><br>
                                    <strong>최신 버전:</strong> v<?php echo esc_html($update_info['version']); ?>
                                </p>
                                <p>
                                    <a href="<?php echo esc_url($update_info['download_url']); ?>" class="button button-primary"
                                        style="margin-right: 10px;">
                                        📥 v<?php echo esc_html($update_info['version']); ?> ZIP 다운로드
                                    </a>
                                    <button type="button" id="sb-force-check-update" class="button" style="margin-right: 10px;">
                                        🔄 지금 바로 확인
                                    </button>
                                    <a href="<?php echo esc_url($update_info['release_url']); ?>" class="button"
                                        target="_blank">
                                        📄 릴리스 노트
                                    </a>
                                </p>
                                <details style="margin-top: 15px;">
                                    <summary style="cursor: pointer; font-weight: 600;">📖 수동 업데이트 방법 (7단계)</summary>
                                    <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                                        <li>위의 <strong>"📥 ZIP 다운로드"</strong> 버튼을 클릭하여 최신 버전 ZIP 파일을 다운로드합니다.</li>
                                        <li><strong>플러그인 → 설치된 플러그인</strong> 메뉴로 이동합니다.</li>
                                        <li><strong>WP Smart Bridge</strong>를 <strong>비활성화</strong>합니다. (데이터는 보존됩니다)</li>
                                        <li><strong>삭제</strong> 버튼을 클릭합니다. (데이터는 보존됩니다)</li>
                                        <li><strong>플러그인 → 새로 추가 → 플러그인 업로드</strong>를 클릭합니다.</li>
                                        <li>다운로드한 ZIP 파일을 업로드하고 <strong>지금 설치</strong>를 클릭합니다.</li>
                                        <li>설치 완료 후 <strong>활성화</strong>합니다. 모든 데이터가 그대로 유지됩니다!</li>
                                    </ol>
                                    <p
                                        style="margin: 10px 0 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                                        ✅ <strong>데이터 안전 보장:</strong> 플러그인 삭제 시에도 모든 링크, 통계, API 키가 보존됩니다!
                                    </p>
                                </details>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($alltime_top_links as $index => $link): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php echo esc_url($link['short_link']); ?>" target="_blank">
                                        <?php echo esc_html($link['slug']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank" class="sb-target-url">
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
                                    <a href="<?php echo get_edit_post_link($link['id']); ?>" class="button button-small">수정</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            // 인기 링크 탭 전환
            // 인기 링크 탭 전환 (하지만 이제 필터로 통합되었으므로, 탭 기능을 숨기고 '현재 조회 기준' 하나만 보여주는 것이 좋음)
            $('.sb-top-links-tabs').hide();
            $('.sb-top-links-header h3').text('📈 인기 링크 (현재 필터 기준)');
        });
    </script>

    <style>
        .sb-top-links-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .sb-top-links-header h3 {
            margin: 0;
        }

        .sb-top-links-tabs {
            display: flex;
            gap: 5px;
        }

        .sb-tab-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 13px;
            transition: all 0.2s;
        }

        .sb-tab-btn:hover {
            background: #f0f0f0;
        }

        .sb-tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-color: #667eea;
        }
    </style>

    <!-- 하단 고정 가이드 섹션 -->
    <div class="sb-quick-guide">
        <h3>📖 빠른 시작 가이드</h3>
        <div class="sb-guide-grid">
            <div class="sb-guide-item <?php echo $has_api_keys ? 'completed' : ''; ?>">
                <div class="sb-guide-step">1</div>
                <div class="sb-guide-content">
                    <strong>API 키 발급</strong>
                    <p><a href="<?php echo admin_url('admin.php?page=smart-bridge-settings'); ?>">설정 페이지</a>에서 API Key와
                        Secret Key를 발급받으세요.</p>
                    <?php if ($has_api_keys): ?>
                        <span class="sb-guide-status completed">✅ 완료</span>
                    <?php else: ?>
                        <span class="sb-guide-status pending">⏳ 대기 중</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sb-guide-item">
                <div class="sb-guide-step">2</div>
                <div class="sb-guide-content">
                    <strong>퍼마링크 새로고침</strong>
                    <p><a href="<?php echo admin_url('options-permalink.php'); ?>">설정 → 퍼마링크</a>에서 "변경사항 저장" 버튼을 클릭해
                        주세요.</p>
                    <span class="sb-guide-status info">💡 최초 1회 필수</span>
                </div>
            </div>

            <div class="sb-guide-item">
                <div class="sb-guide-step">3</div>
                <div class="sb-guide-content">
                    <strong>EXE 프로그램 설정</strong>
                    <p>발급받은 API Key와 Secret Key를 EXE 프로그램에 입력하세요.</p>
                    <span class="sb-guide-status info">💻 로컬 PC</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .sb-quick-guide {
            background: #f8f9fa;
            border: 1px solid #e2e4e7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .sb-quick-guide h3 {
            margin: 0 0 15px;
            color: #1e1e1e;
            font-size: 16px;
        }

        .sb-guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .sb-guide-item {
            display: flex;
            gap: 12px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e4e7;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .sb-guide-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-color: #667eea;
        }

        .sb-guide-item.completed {
            border-color: #00a32a;
            background: #f0fff4;
        }

        .sb-guide-step {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .sb-guide-item.completed .sb-guide-step {
            background: #00a32a;
        }

        .sb-guide-content strong {
            display: block;
            margin-bottom: 4px;
            color: #1e1e1e;
        }

        .sb-guide-content p {
            margin: 0 0 8px;
            font-size: 13px;
            color: #646970;
            line-height: 1.4;
        }

        .sb-guide-status {
            display: inline-block;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .sb-guide-status.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .sb-guide-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .sb-guide-status.info {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* 필터 도움말 아이콘 */
        .sb-filter-help {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-left: 5px;
            background: #e5e7eb;
            border-radius: 50%;
            font-size: 11px;
            cursor: help;
            color: #6b7280;
            vertical-align: middle;
        }

        .sb-filter-help:hover {
            background: #667eea;
            color: #fff;
        }

        /* ========================================
           Phase 2-5: 새로운 분석 섹션 스타일
           ======================================== */

        .sb-analytics-section {
            margin-top: 30px;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e4e7;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .sb-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 20px;
            font-size: 18px;
            color: #1e1e1e;
        }

        .sb-section-title .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #667eea;
        }

        .sb-section-badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            font-weight: 500;
        }

        .sb-charts-3col {
            grid-template-columns: repeat(3, 1fr) !important;
        }

        @media (max-width: 1200px) {
            .sb-charts-3col {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 768px) {
            .sb-charts-3col {
                grid-template-columns: 1fr !important;
            }
        }

        /* 방문자 유형 카드 */
        .sb-visitor-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 20px 0;
        }

        @media (max-width: 900px) {
            .sb-visitor-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .sb-stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.2s;
        }

        .sb-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sb-stat-card.highlight {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }

        .sb-stat-card.highlight .sb-stat-label {
            color: rgba(255, 255, 255, 0.8);
        }

        .sb-stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .sb-stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sb-stat-label {
            font-size: 12px;
            color: #666;
        }

        /* 이상치 섹션 */
        .sb-anomaly-section {
            margin-top: 20px;
            padding: 20px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 12px;
        }

        .sb-anomaly-section h3 {
            margin: 0 0 15px;
            color: #92400e;
        }

        .sb-anomaly-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .sb-anomaly-item.spike {
            border-left: 4px solid #22c55e;
        }

        .sb-anomaly-item.drop {
            border-left: 4px solid #ef4444;
        }

        /* 기간 비교 */
        .sb-comparison-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .sb-comparison-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .sb-comparison-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .sb-comparison-card h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #666;
        }

        .sb-comparison-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e1e1e;
        }

        .sb-comparison-card.highlight .sb-comparison-value.positive {
            color: #22c55e;
        }

        .sb-comparison-card.highlight .sb-comparison-value.negative {
            color: #ef4444;
        }

        /* 차트 레전드 */
        .sb-chart-legend {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .sb-legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #666;
        }

        .sb-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        /* 링크 행 클릭 가능 */
        #sb-today-links tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        #sb-today-links tbody tr:hover {
            background: #f0f5ff !important;
        }
    </style>
</div>

<!-- 링크 상세 분석 모달 -->
<div id="sb-link-detail-modal" class="sb-modal" style="display: none;">
    <div class="sb-modal-overlay"></div>
    <div class="sb-modal-content sb-modal-large">
        <div class="sb-modal-header">
            <h2 id="sb-link-modal-title">📊 링크 상세 분석</h2>
            <button type="button" class="sb-modal-close">&times;</button>
        </div>
        <div class="sb-modal-body">
            <div class="sb-link-info-bar" id="sb-link-info-bar">
                <span><strong>Slug:</strong> <span id="sb-link-slug">-</span></span>
                <span><strong>플랫폼:</strong> <span id="sb-link-platform">-</span></span>
                <span><strong>생성일:</strong> <span id="sb-link-created">-</span></span>
            </div>

            <div class="sb-link-stats-grid">
                <div class="sb-link-stat">
                    <div class="sb-link-stat-value" id="sb-link-total-clicks">-</div>
                    <div class="sb-link-stat-label">총 클릭</div>
                </div>
                <div class="sb-link-stat">
                    <div class="sb-link-stat-value" id="sb-link-unique-visitors">-</div>
                    <div class="sb-link-stat-label">고유 방문자</div>
                </div>
            </div>

            <div class="sb-link-charts-grid">
                <div class="sb-chart-box">
                    <h4>🕐 시간대별 분포</h4>
                    <div class="sb-chart-container">
                        <canvas id="sb-link-hourly-chart"></canvas>
                    </div>
                </div>
                <div class="sb-chart-box">
                    <h4>🔗 유입 경로</h4>
                    <div class="sb-link-referers" id="sb-link-referers">
                        <!-- JS로 채워짐 -->
                    </div>
                </div>
            </div>

            <div class="sb-link-device-info">
                <h4>📱 디바이스 정보</h4>
                <div class="sb-device-bars" id="sb-link-device-bars">
                    <!-- JS로 채워짐 -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* 링크 상세 모달 스타일 */
    .sb-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sb-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }

    .sb-modal-content {
        position: relative;
        background: #fff;
        border-radius: 16px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .sb-modal-large {
        max-width: 900px;
    }

    .sb-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #e2e4e7;
    }

    .sb-modal-header h2 {
        margin: 0;
        font-size: 20px;
    }

    .sb-modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        line-height: 1;
    }

    .sb-modal-close:hover {
        color: #333;
    }

    .sb-modal-body {
        padding: 25px;
    }

    .sb-link-info-bar {
        display: flex;
        gap: 25px;
        padding: 15px 20px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .sb-link-stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }

    .sb-link-stat {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
        padding: 25px;
        border-radius: 12px;
        text-align: center;
    }

    .sb-link-stat-value {
        font-size: 36px;
        font-weight: 700;
    }

    .sb-link-stat-label {
        font-size: 14px;
        opacity: 0.9;
        margin-top: 5px;
    }

    .sb-link-charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .sb-link-charts-grid {
            grid-template-columns: 1fr;
        }
    }

    .sb-link-referers {
        max-height: 200px;
        overflow-y: auto;
    }

    .sb-referer-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 6px;
    }

    .sb-device-bars {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sb-device-bar {
        flex: 1;
        min-width: 100px;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }

    .sb-device-bar-value {
        font-size: 24px;
        font-weight: 700;
        color: #667eea;
    }

    .sb-device-bar-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
</style>

<!-- 차트 데이터 -->
<script>
    var sbChartData = {
        dailyTrend: <?php echo json_encode($daily_trend); ?>,
        clicksByHour: <?php echo json_encode($clicks_by_hour); ?>,
        platformShare: <?php echo json_encode($platform_share); ?>
    };
</script>