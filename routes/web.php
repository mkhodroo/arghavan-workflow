<?php

use ArghavanWorkflow\Http\Controllers\AssetController;
use ArghavanWorkflow\Http\Controllers\Api\InboxController;
use ArghavanWorkflow\Http\Controllers\Api\ProcessController;
use ArghavanWorkflow\Http\Controllers\DesignerController;
use ArghavanWorkflow\Http\Controllers\DesignerFlowConfigController;
use ArghavanWorkflow\Http\Controllers\DesignerScriptConfigController;
use ArghavanWorkflow\Http\Controllers\DesignerTaskConfigController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('workflow2.route_prefix', 'workflow2'),
    'middleware' => config('workflow2.middleware', ['web']),
    'as' => 'workflow2.',
], function () {
    // فهرست نمودارهای ذخیره‌شده
    Route::get('/', [DesignerController::class, 'index'])->name('index');

    // صفحه شروع فرآیندها (لیست فرآیندهایی که کاربر می‌تواند اجرا کند)
    Route::get('/start', [ProcessController::class, 'startPage'])->name('start');

    // صفحه کارتابل کاربر
    Route::get('/inbox', [InboxController::class, 'inboxPage'])->name('inbox');

    // پیکربندی تسک (تنظیمات UserTask) درون نمودار
    // نکته: این روت‌ها باید پیش از روت wildcard «/designer/{doc?}» ثبت شوند،
    // وگرنه «/designer/element-config» به‌عنوان {doc} تفسیر می‌شود.
    Route::get('/designer/element-config', [DesignerTaskConfigController::class, 'list'])->name('designer.element-config.list');
    Route::get('/designer/element-config/{doc}/{element}', [DesignerTaskConfigController::class, 'get'])->name('designer.element-config.get');
    Route::post('/designer/element-config/{doc}/{element}', [DesignerTaskConfigController::class, 'save'])->name('designer.element-config.save');

    // پیکربندی تسک اسکریپت (تنظیمات ScriptTask) درون نمودار
    // نکته: این روت‌ها باید پیش از روت wildcard «/designer/{doc?}» ثبت شوند،
    // وگرنه «/designer/script-config» به‌عنوان {doc} تفسیر می‌شود.
    Route::get('/designer/script-config', [DesignerScriptConfigController::class, 'list'])->name('designer.script-config.list');
    Route::get('/designer/script-config/{doc}/{element}', [DesignerScriptConfigController::class, 'get'])->name('designer.script-config.get');
    Route::post('/designer/script-config/{doc}/{element}', [DesignerScriptConfigController::class, 'save'])->name('designer.script-config.save');

    // پیکربندی جریان ترتیبی (Sequence Flow): انتخاب شرط بررسی‌شونده در گیت‌وی
    // نکته: این روت‌ها باید پیش از روت wildcard «/designer/{doc?}» ثبت شوند.
    Route::get('/designer/flow-config', [DesignerFlowConfigController::class, 'list'])->name('designer.flow-config.list');
    Route::get('/designer/flow-config/{doc}/{element}', [DesignerFlowConfigController::class, 'get'])->name('designer.flow-config.get');
    Route::post('/designer/flow-config/{doc}/{element}', [DesignerFlowConfigController::class, 'save'])->name('designer.flow-config.save');

    // محیط طراحی؛ {doc} اختیاری است (شناسه نمودار ذخیره‌شده)
    Route::get('/designer/{doc?}', [DesignerController::class, 'designer'])->name('designer');

    // ذخیره نمودار (AJAX)
    Route::post('/save', [DesignerController::class, 'save'])->name('save');

    // حذف نمودار
    Route::delete('/diagram/{doc}', [DesignerController::class, 'destroy'])->name('destroy');

    // سرو فایل‌های استاتیک پکیج (bpmn-js + فایل‌های خود پکیج) بدون نیاز به publish
    Route::get('/assets/{path}', [AssetController::class, 'show'])
        ->where('path', '.*')
        ->name('assets');
});
