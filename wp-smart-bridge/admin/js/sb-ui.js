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
         * Open Modal
         * @param {string} modalId 
         */
        openModal: function (modalId) {
            $(modalId).fadeIn(200);
            $('body').addClass('sb-modal-open');
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
                clone.querySelector('.sb-tmpl-desc').textContent = ' 클릭 (' + (type === 'spike' ? '+' : '') + item.deviation + 'σ)';

                $content.append(clone);
            }

            data.spikes.forEach(function (item) { appendItem(item, 'spike'); });
            data.drops.forEach(function (item) { appendItem(item, 'drop'); });
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
                div.textContent = '데이터 없음';
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
