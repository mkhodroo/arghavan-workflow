/**
 * ارغوان ورکفلو | Arghavan Workflow
 *
 * مترجم سمت مرورگر.
 *
 * کلیدها انگلیسی‌اند و ترجمه‌ها از `lang/{locale}/fields.php` در
 * window.AW_CONFIG.i18n قرار می‌گیرند. اگر کلیدی ترجمه نشده باشد، خود کلید
 * انگلیسی نمایش داده می‌شود تا کاربر بداند چه متنی را باید ترجمه کند.
 */
(function () {
    'use strict';

    /**
     * @param {string} key          کلید انگلیسی متن
     * @param {Object} replacements جایگزین‌های :name داخل متن
     * @returns {string}
     */
    function translate(key, replacements) {
        var config = window.AW_CONFIG || {};
        var dictionary = config.i18n || {};
        var value = null;

        if (key !== null && key !== undefined
            && Object.prototype.hasOwnProperty.call(dictionary, key)) {
            value = dictionary[key];
        }

        if (typeof value !== 'string' || value === '') {
            value = key === null || key === undefined ? '' : String(key);
        }

        if (replacements) {
            Object.keys(replacements).forEach(function (name) {
                value = value.split(':' + name).join(String(replacements[name]));
            });
        }

        return value;
    }

    window.awTranslate = translate;
})();
