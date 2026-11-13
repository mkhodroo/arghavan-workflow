<?php

namespace ArghavanWorkflow\Models\Runtime;

use App\Models\User;
use ArghavanWorkflow\Models\Elements\UserTask;
use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * رکورد کارتابل؛ معادل wf_inbox پکیج behin-simple-workflow.
 *
 * وقتی روتینگ به یک «تسک کاربری» می‌رسد، برای هر کاربر تخصیص‌یافته
 * (کاربر مستقیم و/یا کاربرانِ دارای نقش) یک رکورد در این جدول ساخته می‌شود.
 *
 * @property string $id
 * @property int $task_id
 * @property string $case_id
 * @property int|null $actor
 * @property string $status
 * @property string|null $case_name
 */
class WorkflowInbox extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'workflow_inbox';

    protected $fillable = [
        'task_id',
        'case_id',
        'actor',
        'status',
        'case_name',
        'done_at',
        'done_by',
    ];

    protected $casts = [
        'done_at' => 'datetime',
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

    public function case(): BelongsTo
    {
        return $this->belongsTo(WorkflowCase::class, 'case_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(UserTask::class, 'task_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'opened', 'inProgress', 'draft']);
    }
}
