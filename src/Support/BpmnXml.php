<?php

namespace ArghavanWorkflow\Support;

class BpmnXml
{
    /**
     * بررسی سبک صحت XML نمودار پیش از ذخیره:
     * باید یک سند BPMN (المان definitions) و XML معتبر باشد.
     *
     * اعتبارسنجی کامل BPMN 2.0 هنگام بارگذاری در محیط طراحی
     * توسط خود bpmn-js انجام می‌شود.
     */
    public static function isValid(?string $xml): bool
    {
        if (! is_string($xml) || trim($xml) === '') {
            return false;
        }

        // المان ریشه باید definitions باشد (با یا بدون پیشوند نام‌فضا)
        if (! preg_match('/<(?:[\w.\-]+:)?definitions\b/i', $xml)) {
            return false;
        }

        if (! function_exists('simplexml_load_string')) {
            // در نبود افزونه simplexml به بررسی ساختاری بالا بسنده می‌کنیم
            return true;
        }

        $previous = libxml_use_internal_errors(true);
        $valid = simplexml_load_string($xml) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $valid;
    }
}
