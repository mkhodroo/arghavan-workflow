<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اضافه کردن script_id به جدول اسکریپت تسک‌ها برای ارجاع به اسکریپت‌های behin-simple-workflow (wf_scripts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::scriptTasks(), function (Blueprint $table) {
            $table->uuid('script_id')->nullable()->after('name');

            // Foreign key به جدول wf_scripts پکیج behin-simple-workflow
            // نکته: چون wf_scripts در کانکشن پیش‌فرض لاراول است، foreign key از آن کانکشن ارجاع می‌دهد.
            $table->foreign('script_id')
                ->references('id')
                ->on('wf_scripts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::scriptTasks(), function (Blueprint $table) {
            $table->dropForeign(['script_id']);
            $table->dropColumn('script_id');
        });
    }
};