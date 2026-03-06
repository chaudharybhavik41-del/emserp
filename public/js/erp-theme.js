(function () {
    'use strict';

    var THEME_KEY = 'erp-theme';

    function readStoredTheme() {
        try {
            var stored = localStorage.getItem(THEME_KEY);
            if (stored === 'dark' || stored === 'light') {
                return stored;
            }
        } catch (e) {
            // Ignore storage errors and fall back to system preference.
        }

        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark'
            : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.style.colorScheme = theme;
        try {
            document.dispatchEvent(new CustomEvent('erp:theme-changed', { detail: { theme: theme } }));
        } catch (e) {
            // Ignore event dispatch errors.
        }

        var icon = document.getElementById('themeToggleIcon');
        if (icon) {
            icon.classList.remove('bi-moon-stars', 'bi-sun');
            icon.classList.add(theme === 'dark' ? 'bi-sun' : 'bi-moon-stars');
        }

        var toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            toggle.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

    function initThemeToggle() {
        var currentTheme = readStoredTheme();
        applyTheme(currentTheme);

        var toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            var nextTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

            try {
                localStorage.setItem(THEME_KEY, nextTheme);
            } catch (e) {
                // Ignore storage errors.
            }

            applyTheme(nextTheme);
        });
    }

    function parseBoolData(value, defaultValue) {
        if (value === undefined || value === null || value === '') {
            return defaultValue;
        }

        if (typeof value === 'boolean') {
            return value;
        }

        var normalized = String(value).toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes';
    }

    function initSelect2(context) {
        if (!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function')) {
            return;
        }

        var $ = window.jQuery;
        var scope = context || document;

        $(scope).find('select[data-erp-select]').each(function () {
            var $select = $(this);

            if ($select.data('select2')) {
                return;
            }

            var hideSearch = parseBoolData($select.data('hideSearch'), false);
            var allowClear = parseBoolData($select.data('allowClear'), !$select.prop('multiple'));
            var placeholder = $select.data('placeholder') || $select.attr('placeholder') || '';
            var minimumInputLength = parseInt($select.data('minimumInputLength'), 10);

            if (Number.isNaN(minimumInputLength)) {
                minimumInputLength = 0;
            }

            var config = {
                width: '100%',
                placeholder: placeholder,
                allowClear: allowClear,
                minimumResultsForSearch: hideSearch ? Infinity : 0,
                minimumInputLength: minimumInputLength,
                dropdownAutoWidth: true
            };

            if ($select.prop('multiple')) {
                config.closeOnSelect = false;
            }

            var ajaxUrl = $select.data('ajaxUrl');
            if (ajaxUrl) {
                config.ajax = {
                    url: ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;

                        return {
                            results: data.results || [],
                            pagination: {
                                more: !!(data.pagination && data.pagination.more)
                            }
                        };
                    }
                };
                config.minimumInputLength = Math.max(0, minimumInputLength);
                config.escapeMarkup = function (markup) {
                    return markup;
                };
            }

            $select.select2(config);
        });
    }

    function ensureResponsiveTables(context) {
        if (!(window.jQuery && window.jQuery.fn)) {
            return;
        }

        var $ = window.jQuery;
        var scope = context || document;

        $(scope).find('.erp-main-card table.table').each(function () {
            var $table = $(this);

            if ($table.parents('.table-responsive').length > 0) {
                return;
            }

            $table.wrap('<div class="table-responsive"></div>');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initThemeToggle();
        initSelect2(document);
        ensureResponsiveTables(document);
    });

    document.addEventListener('erp:content-updated', function (event) {
        initSelect2(event.target || document);
        ensureResponsiveTables(event.target || document);
    });

    window.ERPUI = window.ERPUI || {};
    window.ERPUI.applyTheme = applyTheme;
    window.ERPUI.initSelect2 = initSelect2;
    window.ERPUI.ensureResponsiveTables = ensureResponsiveTables;
})();
