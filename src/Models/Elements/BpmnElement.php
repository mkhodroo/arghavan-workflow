<?php

namespace ArghavanWorkflow\Models\Elements;

use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مدل پایه‌ی همه‌ی المان‌ها (گره‌های) نمودار BPMN.
 *
 * هر نوع المان جدول مخصوص خودش را دارد؛ ستون‌های مشترک:
 *   process_id، element_id، name، documentation، sort_order
 */
abstract class BpmnElement extends Model
{
    protected $guarded = [];

    /**
     * ستون‌هایی که از XML خوانده و در هر ذخیره‌سازی همگام می‌شوند.
     *
     * تنظیمات دستی (مثل فرم/اکتور تسک) در این فهرست نیستند تا با ذخیره‌ی
     * نمودار پاک نشوند.
     *
     * @return array<int, string>
     */
    public static function structuralColumns(): array
    {
        return ['name', 'documentation', 'sort_order'];
    }

    public function getConnectionName()
    {
        return $this->connection ?: TableNames::connection();
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(WorkflowProcess::class, 'process_id');
    }

    public function scopeForProcess(Builder $query, string $processId): Builder
    {
        return $query->where('process_id', $processId);
    }
}