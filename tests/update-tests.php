<?php

declare(strict_types=1);

/**
 * Automation for updating tests/run.php palette/checks.
 * Run: php -f tests/update-tests.php
 */

$cwd = __DIR__;
$file = $cwd . '/run.php';
$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "Failed to read $file\n");
    exit(1);
}

$oldPal = "count($config['palette']) === 5";
$newPal = "count($config['palette']) === 7";
if (strpos($content, $oldPal) !== false) {
    $content = str_replace($oldPal, $newPal, $content);
    file_put_contents($file, $content);
    echo "Patched palette count to 7\n";
} else {
    echo "Palette check not found; skipped.\n";
}

$oldList = "array_column($config['palette'], 'type') === [\n        'bpmn:StartEvent',\n        'bpmn:EndEvent',\n        'bpmn:UserTask',\n        'bpmn:ScriptTask',\n        'bpmn:ExclusiveGateway',\n    ]";
$newList = "array_column($config['palette'], 'type') === [\n        'bpmn:StartEvent',\n        'bpmn:EndEvent',\n        'bpmn:UserTask',\n        'bpmn:ScriptTask',\n        'bpmn:ExclusiveGateway',\n        'bpmn:Participant',\n        'bpmn:Lane',\n    ]";
if (strpos($content, $oldList) !== false) {
    $content = str_replace($oldList, $newList, $content);
    file_put_contents($file, $content);
    echo "Patched palette type list to 7\n";
} else {
    $oldList2 = "array_column($config['palette'], 'type') === [\n        'bpmn:StartEvent',\n        'bpmn:EndEvent',\n        'bpmn:UserTask',\n        'bpmn:ScriptTask',\n        'bpmn:ExclusiveGateway',\n    ]";
    if (strpos($content, $oldList2) !== false) {
        $newList2 = $oldList2 . "        'bpmn:Participant',\n        'bpmn:Lane',\n    ]";
        $content = str_replace($oldList2, $newList2, $content);
        file_put_contents($file, $content);
        echo "Patched palette type list to 7 (alt)\n";
    } else {
        echo "Palette type list not found; skipped.\n";
    }
}

$marker = "check('بعد از حذف مخزن خالی است', $repo->all() === []);";
if (strpos($content, $marker) === false) {
    $dbCheck = <<<'PHP'
$storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arghavan-workflow-test-' . uniqid();
$repo = new DiagramRepository($storageDir);

check('در ابتدا خالی است', $repo->all() === []);
check('save به فایل ذخیره می‌کند', $repo->save(null, 'test', $validXml) !== null);
check('find در دیتابیس می‌یابد', $repo->find('test-id') !== null);
check('delete حذف می‌کند', $repo->delete('test-id') === true);

removeDirectory($storageDir);

// ------------------------------------------------------------------
// 3b) سازگاری storage.mode و جدول
// ------------------------------------------------------------------
echo PHP_EOL . '3b) storage.mode و table'.PHP_EOL;

$storageConfig = $config['storage'];
check('storage.mode درست است', $storageConfig['mode'] === 'db');
check('جدول storage درست است', $storageConfig['table'] === 'workflow_diagrams');

PHP;
    $content = str_replace($marker, $dbCheck . "\n" . $marker, $content);
    file_put_contents($file, $content);
    echo "Added storage mode/table check\n";
} else {
    echo "Storage check already present; skipped.\n";
}

echo "Done.\n";