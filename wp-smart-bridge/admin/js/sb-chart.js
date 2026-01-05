/**
 * Smart Bridge Chart Module
 * Encapsulates all Chart.js configurations and rendering logic.
 * Uses CSS variables for consistent styling.
 * 
 * @package WP_Smart_Bridge
 * @since 3.0.0
 */

var SB_Chart = (function ($) {
    'use strict';

    if (typeof Chart === 'undefined') {
        console.error('SB_Chart: Chart.js library is not loaded.');
        return {};
    }

    /**
     * Get CSS Variable Value
     * @param {string} name 
     * @returns {string}
     */
    function getCssVar(name) {
        return getComputedStyle(document.body).getPropertyValue(name).trim();
    }

    /**
     * Dynamic Color Palette
     */
    function getColors() {
        return {
            primary: getCssVar('--sb-primary') || '#667eea',
            primaryAlpha: 'rgba(102, 126, 234, 0.1)', // Hard to derive alpha from hex var easily without calc
            primaryStrong: 'rgba(102, 126, 234, 0.7)',
            secondary: getCssVar('--sb-secondary') || '#764ba2',
            success: getCssVar('--sb-success') || '#22c55e',
            warning: getCssVar('--sb-warning') || '#f59e0b',
            danger: getCssVar('--sb-danger') || '#ef4444',
            info: getCssVar('--sb-info') || '#3b82f6',
            grey: '#6B7280',
            previous: '#94a3b8',
            previousAlpha: 'rgba(148, 163, 184, 0.1)'
        };
    }

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
     * Set Accessibility Attributes
     */
    function setA11y(ctx, label) {
        if (!ctx || !ctx.canvas) return;
        ctx.canvas.setAttribute('role', 'img');
        ctx.canvas.setAttribute('aria-label', label);
        // Fallback content for screen readers
        ctx.canvas.innerHTML = '<p>' + label + '</p>';
    }

    function getPlatformColor(str) {
        if (!str || str === 'Unknown' || str === 'Etc') return getColors().grey;
        var hash = 0;
        for (var i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        var h = Math.abs(hash) % 360;
        return 'hsl(' + h + ', 70%, 50%)';
    }

    function getCommonOptions(overrides) {
        return $.extend(true, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
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
        initTrafficTrend: function (data) {
            var ctx = document.getElementById('sb-traffic-trend-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_daily_trend : 'Daily Traffic Trend');

            if (instances.trafficTrend) instances.trafficTrend.destroy();
            var c = getColors();

            instances.trafficTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(function (item) { return item.date.substring(5); }),
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: data.map(function (item) { return item.clicks; }),
                        borderColor: c.primary,
                        backgroundColor: c.primaryAlpha,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6
                    }]
                },
                options: getCommonOptions()
            });
        },

        initHourly: function (data) {
            var ctx = document.getElementById('sb-hourly-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_hourly : 'Hourly Click Stats');

            if (instances.hourly) instances.hourly.destroy();

            var labels = [];
            for (var i = 0; i < 24; i++) labels.push(i + (typeof sb_i18n !== 'undefined' ? sb_i18n.hour_suffix : 'h'));

            instances.hourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
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

        initPlatform: function (data) {
            var ctx = document.getElementById('sb-platform-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_platform : 'Platform Share Chart');

            if (instances.platform) instances.platform.destroy();

            var labels = Object.keys(data);
            var values = Object.values(data);
            if (labels.length === 0) {
                labels = [typeof sb_i18n !== 'undefined' ? sb_i18n.no_data : 'No Data'];
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
                    plugins: { legend: { position: 'bottom', labels: { padding: 20 } } }
                }
            });
        },

        renderReferer: function (data) {
            var ctx = document.getElementById('sb-referer-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_referer : 'Top Referer Chart');

            if (instances.referer) instances.referer.destroy();
            var c = getColors();

            instances.referer = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(function (i) { return i.referer_domain; }),
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: data.map(function (i) { return parseInt(i.clicks); }),
                        backgroundColor: c.primaryStrong,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: { display: false } }, y: { grid: { display: false } } }
                }
            });
        },

        renderRefererGroups: function (data) {
            var ctx = document.getElementById('sb-referer-groups-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? 'Referer Groups' : 'Referer Groups');

            if (instances.refererGroups) instances.refererGroups.destroy();
            var c = getColors();

            instances.refererGroups = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Direct', 'SNS', 'Search', 'Other'],
                    datasets: [{
                        data: [data.Direct, data.SNS, data.Search, data.Other],
                        backgroundColor: [c.info, c.danger, c.success, c.warning]
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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_device : 'Device Type Stats');

            if (instances.device) instances.device.destroy();
            var c = getColors();

            instances.device = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: [c.info, c.success, c.warning]
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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_os : 'OS Statistics');

            if (instances.os) instances.os.destroy();
            var c = getColors();
            var count = Object.keys(data).length;
            var colors = [c.primary, c.secondary, c.warning, c.success, c.danger, c.info].slice(0, count);

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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_browser : 'Browser Statistics');

            if (instances.browser) instances.browser.destroy();
            var c = getColors();
            var count = Object.keys(data).length;
            var colors = [c.info, c.success, c.warning, c.primary, c.secondary, c.danger].slice(0, count);

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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_weekday : 'Weekday Click Pattern');

            if (instances.weekday) instances.weekday.destroy();
            var c = getColors();

            instances.weekday = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: Object.values(data),
                        fill: true,
                        backgroundColor: 'rgba(102, 126, 234, 0.3)',
                        borderColor: c.primary,
                        pointBackgroundColor: c.primary
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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? 'Comparison Analysis' : 'Comparison Analysis');

            if (instances.comparison) instances.comparison.destroy();
            var c = getColors();

            var currentLabels = data.current.trend.map(function (i) { return i.date.substring(5); });
            var currentData = data.current.trend.map(function (i) { return i.clicks; });
            var previousData = data.previous.trend.map(function (i) { return i.clicks; });

            instances.comparison = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: currentLabels,
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.current_period : 'Current Period',
                        data: currentData,
                        borderColor: c.primary,
                        backgroundColor: c.primaryAlpha,
                        fill: true,
                        tension: 0.3
                    }, {
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.previous_period : 'Previous Period',
                        data: previousData,
                        borderColor: c.previous,
                        backgroundColor: c.previousAlpha,
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
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.link_hourly_chart : 'Link Hourly Distribution');

            if (instances.linkHourly) instances.linkHourly.destroy();
            var c = getColors();

            var labels = [];
            for (var i = 0; i < 24; i++) labels.push(i + (typeof sb_i18n !== 'undefined' ? sb_i18n.hour_suffix : 'h'));

            instances.linkHourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: data,
                        backgroundColor: c.primaryStrong,
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
