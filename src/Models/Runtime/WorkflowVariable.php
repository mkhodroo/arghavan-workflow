<?php

namespace ArghavanWorkflow\Models\Runtime;

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * متغیر پرونده؛ معادل wf_variables پکیج behin-simple-workflow.
 *
 * ستون‌های key و value آن با API شرط‌ها و اسکریپت‌های behin سازگار است
 * (جمع‌آوری با collect()->where('key', ...)->where('value', op, value)).
 *
 * @property int $id
 * @property string $process_id
 * @property string $case_id
 * @property string $key
 * @property string|null $value
 */
class WorkflowVariable extends Model
{
    protected $table = 'workflow_variables';

    protected $fillable = [
        'process_id',
        'case_id',
        'key',
        'value',
    ];

    public function getConnectionName()
    {
        return $this->connection ?: TableNames::connection();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(WorkflowCase::class, 'case_id');
    }
}
