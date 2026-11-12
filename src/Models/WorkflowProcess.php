<?php

namespace ArghavanWorkflow\Models;

use ArghavanWorkflow\Models\Elements\EndEvent;
use ArghavanWorkflow\Models\Elements\ExclusiveGateway;
use ArghavanWorkflow\Models\Elements\Lane;
use ArghavanWorkflow\Models\Elements\Pool;
use ArghavanWorkflow\Models\Elements\ScriptTask;
use ArghavanWorkflow\Models\Elements\SequenceFlow;
use ArghavanWorkflow\Models\Elements\StartEvent;
use ArghavanWorkflow\Models\Elements\UserTask;
use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فرایند (نمودار BPMN)؛ هر نمودار یک رکورد در جدول فرایندها دارد.
 *
 * المان‌ها/گره‌های هر فرایند در جدول‌های مخصوص نوع خودشان ذخیره می‌شوند و
 * از طریق رابطه‌های زیر در دسترس‌اند.
 *
 * @property string $id
 * @property string $name
 * @property string|null $process_key
 * @property string|null $description
 * @property string $xml
 * @property array<string, mixed>|null $meta
 */
class WorkflowProcess extends Model
{
    /**
     * شناسه‌ی فرایند رشته‌ای است و توسط پکیج ساخته می‌شود.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getTable(): string
    {
        return TableNames::processes();
    }

    public function getConnectionName()
    {
        return $this->connection ?: TableNames::connection();
    }

    public function startEvents(): HasMany
    {
        return $this->hasMany(StartEvent::class, 'process_id');
    }

    public function endEvents(): HasMany
    {
        return $this->hasMany(EndEvent::class, 'process_id');
    }

    public function userTasks(): HasMany
    {
        return $this->hasMany(UserTask::class, 'process_id');
    }

    public function scriptTasks(): HasMany
    {
        return $this->hasMany(ScriptTask::class, 'process_id');
    }

    public function exclusiveGateways(): HasMany
    {
        return $this->hasMany(ExclusiveGateway::class, 'process_id');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class, 'process_id');
    }

    public function lanes(): HasMany
    {
        return $this->hasMany(Lane::class, 'process_id');
    }

    public function sequenceFlows(): HasMany
    {
        return $this->hasMany(SequenceFlow::class, 'process_id');
    }
}