<?php

namespace ArghavanWorkflow\Services;

use ArghavanWorkflow\Models\Elements\SequenceFlow;
use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\BpmnXmlParser;
use ArghavanWorkflow\Support\Lang;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ذخیره و خواندن تنظیمات «جریان ترتیبی» (Sequence Flow).
 *
 * جریان‌های خروجی از گیت‌وی شرطی باید مشخص کنند کدام شرط (از جدول
 * wf_conditions پکیج behin-simple-workflow) بررسی شود؛ شناسه‌ی شرط در ستون
 * condition_id جدول workflow_sequence_flows ذخیره می‌شود.
 */
class DiagramFlowConfigStore
{
    /**
     * فیلدهای مجاز تنظیمات یک جریان ترتیبی.
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'name',
        'condition_id',
    ];

    /**
     * نگاشت فیلدهای فرم به ستون‌های جدول جریان‌های ترتیبی.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'name' => 'name',
        'condition_id' => 'condition_id',
    ];

    public function __construct(private readonly DiagramRepository $diagrams)
    {
    }

    /**
     * لیست شرط‌های موجود (wf_conditions) برای انتخاب در مودال.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getConditionsList(): array
    {
        return DB::table('wf_conditions')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) ($row->name ?: $row->id),
            ])
            ->values()
            ->all();
    }

    /**
     * تنظیمات یک جریان ترتیبی؛ اگر رکورد هنوز ذخیره نشده باشد فرم خالی
     * برگردانده می‌شود و اگر فرایند وجود نداشته باشد null.
     *
     * @return array<string, string>|null
     */
    public function find(string $docId, string $elementId): ?array
    {
        if (! DiagramRepository::isValidId($docId)) {
            return null;
        }

        $process = WorkflowProcess::query()->find($docId);

        if ($process === null) {
            return null;
        }

        $flow = SequenceFlow::query()
            ->where('process_id', $docId)
            ->where('element_id', $elementId)
            ->first();

        if ($flow === null) {
            return array_fill_keys(self::FIELDS, '');
        }

        $data = [];

        foreach (self::COLUMNS as $field => $column) {
            $value = $flow->getAttribute($column);
            $data[$field] = $value === null ? '' : (string) $value;
        }

        return $data;
    }

    /**
     * ذخیره تنظیمات جریان؛ فقط فیلدهای مجاز (FIELDS) نوشته می‌شوند.
     *
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, message?: string, xml?: string}
     *
     * @throws InvalidArgumentException وقتی جریان خواسته‌شده در نمودار نباشد
     */
    public function save(string $docId, string $elementId, array $fields): array
    {
        if (! DiagramRepository::isValidId($docId)) {
            return ['ok' => false, 'message' => Lang::field('Invalid diagram ID.')];
        }

        $process = WorkflowProcess::query()->find($docId);

        if ($process === null) {
            return ['ok' => false, 'message' => Lang::field('Diagram not found.')];
        }

        /** @var SequenceFlow $flow */
        $flow = SequenceFlow::query()->firstOrNew([
            'process_id' => $docId,
            'element_id' => $elementId,
        ]);

        if (! $flow->exists
            && ! in_array($elementId, BpmnXmlParser::sequenceFlowIds((string) $process->xml), true)
        ) {
            throw new InvalidArgumentException(
                Lang::field('SequenceFlow with ID :id was not found.', ['id' => $elementId])
            );
        }

        foreach (self::COLUMNS as $field => $column) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $flow->setAttribute($column, $this->normalizeValue($fields[$field]));
        }

        $flow->process_id = $docId;
        $flow->element_id = $elementId;
        $flow->save();

        return ['ok' => true, 'xml' => (string) $process->xml];
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    private function normalizeValue($value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
