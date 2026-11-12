# ارغوان ورکفلو (Arghavan Workflow)

پکیج لاراول برای **طراحی فرایند BPMN** در مرورگر، مبتنی بر کتابخانه [bpmn.io](https://bpmn.io) (`bpmn-js`).

با این پکیج یک محیط طراحی کامل با پالت سفارشی فارسی در اختیار دارید:

| ابزار پالت | نوع BPMN |
|---|---|
| المان شروع | `bpmn:StartEvent` |
| المان پایان | `bpmn:EndEvent` |
| تسک کاربری | `bpmn:UserTask` |
| اسکریپت تسک | `bpmn:ScriptTask` |
| گیت‌وی شرطی | `bpmn:ExclusiveGateway` |
| استخر (Pool) | `bpmn:Participant` |
| لین (Lane) | `bpmn:Lane` |

## امکانات

- محیط طراحی BPMN 2.0 با `bpmn-js@18` (ورژن‌های جدید مرورگر)
- پالت فقط با ابزارهای تعیین‌شده در کانفیگ (قابل افزایش بدون کد)
- خروجی و بارگذاری XML، دانلود فایل `.bpmn` و `.SVG`
- بازگردانی/ازسرگیری (Undo/Redo)، زوم و نمای کل
- ذخیره‌سازی **کاملا در دیتابیس** با migration: یک جدول برای فرایندها و یک جدول
  برای هر نوع المان/گره (قابل غیرفعال‌سازی)
- استفاده از **layout اصلی پروژه** (`behin-layouts.app`؛ در صورت نبود، layout داخلی پکیج)
- بدون نیاز به `npm` / `webpack`؛ فایل‌های bpmn-js همراه پکیج است (حالت CDN هم دارد)
- رابط فارسی و راست‌چین

## نصب

1. پکیج را در `packages/arghavan-workflow` پروژه قرار دهید.
2. در `composer.json` پروژه، مسیر را معرفی کنید:

```json
"repositories": [
    { "type": "path", "url": "packages/arghavan-workflow" }
],
"require": {
    "arghavan/workflow": "*"
}
```

3. نصب کنید (ServiceProvider خودکار ثبت می‌شود):

```bash
composer update arghavan/workflow
```

4. جدول‌ها را بسازید:

```bash
php artisan migrate
```

5. پنل را باز کنید: `/workflow`

> توجه: migrationها همراه پکیج‌اند و با `loadMigrationsFrom` خودکار اجرا می‌شوند؛
> نیازی به publish نیست. اگر می‌خواهید فایل‌ها در پروژه‌ی خودتان باشند:
> `php artisan vendor:publish --tag=workflow-migrations`
>
> کانفیگ با `mergeConfigFrom` خوانده می‌شود و ویوها روی `behin-layouts.app` سوار
> می‌شوند. برای تغییر تنظیمات، کلیدهای لازم را در `config/workflow.php` پروژه
> اضافه کنید (کلیدهای موجود آن فایل حفظ می‌ماند).

## مسیرها

| مسیر | عملکرد |
|---|---|
| `GET /workflow` | فهرست نمودارهای ذخیره‌شده |
| `GET /workflow/designer` | محیط طراحی (نمودار خالی) |
| `GET /workflow/designer/{doc}` | محیط طراحی با نمودار ذخیره‌شده |
| `POST /workflow/save` | ذخیره AJAX (JSON) |
| `DELETE /workflow/diagram/{doc}` | حذف نمودار |
| `GET /workflow/assets/{path}` | سرو assetهای پکیج (بدون publish) |

پیشوند و میدلورها در `config/workflow.php` قابل تغییرند.

## شخصی‌سازی پالت

ابزارهای پالت فقط از `config/workflow.php → palette` ساخته می‌شوند. برای افزودن
یک المان جدید (مثلا تسک سرویس) کافی است یک ورودی اضافه کنید:

```php
'palette' => [
    // ...
    [
        'key'   => 'create.pool',
        'type'  => 'bpmn:Participant',
        'label' => 'Pool',
        'icon'  => 'bpmn-icon-participant',
        'group' => 'collaboration',
    ],
    [
        'key'   => 'create.lane',
        'type'  => 'bpmn:Lane',
        'label' => 'Lane',
        'icon'  => 'bpmn-icon-lane',
        'group' => 'collaboration',
    ],
],
```

`group` یکی از مقادیر `event | activity | gateway | data-object | data-store | collaboration | artifact` است.

> نکته: «لین (Lane)» فقط داخل یک «استخر (Pool)» (یا لین دیگر) قابل ریختن است؛
> نمودار جدید پکیج از ابتدا یک استخر دارد.

## ترجمه‌ی متن‌ها (فایل `fields`)

همه‌ی متن‌های رابط کاربری با **کلید انگلیسی** نوشته شده‌اند و معادل فارسی آن‌ها
از فایل ترجمه‌ی پروژه خوانده می‌شود:

`lang/{locale}/fields.php`

مثلا برای فارسی:

```php
// lang/fa/fields.php
return [
    'Start Event' => 'المان شروع',
    'End Event' => 'المان پایان',
    'User Task' => 'تسک کاربری',
    'Script Task' => 'اسکریپت تسک',
    'Exclusive Gateway' => 'گیت‌وی شرطی',
    'Pool' => 'استخر (Pool)',
    'Lane' => 'لین (Lane)',

    'Script Task Configuration' => 'پیکربندی اسکریپت تسک',
    'User Task Configuration' => 'پیکربندی تسک کاربری',
    'Task Name' => 'نام تسک',
    'Script' => 'اسکریپت',
    '--- Select Script ---' => '--- انتخاب اسکریپت ---',
    'Save' => 'ذخیره',
    'Close' => 'بستن',
    'Cancel' => 'انصراف',
    'Delete' => 'حذف',

    // پیام‌های سرور و خطاها
    'Diagram not found.' => 'نمودار یافت نشد.',
    'Invalid diagram ID.' => 'شناسه‌ی نمودار نامعتبر است.',
    'Invalid element ID.' => 'شناسه‌ی المان نامعتبر است.',
    'Saving failed.' => 'ذخیره‌سازی انجام نشد.',
    'Task settings saved.' => 'تنظیمات تسک ذخیره شد.',
    'UserTask with ID :id was not found.' => 'تسک کاربری با شناسه :id یافت نشد.',
    'ScriptTask with ID :id was not found.' => 'اسکریپت تسک با شناسه :id یافت نشد.',

    // انواع ارجاع
    'Normal' => 'عادی',
    'Dynamic' => 'پویا',
    'Public' => 'عمومی',
];
```

> در متن‌هایی مثل `Diagram loaded with :count warning(s).` یا
> `UserTask with ID :id was not found.` بخش‌های `:count` و `:id` به‌صورت
> خودکار با مقدار واقعی جایگزین می‌شوند؛ آن‌ها را در ترجمه هم نگه دارید.

برای دیدن کلیدهای استفاده‌شده در ویوها و جاوااسکریپت:

```bash
grep -rhoE "(\\\$t|\\bt)\\('[^']+'" resources/views resources/js
grep -rhoE "Lang::field\\('[^']+'" src
```

اگر کلیدی در فایل `fields` نباشد، همان متن انگلیسی نمایش داده می‌شود؛ بنابراین
می‌توانید به‌تدریج ترجمه‌ها را کامل کنید.

> متن‌های سمت سرور (پیام‌های خطای ذخیره‌سازی، اعتبارسنجی و ...) و متن‌های
> جاوااسکریپت (نوار وضعیت، مودال‌ها و پالت) همگی از همین فایل خوانده می‌شوند.

## ذخیره‌سازی و اتصال به برنامه شما

**روش ۱ — دیتابیس (پیش‌فرض و تنها روش داخلی):** با migration و Eloquent.

هر نمودار یک **فرایند** در جدول `workflow_processes` است و المان‌های آن (گره‌ها)
هر کدام در جدول مخصوص نوع خودشان ذخیره می‌شوند:

| جدول | محتوا |
|---|---|
| `workflow_processes` | فرایندها (هر نمودار = یک رکورد؛ XML کامل هم اینجاست) |
| `workflow_start_events` | المان‌های شروع (`bpmn:StartEvent`) |
| `workflow_end_events` | المان‌های پایان (`bpmn:EndEvent`) |
| `workflow_user_tasks` | تسک‌های کاربری + تنظیمات (فرم، اکتور، نقش، مدت، رنگ و ...) |
| `workflow_script_tasks` | اسکریپت تسک‌ها (`bpmn:ScriptTask`) |
| `workflow_exclusive_gateways` | گیت‌وی‌های شرطی (`bpmn:ExclusiveGateway`) |
| `workflow_pools` | استخرها (`bpmn:Participant`) |
| `workflow_lanes` | لین‌ها (`bpmn:Lane`) |
| `workflow_sequence_flows` | جریان‌های ترتیبی بین گره‌ها (`bpmn:SequenceFlow`) |

نام جدول‌ها در `config/workflow.php` قابل تغییرند (`storage.table` برای فرایندها و
`storage.tables.*` برای المان‌ها).

هنگام ذخیره‌ی نمودار، XML تجزیه می‌شود و جداول المان‌ها همگام می‌شوند: گره‌های
جدید اضافه، گره‌های موجود به‌روزرسانی و گره‌های حذف‌شده پاک می‌شوند. تنظیمات
دستی تسک‌های کاربری (فرم/اکتور/رنگ و ...) با ذخیره‌ی نمودار پاک نمی‌شود.

> اگر پروژه‌ی شما از نسخه‌ی قبلی پکیج جدول `workflow_diagrams` دارد، migration
> `..._migrate_legacy_workflow_diagrams_table` داده‌ها را به `workflow_processes`
> منتقل و جدول قدیمی را حذف می‌کند.

**دسترسی از کد:**

```php
use ArghavanWorkflow\Models\WorkflowProcess;

$process = WorkflowProcess::find($id);       // فرایند + XML
$process->userTasks;                         // تسک‌های کاربری
$process->startEvents;                       // المان‌های شروع
$process->sequenceFlows;                     // جریان‌های ترتیبی

$task = $process->userTasks()->where('element_id', 'UserTask_1')->first();
$task->form_id; $task->actor; $task->role_id; $task->color;
```

**روش ۲ — هوک سفارشی:** اگر ذخیره را خودتان می‌خواهید (مثلا در جای دیگر)،
قبل از لود `designer.js` تابع زیر را تعریف کنید:

```html
<script>
window.ARGHAVAN_ON_SAVE = function (payload) {
    // payload: { id, name, xml }
    return fetch('/api/my-diagrams', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    }).then(function (r) { return r.ok; });
};
</script>
```

**روش ۳ — API جاوااسکریپت:**

```js
window.ARGHAVAN_WORKFLOW.getXml();          // دریافت XML فعلی (Promise)
window.ARGHAVAN_WORKFLOW.setXml(xml);       // بارگذاری XML (Promise<boolean>)
window.ARGHAVAN_WORKFLOW.save();            // اجرای همان ذخیره دکمه «ذخیره»
window.ARGHAVAN_WORKFLOW.modeler;           // نمونه bpmn-js Modeler
```

## assetها (bpmn-js)

- حالت پیش‌فرض `local` است: فایل‌های bpmn-js داخل `resources/assets/bpmn-js`
  هستند و از مسیر `/workflow/assets/...` سرو می‌شوند؛ **اینترنت و npm لازم نیست.**
- برای حالت CDN در کانفیگ:

```php
'assets' => ['mode' => 'cdn'],
```

## نکات امنیتی

- مسیرهای پیش‌فرض `web` و `auth` دارند؛ در صورت نیاز میدلور نقش
  (مثل `can:manage-workflows`) به `workflow.middleware` اضافه کنید.
- حجم XML با `storage.max_xml_bytes` (پیش‌فرض ۲ مگابایت) محدود می‌شود.
- فقط XMLِ دارای المان `definitions` که صحت آن با `simplexml` بررسی شود ذخیره می‌شود.
- **این پکیج فعلا فقط محیط طراحی است؛** اجرای اسکریپت‌ها/فرایندها بر عهده موتور
  اجرای برنامه شماست و هیچ کدی از سمت این پکیج اجرا نمی‌شود.

## محدودیت‌های فعلی

- پنل خواص (Properties Panel) ندارد؛ نام المان‌ها با **دابل‌کلیک** روی آن‌ها
  قابل ویرایش است.
- منوی زمینه (Context Pad) و منوی «تغییر نوع المان» رفتار پیش‌فرض bpmn-js را
  دارند و انواع بیشتری از المان‌ها را نشان می‌دهند؛ برای محدود کردن آن‌ها می‌توان
  provider سفارشی افزود (نقطه توسعه: `resources/js/designer.js`).

## تست

```bash
php tests/run.php
```

## ساختار پکیج

```
arghavan-workflow/
├── composer.json
├── config/workflow.php              پیشوندها، پالت، assetها، ذخیره‌سازی
├── routes/web.php
├── src/
│   ├── WorkflowServiceProvider.php
│   ├── Migrations/                  ساخت جدول فرایندها + جدول هر نوع المان
│   ├── Models/
│   │   ├── WorkflowProcess.php      مدل فرایند (نمودار)
│   │   └── Elements/                مدل هر نوع المان (StartEvent، UserTask، ...)
│   ├── Http/Controllers/
│   │   ├── DesignerController.php   فهرست / طراح / ذخیره / حذف
│   │   ├── DesignerTaskConfigController.php  تنظیمات تسک کاربری
│   │   └── AssetController.php      سرو امن assetها (ضد traversal)
│   ├── Services/
│   │   ├── DiagramRepository.php    ذخیره/خواندن فرایندها با Eloquent
│   │   ├── DiagramSynchronizer.php  همگام‌سازی جداول المان‌ها با XML
│   │   └── DiagramConfigStore.php   تنظیمات تسک کاربری در جدول
│   └── Support/
│       ├── Assets.php               ساخت آدرس CSS/JS (local یا cdn)
│       ├── BpmnXml.php              اعتبارسنج سبک XML
│       ├── BpmnXmlParser.php        تجزیه XML به ردیف‌های جداول
│       └── TableNames.php           نام جدول‌ها/اتصال از کانفیگ
├── resources/
│   ├── views/                       index, designer (روی behin-layouts.app + layout داخلی به‌عنوان fallback)
│   ├── css/workflow.css
│   ├── js/designer.js               پالت سفارشی + ابزارها + ذخیره
│   └── assets/bpmn-js/              باندل و CSSهای bpmn-js (MIT)
└── tests/run.php
```

## لایسنس

MIT. کتابخانه bpmn-js نیز MIT است (متن لایسنس در
`resources/assets/bpmn-js/LICENSE-bpmn-js.txt`).
