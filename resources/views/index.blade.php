@extends($layout)

@php
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);
@endphp

@section('title', $t('Diagram List'))

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">
@endsection

@section('content')
<div class="aw-card">
    <div class="aw-card-header">
        <h2>{{ $t('Diagram List') }}</h2>
        <a class="aw-btn" href="{{ route('workflow2.designer') }}">＋ {{ $t('New Diagram') }}</a>
    </div>

    @if (! $storageEnabled)
        <p class="aw-muted">
            {!! str_replace(':key', '<code>workflow.storage.enabled</code>', e($t('Storing diagrams is disabled in the package config (:key); the designer is only available for viewing and export.'))) !!}
        </p>
    @elseif (count($diagrams) === 0)
        <p class="aw-muted">{{ $t('No diagram has been saved yet. Start with "New Diagram".') }}</p>
    @else
        <table class="aw-table">
            <thead>
                <tr>
                    <th>{{ $t('Title') }}</th>
                    <th>{{ $t('Last Modified') }}</th>
                    <th>{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($diagrams as $diagram)
                    <tr>
                        <td>{{ $diagram['name'] }}</td>
                        <td>{{ $diagram['updated_at'] }}</td>
                        <td class="aw-actions">
                            <a class="aw-btn small" href="{{ route('workflow2.designer', $diagram['id']) }}">{{ $t('Edit') }}</a>

                            <form method="POST" action="{{ route('workflow2.destroy', $diagram['id']) }}"
                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($t('Delete this diagram permanently?')) }});">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="aw-btn small danger">{{ $t('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
