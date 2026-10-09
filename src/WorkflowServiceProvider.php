<?php

namespace ArghavanWorkflow;

use ArghavanWorkflow\Services\DiagramRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow.php', 'workflow');

        // مخزن نمودارها؛ اتصال از کانفیگ و نام جدول با پیشوند اتصال.
        // جدول در صورت نبود به‌صورت خودکار ساخته می‌شود (بدون migration).
        $this->app->singleton(DiagramRepository::class, function () {
            $connection = DB::connection(config('workflow.storage.connection'));

            return new DiagramRepository(
                $connection->getPdo(),
                $connection->getTablePrefix().(string) config('workflow.storage.table', 'workflow_diagrams'),
                (string) config('workflow.storage.legacy_path', storage_path('arghavan-workflow')),
                (bool) config('workflow.storage.import_legacy', true),
                (bool) config('workflow.storage.auto_create', true)
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workflow');

        // انتشار فایل (publish) حذف شد: پکیج هیچ فایلی تولید نمی‌کند؛
        // کانفیگ با mergeConfigFrom ادغام می‌شود و ویوها مستقیم لود می‌شوند.
    }
}
