<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ارغوان ورکفلو')</title>
    @yield('style')
</head>
<body>
    <div class="aw-header">
        <a class="aw-brand" href="{{ route('workflow.index') }}">🌸 ارغوان ورکفلو</a>
        <nav class="aw-nav">
            <a href="{{ route('workflow.index') }}">فهرست نمودارها</a>
            <a href="{{ route('workflow.designer') }}">نمودار جدید</a>
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
