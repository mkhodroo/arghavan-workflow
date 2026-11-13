<?php

namespace ArghavanWorkflow\Models\Runtime;

use ArghavanWorkflow\Models\WorkflowProcess;
use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * پرونده (کیس) فرایند؛ معادل wf_cases پکیج behin-simple-workflow.
 *
 * با استارت زدن یک فرایند یک رکورد در این جدول ساخته می‌شود و روتینگ
 * از المان شروعِ فرایند جلو می‌رود:
 *   - المان شروع: وضعیت پرونده «new» می‌شود
 *   - تسک کاربری: برای اکتورها رکورد در workflow_inbox ساخته می‌شود
 *   - اسکریپت تسک: فایل اجرایی اجرا می‌شود
 *   - المان پایان: وضعیت پرونده «done» می‌شود
 *
 * API این مدل (getVariable/saveVariable/variables) با API مدل Cases پکیج
 * behin-simple-workflow سازگار است تا اسکریپت‌های موجود (Behin\SimpleWorkflow\
 * Controllers\Scripts\*) بدون تغییر با همین پرونده کار کنند.
 *
 * @property string $id
 * @property string $process_id
 * @property int|null $number
 * @property string|null $name
 * @property int|null $creator
 * @property string $status
 */
class WorkflowCase extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'wf_cases';

    protected $fillable = [
        'process_id',
        'number',
        'name',
        'creator',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getConnectionName()
    {
        return $this->connection ?: TableNames::connection();
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(WorkflowProcess::class, 'process_id');
    }

    public function inboxes(): HasMany
    {
        return $this->hasMany(WorkflowInbox::class, 'case_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(WorkflowVariable::class, 'case_id');
    }

    /**
     * مقدار یک متغیر پرونده (سازگار با behin-simple-workflow).
     */
    public function getVariable($name)
    {
        return $this->variables()
            ->where('key', $name)
            ->value('value');
    }

    /**
     * ذخیره مقدار یک متغیر پرونده (سازگار با behin-simple-workflow).
     */
    public function saveVariable($name, $value)
    {
        return $this->variables()->updateOrCreate(
            ['key' => $name],
            ['value' => $value, 'process_id' => $this->process_id]
        );
    }

    public function scopeForProcess(Builder $query, string $processId): Builder
    {
        return $query->where('process_id', $processId);
    }
}
