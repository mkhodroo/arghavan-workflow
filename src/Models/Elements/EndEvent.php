<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * المان پایان (bpmn:EndEvent).
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $event_definition
 * @property bool $is_interrupting
 */
class EndEvent extends BpmnElement
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
        return TableNames::endEvents();
    }
}