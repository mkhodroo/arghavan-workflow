<?php

namespace ArghavanWorkflow\Support;

/**
 * نام جداول پکیج و اتصال دیتابیس مربوطه.
 *
 * همه‌ی جدول‌ها از کانفیگ خوانده می‌شوند تا در پروژه‌ی میزبان قابل تغییر باشند؛
 * مقادیر پیش‌فرض در همین کلاس نگه داشته می‌شوند تا migrationها و مدل‌ها
 * همیشه یک نام را ببینند.
 */
final class TableNames
{
    /**
     * جدول فرایندها (هر نمودار BPMN = یک فرایند).
     */
    public const PROCESSES = 'workflow_processes';

    /**
     * جدول المان‌ها/گره‌ها؛ هر نوع المان جدول مخصوص خودش را دارد.
     */
    public const START_EVENTS = 'workflow_start_events';
    public const END_EVENTS = 'workflow_end_events';
    public const USER_TASKS = 'workflow_user_tasks';
    public const SCRIPT_TASKS = 'workflow_script_tasks';
    public const EXCLUSIVE_GATEWAYS = 'workflow_exclusive_gateways';
    public const POOLS = 'workflow_pools';
    public const LANES = 'workflow_lanes';
    public const SEQUENCE_FLOWS = 'workflow_sequence_flows';

    /**
     * جداول اجرای فرایند (معادل wf_cases، wf_inbox و wf_variables پکیج behin).
     */
    public const CASES = 'workflow_cases';
    public const INBOX = 'workflow_inbox';
    public const VARIABLES = 'workflow_variables';

    /**
     * کلیدهای کانفیگ (workflow.storage.tables.*) و نام پیش‌فرض هر جدول.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'start_events' => self::START_EVENTS,
        'end_events' => self::END_EVENTS,
        'user_tasks' => self::USER_TASKS,
        'script_tasks' => self::SCRIPT_TASKS,
        'exclusive_gateways' => self::EXCLUSIVE_GATEWAYS,
        'pools' => self::POOLS,
        'lanes' => self::LANES,
        'sequence_flows' => self::SEQUENCE_FLOWS,
        'cases' => self::CASES,
        'inbox' => self::INBOX,
        'variables' => self::VARIABLES,
    ];

    public static function processes(): string
    {
        return self::resolve('workflow.storage.table', self::PROCESSES);
    }

    public static function startEvents(): string
    {
        return self::element('start_events');
    }

    public static function endEvents(): string
    {
        return self::element('end_events');
    }

    public static function userTasks(): string
    {
        return self::element('user_tasks');
    }

    public static function scriptTasks(): string
    {
        return self::element('script_tasks');
    }

    public static function exclusiveGateways(): string
    {
        return self::element('exclusive_gateways');
    }

    public static function pools(): string
    {
        return self::element('pools');
    }

    public static function lanes(): string
    {
        return self::element('lanes');
    }

    public static function sequenceFlows(): string
    {
        return self::element('sequence_flows');
    }

    public static function cases(): string
    {
        return self::element('cases');
    }

    public static function inbox(): string
    {
        return self::element('inbox');
    }

    public static function variables(): string
    {
        return self::element('variables');
    }

    /**
     * نام اتصال دیتابیس پکیج؛ null یعنی اتصال پیش‌فرض لاراول.
     */
    public static function connection(): ?string
    {
        if (! function_exists('config')) {
            return null;
        }

        $connection = config('workflow.storage.connection');

        return $connection ? (string) $connection : null;
    }

    /**
     * نام جدول یک نوع المان بر اساس کلید کانفیگ.
     */
    public static function element(string $key): string
    {
        $default = self::DEFAULTS[$key] ?? 'workflow_'.$key;

        return self::resolve('workflow.storage.tables.'.$key, $default);
    }

    private static function resolve(string $configKey, string $default): string
    {
        if (! function_exists('config')) {
            return $default;
        }

        $table = config($configKey);

        return is_string($table) && trim($table) !== '' ? trim($table) : $default;
    }
}