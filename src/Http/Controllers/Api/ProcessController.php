<?php

namespace ArghavanWorkflow\Http\Controllers\Api;

use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Support\BpmnXml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
     * علامت‌گذاری فرآیند به‌عنوان آماده برای اجرا.
     *
     * این پکیج خود فرآیند را اجرا نمی‌کند؛ فقط شناسه و مشخصات فرآیند
     * ذخیره‌شده را برمی‌گرداند تا موتور اجرا در برنامه میزبان بتواند
     * از آن برای آغاز اجرای BPMN استفاده کند.
     */
    public function start(string $process): JsonResponse
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
            'id' => $diagram['id'],
            'name' => $diagram['name'],
            'xml' => $diagram['xml'],
            'message' => 'فرآیند برای اجرا آماده است.',
        ]);
    }
}
