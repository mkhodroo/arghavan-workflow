<?php

namespace ArghavanWorkflow\Services;

use ArghavanWorkflow\Models\Elements\UserTask;
use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\BpmnXmlParser;
use ArghavanWorkflow\Support\Lang;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * ذخیره و خواندن تنظیمات «تسک کاربری».
 *
 * تنظیمات در جدول `workflow_user_tasks` (همان رکورد گره در جدول تسک‌های
 * کاربری) ذخیره می‌شود؛ دیگر درون XML نمودار نوشته نمی‌شود.
 */
class DiagramConfigStore
{
    /**
     * فیلدهای مجاز تنظیمات یک «تسک کاربری» (نام فیلدهای فرم).
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'name',
        'formId',
        'actor',
        'roleId',
        'assignment_type',
        'case_name',
        'duration',
        'order',
        'is_preview',
        'show_cancel_button',
        'color',
        'background',
    ];

    /**
     * نگاشت فیلدهای فرم به ستون‌های جدول تسک‌های کاربری.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'name' => 'name',
        'formId' => 'form_id',
        'actor' => 'actor',
        'roleId' => 'role_id',
        'assignment_type' => 'assignment_type',
        'case_name' => 'case_name',
        'duration' => 'duration',
        'order' => 'order',
        'is_preview' => 'is_preview',
        'show_cancel_button' => 'show_cancel_button',
        'color' => 'color',
        'background' => 'background',
    ];

    /**
     * ستون‌های بولی جدول تسک کاربری.
     *
     * @var array<int, string>
     */
    private const BOOLEAN_COLUMNS = ['is_preview', 'show_cancel_button'];

    public function __construct(private readonly DiagramRepository $diagrams)
    {
    }

    /**
     * تنظیمات یک تسک کاربری؛ اگر گره هنوز ذخیره نشده باشد فرم خالی
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

        $task = UserTask::query()
            ->where('process_id', $docId)
            ->where('element_id', $elementId)
            ->first();

        if ($task === null) {
            return array_fill_keys(self::FIELDS, '');
        }

        $data = [];

        foreach (self::COLUMNS as $field => $column) {
            $value = $task->getAttribute($column);

            if (is_bool($value)) {
                $data[$field] = $value ? '1' : '0';

                continue;
            }

            $data[$field] = $value === null ? '' : (string) $value;
        }

        return $data;
    }

    /**
     * ذخیره تنظیمات تسک؛ فقط فیلدهای مجاز (FIELDS) نوشته می‌شوند.
     *
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, message?: string, xml?: string}
     *
     * @throws InvalidArgumentException وقتی گره‌ی خواسته‌شده در نمودار نباشد
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

        /** @var UserTask $task */
        $task = UserTask::query()->firstOrNew([
            'process_id' => $docId,
            'element_id' => $elementId,
        ]);

        if (! $task->exists
            && ! in_array($elementId, BpmnXmlParser::userTaskIds((string) $process->xml), true)
        ) {
            throw new InvalidArgumentException(
                Lang::field('UserTask with ID :id was not found.', ['id' => $elementId])
            );
        }

        foreach (self::COLUMNS as $field => $column) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $task->setAttribute($column, $this->normalizeValue($column, $fields[$field]));
        }

        $task->process_id = $docId;
        $task->element_id = $elementId;
        $task->save();

        // نام گره روی بوم طراحی از روی همین مقدار نمایش داده می‌شود؛
        // بنابراین نام در XML هم به‌روزرسانی می‌شود.
        $xml = $this->renameElement((string) $process->xml, $elementId, (string) ($fields['name'] ?? ''));

        if ($xml !== null && $xml !== (string) $process->xml) {
            $process->xml = $xml;
            $process->save();
        }

        return ['ok' => true, 'xml' => (string) $process->xml];
    }

    /**
     * @param  mixed  $value
     * @return string|int|bool|null
     */
    private function normalizeValue(string $column, $value)
    {
        if (in_array($column, self::BOOLEAN_COLUMNS, true)) {
            return $this->toBool($value);
        }

        if ($column === 'order') {
            return $value === null || $value === '' ? null : (int) $value;
        }

        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  mixed  $value
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * به‌روزرسانی نام یک UserTask در XML نمودار.
     */
    private function renameElement(string $xml, string $elementId, string $name): ?string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $dom->documentElement === null) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('bpmn', BpmnXmlParser::BPMN_NS);

        $nodes = $xpath->query("//bpmn:userTask[@id = '".$elementId."']");

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        if ($name === '') {
            $node->removeAttribute('name');
        } else {
            $node->setAttribute('name', $name);
        }

        return $dom->saveXML();
    }
}