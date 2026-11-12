<?php

namespace ArghavanWorkflow\Support;

/**
 * ترجمه‌ی متن‌های رابط کاربری طراح.
 *
 * کلیدها انگلیسی‌اند و معادل فارسی در فایل ترجمه‌ی پروژه
 * (`lang/{locale}/fields.php`) نوشته می‌شود. اگر کلیدی ترجمه نشده باشد،
 * همان متن انگلیسی برگردانده می‌شود تا معلوم باشد چه کلیدی باید ترجمه شود.
 */
class Lang
{
    /**
     * ترجمه‌ی یک کلید از گروه `fields`.
     *
     * @param  array<string, string|int>  $replacements  جایگزین‌های :name
     */
    public static function field(string $key, array $replacements = []): string
    {
        if (! function_exists('trans')) {
            $translation = $key;
        } else {
            $full = 'fields.'.$key;
            $value = trans($full);
            $translation = is_string($value) && $value !== $full ? $value : $key;
        }

        foreach ($replacements as $name => $replacement) {
            $translation = str_replace(':'.$name, (string) $replacement, $translation);
        }

        return $translation;
    }

    /**
     * همه‌ی رشته‌های ترجمه‌شده‌ی گروه `fields` برای استفاده در جاوااسکریپت.
     *
     * @return array<string, string>
     */
    public static function fields(): array
    {
        if (! function_exists('trans')) {
            return [];
        }

        $group = trans('fields');

        if (! is_array($group)) {
            return [];
        }

        return array_filter(
            $group,
            static fn ($value, $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
