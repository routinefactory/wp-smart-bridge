/**
 * Smart Bridge Main Admin Script
 * Orchestrates Chart and UI modules.
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.22
 */

(function ($) {
    'use strict';

    var sbAdmin = window.sbAdmin || {};

    /**
     * Initialize all Analytics
     */
    function initAnalytics() {
        if (typeof sbChartData === 'undefined') return;

        // Init Charts via Module
        SB_Chart.initTrafficTrend(sbChartData.dailyTrend);
        SB_Chart.initHourly(sbChartData.clicksByHour);
        SB_Chart.initPlatform(sbChartData.platformShare);

        // Load Async Data
        loadRefererAnalytics();
        loadDeviceAnalytics();
        loadPatternAnalytics();
    }

    /**
     * Data Loaders
     */
    function loadRefererAnalytics() {
        var params = getFilterParams();
        $.ajax({
            url: sbAdmin.restUrl + 'analytics/referers',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderReferer(response.data.top_referers);
                    SB_Chart.renderRefererGroups(response.data.referer_groups);
                }
            }
        });
    }

    function loadDeviceAnalytics() {
        var params = getFilterParams();
        $.ajax({
            url: sbAdmin.restUrl + 'analytics/devices',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderDevice(response.data.devices);
                    SB_Chart.renderOS(response.data.os);
                    SB_Chart.renderBrowser(response.data.browsers);
                }
            }
        });
    }

    function loadPatternAnalytics() {
        var params = getFilterParams();
        $.ajax({
            url: sbAdmin.restUrl + 'analytics/patterns',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderWeekday(response.data.weekday_stats);
                    renderVisitorStats(response.data.visitor_stats);
                    SB_UI.renderAnomalies(response.data.anomalies);
                }
            }
        });
    }

    /**
     * Get Filter Parameters
     */
    function getFilterParams() {
        var range = $('#sb-date-range').val();
        var platform = $('#sb-platform-filter').val();
        var params = { range: range, platform: platform };

        if (range === 'custom') {
            params.start_date = $('#sb-start-date').val();
            params.end_date = $('#sb-end-date').val();
        }
        return params;
    }

    /**
     * Visitor Stats Renderer (Simple enough to keep here or move to UI)
     */
    function renderVisitorStats(data) {
        SB_UI.setText('#sb-new-visitors', data.new_visitors.toLocaleString());
        SB_UI.setText('#sb-returning-visitors', data.returning.toLocaleString());
        SB_UI.setText('#sb-frequent-visitors', data.frequent.toLocaleString());
        SB_UI.setText('#sb-returning-rate', data.returning_rate + '%');
    }

    /**
     * Event Listeners
     */
    $(document).ready(function () {
        initAnalytics();

        // Filter events
        $('#sb-apply-filters').on('click', function () {
            var params = getFilterParams();
            // TODO: Ideally reload all data. For now just re-init initial parts if needed or reload page
            // The original code reloaded the page often for filters, or calls specific update functions.
            // For this refactor, we keep existing behavior:
            window.location.reload(); // Simplest way to refresh PHP-rendered stats
        });

        $('#sb-date-range').on('change', function () {
            if ($(this).val() === 'custom') {
                $('.sb-custom-dates').slideDown();
            } else {
                $('.sb-custom-dates').slideUp();
            }
        });

        // Modal - Link Details
        $(document).on('click', '#sb-today-links tbody tr', function () {
            var linkId = $(this).find('a[href*="post="]').attr('href'); // Safer selector logic
            if (!linkId) return;

            var match = linkId.match(/post=(\d+)/);
            if (match) openLinkDetailModal(match[1]);
        });

        // Comparison Logic
        $('#sb-toggle-comparison').on('click', function () {
            var $container = $('#sb-comparison-container');
            if ($container.is(':visible')) {
                $container.slideUp();
                $(this).text('비교 모드 활성화');
            } else {
                $container.slideDown();
                $(this).text('비교 모드 비활성화');
            }
        });

        $('#sb-load-comparison').on('click', function () {
            var params = getFilterParams();
            params.type = $('#sb-comparison-type').val(); // Add type

            $.ajax({
                url: sbAdmin.restUrl + 'analytics/comparison',
                method: 'GET',
                data: params,
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
                success: function (response) {
                    if (response.success) {
                        renderComparison(response.data);
                    }
                }
            });
        });
    });

    // Comparison Render Logic
    function renderComparison(data) {
        SB_UI.setText('#sb-current-clicks', data.current.clicks.toLocaleString());
        SB_UI.setText('#sb-previous-clicks', data.previous.clicks.toLocaleString());

        var rate = data.comparison.clicks_rate;
        var $rateEl = $('#sb-comparison-rate');
        $rateEl.text((rate >= 0 ? '+' : '') + rate + '%')
            .removeClass('positive negative')
            .addClass(rate >= 0 ? 'positive' : 'negative');

        SB_Chart.renderComparison(data);
    }

    /**
     * Link Detail Modal Logic
     */
    function openLinkDetailModal(linkId) {
        var params = getFilterParams();
        $.ajax({
            url: sbAdmin.restUrl + 'links/' + linkId + '/analytics',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
            success: function (response) {
                if (response.success) {
                    renderLinkDetailModal(response.data);
                    SB_UI.openModal('#sb-link-detail-modal');
                }
            }
        });
    }

    function renderLinkDetailModal(data) {
        // Basic Info
        SB_UI.setText('#sb-link-slug', data.link_info.slug);
        SB_UI.setText('#sb-link-platform', data.link_info.platform);
        SB_UI.setText('#sb-link-created', data.link_info.created_at.substring(0, 10));
        SB_UI.setText('#sb-link-total-clicks', data.stats.total_clicks.toLocaleString());
        SB_UI.setText('#sb-link-unique-visitors', data.stats.unique_visitors.toLocaleString());

        // Charts & Lists
        SB_Chart.renderLinkHourly(data.stats.clicks_by_hour);
        SB_UI.renderReferers(data.referers);
        SB_UI.renderDeviceBars(data.devices);
    }

    // Close Modal Event Delegation
    $(document).on('click', '.sb-modal-close, .sb-modal-overlay', function () {
        SB_UI.closeModal(this);
    });

})(jQuery);
