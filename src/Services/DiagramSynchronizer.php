<?php

namespace ArghavanWorkflow\Services;

use ArghavanWorkflow\Models\Elements\BpmnElement;
use ArghavanWorkflow\Models\Elements\EndEvent;
use ArghavanWorkflow\Models\Elements\ExclusiveGateway;
use ArghavanWorkflow\Models\Elements\Lane;
use ArghavanWorkflow\Models\Elements\Pool;
use ArghavanWorkflow\Models\Elements\ScriptTask;
use ArghavanWorkflow\Models\Elements\SequenceFlow;
use ArghavanWorkflow\Models\Elements\StartEvent;
use ArghavanWorkflow\Models\Elements\UserTask;
use ArghavanWorkflow\Models\WorkflowProcess;

/**
 * همگام‌سازی جداول المان‌ها با XML نمودار.
 *
 * هنگام ذخیره‌ی نمودار، برای هر گره/المان یک رکورد در جدول نوع خودش
 * ساخته یا به‌روزرسانی می‌شود و رکوردهای حذف‌شده از نمودار پاک می‌شوند.
 *
 * فقط ستون‌های ساختاری (BpmnElement::structuralColumns) نوشته می‌شوند تا
 * تنظیمات دستی تسک‌های کاربری (فرم، اکتور، رنگ و ...) با ذخیره‌ی نمودار
 * از بین نرود.
 */
class DiagramSynchronizer
{
    /**
     * نگاشت کلید خروجی BpmnXmlParser به مدل جدول مربوطه.
     *
     * @var array<string, class-string<BpmnElement>>
     */
    public const ELEMENT_MODELS = [
        'start_events' => StartEvent::class,
        'end_events' => EndEvent::class,
        'user_tasks' => UserTask::class,
        'script_tasks' => ScriptTask::class,
        'exclusive_gateways' => ExclusiveGateway::class,
        'pools' => Pool::class,
        'lanes' => Lane::class,
        'sequence_flows' => SequenceFlow::class,
    ];

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $parsed  خروجی BpmnXmlParser::parse
     */
    public function sync(WorkflowProcess $process, array $parsed): void
    {
        foreach (self::ELEMENT_MODELS as $key => $modelClass) {
            $this->syncElements($process, $modelClass, $parsed[$key] ?? []);
        }
    }

    /**
     * حذف همه‌ی المان‌های یک فرایند (پیش از حذف خود فرایند).
     */
    public function purge(WorkflowProcess $process): void
    {
        foreach (self::ELEMENT_MODELS as $modelClass) {
            $modelClass::query()->where('process_id', $process->id)->delete();
        }
    }

    /**
     * @param  class-string<BpmnElement>  $modelClass
     * @param  array<int, array<string, mixed>>  $elements
     */
    private function syncElements(WorkflowProcess $process, string $modelClass, array $elements): void
    {
        $columns = array_flip($modelClass::structuralColumns());
        $elementIds = [];

        foreach ($elements as $element) {
            $elementId = (string) ($element['element_id'] ?? '');

            if ($elementId === '') {
                continue;
            }

            $elementIds[] = $elementId;

            /** @var BpmnElement $model */
            $model = $modelClass::query()->firstOrNew([
                'process_id' => $process->id,
                'element_id' => $elementId,
            ]);

            $model->fill(array_intersect_key($element, $columns));
            $model->process_id = $process->id;
            $model->element_id = $elementId;
            $model->save();
        }

        $query = $modelClass::query()->where('process_id', $process->id);

        if ($elementIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('element_id', $elementIds)->delete();
    }
}