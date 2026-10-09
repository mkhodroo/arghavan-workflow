/**
 * ØªØ³Øª Ù¾Ø§Ù„Øª Ø³ÙØ§Ø±Ø´ÛŒ Â«Ø§Ø±ØºÙˆØ§Ù† ÙˆØ±Ú©ÙÙ„ÙˆÂ» (Ø¨Ø¯ÙˆÙ† ÙˆØ§Ø¨Ø³ØªÚ¯ÛŒ Ø®Ø§Ø±Ø¬ÛŒ).
 *
 * Ø§Ø¬Ø±Ø§:  node tests/designer-palette.test.js
 *
 * ÙØ§ÛŒÙ„ resources/js/designer.js Ø±Ø§ Ø¯Ø± ÛŒÚ© Ù…Ø­ÛŒØ· Ù…Ø¬Ø§Ø²ÛŒ (vm) Ø¨Ø§ stubÙ‡Ø§ÛŒ
 * Ø­Ø¯Ø§Ù‚Ù„ÛŒ Ø§Ø¬Ø±Ø§ Ù…ÛŒâ€ŒÚ©Ù†Ø¯ Ùˆ Ø¨Ø±Ø±Ø³ÛŒ Ù…ÛŒâ€ŒÚ©Ù†Ø¯ Ú©Ù‡:
 *   1) Ù…Ø§Ú˜ÙˆÙ„ additionalModules Ø³Ø§Ø®ØªÙ‡ Ùˆ paletteProvider Ù¾ÛŒØ´â€ŒÙØ±Ø¶ Ø¬Ø§ÛŒÚ¯Ø²ÛŒÙ† Ù…ÛŒâ€ŒØ´ÙˆØ¯ØŒ
 *   2) Ù¾Ø§Ù„Øª Ø¯Ù‚ÛŒÙ‚Ø§ Ø§Ø¨Ø²Ø§Ø±Ù‡Ø§ÛŒ Ú©Ø§Ù†ÙÛŒÚ¯â€ŒØ´Ø¯Ù‡ Ø±Ø§ Ø¨Ø±Ù…ÛŒâ€ŒÚ¯Ø±Ø¯Ø§Ù†Ø¯ØŒ
 *   3) Ú©Ù„ÛŒÚ© Ø±ÙˆÛŒ Ù‡Ø± Ø§Ø¨Ø²Ø§Ø±ØŒ Ø§Ù„Ù…Ø§Ù† BPMN Ø¯Ø±Ø³Øª Ø±Ø§ Ù…ÛŒâ€ŒØ³Ø§Ø²Ø¯ØŒ
 *   4) Ø¨Ø¯ÙˆÙ† Ú©Ø§Ù†ÙÛŒÚ¯ØŒ Ø§Ø¨Ø²Ø§Ø±Ù‡Ø§ÛŒ Ù¾ÛŒØ´â€ŒÙØ±Ø¶ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ù…ÛŒâ€ŒØ´ÙˆÙ†Ø¯ØŒ
 *   5) Ø§Ú¯Ø± bpmn-js Ù„ÙˆØ¯ Ù†Ø´Ø¯Ù‡ Ø¨Ø§Ø´Ø¯ØŒ Ù¾ÛŒØ§Ù… Ø®Ø·Ø§ Ù†Ù…Ø§ÛŒØ´ Ø¯Ø§Ø¯Ù‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯.
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
    { key: 'create.start-event', type: 'bpmn:StartEvent', label: 'Ø§Ù„Ù…Ø§Ù† Ø´Ø±ÙˆØ¹', icon: 'bpmn-icon-start-event-none', group: 'event' },
    { key: 'create.end-event', type: 'bpmn:EndEvent', label: 'Ø§Ù„Ù…Ø§Ù† Ù¾Ø§ÛŒØ§Ù†', icon: 'bpmn-icon-end-event-none', group: 'event' },
    { key: 'create.user-task', type: 'bpmn:UserTask', label: 'ØªØ³Ú© Ú©Ø§Ø±Ø¨Ø±ÛŒ', icon: 'bpmn-icon-user-task', group: 'activity' },
    { key: 'create.script-task', type: 'bpmn:ScriptTask', label: 'Ø§Ø³Ú©Ø±ÛŒÙ¾Øª ØªØ³Ú©', icon: 'bpmn-icon-script-task', group: 'activity' },
    { key: 'create.exclusive-gateway', type: 'bpmn:ExclusiveGateway', label: 'Ú¯ÛŒØªâ€ŒÙˆÛŒ Ø´Ø±Ø·ÛŒ', icon: 'bpmn-icon-gateway-xor', group: 'gateway' },
    { key: 'create.pool', type: 'bpmn:Participant', label: 'Ø§Ø³ØªØ®Ø± (Pool)', icon: 'bpmn-icon-participant', group: 'collaboration' },
    { key: 'create.lane', type: 'bpmn:Lane', label: 'Ù„ÛŒÙ† (Lane)', icon: 'bpmn-icon-lane', group: 'collaboration' }
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
 * Ø§Ø¬Ø±Ø§ÛŒ designer.js Ø¯Ø± sandbox Ùˆ Ø¨Ø±Ú¯Ø±Ø¯Ø§Ù†Ø¯Ù† context Ùˆ Ú¯Ø²ÛŒÙ†Ù‡â€ŒÙ‡Ø§ÛŒ BpmnJS.
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
// 1) Ø³Ø§Ø®Øª Ù…Ø§Ú˜ÙˆÙ„ Ù¾Ø§Ù„Øª
// ------------------------------------------------------------------
console.log('\n1) Ø³Ø§Ø®Øª Ù…Ø§Ú˜ÙˆÙ„ additionalModules');

const main = runDesigner({
    palette: PALETTE,
    storageEnabled: true,
    saveUrl: '/workflow/save',
    csrf: 'token'
});

check('BpmnJS Ø¨Ø§ Ú¯Ø²ÛŒÙ†Ù‡â€ŒÙ‡Ø§ Ø³Ø§Ø®ØªÙ‡ Ø´Ø¯', main.captured.options !== null);

const additional = main.captured.options.additionalModules;
check('additionalModules Ø´Ø§Ù…Ù„ ÛŒÚ© Ù…Ø§Ú˜ÙˆÙ„ Ø§Ø³Øª', Array.isArray(additional) && additional.length === 1);

const paletteProviderDecl = additional[0].paletteProvider;
check('Ú©Ù„ÛŒØ¯ paletteProvider Ø§Ø² Ù†ÙˆØ¹ type Ø§Ø³Øª', Array.isArray(paletteProviderDecl) && paletteProviderDecl[0] === 'type');

const Provider = paletteProviderDecl[1];

check(
    '$inject Ø¯Ø±Ø³Øª Ø§Ø³Øª',
    JSON.stringify(Provider.$inject) === JSON.stringify(['palette', 'create', 'elementFactory', 'translate'])
);

check('window.ARGHAVAN_WORKFLOW Ø³Ø§Ø®ØªÙ‡ Ø´Ø¯', !!main.sandbox.window.ARGHAVAN_WORKFLOW);
check('palette Ø¯Ø± API Ø¹Ù…ÙˆÙ…ÛŒ Ù‡Ø³Øª', main.sandbox.window.ARGHAVAN_WORKFLOW.palette.length === 7);

// ------------------------------------------------------------------
// 2) ÙˆØ±ÙˆØ¯ÛŒâ€ŒÙ‡Ø§ÛŒ Ù¾Ø§Ù„Øª
// ------------------------------------------------------------------
console.log('\n2) ÙˆØ±ÙˆØ¯ÛŒâ€ŒÙ‡Ø§ÛŒ Ù¾Ø§Ù„Øª');

const mainFake = fakeProviderArgs(main.captured);
const provider = new Provider(
    mainFake.args[0],
    mainFake.args[1],
    mainFake.args[2],
    mainFake.args[3]
);

check('Ù¾Ø±ÙˆØ§ÛŒØ¯Ø± Ù‡Ù†Ú¯Ø§Ù… Ø³Ø§Ø®Øª Ø¯Ø± Ù¾Ø§Ù„Øª Ø«Ø¨Øª Ù…ÛŒâ€ŒØ´ÙˆØ¯', mainFake.isRegistered(provider));

const entries = provider.getPaletteEntries();
const keys = Object.keys(entries);

check('Ø¯Ù‚ÛŒÙ‚Ø§ 7 ÙˆØ±ÙˆØ¯ÛŒ Ø¯Ø§Ø±Ø¯', keys.length === 7);

check(
    'ØªØ±ØªÛŒØ¨ ÙˆØ±ÙˆØ¯ÛŒâ€ŒÙ‡Ø§ Ø¨Ø§ Ú©Ø§Ù†ÙÛŒÚ¯ ÛŒÚ©ÛŒ Ø§Ø³Øª',
    JSON.stringify(keys) === JSON.stringify(PALETTE.map(function (item) { return item.key; }))
);

PALETTE.forEach(function (item) {
    const entry = entries[item.key];

    check('Ø¢ÛŒÚ©ÙˆÙ† Ø¯Ø±Ø³Øª: ' + item.key, !!entry && entry.className === item.icon);
    check('Ø¹Ù†ÙˆØ§Ù† ÙØ§Ø±Ø³ÛŒ: ' + item.key, !!entry && entry.title === item.label);
    check('Ú¯Ø±ÙˆÙ‡ Ø¯Ø±Ø³Øª: ' + item.key, !!entry && entry.group === item.group);

    check(
        'dragstart Ùˆ click: ' + item.key,
        !!entry && typeof entry.action.click === 'function' && typeof entry.action.dragstart === 'function'
    );
});

// ------------------------------------------------------------------
// 3) Ø³Ø§Ø®Øª Ø§Ù„Ù…Ø§Ù† Ø¨Ø§ Ú©Ù„ÛŒÚ© Ø±ÙˆÛŒ Ø§Ø¨Ø²Ø§Ø±Ù‡Ø§
// ------------------------------------------------------------------
console.log('\n3) Ø³Ø§Ø®Øª Ø§Ù„Ù…Ø§Ù† Ø¨Ø§ Ú©Ù„ÛŒÚ©');

keys.forEach(function (key) {
    entries[key].action.click({ type: 'click' });
});

check('7 Ø§Ù„Ù…Ø§Ù† Ø³Ø§Ø®ØªÙ‡ Ø´Ø¯', mainFake.created.length === 7);

check(
    'Ø§Ù†ÙˆØ§Ø¹ BPMN Ø³Ø§Ø®ØªÙ‡â€ŒØ´Ø¯Ù‡ Ø¯Ø±Ø³Øª Ø§Ø³Øª',
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
// 4) Ø¨Ø¯ÙˆÙ† Ú©Ø§Ù†ÙÛŒÚ¯ Ù¾Ø§Ù„Øª â†’ Ø§Ø¨Ø²Ø§Ø±Ù‡Ø§ÛŒ Ù¾ÛŒØ´â€ŒÙØ±Ø¶
// ------------------------------------------------------------------
console.log('\n4) Ù¾Ø§Ù„Øª Ù¾ÛŒØ´â€ŒÙØ±Ø¶');

const fallback = runDesigner({ palette: [] });
const FallbackProvider = fallback.captured.options.additionalModules[0].paletteProvider[1];
const fallbackFake = fakeProviderArgs(fallback.captured);
const fallbackProvider = new FallbackProvider(
    fallbackFake.args[0],
    fallbackFake.args[1],
    fallbackFake.args[2],
    fallbackFake.args[3]
);

check('7 Ø§Ø¨Ø²Ø§Ø± Ù¾ÛŒØ´â€ŒÙØ±Ø¶ Ø¯Ø§Ø±Ø¯', Object.keys(fallbackProvider.getPaletteEntries()).length === 7);

// ------------------------------------------------------------------
// 5) Ù†Ø¨ÙˆØ¯ bpmn-js
// ------------------------------------------------------------------
console.log('\n5) Ù†Ø¨ÙˆØ¯ Ú©ØªØ§Ø¨Ø®Ø§Ù†Ù‡ bpmn-js');

const missing = runDesigner({ palette: PALETTE }, { withBpmn: false });

check('ÙˆÛŒØ±Ø§ÛŒØ´Ú¯Ø± Ø³Ø§Ø®ØªÙ‡ Ù†Ø´Ø¯', missing.captured.options === null);
check('Ù¾ÛŒØ§Ù… Ø®Ø·Ø§ Ù†Ù…Ø§ÛŒØ´ Ø¯Ø§Ø¯Ù‡ Ø´Ø¯', missing.captured.status.textContent.indexOf('bpmn-js') !== -1);
check('API Ø¹Ù…ÙˆÙ…ÛŒ Ø³Ø§Ø®ØªÙ‡ Ù†Ø´Ø¯', !missing.sandbox.window.ARGHAVAN_WORKFLOW);

// ------------------------------------------------------------------
// Ø¬Ù…Ø¹â€ŒØ¨Ù†Ø¯ÛŒ
// ------------------------------------------------------------------
console.log('\n--------------------------------------------------');
console.log('Ù†ØªÛŒØ¬Ù‡: ' + passed + ' Ù…ÙˆÙÙ‚ØŒ ' + failed + ' Ù†Ø§Ù…ÙˆÙÙ‚');

process.exit(failed > 0 ? 1 : 0);
