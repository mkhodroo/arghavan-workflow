<?php

use ArghavanWorkflow\Http\Controllers\Api\ProcessController;
use ArghavanWorkflow\Http\Controllers\Api\InboxController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('workflow2.api_route_prefix', 'workflow2/api'),
    'middleware' => config('workflow2.middleware', ['web', 'auth']),
    'as' => 'workflow2.api.',
], function () {
    // فهرست فرآیندهای ذخیره‌شده
    Route::get('/processes', [ProcessController::class, 'index'])->name('processes.index');

    // ایجاد / شروع یک فرآیند جدید (ذخیره نمودار BPMN)
    Route::post('/processes', [ProcessController::class, 'store'])->name('processes.store');

    // نمایش جزئیات یک فرآیند
    Route::get('/processes/{process}', [ProcessController::class, 'show'])->name('processes.show');

    // آماده‌سازی فرآیند برای اجرا (شناسه فرآیند برگردانده می‌شود تا موتور اجرا برنامه میزبان از آن استفاده کند)
    Route::post('/processes/{process}/start', [ProcessController::class, 'start'])->name('processes.start');

    // کارتابل کاربر
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{inbox}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{inbox}/done', [InboxController::class, 'done'])->name('inbox.done');
    Route::post('/inbox/{inbox}/next', [InboxController::class, 'next'])->name('inbox.next');
    Route::post('/inbox/{inbox}/complete', [InboxController::class, 'complete'])->name('inbox.complete');

    // Task form page (for editing/creating tasks in inbox)
    Route::get('/inbox/{inbox}/form', [InboxController::class, 'formPage'])->name('inbox.formPage');
});
