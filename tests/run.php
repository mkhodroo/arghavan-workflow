<?php

/**
 * تست‌های سبک پکیج «ارغوان ورکفلو» (بدون نیاز به نصب لاراول).
 *
 * اجرا:  php tests/run.php
 *
 * استاب‌های حداقلی helperهای لاراول (config / route / storage_path)
 * فقط وقتی تعریف نشده باشند ساخته می‌شوند تا اجرای تست درون پروژه
 * لاراول هم بی‌خطر باشد.
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
require $AW_ROOT.'/src/Support/TableNames.php';
require $AW_ROOT.'/src/Support/BpmnXmlParser.php';
require __DIR__.'/stubs.php';
require $AW_ROOT.'/src/Http/Controllers/AssetController.php';

use ArghavanWorkflow\Http\Controllers\AssetController;
use ArghavanWorkflow\Support\Assets;
use ArghavanWorkflow\Support\BpmnXml;
use ArghavanWorkflow\Support\BpmnXmlParser;
use ArghavanWorkflow\Support\TableNames;

// ------------------------------------------------------------------
// ابزار check
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

// ------------------------------------------------------------------
// 1) کانفیگ پکیج
// ------------------------------------------------------------------
echo PHP_EOL.'1) config/workflow.php'.PHP_EOL;

$config = require $AW_ROOT.'/config/workflow.php';

check('route_prefix برابر workflow است', $config['route_prefix'] === 'workflow');
check('layout پیش‌فرض behin-layouts.app است', $config['layout'] === 'behin-layouts.app');
check('پالت دقیقا 7 ابزار دارد', count($config['palette']) === 7);

check(
    'ترتیب و انواع BPMN درست است',
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

check('همه آیکون‌ها پر شده‌اند', count(array_filter(array_column($config['palette'], 'icon'))) === 7);
check('همه labelهای فارسی پر شده‌اند', count(array_filter(array_column($config['palette'], 'label'))) === 7);
check('storage.enabled پیش‌فرض true است', $config['storage']['enabled'] === true);
check('storage.table پیش‌فرض workflow_processes است', $config['storage']['table'] === 'workflow_processes');
check(
    'جدول همه‌ی المان‌ها در کانفیگ تعریف شده است',
    array_keys($config['storage']['tables']) === [
        'start_events',
        'end_events',
        'user_tasks',
        'script_tasks',
        'exclusive_gateways',
        'pools',
        'lanes',
        'sequence_flows',
        'cases',
        'inbox',
        'variables',
    ]
);
check('ساخت خودکار جدول (PDO) حذف شده است', ! isset($config['storage']['auto_create']));
check('ورود فایل JSON قدیمی حذف شده است', ! isset($config['storage']['import_legacy']));
check('فایل‌ای برای ذخیره لازم نیست (بدون path)', ! isset($config['storage']['path']));

// ------------------------------------------------------------------
// 2) اعتبارسنج XML
// ------------------------------------------------------------------
echo PHP_EOL.'2) BpmnXml'.PHP_EOL;

$validXml = '<?xml version="1.0" encoding="UTF-8"?>'
    .'<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'
    .'<bpmn:process id="Process_1" /></bpmn:definitions>';

check('XML معتبر پذیرفته می‌شود', BpmnXml::isValid($validXml));
check('XML خراب رد می‌شود', ! BpmnXml::isValid('<bpmn:definitions><unclosed'));
check('رشته خالی رد می‌شود', ! BpmnXml::isValid(''));
check('متن معمولی رد می‌شود', ! BpmnXml::isValid('hello world'));
check('سند بدون definitions رد می‌شود', ! BpmnXml::isValid('<?xml version="1.0"?><foo />'));

// ------------------------------------------------------------------
// 3) تجزیه‌ی XML به جداول المان‌ها (BpmnXmlParser)
// ------------------------------------------------------------------
echo PHP_EOL.'3) BpmnXmlParser'.PHP_EOL;

$diagramXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:aw="http://arghavan-workflow.local/config"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:collaboration id="Collaboration_1">
    <bpmn:participant id="Participant_1" name="استخر اصلی" processRef="Process_1" />
  </bpmn:collaboration>
  <bpmn:process id="Process_1" name="فرایند تصویب" isExecutable="true">
    <bpmn:laneSet id="LaneSet_1">
      <bpmn:lane id="Lane_1" name="کارشناس">
        <bpmn:flowNodeRef>StartEvent_1</bpmn:flowNodeRef>
      </bpmn:lane>
    </bpmn:laneSet>
    <bpmn:startEvent id="StartEvent_1" name="شروع" />
    <bpmn:userTask id="UserTask_1" name="بررسی">
      <bpmn:extensionElements>
        <aw:config>
          <aw:formId>12</aw:formId>
          <aw:actor>5</aw:actor>
          <aw:roleId>3</aw:roleId>
          <aw:assignment_type>normal</aw:assignment_type>
          <aw:case_name>case_1</aw:case_name>
          <aw:duration>30</aw:duration>
          <aw:order>1</aw:order>
          <aw:is_preview>1</aw:is_preview>
          <aw:show_cancel_button>0</aw:show_cancel_button>
          <aw:color>#111111</aw:color>
          <aw:background>#eeeeee</aw:background>
        </aw:config>
      </bpmn:extensionElements>
    </bpmn:userTask>
    <bpmn:exclusiveGateway id="Gateway_1" name="تایید؟" default="Flow_2" />
    <bpmn:scriptTask id="ScriptTask_1" name="ثبت" scriptFormat="javascript">
      <bpmn:script>return 1;</bpmn:script>
    </bpmn:scriptTask>
    <bpmn:endEvent id="EndEvent_1" name="پایان">
      <bpmn:terminateEventDefinition />
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="UserTask_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Gateway_1" targetRef="EndEvent_1">
      <bpmn:conditionExpression>${ok}</bpmn:conditionExpression>
    </bpmn:sequenceFlow>
  </bpmn:process>
</bpmn:definitions>
XML;

$parsed = BpmnXmlParser::parse($diagramXml);

check('فرایند استخراج شد', count($parsed['processes']) === 1);
check('نام فرایند درست است', $parsed['processes'][0]['name'] === 'فرایند تصویب');
check('isExecutable خوانده شد', $parsed['processes'][0]['is_executable'] === true);

check('المان شروع استخراج شد', count($parsed['start_events']) === 1);
check('شناسه المان شروع درست است', $parsed['start_events'][0]['element_id'] === 'StartEvent_1');
check('نام المان شروع درست است', $parsed['start_events'][0]['name'] === 'شروع');

check('المان پایان استخراج شد', count($parsed['end_events']) === 1);
check('نوع رویداد پایان terminate است', $parsed['end_events'][0]['event_definition'] === 'terminate');

check('تسک کاربری استخراج شد', count($parsed['user_tasks']) === 1);
$task = $parsed['user_tasks'][0];
check('شناسه تسک کاربری درست است', $task['element_id'] === 'UserTask_1');
check('نام تسک کاربری درست است', $task['name'] === 'بررسی');
check('form_id از تنظیمات قدیمی خوانده شد', $task['form_id'] === '12');
check('actor از تنظیمات قدیمی خوانده شد', $task['actor'] === '5');
check('role_id از تنظیمات قدیمی خوانده شد', $task['role_id'] === '3');
check('assignment_type خوانده شد', $task['assignment_type'] === 'normal');
check('case_name خوانده شد', $task['case_name'] === 'case_1');
check('duration خوانده شد', $task['duration'] === '30');
check('order خوانده شد', $task['order'] === '1');
check('is_preview بولی شد', $task['is_preview'] === true);
check('show_cancel_button بولی شد', $task['show_cancel_button'] === false);
check('color خوانده شد', $task['color'] === '#111111');
check('background خوانده شد', $task['background'] === '#eeeeee');

check('اسکریپت تسک استخراج شد', count($parsed['script_tasks']) === 1);
check('script_format درست است', $parsed['script_tasks'][0]['script_format'] === 'javascript');
check('متن اسکریپت درست است', $parsed['script_tasks'][0]['script'] === 'return 1;');

check('گیت‌وی شرطی استخراج شد', count($parsed['exclusive_gateways']) === 1);
check('جریان پیش‌فرض گیت‌وی درست است', $parsed['exclusive_gateways'][0]['default_flow'] === 'Flow_2');

check('استخر استخراج شد', count($parsed['pools']) === 1);
check('processRef استخر درست است', $parsed['pools'][0]['process_ref'] === 'Process_1');

check('لین استخراج شد', count($parsed['lanes']) === 1);
check('والد لین استخر است', $parsed['lanes'][0]['parent_element_id'] === 'Participant_1');

check('جریان‌های ترتیبی استخراج شدند', count($parsed['sequence_flows']) === 2);
check('sourceRef درست است', $parsed['sequence_flows'][0]['source_ref'] === 'StartEvent_1');
check('targetRef درست است', $parsed['sequence_flows'][0]['target_ref'] === 'UserTask_1');
check('conditionExpression خوانده شد', $parsed['sequence_flows'][1]['condition_expression'] === '${ok}');
check('جریان پیش‌فرض علامت خورد', $parsed['sequence_flows'][1]['is_default'] === true);

check('userTaskIds فقط تسک کاربری برمی‌گرداند', BpmnXmlParser::userTaskIds($diagramXml) === ['UserTask_1']);
check('userTaskIds برای XML خراب آرایه خالی است', BpmnXmlParser::userTaskIds('<broken') === []);

try {
    BpmnXmlParser::parse('<broken');

    check('XML نامعتبر استثنا می‌دهد', false);
} catch (InvalidArgumentException) {
    check('XML نامعتبر استثنا می‌دهد', true);
}

// ------------------------------------------------------------------
// 3-ب) نام جداول و فایل‌های migration/مدل
// ------------------------------------------------------------------
echo PHP_EOL.'3-ب) جداول و مدل‌ها'.PHP_EOL;

check('نام جدول فرایندها پیش‌فرض است', TableNames::processes() === 'workflow_processes');
check('نام جدول تسک‌های کاربری پیش‌فرض است', TableNames::userTasks() === 'workflow_user_tasks');
check('نام جدول جریان‌های ترتیبی پیش‌فرض است', TableNames::sequenceFlows() === 'workflow_sequence_flows');

$packageFiles = [
    'src/Migrations/2026_11_06_000001_create_workflow_processes_table.php',
    'src/Migrations/2026_11_06_000002_create_workflow_start_events_table.php',
    'src/Migrations/2026_11_06_000003_create_workflow_end_events_table.php',
    'src/Migrations/2026_11_06_000004_create_workflow_user_tasks_table.php',
    'src/Migrations/2026_11_06_000005_create_workflow_script_tasks_table.php',
    'src/Migrations/2026_11_06_000006_create_workflow_exclusive_gateways_table.php',
    'src/Migrations/2026_11_06_000007_create_workflow_pools_table.php',
    'src/Migrations/2026_11_06_000008_create_workflow_lanes_table.php',
    'src/Migrations/2026_11_06_000009_create_workflow_sequence_flows_table.php',
    'src/Migrations/2026_11_06_000010_migrate_legacy_workflow_diagrams_table.php',
    'src/Models/WorkflowProcess.php',
    'src/Models/Elements/BpmnElement.php',
    'src/Models/Elements/StartEvent.php',
    'src/Models/Elements/EndEvent.php',
    'src/Models/Elements/UserTask.php',
    'src/Models/Elements/ScriptTask.php',
    'src/Models/Elements/ExclusiveGateway.php',
    'src/Models/Elements/Pool.php',
    'src/Models/Elements/Lane.php',
    'src/Models/Elements/SequenceFlow.php',
    'src/Services/DiagramSynchronizer.php',
];

foreach ($packageFiles as $file) {
    check('موجود است: '.$file, is_file($AW_ROOT.'/'.$file));
}

$repositorySource = (string) file_get_contents($AW_ROOT.'/src/Services/DiagramRepository.php');
check(
    'مخزن دیگر از PDO استفاده نمی‌کند',
    preg_match('/\bnew\s+PDO\b|PDO::|use\s+PDO\s*;/', $repositorySource) === 0
);
check('مخزن از مدل فرایند استفاده می‌کند', strpos($repositorySource, 'WorkflowProcess') !== false);

// ------------------------------------------------------------------
// 4) ساخت آدرس assetها
// ------------------------------------------------------------------
echo PHP_EOL.'4) Assets'.PHP_EOL;

$GLOBALS['AW_TEST_CONFIG'] = ['workflow' => $config];

$styles = Assets::styles();
check('سه CSS برمی‌گرداند', count($styles) === 3);
check('مسیر محلی به bpmn-js اشاره دارد', strpos($styles[0], 'bpmn-js/assets/diagram-js.css') !== false);
check('نسخه روی آدرس نوشته می‌شود', strpos($styles[0], '?v=') !== false);

$scripts = Assets::scripts();
check('باندل bpmn-js اول است', strpos($scripts[0], 'bpmn-modeler.production.min.js') !== false);
check('designer.js آخر است', strpos(end($scripts), 'js/designer.js') !== false);
check('CSS مشترک پکیج آدرس محلی دارد', strpos(Assets::stylesheet(), 'css/workflow.css') !== false);

$GLOBALS['AW_TEST_CONFIG']['workflow']['assets']['mode'] = 'cdn';
$cdnStyles = Assets::styles();
check('در حالت cdn از unpkg می‌آید', strpos($cdnStyles[0], 'https://unpkg.com/bpmn-js@') === 0);
$GLOBALS['AW_TEST_CONFIG']['workflow']['assets']['mode'] = 'local';

// ------------------------------------------------------------------
// 5) فایل‌های همراه bpmn-js
// ------------------------------------------------------------------
echo PHP_EOL.'5) دارایی‌های bpmn-js'.PHP_EOL;

$assetFiles = [
    'resources/assets/bpmn-js/bpmn-modeler.production.min.js',
    'resources/assets/bpmn-js/assets/diagram-js.css',
    'resources/assets/bpmn-js/assets/bpmn-js.css',
    'resources/assets/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css',
    'resources/js/designer.js',
    'resources/css/workflow.css',
];

foreach ($assetFiles as $file) {
    check('موجود است: '.$file, is_file($AW_ROOT.'/'.$file));
}

$bundle = file_get_contents($AW_ROOT.'/resources/assets/bpmn-js/bpmn-modeler.production.min.js');
check('باندل سراسری BpmnJS دارد', strpos($bundle, '.BpmnJS=') !== false);

$iconCss = file_get_contents($AW_ROOT.'/resources/assets/bpmn-js/assets/bpmn-font/css/bpmn-embedded.css');

foreach (array_column($config['palette'], 'icon') as $icon) {
    check('آیکون در CSS موجود است: '.$icon, strpos($iconCss, '.'.$icon) !== false);
}

// ------------------------------------------------------------------
// 6) امنیت سرو assetها (AssetController)
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
    'resolve فایل مجاز را پیدا می‌کند',
    $resolveMethod->invoke($assetController, $jsRoot, 'designer.js') === realpath($jsRoot.'/designer.js')
);

check(
    'resolve مسیر traversal را رد می‌کند',
    $resolveMethod->invoke($assetController, $jsRoot, '../../composer.json') === null
);

check(
    'resolve پسوند غیرمجاز را رد می‌کند',
    $resolveMethod->invoke($assetController, $AW_ROOT, 'composer.json') === null
);

check('resolve فایل ناموجود null است', $resolveMethod->invoke($assetController, $jsRoot, 'missing.js') === null);
check('resolve رشته خالی null است', $resolveMethod->invoke($assetController, $jsRoot, '') === null);
check('resolve نام فایل با null byte رد می‌کند', $resolveMethod->invoke($assetController, $jsRoot, "designer.js\0.txt") === null);

check(
    'packagePath به ریشه پکیج می‌رسد',
    realpath($packageMethod->invoke($assetController, 'resources')) === realpath($AW_ROOT.'/resources')
);

check('mime برای js درست است', strpos($mimeMethod->invoke($assetController, 'a.js'), 'javascript') !== false);
check('mime برای css درست است', $mimeMethod->invoke($assetController, 'a.css') === 'text/css; charset=utf-8');
check('mime برای svg درست است', $mimeMethod->invoke($assetController, 'a.svg') === 'image/svg+xml');

// ------------------------------------------------------------------
// جمع‌بندی
// ------------------------------------------------------------------
echo PHP_EOL.str_repeat('-', 50).PHP_EOL;
echo 'نتیجه: '.$AW_PASSED.' موفق، '.$AW_FAILED.' ناموفق'.PHP_EOL;

exit($AW_FAILED > 0 ? 1 : 0);
