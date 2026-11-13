<?php

namespace ArghavanWorkflow\Http\Controllers\Api;

use ArghavanWorkflow\Runtime\ProcessEngine;
use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Support\BpmnXml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * وب سرویس برای ایجاد و شروع فرآیندها (فرآیندهای BPMN ذخیره‌شده).
 *
 * این پکیج فقط محیط طراحی است؛ اجرای عملیات اجرا بر عهده‌ی
 * برنامه میزبان است. این کنترلر فقط فرآیند را ایجاد/شناسایی کرده و
 * شناسه‌ی آن را برمی‌گرداند تا برنامه می‌تواند مراحل بعدی اجرا را انجام دهد.
 */
class ProcessController extends Controller
{
    public function __construct(private readonly DiagramRepository $diagrams)
    {
    }

    /**
     * فهرست فرآیندها.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'processes' => $this->diagrams->all(),
        ]);
    }

    /**
     * ایجاد / شروع یک فرآیند جدید با دریافت XML نمودار.
     *
     * Body:
     *   name: string ( required )
     *   xml:  string ( required - BPMN XML )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'xml' => ['required', 'string'],
        ]);

        $maxBytes = (int) config('workflow.storage.max_xml_bytes', 2 * 1024 * 1024);

        if (strlen($data['xml']) > $maxBytes) {
            return response()->json([
                'ok' => false,
                'message' => 'حجم XML بیش از حد مجاز است.',
            ], 422);
        }

        if (! BpmnXml::isValid($data['xml'])) {
            return response()->json([
                'ok' => false,
                'message' => 'XML نمودار معتبر نیست.',
            ], 422);
        }

        try {
            $diagram = $this->diagrams->save(null, $data['name'], $data['xml']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'ایجاد فرآیند انجام نشد.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $diagram['id'],
            'name' => $diagram['name'],
            'route' => route('workflow.designer', $diagram['id']),
        ], 201);
    }

    /**
     * نمایش جزئیات یک فرآیند.
     */
    public function show(string $process): JsonResponse
    {
        $diagram = $this->diagrams->find($process);

        if ($diagram === null) {
            return response()->json([
                'ok' => false,
                'message' => 'فرآیند یافت نشد.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'process' => $diagram,
        ]);
    }

    /**
     * استارت زدن فرایند: ساخت پرونده در workflow_cases و اجرای روتینگ از
     * المان شروع فرایند تا رسیدن به اولین تسک کاربری یا المان پایان.
     *
     * Body (اختیاری):
     *   case_name:   string (نام پرونده)
     *   case_number: int    (شماره پرونده؛ در صورت ارسال، شماره‌گذاری خودکار نادیده گرفته می‌شود)
     */
    public function start(Request $request, string $process): JsonResponse
    {
        $diagram = $this->diagrams->find($process);

        if ($diagram === null) {
            return response()->json([
                'ok' => false,
                'message' => 'فرآیند یافت نشد.',
            ], 404);
        }

        $result = ProcessEngine::start(
            $process,
            Auth::id(),
            $request->input('case_name'),
            $request->input('case_number')
        );

        if ($result['ok'] === false) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'] ?? 'اجرای فرآیند انجام نشد.',
            ], 400);
        }

        $case = $result['case'];

        return response()->json([
            'ok' => true,
            'case_id' => $case->id,
            'case_number' => $case->number,
            'status' => $case->status,
            'stopped_at' => $result['stopped_at'] ?? null,
            'message' => 'فرآیند استارت خورد و پرونده ساخته شد.',
        ]);
    }

    /**
     * نمایش صفحه شروع فرآیند (لیست فرآیندها برای اجرا).
     */
    public function startPage()
    {
        $layout = (string) config('workflow.layout', 'behin-layouts.app');
        $layout = $layout !== '' && view()->exists($layout) ? $layout : 'workflow::layout';

        return view('workflow::start', [
            'layout' => $layout,
        ]);
    }
}
