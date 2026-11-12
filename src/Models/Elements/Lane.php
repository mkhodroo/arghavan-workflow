<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * لین (bpmn:Lane)؛ داخل یک استخر یا لین دیگر قرار می‌گیرد.
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $parent_element_id
 */
class Lane extends BpmnElement
{
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), ['parent_element_id']);
    }

    public function getTable(): string
    {
        return TableNames::lanes();
    }
}