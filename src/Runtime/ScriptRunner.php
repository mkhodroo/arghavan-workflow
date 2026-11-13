<?php

namespace ArghavanWorkflow\Runtime;

use ArghavanWorkflow\Models\Runtime\WorkflowCase;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * اجرای مستقل اسکریپت‌های «تسک اسکریپت».
 *
 * اسکریپت‌ها در جدول wf_scripts (پکیج behin-simple-workflow) تعریف می‌شوند و
 * فایل اجرایی آن‌ها در فضای‌نام Behin\SimpleWorkflow\Controllers\Scripts\ است.
 * این رانر بدون وابستگی به کنترلرهای behin، رکورد اسکریپت را می‌خواند، کلاس
 * اجرایی را لود و با پرونده‌ی فعلی اجرا می‌کند.
 */
class ScriptRunner
{
    /**
     * اجرای اسکریپت برای یک پرونده.
     *
     * خروجی: ['ok' => true, 'output' => mixed] در موفقیت و
     *        ['ok' => false, 'error' => string] در خطا.
     *
     * @return array{ok: bool, output?: mixed, error?: string}
     */
    public static function run($scriptId, WorkflowCase $case): array
    {
        if ($scriptId === null || $scriptId === '') {
            return ['ok' => false, 'error' => 'Script task has no script assigned.'];
        }

        $script = DB::table('wf_scripts')->where('id', $scriptId)->first();

        if ($script === null) {
            return ['ok' => false, 'error' => 'Script not found: ' . $scriptId];
        }

        $class = self::resolveClass($script->executive_file ?? null, $script->content ?? null);

        if ($class === null) {
            return ['ok' => false, 'error' => 'Executive file of the script is not defined.'];
        }

        try {
            return DB::transaction(function () use ($class, $case) {
                $instance = new $class($case);
                $output = $instance->execute();

                // سازگار با رفتار behin: خروجی truthy یعنی خطا (مثلاً پیام اعتبارسنجی)
                if ($output !== null && $output !== false && $output !== '') {
                    return ['ok' => false, 'error' => is_string($output) ? $output : json_encode($output)];
                }

                return ['ok' => true, 'output' => $output];
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * کلاس اجرایی اسکریپت را پیدا و در صورت لزوم از محتوای ذخیره‌شده لود می‌کند.
     */
    private static function resolveClass(?string $executiveFile, ?string $content): ?string
    {
        if ($executiveFile === null || trim($executiveFile) === '') {
            return null;
        }

        $executiveFile = trim($executiveFile);
        $class = 'Behin\\SimpleWorkflow\\Controllers\\Scripts\\' . $executiveFile;

        if (! class_exists($class) && $content) {
            // بارگذاری کلاس از محتوای ذخیره‌شده در دیتابیس (مانند behin-simple-workflow)
            $code = preg_replace('/^<\?php\s*/', '', $content);
            try {
                eval($code);
            } catch (Throwable $e) {
                return null;
            }
        }

        return class_exists($class) ? $class : null;
    }
}
