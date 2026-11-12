<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * جریان ترتیبی (bpmn:SequenceFlow)؛ یال ارتباط بین گره‌ها.
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $source_ref
 * @property string|null $target_ref
 * @property string|null $condition_expression
 * @property bool $is_default
 */
class SequenceFlow extends BpmnElement
{
    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), [
            'source_ref',
            'target_ref',
            'condition_expression',
            'is_default',
        ]);
    }

    public function getTable(): string
    {
        return TableNames::sequenceFlows();
    }
}