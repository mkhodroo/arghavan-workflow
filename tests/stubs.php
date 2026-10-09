<?php

/**
 * استاب‌های حداقلی برای اجرای مستقل تست‌ها.
 *
 * فقط وقتی framework لاراول در دسترس نباشد تعریف می‌شوند تا تست‌ها با
 * `php tests/run.php` و بدون نصب وابستگی هم اجرا شوند.
 */

namespace Illuminate\Routing {
    if (! class_exists('Illuminate\Routing\Controller')) {
        class Controller
        {
        }
    }
}
