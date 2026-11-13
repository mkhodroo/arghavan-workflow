@extends('behin-layouts.app')

@php
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);
@endphp

@section('title', $t('Start Process'))

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">

    <style>
        .aw-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .aw-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .aw-modal-content h3 {
            margin-top: 0;
            color: #333;
        }
        .aw-form-group {
            margin-bottom: 15px;
        }
        .aw-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
        }
        .aw-form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .aw-form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .aw-btn.primary {
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .aw-btn.primary:disabled {
            background-color: #6c757d;
        }
        .aw-btn.small {
            padding: 6px 12px;
            font-size: 14px;
        }
    </style>
@endsection

@section('content')
<div class="aw-card">
    <div class="aw-card-header">
        <h2>{{ $t('Start Process') }}</h2>
        <a class="aw-btn" href="{{ route('workflow2.index') }}">{{ $t('Back to Diagrams') }}</a>
    </div>

    <div id="loading" class="aw-muted">{{ $t('Loading processes...') }}</div>
    <div id="error" class="aw-alert error" style="display: none;"></div>

    <div id="processes-container" style="display: none;">
        <table class="aw-table">
            <thead>
                <tr>
                    <th>{{ $t('Title') }}</th>
                    <th>{{ $t('Created') }}</th>
                    <th>{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody id="processes-table-body">
            </tbody>
        </table>
    </div>

    <div id="empty-state" style="display: none;">
        <p class="aw-muted">{{ $t('No process available to start.') }}</p>
    </div>

    <div id="result" class="aw-alert success" style="display: none;"></div>
</div>

<div id="start-modal" class="aw-modal">
    <div class="aw-modal-content">
        <h3 id="modal-title">{{ $t('Start Process') }}</h3>
        <form id="start-form" onsubmit="return false;">
            <div class="aw-form-group">
                <label>{{ $t('Case Name') }}</label>
                <input type="text" name="case_name" id="case_name"
                       placeholder="{{ $t('Enter case name (optional)') }}">
            </div>
            <div class="aw-form-actions">
                <button type="button" class="aw-btn" onclick="closeModal()">{{ $t('Cancel') }}</button>
                <button type="button" class="aw-btn primary" id="confirm-start-btn">{{ $t('Confirm Start') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
    let currentProcessId = null;
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function loadProcesses() {
        const loading = document.getElementById('loading');
        const container = document.getElementById('processes-container');
        const emptyState = document.getElementById('empty-state');
        const tbody = document.getElementById('processes-table-body');

        try {
            const response = await fetch('{{ route('workflow2.api.processes.index') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();

            loading.style.display = 'none';

            if (data.ok && data.processes) {
                if (data.processes.length === 0) {
                    emptyState.style.display = 'block';
                    return;
                }

                tbody.innerHTML = '';
                data.processes.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(p.name)}</td>
                        <td>${p.created_at || '-'}</td>
                        <td class="aw-actions">
                            <button class="aw-btn small primary"
                                    onclick="startProcess('${p.id}', '${escapeHtml(p.name)}')">
                                {{ $t('Start') }}
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                container.style.display = 'block';
            } else {
                showError(data.message || '{{ $t('Failed to load processes.') }}');
            }
        } catch (e) {
            loading.style.display = 'none';
            showError('{{ $t('Error connecting to server.') }}');
        }
    }

    function startProcess(processId, processName) {
        currentProcessId = processId;
        document.getElementById('modal-title').textContent = `{{ $t('Start') }}: ${escapeHtml(processName)}`;
        document.getElementById('case_name').value = '';
        document.getElementById('start-modal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('start-modal').style.display = 'none';
        currentProcessId = null;
    }

    document.getElementById('confirm-start-btn').addEventListener('click', async () => {
        if (!currentProcessId) return;

        const caseName = document.getElementById('case_name').value.trim();
        const btn = document.getElementById('confirm-start-btn');
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = '{{ $t('Starting...') }}';

        try {
            const response = await fetch(`{{ route('workflow2.api.processes.start', ['process' => 'ID_PLACEHOLDER']) }}`.replace('ID_PLACEHOLDER', currentProcessId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    case_name: caseName || null
                })
            });

            const data = await response.json();

            if (data.ok) {
                closeModal();
                showResult(`{{ $t('Process started successfully.') }} Case ID: ${data.case_id}, Number: ${data.case_number}, Status: ${data.status}`);
                loadProcesses();
            } else {
                showError(data.message || '{{ $t('Failed to start process.') }}');
            }
        } catch (e) {
            showError('{{ $t('Error starting process.') }}');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });

    function showError(message) {
        const el = document.getElementById('error');
        el.textContent = message;
        el.style.display = 'block';
        setTimeout(() => el.style.display = 'none', 5000);
    }

    function showResult(message) {
        const el = document.getElementById('result');
        el.textContent = message;
        el.style.display = 'block';
        setTimeout(() => el.style.display = 'none', 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.onclick = function(event) {
        const modal = document.getElementById('start-modal');
        if (event.target === modal) {
            closeModal();
        }
    };

    loadProcesses();
</script>
@endsection