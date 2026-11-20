@extends($layout)

@php
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);

    // کیس مربوط به این کارتابل (برای خواندن مقادیر ذخیره‌شده‌ی متغیرها)
    $case = $inbox->case ?? null;

    // محتوای فرم: آرایه‌ی فیلدها مرتب بر اساس ترتیب نمایش
    $content = $form ? json_decode($form->content) : [];
    $content = is_array($content) ? $content : [];
    usort($content, fn ($a, $b) => (($a->order ?? 0) <=> ($b->order ?? 0)));
@endphp

@section('title', $form ? $form->name : $t('Task Form'))

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">

    <style>
        .aw-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .aw-card-header {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .aw-muted {
            color: #666;
            font-size: 0.9rem;
        }
        .aw-form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        .aw-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
        }
        .aw-btn.primary {
            background-color: #28a745;
            color: #fff;
            border-color: #28a745;
        }
        .aw-btn.primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .aw-alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .aw-alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
@endsection

@section('content')
    <div class="aw-card">
        <div class="aw-card-header">
            <h2>{{ $form->name ?? $t('Form') }}</h2>
            @if ($case)
                <span class="aw-muted">{{ $t('Case') }}: {{ $case->number ?? '-' }}</span>
                <span class="aw-muted">{{ $t('Case') }}: {{ $case->name ?? '-' }}</span>
            @endif
        </div>

        @if (!$form)
            <div class="aw-alert error">{{ $t('No form defined for this task.') }}</div>
        @else
            <form id="task-form">
                @csrf
                @if($case)
                <input type="hidden" name="caseId" id="caseId" value="{{ $case->id }}">

                @endif
                <div class="row col-sm-12 p-0 m-0 dynamic-form" id="{{ $form->id }}">
                    @foreach ($content as $field)
                        @php
                            $fieldName = $field->fieldName;
                            $fieldId = $field->fieldName;
                            $fieldClass = $field->class;
                            $required = $field->required;
                            $readOnly = $field->readOnly;

                            $fieldDetails = getFieldDetailsByName($field->fieldName);
                            $fieldAttributes = $fieldDetails ? json_decode($fieldDetails->attributes) : null;

                            $fieldValue = isset($case) ? $case->getVariable($field->fieldName) : null;
                            $fieldValueAlt = (isset($case) && isset($fieldDetails->type) && in_array($fieldDetails->type, ['datetime', 'date']))
                                ? $case->getVariable($field->fieldName . '_alt')
                                : null;
                        @endphp

                        @if ($fieldDetails)
                            <div class="{{ $fieldClass }}">
                                @include('SimpleWorkflowView::Core.Form.field-generator', [
                                    'fieldName' => $fieldName,
                                    'fieldId' => $fieldId,
                                    'fieldClass' => $fieldClass,
                                    'readOnly' => $readOnly,
                                    'required' => $required,
                                    'fieldValue' => $fieldValue,
                                    'fieldValueAlt' => $fieldValueAlt ?? '',
                                ])
                            </div>
                        @else
                            @php
                                if ($field->fieldName != $form->id) {
                                    $childForm = getFormInformation($field->fieldName);
                                }
                            @endphp
                            @isset($childForm)
                                @include('SimpleWorkflowView::Core.Form.preview', [
                                    'form' => $childForm,
                                    'mode' => $readOnly,
                                ])
                            @endisset
                        @endif
                    @endforeach
                </div>
                <div class="aw-form-actions">
                    <a class="aw-btn" href="{{ route('workflow2.inbox') }}">{{ $t('Back') }}</a>
                    <button type="button" class="aw-btn primary" id="nextBtn">{{ $t('Next Step') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection

@section('script')
    <script>
        document.getElementById('nextBtn')?.addEventListener('click', async () => {
            const btn = document.getElementById('nextBtn');
            const form = document.getElementById('task-form');

            // اعتبارسنجی فیلدهای الزامی (HTML5)
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }

            btn.disabled = true;
            btn.textContent = '{{ $t('Processing...') }}';

            try {
                const response = await fetch('{{ route('workflow2.api.inbox.next', ['inbox' => $inbox->id]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ $csrf_token }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.ok) {
                    location.href = '{{ route('workflow2.inbox') }}';
                } else {
                    btn.disabled = false;
                    btn.textContent = '{{ $t('Next Step') }}';
                    alert(data.message || '{{ $t('Failed to move to next step.') }}');
                }
            } catch (err) {
                btn.disabled = false;
                btn.textContent = '{{ $t('Next Step') }}';
                alert('{{ $t('Error connecting to server.') }}');
            }
        });
    </script>
@endsection

