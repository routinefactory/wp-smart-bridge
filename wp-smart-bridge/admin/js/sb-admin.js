/**
 * Smart Bridge 관리자 JavaScript
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

(function ($) {
    'use strict';

    // DOM 준비 완료
    $(document).ready(function () {
        initDashboard();
        initSettings();
        applyPlatformBadgeColors(); // 배지 색상 적용
    });

    /**
     * 대시보드 초기화
     */
    function initDashboard() {
        if (typeof sbChartData === 'undefined') {
            return;
        }

        // 차트 초기화
        initTrafficTrendChart();
        initHourlyChart();
        initPlatformChart();

        // 필터 이벤트
        $('#sb-date-range').on('change', function () {
            if ($(this).val() === 'custom') {
                $('.sb-custom-dates').show();
            } else {
                $('.sb-custom-dates').hide();
            }
        });

        // 대시보드 필터 적용
        $('#sb-apply-filters').on('click', applyFilters);

        // 시스템 상태 점검 (퍼마링크 404 감지)
        performHealthCheck();

        // 수동 업데이트 강제 체크
        $('#sb-force-check-update').on('click', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> 확인 중...');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_force_check_update',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.has_update) {
                            // 새 버전 발견 - 페이지 새로고침하여 배너 업데이트
                            alert('✅ 새로운 버전이 발견되었습니다!\n\n' +
                                '현재: v' + response.data.current_version + '\n' +
                                '최신: v' + response.data.latest_version + '\n\n' +
                                '페이지를 새로고침합니다.');
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    } else {
                        alert('오류: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('❌ 업데이트 확인 중 오류가 발생했습니다.');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    }

    /**
     * 트래픽 추세 차트
     */
    function initTrafficTrendChart() {
        var ctx = document.getElementById('sb-traffic-trend-chart');
        if (!ctx) return;

        var labels = sbChartData.dailyTrend.map(function (item) {
            return item.date.substring(5); // MM-DD
        });

        var data = sbChartData.dailyTrend.map(function (item) {
            return item.clicks;
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '클릭 수',
                    data: data,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * 시간대별 차트
     */
    function initHourlyChart() {
        var ctx = document.getElementById('sb-hourly-chart');
        if (!ctx) return;

        var labels = [];
        for (var i = 0; i < 24; i++) {
            labels.push(i + '시');
        }

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '클릭 수',
                    data: sbChartData.clicksByHour,
                    backgroundColor: sbChartData.clicksByHour.map(function (value, index) {
                        var max = Math.max.apply(null, sbChartData.clicksByHour);
                        var intensity = max > 0 ? value / max : 0;
                        return 'rgba(102, 126, 234, ' + (0.3 + intensity * 0.7) + ')';
                    }),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * 플랫폼 점유율 차트
     */
    function initPlatformChart() {
        var ctx = document.getElementById('sb-platform-chart');
        if (!ctx) return;

        var labels = Object.keys(sbChartData.platformShare);
        var data = Object.values(sbChartData.platformShare);

        if (labels.length === 0) {
            labels = ['데이터 없음'];
            data = [1];
        }

        var backgroundColors = labels.map(function (label) {
            return getPlatformColor(label);
        });

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    }
                }
            }
        });
    }

    /**
     * 필터 적용 (대시보드 새로고침)
     */
    function applyFilters() {
        loadStats();
    }

    /**
     * 통계 데이터 로드
     */
    function loadStats() {
        var range = $('#sb-date-range').val();
        var platform = $('#sb-platform-filter').val();
        var data = {
            range: range,
            platform_filter: platform
        };

        if (range === 'custom') {
            data.start_date = $('#sb-start-date').val();
            data.end_date = $('#sb-end-date').val();
        }

        $.ajax({
            url: sbAdmin.restUrl + 'stats',
            method: 'GET',
            data: data,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    updateDashboard(response.data);
                }
            },
            error: function (xhr) {
                console.error('Stats load error:', xhr);
            }
        });
    }

    /**
     * 대시보드 업데이트
     */
    function updateDashboard(data) {
        $('#sb-total-clicks').text(data.total_clicks.toLocaleString());
        $('#sb-unique-visitors').text(data.unique_visitors.toLocaleString());

        var rate = data.growth_rate;
        var rateText = (rate >= 0 ? '+' : '') + rate + '%';
        var rateClass = rate >= 0 ? 'positive' : 'negative';

        $('#sb-growth-rate')
            .text(rateText)
            .removeClass('positive negative')
            .addClass(rateClass);

        $('#sb-active-links').text(data.active_links.toLocaleString());

        // ✅ 인기 링크 테이블 업데이트
        if (data.top_links) {
            renderTopLinksTable(data.top_links);
        }
    }

    /**
     * 인기 링크 테이블 렌더링
     */
    function renderTopLinksTable(links) {
        var $tbody = $('#sb-today-links tbody'); // ID는 'today'지만 실제로는 '현재 필터' 기준임
        $tbody.empty();

        if (links.length === 0) {
            $tbody.append('<tr><td colspan="6" class="sb-no-data">데이터가 없습니다.</td></tr>');
            return;
        }

        links.forEach(function (link, index) {
            var platformClass = 'sb-platform-' + (link.platform ? link.platform.toLowerCase().replace(/\s+/g, '-') : 'unknown');

            // 타겟 URL 말줄임 처리
            var targetUrl = link.target_url || '';
            var displayUrl = targetUrl.length > 40 ? targetUrl.substring(0, 40) + '...' : targetUrl;

            // 관리자 수정 링크 생성 (동적으로 ID 추적 불가피하므로 href에서 추출하거나 별도 처리 필요하지만, 
            // 여기서는 JS 객체에 edit_link가 없으므로 간단히 알림 처리하거나, 백엔드에서 edit_link를 보내줘야 함.
            // 일단 수정 버튼은 '링크 목록' 페이지로 안내하는 것이 안전함)

            var row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <a href="${link.short_link}" target="_blank">
                            ${link.slug}
                        </a>
                    </td>
                    <td>
                        <a href="${link.target_url}" target="_blank" class="sb-target-url">
                            ${displayUrl}
                        </a>
                    </td>
                    <td>
                        <span class="sb-platform-badge ${platformClass}">
                            ${link.platform || 'Unknown'}
                        </span>
                    </td>
                    <td><strong>${parseInt(link.clicks).toLocaleString()}</strong></td>
                    <td>
                         <a href="${sbAdmin.adminUrl}post.php?post=${link.id}&action=edit" class="button button-small">수정</a>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    /**
     * 설정 페이지 초기화
     */
    function initSettings() {
        // API 키 생성
        $('#sb-generate-key').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text('생성 중...');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_generate_api_key',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#sb-new-api-key').text(response.data.api_key);
                        $('#sb-new-secret-key').text(response.data.secret_key);
                        $('#sb-new-key-modal').show();

                        // 페이지 새로고침 (목록 업데이트)
                        setTimeout(function () {
                            location.reload();
                        }, 100);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function () {
                    alert('API 키 생성에 실패했습니다.');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2"></span> 새 API 키 발급');
                }
            });
        });

        // API 키 삭제
        $(document).on('click', '.sb-delete-key', function () {
            if (!confirm('정말 이 API 키를 삭제하시겠습니까? 이 키를 사용하는 모든 클라이언트가 작동하지 않게 됩니다.')) {
                return;
            }

            var keyId = $(this).data('key-id');
            var $row = $(this).closest('tr');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_delete_api_key',
                    nonce: sbAdmin.ajaxNonce,
                    key_id: keyId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(function () {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Secret Key 토글
        $(document).on('click', '.sb-toggle-secret', function () {
            var $row = $(this).closest('td');
            $row.find('.sb-masked').toggle();
            $row.find('.sb-revealed').toggle();
        });

        // 복사 버튼
        $(document).on('click', '.sb-copy-btn', function () {
            var text = $(this).data('copy');
            navigator.clipboard.writeText(text).then(function () {
                alert('클립보드에 복사되었습니다!');
            });
        });

        $(document).on('click', '.sb-copy-modal-key', function () {
            var target = $(this).data('target');
            var text = $('#' + target).text();
            navigator.clipboard.writeText(text).then(function () {
                alert('클립보드에 복사되었습니다!');
            });
        });

        // 모달 닫기
        $('.sb-close-modal').on('click', function () {
            $('#sb-new-key-modal').hide();
        });

        // 설정 저장
        $('#sb-settings-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).text('저장 중...');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_save_settings',
                    nonce: sbAdmin.ajaxNonce,
                    redirect_delay: $('#sb-redirect-delay').val()
                },
                success: function (response) {
                    if (response.success) {
                        alert('설정이 저장되었습니다.');
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).text('설정 저장');
                }
            });
        });

        // 템플릿 저장
        $('#sb-template-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $(this).find('button[type="submit"]');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('저장 중...');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_save_redirect_template',
                    nonce: sbAdmin.ajaxNonce,
                    template: $('#sb-redirect-template').val()
                },
                success: function (response) {
                    if (response.success) {
                        alert('템플릿이 저장되었습니다.');
                        $('#sb-template-validation').hide(); // 유효성 검사 경고 숨김
                    } else {
                        alert('오류: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('저장 중 오류가 발생했습니다.');
                },
                complete: function () {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // 템플릿 초기화
        $('#sb-reset-template').on('click', function () {
            if (!confirm('정말로 기본 템플릿으로 초기화하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_reset_redirect_template',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function (response) {
                    if (response.success) {
                        alert('템플릿이 초기화되었습니다.');
                        $('#sb-redirect-template').val(response.data.template);
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    /**
     * 플랫폼명을 기반으로 고유한 색상(HSL) 생성
     */
    function getPlatformColor(str) {
        if (!str || str === 'Unknown' || str === 'Etc') return '#6B7280';

        // 간단한 해시 함수
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }

        // HSL 색상 생성 (Hue: 0-360, Saturation: 60-80%, Lightness: 40-50%)
        // 너무 밝거나 어둡지 않게 범위를 제한하여 프리미엄 느낌 유지
        var h = Math.abs(hash) % 360;
        var s = 65 + (Math.abs(hash >> 8) % 15);
        var l = 40 + (Math.abs(hash >> 16) % 10);

        return 'hsl(' + h + ', ' + s + '%, ' + l + '%)';
    }

    /**
     * 페이지 내 모든 플랫폼 배지에 동적 색상 적용
     */
    function applyPlatformBadgeColors() {
        $('.sb-platform-badge').each(function () {
            var $badge = $(this);
            var platform = $badge.text().trim();
            if (platform) {
                var color = getPlatformColor(platform);
                $badge.css({
                    'background-color': color,
                    'color': '#fff'
                });
            }
        });
    }

    /**
     * 상태 점검 (퍼마링크 깨짐 확인)
     */
    function performHealthCheck() {
        // 대시보드 페이지만 실행
        if ($('#sb-traffic-trend-chart').length === 0) return;

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_health_check',
                nonce: sbAdmin.ajaxNonce
            },
            success: function (response) {
                if (response.success && response.data.status === 'error_404') {
                    showPermalinkWarning();
                }
            }
        });
    }

    /**
     * 퍼마링크 경고 배너 표시
     */
    function showPermalinkWarning() {
        var $container = $('.wrap.sb-dashboard');
        if ($container.length === 0) return;

        var html = `
            <div class="notice notice-error is-dismissible" style="border-left-color: #d63638; padding: 15px 20px;">
                <h3 style="margin: 0 0 10px; color: #d63638; display: flex; align-items: center;">
                    <span class="dashicons dashicons-warning" style="font-size: 24px; margin-right: 10px;"></span>
                    긴급: 단축 링크가 작동하지 않습니다!
                </h3>
                <p style="font-size: 14px; margin: 0 0 15px;">
                    현재 "페이지를 찾을 수 없음(404)" 오류가 발생하고 있습니다.<br>
                    이는 워드프레스의 고유주소(Permalink) 설정이 갱신되지 않아서 발생하는 문제입니다.
                </p>
                <p style="margin: 0;">
                    <a href="${sbAdmin.adminUrl}options-permalink.php" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                        문제 해결하기 (고유주소 설정 이동)
                    </a>
                    <span style="display: inline-block; margin-left: 10px; color: #666; font-size: 13px;">
                        👉 이동 후 아무것도 변경하지 말고 <strong>[변경사항 저장]</strong> 버튼만 한 번 눌러주세요.
                    </span>
                </p>
            </div>
        `;

        $container.prepend(html);
    }

    // ========================================
    // Phase 2-5: 새로운 분석 기능
    // ========================================

    // 차트 인스턴스 저장
    var analyticsCharts = {
        referer: null,
        refererGroups: null,
        device: null,
        os: null,
        browser: null,
        weekday: null,
        comparison: null,
        linkHourly: null
    };

    /**
     * 필터 적용 시 모든 분석 데이터 로드
     */
    function applyFilters() {
        loadStats();
        loadRefererAnalytics();
        loadDeviceAnalytics();
        loadPatternAnalytics();
    }

    /**
     * 유입 경로 분석 API 호출
     */
    function loadRefererAnalytics() {
        var params = getFilterParams();

        $.ajax({
            url: sbAdmin.restUrl + 'analytics/referers',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    renderRefererChart(response.data.top_referers);
                    renderRefererGroupsChart(response.data.referer_groups);
                }
            }
        });
    }

    /**
     * 디바이스 분석 API 호출
     */
    function loadDeviceAnalytics() {
        var params = getFilterParams();

        $.ajax({
            url: sbAdmin.restUrl + 'analytics/devices',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    renderDeviceChart(response.data.devices);
                    renderOSChart(response.data.os);
                    renderBrowserChart(response.data.browsers);
                }
            }
        });
    }

    /**
     * 패턴 분석 API 호출
     */
    function loadPatternAnalytics() {
        var params = getFilterParams();

        $.ajax({
            url: sbAdmin.restUrl + 'analytics/patterns',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    renderWeekdayChart(response.data.weekday_pattern);
                    renderVisitorStats(response.data.returning_visitors);
                    renderAnomalies(response.data.anomalies);
                }
            }
        });
    }

    /**
     * 필터 파라미터 추출
     */
    function getFilterParams() {
        var range = $('#sb-date-range').val();
        var platform = $('#sb-platform-filter').val();
        var params = {
            range: range,
            platform_filter: platform
        };

        if (range === 'custom') {
            params.start_date = $('#sb-start-date').val();
            params.end_date = $('#sb-end-date').val();
        }

        return params;
    }

    /**
     * 유입 경로 TOP 10 차트
     */
    function renderRefererChart(data) {
        var ctx = document.getElementById('sb-referer-chart');
        if (!ctx) return;

        if (analyticsCharts.referer) {
            analyticsCharts.referer.destroy();
        }

        var labels = data.map(function (item) { return item.referer_domain; });
        var clicks = data.map(function (item) { return parseInt(item.clicks); });

        analyticsCharts.referer = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '클릭',
                    data: clicks,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    /**
     * 유입 그룹 분포 차트
     */
    function renderRefererGroupsChart(data) {
        var ctx = document.getElementById('sb-referer-groups-chart');
        if (!ctx) return;

        if (analyticsCharts.refererGroups) {
            analyticsCharts.refererGroups.destroy();
        }

        analyticsCharts.refererGroups = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Direct', 'SNS', 'Search', 'Other'],
                datasets: [{
                    data: [data.Direct, data.SNS, data.Search, data.Other],
                    backgroundColor: ['#3b82f6', '#ec4899', '#22c55e', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    /**
     * 디바이스 분포 차트
     */
    function renderDeviceChart(data) {
        var ctx = document.getElementById('sb-device-chart');
        if (!ctx) return;

        if (analyticsCharts.device) {
            analyticsCharts.device.destroy();
        }

        analyticsCharts.device = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    data: Object.values(data),
                    backgroundColor: ['#3b82f6', '#22c55e', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    /**
     * OS 분포 차트
     */
    function renderOSChart(data) {
        var ctx = document.getElementById('sb-os-chart');
        if (!ctx) return;

        if (analyticsCharts.os) {
            analyticsCharts.os.destroy();
        }

        var colors = ['#667eea', '#764ba2', '#f59e0b', '#22c55e', '#ef4444', '#3b82f6'];

        analyticsCharts.os = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    data: Object.values(data),
                    backgroundColor: colors.slice(0, Object.keys(data).length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    /**
     * 브라우저 분포 차트
     */
    function renderBrowserChart(data) {
        var ctx = document.getElementById('sb-browser-chart');
        if (!ctx) return;

        if (analyticsCharts.browser) {
            analyticsCharts.browser.destroy();
        }

        var colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6', '#ef4444'];

        analyticsCharts.browser = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    data: Object.values(data),
                    backgroundColor: colors.slice(0, Object.keys(data).length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    /**
     * 요일별 패턴 차트
     */
    function renderWeekdayChart(data) {
        var ctx = document.getElementById('sb-weekday-chart');
        if (!ctx) return;

        if (analyticsCharts.weekday) {
            analyticsCharts.weekday.destroy();
        }

        analyticsCharts.weekday = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: '클릭',
                    data: Object.values(data),
                    fill: true,
                    backgroundColor: 'rgba(102, 126, 234, 0.3)',
                    borderColor: '#667eea',
                    pointBackgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    r: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /**
     * 방문자 통계 렌더링
     */
    function renderVisitorStats(data) {
        $('#sb-new-visitors').text(data.new_visitors.toLocaleString());
        $('#sb-returning-visitors').text(data.returning.toLocaleString());
        $('#sb-frequent-visitors').text(data.frequent.toLocaleString());
        $('#sb-returning-rate').text(data.returning_rate + '%');
    }

    /**
     * 이상치 렌더링
     */
    function renderAnomalies(data) {
        var $section = $('#sb-anomaly-section');
        var $content = $('#sb-anomaly-content');

        if (data.message || (data.spikes.length === 0 && data.drops.length === 0)) {
            $section.hide();
            return;
        }

        $section.show();
        $content.empty();

        data.spikes.forEach(function (item) {
            $content.append(`
                <div class="sb-anomaly-item spike">
                    <span>📈 ${item.date}</span>
                    <span><strong>${item.clicks}</strong> 클릭 (+${item.deviation}σ)</span>
                </div>
            `);
        });

        data.drops.forEach(function (item) {
            $content.append(`
                <div class="sb-anomaly-item drop">
                    <span>📉 ${item.date}</span>
                    <span><strong>${item.clicks}</strong> 클릭 (${item.deviation}σ)</span>
                </div>
            `);
        });
    }

    /**
     * 기간 비교 토글
     */
    $(document).on('click', '#sb-toggle-comparison', function () {
        var $container = $('#sb-comparison-container');
        var $btn = $(this);

        if ($container.is(':visible')) {
            $container.slideUp();
            $btn.text('비교 모드 활성화');
        } else {
            $container.slideDown();
            $btn.text('비교 모드 비활성화');
        }
    });

    /**
     * 기간 비교 데이터 로드
     */
    $(document).on('click', '#sb-load-comparison', function () {
        var type = $('#sb-comparison-type').val();
        var params = getFilterParams();

        $.ajax({
            url: sbAdmin.restUrl + 'analytics/comparison',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    renderComparison(response.data);
                }
            }
        });
    });

    /**
     * 기간 비교 렌더링
     */
    function renderComparison(data) {
        $('#sb-current-clicks').text(data.current.clicks.toLocaleString());
        $('#sb-previous-clicks').text(data.previous.clicks.toLocaleString());

        var rate = data.comparison.clicks_rate;
        var rateText = (rate >= 0 ? '+' : '') + rate + '%';
        var $rateEl = $('#sb-comparison-rate');
        $rateEl.text(rateText)
            .removeClass('positive negative')
            .addClass(rate >= 0 ? 'positive' : 'negative');

        // 비교 차트
        var ctx = document.getElementById('sb-comparison-chart');
        if (!ctx) return;

        if (analyticsCharts.comparison) {
            analyticsCharts.comparison.destroy();
        }

        var currentLabels = data.current.trend.map(function (i) { return i.date.substring(5); });
        var currentData = data.current.trend.map(function (i) { return i.clicks; });
        var previousData = data.previous.trend.map(function (i) { return i.clicks; });

        analyticsCharts.comparison = new Chart(ctx, {
            type: 'line',
            data: {
                labels: currentLabels,
                datasets: [
                    {
                        label: '현재 기간',
                        data: currentData,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: '이전 기간',
                        data: previousData,
                        borderColor: '#94a3b8',
                        backgroundColor: 'rgba(148, 163, 184, 0.1)',
                        fill: true,
                        tension: 0.3,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    }

    /**
     * 링크 상세 모달 열기
     */
    $(document).on('click', '#sb-today-links tbody tr', function () {
        var $row = $(this);
        var linkId = $row.find('a.button').attr('href');

        if (!linkId) return;

        var match = linkId.match(/post=(\d+)/);
        if (!match) return;

        var id = match[1];
        openLinkDetailModal(id);
    });

    function openLinkDetailModal(linkId) {
        var params = getFilterParams();
        params.id = linkId;

        $.ajax({
            url: sbAdmin.restUrl + 'links/' + linkId + '/analytics',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function (response) {
                if (response.success) {
                    renderLinkDetailModal(response.data);
                    $('#sb-link-detail-modal').fadeIn(200);
                }
            }
        });
    }

    function renderLinkDetailModal(data) {
        // 기본 정보
        $('#sb-link-slug').text(data.link_info.slug);
        $('#sb-link-platform').text(data.link_info.platform);
        $('#sb-link-created').text(data.link_info.created_at.substring(0, 10));

        // 통계
        $('#sb-link-total-clicks').text(data.stats.total_clicks.toLocaleString());
        $('#sb-link-unique-visitors').text(data.stats.unique_visitors.toLocaleString());

        // 시간대별 차트
        var ctx = document.getElementById('sb-link-hourly-chart');
        if (ctx) {
            if (analyticsCharts.linkHourly) {
                analyticsCharts.linkHourly.destroy();
            }

            var labels = [];
            for (var i = 0; i < 24; i++) {
                labels.push(i + '시');
            }

            analyticsCharts.linkHourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '클릭',
                        data: data.stats.clicks_by_hour,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // 유입 경로
        var $referers = $('#sb-link-referers');
        $referers.empty();
        if (data.referers.length === 0) {
            $referers.append('<div class="sb-referer-item">데이터 없음</div>');
        } else {
            data.referers.forEach(function (item) {
                $referers.append(`
                    <div class="sb-referer-item">
                        <span>${item.referer_domain}</span>
                        <strong>${parseInt(item.clicks).toLocaleString()}</strong>
                    </div>
                `);
            });
        }

        // 디바이스
        var $devices = $('#sb-link-device-bars');
        $devices.empty();
        var deviceData = data.devices.devices;
        Object.keys(deviceData).forEach(function (device) {
            $devices.append(`
                <div class="sb-device-bar">
                    <div class="sb-device-bar-value">${deviceData[device].toLocaleString()}</div>
                    <div class="sb-device-bar-label">${device}</div>
                </div>
            `);
        });
    }

    // 모달 닫기
    $(document).on('click', '.sb-modal-close, .sb-modal-overlay', function () {
        $(this).closest('.sb-modal').fadeOut(200);
    });

    // 대시보드 초기 로드 시 분석 데이터도 로드
    $(document).ready(function () {
        if (typeof sbChartData !== 'undefined') {
            setTimeout(function () {
                loadRefererAnalytics();
                loadDeviceAnalytics();
                loadPatternAnalytics();
            }, 500);
        }
    });

})(jQuery);

