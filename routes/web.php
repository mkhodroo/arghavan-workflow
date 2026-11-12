<?php

use ArghavanWorkflow\Http\Controllers\AssetController;
use ArghavanWorkflow\Http\Controllers\DesignerController;
use ArghavanWorkflow\Http\Controllers\DesignerScriptConfigController;
use ArghavanWorkflow\Http\Controllers\DesignerTaskConfigController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('workflow.route_prefix', 'workflow'),
    'middleware' => config('workflow.middleware', ['web']),
    'as' => 'workflow.',
], function () {
    // فهرست نمودارهای ذخیره‌شده
    Route::get('/', [DesignerController::class, 'index'])->name('index');

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
