<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * استخر (bpmn:Participant).
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $process_ref
 */
class Pool extends BpmnElement
{
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), ['process_ref']);
    }

    public function getTable(): string
    {
        return TableNames::pools();
    }
}