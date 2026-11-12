<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * المان شروع (bpmn:StartEvent).
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $event_definition
 * @property bool $is_interrupting
 */
class StartEvent extends BpmnElement
{
    protected $casts = [
        'is_interrupting' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), ['event_definition', 'is_interrupting']);
    }

    public function getTable(): string
    {
        return TableNames::startEvents();
    }
}