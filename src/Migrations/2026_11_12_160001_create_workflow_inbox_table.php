<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول کارتابل (معادل wf_inbox پکیج behin-simple-workflow).
 *
 * وقتی روتینگ به یک «تسک کاربری» می‌رسد، برای تک‌تک کاربران تخصیص‌یافته‌ی آن
 * (کاربر مستقیم و/یا همه‌ی کاربرانِ دارای نقش) یک رکورد در این جدول ساخته
 * می‌شود. task_id به رکورد تسک کاربری در workflow_user_tasks ارجاع دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->create(TableNames::inbox(), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('task_id');
            $table->uuid('case_id');
            $table->foreignId('actor')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['new', 'opened', 'inProgress', 'done', 'canceled', 'draft', 'doneByOther', 'doneBySystem'])->default('new');
            $table->string('case_name')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('task_id')
                ->references('id')
                ->on(TableNames::userTasks())
                ->cascadeOnDelete();
            $table->foreign('case_id')
                ->references('id')
                ->on(TableNames::cases())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->dropIfExists(TableNames::inbox());
    }
};
