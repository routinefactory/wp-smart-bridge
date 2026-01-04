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

})(jQuery);
