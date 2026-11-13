<?php

namespace ArghavanWorkflow\Http\Controllers\Api;

use ArghavanWorkflow\Models\Runtime\WorkflowInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * وب سرویس کارتابل کاربر.
 *
 * نمایش لیست کارهای تخصیص‌یافته به کاربر جاری در جدول wf_inbox.
 */
class InboxController extends Controller
{
    /**
     * لیست کارهای کاربر جاری (فقط کارهای باز/درج نشده).
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $rows = WorkflowInbox::with(['task', 'case', 'actorUser'])
            ->where('actor', $userId)
            ->whereIn('status', ['new', 'opened', 'inProgress', 'draft'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $rows->map(function ($row) {
            $task = optional($row->task);
            $case = optional($row->case);

            return [
                'id' => $row->id,
                'task_id' => $row->task_id,
                'task_name' => $task?->name ?? '-',
                'case_id' => $row->case_id,
                'case_name' => $row->case_name ?? ($case?->name ?? '-'),
                'case_number' => $case?->number ?? '-',
                'actor' => $row->actor,
                'actor_name' => optional($row->actorUser)->name ?? '-',
                'status' => $row->status,
                'status_label' => $this->statusLabel($row->status),
                'created_at' => $row->created_at?->format('Y/m/d H:i') ?? '-',
                'updated_at' => $row->updated_at?->format('Y/m/d H:i') ?? '-',
                'url' => ''// route(config('workflow.route_prefix', 'workflow') . '.inbox.view', $row->id),
            ];
        });

        return response()->json([
            'ok' => true,
            'count' => $data->count(),
            'rows' => $data,
        ]);
    }

    /**
     * جزئیات یک رکورد کارتابل.
     *
     * @param string $inboxId
     * @return JsonResponse
     */
    public function show(string $inboxId): JsonResponse
    {
        $row = WorkflowInbox::with(['task', 'case', 'actorUser'])->find($inboxId);

        if (!$row) {
            return response()->json([
                'ok' => false,
                'message' => 'رکورد کارتابل یافت نشد.',
            ], 404);
        }

        $userId = Auth::id();
        if ($row->actor != $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'شما دسترسی به این رکورد ندارید.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'row' => [
                'id' => $row->id,
                'task_id' => $row->task_id,
                'task_name' => optional($row->task)->name ?? '-',
                'case_id' => $row->case_id,
                'case_name' => $row->case_name ?? optional($row->case)->name ?? '-',
                'case_number' => optional($row->case)->number ?? '-',
                'status' => $row->status,
                'created_at' => $row->created_at?->format('Y/m/d H:i') ?? '-',
                'updated_at' => $row->updated_at?->format('Y/m/d H:i') ?? '-',
            ],
        ]);
    }

    /**
     * علامت‌گذاری یک کار به عنوان انجام‌شده.
     *
     * @param Request $request
     * @param string $inboxId
     * @return JsonResponse
     */
    public function done(Request $request, string $inboxId): JsonResponse
    {
        $row = WorkflowInbox::find($inboxId);

        if (!$row) {
            return response()->json([
                'ok' => false,
                'message' => 'رکورد کارتابل یافت نشد.',
            ], 404);
        }

        $userId = Auth::id();
        if ($row->actor != $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'شما دسترسی به این رکورد ندارید.',
            ], 403);
        }

        $row->update([
            'status' => 'done',
            'done_by' => $userId,
            'done_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'کار با موفقیت انجام شد.',
        ]);
    }

    /**\n     * علامت‌گذاری یک کار به عنوان انجام‌شده و اجرای نمره بعدی فرآیند.\n     *\n     * @param Request $request\n     * @param string $inboxId\n     * @return JsonResponse\n     */\n    public function complete(Request $request, string $inboxId): JsonResponse\n    {\n        $inbox = WorkflowInbox::find($inboxId);\n\n        if (!$inbox) {\n            return response()->json(['ok' => false, 'message' => 'رکورد کارتابل یافت نشد.'], 404);\n        }\n\n        $userId = Auth::id();\n        if ($inbox->actor != $userId) {\n            return response()->json(['ok' => false, 'message' => 'شما دسترسی به این کارتبال ندارید.'], 403);\n        }\n\n        $inbox->update([\n            'status' => 'done',\n            'done_by' => $userId,\n            'done_at' => now(),\n        ]);\n\n        return self::continueProcess($inbox->task_id, $inbox->case_id);\n    }\n\n    /**\n     * اجرای نمره بعدی فرآیند پس از اتمام تسک.\n     * \n     * @param string $taskId شناسه تسک ساله‌شده\n     * @param string $caseId شناسه پرونده (حالت ساله‌سازی)\n     * @return JsonResponse\n     */\n    private static function continueProcess(string $taskId, string $caseId): JsonResponse\n    {\n        $task = WorkflowInbox::find($taskId)->task;\n        $case = WorkflowInbox::find($taskId)->case;\n\n        if (!$task || !$case) {\n            return response()->json(['ok' => false, 'message' => 'تسک یا پرونده یافت نشد.']);\n        }\n\n        // پیدا کردن المان اصلی داخل نمودار بر اساس تسک\n        $element = self::findElement($task->process_id, $task->element_id);\n\n        if (!$element) {\n            return response()->json(['ok' => false, 'message' => 'المان تسک یافت نشد.']);\n        }\n\n        // تسک کاربری: در ادامه به کاربر دیگر برویم یا کیف پول فعال است\n        if ($element instanceof ArghavanWorkflow\\Models\\Elements\\UserTask) {\n            return self::continueProcess($task->task_id, $case->id);\n        }\n\n        // تسک اسکریپت: اجرا و ادامه\n        if ($element instanceof ArghavanWorkflow\\Models\\Elements\\ScriptTask) {\n            return self::runNextElement($task->process_id, $task->element_id, $case, []);\n        }\n\n        // گیت‌وی شرطی\n        if ($element instanceof ArghavanWorkflow\\Models\\Elements\\ExclusiveGateway) {\n            $target = self::resolveGatewayTarget($task->process_id, $element->element_id, $case);\n            if ($target === null) {\n                return response()->json(['ok' => false, 'message' => 'هیچ جریان خروجی معتبری یافت نشد.']);\n            }\n            return self::runNextElement($task->process_id, $target, $case, []);\n        }\n\n        // المان پایان\n        if ($element instanceof ArghavanWorkflow\\Models\\Elements\\EndEvent) {\n            $case->status = 'done';\n            $case->save();\n            return response()->json(['ok' => true, 'message' => 'فرآیند با موفقیت تمام شد.']);\n        }\n\n        // المانی دیگر (شامل StartEvent و گره‌های ناشناخته)\n        return self::runNextElement($task->process_id, $task->element_id, $case, []);\n    }
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'جدید',
            'opened' => 'باز شده',
            'inProgress' => 'در حال انجام',
            'draft' => 'پیش‌نویس',
            'done' => 'انجام شده',
            'doneByOther' => 'انجام شده توسط دیگری',
            'canceled' => 'لغو شده',
            default => $status,
        };
    }

    private static function resolveGatewayTarget(string $processId, string $elementId, WorkflowCase $case): ?string
    {
        // جستجوی جریان‌های خروجی از گیت‌وی شرطی
        $flows = DB::table('workflow_sequence_flows')
            ->where('source_ref', $elementId)
            ->where('process_id', $processId)
            ->get();

        $defaultTarget = null;
        $defaultFound = false;

        foreach ($flows as $flow) {
            if ($flow->is_default) {
                $defaultTarget = $flow->target_ref;
                $defaultFound = true;
                continue;
            }

            if ($flow->condition_id) {
                $condition = \Behin\SimpleWorkflow\Models\Core\Condition::find($flow->condition_id);
                if ($condition) {
                    $rules = json_decode((string) $condition->content, true) ?? [];
                    $variables = $case->variables();
                    $allMatch = true;
                    foreach ($rules as $rule) {
                        $match = $variables->where('field', $rule['field'] ?? '')->first();
                        if (!$match || $match->value != ($rule['value'] ?? '')) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        return $flow->target_ref;
                    }
                }
            }
        }

        return $defaultFound ? $defaultTarget : null;
    }

    private static function runNextElement(string $processId, string $elementId, WorkflowCase $case, array $visited): JsonResponse
    {
        $key = $processId . '|' . $elementId;
        if (in_array($key, $visited, true)) {
            return response()->json(['ok' => true, 'stopped_at' => $elementId]);
        }

        $visited[] = $key;

        $element = self::findElement($processId, $elementId);

        if (!$element) {
            return response()->json(['ok' => false, 'message' => 'المان یافت نشد: ' . $elementId]);
        }

        if ($element instanceof ArghavanWorkflow\Models\Elements\StartEvent) {
            $case->status = 'new';
            $case->save();
            return self::runNextElement($processId, $elementId, $case, $visited);
        }

        if ($element instanceof ArghavanWorkflow\Models\Elements\ScriptTask) {
            $result = ScriptRunner::run($element->script_id, $case);
            if ($result['ok'] === false) {
                return response()->json(['ok' => false, 'error' => $result['error']]);
            }
            return self::runNextElement($processId, $elementId, $case, $visited);
        }

        if ($element instanceof ArghavanWorkflow\Models\Elements\ExclusiveGateway) {
            $target = self::resolveGatewayTarget($processId, $elementId, $case);
            if ($target === null) {
                return response()->json(['ok' => false, 'message' => 'هیچ جریان خروجی معتبری یافت نشد.']);
            }
            return self::runNextElement($processId, $target, $case, $visited);
        }

        if ($element instanceof ArghavanWorkflow\Models\Elements\EndEvent) {
            $case->status = 'done';
            $case->save();
            return response()->json(['ok' => true, 'message' => 'فرآیند با موفقیت تمام شد.']);
        }

        // المانی دیگر (شامل گره‌های ناشناخته، UserTask، و غیر آن‌ها)
        if ($element instanceof ArghavanWorkflow\Models\Elements\UserTask) {
            // تسک کاربری: ساخت رکورد کارتابل برای هر اکتور و توقف جریان
            foreach (self::resolveActors($element) as $actor) {
                WorkflowInbox::create([
                    'task_id' => $element->id,
                    'case_id' => $case->id,
                    'actor' => $actor,
                    'status' => 'new',
                    'case_name' => $element->case_name ?: $case->name,
                ]);
            }
            return response()->json(['ok' => true, 'stopped_at' => $elementId]);
        }

        // المانی دیگر (غیر شناخته)
        return self::runNextElement($processId, $elementId, $case, $visited);
    }
    /**
     * نمایش صفحه کارتابل کاربر.
     */
    public function inboxPage()
    {
        $layout = (string) config('workflow2.layout', 'behin-layouts.app');
        $layout = $layout !== '' && view()->exists($layout) ? $layout : 'workflow::layout';
        return view('workflow::inbox', [
            'layout' => $layout,
        ]);
    }
}