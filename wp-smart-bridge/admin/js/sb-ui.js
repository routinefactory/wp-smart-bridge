/**
 * Smart Bridge UI Module
 * Handles DOM manipulation, Modals, and Templates.
 * 
 * @package WP_Smart_Bridge
 * @since 2.9.22
 */

var SB_UI = (function ($) {
    'use strict';

    return {
        /**
         * Safe HTML Text Injection (XSS Prevention)
         * @param {string} selector 
         * @param {string} text 
         */
        setText: function (selector, text) {
            var el = document.querySelector(selector);
            if (el) el.textContent = text;
        },

        /**
         * Show Toast Notification (v2.9.22 Added)
         * @param {string} message 
         * @param {string} type 'success' | 'error' | 'info'
         */
        showToast: function (message, type) {
            type = type || 'info';
            var $container = $('#sb-toast-container');

            if ($container.length === 0) {
                $container = $('<div id="sb-toast-container"></div>');
                $('body').append($container);
            }

            var $toast = $('<div class="sb-toast ' + type + '"></div>');
            var icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');

            // XSS Safe: Use jQuery DOM construction instead of .html()
            var $span = $('<span></span>').text(icon + ' ' + message);
            var $closeBtn = $('<button class="sb-toast-close" aria-label="Close">&times;</button>');
            $toast.append($span).append($closeBtn);

            $container.append($toast);

            // Close Logic
            $toast.find('.sb-toast-close').on('click', function () {
                $toast.remove();
            });

            // Auto Dismiss
            setTimeout(function () {
                $toast.css('animation', 'sb-fade-out 0.5s forwards');
                setTimeout(function () { $toast.remove(); }, 500);
            }, type === 'error' ? 5000 : 3000);
        },

        /**
         * Open Modal
         * @param {string} modalId 
         */
        openModal: function (modalId) {
            var $modal = $(modalId);
            $modal.fadeIn(200);
            $('body').addClass('sb-modal-open');

            // A11y: Focus first focusable element
            setTimeout(function () {
                var $focusable = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                if ($focusable.length) $focusable.first().focus();

                // A11y: Focus Trap
                $modal.on('keydown.sbTrap', function (e) {
                    var isTabPressed = (e.key === 'Tab' || e.keyCode === 9);
                    if (!isTabPressed) return;

                    var $first = $focusable.first();
                    var $last = $focusable.last();

                    if (e.shiftKey) { // Shift + Tab
                        if (document.activeElement === $first[0]) {
                            $last.focus();
                            e.preventDefault();
                        }
                    } else { // Tab
                        if (document.activeElement === $last[0]) {
                            $first.focus();
                            e.preventDefault();
                        }
                    }
                });
            }, 250);

            // A11y: Esc key to close
            $(document).on('keydown.sbModal', function (e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    SB_UI.closeModal();
                }
            });
        },

        /**
         * Close Modal
         */
        closeModal: function (target) {
            if (target) {
                $(target).closest('.sb-modal').fadeOut(200);
            } else {
                $('.sb-modal').fadeOut(200);
            }
            $('body').removeClass('sb-modal-open');

            // A11y: Remove Esc key handler & Focus Trap
            $(document).off('keydown.sbModal');
            $('.sb-modal').off('keydown.sbTrap');
        },

        /**
         * Render Anomaly List using <template>
         */
        renderAnomalies: function (data) {
            var $section = $('#sb-anomaly-section');
            var $content = $('#sb-anomaly-content');
            var template = document.getElementById('sb-tmpl-anomaly-item');

            if (data.message || (data.spikes.length === 0 && data.drops.length === 0)) {
                $section.hide();
                return;
            }

            if (!template) {
                console.error('SB_UI: Template #sb-tmpl-anomaly-item not found.');
                return;
            }

            $section.show();
            $content.empty();

            function appendItem(item, type) {
                var clone = template.content.cloneNode(true);
                var div = clone.querySelector('.sb-anomaly-item');
                div.classList.add(type); // 'spike' or 'drop'

                clone.querySelector('.sb-tmpl-date').textContent = (type === 'spike' ? '📈 ' : '📉 ') + item.date;
                clone.querySelector('.sb-tmpl-clicks').textContent = item.clicks;
                clone.querySelector('.sb-tmpl-desc').textContent = ' ' + (typeof sb_i18n !== 'undefined' ? sb_i18n.click_unit : 'clicks') + ' (' + (type === 'spike' ? '+' : '') + item.deviation + 'σ)';

                $content.append(clone);
            }

            data.spikes.forEach(function (item) { appendItem(item, 'spike'); });
            data.drops.forEach(function (item) { appendItem(item, 'drop'); });
        },

        /**
         * Generic Alert Modal
         * @param {string} message 
         * @param {string} title 
         */
        alert: function (message, title) {
            title = title || (typeof sb_i18n !== 'undefined' ? sb_i18n.title_alert : 'Alert');
            this.confirm({
                title: title,
                message: message,
                yesLabel: (typeof sb_i18n !== 'undefined' ? sb_i18n.close : 'Close'),
                hideCancel: true
            });
        },

        /**
         * Generic Confirm Modal
         * @param {object} options { title, message, yesLabel, noLabel, onYes, hideCancel }
         */
        confirm: function (options) {
            options = $.extend({
                title: (typeof sb_i18n !== 'undefined' ? sb_i18n.title_confirm : 'Confirm'),
                message: '',
                yesLabel: (typeof sb_i18n !== 'undefined' ? sb_i18n.yes : 'Yes'),
                noLabel: (typeof sb_i18n !== 'undefined' ? sb_i18n.no : 'No'),
                onYes: function () { },
                hideCancel: false
            }, options);

            var modalId = 'sb-generic-modal';
            var $modal = $('#' + modalId);

            if ($modal.length === 0) {
                var html = '<div id="' + modalId + '" class="sb-modal" style="display:none; z-index:100001;">';
                html += '<div class="sb-modal-overlay"></div>';
                html += '<div class="sb-modal-content" style="max-width: 400px;">';
                html += '<div class="sb-modal-header">';
                html += '<h2>' + options.title + '</h2>';
                html += '<button type="button" class="sb-modal-close">&times;</button>';
                html += '</div>';
                html += '<div class="sb-modal-body">';
                html += '<p id="sb-confirm-message"></p>';
                html += '<div class="sb-modal-actions" style="margin-top:20px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">';
                html += '<button type="button" class="button button-secondary sb-btn-cancel">' + options.noLabel + '</button>';
                html += '<button type="button" class="button button-primary sb-btn-yes">' + options.yesLabel + '</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';

                $('body').append(html);
                $modal = $('#' + modalId);

                // Event Bindings
                $modal.find('.sb-modal-close, .sb-modal-overlay, .sb-btn-cancel').on('click', function () {
                    $modal.fadeOut(200);
                });
            }

            // Update Content (XSS Safe: use .text() for user-provided content)
            $modal.find('h2').text(options.title);
            // Note: If HTML is truly needed, caller must sanitize. Default to .text() for safety.
            if (options.allowHtml === true) {
                $modal.find('#sb-confirm-message').html(options.message);
            } else {
                $modal.find('#sb-confirm-message').text(options.message);
            }
            $modal.find('.sb-btn-yes').text(options.yesLabel);
            $modal.find('.sb-btn-cancel').text(options.noLabel);

            if (options.hideCancel) {
                $modal.find('.sb-btn-cancel').hide();
            } else {
                $modal.find('.sb-btn-cancel').show();
            }

            // Re-bind Confirm Action
            $modal.find('.sb-btn-yes').off('click').on('click', function () {
                options.onYes();
                $modal.fadeOut(200);
            });

            $modal.fadeIn(200);
        },

        /**
         * Generic Prompt Modal
         * @param {object} options { title, message, placeholder, onSubmit }
         */
        prompt: function (options) {
            options = $.extend({
                title: (typeof sb_i18n !== 'undefined' ? sb_i18n.title_prompt : 'Input'),
                message: '',
                placeholder: '',
                onSubmit: function (value) { }
            }, options);

            var modalId = 'sb-prompt-modal';
            var $modal = $('#' + modalId);

            if ($modal.length === 0) {
                var html = '<div id="' + modalId + '" class="sb-modal" style="display:none; z-index:100002;">';
                html += '<div class="sb-modal-overlay"></div>';
                html += '<div class="sb-modal-content" style="max-width: 400px;">';
                html += '<div class="sb-modal-header">';
                html += '<h2>' + options.title + '</h2>';
                html += '<button type="button" class="sb-modal-close">&times;</button>';
                html += '</div>';
                html += '<div class="sb-modal-body">';
                html += '<p>' + options.message + '</p>';
                html += '<input type="text" id="sb-prompt-input" class="large-text" style="width:100%; margin:10px 0; padding:8px; border:1px solid #ddd; border-radius:4px;">';
                html += '<div class="sb-modal-actions" style="margin-top:20px; text-align:right;">';
                html += '<button type="button" class="button button-primary sb-btn-submit">' + (typeof sb_i18n !== 'undefined' ? sb_i18n.title_confirm : 'Confirm') + '</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';

                $('body').append(html);
                $modal = $('#' + modalId);

                $modal.find('.sb-modal-close, .sb-modal-overlay').on('click', function () {
                    $modal.fadeOut(200);
                });
            }

            $modal.find('h2').text(options.title);
            $modal.find('p').text(options.message);

            var $input = $modal.find('#sb-prompt-input');
            $input.val('').attr('placeholder', options.placeholder).focus();

            $modal.find('.sb-btn-submit').off('click').on('click', function () {
                var val = $input.val();
                if (val) {
                    options.onSubmit(val);
                    $modal.fadeOut(200);
                }
            });

            // Allow Enter key
            $input.off('keypress').on('keypress', function (e) {
                if (e.which == 13) {
                    $modal.find('.sb-btn-submit').click();
                }
            });

            $modal.fadeIn(200);
        },

        /**
         * Render Link Referers using <template>
         */
        renderReferers: function (data) {
            var $referers = $('#sb-link-referers');
            var template = document.getElementById('sb-tmpl-referer-item');
            $referers.empty();

            if (data.length === 0) {
                var div = document.createElement('div');
                div.className = 'sb-referer-item';
                div.textContent = (typeof sb_i18n !== 'undefined' ? sb_i18n.no_data : 'No Data');
                $referers.append(div);
                return;
            }

            if (!template) return;

            data.forEach(function (item) {
                var clone = template.content.cloneNode(true);
                clone.querySelector('.sb-tmpl-domain').textContent = item.referer_domain;
                clone.querySelector('.sb-tmpl-clicks').textContent = parseInt(item.clicks).toLocaleString();
                $referers.append(clone);
            });
        },

        /**
         * Render Link Device Bars using <template>
         */
        renderDeviceBars: function (data) {
            var $devices = $('#sb-link-device-bars');
            var template = document.getElementById('sb-tmpl-device-bar');
            $devices.empty();

            if (!template) return;

            var deviceData = data.devices;
            Object.keys(deviceData).forEach(function (device) {
                var clone = template.content.cloneNode(true);
                clone.querySelector('.sb-tmpl-value').textContent = deviceData[device].toLocaleString();
                clone.querySelector('.sb-tmpl-label').textContent = device;
                $devices.append(clone);
            });
        }
    };

})(jQuery);
