@extends($layout)

@php
    // متن‌های رابط کاربری انگلیسی‌اند و معادل فارسی آن‌ها در
    // فایل ترجمه‌ی پروژه (lang/{locale}/fields.php) نوشته می‌شود.
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);
@endphp

@section('title', ($diagram['name'] ?? null) ? $t('Edit').': '.($diagram['name']) : $t('New Diagram'))

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
            <input type="text" id="aw-name" class="aw-input" placeholder="{{ $t('Diagram Title') }}"
                   value="{{ $diagram['name'] ?? '' }}" {{ $storageEnabled ? '' : 'disabled' }}>

            @if ($storageEnabled)
                <button type="button" id="aw-save" class="aw-btn">💾 {{ $t('Save') }}</button>
            @endif

            <button type="button" id="aw-download-xml" class="aw-btn secondary">⬇ {{ $t('Download XML') }}</button>
            <button type="button" id="aw-download-svg" class="aw-btn secondary">⬇ {{ $t('Download SVG') }}</button>
            <button type="button" id="aw-import-file" class="aw-btn secondary">📂 {{ $t('Upload File') }}</button>
            <button type="button" id="aw-edit-xml" class="aw-btn secondary">📝 {{ $t('Edit XML') }}</button>
            <input type="file" id="aw-file-input" accept=".bpmn,.xml,text/xml,application/xml" hidden>
        </div>

        <div class="aw-toolbar-section">
            <button type="button" id="aw-undo" class="aw-btn ghost" title="{{ $t('Undo (Ctrl+Z)') }}">↩ {{ $t('Undo') }}</button>
            <button type="button" id="aw-redo" class="aw-btn ghost" title="{{ $t('Redo (Ctrl+Y)') }}">↪ {{ $t('Redo') }}</button>

            <span class="aw-sep"></span>

            <button type="button" id="aw-zoom-out" class="aw-btn ghost" title="{{ $t('Zoom Out') }}">−</button>
            <button type="button" id="aw-zoom-reset" class="aw-btn ghost" title="{{ $t('Reset Zoom') }}">100%</button>
            <button type="button" id="aw-zoom-in" class="aw-btn ghost" title="{{ $t('Zoom In') }}">＋</button>
            <button type="button" id="aw-zoom-fit" class="aw-btn ghost" title="{{ $t('Fit View') }}">{{ $t('Fit View') }}</button>

            <span class="aw-sep"></span>

            <button type="button" id="aw-new" class="aw-btn ghost">🔄 {{ $t('New Diagram') }}</button>
        </div>
    </div>

    {{-- بوم طراحی --}}
    <div id="aw-canvas" class="aw-canvas"></div>

    {{-- نوار وضعیت --}}
    <div id="aw-status" class="aw-status" role="status" aria-live="polite"></div>
</div>

    {{-- مودال تنظیمات تسک کاربری (برگرفته از تنظیمات تسک فرم در behin-simple-workflow) --}}
    <div id="aw-taskconfig-modal" class="aw-modal" hidden>
        <div class="aw-modal-box aw-taskconfig-box">
            <div class="aw-modal-header">
                <strong id="aw-tc-title">{{ $t('User Task Configuration') }}</strong>
                <button type="button" class="aw-btn small ghost" data-aw-tc-close>×</button>
            </div>

            <div class="aw-tc-body">
                <div class="aw-tc-tabs" role="tablist">
                    <button type="button" class="aw-tc-tab is-active" data-aw-tc-tab="general">{{ $t('Basic Information') }}</button>
                    <button type="button" class="aw-tc-tab" data-aw-tc-tab="workflow">{{ $t('Workflow') }}</button>
                    <button type="button" class="aw-tc-tab" data-aw-tc-tab="appearance">{{ $t('Appearance and Schedule') }}</button>
                </div>

                <div class="aw-tc-panels">
                    <div class="aw-tc-panel" data-aw-tc-panel="general">
                        <div class="aw-form-grid">
                            <div class="aw-form-group">
                                <label for="aw-tc-name">{{ $t('Task Name') }}</label>
                                <input type="text" id="aw-tc-name" class="aw-input">
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-order">{{ $t('Order') }}</label>
                                <input type="text" id="aw-tc-order" class="aw-input" dir="ltr">
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-is-preview">{{ $t('Preview Mode') }}</label>
                                <select id="aw-tc-is-preview" class="aw-input">
                                    <option value="0">{{ $t('No') }}</option>
                                    <option value="1">{{ $t('Yes') }}</option>
                                </select>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-show-cancel">{{ $t('Show Cancel Button') }}</label>
                                <select id="aw-tc-show-cancel" class="aw-input">
                                    <option value="0">{{ $t('No') }}</option>
                                    <option value="1">{{ $t('Yes') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="aw-tc-panel" data-aw-tc-panel="workflow" hidden>
                        <div class="aw-form-grid">
                            <div class="aw-form-group aw-form-group-full">
                                <label for="aw-tc-form">{{ $t('Form (Executive File)') }}</label>
                                <select id="aw-tc-form" class="aw-input">
                                    <option value="">{{ $t('--- Select Form ---') }}</option>
                                </select>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-assignment">{{ $t('Assignment Type') }}</label>
                                <select id="aw-tc-assignment" class="aw-input">
                                    <option value="">{{ $t('--- Select Assignment Type ---') }}</option>
                                </select>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-actor">{{ $t('Actor (User)') }}</label>
                                <select id="aw-tc-actor" class="aw-input">
                                    <option value="">{{ $t('--- Select Actor ---') }}</option>
                                </select>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-role">{{ $t('Role') }}</label>
                                <select id="aw-tc-role" class="aw-input">
                                    <option value="">{{ $t('--- Select Role ---') }}</option>
                                </select>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-case-name">{{ $t('Case Name') }}</label>
                                <input type="text" id="aw-tc-case-name" class="aw-input" dir="ltr">
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-duration">{{ $t('Duration (Minutes)') }}</label>
                                <input type="text" id="aw-tc-duration" class="aw-input" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div class="aw-tc-panel" data-aw-tc-panel="appearance" hidden>
                        <div class="aw-form-grid">
                            <div class="aw-form-group">
                                <label for="aw-tc-color">{{ $t('Text Color') }}</label>
                                <div class="aw-color-row">
                                    <input type="text" id="aw-tc-color" class="aw-input" dir="ltr">
                                    <input type="color" id="aw-tc-color-picker">
                                </div>
                            </div>
                            <div class="aw-form-group">
                                <label for="aw-tc-background">{{ $t('Background Color') }}</label>
                                <div class="aw-color-row">
                                    <input type="text" id="aw-tc-background" class="aw-input" dir="ltr">
                                    <input type="color" id="aw-tc-background-picker">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="aw-muted aw-tc-hint">{!! str_replace(':table', '<code>workflow_user_tasks</code>', e($t('User task settings are stored in the :table table.'))) !!}</div>
            </div>

            <div class="aw-modal-footer">
                <button type="button" id="aw-tc-save" class="aw-btn">{{ $t('Save') }}</button>
                <button type="button" class="aw-btn secondary" data-aw-tc-close>{{ $t('Close') }}</button>
            </div>
        </div>
    </div>

    {{-- مودال تنظیمات اسکریپت تسک (بر اساس behin-simple-workflow: انتخاب اسکریپت از لیست wf_scripts) --}}
    <div id="aw-scriptconfig-modal" class="aw-modal" hidden>
        <div class="aw-modal-box aw-scriptconfig-box">
            <div class="aw-modal-header">
                <strong id="aw-sc-title">{{ $t('Script Task Configuration') }}</strong>
                <button type="button" class="aw-btn small ghost" data-aw-sc-close>×</button>
            </div>

            <div class="aw-tc-body">
                <div class="aw-form-grid">
                    <div class="aw-form-group">
                        <label for="aw-sc-name">{{ $t('Task Name') }}</label>
                        <input type="text" id="aw-sc-name" class="aw-input" dir="ltr">
                    </div>
                    <div class="aw-form-group full">
                        <label for="aw-sc-script">{{ $t('Script') }}</label>
                        <select id="aw-sc-script" class="aw-select"></select>
                        <small class="aw-muted">{{ $t('Scripts are loaded from the wf_scripts table (behin-simple-workflow).') }}</small>
                    </div>
                </div>
                <div class="aw-muted aw-sc-hint">{!! str_replace(':table', '<code>workflow_script_tasks</code>', e($t('Script task settings are stored in the :table table (script_id references wf_scripts).'))) !!}</div>
            </div>

            <div class="aw-modal-footer">
                <button type="button" id="aw-sc-save" class="aw-btn">{{ $t('Save') }}</button>
                <button type="button" class="aw-btn secondary" data-aw-sc-close>{{ $t('Close') }}</button>
            </div>
        </div>
    </div>

    {{-- مودال تنظیمات جریان ترتیبی (انتخاب شرط بررسی‌شونده روی جریان‌های گیت‌وی) --}}
    <div id="aw-flowconfig-modal" class="aw-modal" hidden>
        <div class="aw-modal-box aw-scriptconfig-box">
            <div class="aw-modal-header">
                <strong id="aw-fl-title">{{ $t('Sequence Flow Configuration') }}</strong>
                <button type="button" class="aw-btn small ghost" data-aw-fl-close>×</button>
            </div>

            <div class="aw-tc-body">
                <div class="aw-form-grid">
                    <div class="aw-form-group">
                        <label for="aw-fl-name">{{ $t('Flow Name') }}</label>
                        <input type="text" id="aw-fl-name" class="aw-input" dir="ltr">
                    </div>
                    <div class="aw-form-group full">
                        <label for="aw-fl-condition">{{ $t('Condition') }}</label>
                        <select id="aw-fl-condition" class="aw-select"></select>
                        <small class="aw-muted">{{ $t('Conditions are loaded from the wf_conditions table (behin-simple-workflow); used on flows leaving an exclusive gateway.') }}</small>
                    </div>
                    <div class="aw-form-group full">
                        <label for="aw-fl-script">{{ $t('Script') }}</label>
                        <select id="aw-fl-script" class="aw-select"></select>
                        <small class="aw-muted">{{ $t('Scripts are loaded from the wf_scripts table (behin-simple-workflow); on gateway flows, true takes this path, false skips it; on normal flows the script runs as a side effect.') }}</small>
                    </div>
                </div>
                <div class="aw-muted aw-sc-hint">{!! str_replace(':table', '<code>workflow_sequence_flows</code>', e($t('Sequence flow settings are stored in the :table table (condition_id and script_id reference wf_conditions and wf_scripts).'))) !!}</div>
            </div>

            <div class="aw-modal-footer">
                <button type="button" id="aw-fl-save" class="aw-btn">{{ $t('Save') }}</button>
                <button type="button" class="aw-btn secondary" data-aw-fl-close>{{ $t('Close') }}</button>
            </div>
        </div>
    </div>

    {{-- مودال ویرایش XML --}}
<div id="aw-xml-modal" class="aw-modal" hidden>
    <div class="aw-modal-box">
        <div class="aw-modal-header">
            <strong>{{ $t('Edit Diagram XML') }}</strong>
            <button type="button" class="aw-btn small ghost" data-aw-close-modal>×</button>
        </div>

        <textarea id="aw-xml-text" class="aw-xml-textarea" spellcheck="false" dir="ltr"></textarea>

        <div class="aw-modal-footer">
            <button type="button" id="aw-xml-apply" class="aw-btn">{{ $t('Apply Changes') }}</button>
            <button type="button" class="aw-btn secondary" data-aw-close-modal>{{ $t('Cancel') }}</button>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    window.AW_CONFIG = {
        palette: @json(config('workflow2.palette', [])),
        initialXml: @json($diagram['xml'] ?? null),
        docId: @json($diagram['id'] ?? null),
        storageEnabled: @json((bool) $storageEnabled),
        saveUrl: @json(route('workflow2.save')),
        newUrl: @json(route('workflow2.designer')),
        indexUrl: @json(route('workflow2.index')),
        csrf: @json(csrf_token()),
        configListUrl: @json(route('workflow2.designer.element-config.list')),
        configGetUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/element-config/{doc}/{element}'),
        configSaveUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/element-config/{doc}/{element}'),
        scriptConfigListUrl: @json(route('workflow2.designer.script-config.list')),
        scriptConfigGetUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/script-config/{doc}/{element}'),
        scriptConfigSaveUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/script-config/{doc}/{element}'),
        flowConfigListUrl: @json(route('workflow2.designer.flow-config.list')),
        flowConfigGetUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/flow-config/{doc}/{element}'),
        flowConfigSaveUrl: @json('/'.config('workflow2.route_prefix', 'workflow').'/designer/flow-config/{doc}/{element}'),
        i18n: @json((object) \ArghavanWorkflow\Support\Lang::fields())
    };
</script>

@foreach (\ArghavanWorkflow\Support\Assets::scripts() as $src)
    <script src="{{ $src }}"></script>
@endforeach
@endsection
