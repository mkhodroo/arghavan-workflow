<?php

namespace ArghavanWorkflow\Services;

use ArghavanWorkflow\Models\Elements\ScriptTask;
use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\BpmnXmlParser;
use ArghavanWorkflow\Support\Lang;
use Behin\SimpleWorkflow\Models\Core\Script;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * ذخیره و خواندن تنظیمات «اسکریپت تسک».
 *
 * تنظیمات در جدول `workflow_script_tasks` ذخیره می‌شود (همان رکورد گره در
 * جدول اسکریپت تسک‌ها)؛ دیگر درون XML نمودار نوشته نمی‌شود.
 *
 * تنظیمات بر اساس پکیج behin-simple-workflow:
 * - نام تسک
 * - انتخاب اسکریپت از لیست اسکریپت‌های تعریف شده در wf_scripts (script_id)
 */
class DiagramScriptConfigStore
{
    /**
     * فیلدهای مجاز تنظیمات یک «اسکریپت تسک» (نام فیلدهای فرم).
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'name',
        'script_id',
    ];

    /**
     * نگاشت فیلدهای فرم به ستون‌های جدول اسکریپت تسک‌ها.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'name' => 'name',
        'script_id' => 'script_id',
    ];

    public function __construct(private readonly DiagramRepository $diagrams)
    {
    }

    /**
     * تنظیمات یک اسکریپت تسک؛ اگر گره هنوز ذخیره نشده باشد فرم خالی
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

        $task = ScriptTask::query()
            ->where('process_id', $docId)
            ->where('element_id', $elementId)
            ->first();

        if ($task === null) {
            return array_fill_keys(self::FIELDS, '');
        }

        $data = [];

        foreach (self::COLUMNS as $field => $column) {
            $value = $task->getAttribute($column);
            $data[$field] = $value === null ? '' : (string) $value;
        }

        return $data;
    }

    /**
     * لیست اسکریپت‌های موجود برای انتخاب در مودال.
     *
     * @return array<int, array{id: string, name: string, executive_file: string|null, content: string|null}>
     */
    public function getScriptsList(): array
    {
        return Script::query()
            ->orderBy('name')
            ->get(['id', 'name', 'executive_file', 'content'])
            ->toArray();
    }

    /**
     * ذخیره تنظیمات اسکریپت تسک؛ فقط فیلدهای مجاز (FIELDS) نوشته می‌شوند.
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

        /** @var ScriptTask $task */
        $task = ScriptTask::query()->firstOrNew([
            'process_id' => $docId,
            'element_id' => $elementId,
        ]);

        if (! $task->exists
            && ! in_array($elementId, BpmnXmlParser::scriptTaskIds((string) $process->xml), true)
        ) {
            throw new InvalidArgumentException(
                Lang::field('ScriptTask with ID :id was not found.', ['id' => $elementId])
            );
        }

        foreach (self::COLUMNS as $field => $column) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $task->setAttribute($column, $this->normalizeValue($fields[$field]));
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
     * @return string|null
     */
    private function normalizeValue($value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * به‌روزرسانی نام یک ScriptTask در XML نمودار.
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

        $nodes = $xpath->query("//bpmn:scriptTask[@id = '".$elementId."']");

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
