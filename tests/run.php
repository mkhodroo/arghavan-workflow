<?php

/**
 * ØªØ³Øªâ€ŒÙ‡Ø§ÛŒ Ø³Ø¨Ú© Ù¾Ú©ÛŒØ¬ Â«Ø§Ø±ØºÙˆØ§Ù† ÙˆØ±Ú©ÙÙ„ÙˆÂ» (Ø¨Ø¯ÙˆÙ† Ù†ÛŒØ§Ø² Ø¨Ù‡ Ù†ØµØ¨ Ù„Ø§Ø±Ø§ÙˆÙ„).
 *
 * Ø§Ø¬Ø±Ø§:  php tests/run.php
 *
 * Ø§Ø³ØªØ§Ø¨â€ŒÙ‡Ø§ÛŒ Ø­Ø¯Ø§Ù‚Ù„ÛŒ helperÙ‡Ø§ÛŒ Ù„Ø§Ø±Ø§ÙˆÙ„ (config / route / storage_path)
 * ÙÙ‚Ø· ÙˆÙ‚ØªÛŒ ØªØ¹Ø±ÛŒÙ Ù†Ø´Ø¯Ù‡ Ø¨Ø§Ø´Ù†Ø¯ Ø³Ø§Ø®ØªÙ‡ Ù…ÛŒâ€ŒØ´ÙˆÙ†Ø¯ ØªØ§ Ø§Ø¬Ø±Ø§ÛŒ ØªØ³Øª Ø¯Ø±ÙˆÙ† Ù¾Ø±ÙˆÚ˜Ù‡
 * Ù„Ø§Ø±Ø§ÙˆÙ„ Ù‡Ù… Ø¨ÛŒâ€ŒØ®Ø·Ø± Ø¨Ø§Ø´Ø¯.
 */

error_reporting(E_ALL);

$AW_ROOT = dirname(__DIR__);

$GLOBALS['AW_TEST_CONFIG'] = [];

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = $GLOBALS['AW_TEST_STORAGE'] ?? sys_get_temp_dir();

        return rtrim($base, '/\\').($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, '/\\') : '');
    }
}

if (! function_exists('config')) {
    function config(string $key, $default = null)
    {
        $value = $GLOBALS['AW_TEST_CONFIG'];

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

if (! function_exists('route')) {
    function route(string $name, $parameters = []): string
    {
        $path = is_array($parameters) ? ($parameters['path'] ?? '') : $parameters;

        return 'https://example.test/'.$name.($path !== '' ? '/'.ltrim((string) $path, '/') : '');
    }
}

require $AW_ROOT.'/src/Support/BpmnXml.php';
require $AW_ROOT.'/src/Support/Assets.php';
require $AW_ROOT.'/src/Services/DiagramRepository.php';
require __DIR__.'/stubs.php';
require $AW_ROOT.'/src/Http/Controllers/AssetController.php';

use ArghavanWorkflow\Http\Controllers\AssetController;
use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Support\Assets;
use ArghavanWorkflow\Support\BpmnXml;

// ------------------------------------------------------------------
// Ø§Ø¨Ø²Ø§Ø± check
// ------------------------------------------------------------------
$AW_PASSED = 0;
$AW_FAILED = 0;

function check(string $label, bool $condition): void
{
    global $AW_PASSED, $AW_FAILED;

    if ($condition) {
        $AW_PASSED++;
        echo '  [OK]   '.$label.PHP_EOL;
    } else {
        $AW_FAILED++;
        echo '  [FAIL] '.$label.PHP_EOL;
    }
}

function removeDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.DIRECTORY_SEPARATOR.$item;

        is_dir($path) ? removeDirectory($path) : @unlink($path);
    }

    @rmdir($dir);
}

// ------------------------------------------------------------------
// 1) Ú©Ø§Ù†ÙÛŒÚ¯ Ù¾Ú©ÛŒØ¬
// ------------------------------------------------------------------
echo PHP_EOL.'1) config/workflow.php'.PHP_EOL;

$config = require $AW_ROOT.'/config/workflow.php';

check('route_prefix Ø¨Ø±Ø§Ø¨Ø± workflow Ø§Ø³Øª', $config['route_prefix'] === 'workflow');
check('layout Ù¾ÛŒØ´â€ŒÙØ±Ø¶ behin-layouts.app Ø§Ø³Øª', $config['layout'] === 'behin-layouts.app');
check('Ù¾Ø§Ù„Øª Ø¯Ù‚ÛŒÙ‚Ø§ 7 Ø§Ø¨Ø²Ø§Ø± Ø¯Ø§Ø±Ø¯', count($config['palette']) === 7);

check(
    'ØªØ±ØªÛŒØ¨ Ùˆ Ø§Ù†ÙˆØ§Ø¹ BPMN Ø¯Ø±Ø³Øª Ø§Ø³Øª',
    array_column($config['palette'], 'type') === [
        'bpmn:StartEvent',
        'bpmn:EndEvent',
        'bpmn:UserTask',
        'bpmn:ScriptTask',
        'bpmn:ExclusiveGateway',
        'bpmn:Participant',
        'bpmn:Lane',
    ]
);

check('Ù‡Ù…Ù‡ Ø¢ÛŒÚ©ÙˆÙ†â€ŒÙ‡Ø§ Ù¾Ø± Ø´Ø¯Ù‡â€ŒØ§Ù†Ø¯', count(array_filter(array_column($config['palette'], 'icon'))) === 7);
check('Ù‡Ù…Ù‡ labelÙ‡Ø§ÛŒ ÙØ§Ø±Ø³ÛŒ Ù¾Ø± Ø´Ø¯Ù‡â€ŒØ§Ù†Ø¯', count(array_filter(array_column($config['palette'], 'label'))) === 7);
check('storage.enabled Ù¾ÛŒØ´â€ŒÙØ±Ø¶ true Ø§Ø³Øª', $config['storage']['enabled'] === true);
check('storage.table Ù¾ÛŒØ´â€ŒÙØ±Ø¶ workflow_diagrams Ø§Ø³Øª', $config['storage']['table'] === 'workflow_diagrams');
check('ÙØ§ÛŒÙ„â€ŒØ§ÛŒ Ø¨Ø±Ø§ÛŒ Ø°Ø®ÛŒØ±Ù‡ Ù„Ø§Ø²Ù… Ù†ÛŒØ³Øª (Ø¨Ø¯ÙˆÙ† path)', ! isset($config['storage']['path']));

// ------------------------------------------------------------------
// 2) Ø§Ø¹ØªØ¨Ø§Ø±Ø³Ù†Ø¬ XML
// ------------------------------------------------------------------
echo PHP_EOL.'2) BpmnXml'.PHP_EOL;

$validXml = '<?xml version="1.0" encoding="UTF-8"?>'
    .'<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'
    .'<bpmn:process id="Process_1" /></bpmn:definitions>';

check('XML Ù…Ø¹ØªØ¨Ø± Ù¾Ø°ÛŒØ±ÙØªÙ‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯', BpmnXml::isValid($validXml));
check('XML Ø®Ø±Ø§Ø¨ Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', ! BpmnXml::isValid('<bpmn:definitions><unclosed'));
check('Ø±Ø´ØªÙ‡ Ø®Ø§Ù„ÛŒ Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', ! BpmnXml::isValid(''));
check('Ù…ØªÙ† Ù…Ø¹Ù…ÙˆÙ„ÛŒ Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', ! BpmnXml::isValid('hello world'));
check('Ø³Ù†Ø¯ Ø¨Ø¯ÙˆÙ† definitions Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', ! BpmnXml::isValid('<?xml version="1.0"?><foo />'));

// ------------------------------------------------------------------
// 3) Ù…Ø®Ø²Ù† Ù†Ù…ÙˆØ¯Ø§Ø±Ù‡Ø§ (Ø¯ÛŒØªØ§Ø¨ÛŒØ³Ø› Ø¨Ø¯ÙˆÙ† ÙØ§ÛŒÙ„)
// ------------------------------------------------------------------
echo PHP_EOL.'3) DiagramRepository'.PHP_EOL;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repo = new DiagramRepository($pdo, 'workflow_diagrams');

check('Ø¬Ø¯ÙˆÙ„ Ø®ÙˆØ¯Ú©Ø§Ø± Ø³Ø§Ø®ØªÙ‡ Ùˆ Ø¯Ø± Ø§Ø¨ØªØ¯Ø§ Ø®Ø§Ù„ÛŒ Ø§Ø³Øª', $repo->all() === []);

$document = $repo->save(null, 'ÙØ±Ø§ÛŒÙ†Ø¯ ØªØµÙˆÛŒØ¨', $validXml);
check('Ø´Ù†Ø§Ø³Ù‡ Ù…Ø¹ØªØ¨Ø± ØªÙˆÙ„ÛŒØ¯ Ø´Ø¯', DiagramRepository::isValidId($document['id']));
check('Ù†Ø§Ù… ÙØ§Ø±Ø³ÛŒ Ø­ÙØ¸ Ø´Ø¯', $document['name'] === 'ÙØ±Ø§ÛŒÙ†Ø¯ ØªØµÙˆÛŒØ¨');

$found = $repo->find($document['id']);
check('find Ø³Ù†Ø¯ Ø±Ø§ Ø¨Ø±Ù…ÛŒâ€ŒÚ¯Ø±Ø¯Ø§Ù†Ø¯', $found !== null && $found['xml'] === $validXml);
check('all ÛŒÚ© Ù…ÙˆØ±Ø¯ Ø¯Ø§Ø±Ø¯', count($repo->all()) === 1);

$updated = $repo->save($document['id'], 'Ø¹Ù†ÙˆØ§Ù† Ø¬Ø¯ÛŒØ¯', $validXml.'<!--x-->');
check('Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ Ù‡Ù…Ø§Ù† Ø´Ù†Ø§Ø³Ù‡ Ø±Ø§ Ù†Ú¯Ù‡ Ù…ÛŒâ€ŒØ¯Ø§Ø±Ø¯', $updated['id'] === $document['id']);
check('Ø¨Ø¹Ø¯ Ø§Ø² Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ Ù‡Ù…Ú†Ù†Ø§Ù† ÛŒÚ© Ø³Ù†Ø¯ Ø§Ø³Øª', count($repo->all()) === 1);
check('Ù†Ø§Ù… Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ Ø´Ø¯', $repo->find($document['id'])['name'] === 'Ø¹Ù†ÙˆØ§Ù† Ø¬Ø¯ÛŒØ¯');

check('find Ø¨Ø§ Ø´Ù†Ø§Ø³Ù‡ traversal Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', $repo->find('../evil') === null);
check('delete Ø¨Ø§ Ø´Ù†Ø§Ø³Ù‡ Ù†Ø§Ù…Ø¹ØªØ¨Ø± false Ø§Ø³Øª', $repo->delete('../evil') === false);
check('delete Ù…ÙˆÙÙ‚ true Ø§Ø³Øª', $repo->delete($document['id']) === true);
check('Ø¨Ø¹Ø¯ Ø§Ø² Ø­Ø°Ù Ù…Ø®Ø²Ù† Ø®Ø§Ù„ÛŒ Ø§Ø³Øª', $repo->all() === []);

$anonymous = $repo->save(null, '   ', $validXml);
check('Ù†Ø§Ù… Ø®Ø§Ù„ÛŒ Ø¨Ù‡ Â«Ø¨Ø¯ÙˆÙ† Ø¹Ù†ÙˆØ§Ù†Â» ØªØ¨Ø¯ÛŒÙ„ Ù…ÛŒâ€ŒØ´ÙˆØ¯', $anonymous['name'] === 'Ø¨Ø¯ÙˆÙ† Ø¹Ù†ÙˆØ§Ù†');

try {
    new DiagramRepository($pdo, 'bad-table;drop table users');

    check('Ù†Ø§Ù… Ø¬Ø¯ÙˆÙ„ Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', false);
} catch (RuntimeException) {
    check('Ù†Ø§Ù… Ø¬Ø¯ÙˆÙ„ Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø±Ø¯ Ù…ÛŒâ€ŒØ´ÙˆØ¯', true);
}

// ÙˆØ±ÙˆØ¯ ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ JSON Ù‚Ø¯ÛŒÙ…ÛŒ Ø¨Ù‡ Ø¯ÛŒØªØ§Ø¨ÛŒØ³
$legacyDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arghavan-legacy-test-'.uniqid();
@mkdir($legacyDir, 0755, true);
file_put_contents(
    $legacyDir.DIRECTORY_SEPARATOR.'legacy01.json',
    json_encode([
        'id' => 'legacy01',
        'name' => 'Ù†Ù…ÙˆØ¯Ø§Ø± Ù‚Ø¯ÛŒÙ…ÛŒ',
        'xml' => $validXml,
        'created_at' => '2025-01-01 10:00:00',
        'updated_at' => '2025-01-02 11:00:00',
    ], JSON_UNESCAPED_UNICODE)
);

$legacyPdo = new PDO('sqlite::memory:');
$legacyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacyRepo = new DiagramRepository($legacyPdo, 'workflow_diagrams', $legacyDir);

check('Ø³Ù†Ø¯ Ù‚Ø¯ÛŒÙ…ÛŒ ÙˆØ§Ø±Ø¯ Ø¯ÛŒØªØ§Ø¨ÛŒØ³ Ø´Ø¯', count($legacyRepo->all()) === 1);
check('Ø²Ù…Ø§Ù† Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ Ø³Ù†Ø¯ Ù‚Ø¯ÛŒÙ…ÛŒ Ø­ÙØ¸ Ø´Ø¯', $legacyRepo->find('legacy01')['updated_at'] === '2025-01-02 11:00:00');
check('ÙØ§ÛŒÙ„ Ù‚Ø¯ÛŒÙ…ÛŒ Ù¾Ø³ Ø§Ø² ÙˆØ±ÙˆØ¯ Ø­Ø°Ù Ø´Ø¯', ! is_file($legacyDir.DIRECTORY_SEPARATOR.'legacy01.json'));
check('Ù¾ÙˆØ´Ù‡ Ø®Ø§Ù„ÛŒ Ù‚Ø¯ÛŒÙ…ÛŒ Ø­Ø°Ù Ø´Ø¯', ! is_dir($legacyDir));

removeDirectory($legacyDir);

// ------------------------------------------------------------------
// 4) Ø³Ø§Ø®Øª Ø¢Ø¯Ø±Ø³ assetÙ‡Ø§
// ------------------------------------------------------------------
echo PHP_EOL.'4) Assets'.PHP_EOL;

$GLOBALS['AW_TEST_CONFIG'] = ['workflow' => $config];

$styles = Assets::styles();
check('Ø³Ù‡ CSS Ø¨Ø±Ù…ÛŒâ€ŒÚ¯Ø±Ø¯Ø§Ù†Ø¯', count($styles) === 3);
check('Ù…Ø³ÛŒØ± Ù…Ø­Ù„ÛŒ Ø¨Ù‡ bpmn-js Ø§Ø´Ø§Ø±Ù‡ Ø¯Ø§Ø±Ø¯', strpos($styles[0], 'bpmn-js/assets/diagram-js.css') !== false);
check('Ù†Ø³Ø®Ù‡ Ø±ÙˆÛŒ Ø¢Ø¯Ø±Ø³ Ù†ÙˆØ´ØªÙ‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯', strpos($styles[0], '?v=') !== false);

$scripts = Assets::scripts();
check('Ø¨Ø§Ù†Ø¯Ù„ bpmn-js Ø§ÙˆÙ„ Ø§Ø³Øª', strpos($scripts[0], 'bpmn-modeler.production.min.js') !== false);
check('designer.js Ø¢Ø®Ø± Ø§Ø³Øª', strpos(end($scripts), 'js/designer.js') !== false);
check('CSS Ù…Ø´ØªØ±Ú© Ù¾Ú©ÛŒØ¬ Ø¢Ø¯Ø±Ø³ Ù…Ø­Ù„ÛŒ Ø¯Ø§Ø±Ø¯', strpos(Assets::stylesheet(), 'css/workflow.css') !== false);

$GLOBALS['AW_TEST_CONFIG']['workflow']['assets']['mode'] = 'cdn';
$cdnStyles = Assets::styles();
check('Ø¯Ø± Ø­Ø§Ù„Øª cdn Ø§Ø² unpkg Ù…ÛŒâ€ŒØ¢ÛŒØ¯', strpos($cdnStyles[0], 'https://unpkg.com/bpmn-js@') === 0);
$GLOBALS['AW_TEST_CONFIG']['workflow']['assets']['mode'] = 'local';

// ------------------------------------------------------------------
// 5) ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ Ù‡Ù…Ø±Ø§Ù‡ bpmn-js
// ------------------------------------------------------------------
echo PHP_EOL.'5) Ø¯Ø§Ø±Ø§ÛŒÛŒâ€ŒÙ‡Ø§ÛŒ bpmn-js'.PHP_EOL;

$assetFiles = [
    'resources/assets/bpmn-js/bpmn-modeler.production.min.js',
    'resources/assets/bpmn-js/assets/diagram-js.css',
    'resources/assets/bpmn-js/assets/bpmn-js.css',
    'resources/assets/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css',
    'resources/js/designer.js',
    'resources/css/workflow.css',
];

foreach ($assetFiles as $file) {
    check('Ù…ÙˆØ¬ÙˆØ¯ Ø§Ø³Øª: '.$file, is_file($AW_ROOT.'/'.$file));
}

$bundle = file_get_contents($AW_ROOT.'/resources/assets/bpmn-js/bpmn-modeler.production.min.js');
check('Ø¨Ø§Ù†Ø¯Ù„ Ø³Ø±Ø§Ø³Ø±ÛŒ BpmnJS Ø¯Ø§Ø±Ø¯', strpos($bundle, '.BpmnJS=') !== false);

$iconCss = file_get_contents($AW_ROOT.'/resources/assets/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css');

foreach (array_column($config['palette'], 'icon') as $icon) {
    check('Ø¢ÛŒÚ©ÙˆÙ† Ø¯Ø± CSS Ù…ÙˆØ¬ÙˆØ¯ Ø§Ø³Øª: '.$icon, strpos($iconCss, '.'.$icon) !== false);
}

// ------------------------------------------------------------------
// 6) Ø§Ù…Ù†ÛŒØª Ø³Ø±Ùˆ assetÙ‡Ø§ (AssetController)
// ------------------------------------------------------------------
echo PHP_EOL.'6) AssetController'.PHP_EOL;

$assetController = new AssetController();
$resolveMethod = new ReflectionMethod($assetController, 'resolve');
$resolveMethod->setAccessible(true);
$packageMethod = new ReflectionMethod($assetController, 'packagePath');
$packageMethod->setAccessible(true);
$mimeMethod = new ReflectionMethod($assetController, 'mime');
$mimeMethod->setAccessible(true);

$jsRoot = $AW_ROOT.'/resources/js';

check(
    'resolve ÙØ§ÛŒÙ„ Ù…Ø¬Ø§Ø² Ø±Ø§ Ù¾ÛŒØ¯Ø§ Ù…ÛŒâ€ŒÚ©Ù†Ø¯',
    $resolveMethod->invoke($assetController, $jsRoot, 'designer.js') === realpath($jsRoot.'/designer.js')
);

check(
    'resolve Ù…Ø³ÛŒØ± traversal Ø±Ø§ Ø±Ø¯ Ù…ÛŒâ€ŒÚ©Ù†Ø¯',
    $resolveMethod->invoke($assetController, $jsRoot, '../../composer.json') === null
);

check(
    'resolve Ù¾Ø³ÙˆÙ†Ø¯ ØºÛŒØ±Ù…Ø¬Ø§Ø² Ø±Ø§ Ø±Ø¯ Ù…ÛŒâ€ŒÚ©Ù†Ø¯',
    $resolveMethod->invoke($assetController, $AW_ROOT, 'composer.json') === null
);

check('resolve ÙØ§ÛŒÙ„ Ù†Ø§Ù…ÙˆØ¬ÙˆØ¯ null Ø§Ø³Øª', $resolveMethod->invoke($assetController, $jsRoot, 'missing.js') === null);
check('resolve Ø±Ø´ØªÙ‡ Ø®Ø§Ù„ÛŒ null Ø§Ø³Øª', $resolveMethod->invoke($assetController, $jsRoot, '') === null);
check('resolve Ù†Ø§Ù… ÙØ§ÛŒÙ„ Ø¨Ø§ null byte Ø±Ø¯ Ù…ÛŒâ€ŒÚ©Ù†Ø¯', $resolveMethod->invoke($assetController, $jsRoot, "designer.js\0.txt") === null);

check(
    'packagePath Ø¨Ù‡ Ø±ÛŒØ´Ù‡ Ù¾Ú©ÛŒØ¬ Ù…ÛŒâ€ŒØ±Ø³Ø¯',
    realpath($packageMethod->invoke($assetController, 'resources')) === realpath($AW_ROOT.'/resources')
);

check('mime Ø¨Ø±Ø§ÛŒ js Ø¯Ø±Ø³Øª Ø§Ø³Øª', strpos($mimeMethod->invoke($assetController, 'a.js'), 'javascript') !== false);
check('mime Ø¨Ø±Ø§ÛŒ css Ø¯Ø±Ø³Øª Ø§Ø³Øª', $mimeMethod->invoke($assetController, 'a.css') === 'text/css; charset=utf-8');
check('mime Ø¨Ø±Ø§ÛŒ svg Ø¯Ø±Ø³Øª Ø§Ø³Øª', $mimeMethod->invoke($assetController, 'a.svg') === 'image/svg+xml');

// ------------------------------------------------------------------
// Ø¬Ù…Ø¹â€ŒØ¨Ù†Ø¯ÛŒ
// ------------------------------------------------------------------
echo PHP_EOL.str_repeat('-', 50).PHP_EOL;
echo 'Ù†ØªÛŒØ¬Ù‡: '.$AW_PASSED.' Ù…ÙˆÙÙ‚ØŒ '.$AW_FAILED.' Ù†Ø§Ù…ÙˆÙÙ‚'.PHP_EOL;

exit($AW_FAILED > 0 ? 1 : 0);
