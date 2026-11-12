@php
    $t = fn (string $key): string => \ArghavanWorkflow\Support\Lang::field($key);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $t('Arghavan Workflow'))</title>
    @yield('style')
</head>
<body>
    <div class="aw-header">
        <a class="aw-brand" href="{{ route('workflow.index') }}">🌸 {{ $t('Arghavan Workflow') }}</a>
        <nav class="aw-nav">
            <a href="{{ route('workflow.index') }}">{{ $t('Diagram List') }}</a>
            <a href="{{ route('workflow.designer') }}">{{ $t('New Diagram') }}</a>
        </nav>
    </div>

    <div class="aw-container">
        @if (session('status'))
            <div class="aw-alert">{{ session('status') }}</div>
        @endif
        @if (session('success'))
            <div class="aw-alert">{{ session('success') }}</div>
        @endif

        @yield('content')
    </div>

    @yield('script')
</body>
</html>
