<?php

namespace ArghavanWorkflow\Http\Controllers;

use ArghavanWorkflow\Services\DiagramFlowConfigStore;
use ArghavanWorkflow\Support\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * پیکربندی جریان ترتیبی (Sequence Flow) در طراح:
 * انتخاب شرطی (از wf_conditions) که روی جریان‌های خروجی گیت‌وی شرطی بررسی می‌شود.
 */
class DesignerFlowConfigController extends BaseController
{
    public function __construct(private readonly DiagramFlowConfigStore $config)
    {
    }

    /**
     * لیست داده‌های کمکی برای مودال تنظیمات جریان: شرط‌های wf_conditions.
     */
    public function list(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'conditions' => $this->config->getConditionsList(),
        ]);
    }

    public function get(Request $request, string $doc, string $element): JsonResponse
    {
        if (! $this->storageEnabled()) {
            return response()->json(['ok' => false, 'message' => Lang::field('Storing is disabled.')], 403);
        }

        $config = $this->config->find($doc, $element);

        if ($config === null) {
            return response()->json(['ok' => false, 'message' => Lang::field('Task configuration was not found.')], 404);
        }

        return response()->json(['ok' => true, 'config' => $config]);
    }

    public function save(Request $request, string $doc, string $element): JsonResponse
    {
        if (! $this->storageEnabled()) {
            return response()->json(['ok' => false, 'message' => Lang::field('Storing is disabled.')], 403);
        }

        $rules = [];

        foreach (DiagramFlowConfigStore::FIELDS as $field) {
            $rules[$field] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{0,63}$/', $doc)) {
            return response()->json(['ok' => false, 'message' => Lang::field('Invalid diagram ID.')], 422);
        }

        if (! preg_match('/^[A-Za-z0-9_\-]+$/', $element)) {
            return response()->json(['ok' => false, 'message' => Lang::field('Invalid element ID.')], 422);
        }

        try {
            $result = $this->config->save($doc, $element, $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => Lang::field('Saving failed.')], 500);
        }

        return response()->json([
            'ok' => $result['ok'] ?? true,
            'xml' => $result['xml'] ?? null,
        ]);
    }

    private function storageEnabled(): bool
    {
        return (bool) config('workflow.storage.enabled', true);
    }
}
