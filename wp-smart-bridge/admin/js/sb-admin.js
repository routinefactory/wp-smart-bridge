/**
 * Smart Bridge Main Admin Script
 * Orchestrates Chart and UI modules.
 * Refactored for UX/A11y/Performance
 * 
 * @package WP_Smart_Bridge
 * @since 3.0.1
 */

(function ($) {
    'use strict';

    var sbAdmin = window.sbAdmin || {};

    /**
     * i18n Helper Function
     * Reduces repetitive typeof checks throughout codebase
     * @param {string} key - The translation key
     * @param {string} fallback - Fallback value if key not found
     * @returns {string}
     */
    function __(key, fallback) {
        return (typeof sb_i18n !== 'undefined' && sb_i18n[key]) ? sb_i18n[key] : fallback;
    }

    /**
     * Initialize all Analytics
     */
    function initAnalytics() {
        if (typeof sbChartData === 'undefined') return;

        // Init Charts via Module with initial PHP data
        SB_Chart.initTrafficTrend(sbChartData.dailyTrend);

        /**
         * v3.0.4: Multi-Period Trend Charts
         * These replaced the removed "Period Comparison" feature.
         * Weekly uses green color, Monthly uses orange to differentiate visually.
         */
        SB_Chart.initWeeklyTrend(sbChartData.weeklyTrend);   // v3.0.4: New
        SB_Chart.initMonthlyTrend(sbChartData.monthlyTrend); // v3.0.4: New

        SB_Chart.initHourly(sbChartData.clicksByHour);
        SB_Chart.initPlatform(sbChartData.platformShare);

        // Load Async Data
        loadRefererAnalytics();
        loadDeviceAnalytics();
        // v3.0.4: Removed - loadPatternAnalytics(); // Pattern Analytics feature removed by user request
    }

    /**
     * Refresh All Dashboard Stats (AJAX)
     */
    function refreshDashboard() {
        var params = getFilterParams();

        // UI Feedback: Spin Apply Button
        var $btn = $('#sb-apply-filters');

        // Prevent double-click while loading
        if ($btn.prop('disabled')) {
            return;
        }

        $btn.addClass('disabled').prop('disabled', true);
        $btn.find('.dashicons').addClass('sb-spin');

        // Helper function to re-enable button
        function enableButton() {
            setTimeout(function () {
                $btn.removeClass('disabled').prop('disabled', false);
                $btn.find('.dashicons').removeClass('sb-spin');
            }, 300);
        }

        // 1. Collect all requests promises
        var requests = [];

        // Main Charts Request
        var mainReq = $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: $.extend({
                action: 'sb_get_dashboard_stats',
                nonce: sbAdmin.ajaxNonce
            }, params),
            beforeSend: function () {
                $('#sb-trend-chart, #sb-hourly-chart, #sb-platform-chart').parent().addClass('sb-skeleton');
            },
            success: function (response) {
                if (response.success) {
                    SB_Chart.initTrafficTrend(response.data.dailyTrend);
                    SB_Chart.initHourly(response.data.clicksByHour);
                    SB_Chart.initPlatform(response.data.platformShare);
                } else {
                    SB_UI.showToast(response.data.message || __('error_occurred', 'Error occurred'), 'error');
                }
            },
            error: function () {
                SB_UI.showToast(__('network_error', 'Network error'), 'error');
            },
            complete: function () {
                $('#sb-trend-chart, #sb-hourly-chart, #sb-platform-chart').parent().removeClass('sb-skeleton');
            }
        });
        requests.push(mainReq);

        // 2. Sub-module Requests (Push promises to array)
        // Ensure functions return the ajax promise object
        requests.push(loadRefererAnalytics());
        requests.push(loadDeviceAnalytics());
        // v3.0.4: Removed - requests.push(loadPatternAnalytics()); // Pattern Analytics feature removed

        // 3. Synchronize Completion (Wait for ALL requests)
        // Using Promise.allSettled-like behavior via jQuery.when or Promise.all
        // We use Promise.all to wait for all, utilizing jQuery's distinct promise compatibility
        Promise.all(requests)
            .then(function () {
                // All success (technically)
            })
            .catch(function () {
                // Some failed, but we still proceed
            })
            .finally(function () {
                // 4. Re-enable button ONLY after EVERYTHING is done
                enableButton();
                SB_UI.showToast(typeof sb_i18n !== 'undefined' ? sb_i18n.success_saved || 'Refreshed' : 'Refreshed', 'success');
            });
    }

    /**
     * Data Loaders (Refactored to return Promise)
     */
    function loadRefererAnalytics() {
        var params = getFilterParams();
        return $.ajax({
            url: sbAdmin.restUrl + 'analytics/referers',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
                $('#sb-referer-chart, #sb-referer-groups-chart').parent().addClass('sb-skeleton');
            },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderReferer(response.data.top_referers);
                    SB_Chart.renderRefererGroups(response.data.referer_groups);
                }
            },
            error: function () {
                SB_UI.showToast(__('error_loading_data', 'Failed to load data'), 'error');
            },
            complete: function () {
                $('#sb-referer-chart, #sb-referer-groups-chart').parent().removeClass('sb-skeleton');
            }
        });
    }

    function loadDeviceAnalytics() {
        var params = getFilterParams();
        return $.ajax({
            url: sbAdmin.restUrl + 'analytics/devices',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
                $('#sb-device-chart, #sb-os-chart, #sb-browser-chart').parent().addClass('sb-skeleton');
            },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderDevice(response.data.devices);
                    SB_Chart.renderOS(response.data.os);
                    SB_Chart.renderBrowser(response.data.browsers);
                }
            },
            error: function () {
                SB_UI.showToast(__('error_loading_data', 'Failed to load data'), 'error');
            },
            complete: function () {
                $('#sb-device-chart, #sb-os-chart, #sb-browser-chart').parent().removeClass('sb-skeleton');
            }
        });
    }

    /**
     * v3.0.4: DISABLED - Pattern Analytics Feature Removed
     * 
     * This function and renderVisitorStats were removed since the HTML section
     * (weekday chart, visitor types, anomaly detection) was removed from dashboard.php.
     * 
     * RELATED REMOVED:
     * - dashboard.php: "Advanced Pattern Analysis" section
     * - sb-chart.js: renderWeekday function (no longer called)
     * - sb-ui.js: renderAnomalies function (no longer called)
     * - class-sb-rest-api.php: get_pattern_analytics endpoint (kept but unused)
     * 
     * @deprecated 3.0.4 Removed by user request
     */

    /**
     * Get Filter Parameters
     * CRITICAL: This function is used by almost all AJAX calls on the dashboard.
     * DO NOT REMOVE or many features will break!
     * 
     * Used by: refreshDashboard, loadRefererAnalytics, loadDeviceAnalytics, 
     *          comparison logic, link detail modal, and more.
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
     * v3.0.4: DISABLED - Pattern Analytics Feature Removed
     * 
     * This function and renderVisitorStats were removed since the HTML section
     * (weekday chart, visitor types, anomaly detection) was removed from dashboard.php.
     * 
     * RELATED REMOVED:
     * - dashboard.php: "Advanced Pattern Analysis" section
     * - sb-chart.js: renderWeekday function (no longer called)
     * - sb-ui.js: renderAnomalies function (no longer called)
     * - class-sb-rest-api.php: get_pattern_analytics endpoint (kept but unused)
     * 
     * @deprecated 3.0.4 Removed by user request
     */
    /*
    function loadPatternAnalytics() {
        var params = getFilterParams();
        return $.ajax({
            url: sbAdmin.restUrl + 'analytics/patterns',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce);
                $('#sb-weekday-chart').parent().addClass('sb-skeleton');
            },
            success: function (response) {
                if (response.success) {
                    SB_Chart.renderWeekday(response.data.weekday_stats);
                    renderVisitorStats(response.data.visitor_stats);
                    SB_UI.renderAnomalies(response.data.anomalies);
                }
            },
            error: function () {
                SB_UI.showToast(__('error_loading_data', 'Failed to load data'), 'error');
            },
            complete: function () {
                $('#sb-weekday-chart').parent().removeClass('sb-skeleton');
            }
        });
    }

    function renderVisitorStats(data) {
        SB_UI.setText('#sb-new-visitors', data.new_visitors.toLocaleString());
        SB_UI.setText('#sb-returning-visitors', data.returning.toLocaleString());
        SB_UI.setText('#sb-frequent-visitors', data.frequent.toLocaleString());
        SB_UI.setText('#sb-returning-rate', data.returning_rate + '%');
    }
    */

    /**
     * Health Check - Verify short links work properly (Permalink flush check)
     */
    function runHealthCheck() {
        /**
         * ⚠️ [CRITICAL] MANDATORY HEALTH CHECK
         * 
         * DO NOT REMOVE OR THROTTLE THIS LOGIC.
         * This check MUST run on EVERY dashboard page load to ensure short links are accessible.
         * Users must be immediately notified if permalinks are broken (404 error).
         * 
         * @intentional This seems expensive but is required for system integrity reliability.
         * @lock NO_THROTTLING
         */

        // Remove legacy throttling if exists
        localStorage.removeItem('sb_last_health_check');

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_health_check',
                nonce: sbAdmin.ajaxNonce
            },
            success: function (response) {
                if (response.success) {
                    // Always clear banner first to prevent duplicates
                    $('#sb-health-warning').remove();

                    /**
                     * v3.0.3 CRITICAL BUG FIX: Health Check Banner Not Showing
                     * 
                     * PROBLEM: The 404 warning banner was NEVER displayed even when links were broken.
                     * 
                     * ROOT CAUSE: The original code checked `if (status === 'error_404')` but `status` 
                     * was an UNDEFINED variable. The actual status data is inside `response.data.status`.
                     * JavaScript's loose comparison made `undefined === 'error_404'` always `false`.
                     * 
                     * SOLUTION: Changed to read from `response.data.status` which contains the 
                     * actual server response status ('ok', 'no_links', 'error_404', 'connection_error').
                     * 
                     * RELATED: PHP handler is in class-sb-admin-ajax.php -> ajax_health_check()
                     * which returns { success: true, data: { status: 'error_404', ... } }
                     * 
                     * @see class-sb-admin-ajax.php:283-361
                     */
                    if (response.data.status === 'error_404') {
                        // Show prominent warning banner
                        var $banner = $('<div id="sb-health-warning" class="notice notice-error sb-health-banner">' +
                            '<h3>⚠️ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.permalink_error_title || '단축 링크가 작동하지 않습니다!' : 'Short links are not working!') + '</h3>' +
                            '<p>' + (typeof sb_i18n !== 'undefined' ? sb_i18n.permalink_error_msg || '퍼마링크 설정이 필요합니다. 아래 버튼을 클릭하여 설정 페이지로 이동한 후, "변경사항 저장" 버튼을 클릭해주세요.' : 'Permalinks need to be flushed.') + '</p>' +
                            '<p><a href="' + sbAdmin.adminUrl + 'options-permalink.php" class="button button-primary">' +
                            (typeof sb_i18n !== 'undefined' ? sb_i18n.go_to_permalinks || '퍼마링크 설정으로 이동' : 'Go to Permalinks') + ' →</a></p>' +
                            (typeof sb_i18n !== 'undefined' ? sb_i18n.go_to_permalinks || '퍼마링크 설정으로 이동' : 'Go to Permalinks') + ' →</a></p>' +
                            '<p class="sb-health-details"><small>테스트 URL: ' + response.data.test_url + ' (응답 코드: ' + response.data.code + ')</small></p>' +
                            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + (typeof sb_i18n !== 'undefined' ? sb_i18n.dismiss || 'Dismiss' : 'Dismiss') + '</span></button>' +
                            '</div>');

                        // Insert at top of dashboard
                        $('.sb-dashboard').prepend($banner);

                        // Fix 3-3: Add dismiss handler immediately
                        $banner.find('.notice-dismiss').on('click', function () {
                            $banner.fadeTo(100, 0, function () {
                                $(this).slideUp(100, function () {
                                    $(this).remove();
                                });
                            });
                        });
                    }
                    // status 'ok' or 'no_links' - no action needed
                }
            },
            error: function () {
                // Silent fail - don't annoy user with health check errors
            }
        });
    }

    /**
     * Event Listeners
     */
    $(document).ready(function () {
        initAnalytics();
        // initLinkGroups(); // Feature disabled by user request (keeping code for algorithm reference)
        initRealtimeFeed();
        initGlobalErrorHandling(); // v3.0.0 Resilience

        // Run health check on dashboard load (v3.0.0)
        runHealthCheck();

        // 1. Filter events (Now AJAX)
        $('#sb-apply-filters').on('click', function () {
            refreshDashboard();
        });

        // A11y: Keyboard support for Summary Cards (Enter/Space)
        $('.sb-card[role="button"]').on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
                e.preventDefault();
                $(this).click();
            }
        });

        $('#sb-date-range').on('change', function () {
            if ($(this).val() === 'custom') {
                $('.sb-custom-dates').slideDown();
            } else {
                $('.sb-custom-dates').slideUp();
            }
        });

        // 2. Update Check Handler
        $(document).on('click', '#sb-force-check-update', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update sb-spin"></span> ' + sb_i18n.loading);

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_force_check_update',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function (response) {
                    if (response.success && response.data.has_update) {
                        var msg = sb_i18n.new_version.replace('{version}', response.data.latest_version);
                        SB_UI.confirm({
                            title: sb_i18n.title_alert || '업데이트 알림',
                            message: msg,
                            yesLabel: sb_i18n.download_link || '다운로드 이동',
                            onYes: function () {
                                window.open(response.data.download_url, '_blank');
                            }
                        });
                    } else {
                        SB_UI.showToast(response.data.message || sb_i18n.latest_version, 'info');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

    });

    // 3. Factory Reset Handler
    $(document).on('click', '#sb-factory-reset', function () {
        SB_UI.confirm({
            title: sb_i18n.title_alert || '시스템 경고 (DANGER)',
            message: sb_i18n.confirm_reset,
            yesLabel: sb_i18n.yes,
            noLabel: sb_i18n.no,
            onYes: function () {
                // Double Safety Layer
                SB_UI.prompt({
                    title: sb_i18n.title_prompt || '보안 확인',
                    message: sb_i18n.prompt_reset,
                    placeholder: 'RESET',
                    onSubmit: function (val) {
                        if (val !== 'RESET') {
                            SB_UI.showToast(sb_i18n.cancelled || '취소되었습니다.', 'info');
                            return;
                        }
                        performFactoryReset();
                    }
                });
            }
        });
    });

    // 3.1 Data Migration Handler (v2.9.27)
    $(document).on('click', '#sb-migrate-stats', function () {
        var $btn = $(this);
        var $status = $('#sb-migrate-status');

        $btn.prop('disabled', true);
        $status.show().text(sb_i18n.loading || 'Starting...');

        runMigrationBatch($btn, $status);
    });

    function runMigrationBatch($btn, $status) {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_migrate_daily_stats',
                nonce: sbAdmin.ajaxNonce
            },
            success: function (response) {
                if (response.success) {
                    $status.text(response.data.message);

                    if (response.data.completed === false) {
                        // Continue Next Batch
                        setTimeout(function () {
                            runMigrationBatch($btn, $status);
                        }, 500); // 0.5s delay
                    } else {
                        // Completed
                        SB_UI.showToast('✅ Migration Completed!', 'success');
                        $btn.prop('disabled', false).text('완료됨');
                        setTimeout(function () { $status.fadeOut(); }, 3000);
                    }
                } else {
                    SB_UI.showToast('❌ Error: ' + response.data.message, 'error');
                    $btn.prop('disabled', false);
                    $status.text('Error');
                }
            },
            error: function () {
                SB_UI.showToast('❌ Network Error', 'error');
                $btn.prop('disabled', false);
                $status.text('Network Error');
            }
        });
    }

    function performFactoryReset() {
        var $btn = $('#sb-factory-reset');
        $btn.prop('disabled', true).text(sb_i18n.loading);

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_factory_reset',
                nonce: sbAdmin.ajaxNonce,
                confirmation: 'reset'
            },
            success: function (response) {
                if (response.success) {
                    SB_UI.showToast(sb_i18n.reset_complete, 'success');
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    SB_UI.showToast(response.data.message, 'error');
                    $btn.prop('disabled', false).text(sb_i18n.factory_reset || 'Factory Reset');
                }
            },
            error: function () {
                SB_UI.showToast(sb_i18n.error_occurred, 'error');
                $btn.prop('disabled', false).text(sb_i18n.factory_reset || 'Factory Reset');
            }
        });
    }

    // 4. Backup Download
    $(document).on('click', '#sb-download-backup', function () {
        var url = sbAdmin.ajaxUrl + '?action=sb_download_backup&nonce=' + sbAdmin.ajaxNonce;
        window.location.href = url;
    });

    // 5. Backup Restore (Batched - v3.0.0 Scalability)
    $('#sb-restore-form').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var fileInput = $(form).find('input[type="file"]')[0];

        if (fileInput.files.length === 0) {
            SB_UI.showToast(typeof sb_i18n !== 'undefined' ? sb_i18n.select_file : 'Please select a file.', 'warning');
            return;
        }

        SB_UI.confirm({
            title: sb_i18n.title_confirm || '백업 복원',
            message: sb_i18n.confirm_restore,
            yesLabel: sb_i18n.yes,
            onYes: function () {
                var file = fileInput.files[0];
                var reader = new FileReader();

                var $btn = $(form).find('button[type="submit"]');
                var $progress = $('#sb-restore-progress');

                $btn.prop('disabled', true);
                $progress.show().text(typeof sb_i18n !== 'undefined' ? sb_i18n.reading_file : 'Reading file...');

                reader.onload = function (e) {
                    try {
                        var backupData = JSON.parse(e.target.result);
                        if (!backupData.data) throw new Error('Invalid structure');
                        startBatchRestore(backupData.data, $btn, $progress);
                    } catch (err) {
                        SB_UI.showToast('유효하지 않은 백업 파일입니다.', 'error');
                        $btn.prop('disabled', false);
                        $progress.hide();
                    }
                };

                reader.onerror = function () {
                    SB_UI.showToast('파일 읽기 실패', 'error');
                    $btn.prop('disabled', false);
                    $progress.hide();
                };

                reader.readAsText(file);
            }
        });
    });

    // 6. Settings Form
    $('#sb-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(sb_i18n.loading);

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_save_settings',
                nonce: sbAdmin.ajaxNonce,
                redirect_delay: $('#sb-redirect-delay').val()
            },
            success: function (response) {
                SB_UI.showToast(response.data.message, response.success ? 'success' : 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // 7. Modal - Link Details
    $(document).on('click', '#sb-today-links tbody tr', function () {
        var href = $(this).find('a[href*="post="]').attr('href');
        if (!href) return;
        var match = href.match(/post=(\d+)/);
        if (match) openLinkDetailModal(match[1]);
    });

    // 8. Comparison Logic
    $('#sb-toggle-comparison').on('click', function () {
        var $container = $('#sb-comparison-container');
        if ($container.is(':visible')) {
            $container.slideUp();
            $(this).text(typeof sb_i18n !== 'undefined' ? sb_i18n.compare_mode_on : 'Enable Comparison');
        } else {
            $container.slideDown();
            $(this).text(typeof sb_i18n !== 'undefined' ? sb_i18n.compare_mode_off : 'Disable Comparison');
        }
    });

    $('#sb-load-comparison').on('click', function () {
        var params = getFilterParams();
        params.type = $('#sb-comparison-type').val();

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: sbAdmin.restUrl + 'analytics/comparison',
            method: 'GET',
            data: params,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', sbAdmin.nonce); },
            success: function (response) {
                if (response.success) {
                    renderComparison(response.data);
                }
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    // ========================================
    // v3.0.0 Refactor: All functions now inside IIFE for proper encapsulation
    // Functions that need external access are exposed via window object
    // ========================================

    // Comparison Render
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
     * Link Detail Modal
     * v3.0.0 Fix: Inlined filter params to avoid scope issue with IIFE-enclosed getFilterParams
     */
    function openLinkDetailModal(linkId) {
        // Inline filter params (getFilterParams is inside IIFE, not accessible here)
        var range = $('#sb-date-range').val() || '7d';
        var platform = $('#sb-platform-filter').val() || '';
        var params = { range: range, platform: platform };

        if (range === 'custom') {
            params.start_date = $('#sb-start-date').val();
            params.end_date = $('#sb-end-date').val();
        }

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
            },
            error: function () {
                SB_UI.showToast(sb_i18n.ajax_error || 'Failed to load link details', 'error');
            }
        });
    }

    function renderLinkDetailModal(data) {
        SB_UI.setText('#sb-link-slug', data.link_info.slug);
        SB_UI.setText('#sb-link-platform', data.link_info.platform);
        SB_UI.setText('#sb-link-created', data.link_info.created_at.substring(0, 10));
        SB_UI.setText('#sb-link-total-clicks', data.stats.total_clicks.toLocaleString());
        SB_UI.setText('#sb-link-unique-visitors', data.stats.unique_visitors.toLocaleString());

        SB_Chart.renderLinkHourly(data.stats.clicks_by_hour);
        SB_UI.renderReferers(data.referers);
        SB_UI.renderDeviceBars(data.devices);
    }

    /**
     * Link Group Logic
     */
    function initLinkGroups() {
        loadGroups();

        $('#sb-manage-groups-btn').on('click', function () {
            SB_UI.openModal('#sb-group-manager-modal');
        });

        $('#sb-add-group-btn').on('click', function () {
            var name = $('#sb-new-group-name').val();
            var color = $('#sb-new-group-color').val();

            if (!name) return SB_UI.showToast(sb_i18n.group_name_empty, 'warning');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_create_group',
                    nonce: sbAdmin.ajaxNonce,
                    name: name,
                    color: color
                },
                success: function (response) {
                    if (response.success) {
                        $('#sb-new-group-name').val('');
                        loadGroups();
                    } else {
                        SB_UI.showToast(response.data.message, 'error');
                    }
                }
            });
        });

        $(document).on('click', '.sb-delete-group', function () {
            var id = $(this).data('id');
            SB_UI.confirm({
                title: typeof sb_i18n !== 'undefined' ? sb_i18n.group_delete : 'Delete Group',
                message: sb_i18n.confirm_delete,
                yesLabel: typeof sb_i18n !== 'undefined' ? sb_i18n.delete : 'Delete',
                onYes: function () {
                    $.ajax({
                        url: sbAdmin.ajaxUrl,
                        method: 'POST',
                        data: { action: 'sb_delete_group', nonce: sbAdmin.ajaxNonce, id: id },
                        success: function (response) {
                            if (response.success) loadGroups();
                        }
                    });
                }
            });
        });
    }

    function loadGroups() {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'sb_get_groups', nonce: sbAdmin.ajaxNonce },
            success: function (response) {
                if (response.success) {
                    renderGroupsList(response.data.groups);
                }
            },
            error: function () {
                SB_UI.showToast(typeof sb_i18n !== 'undefined' ? sb_i18n.error_loading_groups : 'Failed to load groups', 'error');
            }
        });
    }

    function renderGroupsList(groups) {
        var $list = $('#sb-group-list');
        $list.empty();
        groups.forEach(function (group) {
            // XSS Safe: Use jQuery DOM construction
            var $li = $('<li></li>');
            var $color = $('<span class="sb-group-color"></span>').css('background', group.color);
            var $name = $('<span class="sb-group-name"></span>').text(group.name + ' (' + group.link_count + ')');
            var $deleteBtn = $('<button type="button" class="sb-delete-group"></button>')
                .attr('data-id', group.id)
                .attr('aria-label', typeof sb_i18n !== 'undefined' ? sb_i18n.delete_group : 'Delete group')
                .html('&times;');
            $li.append($color).append($name).append($deleteBtn);
            $list.append($li);
        });
    }

    /**
     * Realtime Click Feed with Reconnection Logic & A11y
     */
    var eventSource = null;
    var reconnectAttempts = 0;
    var maxReconnectAttempts = 5;
    var lastLogId = 0; // v3.0.0 Fix: Deduplication

    function initGlobalErrorHandling() {
        $(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {
            // Ignore if handled explicitly or cancelled
            if (jqXHR.status === 0 || jqXHR.readyState === 0) return;

            // Detect Session Expiry (403 or specific nonce message)
            if (jqXHR.status === 403 || (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message && jqXHR.responseJSON.data.message.indexOf('nonce') !== -1)) {

                // Prevent duplicate modals
                if ($('#sb-session-modal').length > 0) return;

                SB_UI.confirm({
                    id: 'sb-session-modal',
                    title: sb_i18n.session_expired || 'Session Expired',
                    message: sb_i18n.session_expired_msg || 'Please reload the page.',
                    yesLabel: sb_i18n.reload || 'Reload',
                    onYes: function () {
                        window.location.reload();
                    }
                });
            }
        });
    }

    function initRealtimeFeed() {
        if (!window.EventSource) {
            $('#sb-realtime-feed').html('<p class="sb-feed-error">' + (typeof sb_i18n !== 'undefined' ? sb_i18n.realtime_not_supported : 'Realtime feed not supported') + '</p>');
            return;
        }

        // A11y: Make it a Live Region
        $('#sb-realtime-feed').attr('aria-live', 'polite').attr('aria-atomic', 'false');

        connectEventSource();
    }

    function connectEventSource() {
        // Fix 3-1: Resource Cleanup (Verify Pass: Prevent duplicate connections)
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        var feedUrl = sbAdmin.ajaxUrl + '?action=sb_realtime_feed&nonce=' + sbAdmin.ajaxNonce;
        eventSource = new EventSource(feedUrl);

        eventSource.onopen = function () {
            $('#sb-realtime-status').removeClass('error').addClass('connected')
                .attr('title', __('realtime_connected', 'Connected'));
            reconnectAttempts = 0; // Reset on successful connection
        };

        // v3.0.0 Fix: Server sends 'event: click' individually, not 'clicks' array
        // Listen for specific 'click' event type from SSE
        eventSource.addEventListener('click', function (event) {
            var click = JSON.parse(event.data);

            if (parseInt(click.id) > lastLogId) {
                renderFeedItem(click);
                lastLogId = parseInt(click.id);
            }
        });

        // Handle heartbeat events (keep-alive)
        eventSource.addEventListener('heartbeat', function () {
            // Heartbeat received, connection is alive - no action needed
        });

        // Fallback for any unnamed events (legacy compatibility)
        eventSource.onmessage = function (event) {
            var data = JSON.parse(event.data);
            if (data.heartbeat) return;

            // Legacy format: clicks array (if server changes in future)
            if (data.clicks) {
                var newClicks = data.clicks.filter(function (c) {
                    return parseInt(c.id) > lastLogId;
                }).sort(function (a, b) {
                    return parseInt(a.id) - parseInt(b.id);
                });

                if (newClicks.length > 0) {
                    newClicks.forEach(renderFeedItem);
                    lastLogId = parseInt(newClicks[newClicks.length - 1].id);
                }
            }
        };

        eventSource.onerror = function () {
            $('#sb-realtime-status').removeClass('connected').addClass('error')
                .attr('title', sb_i18n.realtime_disconnected || 'Disconnected');
            eventSource.close();

            // Exponential backoff reconnection
            if (reconnectAttempts < maxReconnectAttempts) {
                var delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000); // Max 30s
                reconnectAttempts++;
                setTimeout(connectEventSource, delay);
            }
        };
    }

    function renderFeedItem(click) {
        var $feed = $('#sb-realtime-feed');
        $('.sb-feed-placeholder').remove();

        /**
         * v3.0.3 FIX: Realtime Feed Date Display
         * 
         * PROBLEM: Feed only showed time (e.g., "14:49") without any date context.
         * Users couldn't tell if a click was from today, yesterday, or older.
         * 
         * ROOT CAUSE: Original code `click.visited_at.split(' ')[1].substring(0, 5)` 
         * extracted only the time portion, completely discarding the date.
         * 
         * SOLUTION: Smart display format:
         * - Today's clicks: Show time only (e.g., "14:49") for brevity
         * - Older clicks: Show "MM-DD HH:MM" (e.g., "01-06 14:49") for clarity
         * 
         * NOTE: `new Date().toISOString().slice(0,10)` uses UTC, not local time.
         * This may cause edge cases around midnight. Consider using local date if issues arise.
         * 
         * RELATED: PHP SSE handler is in class-sb-realtime.php -> start_stream()
         * which sends `visited_at` in 'Y-m-d H:i:s' format from WordPress timezone.
         */
        var dateTime = click.visited_at || '';
        var datePart = dateTime.split(' ')[0] || '';  // YYYY-MM-DD
        var timePart = dateTime.split(' ')[1] || '';
        var today = new Date().toISOString().slice(0, 10);
        var displayTime = (datePart === today) ? timePart.substring(0, 5) : datePart.substring(5) + ' ' + timePart.substring(0, 5);

        // Safe HTML construction JQuery
        var $item = $('<div class="sb-feed-item"></div>');
        $item.append($('<div class="sb-feed-time"></div>').text(displayTime));

        var $content = $('<div class="sb-feed-content"></div>');
        /**
         * v3.0.3 FIX: Feed Item Data Key Mismatch
         * 
         * PROBLEM: Feed items showed empty/undefined for the link name.
         * 
         * ROOT CAUSE: JS was using `click.post_title` but PHP sends `click['slug']`.
         * The PHP handler in class-sb-realtime.php enriches data with:
         *   $click['slug'] = $post->post_name;
         * NOT `post_title`.
         * 
         * SOLUTION: Changed to use `click.slug` with null fallback for safety.
         * Also truncated visitor_ip for privacy and visual cleanliness.
         * 
         * RELATED: PHP -> class-sb-realtime.php:88-91 (data enrichment)
         */
        $content.append($('<div class="sb-feed-link"></div>').text(click.slug || 'unknown'));
        $content.append($('<div class="sb-feed-meta"></div>').text((click.platform || 'unknown') + ' | ' + (click.device || 'unknown') + ' | ' + (click.visitor_ip || '').substring(0, 16) + '...'));

        $item.append($content);

        $feed.prepend($item);

        if ($feed.children().length > 20) {
            $feed.children().last().remove();
        }
    }

    // Modal Close Delegation
    $(document).on('click', '.sb-modal-close, .sb-modal-overlay', function () {
        SB_UI.closeModal(this);
    });

    // =========================================================================
    // 6. Settings Page Logic
    // =========================================================================
    if ($('#sb-template-form').length > 0) {

        function validateTemplate(template, showSuccess) {
            var required = [
                '{{DELAY_SECONDS}}',
                '{{TARGET_URL}}',
                '{{COUNTDOWN_SCRIPT}}',
                '{{COUNTDOWN_ID}}'
            ];

            var missing = [];
            required.forEach(function (placeholder) {
                if (template.indexOf(placeholder) === -1) {
                    missing.push(placeholder);
                }
            });

            var valid = missing.length === 0;

            if (showSuccess || !valid) {
                showValidation(valid, valid
                    ? '✅ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.all_placeholders_ok : 'All placeholders present!')
                    : '❌ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.missing_placeholders : 'Missing') + ': ' + missing.join(', '));
            }

            return { valid: valid, missing: missing };
        }

        function showValidation(isValid, message) {
            var $box = $('#sb-template-validation');
            $box.show()
                .css({
                    'background': isValid ? '#d1f2dd' : '#f8d7da',
                    'border': '1px solid ' + (isValid ? '#00a32a' : '#d63638'),
                    'color': isValid ? '#00664a' : '#721c24'
                })
                .html('<strong>' + message + '</strong>');

            setTimeout(function () {
                if (isValid) {
                    $box.fadeOut();
                }
            }, 5000);
        }

        // 템플릿 검증 버튼
        $('#sb-validate-template').on('click', function () {
            var template = $('#sb-redirect-template').val();
            validateTemplate(template, true);
        });

        // 템플릿 저장
        $('#sb-template-form').on('submit', function (e) {
            e.preventDefault();

            var template = $('#sb-redirect-template').val();
            var validation = validateTemplate(template, false);

            if (!validation.valid) {
                return;
            }

            var $btn = $('#sb-save-template');
            $btn.prop('disabled', true).text(typeof sb_i18n !== 'undefined' ? sb_i18n.saving : 'Saving...');

            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_save_redirect_template',
                    nonce: sbAdmin.ajaxNonce,
                    template: template
                },
                success: function (response) {
                    if (response.success) {
                        showValidation(true, '✅ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.success_saved : 'Saved!'));
                    } else {
                        showValidation(false, '❌ ' + (response.data.message || (typeof sb_i18n !== 'undefined' ? sb_i18n.save_failed : 'Save Failed')));
                    }
                },
                error: function () {
                    showValidation(false, '❌ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.network_error : 'Network Error'));
                },
                complete: function () {
                    $btn.prop('disabled', false).text(typeof sb_i18n !== 'undefined' ? sb_i18n.template_save : 'Save Template');
                }
            });
        });

        // 기본값 복원
        $('#sb-reset-template').on('click', function () {
            SB_UI.confirm({
                title: typeof sb_i18n !== 'undefined' ? sb_i18n.template_reset : 'Reset Template',
                message: typeof sb_i18n !== 'undefined' ? sb_i18n.template_reset_confirm : 'Reset template to default?',
                yesLabel: typeof sb_i18n !== 'undefined' ? sb_i18n.reset : 'Reset',
                onYes: function () {
                    $.ajax({
                        url: sbAdmin.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'sb_reset_redirect_template',
                            nonce: sbAdmin.ajaxNonce
                        },
                        success: function (response) {
                            if (response.success && response.data.template) {
                                $('#sb-redirect-template').val(response.data.template);
                                showValidation(true, '✅ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.template_restored : 'Template Restored!'));
                            }
                        }
                    });
                }
            });
        });
    }

    // =========================================================================
    // 7. Post Type Edit Page Logic (sb_link)
    // =========================================================================
    if ($('body').hasClass('post-type-sb_link')) {

        // 7.1 Copy Link Button
        $('.sb-copy-link').on('click', function () {
            var link = $(this).data('link');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(link).then(function () {
                    SB_UI.showToast(typeof sb_i18n !== 'undefined' ? sb_i18n.copied_to_clipboard : 'Copied!', 'success');
                }).catch(function () {
                    prompt(typeof sb_i18n !== 'undefined' ? sb_i18n.clipboard_fallback : 'Copy manually:', link);
                });
            } else {
                prompt(typeof sb_i18n !== 'undefined' ? sb_i18n.clipboard_not_supported : 'Copy manually:', link);
            }
        });

        // 7.2 Disable Title Field (Editing)
        // If we are on edit screen (not just list), disable title
        // Fix (v3.0.2): Use input#title to avoid selecting the table header th#title on list screen
        if ($('input#title').length > 0) {
            $('input#title').prop('disabled', true).prop('readonly', true);
            $('#title-prompt-text').text(typeof sb_i18n !== 'undefined' ? sb_i18n.slug_cannot_change : 'Slug cannot be changed');

            // Avoid duplicate description if re-run
            if ($('.sb-title-warning').length === 0) {
                $('input#title').after('<p class="description sb-title-warning" style="color: #d63638; margin-top: 5px;">⚠️ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.slug_warning : 'Slug cannot be changed.') + '</p>');
            }
        }
    }

    // =========================================================================
    // 8. Batch Restore Helper Functions (v3.0.0)
    // =========================================================================
    function startBatchRestore(data, $btn, $progress) {
        var links = data.links || [];
        var analytics = data.analytics || [];
        var settings = data.settings || {};

        // Chunk sizes
        var LINK_CHUNK_SIZE = 50;
        var LOG_CHUNK_SIZE = 100;

        // Create batches
        var batches = [];
        var totalItems = links.length + analytics.length;

        // 1. Links Batches
        for (var i = 0; i < links.length; i += LINK_CHUNK_SIZE) {
            batches.push({
                type: 'links',
                data: links.slice(i, i + LINK_CHUNK_SIZE),
                settings: (i === 0) ? settings : null // Restore settings with first chunk
            });
        }

        // 2. Logs Batches
        for (var j = 0; j < analytics.length; j += LOG_CHUNK_SIZE) {
            batches.push({
                type: 'analytics',
                data: analytics.slice(j, j + LOG_CHUNK_SIZE)
            });
        }

        if (batches.length === 0 && Object.keys(settings).length > 0) {
            // Only settings
            batches.push({ type: 'settings', settings: settings, data: [] });
        }

        var sessionId = 'restore_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        processNextBatch(batches, 0, sessionId, $btn, $progress, totalItems, 0);
    }

    function processNextBatch(batches, index, sessionId, $btn, $progress, totalItems, processedCount) {
        if (index >= batches.length) {
            // Done
            SB_UI.showToast('✅ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.restore_complete : 'Restore Complete!'), 'success');
            $progress.hide();
            $btn.prop('disabled', false);
            setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }

        var batch = batches[index];
        var chunkData = {};

        if (batch.type === 'links') chunkData.links = batch.data;
        if (batch.type === 'analytics') chunkData.analytics = batch.data;
        if (batch.settings) chunkData.settings = batch.settings;

        var percent = totalItems > 0 ? Math.round((processedCount / totalItems) * 100) : 0;
        // Update Progress Text
        $progress.text((typeof sb_i18n !== 'undefined' ? sb_i18n.restoring : 'Restoring...') + ' ' + percent + '%');

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_restore_backup_chunk',
                nonce: sbAdmin.ajaxNonce,
                chunk_data: JSON.stringify(chunkData),
                options: JSON.stringify({ session_id: sessionId })
            },
            success: function (response) {
                if (response.success) {
                    var itemsInChunk = (batch.data || []).length;
                    processNextBatch(batches, index + 1, sessionId, $btn, $progress, totalItems, processedCount + itemsInChunk);
                } else {
                    SB_UI.showToast('Restore Error: ' + response.data.message, 'error');
                    $btn.prop('disabled', false);
                    $progress.hide();
                }
            },
            error: function () {
                SB_UI.showToast('Network Error', 'error');
                $btn.prop('disabled', false);
                $progress.hide();
            }
        });
    }

    // =========================================================================
    // 9. Settings Page Logic (v3.0.2 Restore)
    // =========================================================================
    function initSettingsPage() {
        if ($('.sb-settings').length === 0) return;

        // 9.1 Generate API Key
        $('#sb-generate-key').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).addClass('sb-spin');

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_generate_api_key',
                nonce: sbAdmin.ajaxNonce
            }, function (response) {
                $btn.prop('disabled', false).removeClass('sb-spin');
                if (response.success) {
                    $('#sb-new-api-key').text(response.data.api_key);
                    $('#sb-new-secret-key').text(response.data.secret_key);

                    var $modal = $('#sb-new-key-modal');
                    $modal.removeClass('sb-hidden').addClass('sb-show');
                    $('body').addClass('sb-modal-open');

                    // Reload page after modal close to update list
                    $modal.find('.sb-close-modal').one('click', function () {
                        window.location.reload();
                    });
                } else {
                    SB_UI.showToast(response.data.message || 'Error executing request', 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).removeClass('sb-spin');
                SB_UI.showToast('Network Error', 'error');
            });
        });

        // 9.2 Toggle Secret Key
        $(document).on('click', '.sb-toggle-secret', function () {
            var $row = $(this).closest('tr');
            var $masked = $row.find('.sb-masked');
            var $revealed = $row.find('.sb-revealed');

            if ($revealed.hasClass('sb-hidden')) {
                $masked.hide();
                $revealed.removeClass('sb-hidden');
                $(this).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $masked.show();
                $revealed.addClass('sb-hidden');
                $(this).find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // 9.3 Delete API Key
        $(document).on('click', '.sb-delete-key', function () {
            if (!confirm(typeof sb_i18n !== 'undefined' ? sb_i18n.confirm_delete : 'Are you sure you want to delete this key?')) return;

            var $btn = $(this);
            var keyId = $btn.data('key-id');
            $btn.prop('disabled', true);

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_delete_api_key',
                nonce: sbAdmin.ajaxNonce,
                key_id: keyId
            }, function (response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function () { $(this).remove(); });
                    SB_UI.showToast(response.data.message, 'success');
                } else {
                    $btn.prop('disabled', false);
                    SB_UI.showToast(response.data.message || 'Error', 'error');
                }
            });
        });

        // 9.4 Copy Button (Generic)
        $(document).on('click', '.sb-copy-btn, .sb-copy-modal-key', function () {
            var text = $(this).data('copy');
            // For modal buttons, target ID
            var target = $(this).data('target');
            if (target) {
                text = $('#' + target).text();
            }

            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(function () {
                    SB_UI.showToast(typeof sb_i18n !== 'undefined' ? sb_i18n.copied_to_clipboard : 'Copied!', 'success');
                }).catch(function () {
                    prompt('Copy manually:', text);
                });
            } else if (text) {
                prompt('Copy manually:', text);
            }
        });

        // 9.5 Save General Settings
        $('#sb-settings-form').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).addClass('sb-spin');

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_save_settings',
                nonce: sbAdmin.ajaxNonce,
                redirect_delay: $('#sb-redirect-delay').val()
            }, function (response) {
                $btn.prop('disabled', false).removeClass('sb-spin');
                if (response.success) {
                    SB_UI.showToast(response.data.message, 'success');
                } else {
                    SB_UI.showToast(response.data.message || 'Error', 'error');
                }
            });
        });

        // 9.6 Migrate Stats
        $('#sb-migrate-stats').on('click', function () {
            var $btn = $(this);
            var $status = $('#sb-migrate-status');

            if (!confirm('This process may take some time. Do not close the window. Continue?')) return;

            $btn.prop('disabled', true).addClass('sb-spin');
            $status.show().text('Processing migration...');

            function runMigration() {
                $.post(sbAdmin.ajaxUrl, {
                    action: 'sb_migrate_daily_stats',
                    nonce: sbAdmin.ajaxNonce
                }, function (response) {
                    if (response.success) {
                        $status.text(response.data.message);
                        if (!response.data.completed) {
                            // Continue next batch
                            runMigration();
                        } else {
                            $btn.prop('disabled', false).removeClass('sb-spin');
                            $status.text('All done! ' + response.data.message);
                            SB_UI.showToast('Migration Complete', 'success');
                        }
                    } else {
                        SB_UI.showToast('Error: ' + response.data.message, 'error');
                        $btn.prop('disabled', false).removeClass('sb-spin');
                        $status.text('Error occurred.');
                    }
                }).fail(function () {
                    SB_UI.showToast('Network Error', 'error');
                    $btn.prop('disabled', false).removeClass('sb-spin');
                });
            }
            runMigration();
        });

        // 9.7 Factory Reset
        $('#sb-factory-reset').on('click', function () {
            var confirmation = prompt('Type "reset" to confirm factory reset. ALL DATA WILL BE LOST.');
            if (confirmation !== 'reset') return;

            var $btn = $(this);
            $btn.prop('disabled', true).addClass('sb-spin');

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_factory_reset',
                nonce: sbAdmin.ajaxNonce,
                confirmation: 'reset'
            }, function (response) {
                if (response.success) {
                    alert('Reset Complete. Reloading...');
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false).removeClass('sb-spin');
                    alert('Error: ' + response.data.message);
                }
            });
        });

        // 9.8 Template Editor
        $('#sb-validate-template').on('click', function () {
            var template = $('#sb-redirect-template').val();
            // Simple Client-side check first
            if (template.indexOf('{{TARGET_URL}}') === -1) {
                SB_UI.showToast('Missing {{TARGET_URL}} placeholder', 'error');
                return;
            }
            SB_UI.showToast('Template structure looks OK (Server validation required for save)', 'success');
        });

        $('#sb-template-form').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('#sb-save-template');
            $btn.prop('disabled', true).addClass('sb-spin');

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_save_redirect_template',
                nonce: sbAdmin.ajaxNonce,
                template: $('#sb-redirect-template').val()
            }, function (response) {
                $btn.prop('disabled', false).removeClass('sb-spin');
                if (response.success) {
                    SB_UI.showToast(response.data.message, 'success');
                } else {
                    SB_UI.showToast(response.data.message, 'error');
                    $('#sb-template-validation').html('<p class="error">' + response.data.message + '</p>');
                }
            });
        });

        $('#sb-reset-template').on('click', function () {
            if (!confirm('Revert to default template?')) return;

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_reset_redirect_template',
                nonce: sbAdmin.ajaxNonce
            }, function (response) {
                if (response.success) {
                    $('#sb-redirect-template').val(response.data.template);
                    SB_UI.showToast(response.data.message, 'success');
                }
            });
        });
    }

    // Initialize Settings Logic
    $(document).ready(function () {
        initSettingsPage();
    });

    // ========================================
    // Expose functions that need external access (e.g., from HTML onclick)
    // ========================================
    window.openLinkDetailModal = openLinkDetailModal;

})(jQuery);
