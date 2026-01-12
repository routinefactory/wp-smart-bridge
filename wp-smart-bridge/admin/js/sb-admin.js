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

                    // v3.0.4: Refresh Multi-Period Trend Charts
                    if (response.data.weeklyTrend) SB_Chart.initWeeklyTrend(response.data.weeklyTrend);
                    if (response.data.monthlyTrend) SB_Chart.initMonthlyTrend(response.data.monthlyTrend);

                    SB_Chart.initHourly(response.data.clicksByHour);
                    SB_Chart.initPlatform(response.data.platformShare);

                    // v3.0.4: Update Summary & Top Links (Filter Consistency)
                    if (response.data.summary) updateSummaryStats(response.data.summary);
                    if (response.data.topLinks) updateTopLinksTable(response.data.topLinks);
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

        // v3.0.5: Reload Realtime Feed with new filters
        initRealtimeFeed();

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
     * Health Check - Verify short links work properly
     * 
     * v4.0.0: 파라미터 방식(?go=slug)으로 변경됨
     * - 퍼마링크 flush가 필요 없어짐
     * - 404 시 간단한 경고만 표시 (서버 오류, DB 문제 등 감지용)
     */
    function runHealthCheck() {
        /**
         * ⚠️ [CRITICAL] MANDATORY HEALTH CHECK
         * 
         * DO NOT REMOVE OR THROTTLE THIS LOGIC.
         * This check MUST run on EVERY dashboard page load to ensure short links are accessible.
         * 
         * v4.0.0: Simplified - no more auto-fix permalink flush
         * 
         * @intentional This seems expensive but is required for system integrity reliability.
         * @lock NO_THROTTLING
         */

        // Remove legacy throttling if exists
        localStorage.removeItem('sb_last_health_check');

        /**
         * v4.0.0: 간단한 경고 배너 표시
         * 퍼마링크 관련 메시지 없이 서버 상태 확인 안내만 표시
         */
        function showWarningBanner(testUrl, responseCode) {
            $('#sb-health-warning').remove();
            var $banner = $('<div id="sb-health-warning" class="notice notice-warning sb-health-banner">' +
                '<h3>⚠️ ' + __('health_warning_title', '서버에서 링크에 접근할 수 없습니다') + '</h3>' +
                '<p>' + __('health_warning_message', '단축 링크가 정상적으로 작동하지 않을 수 있습니다. 서버 상태를 확인해주세요.') + '</p>' +
                '<p class="sb-health-details"><small>테스트 URL: ' + testUrl + ' (응답 코드: ' + responseCode + ')</small></p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + __('dismiss', 'Dismiss') + '</span></button>' +
                '</div>');

            $('.sb-dashboard').prepend($banner);

            $banner.find('.notice-dismiss').on('click', function () {
                $banner.fadeTo(100, 0, function () {
                    $(this).slideUp(100, function () {
                        $(this).remove();
                    });
                });
            });
        }

        // Health check AJAX call
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_health_check',
                nonce: sbAdmin.ajaxNonce
            },
            success: function (response) {
                if (response.success) {
                    $('#sb-health-warning').remove();

                    if (response.data.status === 'error_404') {
                        // v4.0.0: 간단한 경고만 표시 (auto-fix 시도 없음)
                        showWarningBanner(response.data.test_url, response.data.code);
                    }
                    // status 'ok', 'no_links', 'connection_error' - no action needed
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

        // ------------------------------------
        // v4.1.3: 3-State Sorting Toggle (ASC -> DESC -> RESET)
        // ------------------------------------
        $('body.post-type-sb_link .wp-list-table th.sortable a, body.post-type-sb_link .wp-list-table th.sorted a').on('click', function (e) {
            var href = $(this).attr('href');
            if (!href) return;

            // Extract params from current URL and target href
            var currentUrl = new URL(window.location.href);
            var targetUrl = new URL(href, window.location.origin);

            var currentOrderBy = currentUrl.searchParams.get('orderby');
            var currentOrder = currentUrl.searchParams.get('order') || 'asc'; // WP defaults to asc usually if omitted but sorted

            var targetOrderBy = targetUrl.searchParams.get('orderby');

            // Logic: If we are currently sorting by this column in DESC order, 
            // the next click (which WP makes ASC) should instead RESET sorting.
            if (currentOrderBy === targetOrderBy && currentOrder.toLowerCase() === 'desc') {
                e.preventDefault();
                currentUrl.searchParams.delete('orderby');
                currentUrl.searchParams.delete('order');
                window.location.href = currentUrl.toString();
            }
        });

        // 기간 필터 변경 이벤트 (custom 옵션 제거됨)
        $('#sb-date-range').on('change', function () {
            // 사용자 지정 날짜 필드 토글 로직 제거
            // 필요한 경우 추후 추가
        });

        // 2. Factory Reset Handler (moved inside $(document).ready for proper DOM load)
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

        // 3. Update Check Handler
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
        var range = $('#sb-date-range').val() || 'today_7d';
        var platform = $('#sb-platform-filter').val() || '';
        var params = { range: range, platform: platform };

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

        // v3.0.5: Filtered Realtime Feed
        var params = getFilterParams();
        var platformParam = params.platform ? '&platform=' + encodeURIComponent(params.platform) : '';

        var feedUrl = sbAdmin.ajaxUrl + '?action=sb_realtime_feed&nonce=' + sbAdmin.ajaxNonce + platformParam;

        // Clear feed on filter change to avoid mixing data
        if (reconnectAttempts === 0) {
            $('#sb-realtime-feed').empty();
        }

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
        /**
         * v3.1.3 FIX: Accurate Timezone Handling
         * 
         * PROBLEM: Server sends 'visited_at' in WordPress timezone (which might be UTC),
         * but user expects to see it in their local browser time (e.g. KST).
         * 
         * SOLUTION: Use 'timestamp' (UTC seconds) sent from server to create Date object.
         * Browser automatically converts this to local system time.
         */
        var displayTime;

        if (click.timestamp) {
            // Timestamp based (Preferred, v3.1.3+)
            var date = new Date(parseInt(click.timestamp) * 1000);
            var now = new Date();

            var isToday = date.getFullYear() === now.getFullYear() &&
                date.getMonth() === now.getMonth() &&
                date.getDate() === now.getDate();

            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            var timeStr = hours + ':' + minutes;

            if (isToday) {
                displayTime = timeStr;
            } else {
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                displayTime = month + '-' + day + ' ' + timeStr;
            }
        } else {
            // Legacy fallback (String parsing)
            var dateTime = click.visited_at || '';
            var datePart = dateTime.split(' ')[0] || '';  // YYYY-MM-DD
            var timePart = dateTime.split(' ')[1] || '';

            var now = new Date();
            var today = now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0');

            displayTime = (datePart === today) ? timePart.substring(0, 5) : datePart.substring(5) + ' ' + timePart.substring(0, 5);
        }

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

        // ============================================================
        // P2 UX 개선: 탭 기반 UI 초기화
        // ============================================================
        initSettingsTabs();

        // ============================================================
        // P2 UX 개선: CodeMirror 에디터 초기화
        // ============================================================
        initCodeMirrorEditor();

        // ============================================================
        // P2 UX 개선: 일반 설정 폼 핸들러
        // ============================================================
        function initGeneralSettingsForm() {
            var $form = $('#sb-general-settings-form');
            var $saveBtn = $form.find('.button-primary');
        
            $form.on('submit', function(e) {
                e.preventDefault();
        
                // 로딩 상태 표시
                showButtonLoading($saveBtn);
                hideAllMessages();
        
                // 폼 데이터 수집
                var formData = {
                    nonce: sbAdmin.nonce,
                    action: 'sb_save_general_settings',
                    redirect_delay: $('#sb-redirect-delay').val()
                };
        
                // AJAX 요청
                $.ajax({
                    url: sbAdmin.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    dataType: 'json'
                })
                .done(function(response) {
                    hideButtonLoading($saveBtn);
        
                    if (response.success) {
                        showSuccessMessage(response.data.message || '설정이 저장되었습니다.');
                    } else {
                        showErrorMessage(response.data.message || '저장 중 오류가 발생했습니다.');
                    }
                })
                .fail(function(xhr, status, error) {
                    hideButtonLoading($saveBtn);
                    showErrorMessage('서버 오류가 발생했습니다. 다시 시도해주세요.');
                });
            });
        }
        
        // 일반 설정 폼 초기화
        $(document).ready(function() {
            if ($('#sb-general-settings-form').length > 0) {
                initGeneralSettingsForm();
            }
        });

        // 9.1 Generate API Key
        $('#sb-generate-key').on('click', function () {
            var $btn = $(this);
            var originalText = $btn.html();
            // v3.1.0: Show visible loading state
            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update sb-spin"></span> ' + __('generating', '발급 중...'));

            $.post(sbAdmin.ajaxUrl, {
                action: 'sb_generate_api_key',
                nonce: sbAdmin.ajaxNonce
            }, function (response) {
                $btn.prop('disabled', false).html(originalText);
                if (response.success) {
                    $('#sb-new-api-key').text(response.data.api_key);
                    $('#sb-new-secret-key').text(response.data.secret_key);

                    var $modal = $('#sb-new-key-modal');
                    $modal.removeClass('sb-hidden').addClass('sb-show');
                    $('body').addClass('sb-modal-open');

                    // v3.1.2 UX Fix: Dynamic append without reload
                    $modal.find('.sb-close-modal').off('click').on('click', function () {
                        $modal.removeClass('sb-show').addClass('sb-hidden');
                        $('body').removeClass('sb-modal-open');
                    });

                    // 1. Remove 'No Keys' placeholder if exists
                    var $tbody = $('#sb-api-keys-list');
                    $tbody.find('.sb-no-keys').remove();

                    // 2. Construct New Row
                    var r = response.data;
                    var $tr = $('<tr data-key-id="' + r.id + '"></tr>');

                    // API Key Column
                    var $td1 = $('<td></td>');
                    $td1.append($('<code class="sb-api-key"></code>').text(r.api_key));
                    $td1.append(' ');
                    $td1.append($('<button type="button" class="button button-small sb-copy-btn"><span class="dashicons dashicons-clipboard"></span></button>').attr('data-copy', r.api_key));
                    $tr.append($td1);

                    // Secret Key Column
                    var $td2 = $('<td></td>');
                    $td2.append('<code class="sb-secret-key sb-masked">••••••••••••••••</code> ');
                    $td2.append($('<code class="sb-secret-key sb-revealed sb-hidden"></code>').text(r.secret_key));
                    $td2.append(' <button type="button" class="button button-small sb-toggle-secret"><span class="dashicons dashicons-visibility"></span></button> ');
                    $td2.append($('<button type="button" class="button button-small sb-copy-btn"><span class="dashicons dashicons-clipboard"></span></button>').attr('data-copy', r.secret_key));
                    $tr.append($td2);

                    // Status Column
                    var activeLabel = (typeof sb_i18n !== 'undefined' && sb_i18n.active) ? sb_i18n.active : '활성';
                    var $td3 = $('<td><span class="sb-status sb-status-active"><span class="dashicons dashicons-yes"></span> ' + activeLabel + '</span></td>');
                    $tr.append($td3);

                    // Date Column
                    var noHistoryLabel = (typeof sb_i18n !== 'undefined' && sb_i18n.no_history) ? sb_i18n.no_history : '사용 기록 없음';
                    var $td4 = $('<td><span class="sb-muted">' + noHistoryLabel + '</span></td>');
                    $tr.append($td4);

                    // Action Column
                    var deleteLabel = (typeof sb_i18n !== 'undefined' && sb_i18n.delete) ? sb_i18n.delete : '삭제';
                    var $td5 = $('<td></td>');
                    $td5.append($('<button type="button" class="button button-small button-link-delete sb-delete-key"></button>')
                        .attr('data-key-id', r.id)
                        .text(deleteLabel));
                    $tr.append($td5);

                    // 3. Prepend to list (DESC order)
                    $tbody.prepend($tr);

                    // Highlight effect
                    $tr.css('background-color', '#fffadd');
                    setTimeout(function () { $tr.css('background-color', ''); }, 2000);

                } else {
                    SB_UI.showToast(response.data.message || 'Error executing request', 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(originalText);
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

        // 9.5 Save General Settings (moved to initSettingsPage function)
        // Note: Duplicate handler removed - the handler in initSettingsPage() is kept
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

        // 9.9 Static HTML Backup (v3.4.0)
        $('#sb-generate-static-backup').on('click', function () {
            var $btn = $(this);
            var $progress = $('#sb-static-backup-progress');
            var $bar = $('#sb-static-backup-bar');
            var $status = $('#sb-static-backup-status');
            var $result = $('#sb-static-backup-result');

            if (!confirm('정적 HTML 백업을 생성하시겠습니까?\n링크 수에 따라 시간이 소요될 수 있습니다.')) {
                return;
            }

            // UI 초기화
            $btn.prop('disabled', true);
            $progress.show();
            $result.hide().empty();
            $bar.css('width', '0%');
            $status.text('초기화 중...');

            var fileId = '';
            var offset = 0;
            var limit = 1000;
            var total = 0;

            function processBatch() {
                $.post(sbAdmin.ajaxUrl, {
                    action: 'sb_generate_static_backup',
                    nonce: sbAdmin.ajaxNonce,
                    offset: offset,
                    limit: limit,
                    file_id: fileId,
                    total_links: total
                }).done(function (res) {
                    if (res.success) {
                        fileId = res.data.file_id;
                        total = parseInt(res.data.total) || 0;
                        offset = parseInt(res.data.offset) || 0;

                        var percent = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
                        $bar.css('width', percent + '%');
                        $status.text(offset + ' / ' + total + ' 처리 완료 (' + percent + '%)');

                        if (!res.data.finished) {
                            processBatch();
                        } else {
                            $btn.prop('disabled', false);
                            $status.text('백업 완료! 총 ' + total + '개 링크');
                            $bar.css('width', '100%');

                            var html = '<a href="' + res.data.download_url + '" class="button button-primary" download>';
                            html += '<span class="dashicons dashicons-download" style="margin-top:4px;"></span> ';
                            html += '백업 파일 다운로드 (.zip)</a>';
                            html += '<p class="description" style="margin-top:10px;">';
                            html += 'sb-assets/loader.js 수정으로 전체 업데이트 가능!</p>';
                            $result.html(html).show();

                            // 자동 다운로드 (v3.4.0)
                            var link = document.createElement('a');
                            link.href = res.data.download_url;
                            link.download = '';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    } else {
                        $btn.prop('disabled', false);
                        $status.text('오류: ' + (res.data.message || 'Unknown'));
                        $bar.css('background', '#d63638');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    $status.text('서버 통신 오류');
                    $bar.css('background', '#d63638');
                });
            }

            processBatch();
        });
    }

    // =========================================================================
    // P2 UX 개선: 탭 기반 UI 함수들
    // =========================================================================

    /**
     * 설정 페이지 탭 기반 UI 초기화
     */
    function initSettingsTabs() {
        // 탭 버튼 클릭 이벤트
        $('.sb-tab-btn').on('click', function () {
            var $btn = $(this);
            var tabId = $btn.data('tab');

            // 활성 탭 변경
            $('.sb-tab-btn').removeClass('active');
            $btn.addClass('active');

            // 탭 컨텐츠 표시
            $('.sb-tab-pane').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            // CodeMirror 리사이즈 (템플릿 탭 활성화 시)
            if (tabId === 'custom-template' && window.sbCodeMirrorEditor) {
                window.sbCodeMirrorEditor.refresh();
            }
        });

        // URL 해시 기반 탭 자동 선택
        var hash = window.location.hash.replace('#', '');
        if (hash) {
            $('.sb-tab-btn[data-tab="' + hash + '"]').click();
        }
    }

    /**
     * CodeMirror 에디터 초기화
     */
    function initCodeMirrorEditor() {
        var $textarea = $('#sb-redirect-template');
        if ($textarea.length === 0) return;

        // CodeMirror가 로드되었는지 확인
        if (typeof CodeMirror === 'undefined') {
            console.warn('CodeMirror 라이브러리가 로드되지 않았습니다. 기본 textarea를 사용합니다.');
            return;
        }

        // CodeMirror 에디터 생성
        var editor = CodeMirror.fromTextArea($textarea[0], {
            mode: 'htmlmixed',
            theme: 'default',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 4,
            tabSize: 4,
            indentWithTabs: false,
            autoCloseBrackets: true,
            autoCloseTags: true,
            matchBrackets: true,
            matchTags: { bothTags: true },
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'Cmd-Space': 'autocomplete',
                'Ctrl-/': 'toggleComment',
                'Cmd-/': 'toggleComment',
                'Ctrl-S': function (cm) {
                    $('#sb-template-form').submit();
                },
                'Cmd-S': function (cm) {
                    $('#sb-template-form').submit();
                }
            },
            hintOptions: {
                completeSingle: false,
                hint: function (cm) {
                    var cur = cm.getCursor();
                    var token = cm.getTokenAt(cur);
                    var start = token.start;
                    var end = cur.ch;
                    var word = token.string.slice(0, end - start);

                    // HTML 템플릿 자동완성
                    var placeholders = [
                        '{{DELAY_SECONDS}}',
                        '{{TARGET_URL}}',
                        '{{COUNTDOWN_SCRIPT}}',
                        '{{COUNTDOWN_ID}}',
                        '<!DOCTYPE html>',
                        '<html>',
                        '<head>',
                        '<body>',
                        '<div>',
                        '<span>',
                        '<style>',
                        '<script>'
                    ];

                    var list = [];
                    for (var i = 0; i < placeholders.length; i++) {
                        if (placeholders[i].toLowerCase().indexOf(word.toLowerCase()) === 0) {
                            list.push({
                                text: placeholders[i],
                                displayText: placeholders[i]
                            });
                        }
                    }

                    return {
                        list: list,
                        from: CodeMirror.Pos(cur.line, start),
                        to: CodeMirror.Pos(cur.line, end)
                    };
                }
            }
        });

        // 전역 변수에 에디터 저장
        window.sbCodeMirrorEditor = editor;

        // 에디터 래퍼 스타일 추가
        editor.getWrapperElement().classList.add('sb-codemirror-wrapper');

        // 변경 감지 (저장되지 않은 변경사항 경고)
        var originalContent = editor.getValue();
        editor.on('change', function () {
            if (editor.getValue() !== originalContent) {
                $('#sb-save-template').addClass('button-primary').prop('disabled', false);
            }
        });

        // 템플릿 저장 시 CodeMirror 값 사용
        $('#sb-template-form').off('submit').on('submit', function (e) {
            e.preventDefault();
            
            var template = editor.getValue();
            var validation = validateTemplate(template, false);

            if (!validation.valid) {
                return;
            }

            var $btn = $('#sb-save-template');
            showButtonLoading($btn);

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
                        originalContent = editor.getValue();
                        showSuccessMessage('템플릿이 성공적으로 저장되었습니다.');
                    } else {
                        showValidation(false, '❌ ' + (response.data.message || (typeof sb_i18n !== 'undefined' ? sb_i18n.save_failed : 'Save Failed')));
                        showErrorMessage('저장 실패: ' + (response.data.message || '알 수 없는 오류'));
                    }
                },
                error: function () {
                    showValidation(false, '❌ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.network_error : 'Network Error'));
                    showErrorMessage('네트워크 오류가 발생했습니다. 다시 시도해주세요.');
                },
                complete: function () {
                    hideButtonLoading($btn);
                }
            });
        });

        // 템플릿 검증 시 CodeMirror 값 사용
        $('#sb-validate-template').off('click').on('click', function () {
            var template = editor.getValue();
            validateTemplate(template, true);
        });

        // 기본값 복원 시 CodeMirror 값 업데이트
        $('#sb-reset-template').off('click').on('click', function () {
            SB_UI.confirm({
                title: typeof sb_i18n !== 'undefined' ? sb_i18n.template_reset : 'Reset Template',
                message: typeof sb_i18n !== 'undefined' ? sb_i18n.template_reset_confirm : 'Reset template to default?',
                yesLabel: typeof sb_i18n !== 'undefined' ? sb_i18n.reset : 'Reset',
                onYes: function () {
                    var $btn = $('#sb-reset-template');
                    showButtonLoading($btn);

                    $.ajax({
                        url: sbAdmin.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'sb_reset_redirect_template',
                            nonce: sbAdmin.ajaxNonce
                        },
                        success: function (response) {
                            if (response.success && response.data.template) {
                                editor.setValue(response.data.template);
                                originalContent = editor.getValue();
                                showValidation(true, '✅ ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.template_restored : 'Template Restored!'));
                                showSuccessMessage('템플릿이 기본값으로 복원되었습니다.');
                            }
                        },
                        complete: function () {
                            hideButtonLoading($btn);
                        }
                    });
                }
            });
        });
    }

    // =========================================================================
    // P2 UX 개선: 진행 상태 피드백 함수들
    // =========================================================================

    /**
     * 버튼 로딩 상태 표시
     */
    function showButtonLoading($btn) {
        $btn.prop('disabled', true).addClass('loading');
    }

    /**
     * 버튼 로딩 상태 숨김
     */
    function hideButtonLoading($btn) {
        $btn.prop('disabled', false).removeClass('loading');
    }

    /**
     * 성공 메시지 표시
     */
    function showSuccessMessage(message) {
        var $msgBox = $('<div class="sb-message-box success">' +
            '<span class="dashicons dashicons-yes-alt"></span>' +
            '<span>' + message + '</span>' +
            '</div>');
        
        // 탭 컨텐츠 영역의 맨 위에 추가
        var $activeTab = $('.sb-tab-pane.active');
        $activeTab.find('.sb-settings-section').first().prepend($msgBox);

        // 5초 후 자동 제거
        setTimeout(function () {
            $msgBox.fadeOut(300, function () {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * 오류 메시지 표시
     */
    function showErrorMessage(message) {
        var $msgBox = $('<div class="sb-message-box error">' +
            '<span class="dashicons dashicons-dismiss"></span>' +
            '<span>' + message + '</span>' +
            '</div>');
        
        // 탭 컨텐츠 영역의 맨 위에 추가
        var $activeTab = $('.sb-tab-pane.active');
        $activeTab.find('.sb-settings-section').first().prepend($msgBox);

        // 7초 후 자동 제거
        setTimeout(function () {
            $msgBox.fadeOut(300, function () {
                $(this).remove();
            });
        }, 7000);
    }

    /**
     * 정보 메시지 표시
     */
    function showInfoMessage(message) {
        var $msgBox = $('<div class="sb-message-box info">' +
            '<span class="dashicons dashicons-info"></span>' +
            '<span>' + message + '</span>' +
            '</div>');
        
        // 탭 컨텐츠 영역의 맨 위에 추가
        var $activeTab = $('.sb-tab-pane.active');
        $activeTab.find('.sb-settings-section').first().prepend($msgBox);

        // 5초 후 자동 제거
        setTimeout(function () {
            $msgBox.fadeOut(300, function () {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * 전체 페이지 로딩 오버레이 표시
     */
    function showPageLoading(message) {
        var $overlay = $('<div class="sb-loading-overlay">' +
            '<span class="spinner is-active"></span>' +
            '<span style="margin-left: 15px; font-size: 16px; font-weight: 500;">' + (message || '처리 중...') + '</span>' +
            '</div>');
        $('body').append($overlay);
    }

    /**
     * 전체 페이지 로딩 오버레이 숨김
     */
    function hidePageLoading() {
        $('.sb-loading-overlay').fadeOut(200, function () {
            $(this).remove();
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

    /**
     * Update Summary Cards (v3.0.4)
     */
    function updateSummaryStats(summary) {
        if (!summary) return;

        // 1. Update Values
        $('#sb-today-total').text(new Intl.NumberFormat().format(summary.total_clicks));
        $('#sb-today-unique').text(new Intl.NumberFormat().format(summary.unique_visitors));

        // Growth Rate
        var $growthElem = $('#sb-growth-rate');
        var rate = parseFloat(summary.growth_rate);
        var rateText = (rate >= 0 ? '+' : '') + rate + '%';
        $growthElem.text(rateText);
        $growthElem.removeClass('positive negative').addClass(rate >= 0 ? 'positive' : 'negative');

        // Icon update
        $('.sb-icon-growth .dashicons').removeClass('dashicons-arrow-up-alt dashicons-arrow-down-alt')
            .addClass(rate >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt');
        $('.sb-icon-growth').removeClass('positive negative').addClass(rate >= 0 ? 'positive' : 'negative');

        // 2. Update Labels based on Range
        var range = $('#sb-date-range').val();
        var labelTotal = __('period_total_clicks', '기간 전체 클릭');
        var labelUnique = __('period_unique_visitors', '기간 고유 클릭 (UV)');
        var subLabel = __('selected_period', '📅 선택 기간');
        var growthLabel = __('period_growth_rate', '전 기간 대비 증감률');

        // 기간별 라벨 설정
        switch (range) {
            case 'today_7d':
                subLabel = __('today_7d', '📅 오늘 + 최근 7일');
                break;
            case '30d':
                subLabel = __('last_30d', '📅 최근 30일');
                break;
            case '90d':
                subLabel = __('last_90d', '📅 최근 3개월');
                break;
            case '180d':
                subLabel = __('last_180d', '📅 최근 6개월');
                break;
            case '365d':
                subLabel = __('last_365d', '📅 최근 12개월');
                break;
        }

        // Update Label Text (Traversing DOM relative to value ID)
        $('#sb-today-total').closest('.sb-card-content').find('.sb-card-label').text(labelTotal);
        $('#sb-today-total').closest('.sb-card-content').find('.sb-card-sublabel').text(subLabel);

        $('#sb-today-unique').closest('.sb-card-content').find('.sb-card-label').text(labelUnique);
        $('#sb-today-unique').closest('.sb-card-content').find('.sb-card-sublabel').text(subLabel);

        // 증감률 카드 라벨 업데이트
        $('#sb-growth-rate').closest('.sb-card-content').find('.sb-card-label').text(growthLabel);
    }

    /**
     * Update Top Links Table (v3.0.4)
     *
     * 🔍 DEBUG LOG: XSS 취약성 확인
     * - 직접 문자열 연결로 HTML 생성하는지 확인
     * - jQuery DOM 생성 방식 사용 여부 확인
     */
    function updateTopLinksTable(links) {
        console.log('[DEBUG] updateTopLinksTable called with links:', links);
        
        // Target specifically the "Today" links panel which serves as the "Filtered" view
        var $panel = $('#sb-today-links');
        var $tbody = $panel.find('tbody');
        $tbody.empty();

        if (!links || links.length === 0) {
            $tbody.append($('<tr>').append(
                $('<td>').attr('colspan', '6').addClass('sb-empty-state').text(__('no_data', '데이터가 없습니다.'))
            ));
            console.log('[DEBUG] No links to display');
            return;
        }

        console.log('[DEBUG] Processing ' + links.length + ' links');
        
        links.forEach(function (link, index) {
            console.log('[DEBUG] Processing link #' + (index + 1) + ':', link);
            
            // XSS 취약성 수정: jQuery DOM 생성 방식 사용 (자동 이스케이프 적용)
            var $row = $('<tr>');

            // # ID
            $row.append($('<td>').attr('data-label', '#').text(index + 1));

            // Slug / Title
            var $slugCell = $('<td>').attr('data-label', 'Slug');
            $slugCell.append($('<strong>').append(
                $('<a>').attr('href', link.short_link).attr('target', '_blank').text(link.slug)
            ));
            if (link.group_name) {
                $slugCell.append(' ').append(
                    $('<span>').addClass('sb-group-badge').css('background-color', link.group_color || '#667eea').text(link.group_name)
                );
            }
            // v4.0.0: 파라미터 방식으로 변경
            $slugCell.append($('<br>')).append(
                $('<small>').addClass('sb-slug-copy').attr('data-url', link.short_link).text('?go=' + link.slug)
            );
            $row.append($slugCell);

            // Target URL
            var targetUrlText = link.target_url.length > 40 ? link.target_url.substring(0, 40) + '...' : link.target_url;
            $row.append($('<td>').attr('data-label', __('target_url', '타겟 URL')).append(
                $('<a>').attr('href', link.target_url).attr('target', '_blank').addClass('sb-target-url').attr('title', link.target_url)
                    .text(targetUrlText)
            ));

            // Platform
            var platformClass = 'sb-platform-' + (link.platform ? link.platform.toLowerCase() : 'unknown');
            $row.append($('<td>').attr('data-label', __('platform', '플랫폼')).append(
                $('<span>').addClass('sb-platform-badge ' + platformClass).text(link.platform || 'General')
            ));

            // Clicks
            $row.append($('<td>').attr('data-label', __('clicks', '클릭 수')).append(
                $('<strong>').text(new Intl.NumberFormat().format(link.clicks))
            ));

            // Action
            $row.append($('<td>').attr('data-label', __('actions', '액션')).append(
                $('<a>').attr('href', sbAdmin.adminUrl + 'post.php?post=' + link.id + '&action=edit').addClass('button button-small').text(__('edit', '수정'))
            ));

            $tbody.append($row);
        });
        
        console.log('[DEBUG] updateTopLinksTable completed');
    }

    // =========================================================================
    // Link Management (Post List) Enhancements
    // =========================================================================
    
    /**
     * Mobile Card View Enhancement
     * Adds data-label attributes to table cells for mobile card view
     */
    function initLinkManagementEnhancements() {
        if (!$('body').hasClass('post-type-sb_link')) {
            return;
        }

        // Add data-label attributes to table cells
        $('.wp-list-table thead th').each(function() {
            var $th = $(this);
            var headerText = $th.text().trim();
            var columnIndex = $th.index();
            
            // Add data-label to corresponding cells in tbody
            $('.wp-list-table tbody tr').each(function() {
                var $td = $(this).find('td').eq(columnIndex);
                if ($td.length > 0) {
                    $td.attr('data-label', headerText);
                }
            });
        });

        // Add filter count update on filter change
        $('.sb-admin-filter').on('change', updateFilterCount);
        $('#post-query-submit').on('click', function() {
            setTimeout(updateFilterCount, 500);
        });

        // Initial filter count update
        updateFilterCount();

        // Initialize bulk actions
        initBulkActions();
    }

    /**
     * 일괄 작업(Bulk Actions) UI 초기화
     */
    function initBulkActions() {
        if (!$('body').hasClass('post-type-sb_link')) {
            return;
        }

        // 체크박스 선택 시 일괄 작업 UI 표시/숨김
        $('.wp-list-table .column-cb input[type="checkbox"]').on('change', function() {
            var checkedCount = $('.wp-list-table .column-cb input[type="checkbox"]:checked').length;
            var $bulkContainer = $('#sb-bulk-actions-container');
            
            if (checkedCount > 0) {
                $bulkContainer.slideDown(200);
            } else {
                $bulkContainer.slideUp(200);
            }
        });

        // 일괄 작업 드롭다운 변경 시 플랫폼 선택 UI 표시/숨김
        $('#sb_bulk_action').on('change', function() {
            var action = $(this).val();
            var $platformSelect = $('#sb_bulk_platform_select');
            
            if (action === 'sb_bulk_update_platform') {
                $platformSelect.slideDown(200);
            } else {
                $platformSelect.slideUp(200);
            }
        });

        // 일괄 작업 적용 버튼 클릭 시
        $('#sb_bulk_apply').on('click', function(e) {
            e.preventDefault();
            
            var action = $('#sb_bulk_action').val();
            var checkedCount = $('.wp-list-table .column-cb input[type="checkbox"]:checked').length;
            
            if (!action) {
                alert('일괄 작업을 선택해주세요.');
                return;
            }
            
            if (checkedCount === 0) {
                alert('최소 하나 이상의 링크를 선택해주세요.');
                return;
            }
            
            if (action === 'sb_bulk_update_platform') {
                var platform = $('#sb_bulk_platform').val();
                if (!platform) {
                    alert('플랫폼을 선택해주세요.');
                    return;
                }
            }
            
            // 폼 제출
            $('form#posts-filter').submit();
        });

        // 정렬 해제 버튼 클릭 시
        $('#sb_clear_sorting').on('click', function(e) {
            e.preventDefault();
            
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('orderby');
            currentUrl.searchParams.delete('order');
            window.location.href = currentUrl.toString();
        });
    }

    /**
     * Update Filter Count Display
     */
    function updateFilterCount() {
        var $countValue = $('.sb-filter-count-value');
        if ($countValue.length === 0) return;

        // Count visible rows
        var visibleCount = $('.wp-list-table tbody tr:visible').length;
        $countValue.text(visibleCount.toLocaleString());
    }

    // Initialize link management enhancements on page load
    $(document).ready(function() {
        initLinkManagementEnhancements();
    });

    // =========================================================================
    // P3 기능 개선: 업데이트 및 롤백 JavaScript
    // =========================================================================

    /**
     * 업데이트 및 롤백 기능 초기화
     */
    function initUpdateRollback() {
        // 업데이트 확인 버튼
        $('#sb-check-update').on('click', function() {
            checkForUpdate();
        });

        // 업데이트 다운로드 버튼
        $('#sb-download-update').on('click', function() {
            downloadUpdate();
        });

        // 업데이트 알림 숨기기 버튼
        $('#sb-dismiss-update').on('click', function() {
            dismissUpdateNotice();
        });

        // 업데이트 로그 삭제 버튼
        $('#sb-clear-update-logs').on('click', function() {
            clearUpdateLogs();
        });

        // 롤백 백업 파일 목록 로드
        loadRollbackBackups();

        // 롤백 로그 로드
        loadRollbackLogs();

        // 오래된 백업 정리 버튼
        $('#sb-cleanup-rollback-backups').on('click', function() {
            cleanupRollbackBackups();
        });

        // 페이지 로드 시 업데이트 상태 확인
        loadUpdateStatus();
    }

    /**
     * 업데이트 확인
     */
    function checkForUpdate() {
        var $btn = $('#sb-check-update');
        var $status = $('#sb-update-status');
        
        showButtonLoading($btn);
        $status.html('<span class="spinner is-active"></span> 확인 중...');

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_check_update',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    renderUpdateStatus(response.data);
                } else {
                    $status.html('<div class="notice notice-error"><p>업데이트 확인 실패</p></div>');
                }
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>서버 통신 오류</p></div>');
            },
            complete: function() {
                hideButtonLoading($btn);
            }
        });
    }

    /**
     * 업데이트 상태 렌더링
     */
    function renderUpdateStatus(data) {
        var $status = $('#sb-update-status');
        var $downloadBtn = $('#sb-download-update');
        var $dismissBtn = $('#sb-dismiss-update');

        if (data.has_update) {
            var html = '<div class="notice notice-info is-dismissible" style="padding: 10px;">';
            html += '<p><strong>🎉 새 버전이 있습니다!</strong></p>';
            html += '<p>현재 버전: <strong>' + data.current_version + '</strong></p>';
            html += '<p>최신 버전: <strong>' + data.new_version.version + '</strong></p>';
            if (data.new_version.release_notes) {
                html += '<p><details><summary>릴리스 노트</summary><pre style="margin-top:10px; padding:10px; background:#f9f9f9; border:1px solid #ddd;">' +
                    escapeHtml(data.new_version.release_notes) + '</pre></details></p>';
            }
            html += '</div>';
            $status.html(html);
            
            $downloadBtn.show();
            $dismissBtn.show();
        } else {
            $status.html('<div class="notice notice-success"><p>✅ 최신 버전을 사용 중입니다! (v' + data.current_version + ')</p></div>');
            $downloadBtn.hide();
            $dismissBtn.hide();
        }

        // 업데이트 로그 렌더링
        renderUpdateLogs(data.recent_logs);
    }

    /**
     * 업데이트 로그 렌더링
     */
    function renderUpdateLogs(logs) {
        var $logsContainer = $('#sb-update-logs');
        
        if (!logs || logs.length === 0) {
            $logsContainer.html('<p class="sb-muted">로그가 없습니다.</p>');
            return;
        }

        var html = '<ul style="margin: 0; padding-left: 20px;">';
        logs.forEach(function(log) {
            var actionLabel = getActionLabel(log.action);
            var date = new Date(log.timestamp);
            var dateStr = date.toLocaleDateString('ko-KR') + ' ' + date.toLocaleTimeString('ko-KR');
            
            html += '<li style="margin-bottom: 5px;">';
            html += '<small style="color: #666;">[' + dateStr + ']</small> ';
            html += '<strong>' + actionLabel + '</strong>';
            if (log.version) {
                html += ' (v' + log.version + ')';
            }
            if (log.message) {
                html += ': ' + escapeHtml(log.message);
            }
            html += '</li>';
        });
        html += '</ul>';
        
        $logsContainer.html(html);
    }

    /**
     * 업데이트 다운로드
     */
    function downloadUpdate() {
        var $btn = $('#sb-download-update');
        var $status = $('#sb-update-status');
        
        // 다운로드 URL 확인
        var downloadUrl = $status.find('a').attr('href');
        if (!downloadUrl) {
            // 새 버전 정보에서 URL 가져오기
            $.ajax({
                url: sbAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'sb_get_update_status',
                    nonce: sbAdmin.ajaxNonce
                },
                success: function(response) {
                    if (response.success && response.data.new_version && response.data.new_version.download_url) {
                        performDownload(response.data.new_version.download_url);
                    } else {
                        SB_UI.showToast('다운로드 URL을 찾을 수 없습니다.', 'error');
                    }
                }
            });
            return;
        }
        
        performDownload(downloadUrl);
    }

    /**
     * 다운로드 실행
     */
    function performDownload(downloadUrl) {
        var $btn = $('#sb-download-update');
        
        showButtonLoading($btn);

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_download_update',
                nonce: sbAdmin.ajaxNonce,
                download_url: downloadUrl
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('업데이트 파일 다운로드 완료!', 'success');
                    loadUpdateStatus(); // 상태 새로고침
                } else {
                    SB_UI.showToast('다운로드 실패: ' + response.data.message, 'error');
                }
            },
            error: function() {
                SB_UI.showToast('서버 통신 오류', 'error');
            },
            complete: function() {
                hideButtonLoading($btn);
            }
        });
    }

    /**
     * 업데이트 알림 숨기기
     */
    function dismissUpdateNotice() {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_dismiss_update_notice',
                nonce: sbAdmin.ajaxNonce,
                version: $('#sb-update-status').find('strong').text().match(/v([\d.]+)/)[1]
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('알림이 숨겨졌습니다.', 'success');
                    $('#sb-dismiss-update').hide();
                }
            }
        });
    }

    /**
     * 업데이트 로그 삭제
     */
    function clearUpdateLogs() {
        if (!confirm('모든 업데이트 로그를 삭제하시겠습니까?')) {
            return;
        }

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_clear_update_logs',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('업데이트 로그가 삭제되었습니다.', 'success');
                    loadUpdateStatus();
                }
            }
        });
    }

    /**
     * 업데이트 상태 로드
     */
    function loadUpdateStatus() {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_get_update_status',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    renderUpdateStatus(response.data);
                }
            }
        });
    }

    /**
     * 롤백 백업 파일 목록 로드
     */
    function loadRollbackBackups() {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_get_rollback_backups',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    renderRollbackBackups(response.data.backups);
                }
            }
        });
    }

    /**
     * 롤백 백업 파일 목록 렌더링
     */
    function renderRollbackBackups(backups) {
        var $container = $('#sb-rollback-backups-list');
        
        if (!backups || backups.length === 0) {
            $container.html('<p class="sb-muted">사용 가능한 백업 파일이 없습니다.</p>');
            return;
        }

        var html = '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
        html += '<thead><tr>';
        html += '<th>파일명</th>';
        html += '<th>버전</th>';
        html += '<th>생성일</th>';
        html += '<th>크기</th>';
        html += '<th>액션</th>';
        html += '</tr></thead><tbody>';

        backups.forEach(function(backup) {
            html += '<tr>';
            html += '<td><code>' + escapeHtml(backup.filename) + '</code></td>';
            html += '<td>v' + escapeHtml(backup.version) + '</td>';
            html += '<td>' + escapeHtml(backup.created_at) + '</td>';
            html += '<td>' + escapeHtml(backup.size) + '</td>';
            html += '<td>';
            html += '<button type="button" class="button button-small sb-rollback-btn" data-filename="' + escapeHtml(backup.filename) + '">롤백</button>';
            html += ' ';
            html += '<button type="button" class="button button-small button-link-delete sb-delete-backup-btn" data-filename="' + escapeHtml(backup.filename) + '">삭제</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);

        // 롤백 버튼 이벤트
        $('.sb-rollback-btn').on('click', function() {
            var filename = $(this).data('filename');
            performRollback(filename);
        });

        // 삭제 버튼 이벤트
        $('.sb-delete-backup-btn').on('click', function() {
            var filename = $(this).data('filename');
            deleteRollbackBackup(filename);
        });
    }

    /**
     * 롤백 실행
     */
    function performRollback(filename) {
        var confirmMsg = '백업 파일 "' + filename + '"로 롤백하시겠습니까?\n\n';
        confirmMsg += '⚠️ 롤백 전 현재 데이터가 자동으로 백업됩니다.\n';
        confirmMsg += '⚠️ 이 작업은 되돌릴 수 없습니다.';
        
        if (!confirm(confirmMsg)) {
            return;
        }

        showPageLoading('롤백 중입니다... 페이지를 닫지 마세요.');

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_perform_rollback',
                nonce: sbAdmin.ajaxNonce,
                backup_file: filename,
                auto_backup: true
            },
            success: function(response) {
                if (response.success) {
                    var msg = '롤백이 완료되었습니다!\n\n';
                    msg += '복원된 링크: ' + response.data.stats.links + '개\n';
                    msg += '복원된 로그: ' + response.data.stats.analytics + '개';
                    
                    if (response.data.pre_rollback_backup) {
                        msg += '\n사전 백업: ' + response.data.pre_rollback_backup;
                    }
                    
                    alert(msg);
                    location.reload();
                } else {
                    hidePageLoading();
                    SB_UI.showToast('롤백 실패: ' + response.data.message, 'error');
                }
            },
            error: function() {
                hidePageLoading();
                SB_UI.showToast('서버 통신 오류', 'error');
            }
        });
    }

    /**
     * 롤백 백업 파일 삭제
     */
    function deleteRollbackBackup(filename) {
        if (!confirm('백업 파일 "' + filename + '"을 삭제하시겠습니까?')) {
            return;
        }

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_delete_rollback_backup',
                nonce: sbAdmin.ajaxNonce,
                filename: filename
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('백업 파일이 삭제되었습니다.', 'success');
                    loadRollbackBackups();
                    loadRollbackLogs();
                } else {
                    SB_UI.showToast('삭제 실패: ' + response.data.message, 'error');
                }
            }
        });
    }

    /**
     * 롤백 로그 로드
     */
    function loadRollbackLogs() {
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_get_rollback_logs',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    renderRollbackLogs(response.data.logs);
                }
            }
        });
    }

    /**
     * 롤백 로그 렌더링
     */
    function renderRollbackLogs(logs) {
        var $logsContainer = $('#sb-rollback-logs');
        
        if (!logs || logs.length === 0) {
            $logsContainer.html('<p class="sb-muted">로그가 없습니다.</p>');
            return;
        }

        var html = '<ul style="margin: 0; padding-left: 20px;">';
        logs.forEach(function(log) {
            var actionLabel = getRollbackActionLabel(log.action);
            var date = new Date(log.timestamp);
            var dateStr = date.toLocaleDateString('ko-KR') + ' ' + date.toLocaleTimeString('ko-KR');
            
            var actionClass = '';
            if (log.action === 'success') actionClass = 'color: #00a32a;';
            else if (log.action === 'fail') actionClass = 'color: #d63638;';
            else if (log.action === 'warning') actionClass = 'color: #dba617;';
            
            html += '<li style="margin-bottom: 5px;">';
            html += '<small style="color: #666;">[' + dateStr + ']</small> ';
            html += '<strong style="' + actionClass + '">' + actionLabel + '</strong>';
            if (log.backup_file) {
                html += ' (' + escapeHtml(log.backup_file) + ')';
            }
            if (log.message) {
                html += ': ' + escapeHtml(log.message);
            }
            html += '</li>';
        });
        html += '</ul>';
        
        $logsContainer.html(html);
    }

    /**
     * 롤백 로그 삭제
     */
    function clearRollbackLogs() {
        if (!confirm('모든 롤백 로그를 삭제하시겠습니까?')) {
            return;
        }

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_clear_rollback_logs',
                nonce: sbAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('롤백 로그가 삭제되었습니다.', 'success');
                    loadRollbackLogs();
                }
            }
        });
    }

    /**
     * 오래된 롤백 백업 파일 정리
     */
    function cleanupRollbackBackups() {
        if (!confirm('30일 이상 된 백업 파일을 정리하시겠습니까?')) {
            return;
        }

        var $btn = $('#sb-cleanup-rollback-backups');
        showButtonLoading($btn);

        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_cleanup_rollback_backups',
                nonce: sbAdmin.ajaxNonce,
                days_old: 30
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast(response.data.message, 'success');
                    loadRollbackBackups();
                    loadRollbackLogs();
                }
            },
            complete: function() {
                hideButtonLoading($btn);
            }
        });
    }

    /**
     * 업데이트 액션 라벨 반환
     */
    function getActionLabel(action) {
        var labels = {
            'check': '확인',
            'download': '다운로드',
            'install': '설치',
            'success': '성공',
            'fail': '실패'
        };
        return labels[action] || action;
    }

    /**
     * 롤백 액션 라벨 반환
     */
    function getRollbackActionLabel(action) {
        var labels = {
            'backup': '백업',
            'success': '성공',
            'fail': '실패',
            'warning': '경고',
            'delete': '삭제',
            'cleanup': '정리'
        };
        return labels[action] || action;
    }

    /**
     * HTML 이스케이프
     */
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
    }

    // 업데이트 및 롤백 기능 초기화
    $(document).ready(function() {
        if ($('#tab-update-rollback').length > 0) {
            initUpdateRollback();
        }
    });

    // =========================================================================
    // 링크 관리 탭: 새 링크 추가 모달 창 기능
    // =========================================================================

    /**
     * 새 링크 추가 모달 창 열기
     */
    function openAddLinkModal() {
        // 폼 초기화
        $('#sb-add-link-form')[0].reset();
        
        // 모달 창 표시
        SB_UI.openModal('#sb-add-link-modal');
    }

    /**
     * 새 링크 추가 모달 창 닫기
     */
    function closeAddLinkModal() {
        SB_UI.closeModal('#sb-add-link-modal');
    }

    /**
     * 링크 생성 처리
     */
    function createLinkFromAdmin() {
        var $form = $('#sb-add-link-form');
        var $submitBtn = $('#sb-modal-submit');
        
        // 폼 데이터 수집
        var targetUrl = $('#sb_modal_target_url').val().trim();
        var slug = $('#sb_modal_slug').val().trim();
        var platform = $('#sb_modal_platform').val().trim();
        
        // 유효성 검증
        if (!targetUrl) {
            SB_UI.showToast('타겟 URL을 입력해주세요.', 'error');
            return;
        }
        
        if (targetUrl.length > 2083) {
            SB_UI.showToast('URL이 너무 깁니다. 최대 2083바이트까지 허용됩니다.', 'error');
            return;
        }
        
        // URL 형식 검증
        try {
            new URL(targetUrl);
        } catch (e) {
            SB_UI.showToast('유효하지 않은 URL 형식입니다.', 'error');
            return;
        }
        
        // 로딩 상태 표시
        $submitBtn.prop('disabled', true).text('생성 중...');
        
        // AJAX 요청
        $.ajax({
            url: sbAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'sb_create_link_from_admin',
                nonce: sbAdmin.ajaxNonce,
                target_url: targetUrl,
                slug: slug,
                platform: platform
            },
            success: function(response) {
                if (response.success) {
                    SB_UI.showToast('링크가 성공적으로 생성되었습니다!', 'success');
                    
                    // 모달 창 닫기
                    closeAddLinkModal();
                    
                    // 페이지 새로고침 (새 링크가 목록에 표시되도록)
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    SB_UI.showToast('링크 생성 실패: ' + (response.data.message || '알 수 없는 오류'), 'error');
                }
            },
            error: function() {
                SB_UI.showToast('서버 통신 오류가 발생했습니다. 다시 시도해주세요.', 'error');
            },
            complete: function() {
                // 로딩 상태 해제
                $submitBtn.prop('disabled', false).text('생성');
            }
        });
    }

    // 링크 관리 탭에서만 실행
    $(document).ready(function() {
        if ($('body').hasClass('post-type-sb_link')) {
            // "새 링크 추가" 버튼 클릭 이벤트
            $(document).on('click', '#sb-open-add-link-modal', function(e) {
                e.preventDefault();
                openAddLinkModal();
            });
            
            // 모달 창 닫기 버튼 클릭 이벤트
            $(document).on('click', '#sb-add-link-modal .sb-modal-close, #sb-add-link-modal .sb-modal-overlay, #sb-modal-cancel', function(e) {
                e.preventDefault();
                closeAddLinkModal();
            });
            
            // 폼 제출 이벤트
            $(document).on('click', '#sb-modal-submit', function(e) {
                e.preventDefault();
                createLinkFromAdmin();
            });
            
            // ESC 키로 모달 창 닫기
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    var $modal = $('#sb-add-link-modal');
                    if ($modal.hasClass('sb-show')) {
                        closeAddLinkModal();
                    }
                }
            });
        }
    });

})(jQuery);
