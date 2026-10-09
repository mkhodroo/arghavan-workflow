@extends($layout)

@section('title', ($diagram['name'] ?? null) ? 'ویرایش: '.($diagram['name']) : 'نمودار جدید')

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">
    @foreach (\ArghavanWorkflow\Support\Assets::styles() as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
@endsection

@section('content')
<div class="aw-designer">

    {{-- نوار ابزار --}}
    <div class="aw-toolbar">
        <div class="aw-toolbar-section">
            <input type="text" id="aw-name" class="aw-input" placeholder="عنوان نمودار"
                   value="{{ $diagram['name'] ?? '' }}" {{ $storageEnabled ? '' : 'disabled' }}>

            @if ($storageEnabled)
                <button type="button" id="aw-save" class="aw-btn">💾 ذخیره</button>
            @endif

            <button type="button" id="aw-download-xml" class="aw-btn secondary">⬇ دانلود XML</button>
            <button type="button" id="aw-download-svg" class="aw-btn secondary">⬇ SVG</button>
            <button type="button" id="aw-import-file" class="aw-btn secondary">📂 بارگذاری فایل</button>
            <button type="button" id="aw-edit-xml" class="aw-btn secondary">📝 ویرایش XML</button>
            <input type="file" id="aw-file-input" accept=".bpmn,.xml,text/xml,application/xml" hidden>
        </div>

        <div class="aw-toolbar-section">
            <button type="button" id="aw-undo" class="aw-btn ghost" title="بازگردانی (Ctrl+Z)">↩ بازگردانی</button>
            <button type="button" id="aw-redo" class="aw-btn ghost" title="ازسرگیری (Ctrl+Y)">↪ ازسرگیری</button>

            <span class="aw-sep"></span>

            <button type="button" id="aw-zoom-out" class="aw-btn ghost" title="کوچک‌نمایی">−</button>
            <button type="button" id="aw-zoom-reset" class="aw-btn ghost" title="اندازه اصلی">۱۰۰٪</button>
            <button type="button" id="aw-zoom-in" class="aw-btn ghost" title="بزرگ‌نمایی">＋</button>
            <button type="button" id="aw-zoom-fit" class="aw-btn ghost" title="نمای کل نمودار">نمای کل</button>

            <span class="aw-sep"></span>

            <button type="button" id="aw-new" class="aw-btn ghost">🔄 نمودار جدید</button>
        </div>
    </div>

    {{-- بوم طراحی --}}
    <div id="aw-canvas" class="aw-canvas"></div>

    {{-- نوار وضعیت --}}
    <div id="aw-status" class="aw-status" role="status" aria-live="polite"></div>
</div>

{{-- مودال ویرایش XML --}}
<div id="aw-xml-modal" class="aw-modal" hidden>
    <div class="aw-modal-box">
        <div class="aw-modal-header">
            <strong>ویرایش XML نمودار</strong>
            <button type="button" class="aw-btn small ghost" data-aw-close-modal>×</button>
        </div>

        <textarea id="aw-xml-text" class="aw-xml-textarea" spellcheck="false" dir="ltr"></textarea>

        <div class="aw-modal-footer">
            <button type="button" id="aw-xml-apply" class="aw-btn">اعمال تغییرات</button>
            <button type="button" class="aw-btn secondary" data-aw-close-modal>انصراف</button>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    window.AW_CONFIG = {
        palette: @json(config('workflow.palette', [])),
        initialXml: @json($diagram['xml'] ?? null),
        docId: @json($diagram['id'] ?? null),
        storageEnabled: @json((bool) $storageEnabled),
        saveUrl: @json(route('workflow.save')),
        newUrl: @json(route('workflow.designer')),
        indexUrl: @json(route('workflow.index')),
        csrf: @json(csrf_token())
    };
</script>

@foreach (\ArghavanWorkflow\Support\Assets::scripts() as $src)
    <script src="{{ $src }}"></script>
@endforeach
@endsection
