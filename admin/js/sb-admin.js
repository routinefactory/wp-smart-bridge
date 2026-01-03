/**
 * Smart Bridge 관리자 JavaScript
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

(function($) {
    'use strict';
    
    // DOM 준비 완료
    $(document).ready(function() {
        initDashboard();
        initSettings();
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
        $('#sb-date-range').on('change', function() {
            if ($(this).val() === 'custom') {
                $('.sb-custom-dates').show();
            } else {
                $('.sb-custom-dates').hide();
            }
        });
        
        $('#sb-apply-filter').on('click', function() {
            loadStats();
        });
    }
    
    /**
     * 트래픽 추세 차트
     */
    function initTrafficTrendChart() {
        var ctx = document.getElementById('sb-traffic-trend-chart');
        if (!ctx) return;
        
        var labels = sbChartData.dailyTrend.map(function(item) {
            return item.date.substring(5); // MM-DD
        });
        
        var data = sbChartData.dailyTrend.map(function(item) {
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
                    backgroundColor: sbChartData.clicksByHour.map(function(value, index) {
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
        
        var colors = {
            'Coupang': '#E31836',
            'AliExpress': '#E62E04',
            'Amazon': '#FF9900',
            'Temu': '#F97316',
            'Etc': '#6B7280'
        };
        
        var backgroundColors = labels.map(function(label) {
            return colors[label] || '#6B7280';
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
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                }
            },
            error: function(xhr) {
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
        $('#sb-generate-key').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('생성 중...');
            
            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_generate_api_key',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#sb-new-api-key').text(response.data.api_key);
                        $('#sb-new-secret-key').text(response.data.secret_key);
                        $('#sb-new-key-modal').show();
                        
                        // 페이지 새로고침 (목록 업데이트)
                        setTimeout(function() {
                            location.reload();
                        }, 100);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('API 키 생성에 실패했습니다.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2"></span> 새 API 키 발급');
                }
            });
        });
        
        // API 키 삭제
        $(document).on('click', '.sb-delete-key', function() {
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
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });
        
        // Secret Key 토글
        $(document).on('click', '.sb-toggle-secret', function() {
            var $row = $(this).closest('td');
            $row.find('.sb-masked').toggle();
            $row.find('.sb-revealed').toggle();
        });
        
        // 복사 버튼
        $(document).on('click', '.sb-copy-btn', function() {
            var text = $(this).data('copy');
            navigator.clipboard.writeText(text).then(function() {
                alert('클립보드에 복사되었습니다!');
            });
        });
        
        $(document).on('click', '.sb-copy-modal-key', function() {
            var target = $(this).data('target');
            var text = $('#' + target).text();
            navigator.clipboard.writeText(text).then(function() {
                alert('클립보드에 복사되었습니다!');
            });
        });
        
        // 모달 닫기
        $('.sb-close-modal').on('click', function() {
            $('#sb-new-key-modal').hide();
        });
        
        // 설정 저장
        $('#sb-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).text('저장 중...');
            
            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_save_settings',
                    nonce: sbAdmin.ajaxNonce,
                    redirect_delay: $('#sb-redirect-delay').val(),
                    default_loading_message: $('#sb-default-loading-message').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('설정이 저장되었습니다.');
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('설정 저장');
                }
            });
        });
    }
    
})(jQuery);
