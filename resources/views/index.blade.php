@extends($layout)

@section('title', 'فهرست نمودارها')

@section('style')
    <link rel="stylesheet" href="{{ \ArghavanWorkflow\Support\Assets::stylesheet() }}">
@endsection

@section('content')
<div class="aw-card">
    <div class="aw-card-header">
        <h2>فهرست نمودارها</h2>
        <a class="aw-btn" href="{{ route('workflow.designer') }}">＋ نمودار جدید</a>
    </div>

    @if (! $storageEnabled)
        <p class="aw-muted">
            ذخیره‌سازی نمودارها در کانفیگ پکیج (<code>workflow.storage.enabled</code>) غیرفعال است؛
            محیط طراحی فقط برای مشاهده و خروجی گرفتن در دسترس است.
        </p>
    @elseif (count($diagrams) === 0)
        <p class="aw-muted">هنوز نموداری ذخیره نشده است. با «نمودار جدید» شروع کنید.</p>
    @else
        <table class="aw-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>آخرین ویرایش</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($diagrams as $diagram)
                    <tr>
                        <td>{{ $diagram['name'] }}</td>
                        <td>{{ $diagram['updated_at'] }}</td>
                        <td class="aw-actions">
                            <a class="aw-btn small" href="{{ route('workflow.designer', $diagram['id']) }}">ویرایش</a>

                            <form method="POST" action="{{ route('workflow.destroy', $diagram['id']) }}"
                                  onsubmit="return confirm('این نمودار برای همیشه حذف شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="aw-btn small danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
