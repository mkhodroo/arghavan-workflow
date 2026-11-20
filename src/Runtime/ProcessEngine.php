<?php

namespace ArghavanWorkflow\Runtime;

use ArghavanWorkflow\Models\Elements\EndEvent;
use ArghavanWorkflow\Models\Elements\ExclusiveGateway;
use ArghavanWorkflow\Models\Elements\ScriptTask;
use ArghavanWorkflow\Models\Elements\SequenceFlow;
use ArghavanWorkflow\Models\Elements\StartEvent;
use ArghavanWorkflow\Models\Elements\UserTask;
use ArghavanWorkflow\Models\Runtime\WorkflowCase;
use ArghavanWorkflow\Models\Runtime\WorkflowInbox;
use ArghavanWorkflow\Models\WorkflowProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Throwable;

/**
 * موتور اجرای فرایند (Routing).
 *
 * با استارت زدن یک فرایند، یک پرونده در workflow_cases ساخته می‌شود و روتینگ
 * از «المان شروع» فرایند جلو می‌رود و به هر المانی که می‌رسد مطابق همان
 * المان رفتار می‌کند:
 *
 *   - المان شروع (StartEvent): وضعیت پرونده «new» می‌شود و جریان ادامه پیدا می‌کند
 *   - تسک کاربری (UserTask): برای تک‌تک اکتورها (کاربر مستقیم و/یا همه‌ی
 *     کاربرانِ دارای نقش) یک رکورد در workflow_inbox ساخته می‌شود و جریان
 *     منتظر انجام کار می‌ماند
 *   - اسکریپت تسک (ScriptTask): فایل اجرایی تعیین‌شده اجرا می‌شود؛ در صورت
 *     خطا، خطا برگردانده می‌شود و در صورت نبود خطا به مرحله بعد می‌رود
 *   - گیت‌وی شرطی (ExclusiveGateway): شرطِ جریان‌های خروجی (wf_conditions)
 *     ارزیابی می‌شود و جریان برقرار طی می‌شود؛ در غیر این صورت جریان پیش‌فرض
 *   - المان پایان (EndEvent): وضعیت پرونده «done» می‌شود و روتینگ تمام می‌شود
 */
class ProcessEngine
{
    /**
     * استارت زدن یک فرایند: ساخت پرونده و اجرای روتینگ از المان شروع.
     *
     * همه‌چیز در یک تراکنش انجام می‌شود؛ در صورت خطای اسکریپت یا نبود مسیر،
     * تراکنش برگردانده می‌شود و خطا برگردانده می‌شود.
     *
     * @return array{
     *     ok: bool,
     *     case?: WorkflowCase,
     *     stopped_at?: string|null,
     *     error?: string
     * }
     */
    public static function start(
        string $processId,
        $creatorId = null,
        ?string $caseName = null,
        $caseNumber = null
    ): array {
        $process = WorkflowProcess::query()->find($processId);

        if ($process === null) {
            return ['ok' => false, 'error' => 'Process not found.'];
        }

        /** @var StartEvent|null $startEvent */
        $startEvent = StartEvent::query()
            ->forProcess($processId)
            ->orderBy('sort_order')
            ->first();

        if ($startEvent === null) {
            return ['ok' => false, 'error' => 'Process has no start event.'];
        }

        DB::beginTransaction();

        try {
            $case = WorkflowCase::create([
                'process_id' => $processId,
                'number' => $caseNumber ?: self::generateCaseNumber(),
                'name' => $caseName,
                'creator' => $creatorId,
                'status' => 'new',
            ]);

            $visited = [];
            $result = self::runElement($processId, (string) $startEvent->element_id, $case, $visited);

            if ($result['ok'] === false) {
                DB::rollBack();

                return $result;
            }

            DB::commit();

            return [
                'ok' => true,
                'case' => $case->refresh(),
                'stopped_at' => $result['stopped_at'] ?? null,
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * اجرای یک المان و ادامه‌ی جریان تا توقف (تسک کاربری / المان پایان / خطا).
     *
     * @param  array<int, string>  $visited  گارد چرخه؛ شناسه‌ی المان‌های طی‌شده
     * @return array{ok: bool, stopped_at?: string|null, error?: string}
     */
    private static function runElement(string $processId, string $elementId, WorkflowCase $case, array &$visited): array
    {
        $key = $processId . '|' . $elementId;

        // گارد چرخه: المانی که قبلاً در همین مسیر اجرا شده دوباره اجرا نمی‌شود
        if (in_array($key, $visited, true)) {
            return ['ok' => true, 'stopped_at' => $elementId];
        }

        $visited[] = $key;

        $element = self::findElement($processId, $elementId);

        if ($element === null) {
            return ['ok' => false, 'error' => 'Element not found in process: ' . $elementId];
        }

        // ------------------------------------------------------------------
        // المان شروع: وضعیت پرونده «new» و ادامه‌ی جریان
        // ------------------------------------------------------------------
        if ($element instanceof StartEvent) {
            $case->status = 'new';
            $case->save();

            return self::continue($processId, $elementId, $case, $visited);
        }

        // ------------------------------------------------------------------
        // تسک کاربری: ساخت رکورد کارتابل برای تک‌تک اکتورها و توقف جریان
        // ------------------------------------------------------------------
        if ($element instanceof UserTask) {
            foreach (self::resolveActors($element) as $actor) {
                WorkflowInbox::create([
                    'task_id' => $element->id,
                    'case_id' => $case->id,
                    'actor' => $actor,
                    'status' => 'new',
                    'case_name' => $element->case_name ?: $case->name,
                ]);
            }

            return ['ok' => true, 'stopped_at' => $elementId];
        }

        // ------------------------------------------------------------------
        // اسکریپت تسک: اجرای فایل اجرایی؛ خطا جریان را متوقف می‌کند
        // ------------------------------------------------------------------
        if ($element instanceof ScriptTask) {
            $result = ScriptRunner::run($element->script_id, $case);
            if ($result['ok'] === false) {
                return ['ok' => false, 'error' => $result['error']];
            }

            return self::continue($processId, $elementId, $case, $visited);
        }

        // ------------------------------------------------------------------
        // گیت‌وی شرطی: ارزیابی شرطِ جریان‌های خروجی و طی جریان برقرار/پیش‌فرض
        // ------------------------------------------------------------------
        if ($element instanceof ExclusiveGateway) {
            $target = self::resolveGatewayTarget($processId, $elementId, $case);

            if ($target === null) {
                return ['ok' => false, 'error' => 'No valid outgoing flow found for gateway: ' . $elementId];
            }

            return self::runElement($processId, $target, $case, $visited);
        }

        // ------------------------------------------------------------------
        // المان پایان: وضعیت پرونده «done» و پایان روتینگ
        // ------------------------------------------------------------------
        if ($element instanceof EndEvent) {
            $case->status = 'done';
            $case->save();

            return ['ok' => true, 'stopped_at' => $elementId];
        }

        // المان ناشناخته: مثل یک گره ساده از آن عبور می‌شود
        return self::continue($processId, $elementId, $case, $visited);
    }

    /**
     * ادامه‌ی جریان از طریق جریان(های) خروجی المان فعلی.
     *
     * @param  array<int, string>  $visited
     * @return array{ok: bool, stopped_at?: string|null, error?: string}
     */
    private static function continue(string $processId, string $elementId, WorkflowCase $case, array &$visited): array
    {
        $targets = self::outgoingTargets($processId, $elementId);

        if (count($targets) === 0) {
            return ['ok' => false, 'error' => 'Element has no outgoing flow: ' . $elementId];
        }

        $last = ['ok' => true, 'stopped_at' => null];

        foreach ($targets as $target) {
            $result = self::runElement($processId, $target, $case, $visited);

            if ($result['ok'] === false) {
                return $result;
            }

            $last = $result;
        }

        return $last;
    }

    /**
     * ادامه‌ی روتینگ بعد از اتمام یک کار کارتابل.
     *
     * رکورد کارتابل «done» می‌شود و جریان از خروجی همان تسک کاربری جلو می‌رود
     * (خودِ تسک دوباره اجرا نمی‌شود). در صورت خطا کل تراکنش برمی‌گردد.
     *
     * @return array{
     *     ok: bool,
     *     case?: WorkflowCase,
     *     stopped_at?: string|null,
     *     error?: string
     * }
     */
    public static function next(WorkflowInbox $inbox, $doneBy = null): array
    {
        $inbox->loadMissing(['task', 'case']);

        $task = $inbox->task;
        $case = $inbox->case;

        if (! $task instanceof UserTask || $case === null) {
            return ['ok' => false, 'error' => 'Task or case not found.'];
        }

        DB::beginTransaction();

        try {
            $now = now();

            $inbox->update([
                'status' => 'done',
                'done_by' => $doneBy,
                'done_at' => $now,
            ]);

            WorkflowInbox::query()
                ->where('task_id', $task->id)
                ->where('case_id', $case->id)
                ->where('id', '!=', $inbox->id)
                ->whereIn('status', ['new', 'opened', 'inProgress', 'draft'])
                ->update([
                    'status' => 'done',
                    'done_at' => $now,
                ]);

            $visited = [(string) $task->process_id . '|' . (string) $task->element_id];
            $result = self::continue((string) $task->process_id, (string) $task->element_id, $case, $visited);
            
            Log::info($result);

            if ($result['ok'] === false) {
                DB::rollBack();

                return $result;
            }

            

            DB::commit();

            return [
                'ok' => true,
                'case' => $case->refresh(),
                'stopped_at' => $result['stopped_at'] ?? null,
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * مقصدهای جریان‌های خروجی یک گره (به ترتیب).
     *
     * @return array<int, string>
     */
    private static function outgoingTargets(string $processId, string $elementId): array
    {
        return SequenceFlow::query()
            ->forProcess($processId)
            ->where('source_ref', $elementId)
            ->orderBy('sort_order')
            ->pluck('target_ref')
            ->map(fn ($target) => (string) $target)
            ->filter(fn ($target) => $target !== '')
            ->values()
            ->all();
    }

    /**
     * انتخاب جریان درست خروجی از گیت‌وی شرطی:
     * اولین جریانی که شرطش برقرار است؛ اگر هیچ‌کدام برقرار نبود جریان پیش‌فرض.
     */
    private static function resolveGatewayTarget(string $processId, string $elementId, WorkflowCase $case): ?string
    {
        $flows = SequenceFlow::query()
            ->forProcess($processId)
            ->where('source_ref', $elementId)
            ->orderBy('sort_order')
            ->get();

        $defaultTarget = null;

        foreach ($flows as $flow) {
            if ($flow->is_default) {
                $defaultTarget = $flow->target_ref;

                continue;
            }

            $result = ConditionEvaluator::evaluate($flow->condition_id, $case);

            if ($result['ok'] === false) {
                // جریان بدون شرط معتبر نیست؛ خطا برمی‌گردد تا مشکل پیکربندی دیده شود
                throw new \RuntimeException($result['error']);
            }

            if ($result['result'] === true) {
                return (string) $flow->target_ref;
            }
        }

        return $defaultTarget !== null ? (string) $defaultTarget : null;
    }

    /**
     * کاربران تخصیص‌یافته‌ی یک تسک کاربری:
     * کاربر مستقیم + همه‌ی کاربرانِ دارای نقش (ترکیب هر دو هم پشتیبانی می‌شود).
     *
     * @return array<int, int|string>
     */
    private static function resolveActors(UserTask $task): array
    {
        $userModel = config('workflow.user_model', \App\Models\User::class);

        $actorIds = collect();

        if ($task->role_id) {
            $actorIds = $actorIds->merge(
                $userModel::query()->where('role_id', $task->role_id)->pluck('id')
            );
        }

        if ($task->actor) {
            $actorIds->push($task->actor);
        }

        return $actorIds
            ->map(fn ($id) => is_numeric($id) ? (int) $id : $id)
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * پیدا کردن مدل المان از جدول نوع خودش.
     */
    private static function findElement(string $processId, string $elementId): ?object
    {
        foreach ([
            StartEvent::class,
            EndEvent::class,
            UserTask::class,
            ScriptTask::class,
            ExclusiveGateway::class,
        ] as $modelClass) {
            /** @var \ArghavanWorkflow\Models\Elements\BpmnElement|null $element */
            $element = $modelClass::query()
                ->forProcess($processId)
                ->where('element_id', $elementId)
                ->first();

            if ($element !== null) {
                return $element;
            }
        }

        return null;
    }

    /**
     * شماره پرونده‌ی بعدی: بزرگ‌ترین شماره‌ی موجود + ۱ (شروع از caseStartValue).
     */
    private static function generateCaseNumber(): int
    {
        $last = (int) (WorkflowCase::query()->max('number') ?: 0);
        $start = (int) config('workflow.caseStartValue', 1000);

        return max($last + 1, $start);
    }
}
