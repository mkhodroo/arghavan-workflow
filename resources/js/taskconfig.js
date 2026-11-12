/**
 * مودال تنظیمات «تسک کاربری» در طراح BPMN.
 *
 * با دوبار کلیک روی یک UserTask باز می‌شود و همان تنظیماتی را نشان می‌دهد که
 * در پکیج behin-simple-workflow برای یک «تسک فرم» وجود دارد (اطلاعات پایه،
 * گردش کار و ظاهر). تنظیمات در جدول workflow_user_tasks (همان رکورد گره در
 * جدول تسک‌های کاربری) ذخیره می‌شوند؛ نه درون XML نمودار.
 *
 * وابسته به window.AW_CONFIG که توسط designer.blade.php ساخته می‌شود.
 */
(function () {
    'use strict';

    function cfg() {
        return window.AW_CONFIG || {};
    }

    /**
     * ترجمه‌ی متن‌ها؛ کلید انگلیسی است و معادل فارسی از
     * window.AW_CONFIG.i18n (فایل fields پروژه) خوانده می‌شود.
     */
    function t(key, replacements) {
        return typeof window.awTranslate === 'function'
            ? window.awTranslate(key, replacements)
            : key;
    }

    function el(id) {
        return document.getElementById(id);
    }

    function status(message, type) {
        if (typeof window.setStatus === 'function') {
            window.setStatus(message, type);
        }
    }

    function setValue(id, value) {
        var node = el(id);

        if (node) {
            node.value = value === null || value === undefined ? '' : String(value);
        }
    }

    function getValue(id) {
        var node = el(id);

        return node ? node.value : '';
    }

    function fillSelect(id, options, value, placeholder) {
        var select = el(id);

        if (!select) {
            return;
        }

        select.innerHTML = '';

        var first = document.createElement('option');
        first.value = '';
        first.textContent = placeholder || t('--- Select ---');
        select.appendChild(first);

        (options || []).forEach(function (option) {
            var item = document.createElement('option');
            item.value = option.id;
            item.textContent = option.name || option.id;
            select.appendChild(item);
        });

        select.value = value === null || value === undefined ? '' : String(value);
    }

    function isHex(value) {
        return /^#([0-9a-f]{3}){1,2}$/i.test(value || '');
    }

    function syncPicker(pickerId, value) {
        var picker = el(pickerId);

        if (picker && isHex(value)) {
            picker.value = value;
        }
    }

    function truthy(value) {
        return value === '1' || value === 'on' || value === 'true' || value === 1 || value === true;
    }

    var Modal = {
        docId: null,
        elementId: null,
        lists: { forms: [], users: [], roles: [], assignmentTypes: [] },

        open: function (docId, elementId) {
            if (!docId) {
                status(t('Save the diagram before saving its settings.'), 'warn');
                return;
            }

            this.docId = docId;
            this.elementId = elementId;

            var title = el('aw-tc-title');

            if (title) {
                title.textContent = t('User Task Configuration') + ' — ' + elementId;
            }

            var modal = el('aw-taskconfig-modal');

            if (modal) {
                modal.hidden = false;
            }

            this.activateTab('general');
            this.apply({}); // پاک‌سازی مقادیر قبلی تا داده‌ی کهنه نمایش داده نشود
            this.refreshLists().then(function () {
                return Modal.load();
            });
        },

        close: function () {
            var modal = el('aw-taskconfig-modal');

            if (modal) {
                modal.hidden = true;
            }
        },

        activateTab: function (name) {
            Array.prototype.forEach.call(
                document.querySelectorAll('[data-aw-tc-tab]'),
                function (tab) {
                    tab.classList.toggle('is-active', tab.getAttribute('data-aw-tc-tab') === name);
                }
            );

            Array.prototype.forEach.call(
                document.querySelectorAll('[data-aw-tc-panel]'),
                function (panel) {
                    panel.hidden = panel.getAttribute('data-aw-tc-panel') !== name;
                }
            );
        },

        refreshLists: function () {
            var url = cfg().configListUrl;

            if (!url) {
                return Promise.resolve();
            }

            return fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    Modal.lists.forms = data.forms || [];
                    Modal.lists.users = data.users || [];
                    Modal.lists.roles = data.roles || [];
                    Modal.lists.assignmentTypes = data.assignmentTypes || [];
                })
                .catch(function () {
                    // فهرست‌ها اختیاری‌اند؛ در صورت خطا فیلدها خالی می‌مانند.
                });
        },

        load: function () {
            var base = cfg().configGetUrl;

            if (!base) {
                return Promise.resolve();
            }

            var url = base
                .replace('{doc}', encodeURIComponent(this.docId))
                .replace('{element}', encodeURIComponent(this.elementId));

            return fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.ok) {
                        throw new Error(result.data.message || t('Configuration was not found.'));
                    }

                    Modal.apply(result.data.config || {});
                })
                .catch(function (error) {
                    status(error.message || t('Failed to load the configuration.'), 'error');
                });
        },

        apply: function (config) {
            fillSelect('aw-tc-form', this.lists.forms, config.formId, t('--- Select Form ---'));
            fillSelect('aw-tc-actor', this.lists.users, config.actor, t('--- Select Actor ---'));
            fillSelect('aw-tc-role', this.lists.roles, config.roleId, t('--- Select Role ---'));
            fillSelect(
                'aw-tc-assignment',
                this.lists.assignmentTypes,
                config.assignment_type,
                t('--- Select Assignment Type ---')
            );

            setValue('aw-tc-name', config.name);
            setValue('aw-tc-order', config.order);
            setValue('aw-tc-case-name', config.case_name);
            setValue('aw-tc-duration', config.duration);
            setValue('aw-tc-color', config.color);
            setValue('aw-tc-background', config.background);
            setValue('aw-tc-is-preview', truthy(config.is_preview) ? '1' : '0');
            setValue('aw-tc-show-cancel', truthy(config.show_cancel_button) ? '1' : '0');

            syncPicker('aw-tc-color-picker', config.color);
            syncPicker('aw-tc-background-picker', config.background);
        },

        collect: function () {
            return {
                name: getValue('aw-tc-name'),
                order: getValue('aw-tc-order'),
                formId: getValue('aw-tc-form'),
                assignment_type: getValue('aw-tc-assignment'),
                actor: getValue('aw-tc-actor'),
                roleId: getValue('aw-tc-role'),
                case_name: getValue('aw-tc-case-name'),
                duration: getValue('aw-tc-duration'),
                color: getValue('aw-tc-color'),
                background: getValue('aw-tc-background'),
                is_preview: getValue('aw-tc-is-preview'),
                show_cancel_button: getValue('aw-tc-show-cancel')
            };
        },

        save: function () {
            var base = cfg().configSaveUrl;

            if (!base || !this.docId) {
                status(t('Save the diagram before saving its settings.'), 'warn');
                return;
            }

            var url = base
                .replace('{doc}', encodeURIComponent(this.docId))
                .replace('{element}', encodeURIComponent(this.elementId));

            var payload = this.collect();
            var button = el('aw-tc-save');

            if (button) {
                button.disabled = true;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': cfg().csrf || ''
                },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.ok) {
                        throw new Error(result.data.message || t('Saving failed.'));
                    }

                    status(t('Task settings saved.'), 'ok');

                    var api = window.ARGHAVAN_WORKFLOW;

                    if (result.data.xml && api && typeof api.setXml === 'function') {
                        // همگام‌سازی نمودار درون‌مرورگر با تنظیمات ذخیره‌شده
                        api.setXml(result.data.xml);
                    } else if (payload.name && api && typeof api.updateElementName === 'function') {
                        api.updateElementName(Modal.elementId, payload.name);
                    }

                    Modal.close();
                })
                .catch(function (error) {
                    status(error.message || t('Could not save the settings.'), 'error');
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                    }
                });
        }
    };

    // ------------------------------------------------------------------
    // اتصال رویدادهای مودال
    // ------------------------------------------------------------------
    function wire() {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-aw-tc-tab]'),
            function (tab) {
                tab.addEventListener('click', function () {
                    Modal.activateTab(tab.getAttribute('data-aw-tc-tab'));
                });
            }
        );

        Array.prototype.forEach.call(
            document.querySelectorAll('[data-aw-tc-close]'),
            function (button) {
                button.addEventListener('click', function () {
                    Modal.close();
                });
            }
        );

        var saveButton = el('aw-tc-save');

        if (saveButton) {
            saveButton.addEventListener('click', function () {
                Modal.save();
            });
        }

        // همگام‌سازی انتخابگر رنگ با فیلد متنی
        [
            ['aw-tc-color-picker', 'aw-tc-color'],
            ['aw-tc-background-picker', 'aw-tc-background']
        ].forEach(function (pair) {
            var picker = el(pair[0]);
            var input = el(pair[1]);

            if (picker && input) {
                picker.addEventListener('input', function () {
                    input.value = picker.value;
                });
                input.addEventListener('input', function () {
                    if (isHex(input.value)) {
                        picker.value = input.value;
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }

    window.AW_TASKCONFIG = Modal;
})();