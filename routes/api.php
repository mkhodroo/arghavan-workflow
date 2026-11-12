<?php

use ArghavanWorkflow\Http\Controllers\Api\ProcessController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('workflow.route_prefix', 'workflow'),
    'middleware' => config('workflow.middleware', ['web', 'auth']),
    'as' => 'workflow.api.',
], function () {
    // فهرست فرآیندهای ذخیره‌شده
    Route::get('/processes', [ProcessController::class, 'index'])->name('processes.index');

    // ایجاد / شروع یک فرآیند جدید (ذخیره نمودار BPMN)
    Route::post('/processes', [ProcessController::class, 'store'])->name('processes.store');

    // نمایش جزئیات یک فرآیند
    Route::get('/processes/{process}', [ProcessController::class, 'show'])->name('processes.show');

    // آماده‌سازی فرآیند برای اجرا (شناسه فرآیند برگردانده می‌شود تا موتور اجرا برنامه میزبان از آن استفاده کند)
    Route::post('/processes/{process}/start', [ProcessController::class, 'start'])->name('processes.start');
});
