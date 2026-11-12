/**
 * تست پالت سفارشی «ارغوان ورکفلو» (بدون وابستگی خارجی).
 *
 * اجرا:  node tests/designer-palette.test.js
 *
 * فایل resources/js/designer.js را در یک محیط مجازی (vm) با stubهای
 * حداقلی اجرا می‌کند و بررسی می‌کند که:
 *   1) ماژول additionalModules ساخته و paletteProvider پیش‌فرض جایگزین می‌شود،
 *   2) پالت دقیقا ابزارهای کانفیگ‌شده را برمی‌گرداند،
 *   3) کلیک روی هر ابزار، المان BPMN درست را می‌سازد،
 *   4) بدون کانفیگ، ابزارهای پیش‌فرض استفاده می‌شوند،
 *   5) اگر bpmn-js لود نشده باشد، پیام خطا نمایش داده می‌شود.
 */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const DESIGNER_JS = fs.readFileSync(
    path.join(__dirname, '..', 'resources', 'js', 'designer.js'),
    'utf8'
);

const PALETTE = [
    { key: 'create.start-event', type: 'bpmn:StartEvent', label: 'المان شروع', icon: 'bpmn-icon-start-event-none', group: 'event' },
    { key: 'create.end-event', type: 'bpmn:EndEvent', label: 'المان پایان', icon: 'bpmn-icon-end-event-none', group: 'event' },
    { key: 'create.user-task', type: 'bpmn:UserTask', label: 'تسک کاربری', icon: 'bpmn-icon-user-task', group: 'activity' },
    { key: 'create.script-task', type: 'bpmn:ScriptTask', label: 'اسکریپت تسک', icon: 'bpmn-icon-script-task', group: 'activity' },
    { key: 'create.exclusive-gateway', type: 'bpmn:ExclusiveGateway', label: 'گیت‌وی شرطی', icon: 'bpmn-icon-gateway-xor', group: 'gateway' },
    { key: 'create.pool', type: 'bpmn:Participant', label: 'استخر (Pool)', icon: 'bpmn-icon-participant', group: 'collaboration' },
    { key: 'create.lane', type: 'bpmn:Lane', label: 'لین (Lane)', icon: 'bpmn-icon-lane', group: 'collaboration' }
];

let passed = 0;
let failed = 0;

function check(label, condition) {
    if (condition) {
        passed++;
        console.log('  [OK]   ' + label);
    } else {
        failed++;
        console.log('  [FAIL] ' + label);
    }
}

/**
 * اجرای designer.js در sandbox و برگرداندن context و گزینه‌های BpmnJS.
 */
function runDesigner(config, options) {
    options = options || {};

    const captured = {
        options: null,
        status: { textContent: '', className: '' },
        createdShapes: []
    };

    function FakeBpmnJS(bpmnOptions) {
        captured.options = bpmnOptions;

        this.importXML = function () { return Promise.resolve({ warnings: [] }); };
        this.saveXML = function () { return Promise.resolve({ xml: '<xml />' }); };
        this.saveSVG = function () { return Promise.resolve({ svg: '<svg />' }); };
        this.get = function () {
            return { zoom: function () { return 1; }, undo: function () {}, redo: function () {} };
        };
    }

    const sandbox = {
        window: {
            AW_CONFIG: config,
            history: { replaceState: function () {} },
            confirm: function () { return true; }
        },
        document: {
            getElementById: function (id) {
                return id === 'aw-status' ? captured.status : null;
            },
            querySelectorAll: function () { return []; },
            createElement: function () {
                return { click: function () {}, style: {} };
            },
            body: { appendChild: function () {}, removeChild: function () {} }
        },
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        console: console,
        Promise: Promise,
        fetch: function () { return Promise.resolve({ ok: false }); },
        Blob: function () {},
        URL: {
            createObjectURL: function () { return ''; },
            revokeObjectURL: function () {}
        }
    };

    if (options.withBpmn !== false) {
        sandbox.window.BpmnJS = FakeBpmnJS;
    }

    vm.createContext(sandbox);
    vm.runInContext(DESIGNER_JS, sandbox, { filename: 'designer.js' });

    return { sandbox: sandbox, captured: captured };
}

function fakeProviderArgs(captured) {
    const created = captured.createdShapes;
    let registered = null;

    const args = [
        { registerProvider: function (provider) { registered = provider; } },
        { start: function (event, shape) { created.push({ event: event, shape: shape }); } },
        { createShape: function (attrs) { return Object.assign({ x: 0, y: 0 }, attrs); } },
        function (template) { return template; }
    ];

    return { args: args, created: created, isRegistered: function (provider) { return registered === provider; } };
}

// ------------------------------------------------------------------
// 1) ساخت ماژول پالت
// ------------------------------------------------------------------
console.log('\n1) ساخت ماژول additionalModules');

const main = runDesigner({
    palette: PALETTE,
    storageEnabled: true,
    saveUrl: '/workflow/save',
    csrf: 'token'
});

check('BpmnJS با گزینه‌ها ساخته شد', main.captured.options !== null);

const additional = main.captured.options.additionalModules;
check('additionalModules شامل یک ماژول است', Array.isArray(additional) && additional.length === 1);

const paletteProviderDecl = additional[0].paletteProvider;
check('کلید paletteProvider از نوع type است', Array.isArray(paletteProviderDecl) && paletteProviderDecl[0] === 'type');

const Provider = paletteProviderDecl[1];

check(
    '$inject درست است',
    JSON.stringify(Provider.$inject) === JSON.stringify(['palette', 'create', 'elementFactory', 'translate'])
);

check('window.ARGHAVAN_WORKFLOW ساخته شد', !!main.sandbox.window.ARGHAVAN_WORKFLOW);
check('palette در API عمومی هست', main.sandbox.window.ARGHAVAN_WORKFLOW.palette.length === 7);

// ------------------------------------------------------------------
// 2) ورودی‌های پالت
// ------------------------------------------------------------------
console.log('\n2) ورودی‌های پالت');

const mainFake = fakeProviderArgs(main.captured);
const provider = new Provider(
    mainFake.args[0],
    mainFake.args[1],
    mainFake.args[2],
    mainFake.args[3]
);

check('پروایدر هنگام ساخت در پالت ثبت می‌شود', mainFake.isRegistered(provider));

const entries = provider.getPaletteEntries();
const keys = Object.keys(entries);

check('دقیقا 7 ورودی دارد', keys.length === 7);

check(
    'ترتیب ورودی‌ها با کانفیگ یکی است',
    JSON.stringify(keys) === JSON.stringify(PALETTE.map(function (item) { return item.key; }))
);

PALETTE.forEach(function (item) {
    const entry = entries[item.key];

    check('آیکون درست: ' + item.key, !!entry && entry.className === item.icon);
    check('عنوان فارسی: ' + item.key, !!entry && entry.title === item.label);
    check('گروه درست: ' + item.key, !!entry && entry.group === item.group);

    check(
        'dragstart و click: ' + item.key,
        !!entry && typeof entry.action.click === 'function' && typeof entry.action.dragstart === 'function'
    );
});

// ------------------------------------------------------------------
// 3) ساخت المان با کلیک روی ابزارها
// ------------------------------------------------------------------
console.log('\n3) ساخت المان با کلیک');

keys.forEach(function (key) {
    entries[key].action.click({ type: 'click' });
});

check('7 المان ساخته شد', mainFake.created.length === 7);

check(
    'انواع BPMN ساخته‌شده درست است',
    JSON.stringify(mainFake.created.map(function (c) { return c.shape.type; })) === JSON.stringify([
        'bpmn:StartEvent',
        'bpmn:EndEvent',
        'bpmn:UserTask',
        'bpmn:ScriptTask',
        'bpmn:ExclusiveGateway',
        'bpmn:Participant',
        'bpmn:Lane'
    ])
);

// ------------------------------------------------------------------
// 4) بدون کانفیگ پالت → ابزارهای پیش‌فرض
// ------------------------------------------------------------------
console.log('\n4) پالت پیش‌فرض');

const fallback = runDesigner({ palette: [] });
const FallbackProvider = fallback.captured.options.additionalModules[0].paletteProvider[1];
const fallbackFake = fakeProviderArgs(fallback.captured);
const fallbackProvider = new FallbackProvider(
    fallbackFake.args[0],
    fallbackFake.args[1],
    fallbackFake.args[2],
    fallbackFake.args[3]
);

check('7 ابزار پیش‌فرض دارد', Object.keys(fallbackProvider.getPaletteEntries()).length === 7);

// ------------------------------------------------------------------
// 5) نبود bpmn-js
// ------------------------------------------------------------------
console.log('\n5) نبود کتابخانه bpmn-js');

const missing = runDesigner({ palette: PALETTE }, { withBpmn: false });

check('ویرایشگر ساخته نشد', missing.captured.options === null);
check('پیام خطا نمایش داده شد', missing.captured.status.textContent.indexOf('bpmn-js') !== -1);
check('API عمومی ساخته نشد', !missing.sandbox.window.ARGHAVAN_WORKFLOW);

// ------------------------------------------------------------------
// جمع‌بندی
// ------------------------------------------------------------------
console.log('\n--------------------------------------------------');
console.log('نتیجه: ' + passed + ' موفق، ' + failed + ' ناموفق');

process.exit(failed > 0 ? 1 : 0);
