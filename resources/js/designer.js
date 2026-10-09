/**
 * ارغوان ورکفلو | Arghavan Workflow
 *
 * محیط طراحی BPMN مبتنی بر bpmn-js (bpmn.io) با پالت سفارشی:
 * المان شروع، المان پایان، تسک کاربری، اسکریپت تسک، گیت‌وی شرطی،
 * استخر (Pool) و لین (Lane).
 *
 * پیکربندی از window.AW_CONFIG می‌آید که توسط designer.blade.php ساخته می‌شود.
 * خروجی نهایی: window.ARGHAVAN_WORKFLOW (modeler / getXml / setXml)
 */
(function () {
    'use strict';

    var cfg = window.AW_CONFIG || {};

    // ------------------------------------------------------------------
    // نمودار پیش‌فرض (یک همکاری با یک استخر حاوی المان شروع)؛
    // وجود استخر باعث می‌شود «لین (Lane)» از همان ابتدا قابل ریختن باشد.
    // ------------------------------------------------------------------
    var DEFAULT_XML = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:collaboration id="Collaboration_1">
    <bpmn:participant id="Participant_1" name="استخر" processRef="Process_1" />
  </bpmn:collaboration>
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Collaboration_1">
      <bpmndi:BPMNShape id="Participant_1_di" bpmnElement="Participant_1">
        <dc:Bounds x="160" y="80" width="600" height="240" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="202" y="182" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    // اگر کانفیگ پالت خالی بود، همین ابزارهای پیش‌فرض استفاده می‌شوند
    var DEFAULT_PALETTE = [
        { key: 'create.start-event', type: 'bpmn:StartEvent', label: 'المان شروع', icon: 'bpmn-icon-start-event-none', group: 'event' },
        { key: 'create.end-event', type: 'bpmn:EndEvent', label: 'المان پایان', icon: 'bpmn-icon-end-event-none', group: 'event' },
        { key: 'create.user-task', type: 'bpmn:UserTask', label: 'تسک کاربری', icon: 'bpmn-icon-user-task', group: 'activity' },
        { key: 'create.script-task', type: 'bpmn:ScriptTask', label: 'اسکریپت تسک', icon: 'bpmn-icon-script-task', group: 'activity' },
        { key: 'create.exclusive-gateway', type: 'bpmn:ExclusiveGateway', label: 'گیت‌وی شرطی', icon: 'bpmn-icon-gateway-xor', group: 'gateway' },
        { key: 'create.pool', type: 'bpmn:Participant', label: 'استخر (Pool)', icon: 'bpmn-icon-participant', group: 'collaboration' },
        { key: 'create.lane', type: 'bpmn:Lane', label: 'لین (Lane)', icon: 'bpmn-icon-lane', group: 'collaboration' }
    ];

    var paletteItems = (cfg.palette && cfg.palette.length) ? cfg.palette : DEFAULT_PALETTE;

    // ------------------------------------------------------------------
    // نوار وضعیت
    // ------------------------------------------------------------------
    var statusTimer = null;

    function setStatus(message, type) {
        var el = document.getElementById('aw-status');

        if (!el) {
            return;
        }

        el.textContent = message;
        el.className = 'aw-status' + (type ? ' is-' + type : '');

        if (statusTimer) {
            clearTimeout(statusTimer);
        }

        statusTimer = setTimeout(function () {
            el.className = 'aw-status';
        }, 6000);
    }

    // ------------------------------------------------------------------
    // پالت سفارشی
    //
    // با ماژول additionalModules، سرویس paletteProvider پیش‌فرض bpmn-js
    // جایگزین می‌شود و فقط ابزارهای کانفیگ‌شده در پالت نمایش داده می‌شوند.
    // ------------------------------------------------------------------

    /**
     * آیا در نمودار فعلی حداقل یک استخر (یا لین) وجود دارد؟
     * (لین فقط داخل استخر/لین قابل ریختن است — قوانین canDrop در bpmn-js)
     */
    function hasParticipant() {
        try {
            var registry = (typeof modeler !== 'undefined' && modeler) ? modeler.get('elementRegistry') : null;

            if (!registry || typeof registry.filter !== 'function') {
                return true; // اگر بررسی ممکن نبود، مانع نشو
            }

            return registry.filter(function (el) {
                return el.type === 'bpmn:Participant' || el.type === 'bpmn:Lane';
            }).length > 0;
        } catch (error) {
            return true;
        }
    }

    function CustomPaletteProvider(palette, create, elementFactory, translate) {
        this._palette = palette;
        this._create = create;
        this._elementFactory = elementFactory;
        this._translate = translate;

        palette.registerProvider(this);
    }

    CustomPaletteProvider.$inject = ['palette', 'create', 'elementFactory', 'translate'];

    CustomPaletteProvider.prototype.getPaletteEntries = function () {
        var entries = {},
            create = this._create,
            elementFactory = this._elementFactory,
            translate = this._translate;

        function createAction(item) {
            function createListener(event) {
                var shape;

                if (item.type === 'bpmn:Participant' && typeof elementFactory.createParticipantShape === 'function') {
                    // استخر: با فرآیند داخلی ساخته می‌شود (مثل پالت پیش‌فرض bpmn-js)
                    // تا بتوان المان و لین داخل آن قرار داد.
                    shape = elementFactory.createParticipantShape();
                } else if (item.type === 'bpmn:Lane' && !hasParticipant()) {
                    // لین فقط داخل استخر (یا لین دیگر) قابل ریختن است.
                    setStatus('ابتدا یک «استخر (Pool)» بسازید؛ سپس لین را داخل آن بیندازید.', 'warn');

                    return;
                } else {
                    shape = elementFactory.createShape({ type: item.type });
                }

                create.start(event, shape);
            }

            return {
                group: item.group || 'activity',
                className: item.icon || 'bpmn-icon-task',
                title: translate(item.label || item.type),
                action: {
                    dragstart: createListener,
                    click: createListener
                }
            };
        }

        paletteItems.forEach(function (item) {
            if (!item || !item.type) {
                return;
            }

            entries[item.key || ('create-' + item.type)] = createAction(item);
        });

        return entries;
    };

    // ------------------------------------------------------------------
    // ساخت ویرایشگر
    // ------------------------------------------------------------------
    function createModeler() {
        // ماژول سفارشی: paletteProvider پیش‌فرض را جایگزین می‌کند
        var paletteModule = {
            __init__: ['paletteProvider'],
            paletteProvider: ['type', CustomPaletteProvider]
        };

        return new window.BpmnJS({
            container: '#aw-canvas',
            additionalModules: [paletteModule]
        });
    }

    // ------------------------------------------------------------------
    // XML فعلی
    // ------------------------------------------------------------------
    function getCurrentXml() {
        return modeler
            .saveXML({ format: true })
            .then(function (result) { return result.xml; })
            .catch(function () { return null; });
    }

    /**
     * بارگذاری XML با بازگردانی خودکار به نسخه قبلی در صورت خطا.
     * همیشه با مقدار true (موفق) یا false (ناموفق) resolve می‌شود.
     */
    function importXml(xml) {
        xml = (xml && String(xml).trim()) ? String(xml) : DEFAULT_XML;

        return getCurrentXml().then(function (previousXml) {
            return modeler.importXML(xml).then(
                function (result) {
                    var warnings = (result && result.warnings) || [];

                    setStatus(
                        'نمودار بارگذاری شد' +
                        (warnings.length ? ' (' + warnings.length + ' هشدار)' : ''),
                        warnings.length ? 'warn' : 'ok'
                    );

                    return true;
                },
                function (error) {
                    var message = (error && error.message) ? error.message : 'خطای ناشناخته';

                    function restore() {
                        setStatus('XML نامعتبر است: ' + message, 'error');
                        return false;
                    }

                    // بازگردانی به نسخه قبلی تا بوم خالی نماند
                    if (previousXml) {
                        return modeler.importXML(previousXml).then(restore, restore);
                    }

                    return restore();
                }
            );
        });
    }

    // ------------------------------------------------------------------
    // ذخیره‌سازی
    // ------------------------------------------------------------------
    function diagramFileName(extension) {
        var input = document.getElementById('aw-name');
        var name = (input && input.value.trim()) || 'diagram';

        name = name.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, '-');

        return name + '.' + extension;
    }

    function download(fileName, content, mime) {
        var blob = new Blob([content], { type: mime });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    function save() {
        if (typeof window.ARGHAVAN_ON_SAVE === 'function') {
            // هوک سفارشی برنامه: window.ARGHAVAN_ON_SAVE({ id, name, xml })
            return getCurrentXml().then(function (xml) {
                return window.ARGHAVAN_ON_SAVE({
                    id: cfg.docId,
                    name: document.getElementById('aw-name').value,
                    xml: xml
                });
            });
        }

        if (!cfg.storageEnabled || !cfg.saveUrl) {
            setStatus('ذخیره‌سازی فعال نیست؛ می‌توانید XML را از «ویرایش XML» بردارید.', 'warn');
            return Promise.resolve(false);
        }

        return getCurrentXml().then(function (xml) {
            var nameInput = document.getElementById('aw-name');

            return fetch(cfg.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': cfg.csrf || ''
                },
                body: JSON.stringify({
                    id: cfg.docId,
                    name: nameInput ? nameInput.value : '',
                    xml: xml
                })
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.ok) {
                        throw new Error(data.message || 'خطا در ذخیره نمودار');
                    }

                    return data;
                });
            }).then(function (data) {
                cfg.docId = data.id;

                if (data.name && nameInput && !nameInput.value.trim()) {
                    nameInput.value = data.name;
                }

                // نشانی مرورگر با شناسه جدید هماهنگ می‌شود
                if (data.route && window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', data.route);
                }

                setStatus('نمودار ذخیره شد.', 'ok');

                return true;
            }).catch(function (error) {
                setStatus(error.message || 'ذخیره نمودار ممکن نشد.', 'error');

                return false;
            });
        });
    }

    // ------------------------------------------------------------------
    // نوار ابزار و راه‌اندازی
    // ------------------------------------------------------------------
    function onClick(id, handler) {
        var el = document.getElementById(id);

        if (el) {
            el.addEventListener('click', handler);
        }
    }

    function openXmlModal() {
        var modal = document.getElementById('aw-xml-modal');

        if (modal) {
            modal.hidden = false;
        }
    }

    function closeXmlModal() {
        var modal = document.getElementById('aw-xml-modal');

        if (modal) {
            modal.hidden = true;
        }
    }

    function runCommand(name) {
        try {
            modeler.get('commandStack')[name]();
        } catch (error) {
            setStatus('دستور در دسترس نیست.', 'warn');
        }
    }

    function currentCanvas() {
        return modeler.get('canvas');
    }

    function zoomBy(delta) {
        try {
            var canvas = currentCanvas();
            var scale = Math.min(4, Math.max(0.2, canvas.zoom() + delta));

            canvas.zoom(scale);
        } catch (error) {
            setStatus('زوم در دسترس نیست.', 'warn');
        }
    }

    function wireToolbar() {
        // ذخیره
        onClick('aw-save', function () {
            save();
        });

        // دانلود XML
        onClick('aw-download-xml', function () {
            getCurrentXml().then(function (xml) {
                if (!xml) {
                    setStatus('XML در دسترس نیست.', 'error');
                    return;
                }

                download(diagramFileName('bpmn'), xml, 'application/xml;charset=utf-8');
                setStatus('فایل XML دانلود شد.', 'ok');
            });
        });

        // دانلود SVG
        onClick('aw-download-svg', function () {
            modeler.saveSVG().then(function (result) {
                download(diagramFileName('svg'), result.svg, 'image/svg+xml;charset=utf-8');
                setStatus('فایل SVG دانلود شد.', 'ok');
            }).catch(function () {
                setStatus('خروجی SVG ساخته نشد.', 'error');
            });
        });

        // بارگذاری فایل
        onClick('aw-import-file', function () {
            var input = document.getElementById('aw-file-input');

            if (input) {
                input.click();
            }
        });

        var fileInput = document.getElementById('aw-file-input');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = this.files && this.files[0];

                this.value = '';

                if (!file) {
                    return;
                }

                var reader = new FileReader();

                reader.onload = function () {
                    importXml(String(reader.result));
                };

                reader.onerror = function () {
                    setStatus('خواندن فایل ممکن نشد.', 'error');
                };

                reader.readAsText(file);
            });
        }

        // مودال XML
        onClick('aw-edit-xml', function () {
            getCurrentXml().then(function (xml) {
                var textarea = document.getElementById('aw-xml-text');

                if (textarea) {
                    textarea.value = xml || '';
                }

                openXmlModal();
            });
        });

        Array.prototype.forEach.call(
            document.querySelectorAll('[data-aw-close-modal]'),
            function (el) {
                el.addEventListener('click', closeXmlModal);
            }
        );

        onClick('aw-xml-apply', function () {
            var textarea = document.getElementById('aw-xml-text');

            importXml(textarea ? textarea.value : '').then(function (ok) {
                if (ok) {
                    closeXmlModal();
                }
            });
        });

        // بازگردانی / ازسرگیری
        onClick('aw-undo', function () { runCommand('undo'); });
        onClick('aw-redo', function () { runCommand('redo'); });

        // زوم
        onClick('aw-zoom-in', function () { zoomBy(0.1); });
        onClick('aw-zoom-out', function () { zoomBy(-0.1); });

        onClick('aw-zoom-reset', function () {
            try { currentCanvas().zoom(1); } catch (error) { /* noop */ }
        });

        onClick('aw-zoom-fit', function () {
            try { currentCanvas().zoom('fit-viewport'); } catch (error) { /* noop */ }
        });

        // نمودار جدید
        onClick('aw-new', function () {
            if (!window.confirm('نمودار فعلی کنار گذاشته و یک نمودار خالی ساخته شود؟')) {
                return;
            }

            cfg.docId = null;

            var nameInput = document.getElementById('aw-name');

            if (nameInput) {
                nameInput.value = '';
            }

            if (cfg.newUrl && window.history && window.history.replaceState) {
                window.history.replaceState({}, '', cfg.newUrl);
            }

            importXml(DEFAULT_XML);
        });
    }

    // ------------------------------------------------------------------
    // راه‌اندازی
    // ------------------------------------------------------------------
    if (!window.BpmnJS) {
        setStatus('کتابخانه bpmn-js بارگذاری نشد؛ فایل‌های assets را بررسی کنید.', 'error');
        return;
    }

    var modeler = createModeler();

    // API عمومی پکیج برای اتصال به برنامه میزبان
    window.ARGHAVAN_WORKFLOW = {
        modeler: modeler,
        config: cfg,
        palette: paletteItems,
        getXml: getCurrentXml,
        setXml: importXml,
        save: save
    };

    wireToolbar();

    importXml(cfg.initialXml || DEFAULT_XML);

})();
