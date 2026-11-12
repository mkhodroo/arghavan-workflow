<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Support\TableNames;

/**
 * تسک کاربری (bpmn:UserTask) همراه با تنظیمات گردش کار و ظاهر آن.
 *
 * تنظیمات (فرم، اکتور، نقش، نوع ارجاع، مدت، رنگ و ...) در همین جدول ذخیره
 * می‌شود؛ دیگر درون XML نمودار نوشته نمی‌شود.
 *
 * @property string $element_id
 * @property string|null $name
 * @property string|null $form_id
 * @property string|null $actor
 * @property string|null $role_id
 * @property string|null $assignment_type
 * @property string|null $case_name
 * @property string|null $duration
 * @property int|null $order
 * @property bool $is_preview
 * @property bool $show_cancel_button
 * @property string|null $color
 * @property string|null $background
 */
class UserTask extends BpmnElement
{
    protected $casts = [
        'order' => 'integer',
        'is_preview' => 'boolean',
        'show_cancel_button' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_preview' => false,
        'show_cancel_button' => true,
    ];

    public function getTable(): string
    {
        return TableNames::userTasks();
    }
}