<?php
namespace ArghavanWorkflow\Support;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
/**
 * تجزیه‌ی XML نمودار BPMN به ساختار داده‌ای قابل ذخیره در جداول دیتابیس.
 *
 * هر «فرایند» یک رکورد در جدول فرایندها و هر «المان/گره» یک رکورد در جدول
 * مخصوص نوع خودش می‌سازد:
 *   المان شروع، المان پایان، تسک کاربری، اسکریپت تسک، گیت‌وی شرطی،
 *   استخر (Pool)، لین (Lane) و جریان ترتیبی (Sequence Flow).
 *
 * این کلاس هیچ وابستگی‌ای به لاراول ندارد تا مستقل هم قابل تست باشد.
 */
class BpmnXmlParser
{
    public const BPMN_NS = 'http://www.omg.org/spec/BPMN/20100524/MODEL';
    /**
     * نام‌فضای تنظیمات تسک در نسخه‌های قبلی پکیج (درون extensionElements).
     * فقط برای انتقال داده‌های قدیمی خوانده می‌شود.
     */
    public const CONFIG_NS = 'http://arghavan-workflow.local/config';
    /**
     * نگاشت فیلدهای تنظیمات (camelCase در XML قدیمی) به ستون‌های جدول تسک کاربری.
     *
     * @var array<string, string>
     */
    public const CONFIG_FIELDS = [
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
     * تجزیه‌ی کامل XML نمودار.
     *
     * @return array{
     *     processes: array<int, array<string, mixed>>,
     *     start_events: array<int, array<string, mixed>>,
     *     end_events: array<int, array<string, mixed>>,
     *     user_tasks: array<int, array<string, mixed>>,
     *     script_tasks: array<int, array<string, mixed>>,
     *     exclusive_gateways: array<int, array<string, mixed>>,
     *     pools: array<int, array<string, mixed>>,
     *     lanes: array<int, array<string, mixed>>,
     *     sequence_flows: array<int, array<string, mixed>>
     * }
     *
     * @throws InvalidArgumentException وقتی XML معتبر نباشد
     */
    public static function parse(string $xml): array
    {
        $xpath = self::xpath($xml);
        $defaultFlows = [];
        foreach (self::query($xpath, '//*[@default]') as $node) {
            $defaultFlows[$node->getAttribute('default')] = true;
        }
        $result = [
            'processes' => [],
            'start_events' => [],
            'end_events' => [],
            'user_tasks' => [],
            'script_tasks' => [],
            'exclusive_gateways' => [],
            'pools' => [],
            'lanes' => [],
            'sequence_flows' => [],
        ];
        foreach (self::query($xpath, '//bpmn:process') as $index => $element) {
            $result['processes'][] = [
                'element_id' => $element->getAttribute('id'),
                'name' => self::attribute($element, 'name'),
                'is_executable' => self::toBool($element->getAttribute('isExecutable')),
            ];
        }
        foreach (self::query($xpath, '//bpmn:startEvent') as $index => $element) {
            $result['start_events'][] = self::base($element, $index) + [
                'event_definition' => self::eventDefinition($element),
                'is_interrupting' => self::toBool($element->getAttribute('isInterrupting'), true),
            ];
        }
        foreach (self::query($xpath, '//bpmn:endEvent') as $index => $element) {
            $result['end_events'][] = self::base($element, $index) + [
                'event_definition' => self::eventDefinition($element),
                'is_interrupting' => self::toBool($element->getAttribute('isInterrupting'), true),
            ];
        }
        foreach (self::query($xpath, '//bpmn:userTask') as $index => $element) {
            $row = self::base($element, $index);
            $config = self::taskConfig($element);
            foreach (self::CONFIG_FIELDS as $field => $column) {
                $row[$column] = $config[$field] ?? null;
            }
            $row['is_preview'] = self::toBool($row['is_preview']);
            $row['show_cancel_button'] = self::toBool($row['show_cancel_button'], true);
            if (($row['name'] ?? '') === '' && isset($config['name'])) {
                $row['name'] = $config['name'];
            }
            $result['user_tasks'][] = $row;
        }
        foreach (self::query($xpath, '//bpmn:scriptTask') as $index => $element) {
            $result['script_tasks'][] = self::base($element, $index) + [
                'script_format' => self::attribute($element, 'scriptFormat'),
                'script' => self::childText($element, 'script'),
            ];
        }
        foreach (self::query($xpath, '//bpmn:exclusiveGateway') as $index => $element) {
            $result['exclusive_gateways'][] = self::base($element, $index) + [
                'default_flow' => self::attribute($element, 'default'),
                'direction' => self::attribute($element, 'gatewayDirection'),
            ];
        }
        foreach (self::query($xpath, '//bpmn:participant') as $index => $element) {
            $result['pools'][] = self::base($element, $index) + [
                'process_ref' => self::attribute($element, 'processRef'),
            ];
        }
        // نگاشت شناسه‌ی پردازه به استخری که به آن ارجاع می‌دهد؛ برای یافتن
        // استخر والد لین‌هایی که در laneSet همان پردازه تعریف شده‌اند.
        $poolByProcess = [];
        foreach ($result['pools'] as $pool) {
            if (! empty($pool['process_ref'])) {
                $poolByProcess[(string) $pool['process_ref']] = (string) $pool['element_id'];
            }
        }
        foreach (self::query($xpath, '//bpmn:lane') as $index => $element) {
            $result['lanes'][] = self::base($element, $index) + [
                'parent_element_id' => self::parentContainerId($element, $poolByProcess),
            ];
        }
        foreach (self::query($xpath, '//bpmn:sequenceFlow') as $index => $element) {
            $id = $element->getAttribute('id');
            $result['sequence_flows'][] = self::base($element, $index) + [
                'source_ref' => self::attribute($element, 'sourceRef'),
                'target_ref' => self::attribute($element, 'targetRef'),
                'condition_expression' => self::childText($element, 'conditionExpression'),
                'is_default' => isset($defaultFlows[$id]),
            ];
        }
        return $result;
    }
    /**
     * شناسه‌ی همه‌ی «تسک کاربری»های موجود در XML.
     *
     * @return array<int, string>
     */
    public static function userTaskIds(string $xml): array
    {
        try {
            $parsed = self::parse($xml);
        } catch (InvalidArgumentException) {
            return [];
        }
        $ids = [];
        foreach ($parsed['user_tasks'] as $task) {
            $id = (string) ($task['element_id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return $ids;
    }
    /**
     * شناسه‌ی همه‌ی اسکریپت تسک‌های نمودار.
     *
     * @return array<int, string>
     */
    public static function scriptTaskIds(string $xml): array
    {
        try {
            $parsed = self::parse($xml);
        } catch (InvalidArgumentException) {
            return [];
        }
        $ids = [];
        foreach ($parsed['script_tasks'] as $task) {
            $id = (string) ($task['element_id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return $ids;
    }
private static function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded || $dom->documentElement === null) {
            throw new InvalidArgumentException('Diagram XML is not valid.');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('bpmn', self::BPMN_NS);
        return $xpath;
    }
    /**
     * @return array<int, DOMElement>
     */
    private static function query(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false) {
            return [];
        }
        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }
        return $elements;
    }
    /**
     * ستون‌های مشترک همه‌ی المان‌ها.
     *
     * @return array<string, mixed>
     */
    private static function base(DOMElement $element, int $index): array
    {
        return [
            'element_id' => $element->getAttribute('id'),
            'name' => self::attribute($element, 'name'),
            'documentation' => self::childText($element, 'documentation'),
            'sort_order' => $index,
        ];
    }
    private static function attribute(DOMElement $element, string $name): ?string
    {
        $value = trim($element->getAttribute($name));
        return $value === '' ? null : $value;
    }
    private static function childText(DOMElement $element, string $localName): ?string
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement
                && $child->localName === $localName
                && $child->namespaceURI === self::BPMN_NS
            ) {
                $text = trim((string) $child->textContent);
                return $text === '' ? null : $text;
            }
        }
        return null;
    }
    /**
     * نوع رویداد (message / timer / signal / error / terminate / ...).
     */
    private static function eventDefinition(DOMElement $event): ?string
    {
        foreach ($event->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $localName = (string) $child->localName;
            if (str_ends_with($localName, 'EventDefinition')) {
                $name = substr($localName, 0, -strlen('EventDefinition'));
                return $name === '' ? null : strtolower($name);
            }
        }
        return null;
    }
    /**
     * شناسه‌ی استخر یا لین والد (برای جدول لین‌ها).
     *
     * لین‌ها در BPMN 2.0 درون laneSet همان «پردازه» تعریف می‌شوند، نه داخل
     * استخر؛ بنابراین اگر لین والد پیدا نشود، استخری که به آن پردازه ارجاع
     * می‌دهد به‌عنوان والد در نظر گرفته می‌شود.
     *
     * @param  array<string, string>  $poolByProcess  نگاشت processRef => شناسه استخر
     */
    private static function parentContainerId(DOMElement $element, array $poolByProcess = []): ?string
    {
        $node = $element->parentNode;
        $processId = null;
        while ($node instanceof DOMElement) {
            if ($node->localName === 'lane') {
                $id = trim($node->getAttribute('id'));
                return $id === '' ? null : $id;
            }
            if ($node->localName === 'process' && $processId === null) {
                $processId = trim($node->getAttribute('id'));
            }
            $node = $node->parentNode;
        }
        if ($processId !== null && $processId !== '' && isset($poolByProcess[$processId])) {
            return $poolByProcess[$processId];
        }
        return null;
    }
    /**
     * تنظیمات تسک کاربری که در نسخه‌های قبلی پکیج درون extensionElements ذخیره می‌شد.
     *
     * @return array<string, string>
     */
    private static function taskConfig(DOMElement $userTask): array
    {
        $config = [];
        foreach ($userTask->childNodes as $extension) {
            if (! $extension instanceof DOMElement
                || $extension->localName !== 'extensionElements'
                || $extension->namespaceURI !== self::BPMN_NS
            ) {
                continue;
            }
            foreach ($extension->childNodes as $child) {
                if (! $child instanceof DOMElement
                    || $child->localName !== 'config'
                    || $child->namespaceURI !== self::CONFIG_NS
                ) {
                    continue;
                }
                foreach ($child->childNodes as $field) {
                    if (! $field instanceof DOMElement) {
                        continue;
                    }
                    $config[$field->localName] = trim((string) $field->textContent);
                }
            }
        }
        return $config;
    }
    private static function toBool(?string $value, bool $default = false): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }
}
