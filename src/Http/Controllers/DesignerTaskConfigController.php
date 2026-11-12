<?php

namespace ArghavanWorkflow\Http\Controllers;

use ArghavanWorkflow\Services\DiagramConfigStore;
use ArghavanWorkflow\Services\DiagramRepository;
use ArghavanWorkflow\Support\Lang;
use Behin\SimpleWorkflow\Models\Core\Form;
use BehinUserRoles\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class DesignerTaskConfigController extends BaseController
{
    public function __construct(private readonly DiagramConfigStore $config)
    {
    }

    public function list(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'forms' => $this->collections(Form::class, 'id', 'name'),
            'users' => $this->collections(\App\Models\User::class, 'id', 'name'),
            'roles' => $this->collections(Role::class, 'id', 'name'),
            'assignmentTypes' => [
                ['id' => 'normal', 'name' => Lang::field('Normal')],
                ['id' => 'dynamic', 'name' => Lang::field('Dynamic')],
                ['id' => 'public', 'name' => Lang::field('Public')],
            ],
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

        foreach (DiagramConfigStore::FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if (! preg_match(DiagramRepository::ID_PATTERN, $doc)) {
            return response()->json(['ok' => false, 'message' => Lang::field('Invalid diagram ID.')], 422);
        }

        if (! preg_match('/^[A-Za-z0-9_\-]+$/', $element)) {
            return response()->json(['ok' => false, 'message' => Lang::field('Invalid element ID.')], 422);
        }

        try {
            $result = $this->config->save($doc, $element, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => Lang::field('Saving failed.')], 500);
        }

        // XML به‌روزشده برگردانده می‌شود تا طراح بتواند نمودار درون‌مرورگر را
        // با تنظیمات جدید همگام کند و ذخیره‌ی بعدی نمودار آن‌ها را از دست ندهد.
        return response()->json([
            'ok' => $result['ok'] ?? true,
            'xml' => $result['xml'] ?? null,
        ]);
    }

    private function storageEnabled(): bool
    {
        return (bool) config('workflow.storage.enabled', true);
    }

    /**
     * @param class-string $model
     * @param string $id
     * @param string $label
     * @return array<int, array{id: string, name: string}>
     */
    private function collections(string $model, string $id, string $label): array
    {
        $items = $model::all();

        $items->each(function ($item) use ($id, $label): void {
            if (! is_object($item)) {
                return;
            }

            $item->{$id} = (string) $item->{$id};
            $item->{$label} = (string) $item->{$label};
        });

        return $items->toArray();
    }
}
