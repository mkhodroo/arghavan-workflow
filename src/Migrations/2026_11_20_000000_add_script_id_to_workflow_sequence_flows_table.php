<?php

use ArghavanWorkflow\Support\TableNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اضافه کردن script_id به جریان‌های ترتیبی.
 *
 * هر sequence flow خروجی از گیت‌وی شرطی می‌تواند به یک اسکریپت در جدول
 * wf_scripts پکیج behin-simple-workflow ارجاع داشته باشد؛ روتینگ هنگام
 * رسیدن به گیت‌وی شرطی، اسکریپتِ جریان‌های خروجی را اجرا می‌کند و اولین
 * مسیری که اسکریپت آن true برمی‌گرداند، طی می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::sequenceFlows(), function (Blueprint $table) {
            $table->uuid('script_id')->nullable()->after('condition_id');

            // اسکریپت‌ها در جدول wf_scripts پکیج behin-simple-workflow تعریف می‌شوند
            $table->foreign('script_id')
                ->references('id')
                ->on('wf_scripts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(TableNames::connection())->table(TableNames::sequenceFlows(), function (Blueprint $table) {
            $table->dropForeign(['script_id']);
            $table->dropColumn('script_id');
        });
    }
};