/**
 * Smart Bridge Dashboard Logic
 * Extracted from dashboard.php
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.22
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

            if ($content.hasClass('collapsed')) {
                $content.removeClass('collapsed');
                $(this).removeClass('collapsed');
            } else {
                $content.addClass('collapsed');
                $(this).addClass('collapsed');
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
                $content.slideDown(200);
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
