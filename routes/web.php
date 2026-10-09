<?php

use ArghavanWorkflow\Http\Controllers\AssetController;
use ArghavanWorkflow\Http\Controllers\DesignerController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('workflow.route_prefix', 'workflow'),
    'middleware' => config('workflow.middleware', ['web']),
    'as' => 'workflow.',
], function () {
    // فهرست نمودارهای ذخیره‌شده
    Route::get('/', [DesignerController::class, 'index'])->name('index');

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
