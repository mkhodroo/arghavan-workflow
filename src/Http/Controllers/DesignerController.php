<?php

namespace ArghavanWorkflow\Http\Controllers;

use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Support\BpmnXml;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class DesignerController extends Controller
{
    public function __construct(private readonly DiagramRepository $diagrams)
    {
    }

    /**
     * فهرست نمودارهای ذخیره‌شده.
     */
    public function index()
    {
        return view('workflow::index', [
            'layout' => $this->layout(),
            'storageEnabled' => $this->storageEnabled(),
            'diagrams' => $this->storageEnabled() ? $this->diagrams->all() : [],
        ]);
    }

    /**
     * محیط طراحی؛ با {doc} نمودار ذخیره‌شده و بدون آن یک نمودار خالی.
     */
    public function designer(?string $doc = null)
    {
        $diagram = null;

        if ($doc !== null && $doc !== '') {
            if (! $this->storageEnabled()) {
                abort(404);
            }

            $diagram = $this->diagrams->find($doc);

            if ($diagram === null) {
                abort(404);
            }
        }

        return view('workflow::designer', [
            'layout' => $this->layout(),
            'storageEnabled' => $this->storageEnabled(),
            'diagram' => $diagram,
        ]);
    }

    /**
     * ذخیره نمودار (AJAX)؛ شناسه در صورت نبود تولید می‌شود.
     */
    public function save(Request $request)
    {
        if (! $this->storageEnabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'ذخیره‌سازی در کانفیگ پکیج غیرفعال است.',
            ], 403);
        }

        $data = $request->validate([
            'id' => ['nullable', 'string', 'regex:'.DiagramRepository::ID_PATTERN],
            'name' => ['nullable', 'string', 'max:255'],
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
            $diagram = $this->diagrams->save($data['id'] ?? null, $data['name'] ?? '', $data['xml']);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'ذخیره نمودار در دیتابیس ممکن نشد.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $diagram['id'],
            'name' => $diagram['name'],
            'route' => route('workflow.designer', $diagram['id']),
        ]);
    }

    /**
     * حذف نمودار.
     */
    public function destroy(string $doc)
    {
        if (! $this->storageEnabled() || ! $this->diagrams->delete($doc)) {
            abort(404);
        }

        return redirect()
            ->route('workflow.index')
            ->with('success', 'نمودار حذف شد.');
    }

    private function storageEnabled(): bool
    {
        return (bool) config('workflow.storage.enabled', true);
    }

    /**
     * layout اصلی پروژه (behin-layouts.app)؛ در صورت نبود به layout پکیج.
     */
    private function layout(): string
    {
        $layout = (string) config('workflow.layout', 'behin-layouts.app');

        return $layout !== '' && view()->exists($layout) ? $layout : 'workflow::layout';
    }
}
