/**
 * Smart Bridge Chart Module
 * Encapsulates all Chart.js configurations and rendering logic.
 * Uses CSS variables for consistent styling.
 * 
 * @package WP_Smart_Bridge
 * @since 3.0.1
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
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    /**
     * Dynamic Color Palette - Premium Design System
     * Black, White, Yellow, Red with various opacities
     */
    function getColors() {
        return {
            // Primary - Black
            primary: getCssVar('--sb-black') || '#000000',
            primaryAlpha: getCssVar('--sb-black-10') || 'rgba(0, 0, 0, 0.1)',
            primaryStrong: getCssVar('--sb-black-70') || 'rgba(0, 0, 0, 0.7)',
            
            // Secondary - White
            secondary: getCssVar('--sb-white') || '#ffffff',
            secondaryAlpha: getCssVar('--sb-black-5') || 'rgba(0, 0, 0, 0.05)',
            
            // Accent - Yellow
            accent: getCssVar('--sb-yellow') || '#ffd700',
            accentAlpha: 'rgba(255, 215, 0, 0.2)',
            accentStrong: 'rgba(255, 215, 0, 0.8)',
            
            // Warning - Red
            warning: getCssVar('--sb-red') || '#dc2626',
            warningAlpha: 'rgba(220, 38, 38, 0.2)',
            warningStrong: 'rgba(220, 38, 38, 0.8)',
            
            // Grey tones
            grey: getCssVar('--sb-black-50') || '#666666',
            greyLight: getCssVar('--sb-black-20') || '#b3b3b3',
            previous: getCssVar('--sb-black-30') || '#999999',
            previousAlpha: getCssVar('--sb-black-5') || 'rgba(0, 0, 0, 0.05)'
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
        var h = Math.abs(hash) % 3;
        var colors = ['#000000', '#ffd700', '#dc2626']; // Black, Yellow, Red
        return colors[h];
    }

    function getCommonOptions(overrides) {
        return $.extend(true, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: getCssVar('--sb-text-main') || '#1e1e1e',
                    bodyColor: getCssVar('--sb-text-secondary') || '#50575e',
                    borderColor: getCssVar('--sb-border') || '#e2e4e7',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { size: 13, weight: 600 },
                    bodyFont: { size: 12 }
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
        initTrafficTrend: function (data) {
            // Data Safety: Fix 2-1
            data = data || []; // Verify Pass: Prevent null map error
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

        /**
         * v3.0.4: Weekly Traffic Trend Chart
         * 
         * PURPOSE: Displays click aggregates by week (last 30 weeks).
         * Part of the consolidated multi-period trend charts feature.
         * 
         * DATA FORMAT EXPECTED:
         * [{ week: '2025-W01', clicks: 150 }, { week: '2025-W02', clicks: 200 }, ...]
         * 
         * RELATED:
         * - Data Source: class-sb-analytics.php -> get_weekly_trend()
         * - HTML Element: dashboard.php -> canvas#sb-weekly-trend-chart
         * - Caller: sb-admin.js -> initCharts()
         * 
         * @param {Array} data Weekly trend data from PHP
         * @since 3.0.4
         */
        initWeeklyTrend: function (data) {
            data = data || [];
            var ctx = document.getElementById('sb-weekly-trend-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_weekly_trend : 'Weekly Traffic Trend');

            if (instances.weeklyTrend) instances.weeklyTrend.destroy();
            var c = getColors();

            instances.weeklyTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    // Extract week label like "W01" from "2025-W01"
                    labels: data.map(function (item) { return item.week.split('-')[1] || item.week; }),
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: data.map(function (item) { return item.clicks; }),
                        borderColor: c.accent,  // Yellow to differentiate from daily
                        backgroundColor: c.accentAlpha,
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
         * v3.0.4: Monthly Traffic Trend Chart
         * 
         * PURPOSE: Displays click aggregates by month (last 30 months).
         * Part of the consolidated multi-period trend charts feature.
         * 
         * DATA FORMAT EXPECTED:
         * [{ month: '2024-01', clicks: 1500 }, { month: '2024-02', clicks: 2000 }, ...]
         * 
         * RELATED:
         * - Data Source: class-sb-analytics.php -> get_monthly_trend()
         * - HTML Element: dashboard.php -> canvas#sb-monthly-trend-chart
         * - Caller: sb-admin.js -> initCharts()
         * 
         * @param {Array} data Monthly trend data from PHP
         * @since 3.0.4
         */
        initMonthlyTrend: function (data) {
            data = data || [];
            var ctx = document.getElementById('sb-monthly-trend-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_monthly_trend : 'Monthly Traffic Trend');

            if (instances.monthlyTrend) instances.monthlyTrend.destroy();
            var c = getColors();

            instances.monthlyTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    // Month labels like "01", "02" from "2024-01"
                    labels: data.map(function (item) { return item.month.substring(5) || item.month; }),
                    datasets: [{
                        label: typeof sb_i18n !== 'undefined' ? sb_i18n.click : 'Clicks',
                        data: data.map(function (item) { return item.clicks; }),
                        borderColor: c.warning,  // Red to differentiate
                        backgroundColor: c.warningAlpha,
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
            // Data Safety: Fix 2-1
            data = data || []; // Verify Pass: Prevent math error
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
                            return 'rgba(0, 0, 0, ' + (0.1 + intensity * 0.7) + ')';
                        }),
                        borderRadius: 4
                    }]
                },
                options: getCommonOptions()
            });
        },

        initPlatform: function (data) {
            // Data Safety: Fix 2-1
            data = data || {}; // Verify Pass: Prevent Object.keys error
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
            // Data Safety: Fix 2-1
            data = data || []; // Verify Pass: Prevent map error
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
            // Data Safety: Fix 2-1
            data = data || { Direct: 0, SNS: 0, Search: 0, Other: 0 }; // Verify Pass: Prevent undefined access
            var ctx = document.getElementById('sb-referer-groups-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? 'Referer Groups' : 'Referer Groups');

            if (instances.refererGroups) instances.refererGroups.destroy();
            var c = getColors();

            // Render Dynamic Legend
            var $legend = $('#sb-referer-groups-legend');
            if ($legend.length) {
                var legendHtml = '';
                var items = [
                    { key: 'Direct', label: typeof sb_i18n !== 'undefined' ? sb_i18n.referer_direct || 'Direct' : 'Direct', class: 'direct' },
                    { key: 'SNS', label: typeof sb_i18n !== 'undefined' ? sb_i18n.referer_sns || 'SNS' : 'SNS', class: 'sns' },
                    { key: 'Search', label: typeof sb_i18n !== 'undefined' ? sb_i18n.referer_search || 'Search' : 'Search', class: 'search' },
                    { key: 'Other', label: typeof sb_i18n !== 'undefined' ? sb_i18n.referer_other || 'Other' : 'Other', class: 'other' }
                ];

                items.forEach(function (item) {
                    legendHtml += '<span class="sb-legend-item"><span class="sb-legend-color ' + item.class + '"></span>' + item.label + '</span>';
                });
                $legend.html(legendHtml);
            }

            instances.refererGroups = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [
                        typeof sb_i18n !== 'undefined' ? sb_i18n.referer_direct || 'Direct' : 'Direct',
                        typeof sb_i18n !== 'undefined' ? sb_i18n.referer_sns || 'SNS' : 'SNS',
                        typeof sb_i18n !== 'undefined' ? sb_i18n.referer_search || 'Search' : 'Search',
                        typeof sb_i18n !== 'undefined' ? sb_i18n.referer_other || 'Other' : 'Other'
                    ],
                    datasets: [{
                        data: [data.Direct, data.SNS, data.Search, data.Other],
                        backgroundColor: [c.primary, c.warning, c.accent, c.grey]
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
            // Data Safety: Fix 2-1
            data = data || {}; // Verify Pass: Prevent Object.keys error
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
                        backgroundColor: [c.primary, c.accent, c.warning]
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
            // Data Safety: Fix 2-1
            data = data || {}; // Verify Pass: Prevent Object.keys error
            var ctx = document.getElementById('sb-os-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_os : 'OS Statistics');

            if (instances.os) instances.os.destroy();
            var c = getColors();
            var count = Object.keys(data).length;
            var colors = [c.primary, c.accent, c.warning, c.grey, c.greyLight, c.previous].slice(0, count);

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
            // Data Safety: Fix 2-1
            data = data || {}; // Verify Pass: Prevent Object.keys error
            var ctx = document.getElementById('sb-browser-chart');
            if (!ctx) return;
            setA11y(ctx, typeof sb_i18n !== 'undefined' ? sb_i18n.chart_browser : 'Browser Statistics');

            if (instances.browser) instances.browser.destroy();
            var c = getColors();
            var count = Object.keys(data).length;
            var colors = [c.primary, c.accent, c.warning, c.grey, c.greyLight, c.previous].slice(0, count);

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
            // Data Safety: Fix 2-1
            data = data || {}; // Verify Pass: Prevent Object.keys error
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
                        backgroundColor: c.primaryAlpha,
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
            // Data Safety: Fix 2-1
            // Ensure data structure exists (nested objects)
            data = data || {};
            data.current = data.current || { trend: [] };
            data.previous = data.previous || { trend: [] };
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
            // Data Safety: Fix 2-1
            data = data || []; // Verify Pass: Prevent math/map error
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

        getPlatformColor: getPlatformColor,

        /**
         * v3.0.7: Resize charts by canvas IDs
         * Used when charts in collapsed sections become visible.
         * Chart.js needs visible containers to calculate dimensions.
         * 
         * @param {Array} canvasIds Array of canvas element IDs to resize
         */
        resizeCharts: function (canvasIds) {
            if (!canvasIds || !Array.isArray(canvasIds)) return;

            // Map canvas IDs to instance keys
            var idToInstance = {
                'sb-os-chart': 'os',
                'sb-browser-chart': 'browser',
                'sb-device-chart': 'device',
                'sb-referer-chart': 'referer',
                'sb-referer-groups-chart': 'refererGroups'
            };

            canvasIds.forEach(function (canvasId) {
                var instanceKey = idToInstance[canvasId];
                if (instanceKey && instances[instanceKey]) {
                    instances[instanceKey].resize();
                }
            });
        }
    };


})(jQuery);
