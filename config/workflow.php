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
    //   label : کلید انگلیسی عنوان ابزار؛ معادل فارسی را در فایل ترجمه
    //           پروژه (lang/{locale}/fields.php) بنویسید.
    //   icon  : کلاس آیکون bpmn-font
    //   group : گروه بصری پالت؛ event | activity | gateway | data-object |
    //           data-store | collaboration | artifact
    // نکته: «لین (Lane)» فقط داخل یک «استخر (Pool)» یا لین دیگر قابل ریختن است.
    'palette' => [
        [
            'key' => 'create.start-event',
            'type' => 'bpmn:StartEvent',
            'label' => 'Start Event',
            'icon' => 'bpmn-icon-start-event-none',
            'group' => 'event',
        ],
        [
            'key' => 'create.end-event',
            'type' => 'bpmn:EndEvent',
            'label' => 'End Event',
            'icon' => 'bpmn-icon-end-event-none',
            'group' => 'event',
        ],
        [
            'key' => 'create.user-task',
            'type' => 'bpmn:UserTask',
            'label' => 'User Task',
            'icon' => 'bpmn-icon-user-task',
            'group' => 'activity',
        ],
        [
            'key' => 'create.script-task',
            'type' => 'bpmn:ScriptTask',
            'label' => 'Script Task',
            'icon' => 'bpmn-icon-script-task',
            'group' => 'activity',
        ],
        [
            'key' => 'create.exclusive-gateway',
            'type' => 'bpmn:ExclusiveGateway',
            'label' => 'Exclusive Gateway',
            'icon' => 'bpmn-icon-gateway-xor',
            'group' => 'gateway',
        ],
        [
            'key' => 'create.pool',
            'type' => 'bpmn:Participant',
            'label' => 'Pool',
            'icon' => 'bpmn-icon-participant',
            'group' => 'collaboration',
        ],
        [
            'key' => 'create.lane',
            'type' => 'bpmn:Lane',
            'label' => 'Lane',
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
    // با هر تغییر در JS/CSS این مقدار را بالا ببرید تا کش مرورگر شکسته شود.
    'version' => '1.4.0',

    // ------------------------------------------------------------------
    // ذخیره‌سازی نمودارها (کاملا در دیتابیس با migration؛ هیچ فایلی ساخته نمی‌شود)
    // ------------------------------------------------------------------
    // جدول‌ها با اجرای `php artisan migrate` ساخته می‌شوند:
    //   workflow_processes        فرایندها (هر نمودار = یک فرایند)
    //   workflow_start_events     المان‌های شروع
    //   workflow_end_events       المان‌های پایان
    //   workflow_user_tasks       تسک‌های کاربری + تنظیمات آن‌ها
    //   workflow_script_tasks     اسکریپت تسک‌ها
    //   workflow_exclusive_gateways گیت‌وی‌های شرطی
    //   workflow_pools            استخرها (Pool)
    //   workflow_lanes            لین‌ها (Lane)
    //   workflow_sequence_flows   جریان‌های ترتیبی بین گره‌ها
    'storage' => [
        // برای غیرفعال کردن ذخیره‌سازی (فقط مشاهده/خروجی گرفتن) false کنید
        'enabled' => true,

        // اتصال دیتابیس (null = اتصال پیش‌فرض لاراول)
        'connection' => null,

        // نام جدول فرایندها
        'table' => 'workflow_processes',

        // نام جدول‌های المان‌ها (هر نوع المان، جدول مخصوص خودش)
        'tables' => [
            'start_events' => 'workflow_start_events',
            'end_events' => 'workflow_end_events',
            'user_tasks' => 'workflow_user_tasks',
            'script_tasks' => 'workflow_script_tasks',
            'exclusive_gateways' => 'workflow_exclusive_gateways',
            'pools' => 'workflow_pools',
            'lanes' => 'workflow_lanes',
            'sequence_flows' => 'workflow_sequence_flows',
            'cases' => 'workflow_cases',
            'inbox' => 'workflow_inbox',
            'variables' => 'workflow_variables',
        ],

        // حداکثر حجم مجاز XML نمودار (بایت)
        'max_xml_bytes' => 2 * 1024 * 1024,
    ],

    // ------------------------------------------------------------------
    // موتور اجرای فرایند (Routing)
    // ------------------------------------------------------------------
    // مدل کاربر پروژه؛ برای یافتن کاربرانِ دارای یک نقش (role_id) استفاده می‌شود
    'user_model' => \App\Models\User::class,

    // مقدار اولیه شماره پرونده (کشیدن از این عدد شروع می‌شود)
    'caseStartValue' => 1000,
];
