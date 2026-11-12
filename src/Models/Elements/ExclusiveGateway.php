<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * گیت‌وی شرطی (bpmn:ExclusiveGateway).
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $default_flow
 * @property string|null $direction
 */
class ExclusiveGateway extends BpmnElement
{
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public static function structuralColumns(): array
    {
        return array_merge(parent::structuralColumns(), ['default_flow', 'direction']);
    }

    public function getTable(): string
    {
        return TableNames::exclusiveGateways();
    }
}