<?php

namespace ArghavanWorkflow;

use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Services\DiagramSynchronizer;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow.php', 'workflow');

        // مخزن نمودارها؛ جدول‌ها با migrationهای همین پکیج ساخته می‌شوند
        // (php artisan migrate) و ذخیره‌سازی با Eloquent انجام می‌شود.
        $this->app->singleton(DiagramSynchronizer::class, fn (): DiagramSynchronizer => new DiagramSynchronizer());

        $this->app->singleton(DiagramRepository::class, function ($app): DiagramRepository {
            return new DiagramRepository($app->make(DiagramSynchronizer::class));
        });
    }

    public function boot(): void
    {
        // جدول‌های پکیج: یک جدول برای فرایندها و یک جدول برای هر نوع المان
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workflow');

        // انتشار migrationها برای پروژه‌هایی که می‌خواهند فایل‌ها را در
        // مسیر database/migrations خود داشته باشند.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Migrations' => database_path('migrations'),
            ], 'workflow-migrations');
        }
    }
}