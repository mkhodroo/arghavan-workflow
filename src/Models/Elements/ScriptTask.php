<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * اسکریپت تسک (bpmn:ScriptTask).
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $script_id
 */
class ScriptTask extends BpmnElement
{
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), ['script_id']);
    }

    public function getTable(): string
    {
        return TableNames::scriptTasks();
    }
}