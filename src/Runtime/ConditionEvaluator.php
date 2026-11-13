<?php

namespace ArghavanWorkflow\Runtime;

use ArghavanWorkflow\Models\Runtime\WorkflowCase;
use Illuminate\Support\Facades\DB;

/**
 * ارزیابی شرط‌های تعریف‌شده در جدول wf_conditions پکیج behin-simple-workflow.
 *
 * هر sequence flow خروجی از گیت‌وی شرطی می‌تواند به یکی از همین شرط‌ها
 * ارجاع داشته باشد (ستون condition_id در workflow_sequence_flows).
 *
 * ساختار شرط (ستون content): آرایه‌ای از
 *   { "fieldName": "...", "operation": "=", "value": "..." }
 * همه‌ی ردیف‌ها باید برقرار باشند تا شرط «درست» شود.
 */
class ConditionEvaluator
{
    /**
     * ارزیابی یک شرط برای یک پرونده.
     *
     * @return array{ok: bool, result?: bool, error?: string}
     */
    public static function evaluate($conditionId, WorkflowCase $case): array
    {
        if ($conditionId === null || $conditionId === '') {
            return ['ok' => false, 'error' => 'Sequence flow has no condition assigned.'];
        }

        $condition = DB::table('wf_conditions')->where('id', $conditionId)->first();

        if ($condition === null) {
            return ['ok' => false, 'error' => 'Condition not found: ' . $conditionId];
        }

        $rules = json_decode((string) $condition->content);

        if (! is_array($rules) && ! is_iterable($rules)) {
            $rules = [];
        }

        $variables = collect($case->variables());

        foreach ($rules as $rule) {
            $value = mb_convert_encoding((string) ($rule->value ?? ''), 'UTF-8');
            $matches = $variables
                ->where('key', $rule->fieldName ?? '')
                ->where('value', $rule->operation ?? '=', $value)
                ->first();

            if (! $matches) {
                return ['ok' => true, 'result' => false];
            }
        }

        return ['ok' => true, 'result' => true];
    }
}
