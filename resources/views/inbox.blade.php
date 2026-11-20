@extends($layout)

@php
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);
@endphp

@section('title', $t('Inbox'))

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">

    <style>
        .aw-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .aw-table th,
        .aw-table td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: right;
        }
        .aw-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .aw-table tr:hover {
            background-color: #f5f5f5;
        }
        .aw-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-new { background-color: #e3f2fd; color: #1565c0; }
        .badge-opened { background-color: #fff3e0; color: #ef6c00; }
        .badge-inprogress { background-color: #e8f5e9; color: #2e7d32; }
        .badge-draft { background-color: #f3e5f5; color: #7b1fa2; }
        .aw-btn {
            padding: 6px 12px;
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
        .aw-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .aw-empty {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        .aw-alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .aw-alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .aw-alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .aw-actions {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
        }
    </style>
@endsection

@section('content')
<div class="aw-card">
    <div class="aw-card-header">
        <h2>{{ $t('Inbox') }}</h2>
        <a class="aw-btn" href="{{ route('workflow2.start') }}">{{ $t('Start New Process') }}</a>
        <a class="aw-btn" href="{{ route('workflow2.index') }}">{{ $t('Diagrams') }}</a>
    </div>

    <div id="loading" class="aw-muted">{{ $t('Loading inbox...') }}</div>
    <div id="error" class="aw-alert error" style="display: none;"></div>
    <div id="result" class="aw-alert success" style="display: none;"></div>

    <div id="inbox-container" style="display: none;">
        <table class="aw-table">
            <thead>
                <tr>
                    <th>{{ $t('Task') }}</th>
                    <th>{{ $t('Case') }}</th>
                    <th>{{ $t('Case Number') }}</th>
                    <th>{{ $t('Status') }}</th>
                    <th>{{ $t('Created At') }}</th>
                    <th>{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody id="inbox-table-body">
            </tbody>
        </table>
@endsection

@section('script')
<script>
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function loadInbox() {
        const loading = document.getElementById('loading');
        const container = document.getElementById('inbox-container');
        const emptyState = document.getElementById('empty-state');
        const tbody = document.getElementById('inbox-table-body');

        try {
            const response = await fetch('{{ route('workflow2.api.inbox.index') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();

            loading.style.display = 'none';

            if (data.ok && data.rows) {
                if (data.rows.length === 0) {
                    emptyState.style.display = 'block';
                    return;
                }

                tbody.innerHTML = '';
                data.rows.forEach(row => {
                    const tr = document.createElement('tr');
                    const statusClass = 'badge-' + (row.status || '').toLowerCase();
                    tr.innerHTML = `
                        <td>${escapeHtml(row.task_name)}</td>
                        <td>${escapeHtml(row.case_name)}</td>
                        <td>${row.case_number}</td>
                        <td><span class="aw-badge ${statusClass}">${escapeHtml(row.status_label)}</span></td>
                        <td>${row.created_at}</td>
                        <td class="aw-actions">
                            <button class="aw-btn" onclick="viewDetail('${row.id}')">{{ $t('View') }}</button>
                            <button class="aw-btn primary" onclick="markDone('${row.id}')" ${row.status === 'done' ? 'disabled' : ''}>
                                {{ $t('Mark Done') }}
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                container.style.display = 'block';
            } else {
                showError(data.message || '{{ $t('Failed to load inbox.') }}');
            }
        } catch (e) {
            loading.style.display = 'none';
            showError('{{ $t('Error connecting to server.') }}');
        }
    }

    async function viewDetail(inboxId) {
        try {
            window.location.href = `{{ route('workflow2.api.inbox.formPage', ['inbox' => 'ID_PLACEHOLDER']) }}`.replace('ID_PLACEHOLDER', inboxId);
        } catch (e) {
            showError('{{ $t('Error loading detail.') }}');
        }
    }

    async function markDone(inboxId) {
        if (!confirm('{{ $t('Are you sure you want to mark this task as done?') }}')) {
            return;
        }

        try {
            const response = await fetch(`{{ route('workflow2.api.inbox.next', ['inbox' => 'ID_PLACEHOLDER']) }}`.replace('ID_PLACEHOLDER', inboxId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.ok) {
                showResult(data.message || '{{ $t('Task completed and process continued.') }}');
                loadInbox();
            } else {
                showError(data.message || '{{ $t('Failed to mark as done.') }}');
            }
        } catch (e) {
            showError('{{ $t('Error marking as done.') }}');
        }
    }

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

    loadInbox();
</script>
    </div>

    <div id="empty-state" style="display: none;">
        <div class="aw-empty">
            <p>{{ $t('No pending tasks in your inbox.') }}</p>
        </div>
    </div>
</div>

@endsection