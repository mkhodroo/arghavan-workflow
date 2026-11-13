<?php

namespace ArghavanWorkflow\Support;

/**
 * ساخت آدرس فایل‌های گرافیکی (bpmn-js و فایل‌های خود پکیج).
 *
 * فایل‌های bpmn-js در حالت local از مسیر assets پکیج و در حالت cdn از
 * آدرس پایه کانفیگ‌شده لود می‌شوند؛ فایل‌های خود پکیج همیشه محلی‌اند.
 */
class Assets
{
    /**
     * آدرس CSSهای موردنیاز صفحه طراحی (ترتیب مهم است).
     *
     * @return array<int, string>
     */
    public static function styles(): array
    {
        return self::bpmn([
            'assets/diagram-js.css',
            'assets/bpmn-js.css',
            'assets/bpmn-font/css/bpmn-embedded.css',
        ]);
    }

    /**
     * آدرس اسکریپت‌ها؛ باندل bpmn-js باید پیش از designer.js لود شود.
     *
     * @return array<int, string>
     */
    public static function scripts(): array
    {
        return array_merge(
            self::bpmn(['bpmn-modeler.production.min.js']),
            [
                self::local('js/i18n.js'),
                self::local('js/taskconfig.js'),
                self::local('js/scriptconfig.js'),
                self::local('js/flowconfig.js'),
                self::local('js/designer.js'),
            ]
        );
    }

    /**
     * CSS مشترک صفحات پکیج.
     */
    public static function stylesheet(): string
    {
        return self::local('css/workflow.css');
    }

    /**
     * آدرس یک فایل خود پکیج (همیشه از مسیر محلی).
     */
    public static function local(string $path): string
    {
        $version = (string) config('workflow.version', '1.0.0');

        return route('workflow.assets', ['path' => $path]).'?v='.rawurlencode($version);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private static function bpmn(array $paths): array
    {
        if (config('workflow.assets.mode', 'local') === 'cdn') {
            $base = rtrim((string) config('workflow.assets.cdn_base', ''), '/');

            return array_map(fn (string $path) => $base.'/'.$path, $paths);
        }

        return array_map(fn (string $path) => self::local('bpmn-js/'.$path), $paths);
    }
}
