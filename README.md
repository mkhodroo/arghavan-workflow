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
- ذخیره‌سازی **کاملا در دیتابیس** بدون تولید هیچ فایلی (جدول خودکار، قابل غیرفعال‌سازی)
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

4. پنل را باز کنید: `/workflow`

> توجه: پکیج هیچ فایل (کانفیگ/ویو) را publish نمی‌کند و هیچ migrationای ندارد؛
> کانفیگ با `mergeConfigFrom` خوانده می‌شود، جدول `workflow_diagrams` خودکار ساخته
> می‌شود و ویوها روی `behin-layouts.app` سوار می‌شوند. برای تغییر تنظیمات، کلیدهای
> لازم را در `config/workflow.php` پروژه اضافه کنید (کلیدهای موجود آن فایل حفظ می‌ماند).

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
        'label' => 'استخر (Pool)',
        'icon'  => 'bpmn-icon-participant',
        'group' => 'collaboration',
    ],
    [
        'key'   => 'create.lane',
        'type'  => 'bpmn:Lane',
        'label' => 'لین (Lane)',
        'icon'  => 'bpmn-icon-lane',
        'group' => 'collaboration',
    ],
],
```

`group` یکی از مقادیر `event | activity | gateway | data-object | data-store | collaboration | artifact` است.

> نکته: «لین (Lane)» فقط داخل یک «استخر (Pool)» (یا لین دیگر) قابل ریختن است؛
> نمودار جدید پکیج از ابتدا یک استخر دارد.

## ذخیره‌سازی و اتصال به برنامه شما

**روش ۱ — دیتابیس (پیش‌فرض و تنها روش داخلی):** هر نمودار یک رکورد در جدول
`workflow_diagrams` (قابل تغییر در کانفیگ: `storage.table`) است؛ جدول در صورت
نبود **به‌صورت خودکار** ساخته می‌شود و هیچ فایل یا migrationای لازم نیست.

فایل‌های JSON قدیمی (`storage/arghavan-workflow`) در صورت وجود، خودکار وارد
دیتابیس می‌شوند و حذف می‌گردند (`storage.import_legacy`).

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
│   ├── Http/Controllers/
│   │   ├── DesignerController.php   فهرست / طراح / ذخیره / حذف
│   │   └── AssetController.php      سرو امن assetها (ضد traversal)
│   ├── Services/DiagramRepository.php   ذخیره دیتابیسی نمودارها (PDO؛ بدون فایل)
│   └── Support/
│       ├── Assets.php               ساخت آدرس CSS/JS (local یا cdn)
│       └── BpmnXml.php              اعتبارسنج سبک XML
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
