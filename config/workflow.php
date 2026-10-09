<?php

return [

    // پیشوند مسیرهای پنل طراحی (مثلا /workflow و /workflow/designer)
    'route_prefix' => 'workflow',

    // میدلورهای مسیرها؛ behin-layouts به کاربر واردشده نیاز دارد.
    'middleware' => ['web', 'auth'],

    // ویوی layout اصلی صفحات پکیج؛ در پروژه اپتیک behin-layouts.app
    // (resources/views/behin-layouts). اگر موجود نباشد به
    // workflow::layout برگردانده می‌شود.
    'layout' => 'behin-layouts.app',

    // ------------------------------------------------------------------
    // ابزارهای پالت طراحی
    // ------------------------------------------------------------------
    // هر ورودی یک ابزار در پالت کنار بوم طراحی می‌سازد:
    //   key   : شناسه یکتای ورودی در پالت
    //   type  : نوع استاندارد BPMN 2.0 که ساخته می‌شود
    //   label : عنوان فارسی (روی آیکون نمایش داده می‌شود)
    //   icon  : کلاس آیکون bpmn-font
    //   group : گروه بصری پالت؛ event | activity | gateway | data-object |
    //           data-store | collaboration | artifact
    // نکته: «لین (Lane)» فقط داخل یک «استخر (Pool)» یا لین دیگر قابل ریختن است.
    'palette' => [
        [
            'key' => 'create.start-event',
            'type' => 'bpmn:StartEvent',
            'label' => 'المان شروع',
            'icon' => 'bpmn-icon-start-event-none',
            'group' => 'event',
        ],
        [
            'key' => 'create.end-event',
            'type' => 'bpmn:EndEvent',
            'label' => 'المان پایان',
            'icon' => 'bpmn-icon-end-event-none',
            'group' => 'event',
        ],
        [
            'key' => 'create.user-task',
            'type' => 'bpmn:UserTask',
            'label' => 'تسک کاربری',
            'icon' => 'bpmn-icon-user-task',
            'group' => 'activity',
        ],
        [
            'key' => 'create.script-task',
            'type' => 'bpmn:ScriptTask',
            'label' => 'اسکریپت تسک',
            'icon' => 'bpmn-icon-script-task',
            'group' => 'activity',
        ],
        [
            'key' => 'create.exclusive-gateway',
            'type' => 'bpmn:ExclusiveGateway',
            'label' => 'گیت‌وی شرطی',
            'icon' => 'bpmn-icon-gateway-xor',
            'group' => 'gateway',
        ],
        [
            'key' => 'create.pool',
            'type' => 'bpmn:Participant',
            'label' => 'استخر (Pool)',
            'icon' => 'bpmn-icon-participant',
            'group' => 'collaboration',
        ],
        [
            'key' => 'create.lane',
            'type' => 'bpmn:Lane',
            'label' => 'لین (Lane)',
            'icon' => 'bpmn-icon-lane',
            'group' => 'collaboration',
        ],
    ],

    // ------------------------------------------------------------------
    // منابع گرافیکی bpmn-js
    // ------------------------------------------------------------------
    'assets' => [
        // 'local' => فایل‌های همراه پکیج (پیش‌فرض؛ بدون نیاز به اینترنت و npm)
        // 'cdn'   => بارگذاری از CDN (فقط کتابخانه bpmn-js، فایل‌های خود پکیج همیشه محلی‌اند)
        'mode' => 'local',

        // آدرس پایه در حالت cdn
        'cdn_base' => 'https://unpkg.com/bpmn-js@18.31.0/dist',
    ],

    // نسخه‌ای که روی آدرس فایل‌های استاتیک می‌نشیند (برای کش مرورگر)
    'version' => '1.1.0',

    // ------------------------------------------------------------------
    // ذخیره‌سازی نمودارها (کاملا در دیتابیس؛ هیچ فایلی ساخته نمی‌شود)
    // ------------------------------------------------------------------
    'storage' => [
        // برای غیرفعال کردن ذخیره‌سازی (فقط مشاهده/خروجی گرفتن) false کنید
        'enabled' => true,

        // اتصال دیتابیس (null = اتصال پیش‌فرض لاراول)
        'connection' => null,

        // نام جدول نمودارها؛ در صورت نبود به‌صورت خودکار ساخته می‌شود
        // (بدون migration و بدون تولید فایل)
        'table' => 'workflow_diagrams',

        // ساخت خودکار جدول در صورت نبود
        'auto_create' => true,

        // ورود خودکار فایل‌های JSON قدیمی (ذخیره‌سازی نسخه‌های قبلی پکیج)
        // به دیتابیس؛ پس از ورود موفق فایل قدیمی حذف می‌شود.
        'import_legacy' => true,
        'legacy_path' => storage_path('arghavan-workflow'),

        // حداکثر حجم مجاز XML نمودار (بایت)
        'max_xml_bytes' => 2 * 1024 * 1024,
    ],
];
