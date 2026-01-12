/**
 * Smart Bridge Dashboard Logic
 * Extracted from dashboard.php
 *
 * @package WP_Smart_Bridge
 * @since 3.0.1
 *
 * 차트 범위 동적 조정:
 * - 주간 차트 범위는 기간 필터에 따라 동적으로 조정됩니다.
 * - 오늘 + 최근 7일: 최근 2주
 * - 최근 30일: 최근 8주
 * - 최근 3개월: 최근 12주
 * - 최근 6개월: 최근 24주
 * - 최근 12개월: 최근 52주
 * - 이 로직은 class-sb-admin-ajax.php의 get_weekly_range_by_filter() 메서드에서 처리됩니다.
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // ========================================
        // 1. UI Interaction Handlers
        // ========================================

        // Popular Links Tab Switching
        // Note: Currently hidden by CSS as we moved to a single filter view, but logic preserved.
        $('.sb-top-links-tabs').hide();
        $('.sb-top-links-header h3').text(typeof sb_i18n !== 'undefined' ? sb_i18n.top_links_title : 'Top Links');

        // Collapsible Section Toggle
        $('.sb-section-toggle').on('click', function () {
            var targetId = $(this).data('target');
            var $content = $('#' + targetId);
            var $toggle = $(this);

            if ($content.hasClass('collapsed')) {
                $content.removeClass('collapsed');
                $toggle.removeClass('collapsed');

                // v3.0.7: Trigger Chart.js resize after CSS transition completes
                // Chart.js needs visible container to calculate dimensions
                setTimeout(function () {
                    // Map target IDs to relevant canvas IDs
                    var targetToCharts = {
                        'sb-referer-content': ['sb-referer-chart', 'sb-referer-groups-chart'],
                        'sb-device-content': ['sb-device-chart']
                    };

                    var chartIds = targetToCharts[targetId];
                    if (chartIds && typeof SB_Chart !== 'undefined' && typeof SB_Chart.resizeCharts === 'function') {
                        SB_Chart.resizeCharts(chartIds);
                    } else if (chartIds) {
                        // Fallback: Trigger window resize
                        $(window).trigger('resize');
                    }
                }, 350); // Slightly longer than CSS transition (300ms)
            } else {
                $content.addClass('collapsed');
                $toggle.addClass('collapsed');
            }
        });


        // Advanced Toggle for OS/Browser
        $('#sb-toggle-advanced-device').on('click', function () {
            var $content = $('#sb-advanced-device-content');
            var $btn = $(this);

            if ($content.is(':visible')) {
                $content.slideUp(200);
                $btn.removeClass('expanded');
                $btn.find('span:last').text(typeof sb_i18n !== 'undefined' ? sb_i18n.toggle_advanced_show : 'Show Details');
            } else {
                $content.slideDown(200, function () {
                    // v3.0.7: Trigger Chart.js resize after slide animation completes
                    // Chart.js needs visible container to calculate dimensions
                    if (typeof SB_Chart !== 'undefined' && typeof SB_Chart.resizeCharts === 'function') {
                        SB_Chart.resizeCharts(['sb-os-chart', 'sb-browser-chart']);
                    } else {
                        // Fallback: Trigger window resize event to force Chart.js recalculation
                        $(window).trigger('resize');
                    }
                });
                $btn.addClass('expanded');
                $btn.find('span:last').text(typeof sb_i18n !== 'undefined' ? sb_i18n.toggle_advanced_hide : 'Hide Details');
            }
        });


        // Smooth Scroll for Smart CTA links
        function smoothScroll(targetId) {
            var $target = $(targetId);
            if ($target.length) {
                var $sectionContent = $target.find('.sb-section-content');
                if ($sectionContent.hasClass('collapsed')) {
                    $sectionContent.removeClass('collapsed');
                    $target.find('.sb-section-toggle').removeClass('collapsed');
                }
                $('html, body').animate({
                    scrollTop: $target.offset().top - 50
                }, 400);
            }
        }

        // 1. CTA Link Click
        $('.sb-card-cta').on('click', function (e) {
            var href = $(this).attr('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                smoothScroll(href);
            }
        });

        // 2. Whole Card Accessibility (Click bubbles from sb-admin.js or mouse)
        $('.sb-card').on('click', function (e) {
            // Note: Keydown (Enter/Space) is now handled globally in sb-admin.js which triggers this click.

            // If user clicked the CTA itself, let it bubble.
            if ($(e.target).closest('.sb-card-cta').length) return;

            var $cta = $(this).find('.sb-card-cta');
            if ($cta.length) {
                var href = $cta.attr('href');
                if (href && href.startsWith('#')) {
                    smoothScroll(href);
                }
            }
        });

    });

})(jQuery);
