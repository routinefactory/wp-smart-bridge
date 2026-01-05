/**
 * Smart Bridge Chart Module
 * Encapsulates all Chart.js configurations and rendering logic.
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.22
 */

var SB_Chart = (function ($) {
    'use strict';

    // v2.9.22 Dependency Check
    if (typeof Chart === 'undefined') {
        console.error('SB_Chart: Chart.js library is not loaded.');
        return {};
    }

    // v2.9.22 Configurable Colors
    var COLORS = {
        primary: '#667eea',
        primaryAlpha: 'rgba(102, 126, 234, 0.1)',
        primaryStrong: 'rgba(102, 126, 234, 0.7)',
        secondary: '#764ba2',
        success: '#22c55e',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#3b82f6',
        pink: '#ec4899',
        purple: '#8b5cf6',
        grey: '#6B7280',
        previous: '#94a3b8',
        previousAlpha: 'rgba(148, 163, 184, 0.1)'
    };

    var instances = {
        trafficTrend: null,
        hourly: null,
        platform: null,
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
     * Get platform specific color from string hash
     */
    function getPlatformColor(str) {
        if (!str || str === 'Unknown' || str === 'Etc') return COLORS.grey;

        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }

        var h = Math.abs(hash) % 360;
        var s = 65 + (Math.abs(hash >> 8) % 15);
        var l = 40 + (Math.abs(hash >> 16) % 10);

        return 'hsl(' + h + ', ' + s + '%, ' + l + '%)';
    }

    /**
     * Common Chart Options
     */
    function getCommonOptions(overrides) {
        return $.extend(true, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                }
            }
        }, overrides || {});
    }

    return {
        /**
         * Initialize Traffic Trend Chart
         */
        initTrafficTrend: function (data) {
            var ctx = document.getElementById('sb-traffic-trend-chart');
            if (!ctx) return;

            if (instances.trafficTrend) instances.trafficTrend.destroy();

            var labels = data.map(function (item) { return item.date.substring(5); });
            var clicks = data.map(function (item) { return item.clicks; });

            instances.trafficTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '클릭 수',
                        data: clicks,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6
                    }]
                },
                options: getCommonOptions()
            });
        },

        /**
         * Initialize Hourly Chart
         */
        initHourly: function (data) {
            var ctx = document.getElementById('sb-hourly-chart');
            if (!ctx) return;

            if (instances.hourly) instances.hourly.destroy();

            var labels = [];
            for (var i = 0; i < 24; i++) labels.push(i + '시');

            instances.hourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '클릭 수',
                        data: data,
                        backgroundColor: data.map(function (value) {
                            var max = Math.max.apply(null, data);
                            var intensity = max > 0 ? value / max : 0;
                            return 'rgba(102, 126, 234, ' + (0.3 + intensity * 0.7) + ')';
                        }),
                        borderRadius: 4
                    }]
                },
                options: getCommonOptions()
            });
        },

        /**
         * Initialize Platform Chart
         */
        initPlatform: function (data) {
            var ctx = document.getElementById('sb-platform-chart');
            if (!ctx) return;

            if (instances.platform) instances.platform.destroy();

            var labels = Object.keys(data);
            var values = Object.values(data);

            if (labels.length === 0) {
                labels = ['데이터 없음'];
                values = [1];
            }

            instances.platform = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: labels.map(getPlatformColor),
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
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        },

        renderReferer: function (data) {
            var ctx = document.getElementById('sb-referer-chart');
            if (!ctx) return;
            if (instances.referer) instances.referer.destroy();

            instances.referer = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(function (i) { return i.referer_domain; }),
                    datasets: [{
                        label: '클릭',
                        data: data.map(function (i) { return parseInt(i.clicks); }),
                        backgroundColor: COLORS.primaryStrong,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { grid: { display: false } }
                    }
                }
            });
        },

        renderRefererGroups: function (data) {
            var ctx = document.getElementById('sb-referer-groups-chart');
            if (!ctx) return;
            if (instances.refererGroups) instances.refererGroups.destroy();

            instances.refererGroups = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Direct', 'SNS', 'Search', 'Other'],
                    datasets: [{
                        data: [data.Direct, data.SNS, data.Search, data.Other],
                        backgroundColor: [COLORS.info, COLORS.pink, COLORS.success, COLORS.warning]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        },

        renderDevice: function (data) {
            var ctx = document.getElementById('sb-device-chart');
            if (!ctx) return;
            if (instances.device) instances.device.destroy();

            instances.device = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: [COLORS.info, COLORS.success, COLORS.warning]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderOS: function (data) {
            var ctx = document.getElementById('sb-os-chart');
            if (!ctx) return;
            if (instances.os) instances.os.destroy();

            var count = Object.keys(data).length;
            var colors = [COLORS.primary, COLORS.secondary, COLORS.warning, COLORS.success, COLORS.danger, COLORS.info].slice(0, count);

            instances.os = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderBrowser: function (data) {
            var ctx = document.getElementById('sb-browser-chart');
            if (!ctx) return;
            if (instances.browser) instances.browser.destroy();

            var count = Object.keys(data).length;
            var colors = [COLORS.info, COLORS.success, COLORS.warning, COLORS.pink, COLORS.purple, COLORS.danger].slice(0, count);

            instances.browser = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderWeekday: function (data) {
            var ctx = document.getElementById('sb-weekday-chart');
            if (!ctx) return;
            if (instances.weekday) instances.weekday.destroy();

            instances.weekday = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        label: '클릭',
                        data: Object.values(data),
                        fill: true,
                        backgroundColor: 'rgba(102, 126, 234, 0.3)',
                        borderColor: COLORS.primary,
                        pointBackgroundColor: COLORS.primary
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { r: { beginAtZero: true } }
                }
            });
        },

        renderComparison: function (data) {
            var ctx = document.getElementById('sb-comparison-chart');
            if (!ctx) return;
            if (instances.comparison) instances.comparison.destroy();

            var currentLabels = data.current.trend.map(function (i) { return i.date.substring(5); });
            var currentData = data.current.trend.map(function (i) { return i.clicks; });
            var previousData = data.previous.trend.map(function (i) { return i.clicks; });

            instances.comparison = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: currentLabels,
                    datasets: [{
                        label: '현재 기간',
                        data: currentData,
                        borderColor: COLORS.primary,
                        backgroundColor: COLORS.primaryAlpha,
                        fill: true,
                        tension: 0.3
                    }, {
                        label: '이전 기간',
                        data: previousData,
                        borderColor: COLORS.previous,
                        backgroundColor: COLORS.previousAlpha,
                        fill: true,
                        tension: 0.3,
                        borderDash: [5, 5]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } }
                }
            });
        },

        renderLinkHourly: function (data) {
            var ctx = document.getElementById('sb-link-hourly-chart');
            if (!ctx) return;
            if (instances.linkHourly) instances.linkHourly.destroy();

            var labels = [];
            for (var i = 0; i < 24; i++) labels.push(i + '시');

            instances.linkHourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '클릭',
                        data: data,
                        backgroundColor: COLORS.primaryStrong,
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
        },

        getPlatformColor: getPlatformColor
    };

})(jQuery);
